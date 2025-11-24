<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Field } from '@/components/ui/field'

interface FormFieldProps {
  name: string
  label?: string
  type?: 'text' | 'email' | 'password' | 'textarea' | 'select'
  placeholder?: string
  required?: boolean
  error?: string
  modelValue?: string | number
  options?: Array<{ value: string | number; label: string }>
  class?: string
}

const props = withDefaults(defineProps<FormFieldProps>(), {
  type: 'text',
  required: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const updateValue = (value: string | number) => {
  emit('update:modelValue', value)
}
</script>

<template>
  <Field :name="props.name">
    <div class="space-y-2">
      <label v-if="props.label" :for="props.name" class="text-sm font-medium text-gray-900">
        {{ props.label }}
        <span v-if="props.required" class="text-red-500">*</span>
      </label>

      <!-- Text inputs -->
      <Input
        v-if="['text', 'email', 'password'].includes(props.type)"
        :id="props.name"
        :name="props.name"
        :type="props.type"
        :placeholder="props.placeholder"
        :required="props.required"
        :model-value="props.modelValue"
        :class="[props.class, { 'border-red-500': props.error }]"
        @update:model-value="updateValue"
      />

      <!-- Textarea -->
      <Textarea
        v-else-if="props.type === 'textarea'"
        :id="props.name"
        :name="props.name"
        :placeholder="props.placeholder"
        :required="props.required"
        :model-value="props.modelValue"
        :class="[props.class, { 'border-red-500': props.error }]"
        @update:model-value="updateValue"
      />

      <!-- Select -->
      <Select
        v-else-if="props.type === 'select'"
        :model-value="String(props.modelValue || '')"
        @update:model-value="updateValue"
      >
        <SelectTrigger :class="[props.class, { 'border-red-500': props.error }]">
          <SelectValue :placeholder="props.placeholder" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in props.options"
            :key="option.value"
            :value="String(option.value)"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <!-- Error message -->
      <div v-if="props.error" class="text-sm text-red-500">
        {{ props.error }}
      </div>
    </div>
  </Field>
</template>