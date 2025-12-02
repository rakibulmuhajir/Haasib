<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { parse } from '@/palette/parser'
import { generateSuggestions } from '@/palette/autocomplete'
import { formatTable } from '@/palette/table'
import { getHelp } from '@/palette/help'
import { formatText } from '@/palette/formatter'
import { getCommandExample } from '@/palette/grammar'
import { getQuickActions, resolveQuickActionCommand, getQuickActionLabel } from '@/palette/quick-actions'
import { usePage } from '@inertiajs/vue3'
import type { ParsedCommand, Suggestion, QuickAction, TableState } from '@/types/palette'

const props = defineProps<{ visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [v: boolean] }>()

// State
const input = ref('')
const output = ref<OutputLine[]>([])
const history = ref<string[]>(loadHistory())
const historyIndex = ref(-1)
const executing = ref(false)
const suggestions = ref<Suggestion[]>([])
const suggestionIndex = ref(0)
const showSuggestions = ref(false)
const awaitingConfirmation = ref<{
  original: string
  parsed: ParsedCommand
  message: string
} | null>(null)

// Quick actions state
const tableState = ref<TableState | null>(null)
const quickActions = ref<QuickAction[]>([])
const showSubPrompt = ref(false)
const subPromptAction = ref<QuickAction | null>(null)
const subPromptInput = ref('')
const pendingRefreshEntity = ref<string | null>(null)

// Parsed command (reactive)
const parsed = computed(() => parse(input.value))

// Parsed display helpers
const hasFlags = computed(() => Object.keys(parsed.value.flags).length > 0)
const flagEntries = computed(() => Object.entries(parsed.value.flags))

// Inline placeholder hint (ghosted text showing example)
const placeholderHint = computed(() => {
  // Don't show if suggestions are visible
  if (showSuggestions.value) return ''

  // Don't show if executing
  if (executing.value) return ''

  // Don't show if awaiting confirmation
  if (awaitingConfirmation.value) return ''

  const trimmed = input.value.trim()
  const parsedCmd = parse(trimmed)

  // Only show if we have entity + verb
  if (!parsedCmd.entity || !parsedCmd.verb) return ''

  const example = getCommandExample(parsedCmd.entity, parsedCmd.verb)

  // Only show the remaining part (what user hasn't typed yet)
  if (example && example.startsWith(trimmed)) {
    return example.slice(trimmed.length)
  }

  return ''
})

// Width of typed text for positioning ghost text
const typedTextWidth = ref('0px')

// Dynamic suggestions fetch timeout
const fetchTimeout = ref<number | null>(null)

// Refs
const inputEl = ref<HTMLInputElement>()
const outputEl = ref<HTMLDivElement>()
const subPromptInputEl = ref<HTMLInputElement>()

// Company context
const page = usePage()
const initialCompany = computed(() => (page.props.auth as any)?.currentCompany)
const activeCompany = ref(initialCompany.value || null)
const companySlug = computed(() => activeCompany.value?.slug || '')

interface OutputLine {
  type: 'input' | 'output' | 'error' | 'success' | 'table'
  content: string | string[][]
  headers?: string[]
  footer?: string
}

// Focus on open
watch(() => props.visible, (v) => {
  if (v) {
    nextTick(() => inputEl.value?.focus())
    document.addEventListener('keydown', handleKeydown)
  } else {
    showSuggestions.value = false
    historyIndex.value = -1
    document.removeEventListener('keydown', handleKeydown)
  }
})

// Auto-scroll output
watch(() => output.value.length, () => {
  nextTick(() => {
    if (outputEl.value) outputEl.value.scrollTop = outputEl.value.scrollHeight
  })
})

// Update suggestions on input
watch(input, async (val) => {
  const trimmed = val.trim()

  // Calculate text width for ghost text positioning
  if (inputEl.value) {
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    if (ctx) {
      const style = window.getComputedStyle(inputEl.value)
      ctx.font = style.font
      const width = ctx.measureText(val).width
      typedTextWidth.value = `${width}px`
    }
  }

  if (!trimmed) {
    showSuggestions.value = false
    return
  }

  const words = trimmed.split(/\s+/)
  const parsedCmd = parse(trimmed)

  // Special case: If we have entity + verb and input ends with space,
  // show placeholder hint instead of suggestions
  if (words.length === 2 && val.endsWith(' ') && parsedCmd.entity && parsedCmd.verb) {
    showSuggestions.value = false
    return
  }

  // If less than 3 words, use static grammar-based suggestions
  if (words.length < 3) {
    suggestions.value = generateSuggestions(val)
    showSuggestions.value = suggestions.value.length > 0
    suggestionIndex.value = 0
    return
  }

  // 3+ words: fetch dynamic suggestions
  if (!parsedCmd.entity || !parsedCmd.verb) {
    suggestions.value = generateSuggestions(val)
    showSuggestions.value = suggestions.value.length > 0
    suggestionIndex.value = 0
    return
  }

  // Extract partial value (everything after entity and verb)
  const partialValue = words.slice(2).join(' ')

  // Fetch dynamic suggestions
  await fetchDynamicSuggestions(parsedCmd.entity, parsedCmd.verb, partialValue)
})

