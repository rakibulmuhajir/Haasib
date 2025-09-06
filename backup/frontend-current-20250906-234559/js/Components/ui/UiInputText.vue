<script setup lang="ts">
import { computed } from 'vue'
import InputText from 'primevue/inputtext'

const props = defineProps<{
  modelValue: string | number | null
  invalid?: boolean
  disabled?: boolean
  placeholder?: string
  type?: string
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>()

const value = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})
</script>

<template>
  <InputText
    v-model="value"
    :invalid="props.invalid"
    :disabled="props.disabled"
    :placeholder="props.placeholder"
    :type="props.type || 'text'"
  />
</template>

