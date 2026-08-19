<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div class="relative w-full overflow-auto">
    <table :class="cn(
        'w-full caption-bottom border-collapse text-sm tabular-nums',
        '[&_td.text-right]:font-[family-name:var(--mono-family)] [&_th.text-right]:font-[family-name:var(--mono-family)]',
        props.class,
      )">
      <slot />
    </table>
  </div>
</template>
