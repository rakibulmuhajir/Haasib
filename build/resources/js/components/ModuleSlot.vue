<script setup lang="ts">
import { computed } from 'vue'
import { useModules } from '@/composables/useModules'
import { slotEntries } from '@/extensions/slots'

defineOptions({ inheritAttrs: false })
const props = defineProps<{ name: string }>()
const { has } = useModules()
const entries = computed(() => slotEntries(props.name).filter((e) => has(e.module)))
</script>

<template>
    <component :is="e.component" v-for="e in entries" :key="e.module" v-bind="$attrs" />
</template>