// Watch output for table data to populate tableState
watch(output, (newOutput) => {
  const lastTable = newOutput.slice().reverse().find(line => line.type === 'table')

  if (!lastTable) {
    tableState.value = null
    return
  }

  // Get the last command that was executed
  const lastInput = newOutput.slice().reverse().find(line => line.type === 'input')
  if (!lastInput) {
    return
  }

  // Parse the command to get entity and verb
  const cmd = String(lastInput.content).replace('❯ ', '')
  const parsedCmd = parse(cmd)

  if (!parsedCmd.entity || !parsedCmd.verb) {
    return
  }

  // Populate tableState
  tableState.value = {
    headers: lastTable.headers || [],
    rows: lastTable.content as string[][],
    selectedRowIndex: 0,
    entity: parsedCmd.entity,
    verb: parsedCmd.verb,
  }
}, { deep: true })

// Watch tableState to update quickActions
watch(tableState, (newState) => {
  if (!newState) {
    quickActions.value = []
    return
  }

  const actions = getQuickActions(newState.entity, newState.verb)
  quickActions.value = actions
})

function close() {
  emit('update:visible', false)
  input.value = ''
  if (fetchTimeout.value) clearTimeout(fetchTimeout.value)
}

/**
 * Fetch dynamic suggestions from backend
 */
async function fetchDynamicSuggestions(
  entity: string,
  verb: string,
  partial: string
) {
  // Clear existing timeout
  if (fetchTimeout.value) clearTimeout(fetchTimeout.value)

  // Debounce to avoid excessive requests
  fetchTimeout.value = setTimeout(async () => {
    try {
      const params = new URLSearchParams({
        entity,
        verb,
        q: partial,
      })

      const res = await fetch(`/api/palette/suggestions?${params}`, {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })

      if (!res.ok) {
        // Fallback to static suggestions on error
        suggestions.value = generateSuggestions(input.value)
        showSuggestions.value = suggestions.value.length > 0
        return
      }

      const data = await res.json()

      // Merge dynamic suggestions with static ones
      const dynamicSuggestions = (data.suggestions || []).map((s: any) => ({
        type: 'value' as const,
        value: `${entity} ${verb} ${s.value}`,
        label: s.label,
        description: s.description,
        icon: s.icon,
      }))

      const staticSuggestions = generateSuggestions(input.value)

      // Combine and deduplicate
      suggestions.value = [...dynamicSuggestions, ...staticSuggestions].slice(0, 8)
      showSuggestions.value = suggestions.value.length > 0
      suggestionIndex.value = 0

    } catch (e) {
      console.error('Failed to fetch palette suggestions:', e)
      // Fallback to static suggestions
      suggestions.value = generateSuggestions(input.value)
      showSuggestions.value = suggestions.value.length > 0
    }
  }, 300) // 300ms debounce
}

function addOutput(type: OutputLine['type'], content: string | string[][], headers?: string[], footer?: string) {
  output.value.push({ type, content, headers, footer })
  // Keep max 200 lines
  if (output.value.length > 200) {
    output.value = output.value.slice(-200)
  }
}

// Destructive actions requiring confirmation
const DESTRUCTIVE_ACTIONS = [
  'company.delete',
  'user.delete',
  'role.delete',
  'user.deactivate',
]

async function execute() {
  const cmd = input.value.trim()
  if (!cmd || executing.value) return

  showSuggestions.value = false

  // Check if we're awaiting confirmation
  if (awaitingConfirmation.value) {
    if (cmd.toLowerCase() === 'yes') {
      // User confirmed - execute the original command
      const { parsed } = awaitingConfirmation.value
      awaitingConfirmation.value = null
      await executeCommand(parsed)
    } else {
      // User cancelled
      addOutput('output', '{secondary}Cancelled{/}')
      awaitingConfirmation.value = null
    }
    input.value = ''
    focusInput()
    return
  }

  // Handle built-in commands
  if (cmd === 'clear' || cmd === 'cls') {
    output.value = []
    input.value = ''
    focusInput()
    return
  }

  if (cmd === 'help' || cmd.startsWith('help ')) {
    const topic = cmd.slice(5).trim() || undefined
    const helpText = getHelp(topic)
    addOutput('output', helpText)
    addToHistory(cmd)
    input.value = ''
    focusInput()
    return
  }

  // Parse command
  const parsed = parse(cmd)

  if (parsed.errors.length > 0) {
    addOutput('input', `❯ ${cmd}`)
    addOutput('error', `{error}✗{/} ${parsed.errors.join(', ')}`)
    input.value = ''
    focusInput()
    return
  }

  if (!parsed.entity || !parsed.verb) {
    addOutput('input', `❯ ${cmd}`)
    addOutput('error', `{error}✗{/} Unknown command. Type 'help' for available commands.`)
    input.value = ''
    focusInput()
    return
  }

  // Check if destructive
  const actionKey = `${parsed.entity}.${parsed.verb}`
  if (DESTRUCTIVE_ACTIONS.includes(actionKey)) {
    // Show confirmation prompt
    const entityName = String(parsed.flags.slug || parsed.flags.email || parsed.flags.name || 'this item')
    addOutput('input', `❯ ${cmd}`)
    addOutput('warning', `{warning}⚠️  Delete ${entityName}?{/}`)
    addOutput('warning', `{warning}Type 'yes' to confirm, or anything else to cancel{/}`)

    awaitingConfirmation.value = {
      original: cmd,
      parsed: parsed,
      message: `Confirm deletion`,
    }
    addToHistory(cmd)
    input.value = ''
    focusInput()
    return
  }

  // Normal execution
  await executeCommand(parsed)
  input.value = ''
  focusInput()
}

