<!-- resources/js/Components/CommandPalette.vue -->
<script setup lang="ts">
import { onMounted, onUnmounted, nextTick } from 'vue'
import { Dialog, DialogPanel } from '@headlessui/vue'
import SuggestList from '@/Components/SuggestList.vue'
import { usePalette } from '@/palette/composables/usePalette'
import { usePaletteKeybindings } from '@/palette/composables/usePaletteKeybindings'

const palette = usePalette()
const {
  open, q, step, selectedEntity, selectedVerb, params, inputEl, selectedIndex, executing, results, showResults,
  activeFlagId, animatingToCompleted,
  isSuperAdmin, userSource, companySource,
  panelItems,
  companyDetails, companyMembers, companyMembersLoading, userDetails, deleteConfirmText, deleteConfirmRequired,
  entitySuggestions, verbSuggestions, availableFlags, filledFlags, currentField, dashParameterMatch, allRequiredFilled, currentChoices,
  showUserPicker, showCompanyPicker, showGenericPanelPicker, inlineSuggestions,
  statusText,
  selectFlag, editFilledFlag, completeCurrentFlag, handleDashParameter,
  loadCompanyMembers, quickAssignToCompany, setActiveCompany, quickAssignUserToCompany, quickUnassignUserFromCompany,
  resetAll, goHome, goBack,
  selectEntity, selectVerb, selectChoice, execute,
  pickUserEmail, pickCompanyName, pickGeneric,
} = palette

