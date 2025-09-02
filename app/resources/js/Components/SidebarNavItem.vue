<script setup>
import { Link } from '@inertiajs/vue3'
import Tooltip from '@/Components/Tooltip.vue'

const props = defineProps<{
  href: string
  active: boolean
  expanded: boolean
  tooltip: string
}>()
</script>

<template>
  <Link :href="props.href" class="group mx-1 my-0.5 flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-gray-100"
        :class="props.active ? 'bg-gray-100 text-gray-900' : 'text-gray-700'">
    <Tooltip v-if="!props.expanded" :text="props.tooltip">
      <slot name="icon" />
    </Tooltip>
    <slot v-else name="icon" />
    <span v-if="props.expanded"><slot /></span>
  </Link>
</template>