async function executeCommand(parsed: ParsedCommand) {
  const cmd = parsed.raw
  const resolvedCompanySlug = getRequestCompanySlug(parsed)
  const requiresCompany = needsCompanyContext(parsed)

  // Clear quick actions when new command executes
  tableState.value = null
  quickActions.value = []
  showSubPrompt.value = false
  subPromptAction.value = null
  subPromptInput.value = ''

  addOutput('input', `❯ ${cmd}`)
  addToHistory(cmd)
  executing.value = true

  try {
    // Require company context for tenant commands
    if (requiresCompany && !resolvedCompanySlug) {
      addOutput('error', `{error}✗{/} Company context required. Switch into a company or include --slug.`)
      return
    }

    // Read-only verbs that shouldn't use idempotency (always fetch fresh)
    const readOnlyVerbs = ['list', 'view', 'get', 'show']
    const isReadOnly = readOnlyVerbs.includes(parsed.verb)

    // For read operations, add timestamp to ensure fresh data
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Action': `${parsed.entity}.${parsed.verb}`,
      'X-CSRF-TOKEN': getCsrfToken(),
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
    }

    if (resolvedCompanySlug) {
      headers['X-Company-Slug'] = resolvedCompanySlug
    }

    // Only use idempotency for write operations
    if (!isReadOnly) {
      // ALWAYS generate a fresh idempotency key with timestamp
      // Don't use parsed.idemKey as it's deterministic (same command = same key)
      headers['X-Idempotency-Key'] = generateIdemKey(parsed)
    } else {
      // For read ops, add timestamp to bust any backend caching
      headers['X-Request-Time'] = Date.now().toString()
    }

    const res = await fetch('/api/commands', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store', // Prevent browser caching
      headers,
      body: JSON.stringify({ params: parsed.flags }),
    })

    const data = await res.json()

    if (data.ok) {
      // Table response
      if (data.data?.headers && data.data?.rows) {
        addOutput('table', data.data.rows, data.data.headers, data.data.footer)
      } 
      // Message response
      else if (data.message) {
        addOutput('success', `{success}✓{/} ${data.message}`)
      }
      // Generic success
      else {
        addOutput('success', '{success}✓{/} Done')
      }

      // After deletes, refresh the list to show updated rows
      if (parsed.verb === 'delete' && parsed.entity) {
        pendingRefreshEntity.value = parsed.entity
      }

      // Handle redirect
      if (data.redirect) {
        addOutput('output', `{link:${data.redirect}}→ Open in GUI{/}`)
      }

      // Update active company after switch
      if (parsed.entity === 'company' && parsed.verb === 'switch') {
        const payload = data.data || {}
        activeCompany.value = {
          id: payload.id || '',
          name: payload.name || '',
          slug: payload.slug || '',
          user_count: payload.user_count,
          base_currency: payload.base_currency,
          status: payload.status,
        }
      }
    } else {
      const friendly = normalizePermissionError(data.message)
      addOutput('error', `{error}✗{/} ${friendly}`)
      if (data.errors) {
        Object.entries(data.errors).forEach(([field, messages]) => {
          addOutput('error', `{error}  ${field}:{/} ${(messages as string[]).join(', ')}`)
        })
      }
    }
  } catch (e) {
    addOutput('error', `{error}✗{/} Network error: ${e instanceof Error ? e.message : 'Unknown'}`)
  } finally {
    executing.value = false
    const refreshEntity = pendingRefreshEntity.value
    pendingRefreshEntity.value = null
    focusInput()
    if (refreshEntity) {
      input.value = `${refreshEntity} list`
      execute()
    }
  }
}

