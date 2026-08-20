<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { inject } from 'vue'
import { cn } from '@/lib/utils'
import { CARD_VARIANT } from './variant'

/*
 * variant="register" drops the horizontal padding so a LedgerRegister sits
 * flush to the card's edges instead of floating inside a gutter -- a table
 * with its own ruled columns doesn't want a second margin fighting it. Most
 * call sites currently reach for this by hand-writing class="p-0"; that
 * keeps working unchanged because cn()/tailwind-merge resolves the same way
 * whether the p-0 came from this default or from the page.
 */
const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const variant = inject(CARD_VARIANT, 'default')
</script>

<template>
  <div
    data-slot="card-content"
    :class="cn(variant === 'register' ? 'px-0' : 'px-6', props.class)"
  >
    <slot />
  </div>
</template>
