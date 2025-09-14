<template>
  <LayoutShell :title="pageMeta.title">
    <template #title>
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
        <div class="flex items-center gap-3">
          <Button 
            :label="'Export Customers'" 
            icon="pi pi-download" 
            outlined 
            size="small"
            @click="exportCustomers"
          />
          <Button 
            :label="'Create Customer'" 
            icon="pi pi-plus" 
            size="small"
            @click="router.visit(route('customers.create'))"
          />
        </div>
      </div>
    </template>

    <template #filters>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Status</label>
          <Dropdown 
            v-model="filterForm.status" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value"
            placeholder="All Statuses"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Customer Type</label>
          <Dropdown 
            v-model="filterForm.customer_type" 
            :options="customerTypeOptions" 
            optionLabel="label" 
            optionValue="value"
            placeholder="All Types"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Country</label>
          <Dropdown 
            v-model="filterForm.country_id" 
            :options="countries" 
            optionLabel="name" 
            optionValue="id"
            placeholder="All Countries"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Date Range</label>
          <Calendar 
            v-model="dateRange" 
            selectionMode="range" 
            placeholder="Select date range"
            class="w-full"
            @date-select="handleDateChange"
          />
        </div>
      </div>

      <div class="p-4 border-t">
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-600">Search:</span>
          <InputText 
            v-model="filterForm.search" 
            placeholder="Search customers..."
            class="flex-1"
            @keyup.enter="applyFilters"
          />
          <Button 
            icon="pi pi-refresh" 
            size="small" 
            outlined
            @click="clearFilters"
            v-tooltip.bottom="'Reset filters'"
          />
        </div>
      </div>
    </template>

    <template #content>
      <Card>
        <template #content>
          <DataTable
            :value="customers.data"
            :loading="customers.loading"
            :paginator="true"
            :rows="customers.per_page"
            :totalRecords="customers.total"
            :lazy="true"
            @sort="handleSort"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            stripedRows
            responsiveLayout="scroll"
            class="w-full"
          >
            <Column 
              field="customer_number" 
              header="Customer #" 
              sortable
              style="width: 140px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ data.customer_number }}
                </div>
                <div class="text-xs text-gray-500" v-if="data.is_active">
                  Active
                </div>
              </template>
            </Column>

            <Column 
              field="name" 
              header="Customer Name" 
              sortable
              style="width: 250px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ data.name }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ data.email }}
                </div>
                <div class="text-xs text-gray-500" v-if="data.phone">
                  {{ data.phone }}
                </div>
              </template>
            </Column>

            <Column 
              field="customer_type" 
              header="Type" 
              sortable
              style="width: 100px"
            >
              <template #body="{ data }">
                <Badge 
                  :value="formatCustomerType(data.customer_type)"
                  :severity="getTypeSeverity(data.customer_type)"
                  size="small"
                />
              </template>
            </Column>

            <Column 
              field="country" 
              header="Country" 
              sortable
              style="width: 120px"
            >
              <template #body="{ data }">
                {{ data.country?.name || '-' }}
              </template>
            </Column>

            <Column 
              field="currency" 
              header="Currency" 
              sortable
              style="width: 80px"
            >
              <template #body="{ data }">
                {{ data.currency?.code || '-' }}
              </template>
            </Column>

            <Column 
              field="status" 
              header="Status" 
              sortable
              style="width: 100px"
            >
              <template #body="{ data }">
                <Badge 
                  :value="formatStatus(data.is_active ? 'active' : 'inactive')"
                  :severity="getStatusSeverity(data.is_active ? 'active' : 'inactive')"
                  size="small"
                />
              </template>
            </Column>

            <Column 
              field="outstanding_balance" 
              header="Balance" 
              sortable
              style="width: 120px; text-align: right"
            >
              <template #body="{ data }">
                <div class="text-right">
                  <div class="font-medium" :class="getBalanceClass(data.outstanding_balance)">
                    {{ formatMoney(data.outstanding_balance, data.currency) }}
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ data.risk_level || 'low' }} risk
                  </div>
                </div>
              </template>
            </Column>

            <Column 
              field="created_at" 
              header="Created" 
              sortable
              style="width: 120px"
            >
              <template #body="{ data }">
                <div class="text-sm text-gray-600">
                  {{ formatDate(data.created_at) }}
                </div>
              </template>
            </Column>

            <Column 
              header="Actions" 
              style="width: 120px; text-align: center"
              exportable="false"
            >
              <template #body="{ data }">
                <div class="flex items-center justify-center gap-1">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    text
                    rounded
                    @click="viewCustomer(data)"
                    v-tooltip.bottom="'View customer details'"
                  />
                  
                  <Button
                    icon="pi pi-edit"
                    size="small"
                    text
                    rounded
                    @click="editCustomer(data)"
                    v-tooltip.bottom="'Edit customer'"
                  />
                  
                  <Button
                    icon="pi pi-chart-line"
                    size="small"
                    text
                    rounded
                    @click="viewStatistics(data)"
                    v-tooltip.bottom="'View statistics'"
                  />
                  
                  <Button
                    v-if="canDelete(data)"
                    icon="pi pi-trash"
                    size="small"
                    text
                    rounded
                    severity="danger"
                    @click="confirmDelete(data)"
                    v-tooltip.bottom="'Delete customer'"
                  />
                </div>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-users text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No customers found</p>
                <Button 
                  :label="'Create Customer'" 
                  icon="pi pi-plus" 
                  size="small"
                  class="mt-3"
                  @click="router.visit(route('customers.create'))"
                />
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>
                  Showing {{ customers.from }} to {{ customers.to }} of {{ customers.total }} customers
                </span>
                <span>
                  Active: {{ customers.total_active }} | Inactive: {{ customers.total_inactive }}
                </span>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </template>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialog.visible" 
      :header="'Delete Customer'" 
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to delete customer <strong>{{ deleteDialog.customer?.name }}</strong>?
        </div>
        
        <div v-if="deleteDialog.customer?.outstanding_balance > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
          <div class="flex items-center gap-2">
            <i class="pi pi-exclamation-triangle text-yellow-600"></i>
            <span class="text-sm text-yellow-800">
              This customer has an outstanding balance of {{ formatMoney(deleteDialog.customer.outstanding_balance, deleteDialog.customer.currency) }}
            </span>
          </div>
        </div>
        
        <div class="text-sm text-gray-500">
          This action cannot be undone. All related data including invoices, payments, and contacts will be affected.
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          text 
          @click="deleteDialog.visible = false"
        />
        <Button 
          label="Delete Customer" 
          severity="danger" 
          :loading="deleteDialog.loading"
          @click="deleteCustomer"
        />
      </template>
    </Dialog>
  </LayoutShell>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Dialog from 'primevue/dialog'
