<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tfoot :class="cn(
        'border-y-[1.5px] border-[var(--rule-emphasis)] bg-transparent font-medium',
        '[&_td]:font-[family-name:var(--mono-family)] [&>tr]:last:border-b-0 [&_tr]:bg-transparent',
        props.class,
      )">
    <slot />
  </tfoot>
</template>
