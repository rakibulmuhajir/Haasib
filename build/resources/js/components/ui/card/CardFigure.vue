<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * The conclusion figure of a stat card had no named slot -- three earlier
 * sweeps tried to classify 284 cards as variant="figure" and found zero,
 * because pages split into two dialects (number in CardTitle, or number in
 * a hand-rolled div) and neither matched the contract written from one
 * sample page. Giving the figure its own component removes the guesswork:
 * CardTitle and CardDescription are always label/caption text now, and
 * CardFigure is always the number, in the mono tabular-nums face at the
 * dedicated --text-tally scale.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="card-figure"
    :class="cn('font-mono tabular-nums text-tally text-text-primary', props.class)"
  >
    <slot />
  </div>
</template>
