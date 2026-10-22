<script setup lang="ts">
/** What just happened, with the two things you can do about it. */
import { Button } from '@/components/ui/button'

defineProps<{ message: string; canOpen: boolean; canUndo: boolean; undone: boolean }>()
const emit = defineEmits<{ open: []; undo: [] }>()
</script>

<template>
  <div class="flex items-center gap-3 border-t border-rule-subtle px-4 py-3 text-sm text-text-primary" data-testid="palette-result">
    <span aria-hidden="true" class="font-semibold text-status-success">✓</span>
    <span class="min-w-0 flex-1 truncate">{{ undone ? 'Undone' : message }}</span>
    <Button v-if="canOpen" variant="link" size="sm" class="h-auto p-0" @click="emit('open')">Open</Button>
    <Button v-if="canUndo && !undone" variant="link" size="sm" class="h-auto p-0" @click="emit('undo')">Undo</Button>
  </div>
</template>
