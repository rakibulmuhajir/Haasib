<template>
  <component
    :is="tag"
    :for="for"
    :class="labelClasses"
    v-bind="$attrs"
  >
    <span v-if="required" class="text-red-500 mr-1" aria-hidden="true">*</span>
    
    <!-- Default slot for label content -->
    <slot />
    
    <!-- Help text icon -->
    <span
      v-if="helpText || $slots.helpText"
      class="ml-1 inline-flex items-center"
      :class="helpIconClasses"
    >
      <i
        v-if="!$slots.helpText"
        class="pi pi-question-circle"
        :class="helpIconClass"
      />
      <slot v-else name="helpText" />
      
      <!-- Tooltip for help text -->
      <span
        v-if="helpText && showHelpTooltip"
        role="tooltip"
        :id="tooltipId"
        class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10 whitespace-nowrap"
      >
        {{ helpText }}
        <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></span>
      </span>
    </span>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  for?: string
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  helpText?: string
  showHelpTooltip?: boolean
  helpIconClass?: string
  helpIconPosition?: 'left' | 'right'
  size?: 'sm' | 'md' | 'lg'
  weight?: 'normal' | 'medium' | 'semibold' | 'bold'
  variant?: 'default' | 'secondary' | 'success' | 'warning' | 'danger'
  tag?: 'label' | 'div' | 'span'
  class?: string | object | any[]
}

const props = withDefaults(defineProps<Props>(), {
  showHelpTooltip: true,
  helpIconPosition: 'right',
  size: 'md',
  weight: 'medium',
  variant: 'default',
  tag: 'label'
})

// Generate unique ID for tooltip
const tooltipId = computed(() => {
  return props.for ? `${props.for}-help` : `help-${Math.random().toString(36).substr(2, 9)}`
})

// Computed classes
const labelClasses = computed(() => {
  const classes = [
    'inline-flex items-center',
    'transition-colors duration-200',
    'select-none', // Prevent text selection
    {
      // Size classes
      'text-sm': props.size === 'sm',
      'text-base': props.size === 'md',
      'text-lg': props.size === 'lg',
      
      // Weight classes
      'font-normal': props.weight === 'normal',
      'font-medium': props.weight === 'medium',
      'font-semibold': props.weight === 'semibold',
      'font-bold': props.weight === 'bold',
      
      // Color variants
      'text-gray-700': props.variant === 'default' && !props.disabled && !props.readonly,
      'text-gray-500': props.variant === 'secondary' || props.disabled || props.readonly,
      'text-green-700': props.variant === 'success',
      'text-yellow-700': props.variant === 'warning',
      'text-red-700': props.variant === 'danger',
      
      // Interactive states
      'cursor-default': !props.disabled && !props.readonly,
      'cursor-not-allowed opacity-60': props.disabled,
      'opacity-80': props.readonly
    }
  ]
  
  // Add custom classes
  if (props.class) {
    if (typeof props.class === 'string') {
      classes.push(props.class)
    } else {
      Object.entries(props.class).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const helpIconClasses = computed(() => {
  return [
    'relative group', // For tooltip positioning
    {
      'order-first': props.helpIconPosition === 'left',
      'order-last': props.helpIconPosition === 'right',
      'text-gray-400 hover:text-gray-600': !props.disabled && !props.readonly,
      'text-gray-300': props.disabled || props.readonly,
      'cursor-help': props.helpText && props.showHelpTooltip
    },
    props.helpIconClass
  ]
})
</script>