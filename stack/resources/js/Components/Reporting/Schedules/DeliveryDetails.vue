<template>
  <div class="space-y-6">
    <!-- Delivery Header -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Delivery ID</h3>
          <p class="font-mono text-sm">{{ delivery.delivery_id }}</p>
        </div>
        
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
          <Badge
            :value="formatStatus(delivery.status)"
            :severity="getStatusSeverity(delivery.status)"
          />
        </div>
        
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Channel</h3>
          <Badge
            :value="formatChannel(delivery.channel)"
            :severity="getChannelSeverity(delivery.channel)"
            size="small"
          />
        </div>
        
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Report</h3>
          <p class="font-medium">{{ delivery.report_name || 'Unknown Report' }}</p>
          <p class="text-sm text-gray-500">{{ delivery.report_type || 'Unknown Type' }}</p>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Delivery Timeline
      </h3>
      
      <div class="relative">
        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-300 dark:bg-gray-600"></div>
        
        <div class="space-y-4">
          <!-- Created -->
          <div class="flex items-start space-x-4">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 z-10">
              <i class="pi pi-plus text-blue-600 dark:text-blue-400 text-sm"></i>
            </div>
            <div class="flex-1">
              <div class="bg-white dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="font-medium text-gray-900 dark:text-white">Delivery Created</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(delivery.created_at) }} • {{ getRelativeTime(delivery.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Attempts -->
          <div v-if="delivery.attempt_count > 1" class="space-y-2">
            <div class="flex items-start space-x-4">
              <div class="flex items-center justify-center w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900 z-10">
                <i class="pi pi-refresh text-yellow-600 dark:text-yellow-400 text-sm"></i>
              </div>
              <div class="flex-1">
                <p class="font-medium text-gray-900 dark:text-white">Delivery Attempts</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ delivery.attempt_count }} attempts made
                </p>
              </div>
            </div>
          </div>

          <!-- Last Attempt -->
          <div v-if="delivery.last_attempt_at" class="flex items-start space-x-4">
            <div class="flex items-center justify-center w-8 h-8 rounded-full"
                 :class="delivery.status === 'sent' ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900'">
              <i :class="delivery.status === 'sent' ? 'pi pi-check text-green-600 dark:text-green-400' : 'pi pi-times text-red-600 dark:text-red-400'" 
                 class="text-sm"></i>
            </div>
            <div class="flex-1">
              <div class="bg-white dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="font-medium text-gray-900 dark:text-white">
                  {{ delivery.status === 'sent' ? 'Delivery Successful' : 'Delivery Failed' }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(delivery.last_attempt_at) }} • {{ getRelativeTime(delivery.last_attempt_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Next Retry -->
          <div v-if="delivery.status === 'failed' && delivery.next_retry_at" class="flex items-start space-x-4">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 z-10">
              <i class="pi pi-clock text-purple-600 dark:text-purple-400 text-sm"></i>
            </div>
            <div class="flex-1">
              <div class="bg-white dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="font-medium text-gray-900 dark:text-white">Next Retry Scheduled</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(delivery.next_retry_at) }} • {{ getTimeUntil(delivery.next_retry_at) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Target Configuration -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Target Configuration
      </h3>
      
      <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="space-y-4">
          <!-- Email Target -->
          <div v-if="delivery.channel === 'email' && parsedTarget" class="space-y-3">
            <div v-if="parsedTarget.recipients && parsedTarget.recipients.length > 0">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Recipients</h4>
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="recipient in parsedTarget.recipients"
                  :key="recipient"
                  :value="recipient"
                  severity="info"
                />
              </div>
            </div>
            
            <div v-if="parsedTarget.subject">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Subject</h4>
              <p class="text-gray-900 dark:text-white">{{ parsedTarget.subject }}</p>
            </div>
            
            <div v-if="parsedTarget.message">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Message</h4>
              <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ parsedTarget.message }}</p>
            </div>
            
            <div v-if="parsedTarget.include_download_link !== undefined">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Download Link</h4>
              <Badge
                :value="parsedTarget.include_download_link ? 'Included' : 'Not Included'"
                :severity="parsedTarget.include_download_link ? 'success' : 'secondary'"
              />
            </div>
          </div>

          <!-- SFTP Target -->
          <div v-else-if="delivery.channel === 'sftp' && parsedTarget" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="parsedTarget.host">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Host</h4>
                <p class="text-gray-900 dark:text-white font-mono">{{ parsedTarget.host }}:{{ parsedTarget.port || 22 }}</p>
              </div>
              
              <div v-if="parsedTarget.username">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Username</h4>
                <p class="text-gray-900 dark:text-white font-mono">{{ parsedTarget.username }}</p>
              </div>
              
              <div v-if="parsedTarget.path">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Remote Path</h4>
                <p class="text-gray-900 dark:text-white font-mono">{{ parsedTarget.path }}</p>
              </div>
              
              <div v-if="parsedTarget.filename">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Filename</h4>
                <p class="text-gray-900 dark:text-white font-mono">{{ parsedTarget.filename }}</p>
              </div>
            </div>
          </div>

          <!-- Webhook Target -->
          <div v-else-if="delivery.channel === 'webhook' && parsedTarget" class="space-y-3">
            <div v-if="parsedTarget.url">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Webhook URL</h4>
              <p class="text-gray-900 dark:text-white font-mono text-sm break-all">{{ parsedTarget.url }}</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div v-if="parsedTarget.method">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Method</h4>
                <Badge
                  :value="parsedTarget.method"
                  severity="info"
                />
              </div>
              
              <div v-if="parsedTarget.timeout">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Timeout</h4>
                <p class="text-gray-900 dark:text-white">{{ parsedTarget.timeout }}s</p>
              </div>
              
              <div v-if="parsedTarget.retry_count">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Retry Count</h4>
                <p class="text-gray-900 dark:text-white">{{ parsedTarget.retry_count }}</p>
              </div>
            </div>
            
            <div v-if="parsedTarget.headers">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Headers</h4>
              <div class="bg-white dark:bg-gray-900 rounded p-3">
                <pre class="text-sm">{{ JSON.stringify(parsedTarget.headers, null, 2) }}</pre>
              </div>
            </div>
            
            <div v-if="parsedTarget.payload">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Payload</h4>
              <div class="bg-white dark:bg-gray-900 rounded p-3">
                <pre class="text-sm">{{ JSON.stringify(parsedTarget.payload, null, 2) }}</pre>
              </div>
            </div>
          </div>

          <!-- In-App Target -->
          <div v-else-if="delivery.channel === 'in_app' && parsedTarget" class="space-y-3">
            <div v-if="parsedTarget.notification_type">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Notification Type</h4>
              <Badge
                :value="parsedTarget.notification_type"
                :severity="getNotificationSeverity(parsedTarget.notification_type)"
              />
            </div>
            
            <div v-if="parsedTarget.message">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Message</h4>
              <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ parsedTarget.message }}</p>
            </div>
          </div>

          <!-- Raw Target -->
          <div v-else>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Raw Target Data</h4>
            <div class="bg-white dark:bg-gray-900 rounded p-3">
              <pre class="text-sm whitespace-pre-wrap">{{ formatTarget(delivery.target) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Information -->
    <div v-if="delivery.status === 'failed' && delivery.failure_reason">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Error Information
      </h3>
      
      <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
          <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 mt-1"></i>
          <div>
            <h4 class="font-medium text-red-800 dark:text-red-200 mb-2">Failure Reason</h4>
            <p class="text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ delivery.failure_reason }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
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
  delivery: {
    type: Object,
    required: true
  }
})

