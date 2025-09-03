import { reactive, ref, computed, nextTick, watch, toRefs, type Ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Fuse from 'fuse.js'
import { http, ensureCsrf, withIdempotency } from '@/lib/http'
import { entities, type EntityDef, type VerbDef, type FieldDef } from '@/palette/entities'
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
  })

  const inputEl = ref<HTMLInputElement | null>(null)

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

  // Fuzzy Search
  const entFuse = new Fuse(entities, { keys: ['label', 'aliases'], includeScore: true, threshold: 0.3 })

  // --- COMPUTED PROPERTIES ---

  const entitySuggestions = computed(() => {
    if (state.q.length < 2) return entities.slice(0, 6)
    const results = entFuse.search(state.q)
    return results.map(r => r.item).slice(0, 6)
  })

  const verbSuggestions = computed(() => {
    if (!state.selectedEntity) return []
    const verbs = state.selectedEntity.verbs
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
    if (showUserPicker.value) return 2 // Assign to company, Delete user
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
    if (val || !currentField.value.required) {
      const completingFlagId = state.activeFlagId
      if (val) state.params[completingFlagId] = val

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

  function startVerb(entityId: string, verbId: string, initialParams: Record<string, any> = {}) {
    const entity = entities.find(e => e.id === entityId) || null
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

    if (!allRequiredFilled.value) return

    state.executing = true
    try {
      const response = await http.post('/commands', state.params, { headers: withIdempotency({ 'X-Action': state.selectedVerb.action }) })
      state.results = [{ success: true, action: state.selectedVerb.action, params: state.params, timestamp: new Date().toISOString(), message: `Successfully executed ${state.selectedEntity?.label} ${state.selectedVerb.label}`, data: response.data }, ...state.results.slice(0, 4)]
      // Optional postExecute hook (cleanup, navigation, etc.)
      if (typeof state.selectedVerb.postExecute === 'function') {
        try {
          await state.selectedVerb.postExecute({ response: response.data, params: state.params, palette: paletteApi })
        } catch (_) { /* ignore hook errors */ }
      }
      state.showResults = true
      setTimeout(() => { resetAll(); state.open = false }, 2000)
    } catch (error: any) {
      state.results = [{ success: false, action: state.selectedVerb.action, params: state.params, timestamp: new Date().toISOString(), message: `Failed to execute ${state.selectedEntity?.label} ${state.selectedVerb.label}`, error: error.response?.data || error.message }, ...state.results.slice(0, 4)]
      state.showResults = true
    } finally {
      state.executing = false
    }
  }

  function pickUserEmail(email: string) {
    if (currentField.value?.id === 'email') {
      state.q = email
      setTimeout(completeCurrentFlag, 10)
    } else {
      const userEntity = entities.find(e => e.id === 'user') || null
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
      const coEntity = entities.find(e => e.id === 'company') || null
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

  // Populate panel items for UI list actions (companies/users)
  watch([isUIList, () => state.step, () => state.q, companySource, userSource, () => state.params.email, () => state.selectedVerb], async ([isList, currentStep, qVal, coSource, uSource, email, verb]) => {
    if (!isList || currentStep !== 'fields' || !verb) return
    const verb = state.selectedVerb
    if (verb && verb.fields.length > 0) {
      const fieldDef = verb.fields[0]
      try {
        const items = await provider.fromField(fieldDef, state.q, state.params)
        panelItems.value = items
      } catch (e) {
        panelItems.value = []
      }
    }
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
    open, q, step, selectedEntity, selectedVerb, params, inputEl, selectedIndex, executing, results, showResults, stashParams,
    activeFlagId, flagAnimating, editingFlagId, deleteConfirmText, deleteConfirmRequired,
    isSuperAdmin, currentCompanyId, userSource, companySource,
    panelItems, inlineItems, // Replaces userOptions, companyOptions, etc.
    companyDetails, companyMembers, companyMembersLoading, userDetails,
    entitySuggestions, verbSuggestions, availableFlags, filledFlags, currentField, dashParameterMatch, allRequiredFilled, currentChoices,
    isUIList, showUserPicker, showCompanyPicker, showGenericPanelPicker, inlineSuggestions,
    highlightedUser, highlightedCompany, highlightedItem,
    statusText, getTabCompletion,
    uiListActionMode, uiListActionIndex, uiListActionCount,
    animateFlag, selectFlag, editFilledFlag, completeCurrentFlag, cycleToLastFilledFlag, handleDashParameter,
    loadCompanyMembers, ensureCompanyDetails, startVerb, quickAssignToCompany, setActiveCompany, quickAssignUserToCompany, quickUnassignUserFromCompany,
    resetAll, goHome, goBack,
    selectEntity, selectVerb, selectChoice, execute,
    pickUserEmail, pickCompanyName, pickGeneric,
    performUIListAction,
  }

  // Expose self-reference for hook contexts
  paletteApi = {
    ...toRefs(state),
    ...api,
  } as UsePalette
  return paletteApi as UsePalette
}

export type UsePalette = ReturnType<typeof usePalette>
