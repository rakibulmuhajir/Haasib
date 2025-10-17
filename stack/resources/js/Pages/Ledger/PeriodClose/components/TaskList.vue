<script setup lang="ts">
import { computed } from 'vue'
import PrimeCard from 'primevue/card'
import PrimeProgressBar from 'primevue/progressbar'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'

interface Task {
  id: string
  code: string
  title: string
  category: string
  sequence: number
  status: 'pending' | 'in_progress' | 'completed' | 'blocked' | 'waived'
  is_required: boolean
  notes?: string
  completed_by?: string
  completed_at?: string
  started_at?: string
  started_by?: string
}

interface TaskListProps {
  tasks: Task[]
  showProgress?: boolean
  compact?: boolean
  filterStatus?: string[]
}

const props = withDefaults(defineProps<TaskListProps>(), {
  showProgress: true,
  compact: false,
  filterStatus: () => []
})

const filteredTasks = computed(() => {
  if (props.filterStatus.length === 0) return props.tasks
  return props.tasks.filter(task => props.filterStatus.includes(task.status))
})

const tasksByCategory = computed(() => {
  const grouped: Record<string, Task[]> = {}
  filteredTasks.value.forEach(task => {
    if (!grouped[task.category]) {
      grouped[task.category] = []
    }
    grouped[task.category].push(task)
  })
  return grouped
})

const completedTasks = computed(() => 
  filteredTasks.value.filter(task => task.status === 'completed').length
)

const totalTasks = computed(() => filteredTasks.value.length)

const completionPercentage = computed(() => {
  if (totalTasks.value === 0) return 0
  return Math.round((completedTasks.value / totalTasks.value) * 100)
})

const requiredTasksCompleted = computed(() => {
  const requiredTasks = filteredTasks.value.filter(task => task.is_required)
  const completedRequired = requiredTasks.filter(task => task.status === 'completed')
  return {
    completed: completedRequired.length,
    total: requiredTasks.length,
    percentage: requiredTasks.length > 0 
      ? Math.round((completedRequired.length / requiredTasks.length) * 100) 
      : 0
  }
})

function getStatusColor(status: string): string {
  switch (status) {
    case 'completed': return 'success'
    case 'in_progress': return 'primary'
    case 'blocked': return 'danger'
    case 'waived': return 'warning'
    default: return 'secondary'
  }
}

function getStatusIcon(status: string): string {
  switch (status) {
    case 'completed': return 'pi pi-check-circle'
    case 'in_progress': return 'pi pi-clock'
    case 'blocked': return 'pi pi-times-circle'
    case 'waived': return 'pi pi-forward'
    default: return 'pi pi-circle'
  }
}

function formatDate(dateString?: string): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString()
}

function formatDateTime(dateString?: string): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

function getCategoryTitle(category: string): string {
  switch (category) {
    case 'trial_balance': return 'Trial Balance'
    case 'subledger': return 'Subledger Reconciliation'
    case 'compliance': return 'Compliance & Control'
    case 'reporting': return 'Reporting & Analysis'
    case 'adjustments': return 'Adjustments & Corrections'
    default: return category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
  }
}
</script>

<template>
  <PrimeCard>
    <template #header v-if="!compact">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Task Checklist</h3>
        <div class="flex items-center space-x-2">
          <PrimeBadge 
            :value="`${completedTasks}/${totalTasks}`" 
            severity="info"
          />
          <PrimeTag 
            :value="`${completionPercentage}%`" 
            :severity="completionPercentage === 100 ? 'success' : 'primary'"
          />
        </div>
      </div>
    </template>

    <template #content>
      <!-- Progress Overview -->
      <div v-if="showProgress && !compact" class="mb-6">
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Overall Progress
          </span>
          <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ completionPercentage }}% Complete
          </span>
        </div>
        <PrimeProgressBar 
          :value="completionPercentage" 
          :showValue="false"
          class="mb-3"
        />
        
        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
          <span>{{ completedTasks }} of {{ totalTasks }} tasks completed</span>
          <span>{{ requiredTasksCompleted.completed }} of {{ requiredTasksCompleted.total }} required tasks</span>
        </div>
      </div>

      <!-- Tasks by Category -->
      <div class="space-y-6">
        <div v-for="(tasks, category) in tasksByCategory" :key="category" class="space-y-3">
          <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 flex items-center">
            <i class="pi pi-folder mr-2 text-gray-500"></i>
            {{ getCategoryTitle(category) }}
            <PrimeBadge 
              :value="tasks.length" 
              severity="secondary" 
              size="small"
              class="ml-2"
            />
          </h4>

          <div class="space-y-2">
            <div 
              v-for="task in tasks.sort((a, b) => a.sequence - b.sequence)" 
              :key="task.id"
              class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
              :class="{
                'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': task.status === 'completed',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': task.status === 'in_progress',
                'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': task.status === 'blocked',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800': task.status === 'waived'
              }"
            >
              <div class="flex items-center space-x-3">
                <i 
                  :class="[
                    getStatusIcon(task.status),
                    'text-lg',
                    {
                      'text-green-600': task.status === 'completed',
                      'text-blue-600': task.status === 'in_progress',
                      'text-red-600': task.status === 'blocked',
                      'text-amber-600': task.status === 'waived',
                      'text-gray-400': task.status === 'pending'
                    }
                  ]"
                ></i>
                
                <div>
                  <div class="flex items-center space-x-2">
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ task.title }}
                    </span>
                    <PrimeTag 
                      v-if="task.is_required" 
                      value="Required" 
                      severity="danger" 
                      size="small"
                    />
                  </div>
                  
                  <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ task.code }} • {{ getCategoryTitle(category) }}
                  </div>
                  
                  <div v-if="task.notes" class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    {{ task.notes }}
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-2">
                <PrimeTag 
                  :value="task.status.replace('_', ' ')" 
                  :severity="getStatusColor(task.status)"
                  size="small"
                />
                
                <div v-if="task.status === 'completed'" class="text-right text-xs text-gray-500 dark:text-gray-500">
                  <div>{{ formatDate(task.completed_at) }}</div>
                  <div v-if="task.completed_by">by {{ task.completed_by }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredTasks.length === 0" class="text-center py-8">
        <i class="pi pi-check-circle text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
        <p class="text-gray-500 dark:text-gray-400">
          No tasks found for the selected criteria
        </p>
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
</style>