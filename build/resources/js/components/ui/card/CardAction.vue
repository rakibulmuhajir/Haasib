<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * Grid positioning only -- this is the header's right-hand slot (a menu
 * button, an icon action) and its placement doesn't depend on the card
 * variant, so it's untouched by the caption/conclusion inversion that
 * CardTitle and CardDescription now do.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="card-action"
    :class="cn('col-start-2 row-span-2 row-start-1 self-start justify-self-end', props.class)"
  >
    <slot />
  </div>
</template>
