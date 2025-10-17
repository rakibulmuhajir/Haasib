<script setup lang="ts">
import { computed } from 'vue'
import PrimeCard from 'primevue/card'
import PrimeBadge from 'primevue/badge'
import PrimeTag from 'primevue/tag'

interface Deadline {
  period_id: string
  period_name: string
  deadline: string
  days_until_deadline: number
  status: string
  priority: 'high' | 'medium' | 'low'
}

interface DeadlinesAlertProps {
  deadlines: Deadline[]
  maxItems?: number
  compact?: boolean
}

const props = withDefaults(defineProps<DeadlinesAlertProps>(), {
  maxItems: 5,
  compact: false
})

const filteredDeadlines = computed(() => {
  return props.deadlines
    .sort((a, b) => a.days_until_deadline - b.days_until_deadline)
    .slice(0, props.maxItems)
})

const hasHighPriorityDeadlines = computed(() => 
  filteredDeadlines.value.some(d => d.priority === 'high')
)

const hasOverdueDeadlines = computed(() => 
  filteredDeadlines.value.some(d => d.days_until_deadline < 0)
)

const urgentDeadlines = computed(() => 
  filteredDeadlines.value.filter(d => d.days_until_deadline <= 3)
)

function getPriorityColor(priority: string): string {
  switch (priority) {
    case 'high': return 'danger'
    case 'medium': return 'warning'
    case 'low': return 'info'
    default: return 'secondary'
  }
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'closed': return 'success'
    case 'locked': return 'info'
    case 'awaiting_approval': return 'warning'
    case 'in_review': return 'primary'
    default: return 'secondary'
  }
}

function getDaysUntilDeadlineText(days: number): string {
  if (days === 0) return 'Due today'
  if (days === 1) return 'Due tomorrow'
  if (days < 0) return `${Math.abs(days)} days overdue`
  return `Due in ${days} days`
}

function getDeadlineColor(days: number): string {
  if (days < 0) return 'text-red-600 dark:text-red-400'
  if (days === 0) return 'text-red-500 dark:text-red-400'
  if (days === 1) return 'text-amber-600 dark:text-amber-400'
  if (days <= 3) return 'text-amber-500 dark:text-amber-400'
  if (days <= 7) return 'text-blue-600 dark:text-blue-400'
  return 'text-gray-600 dark:text-gray-400'
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString()
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString()
}
</script>

<template>
  <PrimeCard v-if="filteredDeadlines.length > 0">
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <i class="pi pi-clock mr-2 text-amber-500"></i>
          <h3 class="text-lg font-semibold">Upcoming Deadlines</h3>
        </div>
        <PrimeBadge 
          :value="filteredDeadlines.length" 
          :severity="hasHighPriorityDeadlines ? 'danger' : 'warning'"
        />
      </div>
    </template>

    <template #content>
      <!-- Alert Banner -->
      <div v-if="hasOverdueDeadlines" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="pi pi-exclamation-triangle text-red-600"></i>
          <span class="text-sm font-medium text-red-800 dark:text-red-200">
            {{ filteredDeadlines.filter(d => d.days_until_deadline < 0).length }} period(s) are overdue
          </span>
        </div>
      </div>

      <div v-else-if="urgentDeadlines.length > 0" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="pi pi-exclamation-triangle text-amber-600"></i>
          <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
            {{ urgentDeadlines.length }} period(s) due within 3 days
          </span>
        </div>
      </div>

      <!-- Deadlines List -->
      <div class="space-y-3">
        <div 
          v-for="deadline in filteredDeadlines" 
          :key="deadline.period_id"
          class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
          :class="{
            'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': deadline.days_until_deadline < 0,
            'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800': deadline.days_until_deadline >= 0 && deadline.days_until_deadline <= 3,
            'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': deadline.days_until_deadline > 3 && deadline.days_until_deadline <= 7
          }"
        >
          <div class="flex items-center space-x-3">
            <i 
              class="pi pi-calendar text-lg"
              :class="getDeadlineColor(deadline.days_until_deadline)"
            ></i>
            
            <div>
              <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ deadline.period_name }}
              </div>
              
              <div v-if="!compact" class="text-sm text-gray-600 dark:text-gray-400">
                Deadline: {{ formatDate(deadline.deadline) }}
              </div>
            </div>
          </div>

          <div class="flex items-center space-x-2">
            <div class="text-right">
              <div class="text-sm font-medium" :class="getDeadlineColor(deadline.days_until_deadline)">
                {{ getDaysUntilDeadlineText(deadline.days_until_deadline) }}
              </div>
              
              <PrimeTag 
                :value="deadline.status.replace('_', ' ')" 
                :severity="getStatusColor(deadline.status)"
                size="small"
                class="mt-1"
              />
            </div>
            
            <PrimeBadge 
              :value="deadline.priority" 
              :severity="getPriorityColor(deadline.priority)"
            />
          </div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div v-if="!compact" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <div class="text-lg font-semibold text-red-600">
              {{ filteredDeadlines.filter(d => d.days_until_deadline < 0).length }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Overdue</div>
          </div>
          
          <div>
            <div class="text-lg font-semibold text-amber-600">
              {{ filteredDeadlines.filter(d => d.days_until_deadline >= 0 && d.days_until_deadline <= 7).length }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Due Soon</div>
          </div>
          
          <div>
            <div class="text-lg font-semibold text-blue-600">
              {{ filteredDeadlines.filter(d => d.days_until_deadline > 7).length }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Upcoming</div>
          </div>
        </div>
      </div>

      <!-- Action Reminder -->
      <div v-if="hasOverdueDeadlines || urgentDeadlines.length > 0" class="mt-4">
        <div class="text-sm text-gray-600 dark:text-gray-400 text-center">
          <i class="pi pi-info-circle mr-1"></i>
          Prioritize overdue and urgent deadlines to ensure timely period closure
        </div>
      </div>
    </template>
  </PrimeCard>

  <!-- Empty State -->
  <PrimeCard v-else>
    <template #content>
      <div class="text-center py-6">
        <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
        <p class="text-green-700 dark:text-green-300 font-medium">
          All caught up!
        </p>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
          No upcoming period close deadlines
        </p>
      </div>
    </template>
  </PrimeCard>
</template>

<style scoped>
:deep(.p-tag) {
  font-size: 0.75rem;
}

:deep(.p-card) {
  transition: all 0.3s ease;
}

:deep(.p-card:hover) {
  transform: translateY(-1px);
}
</style>