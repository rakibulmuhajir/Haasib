<script setup lang="ts">
import { ref } from 'vue'
import Panel from 'primevue/panel'

const props = defineProps<{
  defaultOpen?: boolean
}>()

// PrimeVue Panel uses `collapsed` state. Initialize from `defaultOpen` once.
const collapsed = ref(!(props.defaultOpen ?? false))
</script>

<template>
  <Panel toggleable v-model:collapsed="collapsed" class="border-none shadow-none p-0">
    <template #header>
      <slot name="trigger" />
    </template>
    <div>
      <slot />
    </div>
  </Panel>
</template>
