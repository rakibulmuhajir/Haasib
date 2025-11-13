<!--
SMART LINK COMPONENT

A reusable component that intelligently chooses between Link and Button
based on the navigation requirements. It handles both GET and non-GET requests
with consistent styling and behavior.

PROPS:
- href: The URL to navigate to
- method: HTTP method (defaults to 'get')
- as: Render as 'link' or 'button' (defaults to 'button')
- variant: PrimeVue button variant (primary, secondary, danger, text)
- size: Button size (small, normal, large)
- disabled: Disable the link/button
- loading: Show loading state
- preserveScroll: Preserve scroll position on navigation
- preserveState: Preserve component state on navigation

USAGE:
<SmartLink href="/customers/create" variant="primary">
  Add Customer
</SmartLink>

<SmartLink href="/customers/123" method="delete" variant="danger" text>
  Delete Customer
</SmartLink>
-->
<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'

// ============================================================================
// PROPS DEFINITION
// ============================================================================

interface Props {
  href: string
  method?: 'get' | 'post' | 'put' | 'patch' | 'delete'
  as?: 'link' | 'button'
  variant?: 'primary' | 'secondary' | 'danger' | 'text' | 'success' | 'info' | 'warning'
  size?: 'small' | 'normal' | 'large'
  disabled?: boolean
  loading?: boolean
  preserveScroll?: boolean
  preserveState?: boolean
  confirm?: string
  icon?: string
  iconPos?: 'left' | 'right'
  badge?: string | number
  badgeSeverity?: 'success' | 'info' | 'warning' | 'danger'
  outlined?: boolean
  rounded?: boolean
  text?: boolean
  plain?: boolean
  raised?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  method: 'get',
  as: 'button',
  variant: 'primary',
  size: 'normal',
  disabled: false,
  loading: false,
  preserveScroll: false,
  preserveState: false,
  outlined: false,
  rounded: false,
  text: false,
  plain: false,
  raised: false
})

// ============================================================================
// EMITS DEFINITION
// ============================================================================

interface Emits {
  (e: 'click', event: MouseEvent): void
  (e: 'navigate', href: string, method: string): void
  (e: 'confirm', confirmed: boolean): void
}

const emit = defineEmits<Emits>()

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================

const isLink = computed((): boolean => props.as === 'link')

const isGetRequest = computed((): boolean => props.method === 'get')

const componentType = computed(() => {
  return isLink.value ? Link : 'button'
})

const buttonClasses = computed(() => {
  const classes = []
  
  if (!isLink.value) {
    // PrimeVue button classes
    classes.push('p-button')
    
    // Variant
    if (props.variant !== 'primary') {
      classes.push(`p-button-${props.variant}`)
    }
    
    // Size
    if (props.size !== 'normal') {
      classes.push(`p-button-${props.size}`)
    }
    
    // Modifiers
    if (props.outlined) classes.push('p-button-outlined')
    if (props.rounded) classes.push('p-button-rounded')
    if (props.text) classes.push('p-button-text')
    if (props.plain) classes.push('p-button-plain')
    if (props.raised) classes.push('p-button-raised')
    
    // States
    if (props.disabled) classes.push('p-disabled')
    if (props.loading) classes.push('p-button-loading')
    
    // Icon position
    if (props.icon) {
      classes.push(`p-button-icon-pos-${props.iconPos || 'left'}`)
    }
  }
  
  return classes.join(' ')
})

// ============================================================================
// METHODS
// ============================================================================

const handleClick = (event: MouseEvent): void => {
  if (props.disabled || props.loading) {
    event.preventDefault()
    return
  }
  
  emit('click', event)
  
  // Handle confirmation dialog
  if (props.confirm) {
    const confirmed = window.confirm(props.confirm)
    if (!confirmed) {
      event.preventDefault()
      return
    }
    emit('confirm', true)
  }
  
  // Handle non-GET requests with button
  if (!isLink.value && !isGetRequest.value) {
    event.preventDefault()
    
    const navigationOptions = {
      method: props.method,
      preserveScroll: props.preserveScroll,
      preserveState: props.preserveState
    }
    
    router.visit(props.href, navigationOptions)
    emit('navigate', props.href, props.method)
  }
}
</script>

<template>
  <component
    :is="componentType"
    :href="isLink ? href : undefined"
    :method="isLink && !isGetRequest ? method : undefined"
    :class="buttonClasses"
    :disabled="disabled || loading"
    :loading="loading"
    :icon="icon"
    :iconPos="iconPos"
    :badge="badge"
    :badgeSeverity="badgeSeverity"
    :outlined="outlined"
    :rounded="rounded"
    :text="text"
    :plain="plain"
    :raised="raised"
    @click="handleClick"
  >
    <slot />
  </component>
</template>

<style scoped>
/* Custom styles for enhanced interactions */
.p-button {
  transition: all 0.2s ease-in-out;
}

.p-button:hover:not(.p-disabled):not(.p-button-loading) {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.p-button:active:not(.p-disabled):not(.p-button-loading) {
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
  .p-button:hover:not(.p-disabled):not(.p-button-loading) {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
  }
}

/* Loading animation */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.pi-spin {
  animation: spin 1s linear infinite;
}

/* Badge positioning */
.p-button .p-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  min-width: 16px;
  height: 16px;
  line-height: 16px;
}

/* Icon spacing */
.p-button .p-button-icon {
  margin-right: 0.5rem;
}

.p-button-icon-pos-right .p-button-icon {
  margin-right: 0;
  margin-left: 0.5rem;
}

/* Focus states for accessibility */
.p-button:focus {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Disabled state styling */
.p-button.p-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Confirmation dialog styling */
.p-button[data-confirm] {
  position: relative;
}

.p-button[data-confirm]::after {
  content: '';
  position: absolute;
  top: -2px;
  left: -2px;
  right: -2px;
  bottom: -2px;
  border: 2px solid var(--orange-500);
  border-radius: inherit;
  pointer-events: none;
}
</style>