defineEmits(['close'])

// Computed
const parsedTarget = computed(() => {
  if (!props.delivery.target) return null
  
  try {
    if (typeof props.delivery.target === 'string') {
      return JSON.parse(props.delivery.target)
    }
    return props.delivery.target
  } catch {
    return null
  }
})

// Utility Functions
const formatStatus = (status) => {
  const statusMap = {
    sent: 'Sent',
    failed: 'Failed',
    pending: 'Pending'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'sent': return 'success'
    case 'failed': return 'danger'
    case 'pending': return 'warning'
    default: return 'secondary'
  }
}

const formatChannel = (channel) => {
  const channelMap = {
    email: 'Email',
    sftp: 'SFTP',
    webhook: 'Webhook',
    in_app: 'In-App'
  }
  return channelMap[channel] || channel
}

const getChannelSeverity = (channel) => {
  switch (channel) {
    case 'email': return 'info'
    case 'sftp': return 'warning'
    case 'webhook': return 'success'
    case 'in_app': return 'secondary'
    default: return 'info'
  }
}

const getNotificationSeverity = (type) => {
  switch (type) {
    case 'success': return 'success'
    case 'warning': return 'warning'
    case 'error': return 'danger'
    default: return 'info'
  }
}

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}

const getRelativeTime = (dateString) => {
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

const formatTarget = (target) => {
  if (!target) return 'No target configured'
  
  try {
    if (typeof target === 'string') {
      return JSON.stringify(JSON.parse(target), null, 2)
    }
    return JSON.stringify(target, null, 2)
  } catch {
    return target
  }
}
</script>