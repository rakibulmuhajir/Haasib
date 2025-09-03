<script setup lang="ts">
import { TooltipRoot, TooltipTrigger, TooltipContent, TooltipArrow } from 'reka-ui'

const props = defineProps({
  text: { type: String, required: true },
  side: { type: String, default: 'right' },
})
</script>

<template>
  <TooltipRoot>
    <TooltipTrigger as-child>
      <span class="inline-flex" tabindex="0">
        <slot />
      </span>
    </TooltipTrigger>
    <TooltipContent :side="side" class="rounded bg-gray-900 px-2 py-1 text-xs text-white shadow">
      {{ text }}
      <TooltipArrow class="fill-gray-900" />
    </TooltipContent>
  </TooltipRoot>
</template>
