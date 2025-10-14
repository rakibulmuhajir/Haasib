<template>
  <component
    :is="tag"
    :class="cardClasses"
    v-bind="$attrs"
  >
    <!-- Loading overlay -->
    <div v-if="loading" :class="loadingOverlayClasses">
      <div class="flex flex-col items-center justify-center space-y-3">
        <i :class="loadingIconClasses" />
        <span v-if="loadingText" class="text-sm font-medium text-gray-700">
          {{ loadingText }}
        </span>
      </div>
    </div>

    <!-- Card Header -->
    <div v-if="$slots.header || title || subtitle" :class="headerClasses">
      <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <!-- Title -->
          <component
            :is="titleTag"
            v-if="title || $slots.title"
            :class="titleClasses"
          >
            <slot name="title">{{ title }}</slot>
          </component>
          
          <!-- Subtitle -->
          <div v-if="subtitle || $slots.subtitle" :class="subtitleClasses">
            <slot name="subtitle">{{ subtitle }}</slot>
          </div>
        </div>
        
        <!-- Header Actions -->
        <div v-if="$slots.headerActions" :class="headerActionsClasses">
          <slot name="headerActions" />
        </div>
      </div>
    </div>

    <!-- Default slot for card content -->
    <slot />
    
    <!-- Card Footer -->
    <div v-if="$slots.footer" :class="footerClasses">
      <slot name="footer" />
    </div>

    <!-- Border Accent (for accent variant) -->
    <div v-if="variant === 'accent' && accentColor" :class="accentClasses" />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  tag?: string
  title?: string
  subtitle?: string
  titleTag?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'div'
  variant?: 'default' | 'outlined' | 'flat' | 'accent' | 'hover'
  size?: 'sm' | 'md' | 'lg'
  rounded?: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'full'
  shadow?: 'none' | 'sm' | 'md' | 'lg' | 'xl'
  padding?: 'none' | 'sm' | 'md' | 'lg'
  hoverable?: boolean
  loading?: boolean
  loadingText?: string
  loadingIcon?: string
  accentColor?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info'
  bordered?: boolean
  class?: string | object | any[]
  headerClass?: string | object | any[]
  titleClass?: string | object | any[]
  subtitleClass?: string | object | any[]
  headerActionsClass?: string | object | any[]
  footerClass?: string | object | any[]
  bodyClass?: string | object | any[]
}

const props = withDefaults(defineProps<Props>(), {
  tag: 'div',
  titleTag: 'h3',
  variant: 'default',
  size: 'md',
  rounded: 'md',
  shadow: 'sm',
  padding: 'md',
  hoverable: false,
  loadingIcon: 'pi pi-spinner pi-spin',
  bordered: false
})

// Computed classes
const cardClasses = computed(() => {
  const classes = [
    'relative',
    'overflow-hidden',
    'transition-all duration-200',
    {
      // Size classes
      'min-h-[120px]': props.size === 'sm',
      'min-h-[160px]': props.size === 'md',
      'min-h-[200px]': props.size === 'lg',
      
      // Rounded classes
      'rounded-none': props.rounded === 'none',
      'rounded-sm': props.rounded === 'sm',
      'rounded': props.rounded === 'md',
      'rounded-lg': props.rounded === 'lg',
      'rounded-xl': props.rounded === 'xl',
      'rounded-full': props.rounded === 'full',
      
      // Shadow classes
      'shadow-none': props.shadow === 'none',
      'shadow-sm': props.shadow === 'sm',
      'shadow': props.shadow === 'md',
      'shadow-md': props.shadow === 'md',
      'shadow-lg': props.shadow === 'lg',
      'shadow-xl': props.shadow === 'xl',
      
      // Background classes
      'bg-white': props.variant === 'default' || props.variant === 'accent',
      'bg-transparent': props.variant === 'outlined',
      'bg-gray-50': props.variant === 'flat',
      
      // Border classes
      'border border-gray-200': props.bordered || props.variant === 'outlined',
      
      // Hover effect
      'hover:shadow-lg hover:-translate-y-0.5': props.hoverable && !props.loading,
      
      // Loading state
      'pointer-events-none opacity-75': props.loading
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

const loadingOverlayClasses = computed(() => {
  return [
    'absolute inset-0 z-10',
    'bg-white bg-opacity-80 backdrop-blur-sm',
    'flex items-center justify-center'
  ]
})

const loadingIconClasses = computed(() => {
  return [
    props.loadingIcon,
    'text-2xl',
    'text-gray-500'
  ]
})

const headerClasses = computed(() => {
  const classes = [
    'flex items-start justify-between',
    {
      'pb-3 sm:pb-4': props.padding === 'md',
      'pb-2 sm:pb-3': props.padding === 'sm',
      'pb-4 sm:pb-6': props.padding === 'lg',
      'border-b border-gray-200': props.bordered && ($slots.default || $slots.footer)
    }
  ]
  
  if (props.headerClass) {
    if (typeof props.headerClass === 'string') {
      classes.push(props.headerClass)
    } else {
      Object.entries(props.headerClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const titleClasses = computed(() => {
  const classes = [
    'font-semibold text-gray-900 leading-6',
    {
      'text-lg': props.size === 'sm',
      'text-xl': props.size === 'md',
      'text-2xl': props.size === 'lg'
    }
  ]
  
  if (props.titleClass) {
    if (typeof props.titleClass === 'string') {
      classes.push(props.titleClass)
    } else {
      Object.entries(props.titleClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const subtitleClasses = computed(() => {
  const classes = [
    'mt-1 text-sm text-gray-500'
  ]
  
  if (props.subtitleClass) {
    if (typeof props.subtitleClass === 'string') {
      classes.push(props.subtitleClass)
    } else {
      Object.entries(props.subtitleClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const headerActionsClasses = computed(() => {
  const classes = [
    'flex-shrink-0 ml-4'
  ]
  
  if (props.headerActionsClass) {
    if (typeof props.headerActionsClass === 'string') {
      classes.push(props.headerActionsClass)
    } else {
      Object.entries(props.headerActionsClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const footerClasses = computed(() => {
  const classes = [
    {
      'pt-3 sm:pt-4': props.padding === 'md',
      'pt-2 sm:pt-3': props.padding === 'sm',
      'pt-4 sm:pt-6': props.padding === 'lg',
      'border-t border-gray-200': props.bordered && ($slots.default || $slots.header)
    }
  ]
  
  if (props.footerClass) {
    if (typeof props.footerClass === 'string') {
      classes.push(props.footerClass)
    } else {
      Object.entries(props.footerClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const accentClasses = computed(() => {
  const classes = [
    'absolute top-0 left-0 right-0 h-1'
  ]
  
  switch (props.accentColor) {
    case 'primary':
      classes.push('bg-primary-500')
      break
    case 'secondary':
      classes.push('bg-gray-500')
      break
    case 'success':
      classes.push('bg-green-500')
      break
    case 'warning':
      classes.push('bg-yellow-500')
      break
    case 'danger':
      classes.push('bg-red-500')
      break
    case 'info':
      classes.push('bg-blue-500')
      break
  }
  
  return classes
})
</script>