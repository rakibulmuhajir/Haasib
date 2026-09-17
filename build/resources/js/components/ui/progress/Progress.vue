<script setup lang="ts">
import { computed } from "vue"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  value?: number
  modelValue?: number
  class?: HTMLAttributes["class"]
}>(), {
  value: 0,
})

// Keep compatibility with both the native shadcn-style `model-value` API and
// the older `value` prop used by a few existing screens.
const progressValue = computed(() => props.modelValue ?? props.value ?? 0)
const width = computed(() => `${Math.min(Math.max(progressValue.value, 0), 100)}%`)
</script>

<template>
  <div
    role="progressbar"
    :aria-valuemin="0"
    :aria-valuemax="100"
    :aria-valuenow="progressValue"
    :class="cn(
      'relative h-1 w-full overflow-hidden rounded-full bg-surface-sunken',
      props.class,
    )"
  >
    <div
      class="h-full w-full flex-1 bg-primary transition-all duration-200"
      :style="{ width }"
    />
  </div>
</template>
