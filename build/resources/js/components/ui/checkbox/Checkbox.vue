<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot, useForwardProps } from 'reka-ui'
import { reactiveOmit } from '@vueuse/core'
import { computed, type HTMLAttributes } from 'vue'

/**
 * Accepts both the legacy `checked` / `v-model:checked` API and reka-ui's own `v-model`.
 *
 * The app moved from radix-vue to reka-ui, which renamed `checked` to `modelValue` and
 * `update:checked` to `update:modelValue`. Switch was given this same compatibility layer;
 * Checkbox was not, so every call site still written `:checked` + `@update:checked` - some
 * seventy lines across the app - silently stopped working. The box showed unchecked whatever
 * the saved value was, and ticking it never reached the form, which then sent the original
 * value back. On the bank account form that read as "active and default are never saved".
 *
 * The explicit `undefined` defaults matter, as they do in Switch: Vue casts an absent
 * Boolean-typed prop to `false` unless it declares a default, which would make `checked` look
 * passed when it was not and pin the box permanently unchecked.
 *
 * Legacy listeners get a plain boolean, which is what they were written for; `indeterminate`
 * only ever reaches `v-model` users.
 */
const props = withDefaults(
  defineProps<CheckboxRootProps & { class?: HTMLAttributes['class']; checked?: boolean | null }>(),
  { checked: undefined, modelValue: undefined },
)

const emits = defineEmits<CheckboxRootEmits & { 'update:checked': [payload: boolean] }>()

const delegatedProps = reactiveOmit(props, 'class', 'checked', 'modelValue')

const forwarded = useForwardProps(delegatedProps)

const resolvedModelValue = computed(() => props.checked ?? props.modelValue)

const handleUpdate = (value: boolean | 'indeterminate') => {
  emits('update:modelValue', value)
  emits('update:checked', value === true)
}
</script>

<template>
  <CheckboxRoot
    data-slot="checkbox"
    v-bind="forwarded"
    :model-value="resolvedModelValue"
    @update:model-value="handleUpdate"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="flex items-center justify-center text-current transition-none"
    >
      <slot>
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
