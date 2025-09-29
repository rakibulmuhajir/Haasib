<template>
  <div class="date-range-picker">
    <!-- Display mode -->
    <div v-if="mode === 'display'" class="flex items-center space-x-2">
      <span class="text-gray-700">{{ displayText }}</span>
      <Button
        v-if="!disabled"
        icon="pi pi-calendar"
        class="p-button-text p-button-sm p-button-rounded"
        @click="openPicker"
        :title="'Edit date range'"
      />
    </div>

    <!-- Input mode -->
    <div v-else class="space-y-4">
      <!-- Quick presets -->
      <div v-if="showPresets" class="flex flex-wrap gap-2">
        <Button
          v-for="preset in availablePresets"
          :key="preset.key"
          :label="preset.label"
          size="small"
          :outlined="!isPresetActive(preset)"
          class="text-xs"
          @click="applyPreset(preset)"
        />
      </div>

      <!-- Date inputs -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Start Date -->
        <div class="space-y-1">
          <label :for="startInputId" class="text-sm font-medium text-gray-700">
            {{ startLabel }}
          </label>
          <div class="relative">
            <Calendar
              :id="startInputId"
              ref="startCalendar"
              v-model="startDate"
              :selectionMode="'single'"
              :dateFormat="dateFormat"
              :showIcon="showIcon"
              :icon="icon"
              :placeholder="startPlaceholder"
              :disabled="disabled"
              :readonly="readonly"
              :minDate="minDate"
              :maxDate="maxEndDate"
              :showTime="showTime"
              :hourFormat="hourFormat"
              :stepHour="stepHour"
              :stepMinute="stepMinute"
              :stepSecond="stepSecond"
              :showSeconds="showSeconds"
              :numberOfMonths="numberOfMonths"
              :inline="inline"
              :class="['w-full', { 'p-invalid': startError }]"
              @date-select="onStartDateSelect"
              @input="onStartDateInput"
              @blur="onStartDateBlur"
            />
            <div v-if="startError" class="mt-1 text-sm text-red-600 flex items-center">
              <i class="pi pi-exclamation-triangle mr-1"></i>
              {{ startError }}
            </div>
          </div>
        </div>

        <!-- End Date -->
        <div class="space-y-1">
          <label :for="endInputId" class="text-sm font-medium text-gray-700">
            {{ endLabel }}
          </label>
          <div class="relative">
            <Calendar
              :id="endInputId"
              ref="endCalendar"
              v-model="endDate"
              :selectionMode="'single'"
              :dateFormat="dateFormat"
              :showIcon="showIcon"
              :icon="icon"
              :placeholder="endPlaceholder"
              :disabled="disabled"
              :readonly="readonly"
              :minDate="minEndDate"
              :maxDate="maxDate"
              :showTime="showTime"
              :hourFormat="hourFormat"
              :stepHour="stepHour"
              :stepMinute="stepMinute"
              :stepSecond="stepSecond"
              :showSeconds="showSeconds"
              :numberOfMonths="numberOfMonths"
              :inline="inline"
              :class="['w-full', { 'p-invalid': endError }]"
              @date-select="onEndDateSelect"
              @input="onEndDateInput"
              @blur="onEndDateBlur"
            />
            <div v-if="endError" class="mt-1 text-sm text-red-600 flex items-center">
              <i class="pi pi-exclamation-triangle mr-1"></i>
              {{ endError }}
            </div>
          </div>
        </div>
      </div>

      <!-- Range validation info -->
      <div v-if="showValidation" class="text-xs text-gray-500">
        <div class="flex items-center justify-between">
          <span>Duration:</span>
          <span class="font-medium">{{ durationText }}</span>
        </div>
        <div v-if="minDuration && actualDuration < minDuration" class="text-orange-600 mt-1">
          Minimum duration: {{ formatDuration(minDuration) }}
        </div>
        <div v-if="maxDuration && actualDuration > maxDuration" class="text-red-600 mt-1">
          Maximum duration: {{ formatDuration(maxDuration) }}
        </div>
      </div>

      <!-- Helper text -->
      <div v-if="helperText" class="text-sm text-gray-500">
        {{ helperText }}
      </div>
    </div>

    <!-- Error message -->
    <div v-if="error" class="mt-1 text-sm text-red-600 flex items-center">
      <i class="pi pi-exclamation-triangle mr-1"></i>
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Calendar from 'primevue/calendar'
import Button from 'primevue/button'

interface DatePreset {
  key: string
  label: string
  startDate: Date
  endDate: Date
}

