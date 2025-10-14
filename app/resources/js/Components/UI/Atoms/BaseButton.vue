<template>
  <Button
    :class="buttonClasses"
    :label="label"
    :icon="icon"
    :iconPos="iconPos"
    :badge="badge"
    :badgeSeverity="badgeSeverity"
    :loading="loading"
    :loadingIcon="loadingIcon"
    :raised="raised"
    :rounded="rounded"
    :text="text"
    :outlined="outlined"
    :size="size"
    :plain="plain"
    :severity="severity"
    :variant="variant"
    :disabled="disabled || loading"
    :pt="ptOptions"
    :ptOptions="ptOptions"
    v-bind="$attrs"
    @click="onClick"
    @dblclick="onDblclick"
    @keydown="onKeydown"
    @keyup="onKeyup"
  >
    <template v-if="$slots.default" #default>
      <slot />
    </template>
    
    <template v-if="$slots.icon" #icon>
      <slot name="icon" />
    </template>
    
    <template v-if="$slots.loadingicon" #loadingicon>
      <slot name="loadingicon" />
    </template>
    
    <template v-if="$slots.badge" #badge>
      <slot name="badge" />
    </template>
  </Button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'

interface Props {
  label?: string
  icon?: string
  iconPos?: 'left' | 'right' | 'top' | 'bottom'
  badge?: string
  badgeSeverity?: 'secondary' | 'info' | 'success' | 'warning' | 'danger' | 'contrast'
  loading?: boolean
  loadingIcon?: string
  raised?: boolean
  rounded?: boolean
  text?: boolean
  outlined?: boolean
  size?: 'small' | 'large'
  plain?: boolean
  severity?: 'secondary' | 'info' | 'success' | 'warn' | 'danger' | 'contrast' | 'primary'
  variant?: 'primary' | 'secondary' | 'success' | 'info' | 'warn' | 'danger' | 'help' | 'contrast'
  disabled?: boolean
  fullWidth?: boolean
  iconOnly?: boolean
  loadingText?: string
  square?: boolean
}

interface Emits {
  (e: 'click', event: Event): void
  (e: 'dblclick', event: Event): void
  (e: 'keydown', event: Event): void
  (e: 'keyup', event: Event): void
}

const props = withDefaults(defineProps<Props>(), {
  iconPos: 'left',
  loadingIcon: 'pi pi-spinner pi-spin',
  square: false
})

const emit = defineEmits<Emits>()

// Computed classes
const buttonClasses = computed(() => {
  const classes = []
  
  if (props.fullWidth) {
    classes.push('w-full')
  }
  
  if (props.iconOnly && !props.label && !props.$slots.default) {
    classes.push('p-button-icon-only')
    classes.push('p-button-rounded')
  }
  
  if (props.square) {
    classes.push('p-button-square')
  }
  
  return classes.join(' ')
})

// Passthrough options for custom styling
const ptOptions = {
  root: ({ props, state }) => ({
    class: [
      'transition-all duration-200 ease-in-out',
      'focus:outline-none focus:ring-2 focus:ring-offset-2',
      {
        'focus:ring-primary-500': !props.text && !props.outlined && props.severity !== 'secondary',
        'focus:ring-gray-500': props.text || props.outlined || props.severity === 'secondary',
        'opacity-60 cursor-not-allowed': props.disabled || state.loading,
        'shadow-sm': props.raised && !props.text && !props.outlined
      }
    ]
  }),
  label: {
    class: 'font-medium'
  },
  icon: {
    class: 'text-sm'
  },
  loadingicon: {
    class: 'animate-spin'
  }
}

// Event handlers
const onClick = (event: Event) => {
  if (!props.disabled && !props.loading) {
    emit('click', event)
  }
}

const onDblclick = (event: Event) => {
  if (!props.disabled && !props.loading) {
    emit('dblclick', event)
  }
}

const onKeydown = (event: Event) => {
  if (!props.disabled && !props.loading) {
    emit('keydown', event)
  }
}

const onKeyup = (event: Event) => {
  if (!props.disabled && !props.loading) {
    emit('keyup', event)
  }
}
</script>