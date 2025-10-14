<template>
  <div
    :id="errorId"
    :class="errorClasses"
    role="alert"
    aria-live="polite"
  >
    <!-- Icon slot or default icon -->
    <slot name="icon">
      <i :class="iconClasses" />
    </slot>
    
    <!-- Error message -->
    <span :class="textClasses">
      <slot>{{ error }}</slot>
    </span>
    
    <!-- Action slot (e.g., retry button) -->
    <slot v-if="$slots.action" name="action" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  error?: string | null
  errorId?: string
  variant?: 'error' | 'warning' | 'info' | 'success'
  size?: 'sm' | 'md' | 'lg'
  showIcon?: boolean
  icon?: string
  iconClass?: string | object | any[]
  textClass?: string | object | any[]
  class?: string | object | any[]
  dismissible?: boolean
  animated?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'error',
  size: 'md',
  showIcon: true,
  animated: true
})

// Default icons for each variant
const defaultIcons = {
  error: 'pi pi-exclamation-triangle',
  warning: 'pi pi-exclamation-circle',
  info: 'pi pi-info-circle',
  success: 'pi pi-check-circle'
}

// Generate error ID if not provided
const errorId = computed(() => {
  return props.errorId || `error-${Math.random().toString(36).substr(2, 9)}`
})

// Computed classes
const errorClasses = computed(() => {
  const classes = [
    'flex items-start gap-2',
    'transition-all duration-200',
    {
      // Size classes
      'text-xs p-2 rounded': props.size === 'sm',
      'text-sm p-3 rounded-md': props.size === 'md',
      'text-base p-4 rounded-lg': props.size === 'lg',
      
      // Variant classes
      'bg-red-50 text-red-800 border border-red-200': props.variant === 'error',
      'bg-yellow-50 text-yellow-800 border border-yellow-200': props.variant === 'warning',
      'bg-blue-50 text-blue-800 border border-blue-200': props.variant === 'info',
      'bg-green-50 text-green-800 border border-green-200': props.variant === 'success',
      
      // Animation
      'animate-fade-in': props.animated
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

const iconClasses = computed(() => {
  const classes = [
    'flex-shrink-0 mt-0.5', // Align with top of text
    {
      // Size classes
      'text-sm': props.size === 'sm',
      'text-base': props.size === 'md',
      'text-lg': props.size === 'lg',
      
      // Variant colors
      'text-red-500': props.variant === 'error',
      'text-yellow-500': props.variant === 'warning',
      'text-blue-500': props.variant === 'info',
      'text-green-500': props.variant === 'success'
    }
  ]
  
  // Add icon or default icon
  if (props.icon) {
    classes.push(props.icon)
  } else {
    classes.push(defaultIcons[props.variant])
  }
  
  // Add custom icon classes
  if (props.iconClass) {
    if (typeof props.iconClass === 'string') {
      classes.push(props.iconClass)
    } else {
      Object.entries(props.iconClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const textClasses = computed(() => {
  const classes = [
    'flex-1 min-w-0'
  ]
  
  // Add custom text classes
  if (props.textClass) {
    if (typeof props.textClass === 'string') {
      classes.push(props.textClass)
    } else {
      Object.entries(props.textClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})
</script>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>