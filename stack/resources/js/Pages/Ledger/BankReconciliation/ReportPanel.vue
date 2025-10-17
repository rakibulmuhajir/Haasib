<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Timeline from 'primevue/timeline'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  reconciliation: Object,
  canViewReports: Boolean,
  canExportReports: Boolean,
})

const toast = useToast()

const loading = ref(false)
const showReportDialog = ref(false)
const selectedReportType = ref(null)
const selectedFormat = ref('json')
const reportData = ref(null)
const auditData = ref(null)
const varianceData = ref(null)
const metricsData = ref(null)

const reportTypes = [
  { type: 'summary', name: 'Reconciliation Summary', description: 'Complete overview of reconciliation status, matches, and adjustments' },
  { type: 'variance', name: 'Variance Analysis', description: 'Detailed analysis of variance and unmatched items' },
  { type: 'audit', name: 'Audit Trail', description: 'Complete audit history of all reconciliation activities' },
]

const formats = [
  { label: 'JSON', value: 'json' },
  { label: 'PDF', value: 'pdf' },
  { label: 'CSV', value: 'csv' },
]

// Computed properties
const statusVariant = computed(() => {
  const variants = {
    draft: 'secondary',
    in_progress: 'info',
    completed: 'success',
    locked: 'danger',
    reopened: 'warning'
  }
  return variants[props.reconciliation.status] || 'secondary'
})

const varianceStatusVariant = computed(() => {
  const variants = {
    balanced: 'success',
    positive: 'warning',
    negative: 'danger'
  }
  return variants[props.reconciliation.variance_status] || 'secondary'
})

// Methods
const openReportDialog = (reportType) => {
  selectedReportType.value = reportType
  showReportDialog.value = true
}

