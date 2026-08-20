<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * Used to invert into a caption under variant="figure", mirroring
 * CardTitle's old inversion -- that contract came from one sample page and
 * didn't survive a census of 284 cards. Now that a stat card's number has
 * its own slot (CardFigure) and its label is CardTitle in every variant,
 * CardDescription has nothing left to invert into: it is always the
 * ordinary supporting sentence, in every variant.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <p data-slot="card-description" :class="cn('text-sm text-text-secondary', props.class)">
    <slot />
  </p>
</template>
