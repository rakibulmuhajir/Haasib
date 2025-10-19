<template>
  <div class="space-y-6">
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Schedules</p>
            <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">
              {{ statistics.total_schedules || 0 }}
            </p>
          </div>
          <i class="pi pi-calendar text-blue-600 dark:text-blue-400 text-2xl"></i>
        </div>
      </div>

      <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-green-600 dark:text-green-400">Active</p>
            <p class="text-2xl font-bold text-green-900 dark:text-green-100">
              {{ statistics.active_schedules || 0 }}
            </p>
          </div>
          <i class="pi pi-play text-green-600 dark:text-green-400 text-2xl"></i>
        </div>
      </div>

      <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Paused</p>
            <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">
              {{ statistics.paused_schedules || 0 }}
            </p>
          </div>
          <i class="pi pi-pause text-yellow-600 dark:text-yellow-400 text-2xl"></i>
        </div>
      </div>

      <div class="bg-purple-50 dark:bg-purple-900 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Running Today</p>
            <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">
              {{ statistics.running_today || 0 }}
            </p>
          </div>
          <i class="pi pi-clock text-purple-600 dark:text-purple-400 text-2xl"></i>
        </div>
      </div>
    </div>

    <!-- Charts and Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Frequency Distribution -->
      <Card>
        <template #header>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            Schedule Frequency Distribution
          </h3>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-for="(count, frequency) in statistics.frequency_distribution" :key="frequency" class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <Badge
                  :value="formatFrequency(frequency)"
                  :severity="getFrequencySeverity(frequency)"
                  size="small"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ count }} schedules</span>
              </div>
              <div class="flex items-center space-x-2">
                <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                  <div
                    class="h-2 rounded-full"
                    :class="getFrequencyColor(frequency)"
                    :style="{ width: getPercentage(count, statistics.total_schedules) + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-medium">{{ getPercentage(count, statistics.total_schedules) }}%</span>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Template Usage -->
      <Card>
        <template #header>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            Most Used Templates
          </h3>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-for="(template, index) in statistics.most_used_templates" :key="template.template_id" class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700">
                  <span class="text-sm font-medium">{{ index + 1 }}</span>
                </div>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ template.name }}</p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">{{ template.schedule_count }} schedules</p>
                </div>
              </div>
              <Badge
                :value="formatReportType(template.report_type)"
                severity="info"
                size="small"
              />
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Recent Activity -->
    <Card>
      <template #header>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          Recent Activity
        </h3>
      </template>
      <template #content>
        <div class="space-y-4">
          <div v-if="!statistics.recent_activity || statistics.recent_activity.length === 0" class="text-center py-8 text-gray-500">
            <i class="pi pi-history text-4xl mb-4"></i>
            <p>No recent activity to display</p>
          </div>
          
          <div v-else v-for="activity in statistics.recent_activity" :key="activity.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex items-center justify-center w-10 h-10 rounded-full"
                   :class="getActivityIconColor(activity.action)">
                <i :class="getActivityIcon(activity.action)" class="text-white"></i>
              </div>
              <div>
                <p class="font-medium text-gray-900 dark:text-white">{{ activity.description }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ activity.schedule_name }} • {{ formatRelativeTime(activity.created_at) }}
                </p>
              </div>
            </div>
            <Badge
              :value="formatStatus(activity.status)"
              :severity="getStatusSeverity(activity.status)"
              size="small"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Upcoming Runs -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            Upcoming Runs
          </h3>
          <Button
            icon="pi pi-external-link"
            label="View All"
            severity="secondary"
            size="small"
            @click="$emit('viewUpcoming')"
          />
        </div>
      </template>
      <template #content>
        <div class="space-y-4">
          <div v-if="!statistics.upcoming_runs || statistics.upcoming_runs.length === 0" class="text-center py-8 text-gray-500">
            <i class="pi pi-clock text-4xl mb-4"></i>
            <p>No upcoming runs scheduled</p>
          </div>
          
          <div v-else v-for="run in statistics.upcoming_runs" :key="run.schedule_id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900">
                <i class="pi pi-calendar text-blue-600 dark:text-blue-400"></i>
              </div>
              <div>
                <p class="font-medium text-gray-900 dark:text-white">{{ run.name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(run.next_run_at) }} • {{ getTimeUntil(run.next_run_at) }}
                </p>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <Badge
                :value="formatFrequency(run.frequency)"
                :severity="getFrequencySeverity(run.frequency)"
                size="small"
              />
              <Badge
                :value="formatStatus(run.status)"
                :severity="getStatusSeverity(run.status)"
              />
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
      <Button
        label="Close"
        severity="secondary"
        @click="$emit('close')"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  statistics: {
    type: Object,
    required: true
  }
})

defineEmits(['close', 'viewUpcoming'])

// Utility Functions
const formatFrequency = (frequency) => {
  const frequencyMap = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    custom: 'Custom'
  }
  return frequencyMap[frequency] || frequency
}

const getFrequencySeverity = (frequency) => {
  switch (frequency) {
    case 'daily': return 'success'
    case 'weekly': return 'info'
    case 'monthly': return 'warning'
    case 'quarterly': return 'danger'
    case 'yearly': return 'secondary'
    default: return 'info'
  }
}

const getFrequencyColor = (frequency) => {
  switch (frequency) {
    case 'daily': return 'bg-green-500'
    case 'weekly': return 'bg-blue-500'
    case 'monthly': return 'bg-yellow-500'
    case 'quarterly': return 'bg-red-500'
    case 'yearly': return 'bg-gray-500'
    default: return 'bg-purple-500'
  }
}

const formatStatus = (status) => {
  const statusMap = {
    active: 'Active',
    paused: 'Paused',
    running: 'Running',
    completed: 'Completed',
    failed: 'Failed'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'active': return 'success'
    case 'paused': return 'warning'
    case 'running': return 'info'
    case 'failed': return 'danger'
    default: return 'secondary'
  }
}

const formatReportType = (type) => {
  const typeMap = {
    income_statement: 'Income Statement',
    balance_sheet: 'Balance Sheet',
    cash_flow: 'Cash Flow',
    trial_balance: 'Trial Balance',
    kpi_dashboard: 'KPI Dashboard'
  }
  return typeMap[type] || type
}

const getActivityIcon = (action) => {
  switch (action) {
    case 'created': return 'pi pi-plus'
    case 'updated': return 'pi pi-pencil'
    case 'triggered': return 'pi pi-play'
    case 'paused': return 'pi pi-pause'
    case 'resumed': return 'pi pi-play'
    case 'deleted': return 'pi pi-trash'
    case 'completed': return 'pi pi-check'
    case 'failed': return 'pi pi-times'
    default: return 'pi pi-info'
  }
}

const getActivityIconColor = (action) => {
  switch (action) {
    case 'created': return 'bg-green-500'
    case 'updated': return 'bg-blue-500'
    case 'triggered': return 'bg-purple-500'
    case 'paused': return 'bg-yellow-500'
    case 'resumed': return 'bg-green-500'
    case 'deleted': return 'bg-red-500'
    case 'completed': return 'bg-green-500'
    case 'failed': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}

const formatRelativeTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return date.toLocaleDateString()
}

const getTimeUntil = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const now = new Date()
  const diff = date - now
  
  if (diff < 0) return 'Overdue'
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 60) return `In ${minutes}m`
  if (hours < 24) return `In ${hours}h`
  if (days < 7) return `In ${days}d`
  return `In ${Math.floor(days / 7)}w`
}

const getPercentage = (value, total) => {
  if (!total || total === 0) return 0
  return Math.round((value / total) * 100)
}
</script>