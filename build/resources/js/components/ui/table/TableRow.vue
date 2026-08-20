<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr :class="cn(
        'border-b border-[var(--rule-default)] transition-colors',
        'hover:outline hover:outline-[1.5px] hover:-outline-offset-[1.5px] hover:outline-[var(--rule-emphasis)]',
        'data-[state=selected]:bg-[var(--surface-sunken)]',
        props.class,
      )">
    <slot />
  </tr>
</template>
