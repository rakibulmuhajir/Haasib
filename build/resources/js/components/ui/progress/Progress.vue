<script setup lang="ts">
import { computed } from "vue"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  value?: number
  class?: HTMLAttributes["class"]
}>(), {
  value: 0,
})

const width = computed(() => `${Math.min(Math.max(props.value ?? 0, 0), 100)}%`)
</script>

<template>
  <div
    role="progressbar"
    :aria-valuemin="0"
    :aria-valuemax="100"
    :aria-valuenow="value"
    :class="cn(
      'relative h-1 w-full overflow-hidden rounded-full bg-slate-800/70',
      props.class,
    )"
  >
    <div
      class="h-full w-full flex-1 bg-cyan-400 transition-all duration-200"
      :style="{ width }"
    />
  </div>
</template>
