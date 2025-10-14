<script setup lang="ts">
import { watch } from 'vue'
import type { Ref } from 'vue'

type DockSize = 'strip' | 'half' | 'full'

const props = defineProps<{
  palette: any,
  dockSize: Ref<DockSize>,
  lastSize: Ref<Exclude<DockSize, 'strip'>>,
  isExpanded: Ref<boolean>,
  openTo: (size: Exclude<DockSize, 'strip'>, opts?: { reset: boolean, auto?: boolean }) => void,
  collapseToStrip: () => void,
}>()

const { palette, dockSize, lastSize, isExpanded, openTo, collapseToStrip } = props

watch(() => palette.showResults.value, (v) => {
  if (v && dockSize.value === 'strip') {
    openTo(lastSize.value, { reset: false, auto: true })
  }
})

watch(() => palette.q.value, (val) => {
  if (dockSize.value === 'strip' && String(val || '').trim().length > 0) {
    openTo(lastSize.value, { reset: false, auto: true })
  }
})
</script>

<template>
  <!-- Bottom-right mini bubble -->
  <div class="fixed bottom-3 right-3 z-40" aria-live="polite">
    <div
      class="max-w-sm w-[320px] rounded-2xl shadow-lg border border-gray-700 bg-gray-900/90 backdrop-blur-md px-3 py-2 font-mono text-xs text-gray-300 flex items-center gap-2"
    >
      <span class="text-green-400">❯</span>
      <input
        v-if="dockSize === 'strip'"
        v-model="palette.q.value"
        @focus="openTo('half', { reset: true, auto: true })"
        @keydown.enter.prevent="openTo('half', { reset: true, auto: true })"
        class="flex-1 bg-transparent outline-none focus:outline-none ring-0 border-0 placeholder-gray-600 text-gray-300"
        placeholder="Type a command…"
      />
      <button
        v-else
        type="button"
        @click="openTo('half', { reset: true, auto: true })"
        class="ml-auto px-2 py-1 rounded-md border border-gray-700/60 text-gray-400 hover:text-gray-200 hover:bg-gray-800/60"
        title="Open command palette (⌘K)"
      >Open</button>
    </div>
  </div>
</template>
