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

watch(() => palette.showResults, (v) => {
  if (v && dockSize.value === 'strip') {
    openTo(lastSize.value, { reset: false, auto: true })
  }
})

watch(() => palette.q, (val) => {
  if (dockSize.value === 'strip' && String(val || '').trim().length > 0) {
    openTo(lastSize.value, { reset: false, auto: true })
  }
})
</script>

<template>
  <!-- Bottom Command Strip -->
  <div class="fixed bottom-0 inset-x-0 z-40">
    <div class="mx-auto w-full max-w-5xl px-3">
      <div class="h-10 bg-gray-900/95 border border-gray-700 border-b-0 rounded-t-xl shadow-inner flex items-center justify-between px-3 font-mono text-xs text-gray-300">
        <div class="flex items-center gap-2 w-full">
          <span class="text-green-400">❯</span>
          <div class="flex-1 min-w-0">
            <!-- Inline input while minimized -->
            <input v-if="dockSize === 'strip'"
                   v-model="palette.q"
                   @keydown.enter="openTo(lastSize as any, { reset: false, auto: true })"
                   class="w-full bg-transparent outline-none focus:outline-none ring-0 border-0 placeholder-gray-600 text-gray-300"
                   placeholder="Type a command… (Enter to expand)" />
            <div v-else class="hidden sm:flex items-center gap-2 text-gray-500">
              <span>command</span><span>— Press</span>
              <kbd class="px-1.5 py-0.5 bg-gray-800/70 rounded border border-gray-700">⌘K</kbd>
              <span>to {{ isExpanded ? 'collapse' : 'expand' }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-1 whitespace-nowrap">
          <button v-if="dockSize !== 'half'" type="button" title="Half screen" @click="openTo('half', { reset: false })" class="px-2 py-1 rounded hover:bg-gray-800/70 border border-transparent" :class="isExpanded && dockSize === 'half' ? 'text-green-400' : 'text-gray-400'">▭</button>
          <button v-if="dockSize !== 'strip'" type="button" title="Collapse" @click="collapseToStrip" class="px-2 py-1 rounded hover:bg-gray-800/70 text-gray-400">—</button>
          <button v-if="dockSize !== 'full'" type="button" title="Fullscreen" @click="openTo('full', { reset: false })" class="px-2 py-1 rounded hover:bg-gray-800/70 border border-transparent" :class="isExpanded && dockSize === 'full' ? 'text-green-400' : 'text-gray-400'">⛶</button>
        </div>
      </div>
    </div>
  </div>
</template>