function handleKeydown(e: KeyboardEvent) {
  if (!props.visible) return

  if (showSubPrompt.value) {
    if (e.key === 'Escape') {
      e.preventDefault()
      closeSubPrompt()
    }
    return
  }

  // Escape - close
  if (e.key === 'Escape') {
    if (showSuggestions.value) {
      showSuggestions.value = false
    } else {
      close()
    }
    return
  }

  // Enter - execute or accept suggestion
  if (e.key === 'Enter') {
    if (showSuggestions.value && suggestions.value.length > 0) {
      e.preventDefault()
      acceptSuggestion()
    } else {
      execute()
    }
    return
  }

  // Tab - accept suggestion or execute complete command; keep focus inside palette
  if (e.key === 'Tab') {
    e.preventDefault()
    if (showSuggestions.value && suggestions.value.length > 0) {
      acceptSuggestion()
      return
    }
    const parsedCmd = parsed.value
    if (
      parsedCmd.entity &&
      parsedCmd.verb &&
      parsedCmd.errors.length === 0
    ) {
      const noFlags = Object.keys(parsedCmd.flags || {}).length === 0
      if (parsedCmd.complete || noFlags) {
        execute()
      } else {
        focusInput()
      }
    } else {
      focusInput()
    }
    return
  }

  // Arrow navigation
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (showSuggestions.value) {
      suggestionIndex.value = Math.max(0, suggestionIndex.value - 1)
    } else if (tableState.value && tableState.value.rows.length > 0) {
      // Navigate table rows
      const newIndex = Math.max(0, tableState.value.selectedRowIndex - 1)
      tableState.value.selectedRowIndex = newIndex
    } else if (history.value.length && historyIndex.value < history.value.length - 1) {
      historyIndex.value++
      input.value = history.value[historyIndex.value]
    }
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (showSuggestions.value) {
      suggestionIndex.value = Math.min(suggestions.value.length - 1, suggestionIndex.value + 1)
    } else if (tableState.value && tableState.value.rows.length > 0) {
      // Navigate table rows
      const newIndex = Math.min(tableState.value.rows.length - 1, tableState.value.selectedRowIndex + 1)
      tableState.value.selectedRowIndex = newIndex
    } else if (historyIndex.value > 0) {
      historyIndex.value--
      input.value = history.value[historyIndex.value]
    } else if (historyIndex.value === 0) {
      historyIndex.value = -1
      input.value = ''
    }
    return
  }

  // Number keys (0-9) - trigger quick actions
  if (quickActions.value.length > 0 && /^[0-9]$/.test(e.key)) {
    const action = quickActions.value.find(a => a.key === e.key)
    if (action) {
      e.preventDefault()
      handleQuickAction(action)
      return
    }
  }

  // Ctrl+L - clear
  if (e.key === 'l' && e.ctrlKey) {
    e.preventDefault()
    output.value = []
    return
  }

  // Ctrl+U - clear input
  if (e.key === 'u' && e.ctrlKey) {
    e.preventDefault()
    input.value = ''
    return
  }
}

function acceptSuggestion() {
  const suggestion = suggestions.value[suggestionIndex.value] || suggestions.value[0]
  if (!suggestion) return

  // Use the value from the suggestion
  input.value = suggestion.value
  showSuggestions.value = false
  nextTick(() => inputEl.value?.focus())
}

function selectSuggestion(index: number) {
  suggestionIndex.value = index
  acceptSuggestion()
}

function addToHistory(cmd: string) {
  history.value = [cmd, ...history.value.filter(h => h !== cmd)].slice(0, 100)
  historyIndex.value = -1
  saveHistory()
}

function loadHistory(): string[] {
  try {
    return JSON.parse(localStorage.getItem('palette-history') || '[]')
  } catch {
    return []
  }
}

function saveHistory() {
  try {
    localStorage.setItem('palette-history', JSON.stringify(history.value))
  } catch { /* ignore */ }
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function generateIdemKey(parsed: ParsedCommand): string {
  const parts = [parsed.entity, parsed.verb, Date.now(), JSON.stringify(parsed.flags)]
  return btoa(parts.join('|')).substring(0, 32)
}

function needsCompanyContext(parsed: ParsedCommand): boolean {
  const key = `${parsed.entity}.${parsed.verb}`
  const globalCommands = new Set([
    'company.list',
    'company.create',
    'company.switch',
    'help',
    'clear',
  ])
  return !globalCommands.has(key)
}

function normalizePermissionError(message?: string): string {
  if (!message) return 'Command failed'
  if (message.includes('There is no permission named')) {
    return `${message} (permissions cache likely stale — run app:sync-permissions and app:sync-role-permissions)`
  }
  return message
}

function getRequestCompanySlug(parsed: ParsedCommand): string {
  const flags = parsed.flags as Record<string, unknown>
  const fromFlags = ['slug', 'company', 'id'].map((key) => {
    const val = flags?.[key]
    return typeof val === 'string' ? val.trim() : ''
  }).find(Boolean) || ''

  return companySlug.value || fromFlags || getSlugFromTableState()
}

function getSlugFromTableState(): string {
  const state = tableState.value
  if (!state) return ''

  const rowIndex = state.selectedRowIndex
  if (rowIndex < 0 || rowIndex >= state.rows.length) return ''

  const slugIndex = state.headers.findIndex(h => h.toLowerCase().replace(/\s+/g, '') === 'slug')
  if (slugIndex === -1) return ''

  return state.rows[rowIndex]?.[slugIndex] || ''
}

// Handle quick action click
function handleQuickAction(action: QuickAction) {
  // Check if action needs row selection
  if (action.needsRow && (!tableState.value || tableState.value.selectedRowIndex < 0)) {
    return
  }

  // Check if action needs sub-prompt
  if (action.prompt) {
    subPromptAction.value = action
    subPromptInput.value = ''
    showSubPrompt.value = true
    nextTick(() => subPromptInputEl.value?.focus())
    return
  }

  // Execute directly
  const resolved = resolveQuickActionCommand(action.command, tableState.value)
  if (resolved) {
    input.value = resolved
    execute()
  }
}

// Select row in table
function selectRow(rowIndex: number) {
  if (tableState.value) {
    tableState.value.selectedRowIndex = rowIndex
  }
  focusInput()
}

// Close sub-prompt modal
function closeSubPrompt() {
  showSubPrompt.value = false
  subPromptAction.value = null
  subPromptInput.value = ''
  focusInput()
}

// Confirm sub-prompt and execute
function confirmSubPrompt() {
  if (!subPromptAction.value || !subPromptInput.value.trim()) return

  // Resolve command with row data first
  let resolved = resolveQuickActionCommand(subPromptAction.value.command, tableState.value)

  // If command still has placeholders or is null, use the base command
  if (!resolved) {
    resolved = subPromptAction.value.command
  }

  // Append the user input
  const finalCommand = `${resolved} ${subPromptInput.value.trim()}`

  // Execute
  input.value = finalCommand
  closeSubPrompt()
  execute()
}

// Click outside to close suggestions
function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.palette-autocomplete') && !target.closest('.palette-input')) {
    showSuggestions.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})

