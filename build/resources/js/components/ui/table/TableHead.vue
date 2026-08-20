<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <th :class="cn(
        'h-[var(--row-h,44px)] px-[var(--cell-px,14px)] text-left align-middle whitespace-nowrap',
        'font-[family-name:var(--mono-family)] text-[11px] font-medium uppercase tracking-[0.1em] text-[var(--text-secondary)]',
        'border-b-[1.5px] border-[var(--rule-emphasis)]',
        '[&:has([role=checkbox])]:pr-0',
        props.class,
      )">
    <slot />
  </th>
</template>
