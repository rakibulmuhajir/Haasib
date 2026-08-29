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

/*
 * Typed dates used to go nowhere. Without auto-apply the picker holds a
 * value until someone clicks its Select button, so tabbing between the
 * four flight fields left every one of them unset and every menu still
 * open -- four calendars stacked over the form, and an itinerary the
 * approval check then refused as incomplete.
 *
 * openMenu: false keeps the calendar shut while someone is typing into
 * the field, which is the whole point of a text input. The commits are
 * the three ways a person actually leaves a field: Enter, Tab, or
 * clicking away. The parse format has to be named or it is read as ISO
 * rather than the dd/MM/yyyy the field displays.
 */
const textInput = {
  enterSubmit: true,
  tabSubmit: true,
  applyOnBlur: true,
  openMenu: false,
  format: "dd/MM/yyyy HH:mm",
}
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
    :text-input="textInput"
    auto-apply
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