function focusInput() {
  nextTick(() => inputEl.value?.focus())
}
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <div v-if="visible" class="palette-backdrop" @click="close" />

    <!-- Palette -->
    <div v-if="visible" class="palette">
      <!-- Header - minimal, just company context -->
      <div class="palette-header">
        <span class="palette-company">
          <span class="palette-company-icon">●</span>
          {{ activeCompany?.name || 'No company' }}
        </span>
        <div v-if="activeCompany" class="palette-company-meta">
          <span class="palette-chip">Slug: {{ activeCompany.slug }}</span>
          <span v-if="activeCompany.id" class="palette-chip">ID: {{ activeCompany.id }}</span>
          <span v-if="activeCompany.user_count !== undefined" class="palette-chip">Users: {{ activeCompany.user_count }}</span>
        </div>
        <div class="palette-header-right">
          <span class="palette-shortcut">Esc to close</span>
        </div>
      </div>

      <!-- Main content area with sidebar -->
      <div class="palette-body">
        <!-- Main output and input area -->
        <div class="palette-main">
          <!-- Output -->
          <div ref="outputEl" class="palette-output">
        <template v-if="output.length === 0">
          <div class="palette-empty">
            Type a command or <span class="palette-cmd">help</span> for available commands
          </div>
        </template>
        <template v-for="(line, i) in output" :key="i">
          <!-- Table with row selection -->
          <div v-if="line.type === 'table'" class="palette-table">
            <div class="table-wrapper">
              <!-- Table headers -->
              <div v-if="line.headers && line.headers.length" class="table-header">
                <div
                  v-for="(header, colIndex) in line.headers"
                  :key="colIndex"
                  class="table-cell table-cell--header"
                >
                  {{ header }}
                </div>
              </div>
              <!-- Table rows -->
              <div
                v-for="(row, rowIndex) in (line.content as string[][])"
                :key="rowIndex"
                class="table-row"
                :class="{
                  'table-row--selected': tableState && i === output.length - 1 && rowIndex === tableState.selectedRowIndex
                }"
                @click="selectRow(rowIndex)"
              >
                <div
                  v-for="(cell, colIndex) in row"
                  :key="colIndex"
                  class="table-cell"
                >
                  {{ cell }}
                </div>
              </div>
              <!-- Table footer -->
              <div v-if="line.footer" class="table-footer">
                {{ line.footer }}
              </div>
            </div>
          </div>
          <!-- Text lines -->
          <div
            v-else
            class="palette-line"
            :class="{
              'palette-line--input': line.type === 'input',
              'palette-line--error': line.type === 'error',
              'palette-line--success': line.type === 'success',
            }"
            v-html="formatText(String(line.content))"
          ></div>
        </template>
      </div>

      <!-- Input area -->
      <div class="palette-input-area">
        <!-- Autocomplete dropdown (above input) -->
        <div v-if="showSuggestions && suggestions.length" class="palette-autocomplete">
          <div
            v-for="(suggestion, index) in suggestions"
            :key="suggestion.value"
            class="palette-autocomplete-item"
            :class="{ 'palette-autocomplete-item--selected': index === suggestionIndex }"
            @click="selectSuggestion(index)"
            @mouseenter="suggestionIndex = index"
          >
            <span class="suggestion-icon">{{ suggestion.icon || '📦' }}</span>
            <div class="suggestion-content">
              <span class="suggestion-label">{{ suggestion.label }}</span>
              <span v-if="suggestion.description" class="suggestion-desc">{{ suggestion.description }}</span>
            </div>
            <kbd v-if="index === suggestionIndex">Tab</kbd>
          </div>
        </div>

        <!-- Input row -->
        <div class="palette-input-row">
          <span class="palette-prompt" :class="{ 'palette-prompt--busy': executing }">
            {{ executing ? '⋯' : '❯' }}
          </span>
          <div class="palette-input-container">
            <input
              ref="inputEl"
              v-model="input"
              type="text"
              class="palette-input"
              :disabled="executing"
              placeholder="Type a command..."
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
            />
            <span
              v-if="placeholderHint"
              class="palette-placeholder-hint"
              :style="{ '--typed-text-width': typedTextWidth }"
            >
              {{ placeholderHint }}
            </span>
          </div>
          <div v-if="executing" class="palette-loading">
            <div class="palette-spinner"></div>
            <span class="palette-loading-text">Executing...</span>
          </div>
        </div>

        <!-- Helper text -->
        <div class="palette-helper">
          <template v-if="showSuggestions">
            <span><kbd>↑↓</kbd> navigate</span>
            <span><kbd>Tab</kbd> accept</span>
          </template>
          <template v-else>
            <span><kbd>Enter</kbd> run</span>
            <span><kbd>↑↓</kbd> history</span>
            <span><kbd>Ctrl+L</kbd> clear</span>
          </template>
        </div>

        <!-- Parsed status bar -->
        <div v-if="input.trim()" class="palette-parsed">
          <span 
            class="palette-parsed__pill" 
            :class="{ 'palette-parsed__pill--valid': parsed.entity }"
          >
            {{ parsed.entity || 'entity' }}
          </span>
          <span class="palette-parsed__dot">.</span>
          <span 
            class="palette-parsed__pill palette-parsed__pill--verb"
            :class="{ 'palette-parsed__pill--valid': parsed.verb }"
          >
            {{ parsed.verb || 'verb' }}
          </span>
          
          <template v-if="hasFlags">
            <span class="palette-parsed__flags">
              <span 
                v-for="[key, val] in flagEntries" 
                :key="key" 
                class="palette-parsed__flag"
              >
                --{{ key }}={{ val }}
              </span>
            </span>
          </template>

          <span v-if="parsed.errors.length" class="palette-parsed__error">
            ✗ {{ parsed.errors[0] }}
          </span>
          <span v-else-if="parsed.complete" class="palette-parsed__ready">
            ✓ ready
          </span>
        </div>
      </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div v-if="quickActions.length > 0" class="palette-sidebar">
          <div class="sidebar-header">
            Quick Actions
            <span v-if="tableState" class="sidebar-debug">
              ({{ tableState.entity }}.{{ tableState.verb }})
            </span>
          </div>
          <div class="sidebar-actions">
            <div
              v-for="action in quickActions"
              :key="action.key"
              class="sidebar-action"
              :class="{
                'sidebar-action--needs-row': action.needsRow && !tableState,
                'sidebar-action--disabled': action.needsRow && (!tableState || tableState.selectedRowIndex < 0)
              }"
              @click="handleQuickAction(action)"
            >
              <span class="action-key">{{ action.key }}</span>
              <span class="action-label">{{ getQuickActionLabel(action, tableState) }}</span>
            </div>
        </div>
        <div class="sidebar-hint">
          Press the shown number keys (0-9)
          <template v-if="tableState">
            <br />
            <span class="hint-small">Use ↑↓ to select row</span>
          </template>
        </div>
        </div>
      </div>
    </div>

    <!-- Sub-Prompt Modal -->
    <div v-if="showSubPrompt && subPromptAction" class="subprompt-backdrop" @click="closeSubPrompt">
      <div class="subprompt-modal" @click.stop>
        <div class="subprompt-header">
          <span class="subprompt-title">{{ subPromptAction.label }}</span>
          <button class="subprompt-close" @click="closeSubPrompt">✕</button>
        </div>
        <div class="subprompt-body">
          <p class="subprompt-prompt">{{ subPromptAction.prompt }}</p>
          <input
            ref="subPromptInputEl"
            v-model="subPromptInput"
            type="text"
            class="subprompt-input"
            placeholder="Enter value..."
            @keydown.enter="confirmSubPrompt"
            @keydown.esc="closeSubPrompt"
          />
        </div>
        <div class="subprompt-footer">
          <button class="subprompt-btn subprompt-btn--cancel" @click="closeSubPrompt">
            Cancel
          </button>
          <button class="subprompt-btn subprompt-btn--confirm" @click="confirmSubPrompt">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* Single dark theme - no switching */
