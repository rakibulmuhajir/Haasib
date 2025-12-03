<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { parse } from '@/palette/parser'
import { generateSuggestions } from '@/palette/autocomplete'
import { getHelp } from '@/palette/help'
import { formatText } from '@/palette/formatter'
import { getCommandExample, resolveEntityShortcut } from '@/palette/grammar'
import { getQuickActions, resolveQuickActionCommand, getQuickActionLabel } from '@/palette/quick-actions'
import { getSchema } from '@/palette/schemas'
import { buildScaffold } from '@/palette/scaffold'
import { getFrecencyScores, recordCommandUse } from '@/palette/frecency'
import { isPresetShortcut } from '@/palette/shortcuts'
import { usePage } from '@inertiajs/vue3'
import type { ParsedCommand, Suggestion, QuickAction, TableState, OutputLine } from '@/types/palette'

const props = defineProps<{ visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [v: boolean] }>()

type Stage = 'entity' | 'verb'
interface ContextRecord {
  label: string
  value: string
  meta?: string
}

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
const stage = ref<Stage>('entity')
const frecencyScores = ref<Record<string, number>>(getFrecencyScores())
const contextRecords = ref<ContextRecord[]>([])
const contextTitle = ref('')
const defaults = ref<Record<string, { value: string; source?: string }>>({})
const defaultsAbort = ref<AbortController | null>(null)
const cursorIndex = ref(0)
const defaultsLoading = ref(false)
const scaffoldState = computed(() => {
  const schema = getSchema(parsed.value.entity, parsed.value.verb)
  const activeArg = getActiveArgFromCursor(schema)
  const flagsWithState = schema?.flags?.map(flag => ({
    ...flag,
    loading: defaultsLoading.value && !defaults.value[flag.name]?.value,
  })) || []
  return buildScaffold(parsed.value, schema ? { ...schema, flags: flagsWithState } : null, {
    companyName: activeCompany.value?.name,
    companyCurrency: activeCompany.value?.base_currency ?? activeCompany.value?.currency,
    defaults: defaults.value,
  }, activeArg)
})

const validationHint = computed(() => {
  const missing = resolvedMissingArgs.value
  if (!missing.length) return ''
  if (missing.length === 1) {
    const arg = missing[0]
    return `Need ${arg.name}${arg.hint ? ` (${arg.hint})` : ''}`
  }
  return `Need: ${missing.map(arg => arg.name).join(', ')}`
})

// Parsed command (reactive)
const parsed = computed(() => parse(input.value))

const resolvedFlags = computed(() => mergeFlagsWithDefaults(parsed.value.flags))
const resolvedMissingArgs = computed(() => getMissingRequiredArgs(getSchema(parsed.value.entity, parsed.value.verb), resolvedFlags.value))
const resolvedComplete = computed(() =>
  parsed.value.errors.length === 0 &&
  !!parsed.value.entity &&
  !!parsed.value.verb &&
  getSchema(parsed.value.entity, parsed.value.verb) !== null &&
  resolvedMissingArgs.value.length === 0
)

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
const fetchAbort = ref<AbortController | null>(null)

const tabActionLabel = computed(() => {
  if (showSuggestions.value) return 'select'
  if (resolvedComplete.value) return 'run'
  return 'select'
})

// Refs
const inputEl = ref<HTMLInputElement>()
const outputEl = ref<HTMLDivElement>()
const subPromptInputEl = ref<HTMLInputElement>()

// Company context
const page = usePage()
const initialCompany = computed(() => (page.props.auth as any)?.currentCompany)
const activeCompany = ref(initialCompany.value || null)
const companySlug = computed(() => activeCompany.value?.slug || '')

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

watch(parsed, (val) => {
  handleContextRecords(val)
  resolveDefaults(val)
})

