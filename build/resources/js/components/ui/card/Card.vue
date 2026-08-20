<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { provide } from 'vue'
import { cn } from '@/lib/utils'
import { CARD_VARIANT, type CardVariant } from './variant'

/*
 * Card is the app's one widget/panel base -- 522 call sites and counting --
 * so every panel shape (a stat tile, a register wrapper, a form, a detail
 * view) has to come out of this one primitive rather than each page growing
 * its own bordered div. `variant` is how a call site declares which shape it
 * wants; it is provided down so CardTitle, CardDescription and CardContent
 * can read it without every page having to repeat the prop on each child.
 *
 * Law 1 is "rules, not elevation": a card sits on the page, it does not float
 * above it. The stock shadcn treatment (`shadow-sm`, `rounded-xl`) reads as
 * elevation and as a much rounder corner than the 2px ledger radius calls
 * for, so both are gone. Shadow stays reserved for things that really are
 * above the page -- dialog, popover, dropdown, sheet, toast.
 */
const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class']
    variant?: CardVariant
  }>(),
  {
    variant: 'default',
  },
)

provide(CARD_VARIANT, props.variant)
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-surface-raised text-text-primary flex flex-col gap-6 rounded-lg border border-rule-default py-6',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