.palette-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9998;
}

.palette {
  position: fixed;
  top: 8vh;
  left: 50%;
  transform: translateX(-50%);
  width: 1100px;
  max-width: calc(100vw - 40px);
  max-height: 80vh;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 10px;
  font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Consolas', monospace;
  font-size: 14px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.05),
    0 20px 50px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

/* Body with sidebar */
.palette-body {
  display: flex;
  flex: 1;
  min-height: 0;
}

.palette-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* Header */
.palette-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #1e293b;
  border-bottom: 1px solid #334155;
  font-size: 13px;
}

.palette-company {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #e2e8f0;
  font-weight: 500;
}

.palette-company-icon {
  color: #22d3ee;
  font-size: 10px;
}

.palette-company-meta {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-left: 12px;
}

.palette-chip {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  color: #cbd5e1;
  font-size: 11px;
}

.palette-header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.palette-shortcut {
  color: #64748b;
  font-size: 12px;
}

/* Output */
.palette-output {
  flex: 1;
  overflow-y: auto;
  padding: 12px 14px;
  min-height: 120px;
  max-height: 400px;
}

.palette-output::-webkit-scrollbar {
  width: 6px;
}

.palette-output::-webkit-scrollbar-track {
  background: transparent;
}

.palette-output::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 3px;
}