// Update suggestions on input
watch(input, async (val) => {
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

  stage.value = determineStage(val)
  await refreshSuggestions(val)
}, { immediate: true })

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
  showSuggestions.value = false
  stage.value = 'entity'
  contextRecords.value = []
  contextTitle.value = ''
  if (fetchTimeout.value) clearTimeout(fetchTimeout.value)
}

async function refreshSuggestions(val: string) {
  const trimmed = val.trim()
  const parsedCmd = parse(trimmed)
  const tokens = trimmed ? trimmed.split(/\s+/) : []
  const inferredEntity = parsedCmd.entity || resolveEntityShortcut(tokens[0] || '') || ''

  // Hide suggestions when awaiting confirmation
  if (awaitingConfirmation.value) {
    showSuggestions.value = false
    return
  }

  // Show placeholder hint instead of suggestions when both entity+verb are present and user added trailing space
  if (tokens.length === 2 && val.endsWith(' ') && parsedCmd.entity && parsedCmd.verb) {
    showSuggestions.value = false
    return
  }

  const suggestionStage: Stage = stage.value === 'verb' && inferredEntity ? 'verb' : 'entity'
  const baseSuggestions = await generateFieldSuggestions(trimmed, parsedCmd, suggestionStage, inferredEntity)

  suggestions.value = baseSuggestions
  showSuggestions.value = baseSuggestions.length > 0
  suggestionIndex.value = 0

  // If no suggestions and scaffold has a current arg, show a placeholder hint
  if (!showSuggestions.value) {
    const scaffold = scaffoldState.value
    if (scaffold?.currentArg) {
      suggestions.value = [{
        type: 'command',
        value: val,
        label: `<${scaffold.currentArg}>`,
        description: scaffold.pointerLabel,
        icon: '⌨️',
      }]
      showSuggestions.value = true
      suggestionIndex.value = 0
    }
  }

  if (!parsedCmd.entity || !parsedCmd.verb) return
  if (tokens.length <= 2) return

  const dynamic = await fetchDynamicSuggestions(parsedCmd.entity, parsedCmd.verb, tokens.slice(2).join(' '))
  if (dynamic.length > 0) {
    suggestions.value = [...dynamic, ...baseSuggestions].slice(0, 8)
    showSuggestions.value = true
    suggestionIndex.value = 0
  }
}

/**
 * Fetch dynamic suggestions from backend (debounced)
 */
async function fetchDynamicSuggestions(
  entity: string,
  verb: string,
  partial: string
): Promise<Suggestion[]> {
  if (fetchTimeout.value) clearTimeout(fetchTimeout.value)
  fetchAbort.value?.abort()

  return new Promise((resolve) => {
    fetchTimeout.value = window.setTimeout(async () => {
      fetchAbort.value = new AbortController()
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
          signal: fetchAbort.value.signal,
        })

        if (!res.ok) {
          resolve([])
          return
        }

        const data = await res.json()
        const dynamicSuggestions = (data.suggestions || []).map((s: any) => ({
          type: 'value' as const,
          value: `${entity} ${verb} ${s.value}`,
          label: s.label,
          description: s.description,
          icon: s.icon,
        }))

        resolve(dynamicSuggestions)
      } catch (e) {
        if (e instanceof DOMException && e.name === 'AbortError') {
          resolve([])
        } else {
          console.error('Failed to fetch palette suggestions:', e)
          resolve([])
        }
      }
    }, 250)
  })
}

function addOutput(type: OutputLine['type'], content: string | string[][], headers?: string[], footer?: string) {
  output.value.push({ type, content, headers, footer })
  // Keep max 200 lines
  if (output.value.length > 200) {
    output.value = output.value.slice(-200)
  }
}

function determineStage(val: string): Stage {
  const trimmed = val.trim()
  if (!trimmed) return 'entity'
  const tokens = trimmed.split(/\s+/)
  if (tokens.length === 0) return 'entity'

  const first = tokens[0]
  const isShortcut = isPresetShortcut(first)
  const resolved = resolveEntityShortcut(first)

  if ((resolved || isShortcut) && (trimmed.endsWith(' ') || tokens.length > 1)) {
    return 'verb'
  }

  return 'entity'
}

