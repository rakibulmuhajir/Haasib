<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { parse } from '@/palette/parser'
import { generateSuggestions } from '@/palette/autocomplete'
import { formatTable } from '@/palette/table'
import { getHelp } from '@/palette/help'
import { usePage } from '@inertiajs/vue3'
import type { ParsedCommand } from '@/types/palette'

const props = defineProps<{ visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [v: boolean] }>()

// State
const input = ref('')
const output = ref<OutputLine[]>([])
const history = ref<string[]>(loadHistory())
const historyIndex = ref(-1)
const executing = ref(false)
const suggestions = ref<string[]>([])
const suggestionIndex = ref(0)
const showSuggestions = ref(false)

// Parsed command (reactive)
const parsed = computed(() => parse(input.value))

// Parsed display helpers
const hasFlags = computed(() => Object.keys(parsed.value.flags).length > 0)
const flagEntries = computed(() => Object.entries(parsed.value.flags))

// Refs
const inputEl = ref<HTMLInputElement>()
const outputEl = ref<HTMLDivElement>()

// Company context
const page = usePage()
const company = computed(() => (page.props.auth as any)?.currentCompany)
const companySlug = computed(() => company.value?.slug || '')

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
  } else {
    showSuggestions.value = false
    historyIndex.value = -1
  }
})

// Auto-scroll output
watch(() => output.value.length, () => {
  nextTick(() => {
    if (outputEl.value) outputEl.value.scrollTop = outputEl.value.scrollHeight
  })
})

// Update suggestions on input
watch(input, (val) => {
  if (val.trim()) {
    suggestions.value = generateSuggestions(val)
    showSuggestions.value = suggestions.value.length > 0
    suggestionIndex.value = 0
  } else {
    showSuggestions.value = false
  }
})

function close() {
  emit('update:visible', false)
  input.value = ''
}

function addOutput(type: OutputLine['type'], content: string | string[][], headers?: string[], footer?: string) {
  output.value.push({ type, content, headers, footer })
  // Keep max 200 lines
  if (output.value.length > 200) {
    output.value = output.value.slice(-200)
  }
}

async function execute() {
  const cmd = input.value.trim()
  if (!cmd || executing.value) return

  showSuggestions.value = false

  // Handle built-in commands
  if (cmd === 'clear' || cmd === 'cls') {
    output.value = []
    input.value = ''
    return
  }

  if (cmd === 'help' || cmd.startsWith('help ')) {
    const topic = cmd.slice(5).trim() || undefined
    const helpText = getHelp(topic)
    addOutput('output', helpText)
    addToHistory(cmd)
    input.value = ''
    return
  }

  // Parse command
  const parsed = parse(cmd)
  addOutput('input', `❯ ${cmd}`)

  if (parsed.errors.length > 0) {
    addOutput('error', `✗ ${parsed.errors.join(', ')}`)
    input.value = ''
    return
  }

  if (!parsed.entity || !parsed.verb) {
    addOutput('error', `✗ Unknown command. Type 'help' for available commands.`)
    input.value = ''
    return
  }

  addToHistory(cmd)
  input.value = ''
  executing.value = true

  try {
    const res = await fetch('/api/commands', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Action': `${parsed.entity}.${parsed.verb}`,
        'X-Company-Slug': companySlug.value,
        'X-Idempotency-Key': parsed.idemKey || generateIdemKey(parsed),
        'X-CSRF-TOKEN': getCsrfToken(),
      },
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
        addOutput('success', `✓ ${data.message}`)
      } 
      // Generic success
      else {
        addOutput('success', '✓ Done')
      }

      // Handle redirect
      if (data.redirect) {
        addOutput('output', `→ ${data.redirect}`)
      }
    } else {
      addOutput('error', `✗ ${data.message || 'Command failed'}`)
      if (data.errors) {
        Object.entries(data.errors).forEach(([field, messages]) => {
          addOutput('error', `  ${field}: ${(messages as string[]).join(', ')}`)
        })
      }
    }
  } catch (e) {
    addOutput('error', `✗ Network error: ${e instanceof Error ? e.message : 'Unknown'}`)
  } finally {
    executing.value = false
  }
}

