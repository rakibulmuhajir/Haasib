<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'
import Card from 'primevue/card'

const page = usePage()
const filters = ref({
  status: '',
  date_from: '',
  date_to: ''
})

// Permissions
const canView = computed(() => 
  page.props.auth.permissions?.['ledger.view'] ?? false
)
const canCreate = computed(() => 
  page.props.auth.permissions?.['ledger.create'] ?? false
)

const statusOptions = [
  { label: 'All', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Posted', value: 'posted' },
  { label: 'Void', value: 'void' }
]

const getStatusBadge = (status: string) => {
  const variants = {
    draft: 'info',
    posted: 'success',
    void: 'danger'
  }
  
  return {
    severity: variants[status] || 'secondary',
    value: status.charAt(0).toUpperCase() + status.slice(1)
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMM dd, yyyy')
}
</script>

<template>
  <LayoutShell>
    <template #sidebar>
      <!-- Sidebar content will be handled by the layout -->
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between">
        <Breadcrumb :items="[{ label: 'Ledger' }]" />
        
        <div v-if="canCreate" class="flex items-center gap-4">
          <Link :href="route('ledger.create')">
            <Button 
              label="New Journal Entry" 
              icon="plus" 
              size="small"
            />
          </Link>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Journal Entries
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            View and manage your accounting journal entries
          </p>
        </div>
      </div>

      <!-- Filters -->
      <Card>
        <template #title>Filters</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Status
              </label>
              <Select
                v-model="filters.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                class="w-full"
                placeholder="Select status"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Date From
              </label>
              <Calendar
                v-model="filters.date_from"
                dateFormat="yy-mm-dd"
                class="w-full"
                placeholder="From date"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Date To
              </label>
              <Calendar
                v-model="filters.date_to"
                dateFormat="yy-mm-dd"
                class="w-full"
                placeholder="To date"
              />
            </div>
            
            <div class="flex items-end">
              <Link :href="route('ledger.index', filters)" preserve-state>
                <Button label="Apply Filters" class="w-full" />
              </Link>
            </div>
          </div>
        </template>
      </Card>

      <!-- Journal Entries Table -->
      <Card>
        <template #content>
          <DataTable 
            :value="$page.props.entries.data" 
            stripedRows
            responsiveLayout="scroll"
          >
            <Column field="reference" header="Reference" style="width: 120px">
              <template #body="{ data }">
                <span v-if="data.reference" class="font-mono text-sm">
                  {{ data.reference }}
                </span>
                <span v-else class="text-gray-400">
                  —
                </span>
              </template>
            </Column>
            
            <Column field="date" header="Date" style="width: 120px">
              <template #body="{ data }">
                {{ formatDate(data.date) }}
              </template>
            </Column>
            
            <Column field="description" header="Description">
              <template #body="{ data }">
                <div class="max-w-md">
                  <div class="font-medium">{{ data.description }}</div>
                  <div class="text-sm text-gray-500">
                    {{ data.journal_lines?.length || 0 }} lines
                  </div>
                </div>
              </template>
            </Column>
            
            <Column field="total_debit" header="Total Debit" style="width: 120px">
              <template #body="{ data }">
                <span class="font-medium">
                  {{ formatCurrency(data.total_debit) }}
                </span>
              </template>
            </Column>
            
            <Column field="total_credit" header="Total Credit" style="width: 120px">
              <template #body="{ data }">
                <span class="font-medium">
                  {{ formatCurrency(data.total_credit) }}
                </span>
              </template>
            </Column>
            
            <Column field="status" header="Status" style="width: 100px">
              <template #body="{ data }">
                <Badge 
                  :severity="getStatusBadge(data.status).severity"
                  :value="getStatusBadge(data.status).value"
                />
              </template>
            </Column>
            
            <Column field="actions" header="Actions" style="width: 120px">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <Link :href="route('ledger.show', data.id)">
                    <Button
                      text
                      icon="eye"
                      size="small"
                      v-tooltip.top="'View details'"
                    />
                  </Link>
                </div>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>
  </LayoutShell>
</template>

<style scoped>
:deep(.p-datatable-wrapper) {
  border-radius: 0.5rem;
  overflow: hidden;
}

:deep(.p-datatable-thead > tr > th) {
  background-color: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
  color: #374151;
}

:deep(.p-datatable-tbody > tr) {
  border-bottom: 1px solid #f3f4f6;
}

:deep(.p-datatable-tbody > tr:hover) {
  background-color: #f8fafc;
}
</style>