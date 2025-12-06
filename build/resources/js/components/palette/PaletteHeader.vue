<script setup lang="ts">
import type { PaletteCompany } from '@/composables/palette/useCommandPalette'

type Stage = 'entity' | 'verb' | 'chips'

defineProps<{
  activeCompany: PaletteCompany | null
  stage: Stage
}>()
</script>

<template>
  <div class="palette-header">
    <div class="palette-company">
      <span class="palette-dot" :class="{ 'palette-dot--active': activeCompany }">●</span>
      {{ activeCompany?.name || 'Global' }}
    </div>
    <span class="palette-stage-badge" :class="`stage-${stage}`">
      {{ stage === 'entity' ? 'Entity' : stage === 'verb' ? 'Action' : 'Details' }}
    </span>
  </div>
</template>

<style scoped>
.palette-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #1e293b;
}

.palette-company {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
  font-family: ui-monospace, monospace;
}

.palette-dot {
  color: #475569;
}

.palette-dot--active {
  color: #10b981;
}

.palette-stage-badge {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: ui-monospace, monospace;
}

.stage-entity {
  color: #22d3ee;
}

.stage-verb {
  color: #a78bfa;
}

.stage-chips {
  color: #10b981;
}
</style>
