<script setup lang="ts">
import Button from 'primevue/button'

const props = defineProps<{
  label?: string
  severity?: 'primary'|'secondary'|'success'|'info'|'warn'|'help'|'danger'
  outlined?: boolean
  text?: boolean
  rounded?: boolean
  size?: 'small'|'large'
  loading?: boolean
  icon?: string
  iconPos?: 'left'|'right'|'top'|'bottom'
  type?: 'button'|'submit'|'reset'
  disabled?: boolean
}>()

defineEmits<{(e: 'click', event: MouseEvent): void}>()
</script>

<template>
  <Button
    :label="!$slots.default ? props.label : undefined"
    :severity="props.severity"
    :outlined="props.outlined"
    :text="props.text"
    :rounded="props.rounded"
    :size="props.size"
    :loading="props.loading"
    :icon="props.icon"
    :iconPos="props.iconPos || 'left'"
    :type="props.type || 'button'"
    :disabled="props.disabled"
  >
    <slot />
  </Button>
  
</template>