import Calendar from 'primevue/calendar'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import { formatDate, formatMoney } from '@/Utils/formatting'

interface Customer {
  id: number
  customer_number: string
  name: string
  email?: string
  phone?: string
  customer_type: string
  status: string
  created_at: string
  country?: {
    id: number
    name: string
    code: string
  }
  currency?: {
    id: number
    code: string
    symbol: string
  }
}

interface FilterForm {
  status: string | null
  customer_type: string | null
  country_id: number | null
  created_from: string | null
  created_to: string | null
  search: string | null
  sort_by: string
  sort_direction: string
}

const props = defineProps<{
  customers: any
  filters: any
  countries: any[]
  statusOptions: any[]
  customerTypeOptions: any[]
}>()

const filterForm = reactive<FilterForm>({
  status: props.filters.status || null,
  customer_type: props.filters.customer_type || null,
  country_id: props.filters.country_id || null,
  created_from: props.filters.created_from || null,
  created_to: props.filters.created_to || null,
  search: props.filters.search || null,
  sort_by: props.filters.sort_by || 'created_at',
  sort_direction: props.filters.sort_direction || 'desc'
})

const deleteDialog = ref({
  visible: false,
  customer: null as Customer | null,
  loading: false
})
const dateRange = ref<Date[] | null>(null)

const pageMeta = computed(() => ({
  title: 'Customers'
}))

const handleDateChange = (dates: Date[]) => {
  dateRange.value = dates
  if (dates && dates.length === 2) {
    filterForm.created_from = formatDate(dates[0], 'YYYY-MM-DD')
    filterForm.created_to = formatDate(dates[1], 'YYYY-MM-DD')
  } else {
    filterForm.created_from = null
    filterForm.created_to = null
  }
  applyFilters()
}

const viewCustomer = (customer: Customer) => {
  router.visit(route('customers.show', customer.id))
}

const editCustomer = (customer: Customer) => {
  router.visit(route('customers.edit', customer.id))
}

const viewStatistics = (customer: Customer) => {
  router.visit(route('customers.statistics', customer.id))
}

const exportCustomers = () => {
  const params = new URLSearchParams()
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, String(value))
    }
  })
  
  const url = route('customers.export') + '?' + params.toString()
  window.open(url, '_blank')
}

const canDelete = (customer: Customer): boolean => {
  return customer.outstanding_balance === 0
}

const getBalanceClass = (balance: number): string => {
  if (balance > 0) return 'text-red-600'
  if (balance < 0) return 'text-green-600'
  return 'text-gray-600'
}

const applyFilters = () => {
  router.get(route('customers.index'), filterForm, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

const clearFilters = () => {
  Object.keys(filterForm).forEach(key => {
    if (key === 'sort_by' || key === 'sort_direction') return
    filterForm[key as keyof FilterForm] = null
  })
  applyFilters()
}

const handleSort = (event: any) => {
  filterForm.sort_by = event.sortField
  filterForm.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

const confirmDelete = (customer: Customer) => {
  deleteDialog.value.customer = customer
  deleteDialog.value.visible = true
}

const deleteCustomer = () => {
  if (!deleteDialog.value.customer) return

  deleteDialog.value.loading = true
  router.delete(route('customers.destroy', deleteDialog.value.customer.id), {
    onSuccess: () => {
      deleteDialog.value.visible = false
      deleteDialog.value.customer = null
    },
    onFinish: () => {
      deleteDialog.value.loading = false
    }
  })
}

const formatCustomerType = (type: string): string => {
  const typeMap: Record<string, string> = {
    'individual': 'Individual',
    'business': 'Business',
    'non_profit': 'Non-Profit',
    'government': 'Government'
  }
  return typeMap[type] || type
}

const getTypeSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    'individual': 'info',
    'business': 'success',
    'non_profit': 'warning',
    'government': 'secondary'
  }
  return severityMap[type] || 'secondary'
}

const formatStatus = (status: string): string => {
  const statusMap: Record<string, string> = {
    'active': 'Active',
    'inactive': 'Inactive',
    'suspended': 'Suspended'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    'active': 'success',
    'inactive': 'secondary',
    'suspended': 'danger'
  }
  return severityMap[status] || 'secondary'
}

onMounted(() => {
  // Initialize tooltips if available
  if (window.bootstrap && window.bootstrap.Tooltip) {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new window.bootstrap.Tooltip(tooltipTriggerEl)
    })
  }
})
</script>