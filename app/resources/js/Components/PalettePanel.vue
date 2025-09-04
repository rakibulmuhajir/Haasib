<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import SuggestList from '@/Components/SuggestList.vue'

const props = defineProps<{
  palette: any,
  handleKeydown: (e: KeyboardEvent) => void,
  isExpanded: boolean,
}>()

const { palette, handleKeydown, isExpanded } = props
const {
  open, q, step, selectedEntity, selectedVerb, params, inputEl, selectedIndex, executing, showResults,
  activeFlagId, isSuperAdmin, userSource, companySource, panelItems, companyDetails, companyMembers, companyMembersLoading,
  userDetails, deleteConfirmText, deleteConfirmRequired, entitySuggestions, verbSuggestions, availableFlags, filledFlags,
  currentField, dashParameterMatch, allRequiredFilled, currentChoices, isUIList, showUserPicker, showCompanyPicker,
  showGenericPanelPicker, inlineSuggestions, uiListActionMode, uiListActionIndex, uiListActionCount, highlightedItem, statusText,
  selectFlag, editFilledFlag, completeCurrentFlag, handleDashParameter, loadCompanyMembers, ensureCompanyDetails,
  quickAssignToCompany, setActiveCompany, quickAssignUserToCompany, quickUnassignUserFromCompany, resetAll, goHome, goBack,
  selectEntity, selectVerb, selectChoice, execute, startVerb, pickUserEmail, pickCompanyName, pickGeneric, performUIListAction,
} = palette

const mainPanelEl = ref<HTMLDivElement | null>(null)
const deleteConfirmInputEl = ref<HTMLInputElement | null>(null)

watch([selectedVerb, () => params.value.company], async ([verb, company]) => {
  if (verb && (verb as any).id === 'delete' && company) {
    await ensureCompanyDetails(company as any)
    nextTick(() => deleteConfirmInputEl.value?.focus())
  }
})

watch([() => allRequiredFilled.value, () => activeFlagId.value, () => open.value], ([reqFilled, activeId, isOpen]) => {
  if (isOpen && step.value === 'fields' && reqFilled && !activeId) {
    nextTick(() => {
      mainPanelEl.value?.focus()
    })
  }
})

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
}

