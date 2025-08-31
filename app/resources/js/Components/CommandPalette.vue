<!-- resources/js/Components/CommandPalette.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { Dialog, DialogPanel } from '@headlessui/vue'
import { usePage } from '@inertiajs/vue3'
import Fuse from 'fuse.js'
import axios from 'axios'
import { entities, type EntityDef, type VerbDef, type FieldDef } from '@/palette/entities'

type Step = 'entity' | 'verb' | 'fields'
const open = ref(false)
const q = ref('')
const step = ref<Step>('entity')
const selectedEntity = ref<EntityDef|null>(null)
const selectedVerb = ref<VerbDef|null>(null)
const currentFieldIndex = ref(0)
const params = ref<Record<string, any>>({})
const inputEl = ref<HTMLInputElement|null>(null)
const companyOptions = ref<Array<{id:string,name:string}>>([])
const userOptions = ref<Array<{id:string,name:string,email:string}>>([])
// New picker options
const currencyOptions = ref<Array<{code:string,name:string,symbol?:string}>>([])
const languageOptions = ref<Array<{code:string,name:string,native_name?:string,rtl:boolean}>>([])
const localeOptions = ref<Array<{tag:string,name?:string,native_name?:string,language_code:string,country_code?:string}>>([])
const countryOptions = ref<Array<{code:string,alpha3?:string,name:string,emoji?:string}>>([])
const selectedIndex = ref(0)
const executing = ref(false)
const results = ref<any[]>([])
const showResults = ref(false)
const stashParams = ref<Record<string, string>>({})
const page = usePage<any>()
const isSuperAdmin = computed(() => !!page.props?.auth?.isSuperAdmin)
const currentCompanyId = computed(() => page.props?.auth?.companyId || null)
const userSource = ref<'all'|'company'>(isSuperAdmin.value ? 'all' : 'company')
const companySource = ref<'all'|'me'|'byUser'>(isSuperAdmin.value ? 'all' : 'me')

// New animation state
const activeFlagId = ref<string|null>(null)
const flagAnimating = ref<string|null>(null)
const editingFlagId = ref<string|null>(null)
const animatingToCompleted = ref<string|null>(null)

// Fuzzy search for entities
const entFuse = new Fuse(entities, {
  keys: ['label', 'aliases'],
  includeScore: true,
  threshold: 0.3
})

// Smart entity matching - starts fuzzy search after 2 chars
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
  return verbs.filter(v =>
    v.label.toLowerCase().includes(needle) ||
    v.id.toLowerCase().includes(needle)
  )
})

// Available flags (not yet filled)
const availableFlags = computed<FieldDef[]>(() => {
  if (!selectedVerb.value) return []
  return selectedVerb.value.fields.filter(f => !params.value[f.id] && f.id !== activeFlagId.value && f.id !== animatingToCompleted.value)
})

// Filled flags (completed parameters)
const filledFlags = computed<FieldDef[]>(() => {
  if (!selectedVerb.value) return []
  return selectedVerb.value.fields.filter(f => params.value[f.id] && f.id !== activeFlagId.value && f.id !== animatingToCompleted.value)
})

const currentField = computed<FieldDef|undefined>(() => {
  if (activeFlagId.value && selectedVerb.value) {
    return selectedVerb.value.fields.find(f => f.id === activeFlagId.value)
  }
  return undefined
})

// No ghost prefix; active parameter now appears in breadcrumbs



// Detect dash-prefixed parameter input
const dashParameterMatch = computed(() => {
  if (step.value !== 'fields' || !selectedVerb.value || activeFlagId.value) return null
  if (!q.value.startsWith('-')) return null

  const paramName = q.value.slice(1).toLowerCase()
  return selectedVerb.value.fields.find(f =>
    f.id.toLowerCase().startsWith(paramName) ||
    f.placeholder.toLowerCase().startsWith(paramName)
  )
})

const allRequiredFilled = computed(() => {
  if (!selectedVerb.value) return false
  return selectedVerb.value.fields
    .filter(f => f.required)
    .every(f => params.value[f.id])
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
  return currentField.value?.id === 'email'
})
const showCompanyPicker = computed(() => {
  if (!selectedVerb.value) return false
  if (isUIList.value && selectedVerb.value.action === 'ui.list.companies') return true
  return currentField.value?.id === 'company'
})
const showCurrencyPicker = computed(() => step.value === 'fields' && currentField.value?.id === 'base_currency')
const showLanguagePicker = computed(() => step.value === 'fields' && currentField.value?.id === 'language')
const showLocalePicker = computed(() => step.value === 'fields' && currentField.value?.id === 'locale')
const showCountryPicker = computed(() => step.value === 'fields' && currentField.value?.id === 'country')

// Inline suggestions (no separate picker boxes)
type InlineItem = { type: 'currency'|'language'|'locale'|'country', value: string, label: string }
const inlineSuggestions = computed<InlineItem[]>(() => {
  if (step.value !== 'fields' || !currentField.value) return []
  const id = currentField.value.id
  const term = (q.value || '').toString().trim()
  let list: InlineItem[] = []
  if (id === 'base_currency') {
    list = currencyOptions.value.map(c => ({ type: 'currency', value: c.code, label: `${c.code} — ${c.name}${c.symbol ? ` (${c.symbol})` : ''}` }))
  } else if (id === 'language') {
    list = languageOptions.value.map(l => ({ type: 'language', value: l.code, label: `${l.code} — ${l.native_name || l.name}${l.rtl ? ' (RTL)' : ''}` }))
  } else if (id === 'locale') {
    list = localeOptions.value.map(l => ({ type: 'locale', value: l.tag, label: `${l.tag} — ${l.native_name || l.name || ''}`.trim() }))
  } else if (id === 'country') {
    list = countryOptions.value.map(c => ({ type: 'country', value: c.code, label: `${c.emoji ? c.emoji + ' ' : ''}${c.name} — ${c.code}` }))
  }
  if (list.length === 0) return []

  // Determine anchor index for centering
  const lower = term.toLowerCase()
  let idx = list.findIndex(i => i.value.toLowerCase() === lower)
  if (idx === -1 && lower) idx = list.findIndex(i => i.value.toLowerCase().startsWith(lower))
  if (idx === -1 && lower) idx = list.findIndex(i => i.label.toLowerCase().includes(lower))
  if (idx === -1) idx = 0

  const start = Math.max(0, idx - 3)
  return list.slice(start, Math.min(list.length, start + 7))
})

const highlightedUser = computed(() => (showUserPicker.value && userOptions.value.length > 0)
  ? userOptions.value[Math.min(selectedIndex.value, userOptions.value.length - 1)]
  : null)
