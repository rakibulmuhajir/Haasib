import { reactive, ref, computed, nextTick, watch, toRefs, type Ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Fuse from 'fuse.js'
import { http, ensureCsrf, withIdempotency } from '@/lib/http'
import { useToasts } from '@/composables/useToasts.js'
import { entities as baseEntities, type EntityDef, type VerbDef, type FieldDef } from '@/palette/entities'
import { parseCommand } from '@/palette/parser'
import { useSuggestions } from '@/palette/composables/useSuggestions'

export interface PostExecuteContext {
  response: any;
  params: Record<string, any>;
  palette: UsePalette;
}

// This could be in a shared types file, e.g., @/palette/types.ts
export interface PreExecuteContext {
  params: Ref<Record<string, any>>;
  companyDetails: Ref<Record<string, any>>;
  deleteConfirmRequired: Ref<string>;
  deleteConfirmText: Ref<string>;
}

type Step = 'entity' | 'verb' | 'fields'

interface PaletteState {
  open: boolean
  q: string
  step: Step
  selectedIndex: number
  executing: boolean
  results: any[]
  showResults: boolean
  selectedEntity: EntityDef | null
  selectedVerb: VerbDef | null
  params: Record<string, any>
  stashParams: Record<string, string>
  activeFlagId: string | null
  flagAnimating: string | null
  editingFlagId: string | null
  deleteConfirmText: string
  deleteConfirmRequired: string
  uiListActionMode: boolean
  uiListActionIndex: number
  paramFrom?: Record<string, 'freeform' | 'guided'>
}

export function usePalette() {
  let paletteApi = {} as UsePalette

  const state = reactive<PaletteState>({
    open: false,
    q: '',
    step: 'entity',
    selectedIndex: 0,
    executing: false,
    results: [],
    showResults: false,
    selectedEntity: null,
    selectedVerb: null,
    params: {},
    stashParams: {},
    activeFlagId: null,
    flagAnimating: null,
    editingFlagId: null,
    deleteConfirmText: '',
    deleteConfirmRequired: '',
    uiListActionMode: false,
    uiListActionIndex: 0,
    paramFrom: {},
  })

  const inputEl = ref<HTMLInputElement | null>(null)
  const { addToast } = useToasts()

  // Data & Context
  const page = usePage<any>()
  const isSuperAdmin = computed(() => !!page.props?.auth?.isSuperAdmin)
  const currentCompanyId = computed(() => page.props?.auth?.companyId || null)
  const userSource = ref<'all' | 'company'>(isSuperAdmin.value ? 'all' : 'company')
  const companySource = ref<'all' | 'me' | 'byUser'>(isSuperAdmin.value ? 'all' : 'me')

  // Unified Suggestions State
  const panelItems = ref<Array<{ value: string; label: string; meta?: any }>>([])
  const inlineItems = ref<Array<{ value: string; label: string; meta?: any }>>([])

  // Details State
  const companyDetails = ref<Record<string, any>>({})
  const companyMembers = ref<Record<string, Array<{ id: string; name: string; email: string; role: string }>>>({})
  const companyMembersLoading = ref<Record<string, boolean>>({})
  const userDetails = ref<Record<string, any>>({})

  // Suggestions Provider
  const provider = useSuggestions({ isSuperAdmin, currentCompanyId, userSource, companySource, q: toRefs(state).q, params: toRefs(state).params })

  // Command overlays (UI-only): enable/disable, label/aliases/order
  type OverlayRow = { entity_id: string; verb_id?: string | null; enabled?: boolean | null; label_override?: string | null; aliases_override?: string[] | null; order_override?: number | null }
  const overlays = ref<OverlayRow[]>([])
  const allowedActions = ref<string[]>([])

  async function loadOverlays() {
    try {
      await ensureCsrf()
      const { data } = await http.get('/web/commands/overlays')
      overlays.value = data?.data || []
    } catch { overlays.value = [] }
  }

  async function loadCapabilities() {
    try {
      await ensureCsrf()
      const { data } = await http.get('/web/commands/capabilities')
      allowedActions.value = data?.allowed_actions || []
    } catch { allowedActions.value = [] }
  }

  // Merge overlays into base entity catalog
  function applyEntityOverlay(e: EntityDef): EntityDef | null {
    const entityRows = overlays.value.filter(r => r.entity_id === e.id && (!r.verb_id || r.verb_id === null))
    const disabled = entityRows.find(r => r.enabled === false)
    if (disabled) return null
    const labelOverride = entityRows.find(r => r.label_override)?.label_override || null
    const aliasesOverride = (entityRows.find(r => r.aliases_override)?.aliases_override as any) || null
    const mappedVerbs: VerbDef[] = e.verbs
      .map((v) => applyVerbOverlay(e.id, v))
      .filter((v): v is VerbDef => v !== null)
    const out: EntityDef = {
      ...e,
      label: labelOverride || e.label,
      aliases: aliasesOverride || e.aliases,
      verbs: mappedVerbs,
    }
    return out
  }

  function applyVerbOverlay(entityId: string, v: VerbDef): VerbDef | null {
    const rows = overlays.value.filter(r => r.entity_id === entityId && r.verb_id === v.id)
    const disabled = rows.find(r => r.enabled === false)
    if (disabled) return null
    const labelOverride = rows.find(r => r.label_override)?.label_override || null
    const out: VerbDef = { ...v, label: labelOverride || v.label }
    return out
  }

  const activeEntities = computed<EntityDef[]>(() => {
    if (!overlays.value || overlays.value.length === 0) return baseEntities
    const mapped = baseEntities
      .map(e => applyEntityOverlay(e))
      .filter((e): e is EntityDef => e !== null)
    return mapped
  })

  // Fuzzy Search over active entities
  const entFuse = computed(() => new Fuse(activeEntities.value, { keys: ['label', 'aliases'], includeScore: true, threshold: 0.3 }))

  // --- COMPUTED PROPERTIES ---

  const entitySuggestions = computed(() => {
    if (state.q.length < 2) return activeEntities.value.slice(0, 6)
    const results = entFuse.value.search(state.q)
    return results.map(r => r.item).slice(0, 6)
  })

  const verbSuggestions = computed(() => {
    if (!state.selectedEntity) return []
    // Filter verbs by server capabilities; ui.* verbs are always allowed
    const verbs = state.selectedEntity.verbs.filter(v => v.action.startsWith('ui.') || allowedActions.value.includes(v.action))
    const needle = state.q.trim().toLowerCase()
    if (!needle) return verbs
    return verbs.filter(v => v.label.toLowerCase().includes(needle) || v.id.toLowerCase().includes(needle))
  })

  const availableFlags = computed<FieldDef[]>(() => {
    if (!state.selectedVerb) return []
    return state.selectedVerb.fields.filter(f => !state.params[f.id] && f.id !== state.activeFlagId)
  })

  const filledFlags = computed<FieldDef[]>(() => {
    if (!state.selectedVerb) return []
    return state.selectedVerb.fields.filter(f => state.params[f.id] && f.id !== state.activeFlagId)
  })

  const currentField = computed<FieldDef | undefined>(() => {
    if (state.activeFlagId && state.selectedVerb) {
      return state.selectedVerb.fields.find(f => f.id === state.activeFlagId)
    }
    return undefined
  })

  const dashParameterMatch = computed(() => {
    if (state.step !== 'fields' || !state.selectedVerb || state.activeFlagId) return null
    if (!state.q.startsWith('-')) return null
    const paramName = state.q.slice(1).toLowerCase()
    return state.selectedVerb.fields.find(f => f.id.toLowerCase().startsWith(paramName) || f.placeholder.toLowerCase().startsWith(paramName))
  })

  const allRequiredFilled = computed(() => {
    if (!state.selectedVerb) return false
    // UI-only actions never show Execute
    if (state.selectedVerb.action.startsWith('ui.')) return false
    return state.selectedVerb.fields.filter(f => f.required).every(f => state.params[f.id])
  })

  const currentChoices = computed<string[]>(() => {
    const f = currentField.value
    if (!f) return []
    if (f.type === 'select') return f.options || []
    return []
  })

  const isUIList = computed(() => !!state.selectedVerb && state.selectedVerb.action.startsWith('ui.list.'))

  const showUserPicker = computed(() => {
    if (!state.selectedVerb) return false
    if (isUIList.value && state.selectedVerb.action === 'ui.list.users') return true
    const f: any = currentField.value
    return f?.type === 'remote' && f?.picker === 'panel' && f?.source?.endpoint?.includes('/users/')
  })

  const showCompanyPicker = computed(() => {
    if (!state.selectedVerb) return false
    if (isUIList.value && state.selectedVerb.action === 'ui.list.companies') return true
    const f: any = currentField.value
    return f?.type === 'remote' && f?.picker === 'panel' && f?.source?.endpoint?.includes('/companies')
  })

  const showGenericPanelPicker = computed(() => {
    const f: any = currentField.value
    if (!f || f.type !== 'remote' || f.picker !== 'panel') return false
    return !showUserPicker.value && !showCompanyPicker.value
  })

  const inlineSuggestions = computed(() => {
    const f: any = currentField.value
    if (state.step !== 'fields' || !f || f.type !== 'remote' || f.picker !== 'inline') return []
    const term = (state.q || '').toString().trim()
    const list = inlineItems.value
    if (list.length === 0) return []

    const lower = term.toLowerCase()
    let idx = list.findIndex(i => i.value.toLowerCase() === lower)
    if (idx === -1 && lower) idx = list.findIndex(i => i.value.toLowerCase().startsWith(lower))
    if (idx === -1 && lower) idx = list.findIndex(i => i.label.toLowerCase().includes(lower))
    if (idx === -1) idx = 0

    const start = Math.max(0, idx - 3)
    return list.slice(start, Math.min(list.length, start + 7))
  })

  const highlightedItem = computed(() => {
    const isPanelActive = showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value
    if (isPanelActive && panelItems.value.length > 0) {
      return panelItems.value[Math.min(state.selectedIndex, panelItems.value.length - 1)]
    }
    return null
  })
  // UI list action mode state (keyboard navigation for actions on highlighted item)
  const uiListActionCount = computed(() => {
    if (!isUIList.value) return 0
    if (showUserPicker.value) return 3 // Assign to company, Delete user, Update user
    if (showCompanyPicker.value) return 4 // Assign user, Switch active, Delete, View members
    return 0
  })
  // For backward compatibility in the template until it's refactored
  const highlightedUser = computed(() => showUserPicker.value ? highlightedItem.value : null)
  const highlightedCompany = computed(() => showCompanyPicker.value ? highlightedItem.value : null)

  const statusText = computed(() => {
    if (state.step === 'entity') return 'SELECT_ENTITY'
    if (state.step === 'verb') return 'SELECT_ACTION'
    if (state.step === 'fields') {
      if (state.activeFlagId) return 'INPUT_VALUE'
      if (isUIList.value) return state.uiListActionMode ? 'ACTIONS' : 'SEARCH'
      return 'SELECT_PARAM'
    }
    return 'READY'
  })

  const getTabCompletion = computed(() => {
    if (state.step === 'entity' && state.q.length > 0) {
      const matches = entitySuggestions.value.filter(e => e.label.startsWith(state.q.toLowerCase()) || e.aliases.some(a => a.toLowerCase().startsWith(state.q.toLowerCase())))
      if (matches.length === 1) return matches[0].label
      if (matches.length > 1) {
        const labels = matches.map(m => m.label)
        let commonPrefix = labels[0]
        for (let i = 1; i < labels.length; i++) {
          while (commonPrefix.length > 0 && !labels[i].startsWith(commonPrefix)) {
            commonPrefix = commonPrefix.slice(0, -1)
          }
        }
        if (commonPrefix.length > state.q.length) return commonPrefix
      }
    }
    return state.q
  })

  // --- METHODS ---

  function animateFlag(flagId: string) {
    state.flagAnimating = flagId
    setTimeout(() => { state.flagAnimating = null }, 300)
  }

  function selectFlag(flagId: string) {
    if (state.activeFlagId === flagId) return
    animateFlag(flagId)
    state.activeFlagId = flagId
    state.q = ''
    state.selectedIndex = 0
    nextTick(() => inputEl.value?.focus())

    const field = state.selectedVerb?.fields.find(f => f.id === flagId)
    let defVal: string | undefined
    if (field && typeof (field as any).default !== 'undefined') {
      defVal = typeof (field as any).default === 'function' ? (field as any).default(state.params) : (field as any).default
    }
    if (!state.params[flagId] && defVal) {
      state.q = defVal
      nextTick(() => { inputEl.value?.focus(); inputEl.value?.select() })
    }
  }

  function editFilledFlag(flagId: string) {
    const currentValue = state.params[flagId]
    delete state.params[flagId]
    state.activeFlagId = flagId
    state.q = currentValue || ''
    state.editingFlagId = flagId
    nextTick(() => {
      if (inputEl.value) {
        inputEl.value.focus()
        inputEl.value.select()
      }
    })
  }

  function completeCurrentFlag() {
    if (!state.activeFlagId || !currentField.value) return
    const val = state.q.trim()
    // Run field-level validate if provided
    const validator = (currentField.value as any).validate as undefined | ((value: any, params: Record<string, any>) => true | string)
    if (validator) {
      try {
        const ok = validator(val, state.params)
        if (ok !== true) {
          // Keep focus in place; do not complete on validation failure
          if (typeof ok === 'string') { try { (addToast as any)(ok, 'warning') } catch {} }
          return
        }
      } catch { /* ignore and treat as invalid */ return }
    }
    if (val || !currentField.value.required) {
      const completingFlagId = state.activeFlagId
      if (val) {
        state.params[completingFlagId] = val
        try { (state as any).paramFrom && ((state as any).paramFrom[completingFlagId] = 'guided') } catch {}
      }

      let nextField: FieldDef | undefined
      if (state.selectedVerb) {
        const nextRequired = state.selectedVerb.fields.find(f => f.required && !state.params[f.id])
        const nextAvailable = state.selectedVerb.fields.find(f => !state.params[f.id])
        nextField = nextRequired || nextAvailable
      }

      state.activeFlagId = null
      state.editingFlagId = null
      state.q = ''
      state.selectedIndex = 0

      if (nextField) {
        selectFlag(nextField!.id)
      }
    }
  }

  function cycleToLastFilledFlag() {
    const filled = filledFlags.value
    if (filled.length === 0) return
    editFilledFlag(filled[filled.length - 1].id)
  }

  function handleDashParameter() {
    const match = dashParameterMatch.value
    if (match) {
      selectFlag(match.id)
      return true
    }
    return false
  }

  // Attempt to parse the current input as a full command and start the flow.
  // If executeIfComplete is true and all required fields are satisfied, will execute immediately.
  function parseAndStartFromInput(opts: { executeIfComplete?: boolean } = {}): boolean {
    const input = state.q.trim()
    if (!input) return false
    try {
      const parsed = parseCommand(input, activeEntities.value)
      if (!parsed) return false
      // Seed selected entity/verb/params, then advance to next missing field
      startVerb(parsed.entityId, parsed.verbId, parsed.params)
      try { (state as any).paramFrom && Object.keys(parsed.params||{}).forEach(k => (state as any).paramFrom[k] = 'freeform') } catch {}
      state.q = ''
      if (opts.executeIfComplete) {
        // Check if all required fields are present
        const verb = state.selectedVerb
        if (verb) {
          const missing = verb.fields.filter(f => f.required && !state.params[f.id])
          // Require reasonably confident parse to run directly
          const confident = (parsed as any).confidence && (parsed as any).confidence >= 0.85
          if (missing.length === 0 && confident) {
            // Execute on next tick so UI state settles
            nextTick(() => {
              // Guard again in case state changed
              const stillMissing = verb.fields.filter(f => f.required && !(state.params as any)[f.id])
              if (stillMissing.length === 0) {
                ;(execute as any)()
              }
            })
          }
        }
      }
      return true
    } catch {
      return false
    }
  }

  async function loadCompanyMembers(companyId: string) {
    if (companyMembersLoading.value[companyId]) return
    companyMembersLoading.value[companyId] = true
    try {
      await ensureCsrf()
      const { data } = await http.get(`/web/companies/${encodeURIComponent(companyId)}/users`)
      companyMembers.value[companyId] = data?.data || []
    } catch (e) {
      companyMembers.value[companyId] = []
    } finally {
      companyMembersLoading.value[companyId] = false
    }
  }

  async function ensureCompanyDetails(companyId: string) {
    if (!companyId) return
    if (companyDetails.value[companyId]) return
    try {
      await ensureCsrf()
      const { data } = await http.get(`/web/companies/${encodeURIComponent(companyId)}`)
      companyDetails.value[companyId] = data?.data || {}
    } catch (e) { /* ignore */ }
  }

  async function ensureUserDetails(userKey: string) {
    if (!userKey) return
    if (userDetails.value[userKey]) return
    try {
      await ensureCsrf()
      const { data } = await http.get(`/web/users/${encodeURIComponent(userKey)}`)
      const u = data?.data || null
      if (u) {
        userDetails.value[u.id] = u
        userDetails.value[u.email] = u
      }
    } catch (e) { /* ignore */ }
  }

  function startVerb(entityId: string, verbId: string, initialParams: Record<string, any> = {}) {
    const entity = activeEntities.value.find(e => e.id === entityId) || null
    if (!entity) return

    state.selectedEntity = entity
    const verb = entity.verbs.find(v => v.id === verbId) || null
    if (!verb) return

    state.selectedVerb = verb
    state.step = 'fields'
    state.params = { ...initialParams }

    // Find the first field to focus that isn't already pre-filled.
    // Prioritize required fields.
    const nextField = verb.fields.find(f => f.required && !state.params[f.id])
                   || verb.fields.find(f => !state.params[f.id])

    if (nextField) {
      selectFlag(nextField.id)
    } else {
      // All fields are filled, just focus the input.
      state.activeFlagId = null
    }

    state.selectedIndex = 0
    state.q = ''
    nextTick(() => inputEl.value?.focus())
  }

  function quickAssignToCompany(companyId: string) {
    startVerb('company', 'assign', { company: companyId })
  }

  async function setActiveCompany(companyId: string) {
    try {
      await ensureCsrf()
      await http.post('/web/companies/switch', { company_id: companyId })
      window.location.reload()
    } catch (e) { /* ignore */ }
  }

  function quickAssignUserToCompany(userIdOrEmail: string) {
    startVerb('company', 'assign', { email: userIdOrEmail })
  }

  function quickUnassignUserFromCompany(userEmail: string, companyId: string) {
    startVerb('company', 'unassign', { email: userEmail, company: companyId })
  }

  function resetAll() {
    Object.assign(state, {
      open: state.open, // Keep open state
      q: '',
      step: 'entity',
      selectedIndex: 0,
      executing: false,
      // results: [], // Keep results for a bit
      // showResults: false,
      selectedEntity: null,
      selectedVerb: null,
      params: {},
      stashParams: {},
      activeFlagId: null,
      flagAnimating: null,
      editingFlagId: null,
      deleteConfirmText: '',
      deleteConfirmRequired: '',
      uiListActionMode: false,
      uiListActionIndex: 0,
      paramFrom: {},
    })
  }

  function goHome() {
    state.step = 'entity'
    state.q = ''
    state.selectedVerb = null
    state.selectedEntity = null
    state.selectedIndex = 0
    state.activeFlagId = null
    state.editingFlagId = null
    state.uiListActionMode = false
    state.uiListActionIndex = 0
  }

  function goBack() {
    if (state.step === 'fields' && state.activeFlagId) {
      state.activeFlagId = null
      state.editingFlagId = null
      state.q = ''
      return
    }
    if (state.step === 'fields' && state.selectedVerb) {
      if (state.q) { state.q = ''; return }
      state.selectedVerb = null
      state.step = 'verb'
      state.q = ''
      state.selectedIndex = 0
      return
    }
    if (state.step === 'verb' && state.selectedEntity) {
      if (state.q) { state.q = ''; return }
      state.selectedEntity = null
      state.step = 'entity'
      state.q = ''
      state.selectedIndex = 0
      return
    }
    if (state.step === 'entity' && state.q) {
      state.q = ''
      return
    }
    state.open = false
  }

  function selectEntity(entity: EntityDef) {
    state.selectedEntity = entity
    state.step = 'verb'
    state.q = ''
    state.selectedIndex = 0
    nextTick(() => inputEl.value?.focus())
  }

  function selectVerb(verb: VerbDef) {
    state.selectedVerb = verb
    state.step = 'fields'
    state.q = ''
    state.selectedIndex = 0
    state.activeFlagId = null
    // Reset any destructive-action confirmations when switching verbs
    state.deleteConfirmText = ''
    state.deleteConfirmRequired = ''
    nextTick(() => inputEl.value?.focus())

    // UI Help actions: show examples/shortcuts in results panel immediately
    if (verb.action === 'ui.help' || verb.action === 'ui.help.shortcuts') {
      const now = new Date().toISOString()
      const base = { success: true, action: verb.action, params: {}, timestamp: now, message: 'Help' }
      const details = verb.action === 'ui.help'
        ? [
            'Examples:',
            '• company create Acme',
            '• create company Acme',
            '• user create Jane jane@example.com',
            '• user delete jane@example.com',
            '• company assign jane@example.com to Acme as admin',
            '• unassign jane@example.com from Acme',
          ]
        : [
            'Shortcuts:',
            '• Enter: execute/confirm, or parse freeform',
            '• Tab: complete entity/flag; Shift+Tab: edit last flag',
            '• Esc: back; Esc twice: close',
            '• Arrow keys: navigate suggestions; A/S/D/V on lists for actions',
            '• Pro toggle: button top-right of palette',
          ]
      state.results = [{ ...base, details }, ...state.results.slice(0, 4)]
      state.showResults = true
      // Return to verb selection for continued exploration
      state.step = 'verb'
      state.selectedVerb = null
      return
    }

    if (Object.keys(state.stashParams).length > 0 && state.selectedVerb) {
      for (const f of state.selectedVerb.fields) {
        const v = state.stashParams[f.id]
        if (v && !state.params[f.id]) state.params[f.id] = v
      }
      state.stashParams = {}
    }

    // No need to wait, select the first required field immediately.
    const firstRequired = verb.fields.find(f => f.required)
    if (firstRequired) selectFlag(firstRequired.id)
  }

  function selectChoice(choice: string) {
    if (!currentField.value) return
    state.q = choice
    setTimeout(completeCurrentFlag, 50)
  }

  async function execute() {
    if (!state.selectedVerb) return
    if (state.selectedVerb.action.startsWith('ui.')) return
    // Invoke optional preExecute hook (e.g., delete confirmations)
    if (typeof state.selectedVerb.preExecute === 'function') {
      const ok = await state.selectedVerb.preExecute({
        params: toRefs(state).params,
        companyDetails,
        deleteConfirmRequired: toRefs(state).deleteConfirmRequired,
        deleteConfirmText: toRefs(state).deleteConfirmText,
      })
      if (ok === false) return
    }

    // If password provided, require confirmation field to be filled and matching
    try {
      const hasPwd = !!state.params['password']
      const needsConfirm = !!(state.selectedVerb.fields || []).find(f => f.id === 'password_confirm')
      const pwdFrom = ((state as any).paramFrom && (state as any).paramFrom['password']) || 'guided'
      // Only require confirmation when password was entered via guided flow
      if (hasPwd && needsConfirm && !state.params['password_confirm'] && pwdFrom === 'guided') {
        selectFlag('password_confirm')
        return
      }
    } catch { /* ignore */ }
    if (!allRequiredFilled.value) return

    state.executing = true
    try {
      const response = await http.post('/commands', state.params, { headers: withIdempotency({ 'X-Action': state.selectedVerb.action }) })
      const executedEntity = state.selectedEntity
      const executedVerb = state.selectedVerb
      const carriedParams = { ...state.params }

      // Optional postExecute hook (cleanup, navigation, etc.)
      if (typeof executedVerb!.postExecute === 'function') {
        try {
          await executedVerb!.postExecute({ response: response.data, params: carriedParams, palette: paletteApi })
        } catch (_) { /* ignore hook errors */ }
      }
      // Hard reset to start clean after any action
      resetAll()
      state.open = true
      nextTick(() => inputEl.value?.focus())
    } catch (error: any) {
      const status = error?.response?.status
      const data = error?.response?.data
      const message = (data && (data.message || data.error)) || (typeof data === 'string' ? data : '') || error.message || 'Failed'
      const details: string[] = []
      if (data && data.errors && typeof data.errors === 'object') {
        try {
          Object.entries<any>(data.errors).forEach(([field, msgs]) => {
            const first = Array.isArray(msgs) ? msgs[0] : String(msgs)
            details.push(`${field}: ${first}`)
          })
        } catch { /* ignore */ }
      } else if (typeof data === 'string') {
        details.push(data)
      } else if (data && data.error && typeof data.error === 'string') {
        details.push(data.error)
      }
      // Reset after errors too; rely on toasts/logging elsewhere
      resetAll()
      state.open = true
      nextTick(() => inputEl.value?.focus())
    } finally {
      state.executing = false
    }
  }

  function pickUserEmail(email: string) {
    if (currentField.value?.id === 'email') {
      state.q = email
      setTimeout(completeCurrentFlag, 10)
    } else {
      const userEntity = activeEntities.value.find(e => e.id === 'user') || null
      if (userEntity) { // @ts-ignore
        state.selectedEntity = userEntity
        state.step = 'verb'
        state.q = ''
        state.selectedIndex = 0
        state.stashParams = { email }
      } else {
        state.q = email
      }
    }
  }

  function pickCompanyName(idOrName: string) {
    if (currentField.value?.id === 'company') {
      state.q = idOrName
      setTimeout(completeCurrentFlag, 10)
    } else {
      const coEntity = activeEntities.value.find(e => e.id === 'company') || null
      if (coEntity) {
        state.selectedEntity = coEntity
        state.step = 'verb'
        state.q = ''
        state.selectedIndex = 0
        state.stashParams = { company: idOrName }
      } else {
        state.q = idOrName
      }
    }
  }

  function pickGeneric(value: string) {
    if (currentField.value) {
      state.q = value
      setTimeout(completeCurrentFlag, 10)
    } else {
      state.q = value
    }
  }

  // --- WATCHERS ---

  watch(highlightedItem, async (item) => {
    if (!item || !item.meta) return

    if (showUserPicker.value) {
      const userId = item.meta.id
      if (!userId || userDetails.value[userId]) return
      try {
        await ensureCsrf()
        const { data } = await http.get(`/web/users/${encodeURIComponent(userId)}`)
        userDetails.value[userId] = data?.data || {}
      } catch (e) { /* ignore */ }
    } else if (showCompanyPicker.value) {
      const companyId = item.meta.id
      if (!companyId || companyDetails.value[companyId]) return
      try {
        await ensureCsrf()
        const { data } = await http.get(`/web/companies/${encodeURIComponent(companyId)}`)
        companyDetails.value[companyId] = data?.data || {}
      } catch (e) { /* ignore */ }
    }
  })

  watch(() => [state.q, state.step], ([newQ, newStep]) => {
    if (newStep === 'entity' && newQ.length >= 2) {
      const exact = entitySuggestions.value.find(e => e.label.toLowerCase() === newQ.toLowerCase() || e.aliases.some(a => a.toLowerCase() === newQ.toLowerCase()))
      if (exact) setTimeout(() => selectEntity(exact), 100)
    }
  })

  watch([verbSuggestions, () => state.step], ([suggestions, currentStep]) => {
    if (currentStep === 'verb') {
      if (state.selectedIndex >= suggestions.length) {
        state.selectedIndex = 0
      }
    }
  })

  // Keep selection in range for entity and field suggestion sources
  watch([entitySuggestions, () => state.step], ([suggestions, currentStep]) => {
    if (currentStep === 'entity') {
      if (state.selectedIndex >= suggestions.length) state.selectedIndex = 0
    }
  })

  watch([panelItems, inlineItems, currentChoices, () => state.step, () => state.activeFlagId], ([panel, inline, choices, currentStep]) => {
    if (currentStep !== 'fields') return
    const len = (choices && choices.length) ? choices.length
      : (panel && panel.length) ? panel.length
      : (inline && inline.length) ? inline.length
      : 0
    if (len === 0) {
      state.selectedIndex = 0
    } else if (state.selectedIndex >= len) {
      state.selectedIndex = 0
    }
  })

  const lookupTimers: Record<string, any> = {}
  watch([() => state.q, currentField, () => state.step, companySource, userSource, () => state.params.email], async ([qv, cf, st]) => {
    const schedule = (key: string, ms: number, fn: () => void) => {
      clearTimeout(lookupTimers[key])
      lookupTimers[key] = setTimeout(fn, ms)
    }

    if (st !== 'fields' || !cf || (cf as any).type !== 'remote') {
      panelItems.value = []
      inlineItems.value = []
      return
    }

    const qstr = (qv as string) || ''
    const run = async () => {
      const items = await provider.fromField(cf as any, qstr, state.params)
      if ((cf as any).picker === 'panel') {
        panelItems.value = items
      } else {
        inlineItems.value = items
      }
    }

    schedule('remote-lookup:' + (cf as any).id, 160, run)
  })

  // Dynamically scope company suggestions to the selected user during unassign flow
  watch([
    () => state.selectedVerb,
    () => state.params.email,
    () => state.step,
    isSuperAdmin,
  ], ([verb, email, stepVal, isSA]) => {
    const action = verb ? (verb as any).action : ''
    const isFields = stepVal === 'fields'
    if (isFields && action === 'company.unassign' && email) {
      companySource.value = 'byUser'
    } else {
      // Reset to default scope outside of unassign-with-email context
      companySource.value = isSA ? 'all' : 'me'
    }
  })

  // Populate panel items for UI list actions (companies/users)
  watch([
    isUIList,
    () => state.step,
    () => state.q,
    companySource,
    userSource,
    () => state.params.email,
    () => state.selectedVerb,
  ], async ([isList, currentStep, qVal, coSource, uSource, email, selectedVerb]) => {
    if (!isList || currentStep !== 'fields' || !selectedVerb) return
    if (selectedVerb && selectedVerb.fields.length > 0) {
      const fieldDef = selectedVerb.fields[0]
      try {
        const items = await provider.fromField(fieldDef, state.q, state.params)
        panelItems.value = items
      } catch (e) {
        panelItems.value = []
      }
    }
  })

  // Load user details if email param holds an id (no @)
  watch(() => state.params.email, (val) => {
    const v = String(val || '')
    if (v && !v.includes('@')) ensureUserDetails(v)
  })

  // Exit action mode when leaving UI list context
  watch([isUIList, () => state.step], ([isList, currentStep]) => {
    if (currentStep !== 'fields' || !isList) {
      state.uiListActionMode = false
      state.uiListActionIndex = 0
    }
  })

  function performUIListAction() {
    const item = highlightedItem.value
    if (!item) return
    const meta = item.meta || {}
    if (showUserPicker.value) {
      const email = meta.email || item.value
      if (state.uiListActionIndex === 0) {
        // Assign to company flow
        quickAssignUserToCompany(email)
      } else if (state.uiListActionIndex === 1) {
        startVerb('user', 'delete', { email })
      } else if (state.uiListActionIndex === 2) {
        startVerb('user', 'update', { email })
      }
    } else if (showCompanyPicker.value) {
      const id = meta.id || item.value
      if (state.uiListActionIndex === 0) {
        quickAssignToCompany(id)
      } else if (state.uiListActionIndex === 1) {
        setActiveCompany(id)
      } else if (state.uiListActionIndex === 2) {
        startVerb('company', 'delete', { company: id })
      } else if (state.uiListActionIndex === 3) {
        loadCompanyMembers(id)
      }
    }
  }

  const api = {
    inputEl,
    isSuperAdmin, currentCompanyId, userSource, companySource,
    panelItems, inlineItems, // Replaces userOptions, companyOptions, etc.
    companyDetails, companyMembers, companyMembersLoading, userDetails,
    entitySuggestions, verbSuggestions, availableFlags, filledFlags, currentField, dashParameterMatch, allRequiredFilled, currentChoices,
    isUIList, showUserPicker, showCompanyPicker, showGenericPanelPicker, inlineSuggestions,
    highlightedUser, highlightedCompany, highlightedItem,
    statusText, getTabCompletion,
    uiListActionCount,
    animateFlag, selectFlag, editFilledFlag, completeCurrentFlag, cycleToLastFilledFlag, handleDashParameter,
    loadCompanyMembers, ensureCompanyDetails, startVerb, quickAssignToCompany, setActiveCompany, quickAssignUserToCompany, quickUnassignUserFromCompany,
    resetAll, goHome, goBack,
    selectEntity, selectVerb, selectChoice, execute,
    pickUserEmail, pickCompanyName, pickGeneric,
    performUIListAction,
    parseAndStartFromInput,
    ensureUserDetails,
  }

  // Expose self-reference for hook contexts
  paletteApi = {
    ...toRefs(state),
    ...api,
  } as UsePalette
  // Load overlays + capabilities when palette opens
  watch(() => state.open, (isOpen) => { if (isOpen) { loadOverlays(); loadCapabilities(); } }, { immediate: false })

  return paletteApi as UsePalette
}
  export type UsePalette = ReturnType<typeof usePalette>
