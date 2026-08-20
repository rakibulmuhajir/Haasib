<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <td
    :class="
      cn(
        'px-[var(--cell-px,14px)] py-[var(--cell-py,11px)] align-middle text-[var(--text-primary)]',
        '[&:has([role=checkbox])]:pr-0',
        props.class,
      )
    "
  >
    <slot />
  </td>
</template>
