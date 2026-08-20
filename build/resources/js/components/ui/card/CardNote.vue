<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * The small supporting sentence under a stat card's figure (e.g. "+4 since
 * last month") is distinct from CardDescription, which is now always the
 * label/caption above the number. Pairs with CardFigure at the --text-note
 * scale, grounded in the accepted mockup's `.item .sub` treatment.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <p
    data-slot="card-note"
    :class="cn('text-note text-text-secondary', props.class)"
  >
    <slot />
  </p>
</template>
