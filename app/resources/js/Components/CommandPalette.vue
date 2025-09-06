<script setup lang="ts">
import { onMounted, onUnmounted, nextTick, ref, computed, watch } from 'vue'
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent } from 'reka-ui'
import { usePalette } from '@/palette/composables/usePalette'
import { usePaletteKeybindings } from '@/palette/composables/usePaletteKeybindings'
import PaletteStrip from '@/Components/PaletteStrip.vue'
import PalettePanel from '@/Components/PalettePanel.vue'
import PaletteResults from '@/Components/PaletteResults.vue'

const palette = usePalette()
const { open, q, step, entitySuggestions, verbSuggestions, inlineSuggestions, panelItems, currentChoices, showResults, resetAll, inputEl } = palette
const { handleKeydown } = usePaletteKeybindings(palette)

type DockSize = 'strip' | 'half' | 'full'
const lastSize = ref<Exclude<DockSize, 'strip'>>((localStorage.getItem('palette.lastSize') as any) === 'full' ? 'full' : 'half')
const dockSize = ref<DockSize>('strip')
const isExpanded = computed(() => open.value && dockSize.value !== 'strip')
const autoSizing = ref(false)

// Compact (pro) mode preference
const compact = ref(localStorage.getItem('palette.compact') === '1')
watch(compact, (v) => localStorage.setItem('palette.compact', v ? '1' : '0'))

watch(lastSize, (v) => localStorage.setItem('palette.lastSize', v))

watch(isExpanded, (v) => {
  if (!v) {
    dockSize.value = 'strip'
  } else {
    nextTick(() => palette.inputEl.value?.focus())
  }
})

// Ensure focus whenever palette opens (added redundancy for robustness)
watch(() => palette.open.value, (v) => {
  if (v) nextTick(() => palette.inputEl.value?.focus())
})

// Ensure input focuses whenever a new flag starts (e.g., moving to company selection)
watch(() => palette.activeFlagId.value, (v) => {
  if (v) nextTick(() => palette.inputEl.value?.focus())
})

function openTo(size: Exclude<DockSize, 'strip'>, { reset, auto }: { reset: boolean, auto?: boolean } = { reset: true, auto: false }) {
  lastSize.value = size
  if (reset) { resetAll(); palette.showResults.value = false }
  open.value = true
  dockSize.value = size
  autoSizing.value = !!auto
  nextTick(() => inputEl.value?.focus())
}

function collapseToStrip() {
  open.value = false
  dockSize.value = 'strip'
  autoSizing.value = false
  // Clear palette state when minimizing so we start fresh next time
  try {
    palette.resetAll()
    // Also clear residual results/log so reopening feels fresh
    palette.showResults.value = false
    palette.results.value = []
  } catch {}
}

function toggleShortcutOpen() {
  if (!isExpanded.value) {
    openTo(lastSize.value as Exclude<DockSize, 'strip'>, { reset: true, auto: false })
  } else {
    collapseToStrip()
  }
}

const suggestionCount = computed(() => {
  if (step.value === 'entity') return (entitySuggestions.value || []).length
  if (step.value === 'verb') return (verbSuggestions.value || []).length
  const counts = [
    (inlineSuggestions.value || []).length,
    (panelItems.value || []).length,
    (currentChoices.value || []).length,
  ]
  return Math.max(0, ...counts)
})

// Related verbs for results panel
const relatedVerbs = computed(() => {
  const entity = palette.selectedEntity.value
  if (!entity) return []
  const currentVerbId = palette.selectedVerb.value?.id
  return (entity.verbs || []).filter((v: any) => v.id !== currentVerbId).map((v: any) => ({ id: v.id, label: v.label }))
})

