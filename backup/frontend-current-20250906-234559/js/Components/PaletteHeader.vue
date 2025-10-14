<script setup lang="ts">
const props = defineProps<{ goBack: () => void; goHome: () => void; statusText: string }>()
const { goBack, goHome, statusText } = props
</script>

<template>
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
</template>