const generateReport = async () => {
  if (!selectedReportType.value) return
  
  loading.value = true
  
  try {
    const response = await axios.get(`/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reports/${selectedReportType.value}`, {
      params: { format: selectedFormat.value }
    })
    
    reportData.value = response.data.data
    
    toast.add({
      severity: 'success',
      summary: 'Report Generated',
      detail: `${reportTypes.find(r => r.type === selectedReportType.value)?.name} has been generated successfully`,
      life: 3000
    })
    
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to generate report',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

const exportReport = async () => {
  if (!selectedReportType.value) return
  
  loading.value = true
  
  try {
    const response = await axios.post(`/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reports/${selectedReportType.value}/export`, {
      format: selectedFormat.value
    })
    
    const { filename, download_url } = response.data
    
    // Download the file
    window.open(download_url, '_blank')
    
    toast.add({
      severity: 'success',
      summary: 'Export Started',
      detail: `Report exported as ${filename}`,
      life: 3000
    })
    
    showReportDialog.value = false
    
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Export Error',
      detail: error.response?.data?.message || 'Failed to export report',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

const loadAuditData = async () => {
  loading.value = true
  
  try {
    const response = await axios.get(`/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reports/audit`)
    auditData.value = response.data.audit_trail
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load audit data',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

const loadVarianceData = async () => {
  loading.value = true
  
  try {
    const response = await axios.get(`/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reports/variance`)
    varianceData.value = response.data.variance_analysis
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load variance data',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

const loadMetricsData = async () => {
  loading.value = true
  
  try {
    const response = await axios.get(`/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reports/metrics`)
    metricsData.value = response.data.metrics
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load metrics data',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

const formatEventIcon = (eventType) => {
  const icons = {
    'status_change': 'pi pi-flag',
    'match_created': 'pi pi-link',
    'adjustment_created': 'pi pi-calculator',
    'report_accessed': 'pi pi-file',
    'export_operation': 'pi pi-download',
    'failed_action': 'pi pi-exclamation-triangle',
  }
  return icons[eventType] || 'pi pi-info-circle'
}

const formatEventSeverity = (eventType) => {
  const severities = {
    'status_change': 'info',
    'match_created': 'success',
    'adjustment_created': 'warning',
    'report_accessed': 'info',
    'export_operation': 'info',
    'failed_action': 'danger',
  }
  return severities[eventType] || 'info'
}

// Load initial data
onMounted(() => {
  loadAuditData()
  loadVarianceData()
  loadMetricsData()
})
</script>

<template>
  <Head title="Reconciliation Reports" />

  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">
            Reconciliation Reports
          </h1>
          <p class="mt-1 text-sm text-gray-500">
            {{ reconciliation.statement.name }} • {{ reconciliation.statement.period }}
          </p>
        </div>
        
        <div class="flex items-center gap-2">
          <Button
            label="Refresh Data"
            icon="pi pi-refresh"
            @click="loadMetricsData"
            :loading="loading"
            severity="secondary"
            size="small"
          />
        </div>
      </div>

      <!-- Status Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-lg font-semibold capitalize">
                  {{ reconciliation.status.replace('_', ' ') }}
                </p>
              </div>
              <Tag :value="reconciliation.status" :severity="statusVariant" />
            </div>
          </template>
        </Card>

        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Variance</p>
                <p class="text-lg font-semibold">
                  {{ reconciliation.variance }}
                </p>
              </div>
              <Tag :value="reconciliation.variance_status" :severity="varianceStatusVariant" />
            </div>
          </template>
        </Card>

        <Card>
          <template #content>
            <div>
              <p class="text-sm text-gray-500">Progress</p>
              <p class="text-lg font-semibold">{{ reconciliation.percent_complete }}%</p>
              <ProgressBar :value="reconciliation.percent_complete" class="mt-2" />
            </div>
          </template>
        </Card>
      </div>

      <!-- Report Generation -->
      <Card>
        <template #title>
          Available Reports
        </template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="report in reportTypes"
              :key="report.type"
              class="border rounded-lg p-4 hover:border-blue-300 transition-colors cursor-pointer"
              @click="openReportDialog(report.type)"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h3 class="font-medium text-gray-900">{{ report.name }}</h3>
                  <p class="text-sm text-gray-500 mt-1">{{ report.description }}</p>
                </div>
                <i class="pi pi-file text-gray-400 mt-1"></i>
              </div>
              
              <div class="mt-3 flex gap-2">
                <span class="text-xs text-gray-500">Available formats:</span>
                <div class="flex gap-1">
                  <span class="text-xs bg-gray-100 px-2 py-1 rounded">JSON</span>
                  <span v-if="report.type !== 'audit'" class="text-xs bg-gray-100 px-2 py-1 rounded">PDF</span>
                  <span v-if="report.type === 'summary'" class="text-xs bg-gray-100 px-2 py-1 rounded">CSV</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Variance Analysis -->
      <Card v-if="varianceData">
        <template #title>
          Variance Analysis
        </template>
        <template #content>
          <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-500">Variance Amount</p>
                <p class="text-xl font-semibold" :class="varianceData.variance_status === 'balanced' ? 'text-green-600' : 'text-red-600'">
                  {{ varianceData.variance_formatted }}
                </p>
                <Tag 
                  :value="varianceData.variance_status" 
                  :severity="varianceStatusVariant"
                  class="mt-2"
                />
              </div>
              
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-500">Unmatched Items</p>
                <p class="text-xl font-semibold">
                  {{ varianceData.unmatched_items?.length || 0 }}
                </p>
                <p class="text-xs text-gray-400 mt-1">Statement lines</p>
              </div>
            </div>

            <!-- Recommendations -->
            <div v-if="varianceData.recommendations?.length">
              <h4 class="font-medium text-gray-900 mb-3">Recommendations</h4>
              <div class="space-y-2">
                <Message
                  v-for="rec in varianceData.recommendations"
                  :key="rec.type"
                  :severity="rec.priority === 'high' ? 'error' : rec.priority === 'medium' ? 'warn' : 'info'"
                  :closable="false"
                >
                  <div class="flex items-start gap-2">
                    <i :class="`pi ${rec.type === 'variance' ? 'pi-exclamation-triangle' : 'pi-info-circle'}`"></i>
                    <div>
                      <p class="font-medium">{{ rec.title }}</p>
                      <p class="text-sm">{{ rec.description }}</p>
                      <p class="text-xs mt-1 text-gray-600">{{ rec.action }}</p>
                    </div>
                  </div>
                </Message>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Audit Trail -->
      <Card v-if="auditData">
        <template #title>
          Audit Trail
        </template>
        <template #content>
          <div class="space-y-4">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
              <div class="bg-blue-50 p-3 rounded-lg">
                <p class="text-2xl font-bold text-blue-600">{{ auditData.summary.total_activities }}</p>
                <p class="text-sm text-gray-600">Total Activities</p>
              </div>
              <div class="bg-green-50 p-3 rounded-lg">
                <p class="text-2xl font-bold text-green-600">{{ auditData.summary.unique_users }}</p>
                <p class="text-sm text-gray-600">Unique Users</p>
              </div>
              <div class="bg-purple-50 p-3 rounded-lg">
                <p class="text-2xl font-bold text-purple-600">{{ auditData.status_changes?.length || 0 }}</p>
                <p class="text-sm text-gray-600">Status Changes</p>
              </div>
            </div>

            <!-- Status Changes Timeline -->
            <div v-if="auditData.status_changes?.length">
              <h4 class="font-medium text-gray-900 mb-3">Status Changes</h4>
              <Timeline :value="auditData.status_changes" layout="horizontal" class="customized-timeline">
                <template #marker="{ item }">
                  <span 
                    class="flex w-8 h-8 items-center justify-center rounded-full border-2 text-sm font-bold"
                    :class="{
                      'bg-blue-100 border-blue-500 text-blue-700': item.new_status === 'completed',
                      'bg-red-100 border-red-500 text-red-700': item.new_status === 'locked',
                      'bg-yellow-100 border-yellow-500 text-yellow-700': item.new_status === 'reopened',
                    }"
                  >
                    {{ item.new_status?.charAt(0)?.toUpperCase() }}
                  </span>
                </template>
                <template #content="{ item }">
                  <div class="text-sm">
                    <p class="font-medium">Status changed to <span class="capitalize">{{ item.new_status?.replace('_', ' ') }}</span></p>
                    <p class="text-gray-500">by {{ item.user }} • {{ new Date(item.timestamp).toLocaleString() }}</p>
                  </div>
                </template>
              </Timeline>
            </div>

            <!-- Recent Activities -->
            <div v-if="auditData.activities?.length">
              <h4 class="font-medium text-gray-900 mb-3">Recent Activities</h4>
              <DataTable 
                :value="auditData.activities.slice(0, 10)" 
                :paginator="false"
                class="p-datatable-sm"
              >
                <Column field="description" header="Activity">
                  <template #body="{ data }">
                    <div class="flex items-center gap-2">
                      <i :class="`pi ${formatEventIcon(data.event_type)} text-${formatEventSeverity(data.event_type)}-500`"></i>
                      <span>{{ data.description }}</span>
                    </div>
                  </template>
                </Column>
                <Column field="causer" header="User">
                  <template #body="{ data }">
                    {{ data.causer || 'System' }}
                  </template>
                </Column>
                <Column field="created_at" header="Time">
                  <template #body="{ data }">
                    {{ new Date(data.created_at).toLocaleString() }}
                  </template>
                </Column>
              </DataTable>
            </div>
          </div>
        </template>
      </Card>

      <!-- Real-time Metrics -->
      <Card v-if="metricsData">
        <template #title>
          Live Metrics
        </template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Progress Metrics -->
            <div class="space-y-4">
              <h4 class="font-medium text-gray-900">Progress</h4>
              
              <div class="space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-600">Completion</span>
                  <span class="font-medium">{{ metricsData.progress.percent_complete }}%</span>
                </div>
                <ProgressBar :value="metricsData.progress.percent_complete" />
                <p class="text-xs text-gray-500">
                  {{ metricsData.progress.matched_lines }} of {{ metricsData.progress.total_lines }} lines matched
                </p>
              </div>
            </div>

            <!-- Activity Metrics -->
            <div class="space-y-4">
              <h4 class="font-medium text-gray-900">Activity Summary</h4>
              
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Total Matches</span>
                  <span class="font-medium">{{ metricsData.activity.total_matches }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Auto-Matched</span>
                  <span class="font-medium">{{ metricsData.activity.auto_matches }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Manual Matches</span>
                  <span class="font-medium">{{ metricsData.activity.manual_matches }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Adjustments</span>
                  <span class="font-medium">{{ metricsData.activity.total_adjustments }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Status Actions -->
          <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 class="font-medium text-gray-900 mb-3">Available Actions</h4>
            <div class="flex flex-wrap gap-2">
              <Tag 
                v-if="metricsData.status.can_be_edited"
                value="Can Edit"
                severity="success"
              />
              <Tag 
                v-if="metricsData.status.can_be_completed"
                value="Can Complete"
                severity="info"
              />
              <Tag 
                v-if="metricsData.status.can_be_locked"
                value="Can Lock"
                severity="warning"
              />
              <Tag 
                v-if="metricsData.status.can_be_reopened"
                value="Can Reopen"
                severity="danger"
              />
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Report Generation Dialog -->
    <Dialog 
      v-model:visible="showReportDialog" 
      modal 
      header="Generate Report"
      :style="{ width: '500px' }"
    >
      <div class="space-y-4">
        <div v-if="selectedReportType">
          <h3 class="font-medium">
            {{ reportTypes.find(r => r.type === selectedReportType)?.name }}
          </h3>
          <p class="text-sm text-gray-500 mt-1">
            {{ reportTypes.find(r => r.type === selectedReportType)?.description }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Export Format
          </label>
          <Dropdown 
            v-model="selectedFormat" 
            :options="formats"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>

        <div v-if="reportData" class="bg-gray-50 p-4 rounded-lg max-h-60 overflow-y-auto">
          <h4 class="font-medium mb-2">Preview</h4>
          <pre class="text-xs text-gray-600">{{ JSON.stringify(reportData, null, 2) }}</pre>
        </div>
      </div>
      
      <div class="flex justify-end gap-2 mt-6">
        <Button 
          label="Cancel" 
          @click="showReportDialog = false" 
          severity="secondary"
          :disabled="loading"
        />
        <Button 
          label="Preview" 
          @click="generateReport"
          :loading="loading"
          severity="info"
        />
        <Button 
          label="Export" 
          @click="exportReport"
          :loading="loading"
          :disabled="!reportData || !canExportReports"
          severity="success"
        />
      </div>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.customized-timeline :deep(.p-timeline-event-marker) {
  border-width: 2px;
}

.customized-timeline :deep(.p-timeline-event-content) {
  padding-left: 1rem;
}
</style>