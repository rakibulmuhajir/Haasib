<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { useVModel } from '@vueuse/core'

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes['class']
  /**
   * `v-model.number` and `v-model.trim` are handled here rather than by Vue.
   * Vue applies those modifiers to a native input itself, but on a component it
   * only forwards them as this prop and expects the component to honour them.
   * This one did not — so every `v-model.number` in the app was decoration, the
   * value arrived as a string, and quantities got multiplied by coercion and
   * luck. Declaring the prop is what makes the modifier real.
   */
  modelModifiers?: { number?: boolean; trim?: boolean; lazy?: boolean }
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

const inner = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const modelValue = computed({
  get: () => inner.value,
  set: (value: string | number) => {
    if (typeof value === 'string') {
      if (props.modelModifiers?.trim) value = value.trim()

      if (props.modelModifiers?.number) {
        const parsed = Number.parseFloat(value)
        // An empty or half-typed field must stay what the user typed. Coercing
        // "" to 0 puts a zero in a box someone is still clearing.
        inner.value = Number.isNaN(parsed) ? value : parsed
        return
      }
    }

    inner.value = value
  },
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>