.palette-output::-webkit-scrollbar-thumb:hover {
  background: #475569;
}

.palette-empty {
  color: #64748b;
  text-align: center;
  padding: 40px 20px;
}

.palette-cmd {
  color: #22d3ee;
  background: rgba(34, 211, 238, 0.1);
  padding: 2px 6px;
  border-radius: 4px;
}

.palette-line {
  color: #e2e8f0;
  line-height: 1.7;
  white-space: pre-wrap;
  word-break: break-word;
}

.palette-line--input {
  color: #22d3ee;
}

.palette-line--error {
  color: #f43f5e;
}

.palette-line--success {
  color: #10b981;
}

.palette-table {
  margin: 8px 0;
}

.palette-table pre {
  margin: 0;
  color: #e2e8f0;
  font-family: inherit;
  font-size: 13px;
  line-height: 1.5;
  overflow-x: auto;
}

/* Custom table with row selection */
.table-wrapper {
  border: 1px solid #334155;
  border-radius: 6px;
  overflow: hidden;
}

.table-header {
  display: flex;
  background: rgba(34, 211, 238, 0.1);
  border-bottom: 1px solid #334155;
}

.table-row {
  display: flex;
  border-bottom: 1px solid #334155;
  cursor: pointer;
  transition: background 0.1s;
}

.table-row:last-child {
  border-bottom: none;
}

.table-row:hover {
  background: rgba(34, 211, 238, 0.05);
}

.table-row--selected {
  background: rgba(34, 211, 238, 0.15) !important;
  border-left: 3px solid #22d3ee;
}

.table-cell {
  flex: 1;
  padding: 8px 12px;
  color: #e2e8f0;
  font-size: 12px;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.table-cell--header {
  font-weight: 600;
  color: #22d3ee;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}

.table-footer {
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.2);
  border-top: 1px solid #334155;
  color: #64748b;
  font-size: 11px;
  text-align: right;
}

/* Input area */
.palette-input-area {
  position: relative;
  border-top: 1px solid #334155;
  background: #1e293b;
}

.palette-input-row {
  display: flex;
  align-items: center;
  padding: 12px 14px;
}

.palette-prompt {
  color: #22d3ee;
  margin-right: 10px;
  font-weight: 600;
  transition: color 0.15s;
  flex-shrink: 0;
}

.palette-prompt--busy {
  color: #f59e0b;
}

.palette-input-container {
  position: relative;
  flex: 1;
  min-width: 0;
}

.palette-input {
  width: 100%;
  background: transparent;
  border: none;
  outline: none;
  color: #e2e8f0;
  font: inherit;
  caret-color: #22d3ee;
  position: relative;
  z-index: 2;
}

.palette-input::placeholder {
  color: #475569;
}

.palette-input:disabled {
  opacity: 0.5;
}

.palette-placeholder-hint {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #475569;
  opacity: 0.4;
  white-space: nowrap;
  font: inherit;
  z-index: 1;
  padding-left: var(--typed-text-width, 0);
}

/* Helper */
.palette-helper {
  display: flex;
  gap: 16px;
  padding: 0 14px 10px;
  font-size: 11px;
  color: #64748b;
}

.palette-helper kbd {
  display: inline-block;
  background: rgba(255, 255, 255, 0.08);
  padding: 1px 5px;
  border-radius: 3px;
  margin-right: 4px;
  font-family: inherit;
  font-size: 10px;
}

/* Autocomplete */
.palette-autocomplete {
  position: absolute;
  bottom: 100%;
  left: 0;
  right: 0;
  background: #1e293b;
  border: 1px solid #334155;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  max-height: 220px;
  overflow-y: auto;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}

.palette-autocomplete-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  color: #e2e8f0;
  cursor: pointer;
  transition: background 0.1s;
}

.palette-autocomplete-item:hover,
.palette-autocomplete-item--selected {
  background: rgba(34, 211, 238, 0.1);
}

.palette-autocomplete-item--selected .suggestion-label {
  color: #22d3ee;
}

.suggestion-icon {
  font-size: 16px;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}

.suggestion-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.suggestion-label {
  font-weight: 500;
  color: #e2e8f0;
  font-size: 13px;
}

.suggestion-desc {
  font-size: 11px;
  color: #64748b;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.palette-autocomplete-item kbd {
  font-size: 10px;
  padding: 2px 6px;
  background: rgba(34, 211, 238, 0.2);
  border-radius: 3px;
  color: #22d3ee;
  flex-shrink: 0;
}

/* Parsed status bar */
.palette-parsed {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 14px;
  border-top: 1px solid #334155;
  background: rgba(0, 0, 0, 0.2);
  font-size: 12px;
  flex-wrap: wrap;
}

