<script setup lang="ts">
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

withDefaults(defineProps<{
  modelValue: string
  min?: string
  max?: string
  placeholder?: string
  required?: boolean
}>(), {
  placeholder: 'Select date and time',
})

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <VueDatePicker
    :model-value="modelValue || null"
    model-type="yyyy-MM-dd'T'HH:mm"
    format="dd/MM/yyyy HH:mm"
    :is-24="true"
    :enable-seconds="false"
    :minutes-increment="5"
    :min-date="min"
    :max-date="max"
    :placeholder="placeholder"
    :clearable="false"
    :required="required"
    text-input
    teleport="body"
    @update:model-value="$emit('update:modelValue', String($event || ''))"
  />
</template>

<style scoped>
:deep(.dp__input) {
  height: 2.25rem;
  border-color: var(--border);
  border-radius: 0.375rem;
  background: transparent;
  color: var(--foreground);
  font-size: 0.875rem;
}

:deep(.dp__input:focus) {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--ring) 50%, transparent);
}
</style>
