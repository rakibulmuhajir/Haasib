<script setup lang="ts">
/** Key hints on the left, where the entry stands on the right. */
import { cn } from '@/lib/utils'

defineProps<{ enterLabel: string; status: string; critical?: boolean }>()
</script>

<template>
  <div class="flex items-center justify-between gap-4 border-t border-rule-subtle bg-surface-band px-4 py-2 text-xs text-text-secondary">
    <span class="flex items-center gap-3">
      <span><kbd class="kbd">Enter</kbd> {{ enterLabel }}</span>
      <span><kbd class="kbd">Tab</kbd> edit</span>
      <span><kbd class="kbd">Esc</kbd> back</span>
    </span>
    <span :class="cn('truncate', critical && 'text-status-critical')" data-testid="palette-status">{{ status }}</span>
  </div>
</template>

<style scoped>
.kbd {
  font-family: var(--font-mono, ui-monospace, monospace);
  font-size: 11px;
  border: 1px solid var(--rule-default, var(--border));
  border-radius: 4px;
  padding: 0 5px;
  background: var(--surface-raised, var(--card));
}
</style>
