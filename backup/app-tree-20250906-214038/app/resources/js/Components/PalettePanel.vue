<script setup lang="ts">
import { ref } from 'vue'
import PaletteHeader from '@/Components/PaletteHeader.vue'
import PaletteCommandInput from '@/Components/PaletteCommandInput.vue'
import PaletteSuggestions from '@/Components/PaletteSuggestions.vue'
import { usePalettePanelWatch } from '@/palette/composables/usePalettePanelWatch'

const props = defineProps<{
  palette: any,
  handleKeydown: (e: KeyboardEvent) => void,
  isExpanded: boolean,
  compact?: boolean,
}>()

const { palette, handleKeydown, isExpanded, compact } = props
const { selectedVerb, params, ensureCompanyDetails, allRequiredFilled, activeFlagId, open, step, goBack } = palette

const mainPanelEl = ref<HTMLDivElement | null>(null)
const deleteConfirmInputEl = ref<HTMLInputElement | null>(null)

usePalettePanelWatch(
  { selectedVerb, params, ensureCompanyDetails, allRequiredFilled, activeFlagId, open, step },
  mainPanelEl,
  deleteConfirmInputEl
)
</script>
<template>
  <!-- Main Command Palette - Enhanced design -->
  <div class="flex-1 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-t-xl shadow-2xl font-mono text-sm overflow-hidden" :class="[isExpanded ? 'scale-100 opacity-100' : 'scale-105 opacity-0', compact ? 'bg-gray-900' : '']" @keydown="(e) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); goBack() } }">
    <div class="flex flex-col h-full" @keydown="handleKeydown" ref="mainPanelEl" tabindex="-1">
      <PaletteHeader v-if="!compact" :go-back="goBack" :go-home="palette.goHome" :status-text="palette.statusText.value" />
      <PaletteCommandInput :palette="palette" />
      <!-- Hints row: help + pro mode + power execute -->
      <div class="px-4 py-1.5 text-[11px] text-gray-400 flex items-center gap-3 select-none">
        <span>Type <span class="text-gray-200">help</span> for examples</span>
        <span>Pro mode: toggle top-right</span>
        <span>Cmd/Ctrl+Enter: execute parsed</span>
      </div>
      <PaletteSuggestions :palette="palette" :delete-confirm-input-el="deleteConfirmInputEl" />
    </div>
  </div>
</template>