const highlightedCompany = computed(() => (showCompanyPicker.value && companyOptions.value.length > 0)
  ? companyOptions.value[Math.min(selectedIndex.value, companyOptions.value.length - 1)]
  : null)
const highlightedCurrency = computed(() => (showCurrencyPicker.value && currencyOptions.value.length > 0)
  ? currencyOptions.value[Math.min(selectedIndex.value, currencyOptions.value.length - 1)] : null)
const highlightedLanguage = computed(() => (showLanguagePicker.value && languageOptions.value.length > 0)
  ? languageOptions.value[Math.min(selectedIndex.value, languageOptions.value.length - 1)] : null)
const highlightedLocale = computed(() => (showLocalePicker.value && localeOptions.value.length > 0)
  ? localeOptions.value[Math.min(selectedIndex.value, localeOptions.value.length - 1)] : null)
const highlightedCountry = computed(() => (showCountryPicker.value && countryOptions.value.length > 0)
  ? countryOptions.value[Math.min(selectedIndex.value, countryOptions.value.length - 1)] : null)
const companyDetails = ref<Record<string, any>>({})
const companyMembers = ref<Record<string, Array<{id:string,name:string,email:string,role:string}>>>({})
const companyMembersLoading = ref<Record<string, boolean>>({})
const userDetails = ref<Record<string, any>>({})
const deleteConfirmText = ref('')
const deleteConfirmRequired = ref('')

// Animation helpers
function animateFlag(flagId: string) {
  flagAnimating.value = flagId
  setTimeout(() => {
    flagAnimating.value = null
  }, 300)
}

function selectFlag(flagId: string) {
  if (activeFlagId.value === flagId) return

  animateFlag(flagId)
  setTimeout(() => {
    activeFlagId.value = flagId
    q.value = ''
    selectedIndex.value = 0
    nextTick(() => inputEl.value?.focus())

    // Preload data if needed
    if (flagId === 'company') preloadCompanies()
    if (flagId === 'email') lookupUsers('')
    if (flagId === 'base_currency') lookupCurrencies('')
    if (flagId === 'language') lookupLanguages('')
    if (flagId === 'locale') lookupLocales('')
    if (flagId === 'country') lookupCountries('')

    // Apply sensible defaults and select so typing replaces; Tab accepts
    const defaults: Record<string,string> = { base_currency: 'USD', language: 'en', locale: 'en-US' }
    if (!params.value[flagId] && defaults[flagId]) {
      q.value = defaults[flagId]
      nextTick(() => { inputEl.value?.focus(); inputEl.value?.select() })
    }
  }, 200)
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

    if (val) {
      params.value[completingFlagId] = val
    }

    // Figure out the next field right away for seamless handoff
    let nextField: FieldDef | undefined
    if (selectedVerb.value) {
      const nextRequired = selectedVerb.value.fields.find(f => f.required && !params.value[f.id])
      const nextAvailable = selectedVerb.value.fields.find(f => !params.value[f.id])
      nextField = nextRequired || nextAvailable
    }

    // Start animation sequence
    animatingToCompleted.value = completingFlagId
    activeFlagId.value = null
    editingFlagId.value = null
    q.value = ''
    selectedIndex.value = 0

    // Kick off the next parameter animation slightly earlier for continuity
    if (nextField) {
      setTimeout(() => selectFlag(nextField!.id), 200)
    }

    // Complete the downward animation after delay
    setTimeout(() => {
      animatingToCompleted.value = null
    }, 450)
  }
}

function cycleToLastFilledFlag() {
  const filled = filledFlags.value
  if (filled.length === 0) return

  const lastFlag = filled[filled.length - 1]
  editFilledFlag(lastFlag.id)
}

// Handle dash-prefixed parameter input
function handleDashParameter() {
  const match = dashParameterMatch.value
  if (match) {
    // Clear the dash and parameter name, activate the field
    selectFlag(match.id)
    return true
  }
  return false
}

watch(highlightedCompany, async (co) => {
  if (!co) return
  if (companyDetails.value[co.id]) return
  try {
    await ensureCsrf()
    const { data } = await axios.get(`/web/companies/${encodeURIComponent(co.id)}`)
    companyDetails.value[co.id] = data?.data || {}
  } catch (e) {
    // ignore
  }
})

watch(highlightedUser, async (u) => {
  if (!u) return
  if (userDetails.value[u.id]) return
  try {
    await ensureCsrf()
    const { data } = await axios.get(`/web/users/${encodeURIComponent(u.id)}`)
    userDetails.value[u.id] = data?.data || {}
  } catch (e) { /* ignore */ }
})

async function loadCompanyMembers(companyId: string) {
  if (companyMembersLoading.value[companyId]) return
  companyMembersLoading.value[companyId] = true
  try {
    await ensureCsrf()
    const { data } = await axios.get(`/web/companies/${encodeURIComponent(companyId)}/users`)
    companyMembers.value[companyId] = data?.data || []
  } catch (e) {
    companyMembers.value[companyId] = []
  } finally {
    companyMembersLoading.value[companyId] = false
  }
}

function quickAssignToCompany(companyId: string) {
  const coEntity = entities.find(e => e.id === 'company') || null
  if (!coEntity) return
  selectedEntity.value = coEntity
  const assignVerb = coEntity.verbs.find(v => v.id === 'assign') || null
  selectedVerb.value = assignVerb || null
  step.value = 'fields'
  params.value['company'] = companyId
  const emailField = assignVerb?.fields.find(f => f.id === 'email')
  if (emailField) selectFlag('email')
  selectedIndex.value = 0
  q.value = ''
  nextTick(() => inputEl.value?.focus())
}

async function setActiveCompany(companyId: string) {
  try {
    await ensureCsrf()
    await axios.post('/web/companies/switch', { company_id: companyId })
    window.location.reload()
  } catch (e) { /* ignore */ }
}

function quickAssignUserToCompany(userIdOrEmail: string) {
  const coEntity = entities.find(e => e.id === 'company') || null
  if (!coEntity) return
  selectedEntity.value = coEntity
  const assignVerb = coEntity.verbs.find(v => v.id === 'assign') || null
  selectedVerb.value = assignVerb || null
  step.value = 'fields'
  params.value['email'] = userIdOrEmail
  const companyField = assignVerb?.fields.find(f => f.id === 'company')
  if (companyField) selectFlag('company')
  selectedIndex.value = 0
  q.value = ''
  nextTick(() => inputEl.value?.focus())
}

function quickUnassignUserFromCompany(userEmail: string, companyId: string) {
  const coEntity = entities.find(e => e.id === 'company') || null
  if (!coEntity) return
  selectedEntity.value = coEntity
  const unassignVerb = coEntity.verbs.find(v => v.id === 'unassign') || null
  selectedVerb.value = unassignVerb || null
  step.value = 'fields'
  params.value['email'] = userEmail
  params.value['company'] = companyId
  selectedIndex.value = 0
  q.value = ''
  nextTick(() => inputEl.value?.focus())
}