interface Props {
  modelValue?: {
    startDate: Date | string | null
    endDate: Date | string | null
  }
  mode?: 'input' | 'display'
  startLabel?: string
  endLabel?: string
  startPlaceholder?: string
  endPlaceholder?: string
  dateFormat?: string
  showIcon?: boolean
  icon?: string
  disabled?: boolean
  readonly?: boolean
  minDate?: Date
  maxDate?: Date
  showTime?: boolean
  hourFormat?: '12' | '24'
  stepHour?: number
  stepMinute?: number
  stepSecond?: number
  showSeconds?: boolean
  numberOfMonths?: number
  inline?: boolean
  showPresets?: boolean
  presets?: DatePreset[]
  minDuration?: number // in minutes
  maxDuration?: number // in minutes
  showValidation?: boolean
  error?: string
  startError?: string
  endError?: string
  helperText?: string
  startInputId?: string
  endInputId?: string
}

interface Emits {
  (e: 'update:modelValue', value: { startDate: Date | null; endDate: Date | null }): void
  (e: 'start-date-change', date: Date | null): void
  (e: 'end-date-change', date: Date | null): void
  (e: 'range-change', startDate: Date | null, endDate: Date | null): void
  (e: 'preset-apply', preset: DatePreset): void
}

const props = withDefaults(defineProps<Props>(), {
  mode: 'input',
  startLabel: 'Start Date',
  endLabel: 'End Date',
  startPlaceholder: 'Select start date',
  endPlaceholder: 'Select end date',
  dateFormat: 'yy-mm-dd',
  showIcon: true,
  icon: 'pi pi-calendar',
  disabled: false,
  readonly: false,
  showTime: false,
  hourFormat: '24',
  stepHour: 1,
  stepMinute: 1,
  stepSecond: 1,
  showSeconds: false,
  numberOfMonths: 1,
  inline: false,
  showPresets: true,
  presets: () => [],
  showValidation: true,
  startInputId: 'start-date',
  endInputId: 'end-date'
})

const emit = defineEmits<Emits>()

const startCalendar = ref()
const endCalendar = ref()

// Internal date values
const startDate = computed({
  get: () => {
    if (!props.modelValue?.startDate) return null
    return props.modelValue.startDate instanceof Date 
      ? props.modelValue.startDate 
      : new Date(props.modelValue.startDate)
  },
  set: (value) => {
    emitUpdate()
  }
})

const endDate = computed({
  get: () => {
    if (!props.modelValue?.endDate) return null
    return props.modelValue.endDate instanceof Date 
      ? props.modelValue.endDate 
      : new Date(props.modelValue.endDate)
  },
  set: (value) => {
    emitUpdate()
  }
})

// Computed properties
const displayText = computed(() => {
  if (!startDate.value && !endDate.value) return 'No date range selected'
  if (!startDate.value) return `Until ${formatDate(endDate.value)}`
  if (!endDate.value) return `From ${formatDate(startDate.value)}`
  
  return `${formatDate(startDate.value)} - ${formatDate(endDate.value)}`
})

const minEndDate = computed(() => {
  if (!startDate.value) return props.minDate
  return new Date(Math.max(startDate.value.getTime(), props.minDate?.getTime() || 0))
})

const maxEndDate = computed(() => {
  if (!startDate.value) return props.maxDate
  
  const max = props.maxDuration 
    ? new Date(startDate.value.getTime() + props.maxDuration * 60000)
    : null
  
  if (!max || !props.maxDate) return max || props.maxDate
  return new Date(Math.min(max.getTime(), props.maxDate.getTime()))
})

const actualDuration = computed(() => {
  if (!startDate.value || !endDate.value) return 0
  return Math.round((endDate.value.getTime() - startDate.value.getTime()) / 60000)
})

const durationText = computed(() => {
  const duration = actualDuration.value
  if (duration === 0) return '0 minutes'
  
  const days = Math.floor(duration / 1440)
  const hours = Math.floor((duration % 1440) / 60)
  const minutes = duration % 60
  
  const parts = []
  if (days > 0) parts.push(`${days} day${days > 1 ? 's' : ''}`)
  if (hours > 0) parts.push(`${hours} hour${hours > 1 ? 's' : ''}`)
  if (minutes > 0 && days === 0) parts.push(`${minutes} minute${minutes > 1 ? 's' : ''}`)
  
  return parts.join(', ') || '0 minutes'
})

