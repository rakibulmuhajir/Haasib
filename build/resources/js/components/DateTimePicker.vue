<script setup lang="ts">
import { ref } from 'vue'
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

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const picker = ref<{ $el?: HTMLElement } | null>(null)

/*
 * The picker hands back null whenever its own text buffer is empty, and
 * the buffer is empty merely from focusing a filled field and leaving it
 * again -- no typing required. Passed straight through, that wiped a date
 * somebody had already entered, and doing it to four flight fields in turn
 * left only the last one standing.
 *
 * A clear is only real if the field is actually empty on screen. Anything
 * else is the component talking about itself, not about the value.
 */
function onUpdate(value: unknown) {
  const next = String(value || '')

  if (next === '') {
    const shown = picker.value?.$el
      ?.querySelector<HTMLInputElement>('.dp--input')
      ?.value?.trim()

    if (shown) return
  }

  emit('update:modelValue', next)
}

/*
 * One format, used to draw the field and to read it back. They have to be
 * the same string: the field showed a locale default like
 * "11/10/2026, 18:00" while the parser was told dd/MM/yyyy HH:mm, so
 * every re-visit to a filled field failed to parse its own text, handed
 * back null, and wiped the value. The same mismatch also read a typed
 * 01/11/2026 as 11 January rather than 1 November.
 *
 * The display half of that pair is `formats.input`, not `format`, and the
 * clock settings live in `time-config` rather than as is-24 /
 * enable-seconds / minutes-increment props. Those were written against an
 * older major version and this package is v14, so they were silently
 * doing nothing.
 */
const DISPLAY_FORMAT = "dd/MM/yyyy HH:mm"

/*
 * Without auto-apply the picker holds a typed value until someone clicks
 * its Select button, so tabbing between the four flight fields committed
 * none of them and left four calendars stacked over the form. These are
 * the three ways a person actually leaves a field, and openMenu: false
 * keeps the calendar shut while they are typing into it.
 */
const textInput = {
  format: DISPLAY_FORMAT,
}
</script>

<template>
  <VueDatePicker
    ref="picker"
    :model-value="modelValue || null"
    model-type="yyyy-MM-dd'T'HH:mm"
    :formats="{ input: DISPLAY_FORMAT }"
    :time-config="{ is24: true, enableSeconds: false, minutesIncrement: 5 }"
    :min-date="min"
    :max-date="max"
    :placeholder="placeholder"
    :clearable="false"
    :required="required"
    :text-input="textInput"
    auto-apply
    teleport="body"
    @update:model-value="onUpdate"
  />
</template>

<style scoped>
:deep(.dp--input) {
  height: 2.25rem;
  border-color: var(--border);
  border-radius: 0.375rem;
  background: transparent;
  color: var(--foreground);
  font-size: 0.875rem;
}

:deep(.dp--input:focus) {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--ring) 50%, transparent);
}
</style>
