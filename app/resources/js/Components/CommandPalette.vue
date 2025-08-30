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
const selectedIndex = ref(0)
const executing = ref(false)
const results = ref<any[]>([])
const showResults = ref(false)
const page = usePage<any>()
const isSuperAdmin = computed(() => !!page.props?.auth?.isSuperAdmin)
const currentCompanyId = computed(() => page.props?.auth?.companyId || null)
const userSource = ref<'all'|'company'>(isSuperAdmin.value ? 'all' : 'company')
const companySource = ref<'all'|'me'|'byUser'>(isSuperAdmin.value ? 'all' : 'me')
const browseUsers = ref(false)
const browseCompanies = ref(false)

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

const currentField = computed<FieldDef|undefined>(() => {
  if (!selectedVerb.value || currentFieldIndex.value >= selectedVerb.value.fields.length) {
    return undefined
  }
  return selectedVerb.value.fields[currentFieldIndex.value]
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
  if (f.id === 'company') return companyOptions.value.map(c => c.name)
  if (f.id === 'email') return userOptions.value.map(u => u.email)
  return []
})

const statusText = computed(() => {
  if (step.value === 'entity') return 'SELECT_ENTITY'
  if (step.value === 'verb') return 'SELECT_ACTION'
  if (step.value === 'fields') return 'INPUT_PARAMS'
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

    // Find common prefix
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
      // Auto-advance to verb selection
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
}

function goHome() {
  step.value = 'entity'
  q.value = ''
  selectedVerb.value = null
  selectedEntity.value = null
  currentFieldIndex.value = 0
  selectedIndex.value = 0
}

function goBack() {
  if (browseUsers.value) { browseUsers.value = false; return }
  if (browseCompanies.value) { browseCompanies.value = false; return }
  if (step.value === 'fields' && selectedVerb.value) {
    // Clear in-progress value first if present
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
  currentFieldIndex.value = 0
  q.value = ''
  selectedIndex.value = 0
  nextTick(() => inputEl.value?.focus())
  // Preload company suggestions for company fields
  if (verb.fields.some(f => f.id === 'company')) {
    preloadCompanies()
  }
  // If a UI list verb, also preload user suggestions
  if (verb.action === 'ui.list.users') {
    // kick off empty query to fetch first page
    lookupUsers('')
    browseUsers.value = true
  }
  if (verb.action === 'ui.list.companies') {
    preloadCompanies()
    browseCompanies.value = true
  }
}

function advanceToNextField() {
  const current = currentField.value
  if (!current) return

  // Save current field value
  const val = q.value.trim()
  if (val || !current.required) {
    if (val) params.value[current.id] = val
    q.value = ''
  } else if (current.required) {
    // Required field is empty, don't advance
    return
  }

  // Find next unfilled required field
  if (selectedVerb.value) {
    for (let i = currentFieldIndex.value + 1; i < selectedVerb.value.fields.length; i++) {
      const field = selectedVerb.value.fields[i]
      if (!params.value[field.id]) {
        currentFieldIndex.value = i
        nextTick(() => inputEl.value?.focus())
        return
      }
    }
  }

  // No more fields, execute if all required are filled
  if (allRequiredFilled.value) {
    execute()
  }
}

function selectChoice(choice: string) {
  if (!currentField.value) return
  params.value[currentField.value.id] = choice
  q.value = ''

  // Auto-advance
  setTimeout(advanceToNextField, 50)
}

async function execute() {
  if (!selectedVerb.value) return

  // UI-only verbs don't execute a request; keep palette open
  if (selectedVerb.value.action.startsWith('ui.')) {
    return
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

    // Add to results log
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

    // Show success briefly, then close
    console.log('Command executed:', response.data)
    setTimeout(() => {
      resetAll()
      open.value = false
    }, 2000)

  } catch (error) {
    console.error('Command failed:', error)

    // Add error to results
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
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (step.value === 'entity') {
      selectedIndex.value = Math.min(selectedIndex.value + 1, entitySuggestions.value.length - 1)
    } else if (step.value === 'verb') {
      selectedIndex.value = Math.min(selectedIndex.value + 1, verbSuggestions.value.length - 1)
    } else if (currentChoices.value.length > 0) {
      selectedIndex.value = Math.min(selectedIndex.value + 1, currentChoices.value.length - 1)
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
      } else {
        advanceToNextField()
      }
    }
  } else if (e.key === 'Tab') {
    e.preventDefault()
    if (step.value === 'entity') {
      // Tab completion for entity names
      const completion = getTabCompletion.value
      q.value = completion
    } else if (step.value === 'fields' && currentChoices.value.length > 0) {
      selectedIndex.value = (selectedIndex.value + 1) % currentChoices.value.length
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
    await ensureCsrf()
    const { data } = await axios.get('/web/companies', { params: query })
    companyOptions.value = (data?.data || []).map((c: any) => ({ id: c.id, name: c.name }))
  } catch (e) {
    // ignore 401 etc.
  }
}

let emailLookupTimer: any = null
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

watch([q, currentField, step], ([qv, cf, st]) => {
  if (st === 'fields' && cf && cf.id === 'email') {
    clearTimeout(emailLookupTimer)
    if (!qv || qv.length < 2) { userOptions.value = []; return }
    emailLookupTimer = setTimeout(() => lookupUsers(qv), 200)
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

function openBrowseUsers() {
  browseUsers.value = true
  if (userOptions.value.length === 0) lookupUsers('')
}

function openBrowseCompanies() {
  browseCompanies.value = true
  if (companyOptions.value.length === 0) preloadCompanies()
}

function pickUserEmail(email: string) {
  if (currentField.value?.id === 'email') {
    params.value['email'] = email
    browseUsers.value = false
    // Advance
    setTimeout(advanceToNextField, 10)
  } else {
    q.value = email
    browseUsers.value = false
  }
}

function pickCompanyName(name: string) {
  if (currentField.value?.id === 'company') {
    params.value['company'] = name
    browseCompanies.value = false
    setTimeout(advanceToNextField, 10)
  } else {
    q.value = name
    browseCompanies.value = false
  }
}
</script>

<template>
  <!-- Floating Terminal Button -->
  <div class="fixed bottom-4 right-4">
    <button
      @click="open=true; resetAll(); nextTick(() => inputEl?.focus())"
      class="px-3 py-2 bg-gray-900 text-green-400 font-mono text-sm rounded border border-gray-700 hover:bg-gray-800 hover:border-green-400 transition-all duration-200 shadow-lg"
    >
      <span class="text-green-300">$</span> command ⌘K
    </button>
  </div>

  <Dialog :open="open" @close="open=false" class="relative z-50">
    <div class="fixed inset-0 bg-black/50" aria-hidden="true" />

    <div class="fixed inset-0 flex items-start justify-center pt-20">
      <div class="w-full max-w-4xl mx-4 flex gap-4">

        <!-- Main Command Palette -->
        <DialogPanel class="flex-1 bg-gray-900 border border-gray-700 rounded-lg shadow-2xl font-mono text-sm" @keydown="(e) => { if ((e as KeyboardEvent).key === 'Escape') { e.preventDefault(); e.stopPropagation(); goBack() } }">
          <div class="flex flex-col h-96" @keydown="handleKeydown">

            <!-- Terminal Header -->
            <div class="px-4 py-2 border-b border-gray-700 bg-gray-800">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="flex gap-1">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                  </div>
                  <span class="text-gray-400 text-xs">accounting-cli v1.0</span>
                </div>
                <div class="flex items-center gap-3">
                  <button class="text-gray-400 hover:text-gray-200 text-xs" @click="goBack">← Back</button>
                  <button class="text-gray-400 hover:text-gray-200 text-xs" @click="goHome">⌂ Home</button>
                  <div class="text-gray-500 text-xs">{{ statusText }}</div>
                </div>
              </div>
            </div>

            <!-- Command Line -->
            <div class="px-4 py-3 border-b border-gray-700">
              <div class="flex items-center gap-2">
                <span class="text-green-400">$</span>

                <!-- Breadcrumb -->
                <div class="flex items-center gap-1 text-gray-300">
                  <template v-if="selectedEntity">
                    <span class="text-blue-400">{{ selectedEntity.label }}</span>
                    <template v-if="selectedVerb">
                      <span class="text-gray-500">.</span>
                      <span class="text-yellow-400">{{ selectedVerb.label }}</span>
                    </template>
                  </template>
                  <template v-if="currentField">
                    <span class="text-gray-500 mx-1">|</span>
                    <span class="text-orange-400">{{ currentField.placeholder }}</span>
                    <span v-if="currentField.required" class="text-red-400">*</span>
                  </template>
                </div>

                <input
                  ref="inputEl"
                  v-model="q"
                  :placeholder="step === 'entity' ? 'entity>' :
                             step === 'verb' ? 'action>' :
                             currentField ? `${currentField.placeholder}>` : 'value>'"
                  class="flex-1 bg-transparent text-green-400 focus:outline-none placeholder-gray-600"
                  :disabled="executing"
                />

                <button
                  v-if="step === 'fields' && allRequiredFilled"
                  @click="execute"
                  :disabled="executing"
                  class="px-3 py-1 bg-green-700 text-white rounded text-xs hover:bg-green-600 disabled:opacity-50 transition-colors"
                >
                  {{ executing ? 'EXEC...' : 'EXECUTE' }}
                </button>
              </div>
            </div>

            <!-- Options/Suggestions -->
            <div class="flex-1 overflow-auto p-2">

              <!-- Entity suggestions -->
              <div v-if="step === 'entity'" class="space-y-1">
                <div class="text-gray-500 text-xs px-2 py-1">Available entities:</div>
                <div
                  v-for="(entity, index) in entitySuggestions"
                  :key="entity.id"
                  @click="selectEntity(entity)"
                  class="px-3 py-2 rounded cursor-pointer transition-colors"
                  :class="index === selectedIndex ? 'bg-blue-900 text-blue-200 border border-blue-700' : 'hover:bg-gray-800 text-gray-300'"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-green-400">{{ entity.label }}</span>
                    <span class="text-gray-500 text-xs">{{ entity.verbs.length }} actions</span>
                  </div>
                  <div class="text-xs text-gray-500">
                    aliases: {{ entity.aliases.join(', ') }}
                  </div>
                </div>
              </div>

              <!-- Verb suggestions -->
              <div v-else-if="step === 'verb'" class="space-y-1">
                <div class="text-gray-500 text-xs px-2 py-1">Available actions:</div>
                <div
                  v-for="(verb, index) in verbSuggestions"
                  :key="verb.id"
                  @click="selectVerb(verb)"
                  class="px-3 py-2 rounded cursor-pointer transition-colors"
                  :class="index === selectedIndex ? 'bg-yellow-900 text-yellow-200 border border-yellow-700' : 'hover:bg-gray-800 text-gray-300'"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-yellow-400">{{ verb.label }}</span>
                    <span class="text-gray-500 text-xs">
                      {{ verb.fields.filter(f => f.required).length }}/{{ verb.fields.length }} required
                    </span>
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ verb.fields.map(f => f.placeholder).join(' ') }}
                  </div>
                </div>
              </div>

              <!-- Field input -->
              <div v-else-if="step === 'fields'" class="space-y-3">

                <!-- Current field info -->
                <div v-if="currentField" class="bg-gray-800 p-2 rounded border border-gray-700">
                  <div class="text-orange-400 text-xs">CURRENT FIELD:</div>
                  <div class="text-gray-300">{{ currentField.label }}</div>
                  <div class="text-gray-500 text-xs">{{ currentField.placeholder }}</div>
                </div>

                <!-- Select options -->
                <div v-if="currentChoices.length > 0">
                  <div class="text-gray-500 text-xs px-2 py-1 mb-2 flex items-center gap-2">
                    <span>Select option:</span>
                    <template v-if="currentField?.id === 'email' && isSuperAdmin">
                      <button @click="userSource = 'all'; lookupUsers(q)" :class="userSource==='all' ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-400'" class="px-2 py-0.5 rounded border border-gray-600">All users</button>
                      <button @click="userSource = 'company'; lookupUsers(q)" :class="userSource==='company' ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-400'" class="px-2 py-0.5 rounded border border-gray-600">Company members</button>
                    </template>
                    <template v-if="currentField?.id === 'company' && isSuperAdmin">
                      <button @click="companySource = 'all'; preloadCompanies()" :class="companySource==='all' ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-400'" class="px-2 py-0.5 rounded border border-gray-600">All companies</button>
                      <button @click="companySource = 'me'; preloadCompanies()" :class="companySource==='me' ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-400'" class="px-2 py-0.5 rounded border border-gray-600">My companies</button>
                      <button v-if="params.email" @click="companySource = 'byUser'; preloadCompanies()" :class="companySource==='byUser' ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-400'" class="px-2 py-0.5 rounded border border-gray-600">User's companies</button>
                    </template>
                  </div>
                  <div class="grid grid-cols-2 gap-1">
                    <button
                      v-for="(choice, index) in currentChoices"
                      :key="choice"
                      @click="selectChoice(choice)"
                      class="px-3 py-2 text-left rounded transition-colors"
                      :class="index === selectedIndex ? 'bg-orange-900 text-orange-200 border border-orange-700' : 'bg-gray-800 hover:bg-gray-700 text-gray-300'"
                    >
                      {{ choice }}
                    </button>
                  </div>
                </div>

                <!-- Browse pickers for users/companies when needed or summoned -->
                <div v-if="browseUsers || (currentField?.id === 'email' && userOptions.length > 0)" class="border border-gray-700 rounded">
                  <div class="text-gray-500 text-xs px-2 py-1 mb-1 flex items-center justify-between">
                    <span>User suggestions</span>
                    <button class="text-gray-400 hover:text-gray-200" @click="browseUsers=false">close</button>
                  </div>
                  <div class="max-h-40 overflow-auto">
                    <button
                      v-for="u in userOptions"
                      :key="u.email"
                      @click="pickUserEmail(u.email)"
                      class="w-full text-left px-3 py-2 hover:bg-gray-800 text-gray-300 border-t border-gray-800"
                    >
                      {{ u.name }} <span class="text-gray-500">‹{{ u.email }}›</span>
                    </button>
                  </div>
                </div>

                <div v-if="browseCompanies || (currentField?.id === 'company' && companyOptions.length > 0)" class="border border-gray-700 rounded">
                  <div class="text-gray-500 text-xs px-2 py-1 mb-1 flex items-center justify-between">
                    <span>Company suggestions</span>
                    <button class="text-gray-400 hover:text-gray-2 00" @click="browseCompanies=false">close</button>
                  </div>
                  <div class="max-h-40 overflow-auto">
                    <button
                      v-for="c in companyOptions"
                      :key="c.id"
                      @click="pickCompanyName(c.name)"
                      class="w-full text-left px-3 py-2 hover:bg-gray-800 text-gray-300 border-t border-gray-800"
                    >
                      {{ c.name }}
                    </button>
                  </div>
                </div>

                <!-- Summon browse buttons when appropriate -->
                <div class="flex gap-2" v-if="currentField?.id === 'email' || currentField?.id === 'company'">
                  <button v-if="currentField?.id === 'email'" @click="openBrowseUsers" class="px-2 py-1 text-xs rounded border border-gray-700 bg-gray-800 text-gray-300 hover:bg-gray-700">Browse users</button>
                  <button v-if="currentField?.id === 'company'" @click="openBrowseCompanies" class="px-2 py-1 text-xs rounded border border-gray-700 bg-gray-800 text-gray-300 hover:bg-gray-700">Browse companies</button>
                </div>

                <!-- Progress flags -->
                <div v-if="selectedVerb">
                  <div class="text-gray-500 text-xs px-2 py-1 mb-2">Parameters:</div>
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="(field, index) in selectedVerb.fields"
                      :key="field.id"
                      class="px-2 py-1 text-xs rounded border font-mono transition-colors"
                      :class="params[field.id] ? 'bg-green-900 border-green-700 text-green-200' :
                             index === currentFieldIndex ? 'bg-orange-900 border-orange-700 text-orange-200' :
                             'bg-gray-800 border-gray-600 text-gray-400'"
                    >
                      {{ field.placeholder }}
                      <span v-if="params[field.id]" class="ml-1 text-xs">="{{ params[field.id] }}"</span>
                      <span v-if="field.required" class="ml-1 text-red-400">*</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Terminal Footer -->
            <div class="px-4 py-2 border-t border-gray-700 bg-gray-800">
              <div class="flex justify-between items-center text-xs text-gray-500">
                <div class="flex gap-4">
                  <span><kbd class="px-1 py-0.5 bg-gray-700 rounded">↑↓</kbd> navigate</span>
                  <span><kbd class="px-1 py-0.5 bg-gray-700 rounded">↵</kbd> select</span>
                  <span><kbd class="px-1 py-0.5 bg-gray-700 rounded">⇥</kbd> complete</span>
                  <span><kbd class="px-1 py-0.5 bg-gray-700 rounded">⎋</kbd> back</span>
                </div>
                <div class="text-green-400">
                  {{ executing ? 'EXECUTING...' : 'READY' }}
                </div>
              </div>
            </div>
          </div>
        </DialogPanel>

        <!-- Results Panel -->
        <div
          v-if="showResults && results.length > 0"
          class="w-80 bg-gray-900 border border-gray-700 rounded-lg shadow-2xl font-mono text-sm"
        >
          <div class="px-4 py-2 border-b border-gray-700 bg-gray-800">
            <div class="flex items-center gap-2">
              <span class="text-green-400">●</span>
              <span class="text-gray-400 text-xs">EXECUTION_LOG</span>
            </div>
          </div>
          <div class="p-4 space-y-2 max-h-96 overflow-auto">
            <div
              v-for="(result, index) in results"
              :key="index"
              class="bg-gray-800 p-2 rounded border border-gray-700"
            >
              <div class="flex items-center gap-2 mb-1">
                <span :class="result.success ? 'text-green-400' : 'text-red-400'" class="text-xs">
                  {{ result.success ? '✓' : '✗' }}
                </span>
                <span :class="result.success ? 'text-green-400' : 'text-red-400'" class="text-xs">
                  {{ result.action }}
                </span>
              </div>
              <div class="text-gray-300 text-xs mb-1">{{ result.message }}</div>
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