const statusText = computed(() => {
  if (step.value === 'entity') return 'SELECT_ENTITY'
  if (step.value === 'verb') return 'SELECT_ACTION'
  if (step.value === 'fields') return activeFlagId.value ? 'INPUT_VALUE' : 'SELECT_PARAM'
  return 'READY'
})

// Tab completion logic
const getTabCompletion = computed(() => {
  if (step.value === 'entity' && q.value.length > 0) {
    const matches = entitySuggestions.value.filter(e =>
      e.label.startsWith(q.value.toLowerCase()) ||
      e.aliases.some(a => a.startsWith(q.value.toLowerCase()))
    )

    if (matches.length === 1) {
      return matches[0].label
    }

    if (matches.length > 1) {
      const labels = matches.map(m => m.label)
      let commonPrefix = labels[0]
      for (let i = 1; i < labels.length; i++) {
        while (!labels[i].startsWith(commonPrefix) && commonPrefix.length > 0) {
          commonPrefix = commonPrefix.slice(0, -1)
        }
      }
      if (commonPrefix.length > q.value.length) {
        return commonPrefix
      }
    }
  }
  return q.value
})

// Watch for auto-completion when typing
watch([q, step], ([newQ, newStep]) => {
  if (newStep === 'entity' && newQ.length >= 2) {
    const exact = entitySuggestions.value.find(e =>
      e.label.toLowerCase() === newQ.toLowerCase() ||
      e.aliases.some(a => a.toLowerCase() === newQ.toLowerCase())
    )
    if (exact) {
      setTimeout(() => selectEntity(exact), 100)
    }
  }
})

// Keep selection index in range when filtering verbs
watch([verbSuggestions, step], () => {
  if (step.value === 'verb') {
    if (selectedIndex.value >= verbSuggestions.value.length) {
      selectedIndex.value = 0
    }
  }
})

function resetAll() {
  step.value = 'entity'
  q.value = ''
  selectedEntity.value = null
  selectedVerb.value = null
  currentFieldIndex.value = 0
  params.value = {}
  selectedIndex.value = 0
  executing.value = false
  activeFlagId.value = null
  editingFlagId.value = null
  animatingToCompleted.value = null
}

function goHome() {
  step.value = 'entity'
  q.value = ''
  selectedVerb.value = null
  selectedEntity.value = null
  currentFieldIndex.value = 0
  selectedIndex.value = 0
  activeFlagId.value = null
  editingFlagId.value = null
  animatingToCompleted.value = null
}

