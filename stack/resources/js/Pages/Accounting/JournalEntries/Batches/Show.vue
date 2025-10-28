<template>
  <LayoutShell>
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Journal Entries"
      description="Manage and process journal entry batches"
      subDescription="Review, approve, and post accounting entries"
      :show-search="true"
      search-placeholder="Search journal entries..."
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <div class="main-content">
  <div class="batch-show-page">

    <!-- Batch Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {{ statistics.total_entries }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Entries</div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
              {{ statistics.draft_entries }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Draft</div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {{ statistics.approved_entries }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Approved</div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
              {{ statistics.posted_entries }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Posted</div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
              {{ formatCurrency(statistics.total_amount) }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Amount</div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">
              {{ averageAmount }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Avg per Entry</div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Batch Information -->
    <Card class="mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Batch Information
        </h3>
      </template>
      
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Batch ID
                </label>
                <div class="font-mono text-sm text-gray-900 dark:text-white">
                  {{ batch.id }}
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Status
                </label>
                <Tag
                  :value="batch.status"
                  :severity="getStatusSeverity(batch.status)"
                />
              </div>
              
              <div v-if="batch.description">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Description
                </label>
                <div class="text-gray-900 dark:text-white">
                  {{ batch.description }}
                </div>
              </div>
            </div>
          </div>
          
          <div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Created By
                </label>
                <div class="text-gray-900 dark:text-white">
                  {{ batch.created_by_name || 'Unknown User' }}
                </div>
              </div>
              
              <div v-if="batch.approved_at">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Approved At
                </label>
                <div class="text-gray-900 dark:text-white">
                  {{ formatDateTime(batch.approved_at) }}
                </div>
              </div>
              
              <div v-if="batch.posted_at">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Posted At
                </label>
                <div class="text-gray-900 dark:text-white">
                  {{ formatDateTime(batch.posted_at) }}
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Last Updated
                </label>
                <div class="text-gray-900 dark:text-white">
                  {{ formatDateTime(batch.updated_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Journal Entries -->
    <Card>
      <template #header>
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Journal Entries ({{ journalEntries.length }})
          </h3>
          <div class="flex gap-2">
            <Button
              icon="pi pi-filter"
              label="Filter"
              @click="showFilterDialog = true"
              severity="secondary"
              size="small"
            />
            <Button
              icon="pi pi-plus"
              label="Add Entry"
              @click="showAddEntryDialog = true"
              severity="primary"
              size="small"
              v-if="statistics?.can_edit"
            />
          </div>
        </div>
      </template>
      
      <template #content>
        <DataTable
          :value="journalEntries"
          :loading="loading"
          stripedRows
          scrollable
          scrollHeight="400px"
          :paginator="false"
        >
          <Column field="reference" header="Reference" style="min-width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-sm text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                {{ data.reference }}
              </span>
            </template>
          </Column>

          <Column field="description" header="Description" sortable style="min-width: 200px">
            <template #body="{ data }">
              <div>{{ data.description }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400" v-if="data.type">
                Type: {{ data.type }}
              </div>
            </template>
          </Column>

          <Column field="date" header="Date" sortable style="min-width: 100px">
            <template #body="{ data }">
              <span class="text-sm">{{ formatDate(data.date) }}</span>
            </template>
          </Column>

          <Column field="status" header="Status" sortable style="min-width: 100px">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="getStatusSeverity(data.status)"
                class="text-xs"
              />
            </template>
          </Column>

          <Column field="total_amount" header="Amount" style="min-width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-sm">
                {{ formatCurrency(getEntryTotal(data)) }}
              </span>
            </template>
          </Column>

          <Column header="Balance" style="min-width: 100px">
            <template #body="{ data }">
              <span
                class="font-mono text-sm"
                :class="isEntryBalanced(data) ? 'text-green-600' : 'text-red-600'"
              >
                {{ isEntryBalanced(data) ? 'Balanced' : 'Unbalanced' }}
              </span>
            </template>
          </Column>

          <Column header="Actions" style="min-width: 120px">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  severity="secondary"
                  @click="viewEntry(data)"
                  v-tooltip="'View Entry'"
                />
                
                <Button
                  v-if="data.status === 'draft' && statistics?.can_edit"
                  icon="pi pi-pencil"
                  size="small"
                  severity="secondary"
                  @click="editEntry(data)"
                  v-tooltip="'Edit Entry'"
                />
                
                <Button
                  v-if="statistics?.can_edit"
                  icon="pi pi-times"
                  size="small"
                  severity="danger"
                  @click="removeEntry(data)"
                  v-tooltip="'Remove from Batch'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Toast -->
    <Toast />
  </div>
    </div>

    <!-- Right Column - Quick Links -->
    <div class="sidebar-content">
      <QuickLinks 
        :links="quickLinks" 
        title="Batch Actions"
      />
    </div>
  </div>
  </LayoutShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'

// PrimeVue Components
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'

const props = defineProps({
  batch: Object,
  statistics: Object,
  journalEntries: Array,
})

const toast = useToast()
const { actions } = usePageActions()

// Define page actions
const pageActions = [
  {
    key: 'back',
    label: 'Back to Batches',
    icon: 'pi pi-arrow-left',
    severity: 'secondary',
    action: () => router.visit(route('journal-batches.index'))
  },
  {
    key: 'edit',
    label: 'Edit Batch',
    icon: 'pi pi-pencil',
    severity: 'secondary',
    disabled: !statistics?.can_edit,
    action: () => editBatch()
  },
  {
    key: 'approve',
    label: 'Approve Batch',
    icon: 'pi pi-check',
    severity: 'success',
    disabled: !statistics?.can_approve,
    action: () => approveBatch()
  },
  {
    key: 'post',
    label: 'Post Batch',
    icon: 'pi pi-upload',
    severity: 'primary',
    disabled: !statistics?.can_post,
    action: () => postBatch()
  }
]

// Define quick links for batch details
const quickLinks = [
  {
    label: 'Journal Batches',
    url: '/accounting/journal-entries/batches',
    icon: 'pi pi-folder'
  },
  {
    label: 'Trial Balance',
    url: '/accounting/journal-entries/trial-balance',
    icon: 'pi pi-calculator'
  },
  {
    label: 'New Journal Entry',
    url: '/accounting/journal-entries/create',
    icon: 'pi pi-plus'
  },
  {
    label: 'Bank Reconciliation',
    url: '/ledger/bank-reconciliation',
    icon: 'pi pi-bank'
  }
]

// Set page actions
actions.value = pageActions

const loading = ref(false)
const approving = ref(false)
const posting = ref(false)
const deleting = ref(false)
const showFilterDialog = ref(false)
const showAddEntryDialog = ref(false)

// Computed Properties
const averageAmount = computed(() => {
  if (!props.statistics.total_amount || !props.statistics.total_entries) {
    return 0
  }
  return props.statistics.total_amount / props.statistics.total_entries
})

// Methods
const getStatusSeverity = (status) => {
  const severityMap = {
    'draft': 'secondary',
    'pending_approval': 'warning',
    'approved': 'info',
    'posted': 'success',
    'void': 'danger',
  }
  return severityMap[status] || 'secondary'
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}

const getEntryTotal = (entry) => {
  if (!entry.transactions || !entry.transactions.length) {
    return 0
  }
  
  return entry.transactions
    .filter(t => t.debit_credit === 'debit')
    .reduce((sum, t) => sum + parseFloat(t.amount), 0)
}

const isEntryBalanced = (entry) => {
  if (!entry.transactions || !entry.transactions.length) {
    return false
  }
  
  const debits = entry.transactions
    .filter(t => t.debit_credit === 'debit')
    .reduce((sum, t) => sum + parseFloat(t.amount), 0)
    
  const credits = entry.transactions
    .filter(t => t.debit_credit === 'credit')
    .reduce((sum, t) => sum + parseFloat(t.amount), 0)
  
  return Math.abs(debits - credits) < 0.01
}

const editBatch = () => {
  router.get(route('journal-batches.edit', props.batch.id))
}

const approveBatch = async () => {
  if (!confirm('Are you sure you want to approve this batch? This will approve all draft entries in the batch.')) {
    return
  }
  
  approving.value = true
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${props.batch.id}/approve`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch approved successfully',
        life: 3000,
      })
      
      router.reload({ only: ['batch', 'statistics', 'journalEntries'] })
    } else {
      throw new Error(result.message || 'Failed to approve batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  } finally {
    approving.value = false
  }
}

const postBatch = async () => {
  if (!confirm('Are you sure you want to post this batch? This will post all journal entries in the batch and update the ledger.')) {
    return
  }
  
  posting.value = true
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${props.batch.id}/post`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch posted successfully',
        life: 3000,
      })
      
      router.reload({ only: ['batch', 'statistics', 'journalEntries'] })
    } else {
      throw new Error(result.message || 'Failed to post batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  } finally {
    posting.value = false
  }
}

const deleteBatch = async () => {
  if (!confirm('Are you sure you want to delete this batch? This action cannot be undone and will remove the batch association from all entries.')) {
    return
  }
  
  deleting.value = true
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${props.batch.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch deleted successfully',
        life: 3000,
      })
      
      router.visit(route('journal-batches.index'))
    } else {
      throw new Error(result.message || 'Failed to delete batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  } finally {
    deleting.value = false
  }
}

const viewEntry = (entry) => {
  router.get(route('journal-entries.show', entry.id))
}

const editEntry = (entry) => {
  router.get(route('journal-entries.edit', entry.id))
}

const removeEntry = async (entry) => {
  if (!confirm(`Are you sure you want to remove entry "${entry.reference}" from this batch?`)) {
    return
  }
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${props.batch.id}/remove-entries`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
      body: JSON.stringify({
        journal_entry_ids: [entry.id],
      }),
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Entry removed from batch',
        life: 3000,
      })
      
      router.reload({ only: ['batch', 'statistics', 'journalEntries'] })
    } else {
      throw new Error(result.message || 'Failed to remove entry from batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  }
}

// Lifecycle
onMounted(() => {
  // Initialize any required data
})
</script>

