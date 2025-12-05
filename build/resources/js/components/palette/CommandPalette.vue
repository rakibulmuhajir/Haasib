<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted, Transition } from 'vue'
import { parse } from '@/palette/parser'
import { generateSuggestions } from '@/palette/autocomplete'
import { formatText } from '@/palette/formatter'
import { resolveEntityShortcut, getVerbs, ENTITY_ICONS } from '@/palette/grammar'
import { getSchema } from '@/palette/schemas'
import { getFrecencyScores, recordCommandUse } from '@/palette/frecency'
import { getCommandExample } from '@/palette/grammar'
import { getQuickActions, resolveQuickActionCommand, getQuickActionLabel } from '@/palette/quick-actions'
import { usePage, router } from '@inertiajs/vue3'
import type { ParsedCommand, Suggestion, OutputLine, QuickAction, TableState } from '@/types/palette'

const props = defineProps<{ visible: boolean }>()
const emit = defineEmits<{
  'update:visible': [v: boolean]
  'company-switched': [company: { id: string; name: string; slug: string; base_currency: string }]
}>()

// ============================================================================
// Types
// ============================================================================

type Stage = 'entity' | 'verb' | 'chips'

interface ChipDef {
  name: string
  shortcut: string
  required: boolean
  hasEnum: boolean
  hint?: string
  /** Entity type for DB search (e.g., 'customer', 'invoice') */
  searchEntity?: string
}

interface ChipState extends ChipDef {
  value: string
  /** Display label for the selected value (for entity search) */
  displayLabel?: string
  isActive: boolean
  status: 'empty' | 'filled' | 'error'
}

// ============================================================================
// State
// ============================================================================

// Core input state
const entityVerbInput = ref('')
const stage = ref<Stage>('entity')
const confirmedEntity = ref('')
const confirmedVerb = ref('')

// Chip state
const chips = ref<ChipState[]>([])
const activeChipIndex = ref(-1)
const chipInputRefs = ref<Record<number, HTMLInputElement | null>>({})

// Suggestions state
const suggestions = ref<Suggestion[]>([])
const suggestionIndex = ref(0)
const showSuggestions = ref(false)
const chipSuggestions = ref<Array<{ value: string; label: string; icon?: string; meta?: string }>>([])
const chipSuggestionsLoading = ref(false)
const chipSuggestionIndex = ref(0)
const showChipDropdown = ref(false)
// Search query for entity search chips (separate from chip.value which stores the selected ID)
const entitySearchQuery = ref('')

// Sidebar state (quick picks for current chip)
interface SidebarItem {
  value: string
  label: string
  icon?: string
  meta?: string
}
const sidebarItems = ref<SidebarItem[]>([])
const sidebarIndex = ref(0)
const sidebarTitle = ref('Quick Picks')

// Output state
const output = ref<OutputLine[]>([])
const executing = ref(false)
const history = ref<string[]>(loadHistory())
const historyIndex = ref(-1)

// Defaults from API
const defaults = ref<Record<string, { value: string; source?: string }>>({})
const frecencyScores = ref<Record<string, number>>(getFrecencyScores())

// Quick actions state
const tableState = ref<TableState | null>(null)
const quickActions = ref<QuickAction[]>([])
const showSubPrompt = ref(false)
const subPromptAction = ref<QuickAction | null>(null)
const subPromptInput = ref('')
const subPromptInputEl = ref<HTMLInputElement>()

// ============================================================================
// Helper Functions
// ============================================================================

// Check if the current input contains a complete command with all required fields
function checkForCompleteCommand(input: string): boolean {
  const parsed = parse(input)

  // Must have entity and verb
  if (!parsed.entity || !parsed.verb) {
    return false
  }

  // Get the schema to check required fields
  const schema = getSchema(parsed.entity, parsed.verb)
  if (!schema) {
    return false
  }

  // Check all required args are provided
  const requiredFields = schema.args.filter(arg => arg.required).map(arg => arg.name)

  // All required fields must be present in parsed flags
  for (const field of requiredFields) {
    if (!(field in parsed.flags) || !parsed.flags[field]) {
      return false
    }
  }

  return true
}

// Execute a command directly from input string
async function executeDirectCommand(input: string) {
  const parsed = parse(input)

  if (!parsed.entity || !parsed.verb) {
    addOutput('error', '✗ Invalid command format')
    return
  }

  await executeCommandDirectly(input)
}

// ============================================================================
// Refs
// ============================================================================

const entityVerbInputEl = ref<HTMLInputElement>()
const outputEl = ref<HTMLDivElement>()

// ============================================================================
// Computed
// ============================================================================

// Local company state for palette operations (overrides page props when set)
const localActiveCompany = ref<{ id: string; name: string; slug: string; base_currency: string } | null>(null)

const activeCompany = computed(() => {
  // Priority: local state (from palette switch) > Inertia page props
  if (localActiveCompany.value) {
    return localActiveCompany.value
  }
  const page = usePage()
  const authCompany = (page.props.auth as any)?.currentCompany
  return authCompany || null
})

const currentSchema = computed(() => {
  if (!confirmedEntity.value || !confirmedVerb.value) return null
  return getSchema(confirmedEntity.value, confirmedVerb.value)
})

const allRequiredFilled = computed(() => {
  return chips.value
    .filter(c => c.required)
    .every(c => c.value.trim() !== '')
})

const canExecute = computed(() => {
  return stage.value === 'chips' && allRequiredFilled.value
})


const statusMessage = computed(() => {
  if (stage.value === 'entity') return 'Type a command or entity (e.g., invoice, company, customer)'
  if (stage.value === 'verb') return `Select action for ${confirmedEntity.value}`
  if (!allRequiredFilled.value) {
    const missing = chips.value.filter(c => c.required && !c.value.trim())
    return `Required: ${missing.map(c => c.name).join(', ')}`
  }
  return 'Ready — press Enter to execute'
})

const showSidebar = computed(() => {
  // Show sidebar when we have quick actions or chip enum items
  return quickActions.value.length > 0 || (sidebarItems.value.length > 0 && stage.value === 'chips')
})

const showQuickActions = computed(() => {
  // Show quick actions when available
  return quickActions.value.length > 0
})

// ============================================================================
// Watchers
// ============================================================================

watch(() => props.visible, (visible) => {
  if (visible) {
    nextTick(() => entityVerbInputEl.value?.focus())
  } else {
    resetState()
  }
})

watch(confirmedEntity, async (newEntity, oldEntity) => {
  if (newEntity && newEntity !== oldEntity && stage.value === 'verb') {
    await refreshVerbSuggestions()
  }
})

watch(entityVerbInput, async (val, oldVal) => {
  const trimmed = val.trim()
  const tokens = trimmed.split(/\s+/).filter(t => t)
  const hasTrailingSpace = val.endsWith(' ')

  // Stage: Entity
  if (stage.value === 'entity') {
    if (tokens.length === 1 && hasTrailingSpace) {
      // User pressed space after entity - try to confirm
      const resolved = resolveEntityShortcut(tokens[0])
      if (resolved) {
        confirmedEntity.value = resolved
        entityVerbInput.value = resolved + ' '
        stage.value = 'verb'
        await refreshVerbSuggestions()
        return
      }
    }
    // Show entity suggestions
    await refreshEntitySuggestions(trimmed)
    return
  }

  // Stage: Verb
  if (stage.value === 'verb') {
    // Check if entity is being cleared (user backspaced into entity)
    if (tokens.length === 0 || (tokens.length === 1 && !hasTrailingSpace)) {
      const firstToken = tokens[0] || ''

      // If the first token no longer matches the confirmed entity, go back to entity stage
      if (!confirmedEntity.value.startsWith(firstToken) && firstToken !== confirmedEntity.value) {
        stage.value = 'entity'
        confirmedEntity.value = ''
        confirmedVerb.value = ''
        await refreshEntitySuggestions(firstToken)
        return
      }

      // Check if entity is partially deleted
      if (firstToken.length < confirmedEntity.value.length) {
        stage.value = 'entity'
        confirmedEntity.value = ''
        confirmedVerb.value = ''
        await refreshEntitySuggestions(firstToken)
        return
      }
    }

    // Check if user changed the entity (first token)
    if (tokens.length > 0) {
      const firstToken = tokens[0]
      const resolvedEntity = resolveEntityShortcut(firstToken)

      if (resolvedEntity && resolvedEntity !== confirmedEntity.value) {
        // Entity changed, update everything
        confirmedEntity.value = resolvedEntity
        entityVerbInput.value = resolvedEntity + ' '
        confirmedVerb.value = ''
        await refreshVerbSuggestions()
        return
      }
    }

    if (tokens.length >= 2 && hasTrailingSpace) {
      // User pressed space after verb - try to confirm
      const verb = tokens[1]
      const verbs = getVerbs(confirmedEntity.value)
      if (verbs.includes(verb)) {
        confirmedVerb.value = verb
        stage.value = 'chips'
        showSuggestions.value = false
        initializeChips()
        return
      }
    }

    // Show verb suggestions
    const partial = tokens[1] || ''
    await refreshVerbSuggestions(partial)
    return
  }

  // Stage: Chips - check if verb was removed
  if (stage.value === 'chips') {
    const expectedPrefix = `${confirmedEntity.value} ${confirmedVerb.value}`
    if (trimmed.length < expectedPrefix.length) {
      // User deleted part of entity+verb, go back
      stage.value = 'verb'
      chips.value = []
      activeChipIndex.value = -1
      showChipDropdown.value = false
      confirmedVerb.value = ''
      await refreshVerbSuggestions()
    }
  }
})