async function handleContextRecords(parsedCmd: ParsedCommand) {
  if (parsedCmd.entity === 'payment' && parsedCmd.verb === 'create') {
    contextTitle.value = 'Recent unpaid invoices'
    if (contextRecords.value.length === 0) {
      contextRecords.value = await loadRecentInvoices()
    }
    return
  }

  contextTitle.value = ''
  contextRecords.value = []
}

async function loadRecentInvoices(): Promise<ContextRecord[]> {
  try {
    const res = await fetch('/api/palette/invoices/recent', {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!res.ok) return []
    const invoices = await res.json()
    return (invoices || []).slice(0, 5).map((inv: any) => ({
      label: inv.number || inv.id,
      value: inv.number || inv.id,
      meta: inv.meta?.customer
        ? `${inv.meta.customer}${inv.meta.amount ? ` · ${inv.meta.amount}` : ''}`
        : inv.meta?.amount,
    }))
  } catch (e) {
    console.error('Failed to load recent invoices', e)
    return []
  }
}

function applyContextRecord(record: ContextRecord) {
  const base = input.value.trim()
  const parsedCmd = parse(base)
  const entity = parsedCmd.entity || resolveEntityShortcut(base.split(/\s+/)[0] || '') || 'payment'
  const verb = parsedCmd.verb || 'create'
  input.value = `${entity} ${verb} --invoice=${record.value} `
  stage.value = 'verb'
  showSuggestions.value = false
  focusInput()
}

function applyFlagChip(flag: { name: string; value?: string }) {
  const existing = input.value.trim()
  const append = flag.value ? ` --${flag.name}=${flag.value}` : ` --${flag.name}=`
  input.value = `${existing}${append}`.trim() + ' '
  focusInput()
}

function handleCursor() {
  const el = inputEl.value
  if (el) {
    cursorIndex.value = el.selectionStart || 0
  }
}

function getActiveArgFromCursor(schema: any): string | undefined {
  if (!schema || !input.value) return undefined
  // Remove prompt parts
  const beforeCursor = input.value.slice(0, cursorIndex.value)
  const tokens = beforeCursor.trimEnd().split(/\s+/).filter(Boolean)
  // tokens[0] = entity, tokens[1] = verb, args follow
  const argIndex = Math.max(0, tokens.length - 2)
  const args = schema.args || []
  return args[argIndex]?.name
}

async function generateFieldSuggestions(
  trimmed: string,
  parsedCmd: ParsedCommand,
  stage: 'entity' | 'verb',
  inferredEntity: string
): Promise<Suggestion[]> {
  // Default grammar-based suggestions
  const base = generateSuggestions(trimmed, {
    stage,
    entity: inferredEntity,
    frecencyScores: frecencyScores.value,
  })

  const schema = getSchema(parsedCmd.entity, parsedCmd.verb)
  const currentArg = scaffoldState.value?.currentArg

  if (!schema || !currentArg) return base

  // Field-aware suggestions
  if (parsedCmd.entity === 'invoice' && parsedCmd.verb === 'create') {
    if (currentArg === 'customer') {
      const q = trimmed.split(/\s+/).pop() || ''
      const customers = await fetchCatalog(`/api/palette/customers?q=${encodeURIComponent(q)}`)
      const mapped = customers.slice(0, 5).map((c: any) => ({
        type: 'entity' as const,
        value: `${schema.entity} ${schema.verb} ${c.name} `,
        label: c.name,
        description: c.meta?.outstanding,
        icon: '👤',
      }))
      return mapped.length ? mapped : base
    }
    if (currentArg === 'currency') {
      const currency = activeCompany.value?.base_currency
      if (currency) {
        return [{
          type: 'flag',
          value: `${trimmed} ${currency}`.trim(),
          label: currency,
          description: 'Company base currency',
          icon: '💱',
        }]
      }
    }
  }

  if (parsedCmd.entity === 'payment' && parsedCmd.verb === 'create') {
    if (currentArg === 'invoice') {
      const q = trimmed.split(/\s+/).pop() || ''
      const invoices = await fetchCatalog('/api/palette/invoices/recent')
      const filtered = invoices.filter((i: any) =>
        i.number?.toLowerCase().includes(q.toLowerCase()) ||
        i.meta?.customer?.toLowerCase().includes(q.toLowerCase())
      )
      const mapped = filtered.slice(0, 5).map((i: any) => ({
        type: 'entity' as const,
        value: `${schema.entity} ${schema.verb} ${i.number} `,
        label: i.number,
        description: `${i.meta?.customer || ''} ${i.meta?.amount ? `· ${i.meta.amount}` : ''}`,
        icon: '📄',
      }))
      return mapped.length ? mapped : base
    }
  }

  if (parsedCmd.entity === 'customer' && parsedCmd.verb === 'create') {
    if (currentArg === 'currency') {
      const currency = activeCompany.value?.base_currency
      if (currency) {
        return [{
          type: 'flag',
          value: `${trimmed} ${currency}`.trim(),
          label: currency,
          description: 'Company base currency',
          icon: '💱',
        }]
      }
    }
  }

  return base
}

async function fetchCatalog(url: string, signal?: AbortSignal): Promise<any[]> {
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      signal,
    })
    if (!res.ok) return []
    return await res.json()
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return []
    console.error('Catalog fetch failed', e)
    return []
  }
}

