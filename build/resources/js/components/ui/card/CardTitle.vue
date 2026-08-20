<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { inject } from 'vue'
import { cn } from '@/lib/utils'
import { CARD_VARIANT } from './variant'

/*
 * variant="figure" used to invert CardTitle into the conclusion number
 * itself -- that contract was written from one sample stat-card page, and a
 * census of 284 cards found it matched none of them: the number lives in
 * CardTitle at 42 sites and in a hand-rolled div at 32 others, but never in
 * the shape this component assumed. Now that the figure has its own slot
 * (CardFigure), CardTitle never holds the number -- it's the label, full
 * stop, in every variant. The variant branch survives only because a
 * figure card's label reads as a small mono caption (matching the caption
 * that used to sit where CardDescription is), while every other card's
 * title is the ordinary display heading.
 *
 * `cn()` (tailwind-merge) is used rather than a scoped class so a call site
 * that still passes its own size class (many do, mid-migration) wins over
 * this default instead of colliding with it.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const variant = inject(CARD_VARIANT, 'default')
</script>

<template>
  <h3
    data-slot="card-title"
    :class="
      variant === 'figure'
        ? cn('font-mono font-medium uppercase text-caption text-text-secondary', props.class)
        : cn('font-display font-semibold text-panel text-text-primary', props.class)
    "
  >
    <slot />
  </h3>
</template>
