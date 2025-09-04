<script setup lang="ts">
const props = defineProps<{ palette: any }>()
const {
  step,
  allRequiredFilled,
  activeFlagId,
  executing,
  selectedEntity,
  selectedVerb,
  currentField,
  dashParameterMatch,
  q,
  inputEl,
  isUIList,
  execute,
  goBack,
  completeCurrentFlag,
  handleDashParameter,
} = props.palette
</script>

<template>
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
              :ref="inputEl"
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
</template>
