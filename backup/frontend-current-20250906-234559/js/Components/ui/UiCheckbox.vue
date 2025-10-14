<script setup lang="ts">
import { computed } from 'vue'
import Checkbox from 'primevue/checkbox'

const props = defineProps<{
  modelValue: any
  binary?: boolean
  value?: any
  disabled?: boolean
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>()

const checked = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})
</script>

<template>
  <Checkbox v-model="checked" :binary="props.binary" :value="props.value" :disabled="props.disabled" />
</template>