async function resolveDefaults(parsedCmd: ParsedCommand) {
  const schema = getSchema(parsedCmd.entity, parsedCmd.verb)
  if (!schema) {
    defaultsAbort.value?.abort()
    defaultsAbort.value = null
    defaults.value = {}
    defaultsLoading.value = false
    return
  }

  defaultsAbort.value?.abort()
  const controller = new AbortController()
  defaultsAbort.value = controller

  defaultsLoading.value = true
  const nextDefaults: Record<string, { value: string; source?: string }> = {}

  // Invoice create: customer currency/payment_terms
  if (parsedCmd.entity === 'invoice' && parsedCmd.verb === 'create') {
    const customerName = parsedCmd.flags?.customer as string
    if (customerName) {
      const customers = await fetchCatalog(`/api/palette/customers?q=${encodeURIComponent(customerName)}`, controller.signal)
      if (controller.signal.aborted) return
      const match = customers.find((c: any) =>
        c.name?.toLowerCase() === customerName.toLowerCase() ||
        c.id === customerName
      )
      if (match?.meta?.currency) {
        nextDefaults.currency = { value: match.meta.currency, source: 'customer' }
      } else if (activeCompany.value?.base_currency) {
        nextDefaults.currency = { value: activeCompany.value.base_currency, source: 'company' }
      }
      if (match?.meta?.payment_terms) {
        nextDefaults.due = { value: `+${match.meta.payment_terms}d`, source: 'customer' }
      }
    } else if (activeCompany.value?.base_currency) {
      nextDefaults.currency = { value: activeCompany.value.base_currency, source: 'company' }
    }
  }

  // Payment create: invoice balance/currency
  if (parsedCmd.entity === 'payment' && parsedCmd.verb === 'create') {
    const invoiceToken = parsedCmd.flags?.invoice as string
    if (invoiceToken) {
      const invoices = await fetchCatalog('/api/palette/invoices/recent', controller.signal)
      if (controller.signal.aborted) return
      const match = invoices.find((i: any) =>
        i.number?.toLowerCase() === invoiceToken.toLowerCase() ||
        i.id === invoiceToken
      )
      if (match?.meta?.amount) {
        nextDefaults.amount = { value: match.meta.amount.replace(/[^\d.]/g, ''), source: 'invoice.balance' }
      }
      if (match?.meta?.amount_currency) {
        nextDefaults.currency = { value: match.meta.amount_currency, source: 'invoice' }
      }
    }
  }

  if (defaultsAbort.value === controller) {
    defaults.value = nextDefaults
    defaultsLoading.value = false
    defaultsAbort.value = null
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

  if (cmd === '?' || cmd.startsWith('?')) {
    const topic = cmd.slice(1).trim()
    const helpText = getHelp(topic ? `? ${topic}` : '?')
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

  const flagsWithDefaults = mergeFlagsWithDefaults(parsed.flags)
  const schema = getSchema(parsed.entity, parsed.verb)
  const missingArgs = getMissingRequiredArgs(schema, flagsWithDefaults)
  const parsedWithDefaults: ParsedCommand = { ...parsed, flags: flagsWithDefaults, complete: missingArgs.length === 0 }

  if (missingArgs.length > 0) {
    addOutput('input', `❯ ${cmd}`)
    addOutput('error', `{error}✗{/} Need ${missingArgs.map(a => a.name).join(', ')}`)
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
      parsed: parsedWithDefaults,
      message: `Confirm deletion`,
    }
    addToHistory(cmd)
    input.value = ''
    focusInput()
    return
  }

  // Normal execution
  await executeCommand(parsedWithDefaults)
  input.value = ''
  focusInput()
}

async function executeCommand(parsed: ParsedCommand) {
  const cmd = parsed.raw
  const resolvedCompanySlug = getRequestCompanySlug(parsed)
  const requiresCompany = needsCompanyContext(parsed)
  const params = mergeFlagsWithDefaults(parsed.flags)

  // Clear quick actions when new command executes
  tableState.value = null
  quickActions.value = []
  showSubPrompt.value = false
  subPromptAction.value = null
  subPromptInput.value = ''

  addOutput('input', `❯ ${cmd}`)
  addToHistory(cmd)
  recordCommandUse(cmd)
  frecencyScores.value = getFrecencyScores()
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
      body: JSON.stringify({ params }),
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

  // Space - commit entity during stage 1
  if (
    e.key === ' ' &&
    stage.value === 'entity' &&
    showSuggestions.value &&
    !input.value.includes(' ')
  ) {
    e.preventDefault()
    acceptSuggestion(true)
    return
  }

  // Tab - accept suggestion or execute complete command; keep focus inside palette
  if (e.key === 'Tab') {
    e.preventDefault()
    const backwards = e.shiftKey
    if (showSuggestions.value && suggestions.value.length > 0) {
      const wasEntityStage = stage.value === 'entity'
      acceptSuggestion(wasEntityStage)
      nextTick(() => {
        const newParsed = parse(input.value)
        if (wasEntityStage) {
          stage.value = 'verb'
          refreshSuggestions(input.value)
        } else if (stage.value === 'verb' && isCompleteWithDefaults(newParsed)) {
          execute()
        }
      })
      return
    }

    // No suggestions: move through required args in order
    const scaffold = scaffoldState.value
    if (scaffold) {
      const schema = getSchema(parsed.value.entity, parsed.value.verb)
      const argOrder = schema?.args.filter(a => a.required).map(a => a.name) || []
      const current = scaffold.currentArg
      let target = current
      if (argOrder.length) {
        const idx = argOrder.indexOf(current || '')
        if (backwards) {
          target = argOrder[Math.max(0, idx <= 0 ? argOrder.length - 1 : idx - 1)]
        } else {
          target = argOrder[Math.min(argOrder.length - 1, idx < 0 ? 0 : idx + 1)]
        }
      }
      if (target) {
        moveCursorToArg(target)
        refreshSuggestions(input.value)
      }
      return
    }

    if (resolvedComplete.value) {
      execute()
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

function acceptSuggestion(advanceStage = false) {
  const suggestion = suggestions.value[suggestionIndex.value] || suggestions.value[0]
  if (!suggestion) return

  // Use the value from the suggestion
  input.value = suggestion.value
  showSuggestions.value = false
  stage.value = advanceStage ? 'verb' : determineStage(input.value)
  nextTick(() => inputEl.value?.focus())
}

function moveCursorToArg(arg: string) {
  // Simple strategy: append placeholder if missing
  if (!input.value.includes(` ${arg}`) && !input.value.includes(`--${arg}`)) {
    input.value = `${input.value.trim()} ${arg} `.trim()
  }
  focusInput()
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

function mergeFlagsWithDefaults(flags: Record<string, unknown>): Record<string, unknown> {
  const merged: Record<string, unknown> = {}
  Object.entries(defaults.value).forEach(([key, meta]) => {
    if (meta?.value !== undefined && meta?.value !== null) {
      merged[key] = meta.value
    }
  })
  return { ...merged, ...flags }
}

function hasValue(value: unknown): boolean {
  if (value === undefined || value === null) return false
  if (typeof value === 'string') return value.trim().length > 0
  return true
}

function getMissingRequiredArgs(schema: any, flags: Record<string, unknown>): Array<{ name: string; hint?: string }> {
  if (!schema) return []
  const missingArgs = schema.args.filter((arg: any) => arg.required && !hasValue(flags[arg.name]))
  const missingFlags = (schema.flags || []).filter((flag: any) => flag.required && !hasValue(flags[flag.name]))
  return [...missingArgs, ...missingFlags]
}

function isCompleteWithDefaults(parsedCmd: ParsedCommand): boolean {
  if (!parsedCmd.entity || !parsedCmd.verb || parsedCmd.errors.length > 0) return false
  const schema = getSchema(parsedCmd.entity, parsedCmd.verb)
  const flags = mergeFlagsWithDefaults(parsedCmd.flags)
  return getMissingRequiredArgs(schema, flags).length === 0
}

// Click outside to close suggestions
function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.palette')) {
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
      <!-- Header - simplified -->
      <div class="palette-header">
        <div class="palette-company">
          <span class="palette-dot" :class="{ 'palette-dot--active': activeCompany }"></span>
          {{ activeCompany?.name || 'Global' }}
        </div>
        <span class="palette-esc">Esc to close</span>
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
          'palette-line--warning': line.type === 'warning',
        }"
        v-html="formatText(String(line.content))"
      ></div>
        </template>
      </div>

      <!-- Input area -->
      <div class="palette-input-area">
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
              @click="handleCursor"
              @keyup="handleCursor"
              @keydown="handleCursor"
            />
            <span
              v-if="scaffoldState?.ghosts?.length"
              class="palette-placeholder-hint"
              :style="{ '--typed-text-width': typedTextWidth }"
            >
              <template v-for="ghost in scaffoldState.ghosts" :key="ghost.label">
                <span :class="ghost.completed ? 'ghost-complete' : 'ghost-pending'">
                  {{ ghost.completed ? ghost.label : `<${ghost.label}>` }}
                </span>
                <span class="ghost-sep"> </span>
              </template>
            </span>
          </div>
          <div v-if="executing" class="palette-loading">
            <div class="palette-spinner"></div>
            <span class="palette-loading-text">Executing...</span>
          </div>
        </div>

        <!-- Skeleton zone -->
        <div v-if="scaffoldState" class="palette-skeleton">
          <div class="skeleton-row">
            <span class="skeleton-text">{{ scaffoldState.skeleton }}</span>
          </div>
          <div class="skeleton-pointer">
            <span class="skeleton-arrow">↑</span>
            <span class="skeleton-hint">{{ scaffoldState.pointerLabel }}</span>
            <span v-if="cursorIndex" class="skeleton-cursor">cursor: {{ cursorIndex }}</span>
          </div>
          <div class="skeleton-flags">
            <button
              v-for="flag in scaffoldState.optionalFlags"
              :key="flag.name"
              class="flag-chip"
              @click="applyFlagChip(flag)"
            >
              --{{ flag.name }}
              <span v-if="flag.value" class="flag-value">={{ flag.value }}</span>
              <span v-if="flag.source" class="flag-source" :title="flag.source">*</span>
              <span v-if="defaultsLoading && !flag.value" class="flag-loading">…</span>
            </button>
          </div>
          <div class="skeleton-status">
            <span v-if="scaffoldState.requiredRemaining.length">Required: {{ scaffoldState.requiredRemaining.join(', ') }}</span>
            <span v-else>Ready</span>
            <span class="status-optional">Optional: flags</span>
            <span v-if="validationHint" class="status-hint">{{ validationHint }}</span>
          </div>
        </div>

        <!-- Dropdown below input -->
        <div v-if="showSuggestions && suggestions.length" class="palette-dropdown">
          <div
            v-for="(suggestion, index) in suggestions"
            :key="suggestion.value"
            class="dropdown-item"
            :class="{ 'dropdown-item--selected': index === suggestionIndex }"
            @click="selectSuggestion(index)"
            @mouseenter="suggestionIndex = index"
          >
            <span class="dropdown-icon">{{ suggestion.icon || '📦' }}</span>
            <div class="dropdown-text">
              <span class="dropdown-label">{{ suggestion.label }}</span>
              <span v-if="stage === 'verb' && suggestion.description" class="dropdown-desc">
                {{ suggestion.description }}
              </span>
            </div>
            <kbd v-if="index === suggestionIndex">Tab</kbd>
          </div>
        </div>

        <!-- Helper bar -->
        <div class="palette-helper">
          <span class="palette-stage">
            {{ stage === 'entity' ? 'Entity' : 'Verb' }}
          </span>
          <div class="palette-keys">
            <span><kbd>↑↓</kbd> navigate</span>
            <span><kbd>Tab</kbd> {{ tabActionLabel }}</span>
            <span><kbd>Esc</kbd> close</span>
          </div>
        </div>

        <div v-if="contextRecords.length" class="palette-context">
          <div class="palette-context__title">{{ contextTitle }}</div>
          <div class="palette-context__chips">
            <button
              v-for="record in contextRecords"
              :key="record.value"
              class="palette-context__chip"
              @click="applyContextRecord(record)"
            >
              <span class="palette-context__label">{{ record.label }}</span>
              <span v-if="record.meta" class="palette-context__meta">{{ record.meta }}</span>
            </button>
          </div>
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
            :aria-label="`Quick action ${action.key}: ${getQuickActionLabel(action, tableState)}`"
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
  background: linear-gradient(to top, rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.15) 50%, transparent);
  z-index: 9998;
}

.palette {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 80vw;
  height: 80vh;
  max-width: 1400px;
  max-height: calc(100vh - 40px);
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 16px;
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
  font-size: 13px;
  font-weight: 500;
  color: #e2e8f0;
}

.palette-dot {
  width: 8px;
  height: 8px;
  background: #475569;
  border-radius: 50%;
}

.palette-dot--active {
  background: #22d3ee;
}

.palette-esc {
  font-size: 11px;
  color: #64748b;
}

/* Output */
.palette-output {
  flex: 1;
  overflow-y: auto;
  padding: 12px 14px;
  min-height: 120px;
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
.palette-line--warning {
  color: #f59e0b;
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
  display: flex;
  flex-direction: column;
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
  opacity: 0.5;
  white-space: nowrap;
  font: inherit;
  z-index: 1;
  padding-left: var(--typed-text-width, 0);
}

.ghost-complete {
  color: #64748b;
}

.ghost-pending {
  color: #94a3b8;
}

.ghost-sep {
  opacity: 0;
}

/* Helper */
.palette-dropdown {
  border-top: 1px solid #334155;
  max-height: 200px;
  overflow-y: auto;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  cursor: pointer;
  transition: background 0.1s;
}

.dropdown-item:hover {
  background: rgba(34, 211, 238, 0.05);
}

.dropdown-item--selected {
  background: rgba(34, 211, 238, 0.1);
}

.dropdown-item--selected .dropdown-label {
  color: #22d3ee;
}

.dropdown-icon {
  font-size: 16px;
  width: 20px;
  text-align: center;
}

.dropdown-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.dropdown-label {
  font-weight: 500;
  color: #e2e8f0;
  font-size: 13px;
  min-width: 100px;
}

.dropdown-desc {
  color: #64748b;
  font-size: 12px;
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dropdown-item kbd {
  font-size: 10px;
  padding: 2px 8px;
  background: rgba(34, 211, 238, 0.15);
  border: 1px solid rgba(34, 211, 238, 0.3);
  border-radius: 4px;
  color: #22d3ee;
  margin-left: auto;
}

.palette-helper {
  display: flex;
  align-items: center;
  padding: 8px 14px;
  border-top: 1px solid rgba(51, 65, 85, 0.5);
  font-size: 11px;
  color: #64748b;
  gap: 12px;
}

.palette-helper kbd {
  display: inline-block;
  background: rgba(255, 255, 255, 0.06);
  padding: 2px 6px;
  border-radius: 3px;
  margin-right: 4px;
  font-family: inherit;
  font-size: 10px;
}

.palette-stage {
  padding: 3px 10px;
  background: rgba(34, 211, 238, 0.1);
  border: 1px solid rgba(34, 211, 238, 0.25);
  border-radius: 4px;
  color: #22d3ee;
  font-weight: 600;
  font-size: 10px;
  text-transform: uppercase;
}

.palette-keys {
  display: flex;
  gap: 16px;
  margin-left: auto;
}

.palette-skeleton {
  padding: 10px 14px 8px;
  border-top: 1px solid #334155;
  background: rgba(34, 211, 238, 0.04);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-row {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  color: #cbd5e1;
  font-size: 13px;
}

.skeleton-pointer {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #94a3b8;
  font-size: 11px;
}

.skeleton-arrow {
  color: #22d3ee;
}

.skeleton-flags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.flag-chip {
  border: 1px solid rgba(34, 211, 238, 0.3);
  background: rgba(34, 211, 238, 0.08);
  color: #e2e8f0;
  border-radius: 8px;
  padding: 6px 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  transition: all 0.12s;
}

.flag-chip:hover {
  border-color: rgba(34, 211, 238, 0.5);
  background: rgba(34, 211, 238, 0.14);
}

.flag-value {
  color: #22d3ee;
}

.flag-source {
  color: #fbbf24;
}

.flag-loading {
  color: #94a3b8;
  font-size: 10px;
}

.skeleton-status {
  display: flex;
  gap: 12px;
  font-size: 11px;
  color: #94a3b8;
}

.status-optional {
  color: #64748b;
}

.status-hint {
  color: #f59e0b;
  margin-left: auto;
}

.palette-context {
  padding: 8px 14px 12px;
  border-top: 1px solid #334155;
  background: rgba(34, 211, 238, 0.04);
}

.palette-context__title {
  font-size: 12px;
  color: #94a3b8;
  margin-bottom: 6px;
}

.palette-context__chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.palette-context__chip {
  border: 1px solid rgba(34, 211, 238, 0.3);
  background: rgba(34, 211, 238, 0.08);
  color: #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  transition: all 0.15s;
}

.palette-context__chip:hover {
  border-color: rgba(34, 211, 238, 0.5);
  background: rgba(34, 211, 238, 0.14);
}

.palette-context__label {
  font-weight: 600;
}

.palette-context__meta {
  font-size: 11px;
  color: #94a3b8;
}

/* Helper suggestions (inline) */

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
