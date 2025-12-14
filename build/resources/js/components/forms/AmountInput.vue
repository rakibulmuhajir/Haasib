<script setup lang="ts">
/**
 * AmountInput - Currency-Aware Amount Input
 *
 * A formatted amount input that handles currency display and formatting.
 * Auto-formats on blur, strips formatting on focus for editing.
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { ref, computed, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'

// Currency configuration
const CURRENCY_CONFIG: Record<string, { symbol: string; decimals: number; symbolPosition: 'before' | 'after' }> = {
  USD: { symbol: '$', decimals: 2, symbolPosition: 'before' },
  EUR: { symbol: '\u20AC', decimals: 2, symbolPosition: 'before' },
  GBP: { symbol: '\u00A3', decimals: 2, symbolPosition: 'before' },
  SAR: { symbol: 'SAR', decimals: 2, symbolPosition: 'before' },
  AED: { symbol: 'AED', decimals: 2, symbolPosition: 'before' },
  QAR: { symbol: 'QAR', decimals: 2, symbolPosition: 'before' },
  KWD: { symbol: 'KWD', decimals: 3, symbolPosition: 'before' },
  BHD: { symbol: 'BHD', decimals: 3, symbolPosition: 'before' },
  OMR: { symbol: 'OMR', decimals: 3, symbolPosition: 'before' },
  EGP: { symbol: 'EGP', decimals: 2, symbolPosition: 'before' },
  JOD: { symbol: 'JOD', decimals: 3, symbolPosition: 'before' },
  INR: { symbol: '\u20B9', decimals: 2, symbolPosition: 'before' },
  PKR: { symbol: 'PKR', decimals: 2, symbolPosition: 'before' },
  JPY: { symbol: '\u00A5', decimals: 0, symbolPosition: 'before' },
  CNY: { symbol: '\u00A5', decimals: 2, symbolPosition: 'before' },
}

// Props
export interface AmountInputProps {
  modelValue: number | null
  currency?: string
  placeholder?: string
  disabled?: boolean
  error?: string
  size?: 'sm' | 'md' | 'lg'
  showCurrency?: boolean
  min?: number
  max?: number
  class?: string
}

const props = withDefaults(defineProps<AmountInputProps>(), {
  showCurrency: true,
  size: 'md',
  min: 0,
})

// Emits
const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  'blur': [event: FocusEvent]
  'focus': [event: FocusEvent]
}>()

// Company context for default currency
const page = usePage()
const company = computed(() => (page.props.auth as any)?.currentCompany)

// State
const inputRef = ref<HTMLInputElement | null>()
const displayValue = ref('')
const isFocused = ref(false)

// Computed
const effectiveCurrency = computed(() => {
  return props.currency || company.value?.base_currency || 'USD'
})

const currencyConfig = computed(() => {
  return CURRENCY_CONFIG[effectiveCurrency.value] || CURRENCY_CONFIG.USD
})

const currencySymbol = computed(() => currencyConfig.value.symbol)

const decimals = computed(() => currencyConfig.value.decimals)

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 text-sm'
    case 'lg':
      return 'h-12 text-2xl font-semibold tracking-tight'
    default:
      return 'h-9 text-base'
  }
})

const inputPadding = computed(() => {
  if (!props.showCurrency) return ''
  // Adjust padding based on currency symbol length
  const symbolLength = currencySymbol.value.length
  if (symbolLength <= 1) return 'pl-12'
  if (symbolLength <= 3) return 'pl-16'
  return 'pl-20'
})

// Format number for display
const formatNumber = (value: number | null): string => {
  if (value === null || isNaN(value)) return ''

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals.value,
    maximumFractionDigits: decimals.value,
    useGrouping: true,
  }).format(value)
}

// Parse input string to number
const parseInput = (input: string): number | null => {
  if (!input || input.trim() === '') return null

  // Remove currency symbols and formatting
  let cleaned = input
    .replace(/[^\d.,\-]/g, '')  // Keep only digits, dots, commas, minus
    .replace(/,/g, '')           // Remove thousand separators

  const num = parseFloat(cleaned)
  if (isNaN(num)) return null

  // Round to currency decimals
  const multiplier = Math.pow(10, decimals.value)
  return Math.round(num * multiplier) / multiplier
}

// Update display value from model
const updateDisplayFromModel = () => {
  if (isFocused.value) {
    // When focused, show raw number without formatting for easier editing
    displayValue.value = props.modelValue !== null ? String(props.modelValue) : ''
  } else {
    // When not focused, show formatted value
    displayValue.value = formatNumber(props.modelValue)
  }
}

// Handle input changes
const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const rawValue = target.value

  // Allow typing in progress (don't parse yet)
  displayValue.value = rawValue
}

// Handle focus
const handleFocus = (event: FocusEvent) => {
  isFocused.value = true
  // Convert to raw number for editing
  displayValue.value = props.modelValue !== null ? String(props.modelValue) : ''
  // Select all text for easy replacement
  nextTick(() => {
    const input = event.target as HTMLInputElement
    input.select()
  })
  emit('focus', event)
}

// Handle blur
const handleBlur = (event: FocusEvent) => {
  isFocused.value = false

  // Parse and validate the input
  const parsed = parseInput(displayValue.value)

  // Apply min/max constraints
  let validated = parsed
  if (validated !== null) {
    if (props.min !== undefined && validated < props.min) {
      validated = props.min
    }
    if (props.max !== undefined && validated > props.max) {
      validated = props.max
    }
  }

  // Update model
  emit('update:modelValue', validated)

  // Update display with formatted value
  displayValue.value = formatNumber(validated)

  emit('blur', event)
}

// Handle keydown for special keys
const handleKeydown = (event: KeyboardEvent) => {
  // Allow: backspace, delete, tab, escape, enter
  if ([8, 9, 27, 13, 46].includes(event.keyCode)) {
    return
  }

  // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
  if ((event.ctrlKey || event.metaKey) && [65, 67, 86, 88].includes(event.keyCode)) {
    return
  }

  // Allow: home, end, left, right, down, up
  if (event.keyCode >= 35 && event.keyCode <= 40) {
    return
  }

  // Allow: minus for negative numbers (only at start)
  if (event.key === '-' && props.min !== undefined && props.min < 0) {
    const input = event.target as HTMLInputElement
    if (input.selectionStart === 0 && !displayValue.value.includes('-')) {
      return
    }
  }

  // Allow: decimal point (only one)
  if ((event.key === '.' || event.key === ',') && decimals.value > 0) {
    if (!displayValue.value.includes('.')) {
      return
    }
    event.preventDefault()
    return
  }

  // Block non-numeric keys
  if (!/^\d$/.test(event.key)) {
    event.preventDefault()
  }
}

// Watch model value changes
watch(() => props.modelValue, () => {
  if (!isFocused.value) {
    updateDisplayFromModel()
  }
}, { immediate: true })

// Watch currency changes to reformat
watch(effectiveCurrency, () => {
  if (!isFocused.value) {
    updateDisplayFromModel()
  }
})
</script>

<template>
  <div :class="cn('amount-input relative', props.class)">
    <!-- Currency Symbol -->
    <span
      v-if="showCurrency"
      class="absolute left-2 top-1/2 -translate-y-1/2 select-none pointer-events-none rounded-md border border-border/70 bg-muted/60 px-2 py-1 text-xs font-medium text-muted-foreground"
      :class="size === 'sm' ? 'py-0.5 text-[11px]' : 'text-xs'"
    >
      {{ currencySymbol }}
    </span>

    <!-- Input -->
    <Input
      ref="inputRef"
      type="text"
      inputmode="decimal"
      :value="displayValue"
      @input="handleInput"
      @focus="handleFocus"
      @blur="handleBlur"
      @keydown="handleKeydown"
      :placeholder="placeholder || '0.00'"
      :disabled="disabled"
      :class="cn(
        sizeClasses,
        inputPadding,
        'text-right font-mono tabular-nums bg-muted/10 border-border/70',
        error && 'border-destructive focus-visible:ring-destructive/20'
      )"
      :aria-invalid="!!error"
    />

    <!-- Error Message -->
    <p v-if="error" class="text-sm text-destructive mt-1">
      {{ error }}
    </p>
  </div>
</template>
