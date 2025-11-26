<script setup lang="ts">
import type { Slot } from 'vue'

export interface FieldProps {
  label?: string
  description?: string
  id?: string
  name: string
}

defineProps<FieldProps>()

defineSlots<{
  default: (props: Record<string, never>) => any
}>()
</script>

<template>
  <div>
    <slot />
  </div>
</template>