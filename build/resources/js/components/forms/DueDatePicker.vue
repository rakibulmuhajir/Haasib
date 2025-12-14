<script setup lang="ts">
/**
 * DueDatePicker - Mode-Aware Due Date Selection
 *
 * Owner mode: Relative options (Net 15, Net 30, etc.)
 * Accountant mode: Standard date picker
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { ref, computed, watch } from 'vue'
import { useLexicon } from '@/composables/useLexicon'
import { useUserMode } from '@/composables/useUserMode'
import { cn } from '@/lib/utils'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Calendar, Clock } from 'lucide-vue-next'

// Props
export interface DueDatePickerProps {
  modelValue: string | null           // ISO date string (YYYY-MM-DD)
  invoiceDate?: string                // Reference date for calculating relative
  defaultTerms?: number               // Default payment terms in days
  disabled?: boolean
  error?: string
  class?: string
}

const props = withDefaults(defineProps<DueDatePickerProps>(), {
  defaultTerms: 30,
})

// Emits
const emit = defineEmits<{
  'update:modelValue': [date: string | null]
}>()

// Composables
const { t, tpl } = useLexicon()
const { isAccountantMode } = useUserMode()

// State
const relativeOption = ref<string>('30')  // Default to Net 30
const showCustomDate = ref(false)

// Date options for Owner mode
interface DateOption {
  value: string
  label: string
  getDays: () => number | null  // null for special cases like 'receipt' or 'eom'
}

const dateOptions = computed<DateOption[]>(() => [
  { value: 'receipt', label: t('dueOnReceipt'), getDays: () => 0 },
  { value: '7', label: tpl('dueInDays', { days: 7 }), getDays: () => 7 },
  { value: '14', label: tpl('dueInDays', { days: 14 }), getDays: () => 14 },
  { value: '15', label: tpl('dueInDays', { days: 15 }), getDays: () => 15 },
  { value: '30', label: tpl('dueInDays', { days: 30 }), getDays: () => 30 },
  { value: '45', label: tpl('dueInDays', { days: 45 }), getDays: () => 45 },
  { value: '60', label: tpl('dueInDays', { days: 60 }), getDays: () => 60 },
  { value: '90', label: tpl('dueInDays', { days: 90 }), getDays: () => 90 },
  { value: 'eom', label: t('dueEndOfMonth'), getDays: () => null },
  { value: 'custom', label: t('customDate'), getDays: () => null },
])

// Computed
const referenceDate = computed(() => {
  if (props.invoiceDate) {
    return new Date(props.invoiceDate)
  }
  return new Date()
})

const computedDueDate = computed(() => {
  if (!props.modelValue) return null
  return new Date(props.modelValue)
})

const formattedDueDate = computed(() => {
  if (!computedDueDate.value) return ''
  return computedDueDate.value.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
})

const daysFromNow = computed(() => {
  if (!computedDueDate.value) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const due = new Date(computedDueDate.value)
  due.setHours(0, 0, 0, 0)
  const diffTime = due.getTime() - today.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays
})

const dueLabel = computed(() => {
  if (daysFromNow.value === null) return ''
  if (daysFromNow.value === 0) return 'Due today'
  if (daysFromNow.value === 1) return 'Due tomorrow'
  if (daysFromNow.value < 0) {
    return `${Math.abs(daysFromNow.value)} days overdue`
  }
  return `Due in ${daysFromNow.value} days`
})

// Calculate date from relative option
const calculateDateFromOption = (option: string): string | null => {
  const ref = new Date(referenceDate.value)
  ref.setHours(0, 0, 0, 0)

  switch (option) {
    case 'receipt':
      return formatDateISO(ref)

    case 'eom':
      // End of month
      const eom = new Date(ref.getFullYear(), ref.getMonth() + 1, 0)
      return formatDateISO(eom)

    case 'custom':
      // Don't change date, just show custom picker
      return props.modelValue

    default:
      // Numeric days
      const days = parseInt(option, 10)
      if (!isNaN(days)) {
        ref.setDate(ref.getDate() + days)
        return formatDateISO(ref)
      }
      return null
  }
}

// Determine which option matches the current date
const getOptionFromDate = (dateStr: string | null): string => {
  if (!dateStr) return String(props.defaultTerms)

  const date = new Date(dateStr)
  const ref = new Date(referenceDate.value)
  ref.setHours(0, 0, 0, 0)
  date.setHours(0, 0, 0, 0)

  // Check if it's end of month
  const eom = new Date(ref.getFullYear(), ref.getMonth() + 1, 0)
  if (date.getTime() === eom.getTime()) {
    return 'eom'
  }

  // Calculate diff in days
  const diffTime = date.getTime() - ref.getTime()
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24))

  // Check standard options
  if (diffDays === 0) return 'receipt'
  if ([7, 14, 15, 30, 45, 60, 90].includes(diffDays)) {
    return String(diffDays)
  }

  // Custom date
  return 'custom'
}

// Format date as ISO string (YYYY-MM-DD)
const formatDateISO = (date: Date): string => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Handle relative option change
const handleRelativeChange = (value: any) => {
  const strValue = String(value)
  relativeOption.value = strValue

  if (strValue === 'custom') {
    showCustomDate.value = true
    return
  }

  showCustomDate.value = false
  const newDate = calculateDateFromOption(strValue)
  emit('update:modelValue', newDate)
}

// Handle direct date input (accountant mode or custom)
const handleDateInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  emit('update:modelValue', value || null)
}

// Initialize relative option from model value
watch(() => props.modelValue, (newVal) => {
  if (!isAccountantMode.value) {
    const option = getOptionFromDate(newVal)
    relativeOption.value = option
    showCustomDate.value = option === 'custom'
  }
}, { immediate: true })

// Re-calculate when reference date changes
watch(referenceDate, () => {
  if (!isAccountantMode.value && relativeOption.value !== 'custom') {
    const newDate = calculateDateFromOption(relativeOption.value)
    if (newDate !== props.modelValue) {
      emit('update:modelValue', newDate)
    }
  }
})
</script>

<template>
  <div :class="cn('due-date-picker', props.class)">
    <!-- Owner Mode: Relative dropdown -->
    <div v-if="!isAccountantMode" class="space-y-2">
      <Select :modelValue="relativeOption" @update:modelValue="handleRelativeChange" :disabled="disabled">
        <SelectTrigger
          :class="cn(
            'cursor-pointer transition-colors hover:bg-muted/30 data-[placeholder]:text-foreground/70',
            error && 'border-destructive'
          )"
        >
          <Clock class="h-4 w-4 mr-2 opacity-60" />
          <SelectValue :placeholder="t('dueIn')" />
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            <SelectItem
              v-for="option in dateOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>

      <!-- Show computed date -->
      <div v-if="modelValue" class="flex items-center gap-2 text-sm text-muted-foreground">
        <Calendar class="h-3.5 w-3.5" />
        <span>{{ formattedDueDate }}</span>
        <span
          v-if="daysFromNow !== null"
          :class="{
            'text-destructive': daysFromNow < 0,
            'text-amber-600': daysFromNow === 0,
          }"
        >
          ({{ dueLabel }})
        </span>
      </div>

      <!-- Custom date input -->
      <div v-if="showCustomDate" class="mt-2">
        <Input
          type="date"
          :value="modelValue"
          @input="handleDateInput"
          :disabled="disabled"
          :min="invoiceDate"
          :class="cn(error && 'border-destructive')"
        />
      </div>
    </div>

    <!-- Accountant Mode: Standard date picker -->
    <div v-else class="flex items-center gap-2">
      <div class="relative flex-1">
        <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          type="date"
          :value="modelValue"
          @input="handleDateInput"
          :disabled="disabled"
          :min="invoiceDate"
          class="pl-10"
          :class="cn(error && 'border-destructive')"
        />
      </div>

      <!-- Show due label -->
      <span
        v-if="daysFromNow !== null"
        class="text-sm whitespace-nowrap"
        :class="{
          'text-destructive': daysFromNow < 0,
          'text-amber-600': daysFromNow === 0,
          'text-muted-foreground': daysFromNow > 0,
        }"
      >
        {{ dueLabel }}
      </span>
    </div>

    <!-- Error Message -->
    <p v-if="error" class="text-sm text-destructive mt-1">
      {{ error }}
    </p>
  </div>
</template>