const { handleKeydown } = usePaletteKeybindings(palette)

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
                      Set
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
                  class="px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 flex items-center gap-1 backdrop-blur-sm animate-slideToInput"
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
                <SuggestList v-if="showGenericPanelPicker"
                  :items="panelItems"
                  :selected-index="selectedIndex"
                  @select="(it:any) => pickGeneric(it.value)"
                >
                  <template #header>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>Suggestions</span>
                  </template>
                  <template #preview="{ item }">
                    <div class="text-gray-300">{{ item.label }}</div>
                  </template>
                </SuggestList>
                <SuggestList v-if="showUserPicker"
                  :items="panelItems"
                  :selected-index="selectedIndex"
                  @select="(it:any) => pickUserEmail(it.value)"
                >
                  <template #header>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                    <span>Users</span>
                    <div class="flex gap-1 ml-auto">
                      <template v-if="isSuperAdmin">
                        <button @click="userSource = 'all'" :class="userSource==='all' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">All</button>
                        <button @click="userSource = 'company'" :class="userSource==='company' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">Company</button>
                      </template>
                    </div>
                  </template>
                  <template #preview="{ item }">
                    <div class="font-medium text-gray-200 mb-2">Selected User</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                      <div>
                        <div class="text-gray-500">Name:</div>
                        <div class="text-gray-200">{{ item.meta?.name }}</div>
                      </div>
                      <div>
                        <div class="text-gray-500">Email:</div>
                        <div class="text-gray-200">{{ item.meta?.email }}</div>
                      </div>
                    </div>
                    <div v-if="userDetails[item.meta?.id]" class="mt-2">
                      <div class="text-gray-500 font-medium mb-1">Memberships:</div>
                      <div v-for="m in userDetails[item.meta?.id].memberships" :key="m.id" class="text-gray-300 text-xs py-1.5 border-t border-gray-800/30 flex justify-between items-center">
                        <div>
                          <span class="font-medium">{{ m.name }}</span>
                          <span class="text-gray-500 ml-2">— {{ m.role }}</span>
                        </div>
                        <button @click="quickUnassignUserFromCompany(item.meta?.email, m.id)" class="text-red-300 hover:text-red-200 transition-colors px-2 py-0.5 rounded border border-red-800/30 hover:border-red-600/50">
                          Unassign
                        </button>
                      </div>
                      <div class="flex gap-2 mt-3">
                        <button @click="quickAssignUserToCompany(item.meta?.email)" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 transition-all duration-200 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                          </svg>
                          Assign to company…
                        </button>
                      </div>
                    </div>
                  </template>
                </SuggestList>

                <!-- New SuggestList-based Companies picker -->
                <SuggestList v-if="showCompanyPicker"
                  :items="panelItems"
                  :selected-index="selectedIndex"
                  @select="(it:any) => pickCompanyName(it.value)"
                >
                  <template #header>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                    </svg>
                    <span>Companies</span>
                    <div class="flex gap-1 ml-auto">
                      <template v-if="isSuperAdmin">
                        <button @click="companySource = 'all'" :class="companySource==='all' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">All</button>
                        <button @click="companySource = 'me'" :class="companySource==='me' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">Mine</button>
                        <button v-if="params.email" @click="companySource = 'byUser'" :class="companySource==='byUser' ? 'bg-gray-700/50 text-gray-200 border-gray-600/50' : 'bg-gray-800/30 text-gray-400 border-gray-700/30'" class="px-2 py-0.5 rounded-lg border text-xs">User</button>
                      </template>
                    </div>
                  </template>
                  <template #preview="{ item }">
                    <div class="font-medium text-gray-200 mb-2">Selected Company</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <div class="text-gray-500">Name:</div>
                        <div class="text-gray-200">{{ item.label }}</div>
                      </div>
                      <div v-if="companyDetails[item.meta?.id]">
                        <div class="text-gray-500">Slug:</div>
                        <div class="text-gray-200">{{ companyDetails[item.meta?.id].slug }}</div>
                      </div>
                    </div>
                    <div v-if="companyDetails[item.meta?.id]" class="space-y-2 pt-2 border-t border-gray-800/30">
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <div class="text-gray-500">Currency:</div>
                          <div class="text-gray-200">{{ companyDetails[item.meta?.id].base_currency }}</div>
                        </div>
                        <div>
                          <div class="text-gray-500">Language:</div>
                          <div class="text-gray-200">{{ companyDetails[item.meta?.id].language }}</div>
                        </div>
                      </div>
                      <div>
                        <div class="text-gray-500">Members:</div>
                        <div class="text-gray-200">{{ companyDetails[item.meta?.id].members_count }}</div>
                      </div>
                      <div>
                        <div class="text-gray-500">Roles:</div>
                        <div class="text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1">
                          <span>owner: {{ (companyDetails[item.meta?.id].role_counts || {}).owner || 0 }}</span>
                          <span>admin: {{ (companyDetails[item.meta?.id].role_counts || {}).admin || 0 }}</span>
                          <span>accountant: {{ (companyDetails[item.meta?.id].role_counts || {}).accountant || 0 }}</span>
                          <span>viewer: {{ (companyDetails[item.meta?.id].role_counts || {}).viewer || 0 }}</span>
                        </div>
                      </div>
                      <div v-if="companyDetails[item.meta?.id].owners && companyDetails[item.meta?.id].owners.length" class="text-gray-500">
                        Owners:
                        <span class="text-gray-300">{{ companyDetails[item.meta?.id].owners.map((o: any) => o.name).join(', ') }}</span>
                      </div>
                      <div v-if="companyDetails[item.meta?.id].last_activity" class="text-gray-500">
                        Last activity:
                        <span class="text-gray-300">{{ companyDetails[item.meta?.id].last_activity.action }}</span>
                        <span class="text-gray-400 block text-xs mt-0.5">@ {{ companyDetails[item.meta?.id].last_activity.created_at }}</span>
                      </div>
                      <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/30">
                        <button @click="loadCompanyMembers(item.meta?.id)" class="px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                          </svg>
                          View members
                        </button>
                        <button @click="quickAssignToCompany(item.meta?.id)" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                          </svg>
                          Assign user
                        </button>
                        <button @click="setActiveCompany(item.meta?.id)" class="px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                          </svg>
                          Set active
                        </button>
                      </div>
                    </div>
                  </template>
                </SuggestList>

                <!-- companies legacy picker removed -->
                <!-- Inline suggestions under the field (no separate segment) -->
                <div v-if="inlineSuggestions.length > 0" class="rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden">
                  <div class="max-h-40 overflow-auto">
                    <button v-for="(it, index) in inlineSuggestions" :key="it.value"
                      @click="selectChoice(it.value)"
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