function handleKeydown(e: KeyboardEvent) {
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
      acceptSuggestion()
    } else {
      execute()
    }
    return
  }

  // Tab - accept suggestion
  if (e.key === 'Tab' && showSuggestions.value) {
    e.preventDefault()
    acceptSuggestion()
    return
  }

  // Arrow navigation
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (showSuggestions.value) {
      suggestionIndex.value = Math.max(0, suggestionIndex.value - 1)
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
    } else if (historyIndex.value > 0) {
      historyIndex.value--
      input.value = history.value[historyIndex.value]
    } else if (historyIndex.value === 0) {
      historyIndex.value = -1
      input.value = ''
    }
    return
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
  if (suggestions.value[suggestionIndex.value]) {
    input.value = suggestions.value[suggestionIndex.value]
    showSuggestions.value = false
    nextTick(() => inputEl.value?.focus())
  }
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
})
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <div v-if="visible" class="palette-backdrop" @click="close" />

    <!-- Palette -->
    <div v-if="visible" class="palette" @keydown="handleKeydown">
      <!-- Header - minimal, just company context -->
      <div class="palette-header">
        <span class="palette-company">
          <span class="palette-company-icon">●</span>
          {{ company?.name || 'No company' }}
        </span>
        <div class="palette-header-right">
          <span class="palette-shortcut">Esc to close</span>
        </div>
      </div>

      <!-- Output -->
      <div ref="outputEl" class="palette-output">
        <template v-if="output.length === 0">
          <div class="palette-empty">
            Type a command or <span class="palette-cmd">help</span> for available commands
          </div>
        </template>
        <template v-for="(line, i) in output" :key="i">
          <!-- Table -->
          <div v-if="line.type === 'table'" class="palette-table">
            <pre>{{ formatTable(line.content as string[][], line.headers, line.footer) }}</pre>
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
          >{{ line.content }}</div>
        </template>
      </div>

      <!-- Input area -->
      <div class="palette-input-area">
        <!-- Autocomplete dropdown (above input) -->
        <div v-if="showSuggestions && suggestions.length" class="palette-autocomplete">
          <div
            v-for="(suggestion, index) in suggestions"
            :key="suggestion"
            class="palette-autocomplete-item"
            :class="{ 'palette-autocomplete-item--selected': index === suggestionIndex }"
            @click="selectSuggestion(index)"
            @mouseenter="suggestionIndex = index"
          >
            <span>{{ suggestion }}</span>
            <kbd v-if="index === suggestionIndex">Tab</kbd>
          </div>
        </div>

        <!-- Input row -->
        <div class="palette-input-row">
          <span class="palette-prompt" :class="{ 'palette-prompt--busy': executing }">
            {{ executing ? '⋯' : '❯' }}
          </span>
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
  top: 10vh;
  left: 50%;
  transform: translateX(-50%);
  width: 680px;
  max-width: calc(100vw - 40px);
  max-height: 70vh;
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
}

.palette-prompt--busy {
  color: #f59e0b;
}

.palette-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #e2e8f0;
  font: inherit;
  caret-color: #22d3ee;
}

.palette-input::placeholder {
  color: #475569;
}

.palette-input:disabled {
  opacity: 0.5;
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
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  color: #e2e8f0;
  cursor: pointer;
  transition: background 0.1s;
}

.palette-autocomplete-item:hover,
.palette-autocomplete-item--selected {
  background: rgba(34, 211, 238, 0.1);
}

.palette-autocomplete-item--selected {
  color: #22d3ee;
}

.palette-autocomplete-item kbd {
  font-size: 10px;
  padding: 2px 6px;
  background: rgba(34, 211, 238, 0.2);
  border-radius: 3px;
  color: #22d3ee;
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
</style>
