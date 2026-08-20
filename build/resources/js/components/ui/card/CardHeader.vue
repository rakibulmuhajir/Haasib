<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * Layout only -- no variant branching needed here. The caption/conclusion
 * inversion for the figure variant lives in CardTitle/CardDescription
 * because they're the ones whose meaning changes; the header's grid
 * (label column + action column) is the same shape for every variant.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="card-header"
    :class="cn('@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6', props.class)"
  >
    <slot />
  </div>
</template>
