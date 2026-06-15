<script setup lang="ts">
import { computed } from 'vue'
import { dateTimeTitle, formatDateTime, parseDateTime, type DateTimeMode } from '@/lib/datetime'

type Props = {
  value?: string | Date | null
  mode?: DateTimeMode
  locale?: string
  fallback?: string
}

const props = withDefaults(defineProps<Props>(), {
  mode: 'datetime',
  locale: 'en-US',
  fallback: '-',
})

const label = computed(() =>
  formatDateTime(props.value, {
    mode: props.mode,
    locale: props.locale,
    fallback: props.fallback,
  })
)

const machineValue = computed(() => {
  const date = parseDateTime(props.value, props.mode)
  return date?.toISOString()
})

const title = computed(() => dateTimeTitle(props.value, props.mode, props.fallback))
</script>

<template>
  <time
    v-if="machineValue"
    :datetime="machineValue"
    :title="title"
  >
    {{ label }}
  </time>
  <span v-else>{{ label }}</span>
</template>
