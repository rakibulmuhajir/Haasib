<script setup lang="ts">
import { nextTick } from 'vue'
import SuggestList from '@/Components/SuggestList.vue'
import { highlight } from '@/utils/highlight'

const props = defineProps<{ palette: any; deleteConfirmInputEl: any }>()
const { palette, deleteConfirmInputEl } = props
</script>

<template>
  <div class="flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5">
    <!-- Entity suggestions -->
    <div v-if="palette.step.value === 'entity'" class="space-y-2" key="entity-list">
      <div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block">Available entities</div>
      <div
        v-for="(entity, index) in palette.entitySuggestions.value"
        :key="entity.id"
        @click="palette.selectEntity(entity)"
        class="px-4 py-3 rounded-xl cursor-pointer border"
        :class="index === palette.selectedIndex.value ? 'bg-blue-900/30 text-blue-200 border-blue-700/50 scale-[1.02] shadow-lg shadow-blue-500/10' : 'hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent'"
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

    <!-- Verb suggestions -->
    <div v-else-if="palette.step.value === 'verb'" class="space-y-2" key="verb-list">
      <div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block">Available actions</div>
      <div
        v-for="(verb, index) in palette.verbSuggestions.value"
        :key="verb.id"
        @click="palette.selectVerb(verb)"
        class="px-4 py-3 rounded-xl cursor-pointer border"
        :class="index === palette.selectedIndex.value ? 'bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10' : 'hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent'"
      >
        <div class="flex items-center justify-between">
          <span class="text-yellow-400 font-medium" v-html="highlight(verb.label, palette.q.value)"></span>
          <span class="text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full">
            {{ verb.fields.filter(f => f.required).length }}/{{ verb.fields.length }} required
          </span>
        </div>
        <div class="text-xs text-gray-500 mt-1">
          {{ verb.fields.map(f => f.placeholder).join(' ') }}
        </div>
      </div>
    </div>

    <!-- Field input -->
    <div v-else-if="palette.step.value === 'fields'" class="space-y-4" key="fields-step">
      <!-- Always-visible summary of parameters (filled + empty) -->
      <div v-if="palette.selectedVerb.value" class="rounded-xl border border-gray-700/50 bg-gray-800/30 p-3">
        <div class="text-gray-400 text-xs mb-2">Summary — press 1..9 to edit</div>
        <div class="space-y-2">
          <div
            v-for="(flag, idx) in palette.selectedVerb.value.fields.filter((f:any) => f.id !== 'password_confirm')"
            :key="flag.id + ':summary'"
            class="px-3 py-2 rounded-lg border flex items-center gap-2 cursor-pointer"
            :class="palette.params.value[flag.id] ? 'border-gray-700/50 bg-gray-900/40 text-gray-200 hover:bg-gray-800/50' : 'border-gray-800/50 bg-gray-900/20 text-gray-400 hover:bg-gray-800/30'"
            role="button"
            tabindex="0"
            @click="palette.params.value[flag.id] ? palette.editFilledFlag(flag.id) : palette.selectFlag(flag.id)"
          >
            <span class="text-yellow-400">-{{ flag.id }}</span>
            <template v-if="flag.id === 'company'">
              <span>
                {{ (palette.companyDetails.value[palette.params.value[flag.id]] && palette.companyDetails.value[palette.params.value[flag.id]].name) || (palette.params.value[flag.id] || '(empty)') }}
              </span>
            </template>
            <template v-else-if="flag.id === 'email'">
              <span>
                {{ palette.params.value[flag.id]
                    ? (palette.params.value[flag.id].includes('@')
                        ? palette.params.value[flag.id]
                        : (palette.userDetails.value[palette.params.value[flag.id]]?.email || palette.params.value[flag.id]))
                    : '(empty)'
                }}
              </span>
            </template>
            <template v-else-if="flag.type === 'password'">
              <span>{{ palette.params.value[flag.id] ? '••••••' : '(empty)' }}</span>
            </template>
            <template v-else>
              <span>{{ palette.params.value[flag.id] || '(empty)' }}</span>
            </template>
            <span class="ml-auto text-[10px] text-gray-300 px-1.5 py-0.5 border border-gray-600/70 rounded">{{ idx+1 }}</span>
          </div>
        </div>
      </div>
      <div v-if="palette.activeFlagId.value">
        <!-- Suggestions when actively editing a field -->
        <!-- Select options (finite lists only) -->
        <div v-if="palette.currentChoices.value.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <button
            v-for="(choice, index) in palette.currentChoices.value"
            :key="choice"
            @click="palette.selectChoice(choice)"
            class="px-4 py-2.5 text-left rounded-xl border"
            :class="index === palette.selectedIndex.value ? 'bg-orange-900/30 text-orange-200 border-orange-700/50 scale-[1.02] shadow-lg shadow-orange-500/10' : 'bg-gray-800/30 hover:bg-gray-700/30 text-gray-300 hover:scale-[1.01] border-transparent'"
          >
            {{ choice }}
          </button>
        </div>

        <!-- Unified pickers -->
        <SuggestList v-if="palette.showGenericPanelPicker.value" :items="palette.panelItems.value" :selected-index="palette.selectedIndex.value" @select="(it:any) => palette.pickGeneric(it.value)">
          <template #header><span>Suggestions</span></template>
        </SuggestList>
        <SuggestList v-if="palette.showUserPicker.value" :items="palette.panelItems.value" :selected-index="palette.selectedIndex.value" :show-preview="true" @select="(it:any) => palette.pickUserEmail(it.value)">
          <!-- User picker header and preview templates -->
        </SuggestList>
        <SuggestList v-if="palette.showCompanyPicker.value" :items="palette.panelItems.value" :selected-index="palette.selectedIndex.value" :show-preview="true" @select="(it:any) => palette.pickCompanyName(it.value)">
          <!-- Company picker header and preview templates -->
        </SuggestList>

        <!-- Inline suggestions -->
        <div v-if="palette.inlineSuggestions.value.length > 0" class="rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden">
          <div class="max-h-40 overflow-auto">
            <button v-for="(it, index) in palette.inlineSuggestions.value" :key="it.value" @click="palette.selectChoice(it.value)" class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50" :class="index === palette.selectedIndex.value ? 'bg-gray-800/50' : ''">
              <div class="font-medium">{{ it.value }}</div>
              <div class="text-xs text-gray-500">{{ it.label }}</div>
            </button>
          </div>
        </div>
      </div>

      <!-- Summary / UI list view when not editing a field -->
      <div v-else>
        <!-- For UI list actions, show live results without forcing parameter click -->
        <div v-if="palette.isUIList.value">
          <!-- Users UI List -->
          <SuggestList
            v-if="palette.showUserPicker.value"
            :items="palette.panelItems.value"
            :selected-index="palette.selectedIndex.value"
            :show-preview="true"
            @highlight="(i:number) => (palette.selectedIndex.value = i)"
            @choose="(p:any) => { palette.selectedIndex.value = p.index; nextTick(() => palette.inputEl.value?.focus?.()) }"
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
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-blue-100 hover:bg-blue-700/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===0 ? 'bg-blue-700/70 border-blue-500/70' : 'bg-blue-700/40 border-blue-600/40'" @click="palette.quickAssignUserToCompany(item.meta?.email || item.value)">
                    Assign to company <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">A</span>
                  </button>
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-red-100 hover:bg-red-800/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===1 ? 'bg-red-800/70 border-red-600/70' : 'bg-red-800/40 border-red-700/40'" @click="palette.startVerb('user','delete', { email: (item.meta?.email || item.value) })">
                    Delete user <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">X</span>
                  </button>
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-amber-100 hover:bg-amber-700/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===2 ? 'bg-amber-700/70 border-amber-600/70' : 'bg-amber-700/40 border-amber-600/40'" @click="palette.startVerb('user','update', { email: (item.meta?.email || item.value) })">
                    Update user <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">E</span>
                  </button>
                </div>
              </div>
            </template>
          </SuggestList>

          <!-- Companies UI List -->
          <SuggestList
            v-if="palette.showCompanyPicker.value"
            :items="palette.panelItems.value"
            :selected-index="palette.selectedIndex.value"
            :show-preview="true"
            @highlight="(i:number) => (palette.selectedIndex.value = i)"
            @choose="(p:any) => { palette.selectedIndex.value = p.index; nextTick(() => palette.inputEl.value?.focus?.()) }"
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
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-blue-100 hover:bg-blue-700/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===0 ? 'bg-blue-700/70 border-blue-500/70' : 'bg-blue-700/40 border-blue-600/40'" @click="palette.quickAssignToCompany(item.meta?.id || item.value)">
                    Assign user <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">A</span>
                  </button>
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-amber-100 hover:bg-amber-700/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===1 ? 'bg-amber-700/70 border-amber-600/70' : 'bg-amber-700/40 border-amber-600/40'" @click="palette.setActiveCompany(item.meta?.id || item.value)">
                    Switch active <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">S</span>
                  </button>
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-red-100 hover:bg-red-800/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===2 ? 'bg-red-800/70 border-red-600/70' : 'bg-red-800/40 border-red-700/40'" @click="palette.startVerb('company','delete', { company: (item.meta?.id || item.value) })">
                    Delete company <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">D</span>
                  </button>
                  <button type="button" class="px-2.5 py-1 text-xs rounded-md border text-gray-200 hover:bg-gray-700/60" :class="palette.uiListActionMode.value && palette.uiListActionIndex.value===3 ? 'bg-gray-700/70 border-gray-500/70' : 'bg-gray-700/40 border-gray-600/40'" @click="palette.loadCompanyMembers(item.meta?.id || item.value)">
                    View members <span class="ml-1 px-1 py-0.5 border border-current/60 rounded text-[10px]">V</span>
                  </button>
                </div>
                <div class="pt-2" v-if="palette.companyMembers.value[item.meta?.id || item.value] && palette.companyMembers.value[item.meta?.id || item.value].length > 0">
                  <div class="text-gray-400 text-xs mb-1">Members</div>
                  <div class="max-h-24 overflow-auto rounded-md border border-gray-800/50">
                    <div v-for="m in palette.companyMembers.value[item.meta?.id || item.value]" :key="m.id + ':' + m.email" class="px-2 py-1 text-xs text-gray-300 border-b border-gray-800/50 last:border-b-0">
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

        <!-- Default parameter list -->
        <div v-else class="space-y-4">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="flag in palette.availableFlags.value"
              :key="flag.id"
              @click="palette.selectFlag(flag.id)"
              class="px-3 py-1.5 rounded-lg border text-xs flex items-center gap-2"
              :class="palette.activeFlagId.value === flag.id ? 'bg-orange-800/50 border-orange-700/50 text-orange-100' : 'bg-gray-800/40 border-gray-700/40 text-gray-300 hover:bg-gray-700/40'"
            >
              <span class="text-yellow-400">-{{ flag.id }}</span>
              <span>{{ flag.placeholder }}</span>
              <span v-if="flag.required" class="text-red-400">*</span>
            </button>
          </div>
          <!-- Duplicate filled flags list removed; summary above covers both filled and empty flags with hotkeys and click-to-edit. -->
          <div v-if="palette.deleteConfirmRequired.value" class="pt-2">
            <div class="text-gray-400 text-xs mb-1">Type <span class="text-red-400 font-semibold">{{ palette.deleteConfirmRequired.value }}</span> to confirm</div>
            <input
              :ref="deleteConfirmInputEl"
              v-model="palette.deleteConfirmText.value"
              class="w-full bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 appearance-none p-2 text-red-300 placeholder-red-400/50 no-focus-ring border border-red-700/50 rounded-lg"
              placeholder="Enter confirmation"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
