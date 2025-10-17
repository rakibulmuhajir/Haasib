<script setup lang="ts">
import { computed } from 'vue'
import PrimeCard from 'primevue/card'
import PrimeProgressBar from 'primevue/progressbar'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'

interface PeriodCloseData {
  id: string
  status: string
  started_at?: string
  started_by?: string
  tasks_count: number
  completed_tasks_count: number
  required_tasks_count: number
  required_completed_count: number
  completion_percentage?: number
  required_completion_percentage?: number
}

interface Period {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
  fiscal_year: {
    id: string
    name: string
  }
  period_close?: PeriodCloseData
  can_close: boolean
  is_overdue: boolean
}

interface ProgressCardProps {
  period: Period
  detailed?: boolean
  size?: 'small' | 'medium' | 'large'
}

const props = withDefaults(defineProps<ProgressCardProps>(), {
  detailed: true,
  size: 'medium'
})

const hasPeriodClose = computed(() => !!props.period.period_close)

const taskCompletionPercentage = computed(() => {
  if (!props.period.period_close) return 0
  return props.period.period_close.completion_percentage || 
    Math.round((props.period.period_close.completed_tasks_count / props.period.period_close.tasks_count) * 100)
})

const requiredTaskCompletionPercentage = computed(() => {
  if (!props.period.period_close) return 0
  return props.period.period_close.required_completion_percentage || 
    Math.round((props.period.period_close.required_completed_count / props.period.period_close.required_tasks_count) * 100)
})

const statusColor = computed(() => {
  if (!hasPeriodClose.value) return 'secondary'
  
  switch (props.period.period_close!.status) {
    case 'closed': return 'success'
    case 'locked': return 'info'
    case 'awaiting_approval': return 'warning'
    case 'in_review': return 'primary'
    case 'not_started': return 'secondary'
    default: return 'secondary'
  }
})

const statusIcon = computed(() => {
  if (!hasPeriodClose.value) return 'pi pi-circle'
  
  switch (props.period.period_close!.status) {
    case 'closed': return 'pi pi-check-circle'
    case 'locked': return 'pi pi-lock'
    case 'awaiting_approval': return 'pi pi-clock'
    case 'in_review': return 'pi pi-eye'
    case 'not_started': return 'pi pi-circle'
    default: return 'pi pi-question-circle'
  }
})

const progressColor = computed(() => {
  const percentage = taskCompletionPercentage.value
  if (percentage === 100) return 'success'
  if (percentage >= 75) return 'primary'
  if (percentage >= 50) return 'warning'
  return 'danger'
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'small': return 'p-4'
    case 'large': return 'p-6'
    default: return 'p-5'
  }
})

function getStatusText(status: string): string {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString()
}

function formatDateTime(dateString?: string): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

function formatDuration(startDate: string, endDate: string): string {
  const start = new Date(startDate)
  const end = new Date(endDate)
  const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1
  return `${days} days`
}
</script>

<template>
  <PrimeCard :class="[sizeClasses, { 'hover:shadow-lg transition-shadow cursor-pointer': detailed }]">
    <template #header v-if="!detailed">
      <div class="flex items-center space-x-2">
        <i :class="statusIcon" class="text-gray-500"></i>
        <span class="font-medium">{{ period.name }}</span>
      </div>
    </template>

    <template #content>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 v-if="detailed" class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
            {{ period.name }}
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
            <span class="text-xs text-gray-500 ml-1">
              ({{ formatDuration(period.start_date, period.end_date) }})
            </span>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
            {{ period.fiscal_year.name }}
          </p>
        </div>
        
        <div class="flex items-center space-x-2">
          <PrimeTag 
            v-if="hasPeriodClose"
            :value="getStatusText(period.period_close!.status)" 
            :severity="statusColor"
            size="small"
          />
          <PrimeTag 
            v-else
            value="Not Started" 
            severity="secondary"
            size="small"
          />
        </div>
      </div>

      <!-- Progress Overview -->
      <div v-if="hasPeriodClose" class="space-y-3">
        <!-- Overall Progress -->
        <div>
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Task Progress
            </span>
            <div class="flex items-center space-x-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ period.period_close!.completed_tasks_count }}/{{ period.period_close!.tasks_count }}
              </span>
              <PrimeBadge 
                :value="`${taskCompletionPercentage}%`" 
                :severity="progressColor"
                size="small"
              />
            </div>
          </div>
          <PrimeProgressBar 
            :value="taskCompletionPercentage" 
            :showValue="false"
            :class="{ 'mb-2': detailed }"
          />
        </div>

        <!-- Required Tasks Progress -->
        <div v-if="detailed">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Required Tasks
            </span>
            <div class="flex items-center space-x-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ period.period_close!.required_completed_count }}/{{ period.period_close!.required_tasks_count }}
              </span>
              <PrimeBadge 
                :value="`${requiredTaskCompletionPercentage}%`" 
                severity="success"
                size="small"
              />
            </div>
          </div>
          <PrimeProgressBar 
            :value="requiredTaskCompletionPercentage" 
            :showValue="false"
            class="mb-3"
          />
        </div>

        <!-- Additional Details -->
        <div v-if="detailed" class="space-y-2">
          <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
            <span>Started</span>
            <span class="text-right">
              {{ formatDateTime(period.period_close!.started_at) }}
            </span>
          </div>
          
          <div v-if="period.period_close!.started_by" class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
            <span>Started By</span>
            <span>{{ period.period_close!.started_by }}</span>
          </div>

          <div v-if="period.is_overdue" class="flex items-center text-xs text-red-600 dark:text-red-400 mt-2">
            <i class="pi pi-exclamation-triangle mr-1"></i>
            This period is overdue for closing
          </div>
        </div>
      </div>

      <!-- Not Started State -->
      <div v-else class="text-center py-4">
        <i class="pi pi-circle text-3xl text-gray-300 dark:text-gray-600 mb-2"></i>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Period close not started
        </p>
        <div v-if="period.is_overdue" class="flex items-center justify-center text-xs text-red-600 dark:text-red-400 mt-2">
          <i class="pi pi-exclamation-triangle mr-1"></i>
          This period is overdue for closing
        </div>
      </div>

      <!-- Action Indicator -->
      <div v-if="detailed && period.can_close" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-center text-sm text-primary-600 dark:text-primary-400">
          <i class="pi pi-play mr-2"></i>
          Ready to start period close
        </div>
      </div>
    </template>
  </PrimeCard>
</template>

<style scoped>
:deep(.p-progressbar .p-progressbar-value) {
  transition: width 0.3s ease-in-out;
}

:deep(.p-tag) {
  font-size: 0.75rem;
}

:deep(.p-card) {
  transition: all 0.3s ease;
}

:deep(.p-card:hover) {
  transform: translateY(-2px);
}
</style>