function highlight(text: string, needle: string): string {
  const n = (needle || '').trim()
  if (!n) return escapeHtml(text)
  const lower = text.toLowerCase()
  const idx = lower.indexOf(n.toLowerCase())
  if (idx === -1) return escapeHtml(text)
  const before = escapeHtml(text.slice(0, idx))
  const match = escapeHtml(text.slice(idx, idx + n.length))
  const after = escapeHtml(text.slice(idx + n.length))
  return `${before}<span class=\"underline decoration-dotted\">${match}</span>${after}`
}
</script>
<template>
  <!-- Main Command Palette - Enhanced design -->
  <div class="flex-1 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-t-xl shadow-2xl font-mono text-sm overflow-hidden" :class="isExpanded ? 'scale-100 opacity-100' : 'scale-105 opacity-0'" @keydown="(e) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); goBack() } }">
    <div class="flex flex-col h-full" @keydown="handleKeydown" ref="mainPanelEl" tabindex="-1">

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
          <button type="button" class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" @click="goBack">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Back
          </button>
          <button type="button" class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" @click="goHome">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
            </svg>
            Home
          </button>
          <div class="text-gray-500 text-xs px-2 py-1 bg-gray-800/50 rounded-md border border-gray-700/50">{{ statusText }}</div>
        </div>
      </div>
    </div>

    <!-- Command Line - Enhanced -->
    <div class="px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/50 to-gray-900/10">
      <div class="flex items-center gap-2">
        <span class="text-green-400 text-lg">❯</span>
        <div class="flex-1">
          <Transition name="fade-scale" mode="out-in">
            <!-- Ready to Execute State -->
            <div v-if="step === 'fields' && allRequiredFilled && !activeFlagId" key="ready" class="w-full flex items-center justify-between bg-gray-800/50 rounded-lg px-3 py-2 text-xs">
              <template v-if="!executing">
                <div class="flex items-center gap-2 text-gray-400 w-full">
                  <span class="mr-auto">Press ↵ to</span>
                  <button @click="execute" class="px-3 py-1 bg-green-700/50 text-green-100 rounded-md border border-green-600/50 flex items-center gap-1 backdrop-blur-sm hover:bg-green-600/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                    Execute
                  </button>
                  <span>or ⎋ to</span>
                  <button @click="goBack" class="px-3 py-1 bg-red-800/50 text-red-200 rounded-md border border-red-700/50 flex items-center gap-1 backdrop-blur-sm hover:bg-red-700/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    Cancel
                  </button>
                </div>
              </template>
              <template v-else>
                <div class="w-full flex items-center justify-center gap-2 text-green-300">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
                  <span>Executing...</span>
                </div>
              </template>
            </div>

            <!-- Input State -->
            <div v-else key="input" class="flex-1 flex items-center gap-2 relative">
              <!-- Breadcrumb -->
              <div class="flex items-center gap-2 text-gray-400 whitespace-nowrap">
                <template v-if="selectedEntity">
                  <span class="text-blue-300">{{ selectedEntity.label }}</span>
                  <template v-if="selectedVerb">
                    <span class="text-gray-500">&gt;</span>
                    <span class="text-purple-300">{{ selectedVerb.label }}</span>
                  </template>
                  <template v-if="step === 'fields' && currentField">
                    <span class="text-gray-500">&gt;</span>
                    <span class="text-orange-300">{{ currentField.placeholder }}<span v-if="currentField.required" class="ml-0.5 text-red-300">*</span></span>
                  </template>
                </template>
              </div>

              <input
                ref="inputEl"
                v-model="q"
                :placeholder="
                  step === 'entity'
                    ? 'Search entities...'
                    : step === 'verb'
                      ? 'Search actions...'
                      : (!activeFlagId
                          ? (isUIList ? 'Type name, email, slug to search…' : 'Select parameter or type -param...')
                          : 'Enter value...')
                "
                class="w-full bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus-visible:ring-0 appearance-none py-2 no-focus-ring border-0"
                :class="[
                  step === 'fields' && currentField ? 'text-orange-300 placeholder-orange-300/50' : '',
                  step === 'fields' && !currentField && !dashParameterMatch ? 'text-gray-200 placeholder-gray-500' : '',
                  dashParameterMatch ? 'text-yellow-300' : (step !== 'fields' ? 'text-green-400 placeholder-gray-600' : '')
                ]"
                :style="{}"
                :disabled="executing"
              />

              <button type="button" v-if="step === 'fields' && activeFlagId && q.trim()" @click="completeCurrentFlag" class="px-3 py-1.5 bg-orange-700/50 text-orange-100 rounded-lg border border-orange-600/50 text-xs flex items-center gap-1 backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Set
              </button>

              <button type="button" v-if="step === 'fields' && !activeFlagId && dashParameterMatch" @click="handleDashParameter" class="px-3 py-1.5 bg-yellow-700/50 text-yellow-100 rounded-lg border border-yellow-600/50 text-xs flex items-center gap-1 backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                Select
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- Options/Suggestions - Enhanced -->
    <div class="flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5">

      <!-- Entity suggestions - Enhanced -->
      <div v-if="step === 'entity'" class="space-y-2" key="entity-list">
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
      <div v-else-if="step === 'verb'" class="space-y-2" key="verb-list">
        <div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block">Available actions</div>
        <div
          v-for="(verb, index) in verbSuggestions"
          :key="verb.id"
          @click="selectVerb(verb)"
          class="px-4 py-3 rounded-xl cursor-pointer border"
          :class="index === selectedIndex ? 'bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10' : 'hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent'"
        >
          <div class="flex items-center justify-between">
            <span class="text-yellow-400 font-medium" v-html="highlight(verb.label, q)"></span>
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
      <div v-else-if="step === 'fields'" class="space-y-4" key="fields-step">

        <div v-if="activeFlagId">
          <!-- Suggestions when actively editing a field -->
          <!-- Select options (finite lists only) -->
          <div v-if="currentChoices.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
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

          <!-- Unified pickers -->
          <SuggestList v-if="showGenericPanelPicker" :items="panelItems" :selected-index="selectedIndex" @select="(it:any) => pickGeneric(it.value)">
            <template #header><span>Suggestions</span></template>
          </SuggestList>
          <SuggestList v-if="showUserPicker" :items="panelItems" :selected-index="selectedIndex" :show-preview="true" @select="(it:any) => pickUserEmail(it.value)">
             <!-- User picker header and preview templates -->
          </SuggestList>
          <SuggestList v-if="showCompanyPicker" :items="panelItems" :selected-index="selectedIndex" :show-preview="true" @select="(it:any) => pickCompanyName(it.value)">
            <!-- Company picker header and preview templates -->
          </SuggestList>

          <!-- Inline suggestions -->
          <div v-if="inlineSuggestions.length > 0" class="rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden">
            <div class="max-h-40 overflow-auto">
              <button v-for="(it, index) in inlineSuggestions" :key="it.value" @click="selectChoice(it.value)" class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50" :class="index === selectedIndex ? 'bg-gray-800/50' : ''">
                <div class="font-medium">{{ it.value }}</div>
                <div class="text-xs text-gray-500">{{ it.label }}</div>
              </button>
            </div>
          </div>
        </div>

        <!-- Summary / UI list view when not editing a field -->
        <div v-else>
          <!-- For UI list actions, show live results without forcing parameter click -->
          <div v-if="isUIList">
            <!-- Users UI List: hover selects, click is ignored to stay in list mode -->
            <SuggestList
              v-if="showUserPicker"
              :items="panelItems"
              :selected-index="selectedIndex"
              :show-preview="true"
              @highlight="(i:number) => selectedIndex = i"
              @choose="(p:any) => { selectedIndex = p.index; nextTick(() => inputEl?.focus?.()) }"
            >
              <template #header><span>Users</span></template>
              <template #preview="{ item }">
                <div class="space-y-2">
                  <div class="text-gray-300">
                    <div class="font-semibold">{{ item.meta?.name || item.label }}</div>
                    <div class="text-gray-400 text-xs">{{ item.meta?.email || item.value }}</div>
                    <div class="text-gray-500 text-xs" v-if="item.meta?.id">ID: {{ item.meta.id }}</div>
                  </div>
                  <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/60">
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-blue-100 hover:bg-blue-700/60" :class="uiListActionMode && uiListActionIndex===0 ? 'bg-blue-700/70 border-blue-500/70' : 'bg-blue-700/40 border-blue-600/40'" @click="quickAssignUserToCompany(item.meta?.email || item.value)">
                      Assign to company
                    </button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-red-100 hover:bg-red-800/60" :class="uiListActionMode && uiListActionIndex===1 ? 'bg-red-800/70 border-red-600/70' : 'bg-red-800/40 border-red-700/40'" @click="startVerb('user','delete', { email: (item.meta?.email || item.value) })">
                      Delete user
                    </button>
                  </div>
                </div>
              </template>
            </SuggestList>

            <!-- Companies UI List -->
            <SuggestList
              v-if="showCompanyPicker"
              :items="panelItems"
              :selected-index="selectedIndex"
              :show-preview="true"
              @highlight="(i:number) => selectedIndex = i"
              @choose="(p:any) => { selectedIndex = p.index; nextTick(() => inputEl?.focus?.()) }"
            >
              <template #header><span>Companies</span></template>
              <template #preview="{ item }">
                <div class="space-y-2">
                  <div class="text-gray-300">
                    <div class="font-semibold">{{ item.meta?.name || item.label }}</div>
                    <div class="text-gray-400 text-xs" v-if="item.meta?.slug">slug: {{ item.meta.slug }}</div>
                    <div class="text-gray-500 text-xs" v-if="item.meta?.id">ID: {{ item.meta.id }}</div>
                    <div class="text-gray-500 text-xs" v-if="item.meta?.members_count !== undefined">members: {{ item.meta.members_count }}</div>
                  </div>
                  <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/60">
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-blue-100 hover:bg-blue-700/60" :class="uiListActionMode && uiListActionIndex===0 ? 'bg-blue-700/70 border-blue-500/70' : 'bg-blue-700/40 border-blue-600/40'" @click="quickAssignToCompany(item.meta?.id || item.value)">
                      Assign user
                    </button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-amber-100 hover:bg-amber-700/60" :class="uiListActionMode && uiListActionIndex===1 ? 'bg-amber-700/70 border-amber-600/70' : 'bg-amber-700/40 border-amber-600/40'" @click="setActiveCompany(item.meta?.id || item.value)">
                      Switch active
                    </button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-red-100 hover:bg-red-800/60" :class="uiListActionMode && uiListActionIndex===2 ? 'bg-red-800/70 border-red-600/70' : 'bg-red-800/40 border-red-700/40'" @click="startVerb('company','delete', { company: (item.meta?.id || item.value) })">
                      Delete company
                    </button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-gray-200 hover:bg-gray-700/60" :class="uiListActionMode && uiListActionIndex===3 ? 'bg-gray-700/70 border-gray-500/70' : 'bg-gray-700/40 border-gray-600/40'" @click="loadCompanyMembers(item.meta?.id || item.value)">
                      View members
                    </button>
                  </div>
                  <div class="pt-2" v-if="companyMembers[item.meta?.id || item.value] && companyMembers[item.meta?.id || item.value].length > 0">
                    <div class="text-gray-400 text-xs mb-1">Members</div>
                    <div class="max-h-24 overflow-auto rounded-md border border-gray-800/50">
                      <div v-for="m in companyMembers[item.meta?.id || item.value]" :key="m.id + ':' + m.email" class="px-2 py-1 text-xs text-gray-300 border-b border-gray-800/50 last:border-b-0">
                        <span class="text-gray-200">{{ m.name }}</span>
                        <span class="text-gray-500"> — {{ m.email }}</span>
                        <span class="ml-2 text-gray-400">({{ m.role }})</span>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </SuggestList>
          </div>

          <!-- Default parameter list with ability to edit/modify -->
          <div v-else class="space-y-4">
            <div class="flex flex-wrap gap-2">
              <button
                v-for="flag in availableFlags"
                :key="flag.id"
                @click="selectFlag(flag.id)"
                class="px-3 py-1.5 rounded-lg border text-xs flex items-center gap-2"
                :class="activeFlagId === flag.id ? 'bg-orange-800/50 border-orange-700/50 text-orange-100' : 'bg-gray-800/40 border-gray-700/40 text-gray-300 hover:bg-gray-700/40'"
              >
                <span class="text-yellow-400">-{{ flag.id }}</span>
                <span>{{ flag.placeholder }}</span>
                <span v-if="flag.required" class="text-red-400">*</span>
              </button>
            </div>
            <div class="space-y-2">
              <div
                v-for="flag in filledFlags"
                :key="flag.id"
                class="px-3 py-2 bg-gray-800/30 rounded-lg border border-gray-700/40 flex items-center gap-2 text-gray-300 group"
              >
                <span class="text-yellow-400">-{{ flag.id }}</span>
                <span>{{ flag.value }}</span>
                <button type="button" class="opacity-0 group-hover:opacity-100 ml-auto text-xs text-blue-400 hover:underline" @click="editFilledFlag(flag.id)">
                  edit
                </button>
              </div>
            </div>
            <div v-if="deleteConfirmRequired" class="pt-2">
              <div class="text-gray-400 text-xs mb-1">Type <span class="text-red-400 font-semibold">{{ deleteConfirmText }}</span> to confirm</div>
              <input
                ref="deleteConfirmInputEl"
                v-model="params.company_delete_confirm"
                class="w-full bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 appearance-none p-2 text-red-300 placeholder-red-400/50 no-focus-ring border border-red-700/50 rounded-lg"
                placeholder="Enter confirmation"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