let autoSizeTimer: any = null
watch([suggestionCount, isExpanded, autoSizing, () => dockSize.value], ([cnt, expanded, auto]) => {
  if (!expanded || !auto) return
  clearTimeout(autoSizeTimer)
  autoSizeTimer = setTimeout(() => {
    if (cnt >= 12 && dockSize.value !== 'full') {
      openTo('full', { reset: false, auto: true })
    } else if (cnt <= 6 && dockSize.value === 'full') {
      openTo('half', { reset: false, auto: true })
    }
  }, 80)
})

function handleGlobalKeydown(e: KeyboardEvent) {
  const key = (e.key || '').toLowerCase()
  const isCmdK = e.metaKey && !e.ctrlKey && !e.altKey && !e.shiftKey && key === 'k'
  const isCtrlShiftK = e.ctrlKey && e.shiftKey && !e.altKey && key === 'k'
  const isCtrlSlash = e.ctrlKey && !e.altKey && (key === '/' || e.code.toLowerCase() === 'slash')
  const isCtrlSpace = e.ctrlKey && !e.shiftKey && !e.altKey && (key === ' ' || e.code.toLowerCase() === 'space')
  const isAltK = e.altKey && !e.ctrlKey && !e.metaKey && key === 'k'

  if (isCmdK || isCtrlShiftK || isCtrlSlash || isCtrlSpace || isAltK) {
    e.preventDefault()
    e.stopPropagation()
    toggleShortcutOpen()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown, { passive: false })
  // Refocus palette input after Inertia page changes
  const inertiaFinish = () => { if (open.value) nextTick(() => palette.inputEl.value?.focus()) }
  window.addEventListener('inertia:finish', inertiaFinish)
  ;(window as any).__palette_inertia_finish = inertiaFinish
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  const inertiaFinish = (window as any).__palette_inertia_finish
  if (inertiaFinish) window.removeEventListener('inertia:finish', inertiaFinish)
})
</script>

<template>
  <!-- Minimized bubble; hide it when expanded to avoid focus conflicts -->
  <PaletteStrip
    v-if="!isExpanded"
    :palette="palette"
    :dock-size="dockSize"
    :last-size="lastSize"
    :is-expanded="isExpanded"
    :open-to="openTo"
    :collapse-to-strip="collapseToStrip"
  />
  <DialogRoot :open="isExpanded" @update:open="(v) => { if (!v) collapseToStrip() }" class="relative z-50">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/70 backdrop-blur-sm" />
      <div class="fixed inset-x-0 bottom-0 flex items-end justify-center pb-2 sm:pb-4 px-2">
        <DialogContent class="w-full max-w-6xl flex flex-col lg:flex-row gap-4 relative" :class="[palette.showResults.value ? 'lg:max-w-6xl' : '', dockSize === 'full' ? 'h-[88vh]' : 'h-[56vh]']">
          <!-- Compact toggle -->
          <button
            class="absolute top-2 right-2 px-2 py-1 rounded-md text-xs border border-gray-700/60 text-gray-400 hover:text-gray-200 hover:bg-gray-800/60"
            title="Toggle compact (pro) mode"
            @click="compact = !compact"
          >{{ compact ? 'Pro: On' : 'Pro: Off' }}</button>

          <PalettePanel :palette="palette" :handle-keydown="handleKeydown" :is-expanded="isExpanded" :compact="compact" />
          <PaletteResults
            :results="palette.results.value"
            :show="palette.showResults.value"
            :entity-id="palette.selectedEntity.value?.id || null"
            :entity-label="palette.selectedEntity.value?.label || null"
            :related-verbs="relatedVerbs"
            :compact="compact"
            @close="palette.showResults.value = false"
            @start-verb="(vid) => { const eid = palette.selectedEntity.value?.id; if (eid) palette.startVerb(eid, vid, palette.stashParams.value || {}) }"
          />
        </DialogContent>
      </div>
    </DialogPortal>
  </DialogRoot>
</template>

<style>
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
    border-color: rgb(194 120 3 / 0.7);
    background-color: rgb(154 52 18 / 0.2);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
    border-color: rgb(21 128 61 / 0.5);
    background-color: rgb(20 83 45 / 0.2);
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
