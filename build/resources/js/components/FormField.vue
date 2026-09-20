<script setup lang="ts">
/**
 * A labelled field, wired correctly by default.
 *
 * shadcn's Input and Label are deliberately unopinionated primitives: Input forwards an
 * id but never invents one, and Label has no idea what it is labelling. So a field is only
 * addressable if the page remembers to pass `id` and `for`, and across this codebase most
 * do not — 428 inputs carry no id and 654 labels point at nothing.
 *
 * The cost is accessibility first. A <Label> with no `for` is announced by nothing, and
 * clicking it does not focus the field. For an accounting app where people tab through
 * numeric forms all day, that is a real usability defect, not a lint preference.
 * Testability is the second-order symptom — it is simply what made it visible, when the
 * Daily Close screen turned out to have 35 numeric fields and no way to select any of them
 * except by counting along the page.
 *
 * This generates the id with Vue's useId(), points the label at it, and renders the error
 * underneath. Call sites get shorter, which is the only reason a migration like this ever
 * actually happens:
 *
 *   <FormField label="Closing cash" type="number" v-model="form.closing_cash"
 *              :error="form.errors.closing_cash" />
 *
 * Anything it does not name — step, min, placeholder, autocomplete — falls through to the
 * input via attrs, so it does not become a wrapper you have to fight.
 */
import { computed, useId } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    label: string
    modelValue?: string | number | null
    error?: string
    /** Quiet guidance under the field. Not a substitute for the label. */
    hint?: string
    /** Marks the field required for both assistive tech and the eye. */
    required?: boolean
    /** Override only when a field must keep an id something else already points at. */
    id?: string
  }>(),
  { required: false },
)

const emit = defineEmits<{ (e: 'update:modelValue', value: string | number): void }>()

const generatedId = useId()
const fieldId = computed(() => props.id ?? `field-${generatedId}`)
const describedBy = computed(() => {
  const ids: string[] = []
  if (props.hint) ids.push(`${fieldId.value}-hint`)
  if (props.error) ids.push(`${fieldId.value}-error`)
  return ids.length ? ids.join(' ') : undefined
})

const value = computed({
  get: () => props.modelValue ?? '',
  set: (v) => emit('update:modelValue', v),
})
</script>

<template>
  <div class="space-y-1">
    <Label :for="fieldId">
      {{ label }}
      <span v-if="required" class="text-status-critical" aria-hidden="true">*</span>
    </Label>

    <Input
      :id="fieldId"
      v-model="value"
      v-bind="$attrs"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
    />

    <p v-if="hint" :id="`${fieldId}-hint`" class="text-text-metadata">{{ hint }}</p>
    <InputError v-if="error" :id="`${fieldId}-error`" :message="error" />
  </div>
</template>
