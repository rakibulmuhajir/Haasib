<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/*
 * The declared action slot. Before this, form actions had two dialects in
 * the same app: modules/Accounting/Resources/js/pages/customers/Create.vue:133
 * hand-rolls a footer inside CardContent with `pt-4 border-t
 * border-rule-subtle`, while resources/js/pages/partners/Create.vue:248
 * floats a bare `<div class="flex justify-end gap-4">` outside the card
 * entirely. Same intent -- Save/Cancel at the bottom of a form -- two
 * different shapes, because there was nowhere in the primitive for it to
 * live. CardFooter is that place now, so a third dialect has no reason to
 * appear.
 *
 * The top rule is unconditional (the stock `[.border-t]:pt-6` only applied
 * when a caller remembered to add `border-t` itself, which is exactly the
 * kind of per-page decision this change is trying to remove). Actions are
 * right-aligned by default because that's what every Save/Cancel row in the
 * app already does; `align="between"` is the escape hatch for a footer that
 * also carries a note or a secondary link on the left.
 */
const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class']
    align?: 'end' | 'between'
  }>(),
  {
    align: 'end',
  },
)
</script>

<template>
  <div
    data-slot="card-footer"
    :class="
      cn(
        'flex items-center gap-3 border-t border-rule-subtle px-6 pt-4',
        props.align === 'between' ? 'justify-between' : 'justify-end',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
