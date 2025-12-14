import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { generateSuggestions } from '@/palette/autocomplete'
import { formatText } from '@/palette/formatter'
import { getFrecencyScores, recordCommandUse } from '@/palette/frecency'
import { resolveEntityShortcut, getVerbs, ENTITY_ICONS } from '@/palette/grammar'
import { getQuickActions, resolveQuickActionCommand, getQuickActionLabel } from '@/palette/quick-actions'
import { getFieldPlaceholder, getSchema } from '@/palette/schemas'
import { parse } from '@/palette/parser'
import type { InputType } from '@/palette/schemas'
import type { OutputLine, QuickAction, Suggestion, TableState } from '@/types/palette'

export type PaletteCompany = { id: string; name: string; slug: string; base_currency: string }
export type CommandPaletteProps = { visible: boolean }
export type PaletteEmit = ((event: 'update:visible', value: boolean) => void) & ((event: 'company-switched', company: PaletteCompany) => void)

export function useCommandPalette(props: CommandPaletteProps, emit: PaletteEmit) {
  // ============================================================================
  // Types
  // ============================================================================
  
  type Stage = 'entity' | 'verb' | 'chips'
  
  interface ChipDef {
    name: string
    shortcut: string
    required: boolean
    inputType: InputType
    hasEnum: boolean
    hint?: string
    placeholder?: string
    /** Entity type for DB search (e.g., 'customer', 'invoice') */
    searchEntity?: string
    /** Accepted file types for image fields */
    accept?: string
    /** Static enum values for 'select' type */
    enumValues?: string[]
  }
  
  interface ChipState extends ChipDef {
    value: string
    /** Display label for the selected value (for entity search) */
    displayLabel?: string
    /** File object for image fields */
    file?: File
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

  const missingRequiredChips = computed(() => chips.value.filter(c => c.required && !c.value.trim()))

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
    // Show sidebar when we have chip enum items (quick actions handled separately)
    return sidebarItems.value.length > 0 && stage.value === 'chips'
  })
  
  const showQuickActions = computed(() => {
    // Show quick actions only when table has data (rows)
    return quickActions.value.length > 0 && tableState.value && tableState.value.rows.length > 0
  })
  
  // Show chip picker sidebar when we have an active field with suggestions
  const showChipPicker = computed(() => {
    if (stage.value !== 'chips' || activeChipIndex.value < 0) return false
    const chip = chips.value[activeChipIndex.value]
    if (!chip) return false
    // Show for select, lookup, search, or date fields
    return chip.inputType === 'select' || chip.inputType === 'lookup' || chip.inputType === 'search' || chip.inputType === 'date'
  })

  const hasChipDropdownOptions = computed(() => showChipDropdown.value && chipSuggestions.value.length > 0)

  // Get the active chip's field type for sidebar header
  const activeChipFieldType = computed(() => {
    if (activeChipIndex.value < 0) return null
    const chip = chips.value[activeChipIndex.value]
    return chip?.inputType || null
  })
  
  // Get sidebar header text based on active chip
  const chipPickerHeader = computed(() => {
    if (activeChipIndex.value < 0) return ''
    const chip = chips.value[activeChipIndex.value]
    if (!chip) return ''
  
    if (chip.inputType === 'date') return 'Quick Dates'
    if (chip.inputType === 'search') return `Search ${chip.searchEntity || chip.name}`
    return `Select ${chip.name}`
  })
  
  // Split chips into required (args) and optional (flags)
  const requiredChips = computed(() => chips.value.filter(c => c.required))
  const optionalChips = computed(() => chips.value.filter(c => !c.required))
  
  // Get the original index of a chip in the full chips array
  function getChipIndex(chip: ChipState): number {
    return chips.value.findIndex(c => c.name === chip.name)
  }
  
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
  
  async function fetchChipSuggestions(chipName: string, query: string, isLookup: boolean = false) {
    if (!confirmedEntity.value || !confirmedVerb.value) return
  
    // Get current chip to check for searchEntity
    const currentChip = chips.value[activeChipIndex.value]
    const entityToSearch = currentChip?.searchEntity
  
    // If this is an entity search/lookup field, use the API
    if (entityToSearch) {
      // Clear previous timer
      if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
      }
  
      // For search (not lookup): require minimum 2 characters before searching
      // For lookup: fetch all immediately (no minimum requirement)
      if (!isLookup && query.length > 0 && query.length < 2) {
        chipSuggestions.value = []
        chipSuggestionsLoading.value = false
        showChipDropdown.value = true  // Show "type at least 2 characters" message
        return
      }
  
      // For lookup with no query, fetch all options
      if (isLookup && !query) {
        // Continue to fetch (no minimum chars needed)
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
        required: arg.required ?? false,
        inputType: arg.inputType,
        hasEnum: arg.inputType === 'select' || arg.inputType === 'search' || arg.inputType === 'lookup',
        searchEntity: arg.searchEntity,
        hint: arg.hint,
        placeholder: arg.placeholder || getFieldPlaceholder(arg),
        accept: arg.accept,
        enumValues: arg.enum,  // Copy enum values for select types
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
        inputType: flag.inputType,
        hasEnum: flag.inputType === 'select' || flag.inputType === 'search' || flag.inputType === 'lookup',
        searchEntity: flag.searchEntity,
        hint: flag.hint,
        placeholder: flag.placeholder || getFieldPlaceholder(flag),
        accept: flag.accept,
        enumValues: flag.enum,  // Copy enum values for select types
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
  
    // Handle field based on inputType
    if (chip.inputType === 'date') {
      // Date field - show quick picks in sidebar
      loadQuickDates()
      showChipDropdown.value = false
    } else if (chip.inputType === 'image') {
      // Image field - no dropdown, handled by file input
      chipSuggestions.value = []
      showChipDropdown.value = false
      sidebarItems.value = []
    } else if (chip.inputType === 'select') {
      // Select (static enum) - use local enum values, no API call
      if (chip.enumValues && chip.enumValues.length > 0) {
        chipSuggestions.value = chip.enumValues.map(v => ({
          value: v,
          label: v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        }))
        chipSuggestionIndex.value = 0
        showChipDropdown.value = true
      } else {
        // Fallback to API if no local enum
        fetchChipSuggestions(chip.name, '')
        showChipDropdown.value = true
      }
    } else if (chip.inputType === 'lookup') {
      // Lookup (small DB list) - fetch and show all options immediately
      fetchChipSuggestions(chip.name, '', true) // true = fetch all for lookup
      showChipDropdown.value = true
    } else if (chip.inputType === 'search') {
      // Search (large DB) - don't fetch until user types ≥2 chars
      chipSuggestions.value = []
      showChipDropdown.value = true // Show sidebar but with "Type to search" hint
    } else {
      // Text, number, email, phone - no dropdown
      chipSuggestions.value = []
      showChipDropdown.value = false
      sidebarItems.value = []
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
  
  function loadQuickDates() {
    const today = new Date()
    const formatDate = (d: Date) => d.toISOString().split('T')[0]
    const addDays = (d: Date, days: number) => {
      const result = new Date(d)
      result.setDate(result.getDate() + days)
      return result
    }
  
  sidebarItems.value = [
    { value: formatDate(today), label: 'Today', icon: '📅' },
    { value: formatDate(addDays(today, 1)), label: 'Tomorrow', icon: '📅' },
    { value: formatDate(addDays(today, 7)), label: 'In 7 days', icon: '📅' },
    { value: formatDate(addDays(today, 30)), label: 'In 30 days', icon: '📅' },
    ]
    sidebarTitle.value = 'Quick Dates'
    sidebarIndex.value = 0
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

  // Move to next chip (force true to allow skipping optional when user explicitly selected)
  goToNextChip(true)
}
  
  function handleChipInput(index: number, value: string) {
    const chip = chips.value[index]
    if (!chip) return
  
    // For entity search/lookup chips, update the search query (not the chip value yet)
    if (chip.searchEntity) {
      entitySearchQuery.value = value
      // Don't update chip.value until a selection is made
      // Fetch suggestions based on search query
      // isLookup = true for lookup fields (filters existing list), false for search (new API call)
      const isLookup = chip.inputType === 'lookup'
      fetchChipSuggestions(chip.name, value, isLookup)
    } else {
      chip.value = value
      chip.status = value.trim() ? 'filled' : 'empty'
  
      // For select type with local enum, filter locally
      if (chip.inputType === 'select' && chip.enumValues && chip.enumValues.length > 0) {
        const query = value.toLowerCase()
        const filtered = chip.enumValues.filter(v =>
          v.toLowerCase().includes(query) ||
          v.replace(/_/g, ' ').toLowerCase().includes(query)
        )
        chipSuggestions.value = filtered.map(v => ({
          value: v,
          label: v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        }))
        chipSuggestionIndex.value = 0
      } else if (chip.inputType === 'select') {
        // Fallback to API for select without local enum
        fetchChipSuggestions(chip.name, value)
      }
    }
  }
  
  function handleImageSelect(index: number, event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const chip = chips.value[index]
    if (!chip || !file) return
  
    chip.file = file
    chip.value = file.name
    chip.status = 'filled'
  
    // Move to next chip
    goToNextChip()
  }
  
  function clearImageField(index: number) {
    const chip = chips.value[index]
    if (!chip) return
  
    chip.file = undefined
    chip.value = ''
    chip.status = 'empty'
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
  goToNextChip(true)
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

function focusFirstEmptyRequired() {
  const firstEmptyRequired = chips.value.findIndex(c => c.required && !c.value.trim())
  if (firstEmptyRequired >= 0) {
    activateChip(firstEmptyRequired)
  }
}

function handleChipKeydown(e: KeyboardEvent, index: number) {
  const chip = chips.value[index]
  if (!chip) return

  // Backspace - delete character-by-character, then go to previous field when empty
  if (e.key === 'Backspace') {
    const isSearchField = chip.inputType === 'search' || chip.inputType === 'lookup'
    const currentText = isSearchField ? entitySearchQuery.value : chip.value

    if (currentText.length > 0) {
      return
    }

    e.preventDefault()

    if (index === 0) {
      deactivateChips()
      stage.value = 'verb'
      chips.value = []
      entityVerbInput.value = confirmedEntity.value + ' ' + confirmedVerb.value
      confirmedVerb.value = ''
      refreshVerbSuggestions()
      entityVerbInputEl.value?.focus()
    } else {
      activateChip(index - 1)
    }
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

  // Enter - select dropdown item, confirm value, or execute
  if (e.key === 'Enter') {
    e.preventDefault()

    if (hasChipDropdownOptions.value) {
      selectChipSuggestion(chipSuggestionIndex.value)
      return
    }

    if (chip.inputType === 'date') {
      if (chip.value.trim()) {
        chip.status = 'filled'
        if (canExecute.value) {
          execute()
        } else {
          goToNextChip(true)
        }
        return
      }
      if (sidebarItems.value.length > 0) {
        selectSidebarItem(sidebarIndex.value)
        return
      }
    }

    const isTextInput = ['text', 'number', 'email', 'phone'].includes(chip.inputType)
    if (isTextInput && chip.value.trim()) {
      chip.status = 'filled'
      if (canExecute.value) {
        execute()
      } else {
        goToNextChip(true)
      }
      return
    }

    if (canExecute.value) {
      execute()
      return
    }

    focusFirstEmptyRequired()
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

  // Number keys 1-9 - select from dropdown or sidebar (quick picks)
  if (/^[1-9]$/.test(e.key)) {
    const idx = parseInt(e.key) - 1

    if (hasChipDropdownOptions.value && idx < chipSuggestions.value.length) {
      e.preventDefault()
      selectChipSuggestion(idx)
      return
    }

    if (!hasChipDropdownOptions.value && sidebarItems.value.length > 0 && idx < sidebarItems.value.length) {
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

  return {
    activeChipFieldType,
    activeChipIndex,
    activeCompany,
    canExecute,
    chipPickerHeader,
    chipSuggestionIndex,
    chipSuggestions,
    chipSuggestionsLoading,
    chips,
    clearImageField,
    clearInput,
    close,
    closeSubPrompt,
    confirmSubPrompt,
    entitySearchQuery,
    entityVerbInput,
    entityVerbInputEl,
    executing,
    formatText,
    getChipIndex,
    getQuickActionLabel,
    handleChipClick,
    handleChipInput,
    handleChipKeydown,
    handleEntityVerbKeydown,
    handleImageSelect,
    handleQuickAction,
    optionalChips,
    output,
    parseFormatTags,
    missingRequiredChips,
    quickActions,
    requiredChips,
    selectChipSuggestion,
    selectRow,
    selectSidebarItem,
    selectSuggestion,
    setChipInputRef,
    showChipPicker,
    showQuickActions,
    showSidebar,
    showSubPrompt,
    showSuggestions,
    sidebarIndex,
    sidebarItems,
    sidebarTitle,
    stage,
    statusMessage,
    subPromptAction,
    subPromptInput,
    subPromptInputEl,
    suggestionIndex,
    suggestions,
    tableState,
  }
}