function goBack() {
  if (step.value === 'fields' && activeFlagId.value) {
    // Clear active flag first
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

  // Preload company suggestions for company fields
  if (verb.fields.some(f => f.id === 'company')) {
    preloadCompanies()
  }
  if (verb.action === 'ui.list.users') {
    lookupUsers('')
  }
  if (verb.action === 'ui.list.companies') {
    preloadCompanies()
  }

  // Autofill any stashed params
  if (Object.keys(stashParams.value).length > 0 && selectedVerb.value) {
    for (const f of selectedVerb.value.fields) {
      const v = stashParams.value[f.id]
      if (v && !params.value[f.id]) params.value[f.id] = v
    }
    stashParams.value = {}
  }

  // Auto-select first required field
  setTimeout(() => {
    const firstRequired = verb.fields.find(f => f.required)
    if (firstRequired) {
      selectFlag(firstRequired.id)
    }
  }, 100)
}

function selectChoice(choice: string) {
  if (!currentField.value) return
  q.value = choice
  setTimeout(completeCurrentFlag, 50)
}

async function execute() {
  if (!selectedVerb.value) return

  if (selectedVerb.value.action.startsWith('ui.')) {
    return
  }

  // Delete confirmation
  if (selectedVerb.value.id === 'delete' && params.value['company']) {
    const coId = params.value['company']
    const details = companyDetails.value[coId]
    if (details) {
      if (!deleteConfirmRequired.value) deleteConfirmRequired.value = details.slug || details.name
      if (!deleteConfirmText.value || deleteConfirmText.value !== deleteConfirmRequired.value) {
        return
      }
    }
  }

  if (!allRequiredFilled.value) return

  executing.value = true
  try {
    const response = await axios.post('/commands',
      params.value,
      {
        headers: {
          'X-Action': selectedVerb.value.action,
          'X-Idempotency-Key': crypto.randomUUID(),
        }
      }
    )

    const result = {
      success: true,
      action: selectedVerb.value.action,
      params: params.value,
      timestamp: new Date().toISOString(),
      message: `Successfully executed ${selectedEntity.value?.label} ${selectedVerb.value.label}`,
      data: response.data
    }

    results.value = [result, ...results.value.slice(0, 4)]
    showResults.value = true

    console.log('Command executed:', response.data)
    setTimeout(() => {
      resetAll()
      open.value = false
    }, 2000)

  } catch (error) {
    console.error('Command failed:', error)

    const errorResult = {
      success: false,
      action: selectedVerb.value.action,
      params: params.value,
      timestamp: new Date().toISOString(),
      message: `Failed to execute ${selectedEntity.value?.label} ${selectedVerb.value.label}`,
      error: error.response?.data || error.message
    }

    results.value = [errorResult, ...results.value.slice(0, 4)]
    showResults.value = true
    executing.value = false
  }
}

function handleKeydown(e: KeyboardEvent) {
  // Handle dash parameter input
  if (step.value === 'fields' && !activeFlagId.value && e.key === 'Enter' && dashParameterMatch.value) {
    e.preventDefault()
    handleDashParameter()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (step.value === 'entity') {
      selectedIndex.value = Math.min(selectedIndex.value + 1, entitySuggestions.value.length - 1)
    } else if (step.value === 'verb') {
      selectedIndex.value = Math.min(selectedIndex.value + 1, verbSuggestions.value.length - 1)
    } else if (currentChoices.value.length > 0) {
      selectedIndex.value = Math.min(selectedIndex.value + 1, currentChoices.value.length - 1)
    } else {
      const len = showUserPicker.value ? userOptions.value.length
        : showCompanyPicker.value ? companyOptions.value.length
        : showCurrencyPicker.value ? currencyOptions.value.length
        : showLanguagePicker.value ? languageOptions.value.length
        : showLocalePicker.value ? localeOptions.value.length
        : showCountryPicker.value ? countryOptions.value.length
        : 0
      if (len > 0) selectedIndex.value = Math.min(selectedIndex.value + 1, len - 1)
    }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (step.value === 'entity' && entitySuggestions.value[selectedIndex.value]) {
      selectEntity(entitySuggestions.value[selectedIndex.value])
    } else if (step.value === 'verb' && verbSuggestions.value[selectedIndex.value]) {
      selectVerb(verbSuggestions.value[selectedIndex.value])
    } else if (step.value === 'fields') {
      if (currentChoices.value.length > 0 && selectedIndex.value < currentChoices.value.length) {
        selectChoice(currentChoices.value[selectedIndex.value])
      } else if (showUserPicker.value && userOptions.value[selectedIndex.value]) {
        pickUserEmail(userOptions.value[selectedIndex.value].email)
      } else if (showCompanyPicker.value && companyOptions.value[selectedIndex.value]) {
        pickCompanyName(companyOptions.value[selectedIndex.value].name)
      } else if (inlineSuggestions.value.length > 0) {
        const item = inlineSuggestions.value[Math.min(selectedIndex.value, inlineSuggestions.value.length - 1)]
        if (item) {
          if (item.type === 'currency') pickCurrency(item.value)
          else if (item.type === 'language') pickLanguage(item.value)
          else if (item.type === 'locale') pickLocale(item.value)
          else if (item.type === 'country') pickCountry(item.value)
        }
      } else if (showCurrencyPicker.value && currencyOptions.value[selectedIndex.value]) {
        pickCurrency(currencyOptions.value[selectedIndex.value].code)
      } else if (showLanguagePicker.value && languageOptions.value[selectedIndex.value]) {
        pickLanguage(languageOptions.value[selectedIndex.value].code)
      } else if (showLocalePicker.value && localeOptions.value[selectedIndex.value]) {
        pickLocale(localeOptions.value[selectedIndex.value].tag)
      } else if (showCountryPicker.value && countryOptions.value[selectedIndex.value]) {
        pickCountry(countryOptions.value[selectedIndex.value].code)
      } else if (activeFlagId.value) {
        completeCurrentFlag()
      } else if (allRequiredFilled.value) {
        execute()
      }
    }
  } else if (e.key === 'Tab') {
    e.preventDefault()
    if (e.shiftKey && step.value === 'fields' && filledFlags.value.length > 0) {
      cycleToLastFilledFlag()
      return
    }

    if (step.value === 'entity') {
      const completion = getTabCompletion.value
      q.value = completion
    } else if (step.value === 'fields' && activeFlagId.value) {
      completeCurrentFlag()
    } else if (step.value === 'fields') {
      const len = currentChoices.value.length
        || (showUserPicker.value ? userOptions.value.length
        : showCompanyPicker.value ? companyOptions.value.length
        : inlineSuggestions.value.length > 0 ? inlineSuggestions.value.length
        : showCurrencyPicker.value ? currencyOptions.value.length
        : showLanguagePicker.value ? languageOptions.value.length
        : showLocalePicker.value ? localeOptions.value.length
        : showCountryPicker.value ? countryOptions.value.length
        : 0)
      if (len > 0) selectedIndex.value = (selectedIndex.value + 1) % len
    }
  } else if (e.key === 'Escape') {
    e.preventDefault()
    e.stopPropagation()
    goBack()
  }
}

function handleGlobalKeydown(e: KeyboardEvent) {
  const key = (e.key || '').toLowerCase()
  const isCmdK = e.metaKey && !e.ctrlKey && !e.altKey && !e.shiftKey && key === 'k'
  const isCtrlK = e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey && key === 'k'
  const isCtrlShiftK = e.ctrlKey && e.shiftKey && !e.altKey && key === 'k'
  const isCtrlSlash = e.ctrlKey && !e.altKey && (key === '/' || e.code.toLowerCase() === 'slash')
  const isCtrlSpace = e.ctrlKey && !e.shiftKey && !e.altKey && (key === ' ' || e.code.toLowerCase() === 'space')
  const isAltK = e.altKey && !e.ctrlKey && !e.metaKey && key === 'k'

  if (isCmdK || isCtrlShiftK || isCtrlSlash || isCtrlSpace || isAltK) {
    e.preventDefault()
    e.stopPropagation()
    open.value = true
    resetAll()
    nextTick(() => inputEl.value?.focus())
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown, { passive: false })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

async function preloadCompanies() {
  try {
    const query: any = {}
    if (companySource.value === 'byUser' && isSuperAdmin.value && params.value.email) {
      query.user_email = params.value.email
    }
    if (q.value && q.value.length > 0) {
      query.q = q.value
    }
    await ensureCsrf()
    const { data } = await axios.get('/web/companies', { params: query })
    companyOptions.value = (data?.data || []).map((c: any) => ({ id: c.id, name: c.name }))
  } catch (e) {
    // ignore 401 etc.
  }
}

let emailLookupTimer: any = null
let companyLookupTimer: any = null
let currencyLookupTimer: any = null
let languageLookupTimer: any = null
let localeLookupTimer: any = null
let countryLookupTimer: any = null
async function lookupUsers(qstr: string) {
  try {
    const params: any = { q: qstr }
    if (!isSuperAdmin.value || userSource.value === 'company') {
      params.company_id = currentCompanyId.value
    }
    await ensureCsrf()
    const { data } = await axios.get('/web/users/suggest', { params })
    userOptions.value = data?.data || []
  } catch (e) {
    userOptions.value = []
  }
}

async function lookupCurrencies(qstr: string) {
  try {
    await ensureCsrf()
    const { data } = await axios.get('/web/currencies/suggest', { params: { q: qstr, limit: 12 } })
    currencyOptions.value = data?.data || []
  } catch (e) { currencyOptions.value = [] }
}

async function lookupLanguages(qstr: string) {
  try {
    await ensureCsrf()
    const { data } = await axios.get('/web/languages/suggest', { params: { q: qstr, limit: 12 } })
    languageOptions.value = data?.data || []
  } catch (e) { languageOptions.value = [] }
}

async function lookupLocales(qstr: string) {
  try {
    await ensureCsrf()
    const paramsAny: any = { q: qstr, limit: 12 }
    if (params.value?.language) paramsAny.language = params.value.language
    if (params.value?.country) paramsAny.country = params.value.country
    const { data } = await axios.get('/web/locales/suggest', { params: paramsAny })
    localeOptions.value = data?.data || []
  } catch (e) { localeOptions.value = [] }
}

async function lookupCountries(qstr: string) {
  try {
    await ensureCsrf()
    const { data } = await axios.get('/web/countries/suggest', { params: { q: qstr, limit: 12 } })
    countryOptions.value = data?.data || []
  } catch (e) { countryOptions.value = [] }
}

watch([q, currentField, step], ([qv, cf, st]) => {
  if (st === 'fields' && cf && cf.id === 'email') {
    clearTimeout(emailLookupTimer)
    if (!qv || qv.length < 2) { userOptions.value = []; return }
    emailLookupTimer = setTimeout(() => lookupUsers(qv), 200)
  }
  if (st === 'fields' && cf && cf.id === 'company') {
    clearTimeout(companyLookupTimer)
    companyLookupTimer = setTimeout(() => preloadCompanies(), 200)
  }
  if (st === 'fields' && cf && cf.id === 'base_currency') {
    clearTimeout(currencyLookupTimer)
    currencyLookupTimer = setTimeout(() => lookupCurrencies(qv || ''), 150)
  }
  if (st === 'fields' && cf && cf.id === 'language') {
    clearTimeout(languageLookupTimer)
    languageLookupTimer = setTimeout(() => lookupLanguages(qv || ''), 150)
  }
  if (st === 'fields' && cf && cf.id === 'locale') {
    clearTimeout(localeLookupTimer)
    localeLookupTimer = setTimeout(() => lookupLocales(qv || ''), 150)
  }
  if (st === 'fields' && cf && cf.id === 'country') {
    clearTimeout(countryLookupTimer)
    countryLookupTimer = setTimeout(() => lookupCountries(qv || ''), 150)
  }
})

let csrfReady = false
async function ensureCsrf() {
  if (csrfReady) return
  try {
    await axios.get('/sanctum/csrf-cookie')
  } catch (e) {
    // ignore
  } finally {
    csrfReady = true
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

function pickCurrency(code: string) {
  if (currentField.value?.id === 'base_currency') {
    q.value = code
    setTimeout(completeCurrentFlag, 10)
  } else {
    q.value = code
  }
}

function pickLanguage(code: string) {
  if (currentField.value?.id === 'language') {
    q.value = code
    setTimeout(completeCurrentFlag, 10)
  } else { q.value = code }
}

function pickLocale(tag: string) {
  if (currentField.value?.id === 'locale') {
    q.value = tag
    setTimeout(completeCurrentFlag, 10)
  } else { q.value = tag }
}

function pickCountry(code: string) {
  if (currentField.value?.id === 'country') {
    q.value = code
    setTimeout(completeCurrentFlag, 10)
  } else { q.value = code }
}
</script>

<template>
  <!-- Floating Terminal Button -->
  <div class="fixed bottom-4 right-4 z-40">
    <button
      @click="open=true; resetAll(); nextTick(() => inputEl?.focus())"
      class="px-4 py-3 bg-gradient-to-br from-gray-800 to-gray-900 text-green-400 font-mono text-sm rounded-lg border border-green-600/30 hover:border-green-400 shadow-xl flex items-center gap-2 group"
    >
      <span class="text-green-300">$</span>
      <span>command</span>
      <kbd class="px-2 py-1 bg-gray-700/50 rounded text-xs border border-gray-600 group-hover:border-green-400/50">⌘K</kbd>
    </button>
  </div>

  <Dialog :open="open" @close="open=false" class="relative z-50">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" aria-hidden="true" />

    <div class="fixed inset-0 flex items-start justify-center pt-4 sm:pt-20 px-4">
      <div class="w-full max-w-4xl flex flex-col lg:flex-row gap-4" :class="showResults ? 'lg:max-w-5xl' : ''">

        <!-- Main Command Palette - Enhanced design -->
        <DialogPanel class="flex-1 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden" :class="open ? 'scale-100 opacity-100' : 'scale-105 opacity-0'" @keydown="(e) => { if ((e as KeyboardEvent).key === 'Escape') { e.preventDefault(); e.stopPropagation(); goBack() } }">
          <div class="flex flex-col h-96 sm:h-[500px]" @keydown="handleKeydown">

            <!-- Terminal Header - Enhanced -->
            <div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500 shadow-sm"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500 shadow-sm"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500 shadow-sm"></div>
                  </div>
                  <span class="text-gray-400 text-xs hidden sm:inline tracking-wide">accounting-cli v1.0</span>
                </div>
                <div class="flex items-center gap-3">
                  <button class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" @click="goBack">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Back
                  </button>
                  <button class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" @click="goHome">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                  </button>
                  <div class="text-gray-500 text-xs px-2 py-1 bg-gray-800/50 rounded-md border border-gray-700/50">{{ statusText }}</div>
                </div>
              </div>
            </div>

            <!-- Available Flags - Enhanced -->
            <div v-if="step === 'fields' && selectedVerb" class="px-4 py-3 border-b border-gray-700/30 bg-gray-800/40">
              <div class="text-gray-500 text-xs mb-2 font-medium tracking-wide">AVAILABLE PARAMETERS:</div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="flag in availableFlags"
                  :key="flag.id"
                  @click="selectFlag(flag.id)"
                  class="px-3 py-1.5 text-xs rounded-lg border backdrop-blur-sm"
                  :class="[
                    'border-gray-600/50 text-gray-300 bg-gray-800/40 hover:border-orange-500/70 hover:text-orange-300 hover:bg-orange-900/20',
                    ''
                ]"
                >
                  {{ flag.placeholder }}
                  <span v-if="flag.required" class="ml-1 text-red-400">*</span>
                </button>
              </div>
              <div v-if="!activeFlagId && !dashParameterMatch" class="text-gray-600 text-xs mt-2 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                Click a parameter above or type -paramName to start entering values
              </div>
              <div v-if="dashParameterMatch" class="text-orange-400 text-xs mt-2 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                </svg>
                Press Enter to select: {{ dashParameterMatch.placeholder }}
              </div>
            </div>

            <!-- Command Line - Enhanced -->
            <div class="px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/50 to-gray-900/10">
              <div class="flex items-center gap-2">
                <span class="text-green-400 text-lg">❯</span>

                <!-- Breadcrumb - Enhanced (borderless) -->
                <div class="starship-bc">
                  <template v-if="selectedEntity">
                    <div class="seg seg-entity seg-first">
                      {{ selectedEntity.label }}
                    </div>
                    <template v-if="selectedVerb">
                      <div class="seg seg-verb seg-mid">
                        {{ selectedVerb.label }}
                      </div>
                    </template>
                    <template v-if="step === 'fields' && currentField">
                      <div class="seg seg-active seg-last">
                        {{ currentField.placeholder }}<span v-if="currentField.required" class="ml-0.5 text-red-300">*</span>
                      </div>
                    </template>
                  </template>
                </div>

                <div class="flex-1 ml-3">
                  <div class="flex items-center gap-2 relative w-full">

                    <input
                      ref="inputEl"
                      v-model="q"
                      :placeholder="step === 'entity' ? 'Search entities...' : step === 'verb' ? 'Search actions...' : (!activeFlagId ? 'Select parameter or type -param...' : 'Enter value...')"
                      class="flex-1 bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus-visible:ring-0 appearance-none py-2 no-focus-ring rounded-lg px-3 border-0 focus:border-0"
                      :class="[
                        step === 'fields' && currentField ? 'text-orange-300 placeholder-orange-300/50' : '',
                        dashParameterMatch ? 'text-yellow-300' : (step !== 'fields' ? 'text-green-400 placeholder-gray-600' : '')
                      ]"
                      :style="{}"
                      :disabled="executing"
                    />

                    <button
                      v-if="step === 'fields' && activeFlagId && q.trim()"
                      @click="completeCurrentFlag"
                      class="px-3 py-1.5 bg-orange-700/50 text-orange-100 rounded-lg border border-orange-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      Apply
                    </button>

                    <button
                      v-if="step === 'fields' && !activeFlagId && dashParameterMatch"
                      @click="handleDashParameter"
                      class="px-3 py-1.5 bg-yellow-700/50 text-yellow-100 rounded-lg border border-yellow-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                      Select
                    </button>

                    <button
                      v-if="step === 'fields' && allRequiredFilled && !activeFlagId"
                      @click="execute"
                      :disabled="executing"
                      class="px-4 py-1.5 bg-green-700/50 text-green-100 rounded-lg border border-green-600/50 text-xs disabled:opacity-50 flex items-center gap-1 backdrop-blur-sm"
                    >
                      <svg v-if="!executing" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                      </svg>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                      </svg>
                      {{ executing ? 'Executing...' : 'Execute' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Completed Parameters - Enhanced -->
            <div v-if="step === 'fields' && (filledFlags.length > 0 || animatingToCompleted)" class="px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/20 to-gray-900/5">
              <div class="text-gray-500 text-xs mb-2 font-medium tracking-wide">COMPLETED PARAMETERS:</div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="flag in filledFlags"
                  :key="flag.id"
                  @click="editFilledFlag(flag.id)"
                  class="px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 backdrop-blur-sm flex items-center gap-1"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  {{ flag.placeholder }}="{{ params[flag.id] }}"
                </button>
                <!-- Animating parameter -->
                <div
                  v-if="animatingToCompleted && selectedVerb"
                  :key="`animating-${animatingToCompleted}`"
                  class="px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 flex items-center gap-1 backdrop-blur-sm"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  {{ selectedVerb.fields.find(f => f.id === animatingToCompleted)?.placeholder }}="{{ params[animatingToCompleted] }}"
                </div>
              </div>
            </div>

            <!-- Options/Suggestions - Enhanced -->
            <div class="flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5">

              <!-- Entity suggestions - Enhanced -->
              <div v-if="step === 'entity'" class="space-y-2">
                <div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block">Available entities</div>
                <div
                  v-for="(entity, index) in entitySuggestions"
                  :key="entity.id"
                  @click="selectEntity(entity)"
                  class="px-4 py-3 rounded-xl cursor-pointer border"
                  :class="index === selectedIndex ? 'bg-blue-900/30 text-blue-200 border-blue-700/50 scale-[1.02] shadow-lg shadow-blue-500/10' : 'hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent'"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-green-400 font-medium">{{ entity.label }}</span>
                    <span class="text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full">{{ entity.verbs.length }} actions</span>
                  </div>
                  <div class="text-xs text-gray-500 mt-1">
                    aliases: {{ entity.aliases.join(', ') }}
                  </div>
                </div>
              </div>

              <!-- Verb suggestions - Enhanced -->
              <div v-else-if="step === 'verb'" class="space-y-2">
                <div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block">Available actions</div>
                <div
                  v-for="(verb, index) in verbSuggestions"
                  :key="verb.id"
                  @click="selectVerb(verb)"
                  class="px-4 py-3 rounded-xl cursor-pointer border"
                  :class="index === selectedIndex ? 'bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10' : 'hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent'"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-yellow-400 font-medium">{{ verb.label }}</span>
                    <span class="text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full">
                      {{ verb.fields.filter(f => f.required).length }}/{{ verb.fields.length }} required
                    </span>
                  </div>
                  <div class="text-xs text-gray-500 mt-1">
                    {{ verb.fields.map(f => f.placeholder).join(' ') }}
                  </div>
                </div>
              </div>

              <!-- Field input - Enhanced -->
              <div v-else-if="step === 'fields'" class="space-y-4">

                <!-- Select options (finite lists only) - Enhanced -->
                <div v-if="currentChoices.length > 0">
                  <div class="text-gray-500 text-xs px-2 py-1.5 mb-3 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                    Select option
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button
                      v-for="(choice, index) in currentChoices"
                      :key="choice"
                      @click="selectChoice(choice)"
                      class="px-4 py-2.5 text-left rounded-xl border"
                      :class="index === selectedIndex ? 'bg-orange-900/30 text-orange-200 border-orange-700/50 scale-[1.02] shadow-lg shadow-orange-500/10' : 'bg-gray-800/30 hover:bg-gray-700/30 text-gray-300 hover:scale-[1.01] border-transparent'"
                    >
                      {{ choice }}
                    </button>
                  </div>
                </div>

                <!-- Unified pickers - Enhanced -->
            <div v-if="showUserPicker" class="border border-gray-700/50 rounded-xl bg-gray-900/50 backdrop-blur-sm overflow-hidden">
                  <div class="text-gray-500 text-xs px-4 py-2.5 mb-1 bg-gray-800/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                    <span>Users</span>
                    <div class="flex gap-1 ml-auto">
                      <template v-if="isSuperAdmin">
                        <button @click="userSource = 'all'; lookupUsers(q)" :class="userSource==='all' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">All</button>
                        <button @click="userSource = 'company'; lookupUsers(q)" :class="userSource==='company' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">Company</button>
                      </template>
                    </div>
                  </div>
                  <div class="max-h-40 overflow-auto">
                    <button
                      v-for="(u, index) in userOptions"
                      :key="u.email"
                      @click="pickUserEmail(u.email)"
                      class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"
                      :class="index === selectedIndex ? 'bg-gray-800/50' : ''"
                    >
                      <div class="font-medium">{{ u.name }}</div>
                      <div class="text-xs text-gray-500">{{ u.email }}</div>
                    </button>
                  </div>
                  <div v-if="highlightedUser" class="px-4 py-3 text-xs text-gray-400 border-t border-gray-800/50 bg-gray-800/20">
                    <div class="font-medium text-gray-200 mb-2">Selected User</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                      <div>
                        <div class="text-gray-500">Name:</div>
                        <div class="text-gray-200">{{ highlightedUser.name }}</div>
                      </div>
                      <div>
                        <div class="text-gray-500">Email:</div>
                        <div class="text-gray-200">{{ highlightedUser.email }}</div>
                      </div>
                    </div>
                    <div v-if="userDetails[highlightedUser.id]" class="mt-2">
                      <div class="text-gray-500 font-medium mb-1">Memberships:</div>
                      <div v-for="m in userDetails[highlightedUser.id].memberships" :key="m.id" class="text-gray-300 text-xs py-1.5 border-t border-gray-800/30 flex justify-between items-center">
                        <div>
                          <span class="font-medium">{{ m.name }}</span>
                          <span class="text-gray-500 ml-2">— {{ m.role }}</span>
                        </div>
                        <button @click="quickUnassignUserFromCompany(highlightedUser.email, m.id)" class="text-red-300 hover:text-red-200 transition-colors px-2 py-0.5 rounded border border-red-800/30 hover:border-red-600/50">
                          Unassign
                        </button>
                      </div>
                      <div class="flex gap-2 mt-3">
                        <button @click="quickAssignUserToCompany(highlightedUser.email)" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 transition-all duration-200 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                          </svg>
                          Assign to company…
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="showCompanyPicker" class="border border-gray-700/50 rounded-xl bg-gray-900/50 backdrop-blur-sm overflow-hidden">
                  <div class="text-gray-500 text-xs px-4 py-2.5 mb-1 bg-gray-800/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                    </svg>
                    <span>Companies</span>
                    <div class="flex gap-1 ml-auto">
                      <template v-if="isSuperAdmin">
                        <button @click="companySource = 'all'; preloadCompanies()" :class="companySource==='all' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">All</button>
                        <button @click="companySource = 'me'; preloadCompanies()" :class="companySource==='me' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">Mine</button>
                        <button v-if="params.email" @click="companySource = 'byUser'; preloadCompanies()" :class="companySource==='byUser' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">User</button>
                      </template>
                    </div>
                  </div>
                  <div class="max-h-40 overflow-auto">
                    <button
                      v-for="(c, index) in companyOptions"
                      :key="c.id"
                      @click="pickCompanyName(c.id)"
                      class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"
                      :class="index === selectedIndex ? 'bg-gray-800/50' : ''"
                    >
                      {{ c.name }}
                    </button>
                  </div>
                  <div v-if="highlightedCompany" class="px-4 py-3 text-xs text-gray-400 border-t border-gray-800/50 bg-gray-800/20 space-y-3">
                    <div class="font-medium text-gray-200 mb-2">Selected Company</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <div class="text-gray-500">Name:</div>
                        <div class="text-gray-200">{{ highlightedCompany.name }}</div>
                      </div>
                      <div v-if="companyDetails[highlightedCompany.id]">
                        <div class="text-gray-500">Slug:</div>
                        <div class="text-gray-200">{{ companyDetails[highlightedCompany.id].slug }}</div>
                      </div>
                    </div>
                    <div v-if="companyDetails[highlightedCompany.id]" class="space-y-2 pt-2 border-t border-gray-800/30">
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <div class="text-gray-500">Currency:</div>
                          <div class="text-gray-200">{{ companyDetails[highlightedCompany.id].base_currency }}</div>
                        </div>
                        <div>
                          <div class="text-gray-500">Language:</div>
                          <div class="text-gray-200">{{ companyDetails[highlightedCompany.id].language }}</div>
                        </div>
                      </div>
                      <div>
                        <div class="text-gray-500">Members:</div>
                        <div class="text-gray-200">{{ companyDetails[highlightedCompany.id].members_count }}</div>
                      </div>
                      <div>
                        <div class="text-gray-500">Roles:</div>
                        <div class="text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1">
                          <span>owner: {{ (companyDetails[highlightedCompany.id].role_counts || {}).owner || 0 }}</span>
                          <span>admin: {{ (companyDetails[highlightedCompany.id].role_counts || {}).admin || 0 }}</span>
                          <span>accountant: {{ (companyDetails[highlightedCompany.id].role_counts || {}).accountant || 0 }}</span>
                          <span>viewer: {{ (companyDetails[highlightedCompany.id].role_counts || {}).viewer || 0 }}</span>
                        </div>
                      </div>
                      <div v-if="companyDetails[highlightedCompany.id].owners && companyDetails[highlightedCompany.id].owners.length" class="text-gray-500">
                        Owners:
                        <span class="text-gray-300">{{ companyDetails[highlightedCompany.id].owners.map((o: any) => o.name).join(', ') }}</span>
                      </div>
                      <div v-if="companyDetails[highlightedCompany.id].last_activity" class="text-gray-500">
                        Last activity:
                        <span class="text-gray-300">{{ companyDetails[highlightedCompany.id].last_activity.action }}</span>
                        <span class="text-gray-400 block text-xs mt-0.5">@ {{ companyDetails[highlightedCompany.id].last_activity.created_at }}</span>
                      </div>
                      <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/30">
                        <button @click="loadCompanyMembers(highlightedCompany.id)" class="px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                          </svg>
                          View members
                        </button>
                        <button @click="quickAssignToCompany(highlightedCompany.id)" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                          </svg>
                          Assign user
                        </button>
                        <button @click="setActiveCompany(highlightedCompany.id)" class="px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                          </svg>
                          Set active
                        </button>
                      </div>
                      <div v-if="companyMembersLoading[highlightedCompany.id]" class="text-gray-500 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                        </svg>
                        Loading members…
                      </div>
                      <div v-else-if="companyMembers[highlightedCompany.id] && companyMembers[highlightedCompany.id].length" class="mt-2 max-h-32 overflow-auto border-t border-gray-800/30 pt-2">
                        <div v-for="m in companyMembers[highlightedCompany.id]" :key="m.id" class="py-1.5 text-gray-300 text-xs border-b border-gray-800/30 last:border-b-0">
                          <div class="font-medium">{{ m.name }}</div>
                          <div class="text-gray-500">{{ m.email }} — <span class="text-gray-400">{{ m.role }}</span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Inline suggestions under the field (no separate segment) -->
                <div v-if="inlineSuggestions.length > 0" class="rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden">
                  <div class="max-h-40 overflow-auto">
                    <button v-for="(it, index) in inlineSuggestions" :key="it.type + ':' + it.value"
                      @click="it.type==='currency'?pickCurrency(it.value):it.type==='language'?pickLanguage(it.value):it.type==='locale'?pickLocale(it.value):pickCountry(it.value)"
                      class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"
                      :class="index === selectedIndex ? 'bg-gray-800/50' : ''">
                      <div class="font-medium">{{ it.value }}</div>
                      <div class="text-xs text-gray-500">{{ it.label }}</div>
                    </button>
                  </div>
                </div>

                <!-- Delete confirmation prompt - Enhanced -->
            <div v-if="selectedVerb && selectedVerb.id === 'delete' && params.company && companyDetails[params.company]" class="mt-4 bg-red-900/20 border border-red-700/50 rounded-xl p-4 text-red-200 backdrop-blur-sm">
                  <div class="flex items-center gap-2 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="text-sm font-medium">Confirm Deletion</div>
                  </div>
                  <div class="text-xs mb-3">Type <strong class="text-red-100">{{ companyDetails[params.company].slug || companyDetails[params.company].name }}</strong> to confirm deletion of this company with {{ companyDetails[params.company].members_count }} members.</div>
                  <input v-model="deleteConfirmText" class="w-full bg-red-900/30 border border-red-700/50 rounded-lg p-2.5 text-red-100 placeholder-red-300/70 focus:outline-none" :placeholder="companyDetails[params.company].slug || companyDetails[params.company].name" />
                  <div class="mt-3">
                    <button
                      :disabled="deleteConfirmText !== (companyDetails[params.company].slug || companyDetails[params.company].name)"
                      @click="execute"
                      class="px-4 py-2 rounded-lg border transition-all duration-300 flex items-center gap-1.5 text-xs"
                      :class="deleteConfirmText === (companyDetails[params.company].slug || companyDetails[params.company].name) ? 'border-red-500 bg-red-700/50 text-white hover:bg-red-600/50' : 'border-red-800/50 bg-red-950/30 text-red-400 cursor-not-allowed'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                      </svg>
                      Delete company
                    </button>
                  </div>
                </div>

                <!-- No active flag state - Enhanced -->
                <div v-if="!activeFlagId && !showUserPicker && !showCompanyPicker && currentChoices.length === 0" class="text-center py-10">
                  <div class="text-gray-500 text-sm mb-4 flex flex-col items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-2 opacity-50" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Click a parameter above to start entering values
                  </div>
                  <div class="text-gray-600 text-xs">
                    Required parameters: {{ selectedVerb?.fields.filter(f => f.required).length || 0 }}/{{ selectedVerb?.fields.length || 0 }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Terminal Footer - Enhanced -->
            <div class="px-4 py-3 border-t border-gray-700/30 bg-gradient-to-r from-gray-800 to-gray-900">
              <div class="flex justify-between items-center text-xs text-gray-500">
                <div class="flex gap-3 sm:gap-4 flex-wrap">
                  <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50">↑↓</kbd> navigate</span>
                  <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50">↵</kbd> select</span>
                  <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50">⇥</kbd> complete</span>
                  <span class="hidden sm:flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50">⇧⇥</kbd> edit last</span>
                  <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50">⎋</kbd> back</span>
                </div>
                <div class="text-green-400 flex items-center gap-1.5">
                  <span class="h-2 w-2 rounded-full bg-green-400"></span>
                  {{ executing ? 'EXECUTING...' : 'READY' }}
                </div>
              </div>
            </div>
          </div>
        </DialogPanel>

        <!-- Results Panel - Enhanced -->
        <div
          v-if="showResults && results.length > 0"
          class="w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden"
          :class="showResults ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-4'"
        >
          <div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900">
            <div class="flex items-center gap-2">
              <span class="text-green-400">●</span>
              <span class="text-gray-400 text-xs tracking-wide">EXECUTION LOG</span>
              <button @click="showResults = false" class="ml-auto text-gray-500 hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
          <div class="p-4 space-y-3 max-h-96 overflow-auto">
            <div
              v-for="(result, index) in results"
              :key="index"
              class="bg-gray-800/30 p-3 rounded-xl border backdrop-blur-sm"
              :class="result.success ? 'border-green-700/30' : 'border-red-700/30'"
            >
              <div class="flex items-center gap-2 mb-2">
                <span :class="result.success ? 'text-green-400' : 'text-red-400'" class="flex items-center gap-1.5 text-xs">
                  <svg v-if="result.success" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  {{ result.action }}
                </span>
              </div>
              <div :class="result.success ? 'text-green-200' : 'text-red-200'" class="text-xs mb-2">{{ result.message }}</div>
              <div class="text-gray-500 text-xs">
                {{ new Date(result.timestamp).toLocaleTimeString() }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
/* Removed old arrow-shaped breadcrumb styles */
/* Starship-style curved breadcrumb ribbon */
.starship-bc {
  display: flex;
  align-items: center;
  gap: 0;
}
/* Borderless breadcrumb segments */
.starship-bc {
  display: flex;
  align-items: center;
  gap: 0;
}
.seg {
  position: relative;
  display: inline-flex;
  align-items: center;
  height: 30px;
  padding: 0 14px;
  background: var(--bg, #1f2937);
  color: var(--fg, #e5e7eb);
  border-radius: 9999px;
  font-weight: 500;
}
.seg + .seg { margin-left: -10px; }
.seg-first { z-index: 3; }
.seg-mid   { z-index: 2; }
.seg-last  { z-index: 1; }
.seg-mid,
.seg-last,
.seg:not(.seg-first) {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}
.seg-entity { --bg: linear-gradient(90deg, #1e3a8a, #2a56c6); --fg: #93c5fd; }
.seg-verb   { --bg: linear-gradient(90deg, #3f37c9, #6d28d9); --fg: #e9d5ff; }
.seg-active { --bg: linear-gradient(90deg, #7c2d12, #b45309); --fg: #fdba74; }
/* Ensure no blue focus ring/border on the main input */
.no-focus-ring:focus, .no-focus-ring:focus-visible {
  outline: none !important;
  box-shadow: none !important;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-10px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes slideToInput {
  from {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  to {
    opacity: 0.5;
    transform: translateY(56px) scale(0.9);
  }
}

@keyframes slideFromInput {
  from {
    opacity: 0.5;
    transform: translateY(-56px) scale(0.9);
    border-color: rgb(194 120 3 / 0.7); /* orange-600 with opacity */
    background-color: rgb(154 52 18 / 0.2); /* orange-900/20 */
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
    border-color: rgb(21 128 61 / 0.5); /* green-700 with opacity */
    background-color: rgb(20 83 45 / 0.2); /* green-900/20 */
  }
}

@keyframes bounce {
  0%, 20%, 53%, 80%, 100% { transform: translate3d(0,0,0); }
  40%, 43% { transform: translate3d(0,-8px,0); }
  70% { transform: translate3d(0,-4px,0); }
  90% { transform: translate3d(0,-2px,0); }
}

.animate-fadeIn { animation: fadeIn 0.3s ease-out; }
.animate-slideIn { animation: slideIn 0.3s ease-out; }
.animate-slideToInput { animation: slideToInput 0.45s ease-out; }
.animate-slideFromInput { animation: slideFromInput 0.45s ease-out; }
.animate-bounce { animation: bounce 0.6s ease-in-out; }

/* Custom scrollbar for webkit browsers */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.3);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(55, 65, 81, 0.5);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(75, 85, 99, 0.5);
}
</style>