.palette-parsed__pill {
  padding: 3px 8px;
  background: rgba(100, 116, 139, 0.2);
  border: 1px solid rgba(100, 116, 139, 0.3);
  border-radius: 4px;
  color: #64748b;
  font-family: inherit;
}

.palette-parsed__pill--valid {
  background: rgba(34, 211, 238, 0.1);
  border-color: rgba(34, 211, 238, 0.3);
  color: #22d3ee;
}

.palette-parsed__pill--verb.palette-parsed__pill--valid {
  background: rgba(99, 102, 241, 0.1);
  border-color: rgba(99, 102, 241, 0.3);
  color: #a5b4fc;
}

.palette-parsed__dot {
  color: #475569;
  font-weight: 600;
}

.palette-parsed__flags {
  display: flex;
  gap: 6px;
  margin-left: 8px;
  flex-wrap: wrap;
}

.palette-parsed__flag {
  padding: 2px 6px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 4px;
  color: #fbbf24;
  font-size: 11px;
}

.palette-parsed__error {
  margin-left: auto;
  color: #f43f5e;
  font-size: 11px;
}

.palette-parsed__ready {
  margin-left: auto;
  color: #10b981;
  font-size: 11px;
}

/* Loading indicator */
.palette-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 12px;
  color: #f59e0b;
  font-size: 13px;
}

.palette-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(34, 211, 238, 0.2);
  border-top-color: #22d3ee;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.palette-loading-text {
  color: #22d3ee;
  font-size: 12px;
}

/* Semantic formatting classes */
.fmt-success {
  color: #10b981;
}

.fmt-error {
  color: #f43f5e;
}

.fmt-warning {
  color: #f59e0b;
}

.fmt-accent {
  color: #22d3ee;
}

.fmt-primary {
  color: #6366f1;
}

.fmt-secondary {
  color: #64748b;
}

.fmt-dim {
  opacity: 0.5;
}

.fmt-code {
  background: rgba(34, 211, 238, 0.1);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 13px;
}

.fmt-link {
  color: #22d3ee;
  text-decoration: none;
  border-bottom: 1px dotted #22d3ee;
  cursor: pointer;
}

.fmt-link:hover {
  text-decoration: underline;
  border-bottom-style: solid;
}

/* Quick Actions Sidebar */
.palette-sidebar {
  width: 260px;
  background: #1e293b;
  border-left: 1px solid #334155;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 12px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #334155;
}

.sidebar-debug {
  display: block;
  font-size: 10px;
  color: #fbbf24;
  text-transform: none;
  margin-top: 4px;
  font-weight: normal;
}

.sidebar-actions {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.sidebar-action {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 6px;
  background: rgba(34, 211, 238, 0.05);
  border: 1px solid rgba(34, 211, 238, 0.2);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.sidebar-action:hover {
  background: rgba(34, 211, 238, 0.1);
  border-color: rgba(34, 211, 238, 0.3);
}

.sidebar-action--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.sidebar-action--disabled:hover {
  background: rgba(34, 211, 238, 0.05);
  border-color: rgba(34, 211, 238, 0.2);
}

.action-key {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: rgba(34, 211, 238, 0.2);
  color: #22d3ee;
  border-radius: 4px;
  font-weight: 600;
  font-size: 13px;
  flex-shrink: 0;
}

.action-label {
  flex: 1;
  color: #e2e8f0;
  font-size: 12px;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.sidebar-hint {
  padding: 12px 14px;
  border-top: 1px solid #334155;
  font-size: 11px;
  color: #64748b;
  line-height: 1.5;
}

.hint-small {
  font-size: 10px;
  opacity: 0.7;
}

/* Sub-Prompt Modal */
.subprompt-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.subprompt-modal {
  width: 480px;
  max-width: calc(100vw - 40px);
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Consolas', monospace;
}

.subprompt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  border-bottom: 1px solid #334155;
}

.subprompt-title {
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
}

.subprompt-close {
  width: 28px;
  height: 28px;
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 18px;
  cursor: pointer;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.subprompt-close:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #e2e8f0;
}

.subprompt-body {
  padding: 20px 18px;
}

.subprompt-prompt {
  margin: 0 0 14px 0;
  font-size: 13px;
  color: #94a3b8;
  line-height: 1.5;
}

.subprompt-input {
  width: 100%;
  padding: 12px 14px;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 6px;
  color: #e2e8f0;
  font-family: inherit;
  font-size: 14px;
  outline: none;
  transition: border-color 0.15s;
}

.subprompt-input:focus {
  border-color: #22d3ee;
}

.subprompt-input::placeholder {
  color: #475569;
}

.subprompt-footer {
  display: flex;
  gap: 10px;
  padding: 16px 18px;
  border-top: 1px solid #334155;
  justify-content: flex-end;
}

.subprompt-btn {
  padding: 8px 16px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
  border: none;
}

.subprompt-btn--cancel {
  background: transparent;
  color: #64748b;
  border: 1px solid #334155;
}

.subprompt-btn--cancel:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #94a3b8;
}

.subprompt-btn--confirm {
  background: #22d3ee;
  color: #0f172a;
}

.subprompt-btn--confirm:hover {
  background: #06b6d4;
}
</style>
