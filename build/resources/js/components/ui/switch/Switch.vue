<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  SwitchRoot,
  SwitchThumb,
  useForwardProps,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes["class"]; checked?: boolean | null }>()

const emits = defineEmits<SwitchRootEmits & { "update:checked": [payload: boolean] }>()

const delegatedProps = reactiveOmit(props, "class", "checked", "modelValue")

const forwarded = useForwardProps(delegatedProps)

const resolvedModelValue = computed(() => props.checked ?? props.modelValue)

const handleUpdate = (value: boolean) => {
  emits("update:modelValue", value)
  emits("update:checked", value)
}
</script>

<template>
  <SwitchRoot
    v-bind="forwarded"
    :model-value="resolvedModelValue"
    @update:model-value="handleUpdate"
    :class="cn(
      'peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
      props.class,
    )"
  >
    <SwitchThumb
      :class="cn('pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5')"
    >
      <slot name="thumb" />
    </SwitchThumb>
  </SwitchRoot>
</template>
