<template>
  <div class="payment-batch-manager">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Payment Batches
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Import and manage payment receipt batches from CSV files or manual entries
        </p>
      </div>
      
      <div class="flex gap-3">
        <Button
          icon="pi pi-upload"
          label="New Batch"
          @click="showNewBatchDialog = true"
        />
        <Button
          icon="pi pi-refresh"
          label="Refresh"
          @click="refreshBatches"
          :loading="loading"
          severity="secondary"
        />
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg">
              <i class="pi pi-database text-blue-600 dark:text-blue-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Batches</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ batchStats.total_batches || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-green-100 dark:bg-green-900 rounded-lg">
              <i class="pi pi-check-circle text-green-600 dark:text-green-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ batchStats.completed_batches || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-lg">
              <i class="pi pi-spin pi-spinner text-orange-600 dark:text-orange-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Processing</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ batchStats.processing_batches || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900 rounded-lg">
              <i class="pi pi-times-circle text-red-600 dark:text-red-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ batchStats.failed_batches || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filters Section -->
    <Card class="mb-6">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="flex flex-col gap-2">
            <label for="status-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Status
            </label>
            <Dropdown
              id="status-filter"
              v-model="selectedStatus"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="All Statuses"
              class="w-full"
              showClear
              @change="applyFilters"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="source-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Source Type
            </label>
            <Dropdown
              id="source-filter"
              v-model="selectedSourceType"
              :options="sourceTypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="All Sources"
              class="w-full"
              showClear
              @change="applyFilters"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Date Range
            </label>
            <Calendar
              id="date-range"
              v-model="dateRange"
              selectionMode="range"
              :manualInput="false"
              dateFormat="yy-mm-dd"
              placeholder="Select date range"
              class="w-full"
              @change="applyFilters"
            />
          </div>

          <div class="flex items-end gap-2">
            <Button
              label="Apply"
              @click="applyFilters"
              :loading="loading"
              size="small"
            />
            <Button
              label="Clear"
              @click="clearFilters"
              severity="secondary"
              size="small"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Batches Table -->
    <Card>
      <template #title>
        <span class="flex items-center gap-2">
          <i class="pi pi-list"></i>
          Payment Batches
        </span>
      </template>
      
      <template #content>
        <DataTable
          :value="batches"
          :paginator="true"
          :rows="10"
          :loading="loading"
          :globalFilterFields="['batch_number', 'source_type']"
          v-model:filters="filters"
          filterDisplay="menu"
          :rowHover="true"
          dataKey="id"
          class="p-datatable-sm"
          @row-click="viewBatchDetails"
        >
          <!-- Batch Number Column -->
          <Column field="batch_number" header="Batch #" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ data.batch_number }}</span>
                <Tag
                  v-if="data.has_errors"
                  value="Errors"
                  severity="danger"
                  size="small"
                />
              </div>
            </template>
          </Column>

          <!-- Status Column -->
          <Column field="status" header="Status" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <Tag
                  :value="data.status_label"
                  :severity="getStatusSeverity(data.status)"
                  size="small"
                />
                <div v-if="data.progress_percentage < 100 && data.status === 'processing'" 
                     class="w-20 bg-gray-200 rounded-full h-2">
                  <div 
                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                    :style="{ width: data.progress_percentage + '%' }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>

          <!-- Source Type Column -->
          <Column field="source_type" header="Source" sortable>
            <template #body="{ data }">
              <Tag
                :value="getSourceTypeLabel(data.source_type)"
                :severity="getSourceTypeSeverity(data.source_type)"
                size="small"
              />
            </template>
          </Column>

          <!-- Receipt Count Column -->
          <Column field="receipt_count" header="Receipts" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.receipt_count }}</span>
            </template>
          </Column>

          <!-- Total Amount Column -->
          <Column field="total_amount" header="Total Amount" sortable>
            <template #body="{ data }">
              <span class="font-medium">
                {{ data.currency }}{{ formatCurrency(data.total_amount) }}
              </span>
            </template>
          </Column>

          <!-- Created At Column -->
          <Column field="created_at" header="Created" sortable>
            <template #body="{ data }">
              <span>{{ formatDate(data.created_at) }}</span>
            </template>
          </Column>

          <!-- Actions Column -->
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  text
                  rounded
                  v-tooltip="'View Details'"
                  @click="viewBatchDetails(data)"
                />
                
                <Button
                  v-if="data.status === 'failed'"
                  icon="pi pi-refresh"
                  size="small"
                  text
                  rounded
                  severity="warning"
                  v-tooltip="'Retry Batch'"
                  @click="retryBatch(data)"
                />

                <Button
                  v-if="data.status === 'completed'"
                  icon="pi pi-download"
                  size="small"
                  text
                  rounded
                  severity="secondary"
                  v-tooltip="'Download Results'"
                  @click="downloadBatchResults(data)"
                />

                <Button
                  v-if="data.isProcessing()"
                  icon="pi pi-clock"
                  size="small"
                  text
                  rounded
                  severity="info"
                  v-tooltip="'View Progress'"
                  @click="viewBatchProgress(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- New Batch Dialog -->
    <Dialog
      v-model:visible="showNewBatchDialog"
      modal
      header="Create New Payment Batch"
      :style="{ width: '60vw' }"
    >
      <form @submit.prevent="createBatch" class="space-y-4">
        <div class="flex flex-col gap-2">
          <label for="source-type" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Source Type *
          </label>
          <Dropdown
            id="source-type"
            v-model="newBatchForm.sourceType"
            :options="sourceTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Select source type"
            class="w-full"
            required
          />
        </div>

        <!-- CSV File Upload -->
        <div v-if="newBatchForm.sourceType === 'csv_import'" class="space-y-2">
          <label for="csv-file" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            CSV File *
          </label>
          <FileUpload
            id="csv-file"
            mode="basic"
            name="file"
            :auto="true"
            :chooseLabel="'Choose CSV File'"
            accept=".csv"
            @select="onFileSelect"
            @clear="onFileClear"
            class="w-full"
          />
          <small class="text-gray-500 dark:text-gray-400">
            Upload a CSV file with columns: customer_id, payment_method, amount, currency, payment_date
          </small>
        </div>

        <!-- Manual Entry -->
        <div v-else class="space-y-2">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Payment Entries
          </label>
          <div class="p-4 border border-dashed border-gray-300 rounded-lg text-center">
            <i class="pi pi-info-circle text-2xl text-gray-400 mb-2"></i>
            <p class="text-gray-500 dark:text-gray-400">
              Manual/bank-feed entries require JSON format or API input
            </p>
            <p class="text-sm text-gray-400 mt-1">
              Use the CLI command: <code>php artisan payment:batch:import manual --entries=entries.json</code>
            </p>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label for="batch-notes" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Notes
          </label>
          <Textarea
            id="batch-notes"
            v-model="newBatchForm.notes"
            rows="3"
            placeholder="Optional notes about this batch..."
            class="w-full"
          />
        </div>
      </form>

      <template #footer>
        <div class="flex justify-between">
          <Button
            label="Cancel"
            @click="showNewBatchDialog = false"
            severity="secondary"
          />
          <Button
            label="Create Batch"
            icon="pi pi-plus"
            @click="createBatch"
            :loading="creatingBatch"
            :disabled="!canCreateBatch"
          />
        </div>
      </template>
    </Dialog>

    <!-- Batch Details Dialog -->
    <Dialog
      v-model:visible="showDetailsDialog"
      modal
      header="Batch Details"
      :style="{ width: '80vw' }"
      :maximizable="true"
    >
      <div v-if="selectedBatch" class="space-y-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card>
            <template #title>Batch Information</template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Batch #:</span>
                  <span class="font-medium">{{ selectedBatch.batch_number }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Status:</span>
                  <Tag
                    :value="selectedBatch.status_label"
                    :severity="getStatusSeverity(selectedBatch.status)"
                  />
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Source:</span>
                  <span>{{ getSourceTypeLabel(selectedBatch.source_type) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Created By:</span>
                  <span>{{ selectedBatch.created_by || 'N/A' }}</span>
                </div>
              </div>
            </template>
          </Card>

          <Card>
            <template #title>Statistics</template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Receipt Count:</span>
                  <span class="font-medium">{{ selectedBatch.receipt_count }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Total Amount:</span>
                  <span class="font-medium">
                    {{ selectedBatch.currency }}{{ formatCurrency(selectedBatch.total_amount) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Created:</span>
                  <span>{{ formatDateTime(selectedBatch.created_at) }}</span>
                </div>
                <div v-if="selectedBatch.processed_at" class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Processed:</span>
                  <span>{{ formatDateTime(selectedBatch.processed_at) }}</span>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Progress Information -->
        <Card v-if="selectedBatch.isProcessing()">
          <template #title>Processing Progress</template>
          <template #content>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Progress</span>
                <span class="text-sm text-gray-500">{{ selectedBatch.progress_percentage }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3">
                <div 
                  class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                  :style="{ width: selectedBatch.progress_percentage + '%' }"
                ></div>
              </div>
              <div v-if="selectedBatch.estimated_completion" class="text-sm text-gray-500">
                Estimated completion: {{ formatDateTime(selectedBatch.estimated_completion) }}
              </div>
            </div>
          </template>
        </Card>

        <!-- Error Information -->
        <Card v-if="selectedBatch.hasFailed()">
          <template #title>Processing Errors</template>
          <template #content>
            <div class="space-y-3">
              <div>
                <span class="text-sm font-medium text-red-600">Error Type:</span>
                <span class="ml-2">{{ selectedBatch.error_type || 'Unknown' }}</span>
              </div>
              <div v-if="Object.keys(selectedBatch.error_details || {}).length > 0">
                <span class="text-sm font-medium text-red-600">Error Details:</span>
                <div class="mt-2 max-h-40 overflow-y-auto">
                  <div v-for="(error, key) in selectedBatch.error_details" :key="key" class="text-sm">
                    <span class="font-mono text-red-500">{{ key }}:</span>
                    <span class="ml-2 text-red-700">{{ error }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Notes -->
        <Card v-if="selectedBatch.notes">
          <template #title>Notes</template>
          <template #content>
            <p class="text-gray-700 dark:text-gray-300">{{ selectedBatch.notes }}</p>
          </template>
        </Card>

        <!-- Payments List -->
        <Card v-if="batchPayments.length > 0">
          <template #title>Associated Payments</template>
          <template #content>
            <DataTable
              :value="batchPayments"
              :paginator="true"
              :rows="10"
              dataKey="id"
              class="p-datatable-sm"
            >
              <Column field="payment_number" header="Payment #" />
              <Column field="amount" header="Amount">
                <template #body="{ data }">
                  <span>{{ data.currency }}{{ formatCurrency(data.amount) }}</span>
                </template>
              </Column>
              <Column field="payment_method" header="Method" />
              <Column field="payment_date" header="Date" />
              <Column field="status" header="Status">
                <template #body="{ data }">
                  <Tag
                    :value="data.status_label"
                    :severity="getPaymentStatusSeverity(data.status)"
                    size="small"
                  />
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>

      <template #footer>
        <div class="flex justify-between">
          <div>
            <Button
              v-if="selectedBatch?.status === 'failed'"
              label="Retry Batch"
              icon="pi pi-refresh"
              severity="warning"
              @click="retryBatch(selectedBatch)"
            />
          </div>
          <Button
            label="Close"
            @click="showDetailsDialog = false"
            severity="secondary"
          />
        </div>
      </template>
    </Dialog>

    <!-- Progress Dialog -->
    <Dialog
      v-model:visible="showProgressDialog"
      modal
      header="Batch Processing Progress"
      :style="{ width: '50vw' }"
      :closable="false"
    >
      <div v-if="progressBatch" class="space-y-4">
        <div class="text-center">
          <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="8" />
        </div>
        <div class="text-center">
          <p class="text-lg font-medium">Processing Batch {{ progressBatch.batch_number }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ progressBatch.progress_percentage }}% complete
          </p>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
          <div 
            class="bg-blue-600 h-3 rounded-full transition-all duration-500"
            :style="{ width: progressBatch.progress_percentage + '%' }"
          ></div>
        </div>
      </div>

      <template #footer>
        <Button
          label="Close"
          @click="showProgressDialog = false"
          severity="secondary"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { format } from 'date-fns'

// Composition
const toast = useToast()

// State
const loading = ref(false)
const batches = ref([])
const selectedBatch = ref(null)
const batchPayments = ref([])
const showNewBatchDialog = ref(false)
const showDetailsDialog = ref(false)
const showProgressDialog = ref(false)
const progressBatch = ref(null)

// Filters
const filters = ref({})
const selectedStatus = ref(null)
const selectedSourceType = ref(null)
const dateRange = ref(null)

// New Batch Form
const creatingBatch = ref(false)
const newBatchForm = reactive({
  sourceType: null,
  file: null,
  notes: ''
})

// Statistics
const batchStats = ref({
  total_batches: 0,
  completed_batches: 0,
  processing_batches: 0,
  failed_batches: 0
})

// Options
const statusOptions = [
  { label: 'All Statuses', value: null },
  { label: 'Pending', value: 'pending' },
  { label: 'Processing', value: 'processing' },
  { label: 'Completed', value: 'completed' },
  { label: 'Failed', value: 'failed' },
  { label: 'Archived', value: 'archived' }
]

const sourceTypeOptions = [
  { label: 'All Sources', value: null },
  { label: 'Manual Entry', value: 'manual' },
  { label: 'CSV Import', value: 'csv_import' },
  { label: 'Bank Feed', value: 'bank_feed' }
]

// Progress Polling
let progressInterval = null

// Computed
const canCreateBatch = computed(() => {
  return newBatchForm.sourceType && 
         (newBatchForm.sourceType !== 'csv_import' || newBatchForm.file)
})

// Methods
const loadBatches = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    
    if (selectedStatus.value) {
      params.append('status', selectedStatus.value)
    }
    
    if (selectedSourceType.value) {
      params.append('source_type', selectedSourceType.value)
    }
    
    if (dateRange.value && dateRange.value[0]) {
      params.append('date_from', dateRange.value[0])
      params.append('date_to', dateRange.value[1])
    }

    const response = await fetch(`/api/accounting/payment-batches?${params}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load batches')
    }

    const data = await response.json()
    batches.value = data.data || []
    
    // Update statistics
    updateBatchStatistics()

  } catch (error) {
    console.error('Error loading batches:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load batches',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const updateBatchStatistics = () => {
  const stats = {
    total_batches: batches.value.length,
    completed_batches: batches.value.filter(b => b.status === 'completed').length,
    processing_batches: batches.value.filter(b => b.status === 'processing').length,
    failed_batches: batches.value.filter(b => b.status === 'failed').length,
  }
  batchStats.value = stats
}

const applyFilters = () => {
  loadBatches()
}

const clearFilters = () => {
  selectedStatus.value = null
  selectedSourceType.value = null
  dateRange.value = null
  loadBatches()
}

const refreshBatches = () => {
  loadBatches()
}

const onFileSelect = (event) => {
  newBatchForm.file = event.files[0]
}

const onFileClear = () => {
  newBatchForm.file = null
}

const createBatch = async () => {
  if (!canCreateBatch.value) {
    toast.add({
      severity: 'warn',
      summary: 'Validation Error',
      detail: 'Please fill in all required fields',
      life: 3000
    })
    return
  }

  creatingBatch.value = true
  try {
    const formData = new FormData()
    formData.append('source_type', newBatchForm.sourceType)
    
    if (newBatchForm.sourceType === 'csv_import') {
      formData.append('file', newBatchForm.file)
    }
    
    formData.append('notes', newBatchForm.notes)

    const response = await fetch('/api/accounting/payment-batches', {
      method: 'POST',
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      },
      body: formData
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Failed to create batch')
    }

    const result = await response.json()
    
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Batch ${result.batch_number} created successfully`,
      life: 3000
    })

    showNewBatchDialog.value = false
    resetBatchForm()
    await loadBatches()

    // Start progress monitoring for CSV imports
    if (newBatchForm.sourceType === 'csv_import') {
      startProgressMonitoring(result.batch_id)
    }

  } catch (error) {
    console.error('Error creating batch:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message || 'Failed to create batch',
      life: 3000
    })
  } finally {
    creatingBatch.value = false
  }
}

const resetBatchForm = () => {
  newBatchForm.sourceType = null
  newBatchForm.file = null
  newBatchForm.notes = ''
}

const viewBatchDetails = async (batch) => {
  selectedBatch.value = batch
  
  // Load payments for this batch
  try {
    const response = await fetch(`/api/accounting/payment-batches/${batch.id}?include_payments=true`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (response.ok) {
      const data = await response.json()
      selectedBatch.value = data
      batchPayments.value = data.payments || []
    }
  } catch (error) {
    console.error('Error loading batch details:', error)
  }
  
  showDetailsDialog.value = true
}

const viewBatchProgress = (batch) => {
  progressBatch.value = batch
  showProgressDialog.value = true
  
  // Start progress monitoring
  startProgressMonitoring(batch.id)
}

const startProgressMonitoring = (batchId) => {
  if (progressInterval) {
    clearInterval(progressInterval)
  }

  progressInterval = setInterval(async () => {
    try {
      const response = await fetch(`/api/accounting/payment-batches/${batchId}`, {
        headers: {
          'X-Company-Id': localStorage.getItem('companyId') || '',
          'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
        }
      })

      if (response.ok) {
        const batchData = await response.json()
        
        if (progressBatch.value) {
          progressBatch.value = batchData
        }
        
        // Update batch in list if present
        const index = batches.value.findIndex(b => b.id === batchId)
        if (index !== -1) {
          batches.value[index] = batchData
        }
        
        // Stop monitoring if batch is no longer processing
        if (batchData.status !== 'processing') {
          clearInterval(progressInterval)
          progressInterval = null
          
          if (showProgressDialog.value) {
            showProgressDialog.value = false
          }
          
          toast.add({
            severity: batchData.status === 'completed' ? 'success' : 'error',
            summary: `Batch ${batchData.status_label}`,
            detail: `Batch ${batchData.batch_number} processing ${batchData.status_label}`,
            life: 5000
          })
        }
      }
    } catch (error) {
      console.error('Error monitoring batch progress:', error)
    }
  }, 3000) // Check every 3 seconds
}

const retryBatch = async (batch) => {
  try {
    const response = await fetch(`/api/accounting/payment-batches/${batch.id}/retry`, {
      method: 'POST',
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to retry batch')
    }

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Batch ${batch.batch_number} retry initiated`,
      life: 3000
    })

    await loadBatches()
    
    // Start progress monitoring
    startProgressMonitoring(batch.id)

  } catch (error) {
    console.error('Error retrying batch:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to retry batch',
      life: 3000
    })
  }
}

const downloadBatchResults = async (batch) => {
  try {
    const response = await fetch(`/api/accounting/payment-batches/${batch.id}/results`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to download results')
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `batch-${batch.batch_number}-results.csv`
    link.click()
    window.URL.revokeObjectURL(url)

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Batch results downloaded',
      life: 3000
    })

  } catch (error) {
    console.error('Error downloading results:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to download results',
      life: 3000
    })
  }
}

// Helper Methods
const getStatusSeverity = (status) => {
  const severities = {
    'pending': 'warning',
    'processing': 'info',
    'completed': 'success',
    'failed': 'danger',
    'archived': 'secondary'
  }
  return severities[status] || 'secondary'
}

const getSourceTypeLabel = (sourceType) => {
  const labels = {
    'manual': 'Manual',
    'csv_import': 'CSV Import',
    'bank_feed': 'Bank Feed'
  }
  return labels[sourceType] || sourceType
}

const getSourceTypeSeverity = (sourceType) => {
  const severities = {
    'manual': 'info',
    'csv_import' : 'success',
    'bank_feed': 'warning'
  }
  return severities[sourceType] || 'secondary'
}

const getPaymentStatusSeverity = (status) => {
  const severities = {
    'pending': 'warning',
    'completed': 'success',
    'failed': 'danger'
  }
  return severities[status] || 'secondary'
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount || 0)
}

const formatDate = (dateString) => {
  try {
    return format(new Date(dateString), 'MMM dd, yyyy')
  } catch {
    return dateString
  }
}

const formatDateTime = (dateString) => {
  try {
    return format(new Date(dateString), 'MMM dd, yyyy HH:mm')
  } catch {
    return dateString
  }
}

// Lifecycle
onMounted(() => {
  loadBatches()
})

onUnmounted(() => {
  if (progressInterval) {
    clearInterval(progressInterval)
  }
})
</script>

<style scoped>
.payment-batch-manager {
  @apply space-y-6;
}

/* Custom scrollbar styles */
.overflow-y-auto {
  scrollbar-width: thin;
  scrollbar-color: rgb(156 163 175) transparent;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background-color: rgb(156 163 175);
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background-color: rgb(107 114 128);
}
</style>