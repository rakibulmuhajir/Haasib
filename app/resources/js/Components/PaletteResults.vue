<script setup lang="ts">
const props = defineProps<{
  results: any[],
  show: boolean,
}>()

const emit = defineEmits(['close'])
</script>

<template>
  <div v-if="show" class="w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-t-xl shadow-2xl font-mono text-sm overflow-hidden" :class="show ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-4'">
    <div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900">
      <div class="flex items-center gap-2">
        <span class="text-green-400">●</span>
        <span class="text-gray-400 text-xs tracking-wide">EXECUTION LOG</span>
        <button @click="emit('close')" class="ml-auto text-gray-500 hover:text-gray-300">
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
</template>
