// resources/js/palette/composables/usePalette.ts
import { ref, computed, nextTick, watch, type Ref } from 'vue'
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

export function usePalette() {
  const paletteApi = {} as UsePalette

  // Core State
  const open = ref(false)
  const q = ref('')
  const step = ref<Step>('entity')
  const selectedEntity = ref<EntityDef | null>(null)
  const selectedVerb = ref<VerbDef | null>(null)
  const params = ref<Record<string, any>>({})
  const inputEl = ref<HTMLInputElement | null>(null)
  const selectedIndex = ref(0)
  const executing = ref(false)
  const results = ref<any[]>([])
  const showResults = ref(false)
  const stashParams = ref<Record<string, string>>({})

  // Animation & UI State
  const activeFlagId = ref<string | null>(null)
  const flagAnimating = ref<string | null>(null)
  const editingFlagId = ref<string | null>(null)

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
  const deleteConfirmText = ref('')
  const deleteConfirmRequired = ref('')

  // Suggestions Provider
  const provider = useSuggestions({ isSuperAdmin, currentCompanyId, userSource, companySource, q, params })

  // Fuzzy Search
  const entFuse = new Fuse(entities, { keys: ['label', 'aliases'], includeScore: true, threshold: 0.3 })

  // --- COMPUTED PROPERTIES ---

  const entitySuggestions = computed(() => {
    if (q.value.length < 2) return entities.slice(0, 6)
    const results = entFuse.search(q.value)
    return results.map(r => r.item).slice(0, 6)
  })

  const verbSuggestions = computed(() => {
    if (!selectedEntity.value) return []
    const verbs = selectedEntity.value.verbs
    const needle = q.value.trim().toLowerCase()
    if (!needle) return verbs
    return verbs.filter(v => v.label.toLowerCase().includes(needle) || v.id.toLowerCase().includes(needle))
  })

  const availableFlags = computed<FieldDef[]>(() => {
    if (!selectedVerb.value) return []
    return selectedVerb.value.fields.filter(f => !params.value[f.id] && f.id !== activeFlagId.value)
  })

  const filledFlags = computed<FieldDef[]>(() => {
    if (!selectedVerb.value) return []
    return selectedVerb.value.fields.filter(f => params.value[f.id] && f.id !== activeFlagId.value)
  })

  const currentField = computed<FieldDef | undefined>(() => {
    if (activeFlagId.value && selectedVerb.value) {
      return selectedVerb.value.fields.find(f => f.id === activeFlagId.value)
    }
    return undefined
  })

  const dashParameterMatch = computed(() => {
    if (step.value !== 'fields' || !selectedVerb.value || activeFlagId.value) return null
    if (!q.value.startsWith('-')) return null
    const paramName = q.value.slice(1).toLowerCase()
    return selectedVerb.value.fields.find(f => f.id.toLowerCase().startsWith(paramName) || f.placeholder.toLowerCase().startsWith(paramName))
  })

  const allRequiredFilled = computed(() => {
    if (!selectedVerb.value) return false
    // UI-only actions never show Execute
    if (selectedVerb.value.action.startsWith('ui.')) return false
    return selectedVerb.value.fields.filter(f => f.required).every(f => params.value[f.id])
  })

  const currentChoices = computed<string[]>(() => {
    const f = currentField.value
    if (!f) return []
    if (f.type === 'select') return f.options || []
    return []
  })

  const isUIList = computed(() => !!selectedVerb.value && selectedVerb.value.action.startsWith('ui.list.'))

  const showUserPicker = computed(() => {
    if (!selectedVerb.value) return false
    if (isUIList.value && selectedVerb.value.action === 'ui.list.users') return true
    const f: any = currentField.value
    return f?.type === 'remote' && f?.picker === 'panel' && f?.source?.endpoint?.includes('/users/')
  })

  const showCompanyPicker = computed(() => {
    if (!selectedVerb.value) return false
    if (isUIList.value && selectedVerb.value.action === 'ui.list.companies') return true
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
    if (step.value !== 'fields' || !f || f.type !== 'remote' || f.picker !== 'inline') return []
    const term = (q.value || '').toString().trim()
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
      return panelItems.value[Math.min(selectedIndex.value, panelItems.value.length - 1)]
    }
    return null
  })
  // For backward compatibility in the template until it's refactored
  const highlightedUser = computed(() => showUserPicker.value ? highlightedItem.value : null)
  const highlightedCompany = computed(() => showCompanyPicker.value ? highlightedItem.value : null)

  const statusText = computed(() => {
    if (step.value === 'entity') return 'SELECT_ENTITY'
    if (step.value === 'verb') return 'SELECT_ACTION'
    if (step.value === 'fields') return activeFlagId.value ? 'INPUT_VALUE' : 'SELECT_PARAM'
    return 'READY'
  })

  const getTabCompletion = computed(() => {
    if (step.value === 'entity' && q.value.length > 0) {
      const matches = entitySuggestions.value.filter(e => e.label.startsWith(q.value.toLowerCase()) || e.aliases.some(a => a.startsWith(q.value.toLowerCase())))
      if (matches.length === 1) return matches[0].label
      if (matches.length > 1) {
        const labels = matches.map(m => m.label)
        let commonPrefix = labels[0]
        for (let i = 1; i < labels.length; i++) {
          while (!labels[i].startsWith(commonPrefix) && commonPrefix.length > 0) {
            commonPrefix = commonPrefix.slice(0, -1)
          }
        }
        if (commonPrefix.length > q.value.length) return commonPrefix
      }
    }
    return q.value
  })

  // --- METHODS ---

  function animateFlag(flagId: string) {
    flagAnimating.value = flagId
    setTimeout(() => { flagAnimating.value = null }, 300)
  }

  function selectFlag(flagId: string) {
    if (activeFlagId.value === flagId) return
    animateFlag(flagId)
    activeFlagId.value = flagId
    q.value = ''
    selectedIndex.value = 0
    nextTick(() => inputEl.value?.focus())

    const field = selectedVerb.value?.fields.find(f => f.id === flagId)
    let defVal: string | undefined
    if (field && typeof (field as any).default !== 'undefined') {
      defVal = typeof (field as any).default === 'function' ? (field as any).default(params.value) : (field as any).default
    }
    if (!params.value[flagId] && defVal) {
      q.value = defVal
      nextTick(() => { inputEl.value?.focus(); inputEl.value?.select() })
    }
  }

  function editFilledFlag(flagId: string) {
    const currentValue = params.value[flagId]
    delete params.value[flagId]
    activeFlagId.value = flagId
    q.value = currentValue || ''
    editingFlagId.value = flagId
    nextTick(() => {
      if (inputEl.value) {
        inputEl.value.focus()
        inputEl.value.select()
      }
    })
  }

  function completeCurrentFlag() {
    if (!activeFlagId.value || !currentField.value) return
    const val = q.value.trim()
    if (val || !currentField.value.required) {
      const completingFlagId = activeFlagId.value
      if (val) params.value[completingFlagId] = val

      let nextField: FieldDef | undefined
      if (selectedVerb.value) {
        const nextRequired = selectedVerb.value.fields.find(f => f.required && !params.value[f.id])
        const nextAvailable = selectedVerb.value.fields.find(f => !params.value[f.id])
        nextField = nextRequired || nextAvailable
      }

      activeFlagId.value = null
      editingFlagId.value = null
      q.value = ''
      selectedIndex.value = 0

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

  function startVerb(entityId: string, verbId: string, initialParams: Record<string, any> = {}) {
    const entity = entities.find(e => e.id === entityId) || null
    if (!entity) return

    selectedEntity.value = entity
    const verb = entity.verbs.find(v => v.id === verbId) || null
    if (!verb) return

    selectedVerb.value = verb
    step.value = 'fields'
    params.value = { ...initialParams }

    // Find the first field to focus that isn't already pre-filled.
    // Prioritize required fields.
    const nextField = verb.fields.find(f => f.required && !params.value[f.id])
                   || verb.fields.find(f => !params.value[f.id])

    if (nextField) {
      selectFlag(nextField.id)
    } else {
      // All fields are filled, just focus the input.
      activeFlagId.value = null
    }

    selectedIndex.value = 0
    q.value = ''
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
    step.value = 'entity'
    q.value = ''
    selectedEntity.value = null
    selectedVerb.value = null
    params.value = {}
    selectedIndex.value = 0
    executing.value = false
    activeFlagId.value = null
    editingFlagId.value = null
  }

  function goHome() {
    step.value = 'entity'
    q.value = ''
    selectedVerb.value = null
    selectedEntity.value = null
    selectedIndex.value = 0
    activeFlagId.value = null
    editingFlagId.value = null
  }

  function goBack() {
    if (step.value === 'fields' && activeFlagId.value) {
      activeFlagId.value = null
      editingFlagId.value = null
      q.value = ''
      return
    }
    if (step.value === 'fields' && selectedVerb.value) {
      if (q.value) { q.value = ''; return }
      selectedVerb.value = null
      step.value = 'verb'
      q.value = ''
      selectedIndex.value = 0
      return
    }
    if (step.value === 'verb' && selectedEntity.value) {
      if (q.value) { q.value = ''; return }
      selectedEntity.value = null
      step.value = 'entity'
      q.value = ''
      selectedIndex.value = 0
      return
    }
    if (step.value === 'entity' && q.value) {
      q.value = ''
      return
    }
    open.value = false
  }

  function selectEntity(entity: EntityDef) {
    selectedEntity.value = entity
    step.value = 'verb'
    q.value = ''
    selectedIndex.value = 0
    nextTick(() => inputEl.value?.focus())
  }

  function selectVerb(verb: VerbDef) {
    selectedVerb.value = verb
    step.value = 'fields'
    q.value = ''
    selectedIndex.value = 0
    activeFlagId.value = null
    nextTick(() => inputEl.value?.focus())

    if (Object.keys(stashParams.value).length > 0 && selectedVerb.value) {
      for (const f of selectedVerb.value.fields) {
        const v = stashParams.value[f.id]
        if (v && !params.value[f.id]) params.value[f.id] = v
      }
      stashParams.value = {}
    }

    // No need to wait, select the first required field immediately.
    const firstRequired = verb.fields.find(f => f.required)
    if (firstRequired) selectFlag(firstRequired.id)
  }

  function selectChoice(choice: string) {
    if (!currentField.value) return
    q.value = choice
    setTimeout(completeCurrentFlag, 50)
  }

  async function execute() {
    if (!selectedVerb.value) return
    if (selectedVerb.value.action.startsWith('ui.')) return

    if (selectedVerb.value.id === 'delete' && params.value['company']) {
      const coId = params.value['company']
      const details = companyDetails.value[coId]
      if (details) {
        if (!deleteConfirmRequired.value) deleteConfirmRequired.value = details.slug || details.name
        if (!deleteConfirmText.value || deleteConfirmText.value !== deleteConfirmRequired.value) return
      }
    }

    if (!allRequiredFilled.value) return

    executing.value = true
    try {
      const response = await http.post('/commands', params.value, { headers: withIdempotency({ 'X-Action': selectedVerb.value.action }) })
      results.value = [{ success: true, action: selectedVerb.value.action, params: params.value, timestamp: new Date().toISOString(), message: `Successfully executed ${selectedEntity.value?.label} ${selectedVerb.value.label}`, data: response.data }, ...results.value.slice(0, 4)]
      showResults.value = true
      setTimeout(() => { resetAll(); open.value = false }, 2000)
    } catch (error: any) {
      results.value = [{ success: false, action: selectedVerb.value.action, params: params.value, timestamp: new Date().toISOString(), message: `Failed to execute ${selectedEntity.value?.label} ${selectedVerb.value.label}`, error: error.response?.data || error.message }, ...results.value.slice(0, 4)]
      showResults.value = true
    } finally {
      executing.value = false
    }
  }

  function pickUserEmail(email: string) {
    if (currentField.value?.id === 'email') {
      q.value = email
      setTimeout(completeCurrentFlag, 10)
    } else {
      const userEntity = entities.find(e => e.id === 'user') || null
      if (userEntity) {
        selectedEntity.value = userEntity
        step.value = 'verb'
        q.value = ''
        selectedIndex.value = 0
        stashParams.value = { email }
      } else {
        q.value = email
      }
    }
  }

  function pickCompanyName(idOrName: string) {
    if (currentField.value?.id === 'company') {
      q.value = idOrName
      setTimeout(completeCurrentFlag, 10)
    } else {
      const coEntity = entities.find(e => e.id === 'company') || null
      if (coEntity) {
        selectedEntity.value = coEntity
        step.value = 'verb'
        q.value = ''
        selectedIndex.value = 0
        stashParams.value = { company: idOrName }
      } else {
        q.value = idOrName
      }
    }
  }

  function pickGeneric(value: string) {
    if (currentField.value) {
      q.value = value
      setTimeout(completeCurrentFlag, 10)
    } else {
      q.value = value
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

  watch([q, step], ([newQ, newStep]) => {
    if (newStep === 'entity' && newQ.length >= 2) {
      const exact = entitySuggestions.value.find(e => e.label.toLowerCase() === newQ.toLowerCase() || e.aliases.some(a => a.toLowerCase() === newQ.toLowerCase()))
      if (exact) setTimeout(() => selectEntity(exact), 100)
    }
  })

  watch([verbSuggestions, step], () => {
    if (step.value === 'verb') {
      if (selectedIndex.value >= verbSuggestions.value.length) {
        selectedIndex.value = 0
      }
    }
  })

  const lookupTimers: Record<string, any> = {}
  watch([q, currentField, step, companySource, userSource, () => params.value.email], async ([qv, cf, st]) => {
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
      const items = await provider.fromField(cf as any, qstr, params.value)
      if ((cf as any).picker === 'panel') {
        panelItems.value = items
      } else {
        inlineItems.value = items
      }
    }

    schedule('remote-lookup:' + (cf as any).id, 160, run)
  })

  // Populate panel items for UI list actions (companies/users)
  watch([isUIList, step, q, companySource, userSource, () => params.value.email, selectedVerb], async ([ui, st]) => {
    if (!ui || st !== 'fields') return
    const verb = selectedVerb.value
    if (verb && verb.fields.length > 0) {
      const fieldDef = verb.fields[0]
      try {
        const items = await provider.fromField(fieldDef, q.value, params.value)
        panelItems.value = items
      } catch (e) {
        panelItems.value = []
      }
    }
  })

  return {
    open, q, step, selectedEntity, selectedVerb, params, inputEl, selectedIndex, executing, results, showResults, stashParams,
    activeFlagId, flagAnimating, editingFlagId,
    isSuperAdmin, currentCompanyId, userSource, companySource,
    panelItems, inlineItems, // Replaces userOptions, companyOptions, etc.
    companyDetails, companyMembers, companyMembersLoading, userDetails, deleteConfirmText, deleteConfirmRequired,
    entitySuggestions, verbSuggestions, availableFlags, filledFlags, currentField, dashParameterMatch, allRequiredFilled, currentChoices,
    isUIList, showUserPicker, showCompanyPicker, showGenericPanelPicker, inlineSuggestions,
    highlightedUser, highlightedCompany, highlightedItem,
    statusText, getTabCompletion,
    animateFlag, selectFlag, editFilledFlag, completeCurrentFlag, cycleToLastFilledFlag, handleDashParameter,
    loadCompanyMembers, startVerb, quickAssignToCompany, setActiveCompany, quickAssignUserToCompany, quickUnassignUserFromCompany,
    resetAll, goHome, goBack,
    selectEntity, selectVerb, selectChoice, execute,
    pickUserEmail, pickCompanyName, pickGeneric,
  }
}

export type UsePalette = ReturnType<typeof usePalette>
