<script setup lang="ts">
import { CollapsibleRoot, CollapsibleTrigger, CollapsibleContent } from 'reka-ui'
import { Transition } from 'vue'

defineProps<{
  defaultOpen?: boolean
}>()
</script>

<template>
  <CollapsibleRoot :default-open="defaultOpen">
    <CollapsibleTrigger as-child>
      <slot name="trigger" />
    </CollapsibleTrigger>
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <CollapsibleContent><slot /></CollapsibleContent>
    </Transition>
  </CollapsibleRoot>
</template>