// Default presets
const defaultPresets: DatePreset[] = [
  {
    key: 'today',
    label: 'Today',
    startDate: new Date(new Date().setHours(0, 0, 0, 0)),
    endDate: new Date(new Date().setHours(23, 59, 59, 999))
  },
  {
    key: 'yesterday',
    label: 'Yesterday',
    startDate: new Date(new Date().setDate(new Date().getDate() - 1).setHours(0, 0, 0, 0)),
    endDate: new Date(new Date().setDate(new Date().getDate() - 1).setHours(23, 59, 59, 999))
  },
  {
    key: 'thisWeek',
    label: 'This Week',
    startDate: new Date(new Date().setDate(new Date().getDate() - new Date().getDay()).setHours(0, 0, 0, 0)),
    endDate: new Date(new Date().setDate(new Date().getDate() + (6 - new Date().getDay())).setHours(23, 59, 59, 999))
  },
  {
    key: 'lastWeek',
    label: 'Last Week',
    startDate: new Date(new Date().setDate(new Date().getDate() - new Date().getDay() - 7).setHours(0, 0, 0, 0)),
    endDate: new Date(new Date().setDate(new Date().getDate() - new Date().getDay() - 1).setHours(23, 59, 59, 999))
  },
  {
    key: 'thisMonth',
    label: 'This Month',
    startDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    endDate: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0, 23, 59, 59, 999)
  },
  {
    key: 'lastMonth',
    label: 'Last Month',
    startDate: new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1),
    endDate: new Date(new Date().getFullYear(), new Date().getMonth(), 0, 23, 59, 59, 999)
  },
  {
    key: 'last30Days',
    label: 'Last 30 Days',
    startDate: new Date(new Date().setDate(new Date().getDate() - 30).setHours(0, 0, 0, 0)),
    endDate: new Date(new Date().setHours(23, 59, 59, 999))
  },
  {
    key: 'thisYear',
    label: 'This Year',
    startDate: new Date(new Date().getFullYear(), 0, 1),
    endDate: new Date(new Date().getFullYear(), 11, 31, 23, 59, 59, 999)
  }
]

const availablePresets = computed(() => {
  return [...props.presets, ...defaultPresets]
})

// Methods
const formatDate = (date: Date | null): string => {
  if (!date) return ''
  
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: props.showTime ? 'numeric' : undefined,
    minute: props.showTime ? 'numeric' : undefined
  }).format(date)
}

const formatDuration = (minutes: number): string => {
  const days = Math.floor(minutes / 1440)
  const hours = Math.floor((minutes % 1440) / 60)
  const mins = minutes % 60
  
  if (days > 0) return `${days} day${days > 1 ? 's' : ''}`
  if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''}`
  return `${mins} minute${mins > 1 ? 's' : ''}`
}

const isPresetActive = (preset: DatePreset): boolean => {
  if (!startDate.value || !endDate.value) return false
  
  const startMatch = Math.abs(startDate.value.getTime() - preset.startDate.getTime()) < 1000
  const endMatch = Math.abs(endDate.value.getTime() - preset.endDate.getTime()) < 1000
  
  return startMatch && endMatch
}

const emitUpdate = () => {
  emit('update:modelValue', {
    startDate: startDate.value,
    endDate: endDate.value
  })
  emit('range-change', startDate.value, endDate.value)
}

// Event handlers
const onStartDateSelect = (date: Date) => {
  emit('start-date-change', date)
  
  // Auto-select end date if min duration is set
  if (props.minDuration && !endDate.value) {
    const newEndDate = new Date(date.getTime() + props.minDuration * 60000)
    emit('update:modelValue', {
      startDate: date,
      endDate: newEndDate
    })
  }
}

const onEndDateSelect = (date: Date) => {
  emit('end-date-change', date)
}

const onStartDateInput = () => {
  emitUpdate()
}

const onEndDateInput = () => {
  emitUpdate()
}

const onStartDateBlur = () => {
  // Additional validation if needed
}

const onEndDateBlur = () => {
  // Additional validation if needed
}

const applyPreset = (preset: DatePreset) => {
  emit('update:modelValue', {
    startDate: preset.startDate,
    endDate: preset.endDate
  })
  emit('preset-apply', preset)
}

const openPicker = () => {
  // Switch to input mode
  // This would require a prop or event to handle mode switching
}

// Expose methods
const focusStart = () => {
  nextTick(() => {
    startCalendar.value?.$el?.querySelector('input')?.focus()
  })
}

const focusEnd = () => {
  nextTick(() => {
    endCalendar.value?.$el?.querySelector('input')?.focus()
  })
}

defineExpose({
  focusStart,
  focusEnd
})
</script>

<style scoped>
.date-range-picker :deep(.p-calendar) {
  width: 100%;
}

.date-range-picker :deep(.p-calendar-input) {
  width: 100%;
}

/* Preset buttons */
.date-range-picker :deep(.p-button-outlined) {
  border-color: #e5e7eb;
}

.date-range-picker :deep(.p-button-outlined.p-button-sm) {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}
</style>