watch(output, (newOutput) => {
  nextTick(() => {
    if (outputEl.value) outputEl.value.scrollTop = outputEl.value.scrollHeight
  })

  // Watch output for table data to populate tableState
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
    rowIds: lastTable.rowIds,
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

// ============================================================================
// Suggestion Functions
// ============================================================================

async function refreshEntitySuggestions(query: string) {
  if (!query) {
    suggestions.value = []
    showSuggestions.value = false
    return
  }

  const results = generateSuggestions(query, {
    stage: 'entity',
    frecencyScores: frecencyScores.value,
  })
  suggestions.value = results.slice(0, 8)
  showSuggestions.value = results.length > 0
  suggestionIndex.value = 0
}

async function refreshVerbSuggestions(partial = '') {
  const verbs = getVerbs(confirmedEntity.value)
  const lowerPartial = partial.toLowerCase()

  const verbSuggestions = verbs
    .map(verb => ({
      type: 'verb' as const,
      value: verb,
      label: verb,
      icon: ENTITY_ICONS[confirmedEntity.value] || '📄',
      score: verb.toLowerCase().startsWith(lowerPartial) ? 100 : 50,
    }))
    .filter(v => !partial || v.label.toLowerCase().includes(lowerPartial))
    .sort((a, b) => b.score - a.score)

  suggestions.value = verbSuggestions
  showSuggestions.value = verbSuggestions.length > 0
  suggestionIndex.value = 0
}

// Debounce timer for entity search
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

async function fetchChipSuggestions(chipName: string, query: string, searchEntity?: string) {
  if (!confirmedEntity.value || !confirmedVerb.value) return

  // Get current chip to check for searchEntity
  const currentChip = chips.value[activeChipIndex.value]
  const entityToSearch = searchEntity || currentChip?.searchEntity

  // If this is an entity search field, use the API with debouncing
  if (entityToSearch) {
    // Clear previous timer
    if (searchDebounceTimer) {
      clearTimeout(searchDebounceTimer)
    }

    // Require minimum 3 characters before searching (prevents excessive API calls)
    if (query.length > 0 && query.length < 3) {
      chipSuggestions.value = []
      chipSuggestionsLoading.value = false
      showChipDropdown.value = true  // Show "type at least 3 characters" message
      return
    }

    chipSuggestionsLoading.value = true
    showChipDropdown.value = true

    // Debounce the search (300ms for typing, immediate for initial load)
    const delay = query ? 300 : 0
    searchDebounceTimer = setTimeout(async () => {
      try {
        const params = new URLSearchParams({
          search_entity: entityToSearch,
          q: query,
        })

        // Add company slug header if available
        const headers: Record<string, string> = {
          'Accept': 'application/json',
        }
        if (activeCompany.value?.slug) {
          headers['X-Company-Slug'] = activeCompany.value.slug
        }

        const res = await fetch(`/api/palette/flag-values?${params}`, {
          credentials: 'same-origin',
          headers,
        })

        if (!res.ok) {
          throw new Error('API returned error')
        }

        const data = await res.json()
        const items = (data.values || []).slice(0, 10)

        chipSuggestions.value = items
        chipSuggestionIndex.value = 0
        showChipDropdown.value = true
        sidebarItems.value = []  // Don't show in sidebar for entity search
        sidebarTitle.value = entityToSearch.charAt(0).toUpperCase() + entityToSearch.slice(1) + 's'
      } catch (e) {
        chipSuggestions.value = []
        showChipDropdown.value = true  // Keep dropdown open to show "no results"
      } finally {
        chipSuggestionsLoading.value = false
      }
    }, delay)

    return
  }

  // Check if this is a date field - provide quick date options
  const dateFields = ['due', 'date', 'from', 'to', 'payment_date', 'invoice_date', 'bill_date']
  const isDateField = dateFields.some(f => chipName.toLowerCase().includes(f))

  if (isDateField) {
    const today = new Date()
    const formatDate = (d: Date) => d.toISOString().split('T')[0]
    const addDays = (d: Date, days: number) => {
      const result = new Date(d)
      result.setDate(result.getDate() + days)
      return result
    }

    const dateOptions: Array<{ value: string; label: string; icon?: string }> = [
      { value: formatDate(today), label: 'Today', icon: '📅' },
      { value: formatDate(addDays(today, 1)), label: 'Tomorrow', icon: '📅' },
      { value: formatDate(addDays(today, 7)), label: 'In 1 week', icon: '📅' },
      { value: formatDate(addDays(today, 14)), label: 'In 2 weeks', icon: '📅' },
      { value: formatDate(addDays(today, 30)), label: 'In 30 days', icon: '📅' },
      { value: formatDate(addDays(today, 45)), label: 'In 45 days', icon: '📅' },
      { value: formatDate(addDays(today, 60)), label: 'In 60 days', icon: '📅' },
      { value: formatDate(addDays(today, 90)), label: 'In 90 days', icon: '📅' },
    ]

    // Add end of month options
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)
    dateOptions.push({ value: formatDate(endOfMonth), label: 'End of month', icon: '📅' })

    // Filter by query if provided
    let items = dateOptions
    if (query) {
      const q = query.toLowerCase()
      items = dateOptions.filter(item =>
        item.value.includes(q) ||
        item.label.toLowerCase().includes(q)
      )
    }

    chipSuggestions.value = items.slice(0, 9)
    chipSuggestionIndex.value = 0
    showChipDropdown.value = items.length > 0
    sidebarItems.value = items.slice(0, 9)
    sidebarIndex.value = 0
    sidebarTitle.value = 'Quick Dates'
    return
  }

  // For other static dropdown fields, use the API
  try {
    const params = new URLSearchParams({
      entity: confirmedEntity.value,
      verb: confirmedVerb.value,
      flag: chipName,
      q: query,
    })
    const res = await fetch(`/api/palette/flag-values?${params}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    })
    if (!res.ok) {
      throw new Error('API returned error')
    }
    const data = await res.json()
    const items = (data.values || []).slice(0, 9)

    if (items.length > 0) {
      chipSuggestions.value = items
      chipSuggestionIndex.value = 0
      showChipDropdown.value = true
      sidebarItems.value = items
      sidebarIndex.value = 0
      sidebarTitle.value = chipName.charAt(0).toUpperCase() + chipName.slice(1)
      return
    }
  } catch (e) {
    // Fallback silently
  }

  // Clear suggestions if nothing found
  chipSuggestions.value = []
  showChipDropdown.value = false
}

// ============================================================================
// Chip Functions
// ============================================================================

function initializeChips() {
  const schema = currentSchema.value
  if (!schema) return

  const allChips: ChipState[] = []
  const usedShortcuts = new Set<string>()

  // Helper to get unique shortcut
  const getShortcut = (name: string): string => {
    for (const char of name.toLowerCase()) {
      if (!usedShortcuts.has(char) && /[a-z]/.test(char)) {
        usedShortcuts.add(char)
        return char
      }
    }
    return ''
  }

  // Add required args first
  schema.args.forEach(arg => {
    const shortcut = getShortcut(arg.name)
    const defaultValue = defaults.value[arg.name]?.value || ''
    allChips.push({
      name: arg.name,
      shortcut,
      required: arg.required,
      // hasEnum comes from schema, or check if arg has 'enum', 'options', 'hasDropdown', or 'searchEntity'
      hasEnum: !!(arg.enum || arg.options || arg.hasDropdown || arg.searchEntity),
      searchEntity: arg.searchEntity,
      hint: arg.hint,
      value: defaultValue,
      isActive: false,
      status: defaultValue ? 'filled' : 'empty',
    })
  })

  // Add optional flags
  schema.flags.forEach(flag => {
    const shortcut = getShortcut(flag.name)
    const defaultValue = defaults.value[flag.name]?.value || ''
    allChips.push({
      name: flag.name,
      shortcut,
      required: false,
      // hasEnum comes from schema, or check if flag has 'enum', 'options', 'hasDropdown', or 'searchEntity'
      hasEnum: !!(flag.enum || flag.options || flag.hasDropdown || flag.searchEntity),
      searchEntity: flag.searchEntity,
      hint: flag.hint,
      value: defaultValue,
      isActive: false,
      status: defaultValue ? 'filled' : 'empty',
    })
  })

  chips.value = allChips

  // Focus first required empty chip
  const firstEmptyRequired = allChips.findIndex(c => c.required && !c.value)
  if (firstEmptyRequired >= 0) {
    activateChip(firstEmptyRequired)
  } else {
    // All required filled (from defaults), focus first empty optional or first chip
    const firstEmpty = allChips.findIndex(c => !c.value)
    activateChip(firstEmpty >= 0 ? firstEmpty : 0)
  }
}

function activateChip(index: number) {
  if (index < 0 || index >= chips.value.length) return

  // Deactivate all
  chips.value.forEach((c, i) => {
    c.isActive = i === index
  })
  activeChipIndex.value = index

  const chip = chips.value[index]

  // Reset entity search query when activating a new chip
  entitySearchQuery.value = ''

  // Fetch suggestions if has enum
  if (chip.hasEnum) {
    // For entity search, pass empty string to get initial results
    fetchChipSuggestions(chip.name, chip.searchEntity ? '' : chip.value)
  } else {
    chipSuggestions.value = []
    showChipDropdown.value = false
  }

  // Focus input
  nextTick(() => {
    const input = chipInputRefs.value[index]
    if (input) {
      input.focus()
      input.select()
    }
  })
}

function deactivateChips() {
  chips.value.forEach(c => c.isActive = false)
  activeChipIndex.value = -1
  showChipDropdown.value = false
  chipSuggestions.value = []
  sidebarItems.value = []
}

function setChipInputRef(index: number, el: HTMLInputElement | null) {
  chipInputRefs.value[index] = el
}

function selectSidebarItem(index: number) {
  const item = sidebarItems.value[index]
  if (!item || activeChipIndex.value < 0) return

  const chip = chips.value[activeChipIndex.value]
  chip.value = item.value
  chip.status = 'filled'
  showChipDropdown.value = false

  // Move to next chip
  goToNextChip()
}

function handleChipInput(index: number, value: string) {
  const chip = chips.value[index]
  if (!chip) return

  // For entity search chips, update the search query (not the chip value yet)
  if (chip.searchEntity) {
    entitySearchQuery.value = value
    // Don't update chip.value until a selection is made
    // Fetch suggestions based on search query
    fetchChipSuggestions(chip.name, value)
  } else {
    chip.value = value
    chip.status = value.trim() ? 'filled' : 'empty'

    // Fetch new suggestions if has enum
    if (chip.hasEnum) {
      fetchChipSuggestions(chip.name, value)
    }
  }
}

function selectChipSuggestion(suggestionIdx: number) {
  const suggestion = chipSuggestions.value[suggestionIdx]
  if (!suggestion || activeChipIndex.value < 0) return

  const chip = chips.value[activeChipIndex.value]
  chip.value = suggestion.value
  // Store display label for entity search fields
  chip.displayLabel = suggestion.label
  chip.status = 'filled'
  showChipDropdown.value = false
  chipSuggestionsLoading.value = false
  // Reset search query after selection
  entitySearchQuery.value = ''

  // Move to next chip
  goToNextChip()
}

/**
 * Move to the next chip.
 * @param force - If true, always move to next chip (used by Tab key).
 *                If false, skip optional chips when all required are filled (used after value selection).
 */
function goToNextChip(force = false) {
  const current = activeChipIndex.value
  const next = current + 1

  // Check if all required chips are filled
  const allRequiredFilled = chips.value
    .filter(c => c.required)
    .every(c => c.value.trim())

  if (next < chips.value.length) {
    const nextChip = chips.value[next]

    // If force (Tab key), always go to next chip
    if (force) {
      activateChip(next)
      return
    }

    // If next chip is required, activate it
    // If next chip is optional AND all required are filled, don't auto-activate
    if (nextChip.required) {
      activateChip(next)
    } else if (!allRequiredFilled) {
      // Still have unfilled required chips, activate next anyway
      activateChip(next)
    } else {
      // All required filled, optional chip is next - don't auto-activate
      // User can press Enter to execute or Tab to move to optional chips
      deactivateChips()
    }
  } else {
    // At last chip, check if can execute
    if (canExecute.value) {
      deactivateChips()
      // Don't auto-execute, let user press Enter
    } else {
      // Go back to first empty required
      const firstEmptyRequired = chips.value.findIndex(c => c.required && !c.value.trim())
      if (firstEmptyRequired >= 0) {
        activateChip(firstEmptyRequired)
      }
    }
  }
}

function goToPrevChip() {
  const current = activeChipIndex.value
  const prev = current - 1

  if (prev >= 0) {
    activateChip(prev)
  } else {
    // At first chip, go back to entity/verb input
    deactivateChips()
    stage.value = 'verb'
    entityVerbInputEl.value?.focus()
  }
}

function handleChipKeydown(e: KeyboardEvent, index: number) {
  const chip = chips.value[index]
  if (!chip) return

  // Backspace - clear chip and go to previous if empty
  if (e.key === 'Backspace') {
    if (chip.value === '') {
      e.preventDefault()
      // If this is the first chip, go back to verb input
      if (index === 0) {
        deactivateChips()
        stage.value = 'verb'
        chips.value = []
        entityVerbInput.value = confirmedEntity.value + ' ' + confirmedVerb.value
        confirmedVerb.value = ''
        refreshVerbSuggestions()
        entityVerbInputEl.value?.focus()
      } else {
        // Go to previous chip and clear it too
        const prevChip = chips.value[index - 1]
        if (prevChip) {
          prevChip.value = ''
          prevChip.status = 'empty'
        }
        activateChip(index - 1)
      }
      return
    }
    // Let default behavior clear the value
    return
  }

  // Tab - next chip (force=true to always navigate, even to optional chips)
  if (e.key === 'Tab' && !e.shiftKey) {
    e.preventDefault()
    goToNextChip(true)
    return
  }

  // Shift+Tab - prev chip
  if (e.key === 'Tab' && e.shiftKey) {
    e.preventDefault()
    goToPrevChip()
    return
  }

  // Enter - select dropdown item or execute
  if (e.key === 'Enter') {
    e.preventDefault()
    if (showChipDropdown.value && chipSuggestions.value.length > 0) {
      selectChipSuggestion(chipSuggestionIndex.value)
    } else if (canExecute.value) {
      execute()
    } else {
      goToNextChip()
    }
    return
  }

  // Escape - close dropdown or go back
  if (e.key === 'Escape') {
    e.preventDefault()
    if (showChipDropdown.value) {
      showChipDropdown.value = false
    } else {
      deactivateChips()
      entityVerbInputEl.value?.focus()
    }
    return
  }

  // Arrow down - open/navigate dropdown
  if (e.key === 'ArrowDown') {
    if (chip.hasEnum) {
      e.preventDefault()
      if (!showChipDropdown.value) {
        fetchChipSuggestions(chip.name, chip.value)
        showChipDropdown.value = true
      } else {
        chipSuggestionIndex.value = Math.min(
          chipSuggestions.value.length - 1,
          chipSuggestionIndex.value + 1
        )
      }
    }
    return
  }

  // Arrow up - navigate dropdown
  if (e.key === 'ArrowUp') {
    if (showChipDropdown.value) {
      e.preventDefault()
      chipSuggestionIndex.value = Math.max(0, chipSuggestionIndex.value - 1)
    }
    return
  }

  // Number keys 1-9 - select from sidebar or dropdown
  if (/^[1-9]$/.test(e.key)) {
    const idx = parseInt(e.key) - 1
    // Prefer dropdown if visible, else sidebar
    if (showChipDropdown.value && idx < chipSuggestions.value.length) {
      e.preventDefault()
      selectChipSuggestion(idx)
      return
    }
    if (sidebarItems.value.length > 0 && idx < sidebarItems.value.length) {
      e.preventDefault()
      selectSidebarItem(idx)
      return
    }
  }
}

function handleChipClick(index: number) {
  activateChip(index)
}

function handleDropdownToggle(index: number, e: Event) {
  e.stopPropagation()
  const chip = chips.value[index]
  if (!chip.hasEnum) return

  if (activeChipIndex.value === index && showChipDropdown.value) {
    showChipDropdown.value = false
  } else {
    activateChip(index)
    fetchChipSuggestions(chip.name, chip.value)
    showChipDropdown.value = true
  }
}

// ============================================================================
// Entity/Verb Input Keydown
// ============================================================================

function handleEntityVerbKeydown(e: KeyboardEvent) {
  // Escape - close palette
  if (e.key === 'Escape') {
    if (showSuggestions.value) {
      showSuggestions.value = false
    } else {
      close()
    }
    return
  }

  // Backspace - handle stage transitions
  if (e.key === 'Backspace') {
    const input = entityVerbInput.value
    const trimmed = input.trim()
    const tokens = trimmed.split(/\s+/)

    // In chips stage - if input ends with entity+verb only, go back to verb stage
    if (stage.value === 'chips') {
      // Check if we're about to delete all the way back
      const expectedPrefix = `${confirmedEntity.value} ${confirmedVerb.value}`
      if (trimmed === expectedPrefix || trimmed.length <= expectedPrefix.length) {
        // User is backspacing into the entity+verb area, go back to verb stage
        e.preventDefault()
        stage.value = 'verb'
        chips.value = []
        activeChipIndex.value = -1
        showChipDropdown.value = false
        entityVerbInput.value = confirmedEntity.value + ' '
        confirmedVerb.value = ''
        refreshVerbSuggestions()
        return
      }
    }

    // In verb stage - if input is just entity + space, go back to entity stage
    if (stage.value === 'verb' && tokens.length <= 1) {
      // Check if cursor is at the end of the entity
      if (trimmed === confirmedEntity.value || trimmed.length < confirmedEntity.value.length) {
        e.preventDefault()
        stage.value = 'entity'
        confirmedEntity.value = ''
        confirmedVerb.value = ''
        entityVerbInput.value = trimmed.slice(0, -1) // remove last char
        refreshEntitySuggestions(entityVerbInput.value)
        return
      }
    }

    // Let the default backspace behavior happen
    return
  }

  // Enter - accept suggestion or execute complete command
  if (e.key === 'Enter') {
    e.preventDefault()
    if (showSuggestions.value && suggestions.value.length > 0) {
      acceptSuggestion()
      // After accepting, check if we can execute immediately (no required fields)
      nextTick(() => {
        if (canExecute.value) {
          execute()
        }
      })
    } else if (stage.value === 'chips' && canExecute.value) {
      execute()
    } else if (stage.value === 'chips' && !canExecute.value) {
      // Go to first empty required chip
      const firstEmptyRequired = chips.value.findIndex(c => c.required && !c.value.trim())
      if (firstEmptyRequired >= 0) {
        activateChip(firstEmptyRequired)
      }
    } else {
      // Check if current input is a complete command that can be executed directly
      const input = entityVerbInput.value.trim()
      if (checkForCompleteCommand(input)) {
        executeDirectCommand(input)
        resetForNextCommand()
      }
    }
    return
  }

  // Tab - accept suggestion or go to chips
  if (e.key === 'Tab') {
    e.preventDefault()
    if (showSuggestions.value && suggestions.value.length > 0) {
      acceptSuggestion()
    } else if (stage.value === 'chips' && chips.value.length > 0) {
      activateChip(0)
    }
    return
  }

  // Arrow down - navigate suggestions or table rows
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (showSuggestions.value) {
      suggestionIndex.value = Math.min(suggestions.value.length - 1, suggestionIndex.value + 1)
    } else if (tableState.value && tableState.value.rows.length > 0) {
      // Navigate table rows
      tableState.value.selectedRowIndex = Math.min(
        tableState.value.rows.length - 1,
        tableState.value.selectedRowIndex + 1
      )
    }
    return
  }

  // Arrow up - navigate suggestions or table rows
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (showSuggestions.value) {
      suggestionIndex.value = Math.max(0, suggestionIndex.value - 1)
    } else if (tableState.value && tableState.value.rows.length > 0) {
      // Navigate table rows
      tableState.value.selectedRowIndex = Math.max(0, tableState.value.selectedRowIndex - 1)
    }
    return
  }

  // Arrow right/left for horizontal navigation
  if (e.key === 'ArrowRight' && showSuggestions.value) {
    e.preventDefault()
    suggestionIndex.value = Math.min(suggestions.value.length - 1, suggestionIndex.value + 1)
    return
  }
  if (e.key === 'ArrowLeft' && showSuggestions.value) {
    e.preventDefault()
    suggestionIndex.value = Math.max(0, suggestionIndex.value - 1)
    return
  }

  // Ctrl+L - clear input
  if (e.key === 'l' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    clearInput()
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
}

function acceptSuggestion() {
  const suggestion = suggestions.value[suggestionIndex.value]
  if (!suggestion) return

  if (stage.value === 'entity') {
    const entity = suggestion.value.split(' ')[0]
    const resolved = resolveEntityShortcut(entity) || entity
    confirmedEntity.value = resolved
    entityVerbInput.value = resolved + ' '
    stage.value = 'verb'
    refreshVerbSuggestions()
  } else if (stage.value === 'verb') {
    confirmedVerb.value = suggestion.value
    entityVerbInput.value = confirmedEntity.value + ' ' + suggestion.value + ' '
    stage.value = 'chips'
    showSuggestions.value = false
    initializeChips()

    // If no required fields or all filled, execute immediately on next Enter
    // (canExecute will be true after initializeChips if no required args)
  }
}

function selectSuggestion(index: number) {
  suggestionIndex.value = index
  acceptSuggestion()
}

// ============================================================================
// Execute
// ============================================================================

async function execute() {
  if (!canExecute.value) return

  const entity = confirmedEntity.value
  const verb = confirmedVerb.value

  // Build params from chips
  const params: Record<string, string> = {}
  chips.value.forEach(chip => {
    if (chip.value.trim()) {
      params[chip.name] = chip.value.trim()
    }
  })

  // Build display command
  const chipDisplay = chips.value
    .filter(c => c.value.trim())
    .map(c => c.required ? c.value : `--${c.name}=${c.value}`)
    .join(' ')
  const displayCmd = `${entity} ${verb} ${chipDisplay}`.trim()

  addOutput('input', `❯ ${displayCmd}`)
  addToHistory(displayCmd)
  recordCommandUse(displayCmd)
  frecencyScores.value = getFrecencyScores()
  executing.value = true

  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Action': `${entity}.${verb}`,
      'X-CSRF-TOKEN': getCsrfToken(),
    }

    if (activeCompany.value?.slug) {
      headers['X-Company-Slug'] = activeCompany.value.slug
    }

    const res = await fetch('/api/palette/execute', {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({
        entity,
        verb,
        params,
      }),
    })

    const data = await res.json()

    if (data.ok) {
      // Check if this was a company switch operation and update local state
      if (confirmedEntity.value === 'company' && confirmedVerb.value === 'switch' && data.data) {
        const newCompany = {
          id: data.data.id,
          name: data.data.name,
          slug: data.data.slug,
          base_currency: data.data.base_currency,
        }
        localActiveCompany.value = newCompany

        // Also update the defaults with new company info
        defaults.value = {
          company: { value: data.data.name, source: 'company_switch' },
          currency: { value: data.data.base_currency, source: 'company_switch' },
        }

        // Emit event for parent components and trigger Inertia reload to sync sidebar
        emit('company-switched', newCompany)

        // Reload Inertia page data to update sidebar (preserves palette state)
        router.reload({ only: ['auth'] })
      }

      if (data.data?.headers && data.data?.rows) {
        addOutput('table', data.data.rows, data.data.headers, data.data.footer, data.data.rowIds)
      } else if (data.message) {
        addOutput('success', `✓ ${data.message}`)
      } else {
        addOutput('success', '✓ Done')
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
    resetForNextCommand()
  }
}

// ============================================================================
// Output Functions
// ============================================================================

function addOutput(type: OutputLine['type'], content: string | string[][], headers?: string[], footer?: string, rowIds?: string[]) {
  output.value.push({ type, content, headers, footer, rowIds })
  if (output.value.length > 200) {
    output.value = output.value.slice(-200)
  }
}

// ============================================================================
// State Management
// ============================================================================

function resetState() {
  entityVerbInput.value = ''
  stage.value = 'entity'
  confirmedEntity.value = ''
  confirmedVerb.value = ''
  chips.value = []
  activeChipIndex.value = -1
  suggestions.value = []
  suggestionIndex.value = 0
  showSuggestions.value = false
  chipSuggestions.value = []
  chipSuggestionIndex.value = 0
  showChipDropdown.value = false
  defaults.value = {}
  // Reset localActiveCompany on close so next open picks up fresh Inertia props
  localActiveCompany.value = null
}

function resetForNextCommand() {
  entityVerbInput.value = ''
  stage.value = 'entity'
  confirmedEntity.value = ''
  confirmedVerb.value = ''
  chips.value = []
  activeChipIndex.value = -1
  showSuggestions.value = false
  chipSuggestions.value = []
  showChipDropdown.value = false
  defaults.value = {}
  nextTick(() => entityVerbInputEl.value?.focus())
}

function close() {
  emit('update:visible', false)
  resetState()
}

/**
 * Parse format tags like {success}text{/}, {warning}text{/}, {secondary}text{/}
 * and return HTML with appropriate styling
 */
function parseFormatTags(text: string): string {
  if (!text || typeof text !== 'string') return text

  // Define tag to CSS class mapping
  const tagStyles: Record<string, string> = {
    success: 'cell-success',
    warning: 'cell-warning',
    error: 'cell-error',
    secondary: 'cell-secondary',
    muted: 'cell-muted',
    info: 'cell-info',
    primary: 'cell-primary',
  }

  // Replace {tag}content{/} patterns with styled spans
  let result = text
  for (const [tag, className] of Object.entries(tagStyles)) {
    const regex = new RegExp(`\\{${tag}\\}(.*?)\\{/\\}`, 'g')
    result = result.replace(regex, `<span class="${className}">$1</span>`)
  }

  return result
}

function clearInput() {
  entityVerbInput.value = ''
  stage.value = 'entity'
  confirmedEntity.value = ''
  confirmedVerb.value = ''
  chips.value = []
  activeChipIndex.value = -1
  showSuggestions.value = false
  chipSuggestions.value = []
  showChipDropdown.value = false
  sidebarItems.value = []
  nextTick(() => entityVerbInputEl.value?.focus())
}

// ============================================================================
// Quick Actions Functions
// ============================================================================

// Select row in table
function selectRow(rowIndex: number) {
  if (tableState.value) {
    tableState.value.selectedRowIndex = rowIndex
  }
  entityVerbInputEl.value?.focus()
}

// Execute a command directly without going through chips
async function executeCommandDirectly(cmd: string) {
  const parsed = parse(cmd)

  if (!parsed.entity || !parsed.verb) {
    addOutput('error', '✗ Invalid command format')
    return
  }

  addOutput('input', `❯ ${cmd}`)
  addToHistory(cmd)
  recordCommandUse(cmd)
  frecencyScores.value = getFrecencyScores()
  executing.value = true

  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Action': `${parsed.entity}.${parsed.verb}`,
      'X-CSRF-TOKEN': getCsrfToken(),
    }

    if (activeCompany.value?.slug) {
      headers['X-Company-Slug'] = activeCompany.value.slug
    }

    const res = await fetch('/api/palette/execute', {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({
        entity: parsed.entity,
        verb: parsed.verb,
        params: parsed.flags,
      }),
    })

    const data = await res.json()

    if (data.ok) {
      // Check if this was a company switch operation and update local state
      if (parsed.entity === 'company' && parsed.verb === 'switch' && data.data) {
        const newCompany = {
          id: data.data.id,
          name: data.data.name,
          slug: data.data.slug,
          base_currency: data.data.base_currency,
        }
        localActiveCompany.value = newCompany

        // Also update the defaults with new company info
        defaults.value = {
          company: { value: data.data.name, source: 'company_switch' },
          currency: { value: data.data.base_currency, source: 'company_switch' },
        }

        // Emit event for parent components and trigger Inertia reload to sync sidebar
        emit('company-switched', newCompany)

        // Reload Inertia page data to update sidebar (preserves palette state)
        router.reload({ only: ['auth'] })
      }

      if (data.data?.headers && data.data?.rows) {
        addOutput('table', data.data.rows, data.data.headers, data.data.footer, data.data.rowIds)
      } else if (data.message) {
        addOutput('success', `✓ ${data.message}`)
      } else {
        addOutput('success', '✓ Done')
      }

      // Ignore any redirect from backend - palette commands should NOT redirect
      // User stays in palette to continue using command interface
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
    resetForNextCommand()
  }
}

// Handle quick action click or number key
function handleQuickAction(action: QuickAction) {
  // Check if action needs row selection
  if (action.needsRow && (!tableState.value || tableState.value.selectedRowIndex < 0)) {
    addOutput('error', '✗ Please select a row first (click on a row or use ↑↓)')
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

  // Execute directly without going through chips
  const resolved = resolveQuickActionCommand(action.command, tableState.value)
  if (resolved) {
    executeCommandDirectly(resolved)
  } else {
    addOutput('error', '✗ Could not resolve command. Please select a valid row.')
  }
}

// Close sub-prompt modal
function closeSubPrompt() {
  showSubPrompt.value = false
  subPromptAction.value = null
  subPromptInput.value = ''
  entityVerbInputEl.value?.focus()
}

// Confirm sub-prompt and execute
function confirmSubPrompt() {
  if (!subPromptAction.value || !subPromptInput.value.trim()) return

  // Special handling for delete confirmation
  if (subPromptAction.value.command.includes('delete')) {
    if (subPromptInput.value.trim().toLowerCase() !== 'confirm') {
      addOutput('warning', '⚠ Delete cancelled. Type "confirm" to proceed.')
      closeSubPrompt()
      return
    }
  }

  // Resolve command with row data first
  let resolved = resolveQuickActionCommand(subPromptAction.value.command, tableState.value)

  // If command still has placeholders or is null, use the base command
  if (!resolved) {
    resolved = subPromptAction.value.command
  }

  // Replace placeholder with user input (for non-delete prompts)
  if (!subPromptAction.value.command.includes('delete')) {
    resolved = resolved.replace(/\{user_input\}/g, subPromptInput.value.trim())
  }

  closeSubPrompt()
  executeCommandDirectly(resolved)
}

// ============================================================================
// History
// ============================================================================

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
  } catch {
    // ignore
  }
}

function addToHistory(cmd: string) {
  history.value = [cmd, ...history.value.filter(h => h !== cmd)].slice(0, 100)
  saveHistory()
}

// ============================================================================
// Utilities
// ============================================================================

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta?.getAttribute('content') || ''
}

// ============================================================================
// Lifecycle
// ============================================================================

onMounted(() => {
  if (props.visible) {
    nextTick(() => entityVerbInputEl.value?.focus())
  }
})
</script>

<template>
  <Teleport to="body">
    <!-- Animated Backdrop & Palette Wrapper -->
    <Transition name="palette">
      <div v-if="visible" class="palette-wrapper" @click.self="close">
        <!-- Backdrop -->
        <div class="palette-backdrop" @click="close" />

        <!-- Palette Modal -->
        <div class="palette palette-modal">
      <!-- Header -->
      <div class="palette-header">
        <div class="palette-company">
          <span class="palette-dot" :class="{ 'palette-dot--active': activeCompany }"></span>
          {{ activeCompany?.name || 'Global' }}
        </div>
        <span class="palette-stage-badge" :class="`stage-${stage}`">
          {{ stage === 'entity' ? 'Entity' : stage === 'verb' ? 'Action' : 'Details' }}
        </span>
      </div>

      <!-- Main body with sidebar -->
      <div class="palette-body">
        <!-- Main content area -->
        <div class="palette-main">
          <!-- Output Area -->
          <div ref="outputEl" class="palette-output">
            <template v-if="output.length === 0">
              <div class="palette-empty">
                Type a command (e.g., <span class="palette-cmd">invoice create</span>)
              </div>
            </template>
            <template v-for="(line, i) in output" :key="i">
              <div v-if="line.type === 'table'" class="palette-table">
                <div class="table-wrapper">
                  <div v-if="line.headers?.length" class="table-header">
                    <div v-for="(header, ci) in line.headers" :key="ci" class="table-cell table-cell--header">
                      {{ header }}
                    </div>
                  </div>
                  <div
                    v-for="(row, ri) in (line.content as string[][])"
                    :key="ri"
                    class="table-row"
                    :class="{
                      'table-row--selected': tableState && i === output.length - 1 && ri === tableState.selectedRowIndex
                    }"
                    @click="selectRow(ri)"
                  >
                    <div v-for="(cell, ci) in row" :key="ci" class="table-cell" v-html="parseFormatTags(cell)"></div>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="palette-line"
                :class="{
                  'palette-line--input': line.type === 'input',
                  'palette-line--error': line.type === 'error',
                  'palette-line--success': line.type === 'success',
                }"
                v-html="formatText(String(line.content))"
              />
            </template>
          </div>

          <!-- Input Area -->
          <div class="palette-input-area">
            <div class="palette-input-row">
              <span class="palette-prompt" :class="{ 'palette-prompt--busy': executing }">
                {{ executing ? '⋯' : '❯' }}
              </span>

              <!-- Entity/Verb Input -->
              <input
                ref="entityVerbInputEl"
                v-model="entityVerbInput"
                type="text"
                class="palette-entity-verb-input"
                :class="{ 'palette-entity-verb-input--has-chips': stage === 'chips' }"
                :disabled="executing"
                :placeholder="stage === 'entity' ? 'entity...' : stage === 'verb' ? 'action...' : ''"
                autocomplete="off"
                autocorrect="off"
                spellcheck="false"
                @keydown="handleEntityVerbKeydown"
              />

              <!-- Clear button (shown when there's input) -->
              <button
                v-if="entityVerbInput || chips.length > 0"
                class="palette-clear-btn"
                @click="clearInput"
                title="Clear input (Ctrl+L)"
                tabindex="-1"
              >
                ✕
              </button>

              <!-- Chips (after entity+verb confirmed) -->
              <div v-if="stage === 'chips'" class="palette-chips">
                <div
                  v-for="(chip, index) in chips"
                  :key="chip.name"
                  class="chip chip--animated"
                  :class="{
                    'chip--required': chip.required,
                    'chip--active': chip.isActive,
                    'chip--filled': chip.status === 'filled',
                    'chip--error': chip.status === 'error',
                    'chip--entity': !!chip.searchEntity,
                  }"
                  :style="{ animationDelay: `${index * 30}ms` }"
                  @click="handleChipClick(index)"
                >
                  <!-- Prefix with name, star for required, search icon for DB fields -->
                  <span class="chip-prefix" :class="{ 'chip-prefix--active': chip.isActive }">
                    <span v-if="chip.searchEntity" class="chip-search-icon">⌕</span>
                    <span class="chip-name">{{ chip.name }}</span>
                    <span v-if="chip.required" class="chip-star">★</span>
                  </span>

                  <!-- For entity search: show display label when filled and not active -->
                  <template v-if="chip.searchEntity && chip.displayLabel && !chip.isActive">
                    <span class="chip-display-label">{{ chip.displayLabel }}</span>
                  </template>

                  <!-- Input area (shown when active or no display label) -->
                  <input
                    v-else
                    :ref="(el) => setChipInputRef(index, el as HTMLInputElement)"
                    type="text"
                    class="chip-input"
                    :class="{ 'chip-input--search': chip.searchEntity }"
                    :value="chip.isActive && chip.searchEntity ? entitySearchQuery : (chip.isActive ? chip.value : (chip.displayLabel || chip.value))"
                    :placeholder="chip.searchEntity ? 'Type to search...' : chip.name"
                    @input="handleChipInput(index, ($event.target as HTMLInputElement).value)"
                    @keydown="handleChipKeydown($event, index)"
                    @focus="activateChip(index)"
                  />

                  <!-- Dropdown indicator -->
                  <button
                    v-if="chip.hasEnum"
                    class="chip-dropdown-btn"
                    :class="{ 'chip-dropdown-btn--open': chip.isActive && showChipDropdown }"
                    @click="handleDropdownToggle(index, $event)"
                    tabindex="-1"
                  >
                    <template v-if="chipSuggestionsLoading && chip.isActive">
                      <span class="chip-dropdown-spinner"></span>
                    </template>
                    <template v-else>▾</template>
                  </button>
                </div>
              </div>
            </div>

            <!-- Suggestions Bar (for entity/verb) -->
            <div v-if="showSuggestions && suggestions.length > 0" class="palette-suggestions">
              <button
                v-for="(suggestion, index) in suggestions"
                :key="suggestion.value"
                class="suggestion-chip"
                :class="{ 'suggestion-chip--selected': index === suggestionIndex }"
                @click="selectSuggestion(index)"
                @mouseenter="suggestionIndex = index"
              >
                <span class="suggestion-icon">{{ suggestion.icon || '📄' }}</span>
                <span class="suggestion-label">{{ suggestion.label }}</span>
                <kbd v-if="index === suggestionIndex" class="suggestion-kbd">Tab</kbd>
              </button>
            </div>

            <!-- Chip Dropdown (for enum values and entity search) -->
            <div v-if="showChipDropdown" class="chip-dropdown" :class="{ 'chip-dropdown--entity': chips[activeChipIndex]?.searchEntity }">
              <!-- Loading state -->
              <div v-if="chipSuggestionsLoading" class="chip-dropdown-loading">
                <span class="chip-dropdown-spinner"></span>
                <span>Searching...</span>
              </div>

              <!-- Results -->
              <template v-else-if="chipSuggestions.length > 0">
                <button
                  v-for="(suggestion, index) in chipSuggestions"
                  :key="suggestion.value"
                  class="chip-dropdown-item"
                  :class="{ 'chip-dropdown-item--selected': index === chipSuggestionIndex }"
                  @click="selectChipSuggestion(index)"
                  @mouseenter="chipSuggestionIndex = index"
                >
                  <span v-if="suggestion.icon" class="dropdown-icon">{{ suggestion.icon }}</span>
                  <div class="dropdown-content">
                    <span class="dropdown-label">{{ suggestion.label }}</span>
                    <span v-if="suggestion.meta" class="dropdown-meta">{{ suggestion.meta }}</span>
                  </div>
                </button>
              </template>

              <!-- No results / Minimum chars hint -->
              <div v-else class="chip-dropdown-empty">
                <template v-if="chips[activeChipIndex]?.searchEntity && entitySearchQuery.length > 0 && entitySearchQuery.length < 3">
                  <span>Type at least 3 characters</span>
                  <span class="chip-dropdown-hint">
                    to search {{ chips[activeChipIndex]?.searchEntity }}s
                  </span>
                </template>
                <template v-else>
                  <span>{{ chips[activeChipIndex]?.searchEntity ? 'Start typing to search' : 'No results found' }}</span>
                  <span v-if="chips[activeChipIndex]?.searchEntity" class="chip-dropdown-hint">
                    Search {{ chips[activeChipIndex]?.searchEntity }}s by name
                  </span>
                </template>
              </div>
            </div>

            <!-- Status Bar -->
            <div class="palette-status">
              <span class="status-message" :class="{ 'status-ready': canExecute }">
                {{ statusMessage }}
              </span>
              <div class="status-keys">
                <template v-if="stage === 'chips'">
                  <span><kbd>Tab</kbd> next</span>
                  <span><kbd>↑↓</kbd> dropdown</span>
                  <span><kbd>Enter</kbd> {{ canExecute ? 'execute' : 'next' }}</span>
                </template>
                <template v-else-if="tableState">
                  <span><kbd>↑↓</kbd> select row</span>
                  <span><kbd>1-9</kbd> actions</span>
                </template>
                <template v-else>
                  <span><kbd>Tab</kbd> select</span>
                  <span><kbd>←→</kbd> navigate</span>
                </template>
                <span><kbd>Ctrl+L</kbd> clear</span>
                <span><kbd>Esc</kbd> close</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div v-if="showQuickActions" class="palette-sidebar">
          <div class="sidebar-header">
            Quick Actions
            <span v-if="tableState" class="sidebar-debug">
              ({{ tableState.entity }}.{{ tableState.verb }})
            </span>
          </div>
          <div class="sidebar-actions">
            <div
              v-for="(action, index) in quickActions"
              :key="action.key"
              class="sidebar-action sidebar-action--animated"
              :class="{
                'sidebar-action--needs-row': action.needsRow && !tableState,
                'sidebar-action--disabled': action.needsRow && (!tableState || tableState.selectedRowIndex < 0)
              }"
              :style="{ animationDelay: `${index * 25}ms` }"
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

        <!-- Sidebar with quick picks (for chips) -->
        <div v-else-if="showSidebar" class="palette-sidebar">
          <div class="sidebar-header">{{ sidebarTitle }}</div>
          <div class="sidebar-items">
            <button
              v-for="(item, index) in sidebarItems"
              :key="item.value"
              class="sidebar-item sidebar-item--animated"
              :class="{ 'sidebar-item--selected': index === sidebarIndex }"
              :style="{ animationDelay: `${index * 25}ms` }"
              @click="selectSidebarItem(index)"
              @mouseenter="sidebarIndex = index"
            >
              <kbd class="sidebar-key">{{ index + 1 }}</kbd>
              <span v-if="item.icon" class="sidebar-icon">{{ item.icon }}</span>
              <span class="sidebar-label">{{ item.label }}</span>
              <span v-if="item.meta" class="sidebar-meta">{{ item.meta }}</span>
            </button>
          </div>
          <div class="sidebar-hint">
            Press <kbd>1</kbd>-<kbd>9</kbd> to select
          </div>
        </div>
      </div>
        </div>
      </div>
    </Transition>

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
/* ============================================================================
   Animation Foundation
   ============================================================================ */

/* Custom Properties for Timing - defined on wrapper element for cascading */

/* Keyframes */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}

@keyframes scaleIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-4px); }
  75% { transform: translateX(4px); }
}

@keyframes chipEnter {
  from { opacity: 0; transform: scale(0.8) translateX(-8px); }
  to { opacity: 1; transform: scale(1) translateX(0); }
}

@keyframes glow {
  0%, 100% { box-shadow: 0 0 0 0 transparent; }
  50% { box-shadow: 0 0 8px 2px var(--glow-color, rgba(34, 211, 238, 0.4)); }
}

@keyframes lineEnter {
  from { opacity: 0; transform: translateX(-8px); }
  to { opacity: 1; transform: translateX(0); }
}

/* Entrance/Exit Transition Classes */
.palette-enter-active {
  animation: fadeIn var(--palette-duration-normal) ease-out;
}

.palette-leave-active {
  animation: fadeOut var(--palette-duration-fast) ease-in;
}

.palette-enter-active .palette-modal {
  animation: scaleIn var(--palette-duration-slow) var(--palette-ease);
}

.palette-leave-active .palette-modal {
  opacity: 0;
  transform: scale(0.98);
  transition: all var(--palette-duration-fast) ease-in;
}

/* ============================================================================
   Base Styles
   ============================================================================ */

/* Base */
.palette-wrapper {
  /* Animation timing variables */
  --palette-duration-fast: 100ms;
  --palette-duration-normal: 150ms;
  --palette-duration-slow: 200ms;
  --palette-ease: cubic-bezier(0.16, 1, 0.3, 1); /* ease-out-expo */
  --palette-ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1); /* slight overshoot */

  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 10vh;
}

.palette-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
}

.palette {
  position: relative;
  width: 80vw;
  max-width: 1200px;
  height: 80vh;
  max-height: 80vh;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  z-index: 1;
  overflow: hidden;
}

/* Body layout with sidebar */
.palette-body {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.palette-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

/* Sidebar */
.palette-sidebar {
  width: 240px;
  background: rgba(15, 23, 42, 0.95);
  border-left: 1px solid #334155;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 12px 16px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  border-bottom: 1px solid #1e293b;
}

.sidebar-items {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.sidebar-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  transition: all var(--palette-duration-fast) var(--palette-ease);
  margin-bottom: 4px;
}

/* Staggered entrance animation for sidebar items */
.sidebar-item--animated {
  animation: slideUp var(--palette-duration-normal) var(--palette-ease) backwards;
}

.sidebar-item:hover,
.sidebar-item--selected {
  background: rgba(34, 211, 238, 0.1);
  border-color: rgba(34, 211, 238, 0.3);
  transform: translateX(4px);
}

.sidebar-key {
  padding: 2px 6px;
  background: rgba(251, 191, 36, 0.2);
  border: 1px solid rgba(251, 191, 36, 0.4);
  border-radius: 3px;
  color: #fbbf24;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.sidebar-icon {
  font-size: 14px;
  flex-shrink: 0;
}

.sidebar-label {
  color: #e2e8f0;
  font-size: 12px;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-item--selected .sidebar-label {
  color: #22d3ee;
}

.sidebar-meta {
  color: #64748b;
  font-size: 10px;
  flex-shrink: 0;
}

.sidebar-hint {
  padding: 10px 16px;
  font-size: 10px;
  color: #64748b;
  border-top: 1px solid #1e293b;
  text-align: center;
}

.sidebar-hint kbd {
  padding: 2px 4px;
  background: rgba(71, 85, 105, 0.4);
  border: 1px solid #475569;
  border-radius: 2px;
  color: #94a3b8;
  font-size: 9px;
  margin: 0 2px;
}

/* Header */
.palette-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #1e293b;
  background: rgba(15, 23, 42, 0.8);
}

.palette-company {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
}

.palette-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #475569;
}

.palette-dot--active {
  background: #10b981;
}

.palette-stage-badge {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stage-entity { background: rgba(34, 211, 238, 0.15); color: #22d3ee; }
.stage-verb { background: rgba(167, 139, 250, 0.15); color: #a78bfa; }
.stage-chips { background: rgba(16, 185, 129, 0.15); color: #10b981; }

/* Output */
.palette-output {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px 16px;
  font-family: ui-monospace, monospace;
  font-size: 13px;
}

.palette-empty {
  color: #64748b;
  text-align: center;
  padding: 24px;
}

.palette-cmd {
  color: #22d3ee;
  background: rgba(34, 211, 238, 0.1);
  padding: 2px 6px;
  border-radius: 4px;
}

.palette-line {
  padding: 4px 0;
  color: #e2e8f0;
  animation: lineEnter var(--palette-duration-fast) var(--palette-ease);
}

.palette-line--input { color: #64748b; }
.palette-line--error { color: #f87171; }
.palette-line--success { color: #10b981; }

.palette-table {
  margin: 8px 0;
  animation: slideUp var(--palette-duration-fast) var(--palette-ease);
}
.table-wrapper { overflow-x: auto; }
.table-header { display: flex; background: #1e293b; }
.table-row {
  display: flex;
  border-bottom: 1px solid #1e293b;
  cursor: pointer;
  transition: all var(--palette-duration-fast) var(--palette-ease);
}
.table-row:hover {
  background: rgba(34, 211, 238, 0.08);
  transform: translateX(2px);
}
.table-cell { padding: 8px 12px; flex: 1; min-width: 80px; color: #e2e8f0; }
.table-cell--header { font-weight: 600; color: #94a3b8; }

/* Format tag styles for table cells */
.table-cell .cell-success { color: #10b981; }
.table-cell .cell-warning { color: #f59e0b; }
.table-cell .cell-error { color: #f87171; }
.table-cell .cell-secondary { color: #64748b; }
.table-cell .cell-muted { color: #475569; }
.table-cell .cell-info { color: #22d3ee; }
.table-cell .cell-primary { color: #a78bfa; }

/* Input Area */
.palette-input-area {
  border-top: 1px solid #334155;
  background: linear-gradient(to bottom, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.8));
}

.palette-input-row {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  gap: 8px;
  overflow-x: auto;
}

.palette-prompt {
  color: #22d3ee;
  font-weight: 600;
  font-size: 16px;
  flex-shrink: 0;
}

.palette-prompt--busy {
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.palette-entity-verb-input {
  background: transparent;
  border: none;
  outline: none;
  color: #e2e8f0;
  font-size: 14px;
  font-family: ui-monospace, monospace;
  width: 100%;
  flex: 1;
}

.palette-entity-verb-input--has-chips {
  width: auto;
  flex-shrink: 0;
  color: #94a3b8;
}

.palette-entity-verb-input::placeholder {
  color: #475569;
}

/* Clear button */
.palette-clear-btn {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 4px;
  color: #ef4444;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
  margin-left: 8px;
}

.palette-clear-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.5);
}

/* Chips */
.palette-chips {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.chip {
  display: flex;
  align-items: center;
  border: 1px solid #475569;
  border-radius: 6px;
  overflow: hidden;
  background: rgba(30, 41, 59, 0.5);
  transition: all var(--palette-duration-normal) var(--palette-ease);
  flex-shrink: 0;
}

/* Staggered entrance animation for chips */
.chip--animated {
  animation: chipEnter var(--palette-duration-slow) var(--palette-ease) backwards;
}

.chip--active {
  border-color: #22d3ee;
  box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.2);
  --glow-color: rgba(34, 211, 238, 0.4);
  animation: glow 300ms ease-out;
}

.chip--filled {
  border-color: #10b981;
}

.chip--error {
  border-color: #f87171;
  animation: shake var(--palette-duration-slow) ease-in-out;
}

.chip-prefix {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 8px;
  background: #475569;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
  font-family: ui-monospace, monospace;
  border-right: 1px solid #334155;
  flex-shrink: 0;
}

.chip-name {
  text-transform: capitalize;
}

.chip-star {
  color: #fbbf24;
  font-size: 10px;
}

.chip-search-icon {
  color: #a78bfa;
  font-size: 12px;
}

.chip--required .chip-prefix {
  background: #475569;
}

.chip--required .chip-star {
  color: #f87171;
}

.chip-prefix--active {
  background: #22d3ee;
  color: #0f172a;
}

.chip-prefix--active .chip-star {
  color: #0f172a;
}

.chip-prefix--active .chip-search-icon {
  color: #0f172a;
}

.chip--filled .chip-prefix {
  background: #10b981;
  color: #0f172a;
  transform: scale(1.02);
  transition: all var(--palette-duration-normal) var(--palette-ease);
}

.chip--filled .chip-star,
.chip--filled .chip-search-icon {
  color: #0f172a;
}

.chip-input {
  width: 100px;
  padding: 6px 8px;
  background: transparent;
  border: none;
  outline: none;
  color: #e2e8f0;
  font-size: 12px;
  font-family: inherit;
}

.chip-input::placeholder {
  color: #64748b;
}

.chip--filled .chip-input {
  color: #10b981;
}

.chip-dropdown-btn {
  padding: 6px 8px;
  background: transparent;
  border: none;
  border-left: 1px solid #334155;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s;
}

.chip-dropdown-btn:hover {
  background: rgba(34, 211, 238, 0.1);
  color: #22d3ee;
}

.chip-dropdown-btn--open {
  background: rgba(34, 211, 238, 0.15);
  color: #22d3ee;
}

/* Suggestions Bar */
.palette-suggestions {
  display: flex;
  gap: 8px;
  padding: 8px 16px;
  border-top: 1px solid #1e293b;
  overflow-x: auto;
  animation: slideUp var(--palette-duration-normal) var(--palette-ease);
}

.suggestion-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: rgba(30, 41, 59, 0.8);
  border: 1px solid #475569;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--palette-duration-fast) var(--palette-ease);
  flex-shrink: 0;
}

.suggestion-chip:hover,
.suggestion-chip--selected {
  background: rgba(34, 211, 238, 0.1);
  border-color: #22d3ee;
  transform: translateY(-1px);
}

.suggestion-icon { font-size: 14px; }
.suggestion-label { color: #e2e8f0; font-size: 12px; font-weight: 500; }
.suggestion-chip--selected .suggestion-label { color: #22d3ee; }

.suggestion-kbd {
  padding: 2px 6px;
  background: rgba(34, 211, 238, 0.15);
  border: 1px solid rgba(34, 211, 238, 0.3);
  border-radius: 3px;
  color: #22d3ee;
  font-size: 9px;
}

/* Chip Dropdown */
.chip-dropdown {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 10px 16px;
  background: rgba(30, 41, 59, 0.95);
  border-top: 1px solid #334155;
  animation: slideDown var(--palette-duration-normal) var(--palette-ease);
  transform-origin: top center;
}

.chip-dropdown-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: rgba(51, 65, 85, 0.5);
  border: 1px solid #475569;
  border-radius: 4px;
  cursor: pointer;
  transition: all var(--palette-duration-fast) var(--palette-ease);
}

.chip-dropdown-item:hover,
.chip-dropdown-item--selected {
  background: rgba(34, 211, 238, 0.1);
  border-color: rgba(34, 211, 238, 0.4);
  transform: translateX(4px);
}

.dropdown-key {
  padding: 2px 6px;
  background: rgba(251, 191, 36, 0.2);
  border: 1px solid rgba(251, 191, 36, 0.4);
  border-radius: 3px;
  color: #fbbf24;
  font-size: 10px;
  font-weight: 700;
}

.dropdown-icon { font-size: 14px; }
.dropdown-label { color: #e2e8f0; font-size: 12px; font-weight: 500; }
.chip-dropdown-item--selected .dropdown-label { color: #22d3ee; }

/* Entity search dropdown improvements */
.chip-dropdown--entity {
  flex-direction: column;
  gap: 4px;
  max-height: 240px;
  overflow-y: auto;
}

.chip-dropdown--entity .chip-dropdown-item {
  width: 100%;
  padding: 8px 12px;
  border-radius: 6px;
}

.dropdown-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.dropdown-meta {
  color: #64748b;
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chip-dropdown-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  color: #94a3b8;
  font-size: 12px;
}

.chip-dropdown-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px;
  color: #64748b;
  font-size: 12px;
  text-align: center;
}

.chip-dropdown-hint {
  font-size: 11px;
  color: #475569;
}

.chip-dropdown-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #475569;
  border-top-color: #22d3ee;
  border-radius: 50%;
  animation: spin 0.6s linear infinite; /* faster, more responsive feel */
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Entity chip styling */
.chip--entity {
  min-width: 140px;
}

.chip--entity.chip--filled {
  border-color: #a78bfa;
}

.chip--entity.chip--filled .chip-prefix {
  background: #a78bfa;
}

.chip-display-label {
  padding: 6px 8px;
  color: #a78bfa;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 150px;
}

.chip-input--search {
  min-width: 120px;
}

/* Status Bar */
.palette-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  border-top: 1px solid #1e293b;
  font-size: 11px;
}

.status-message {
  color: #f59e0b;
  font-weight: 500;
}

.status-ready {
  color: #10b981;
  text-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
  transition: text-shadow var(--palette-duration-slow) ease;
}

.status-keys {
  display: flex;
  gap: 12px;
  color: #64748b;
}

.status-keys kbd {
  padding: 2px 5px;
  background: rgba(71, 85, 105, 0.4);
  border: 1px solid #475569;
  border-radius: 3px;
  color: #94a3b8;
  font-size: 10px;
  margin-right: 4px;
}

/* Quick Actions Sidebar */
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
  transition: all var(--palette-duration-normal) var(--palette-ease);
}

/* Staggered entrance animation for quick actions */
.sidebar-action--animated {
  animation: slideUp var(--palette-duration-normal) var(--palette-ease) backwards;
}

.sidebar-action:hover {
  background: rgba(34, 211, 238, 0.1);
  border-color: rgba(34, 211, 238, 0.3);
  transform: translateX(4px);
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
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.sidebar-debug {
  display: block;
  font-size: 10px;
  color: #fbbf24;
  text-transform: none;
  margin-top: 4px;
  font-weight: normal;
}

.hint-small {
  font-size: 10px;
  opacity: 0.7;
}

/* Table row selection */
.table-row {
  cursor: pointer;
  transition: background-color 0.15s;
}

.table-row:hover {
  background: rgba(34, 211, 238, 0.05);
}

.table-row--selected {
  background: rgba(34, 211, 238, 0.1);
  border-left: 3px solid #22d3ee;
  padding-left: calc(12px - 3px);
  transition: all var(--palette-duration-fast) var(--palette-ease);
}

/* Sub-prompt modal */
.subprompt-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.subprompt-modal {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  max-width: 400px;
  width: 90%;
}

.subprompt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #334155;
}

.subprompt-title {
  font-weight: 600;
  color: #e2e8f0;
}

.subprompt-close {
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 16px;
  cursor: pointer;
  padding: 4px;
}

.subprompt-body {
  padding: 20px;
}

.subprompt-prompt {
  margin: 0 0 16px 0;
  color: #94a3b8;
  font-size: 14px;
}

.subprompt-input {
  width: 100%;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 6px;
  color: #e2e8f0;
  padding: 10px 12px;
  font-size: 14px;
}

.subprompt-input:focus {
  outline: none;
  border-color: #22d3ee;
  box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.2);
}

.subprompt-footer {
  display: flex;
  gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid #334155;
  justify-content: flex-end;
}

.subprompt-btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.15s;
}

.subprompt-btn--cancel {
  background: #374151;
  color: #94a3b8;
}

.subprompt-btn--cancel:hover {
  background: #4b5563;
}

.subprompt-btn--confirm {
  background: #22d3ee;
  color: #0f172a;
}

.subprompt-btn--confirm:hover {
  background: #06b6d4;
}

/* ============================================================================
   Accessibility: Reduced Motion Support
   ============================================================================ */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
