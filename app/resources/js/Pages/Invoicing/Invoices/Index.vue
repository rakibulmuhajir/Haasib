<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'

const props = defineProps({
  invoices: Object,
  filters: Object,
  customers: Array,
  currencies: Array,
  statusOptions: Array,
})

const page = usePage()
const toast = page.props.toast || {}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
])

// Filter form
const filterForm = useForm({
  status: props.filters?.status || '',
  customer_id: props.filters?.customer_id || '',
  currency_id: props.filters?.currency_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  sort_by: props.filters?.sort_by || 'created_at',
  sort_direction: props.filters?.sort_direction || 'desc',
})

// Status badge styling
const getStatusSeverity = (status) => {
  const severityMap = {
    draft: 'secondary',
    sent: 'info',
    posted: 'warning',
    paid: 'success',
    cancelled: 'danger',
    void: 'contrast'
  }
  return severityMap[status] || 'secondary'
}

// Format currency
const formatCurrency = (amount, currency) => {
  if (!amount || !currency) return '-'
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency.code || 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

// Format date
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Apply filters
const applyFilters = () => {
  filterForm.get(route('invoices.index'), {
    preserveState: true,
    preserveScroll: true
  })
}

// Clear filters
const clearFilters = () => {
  filterForm.reset()
  filterForm.get(route('invoices.index'), {
    preserveState: true,
    preserveScroll: true
  })
}

// Watch for filter changes and auto-apply
watch(
  () => [
    filterForm.status,
    filterForm.customer_id,
    filterForm.currency_id,
    filterForm.date_from,
    filterForm.date_to,
    filterForm.search,
    filterForm.sort_by,
    filterForm.sort_direction
  ],
  () => {
    if (filterForm.recentlySuccessful) return // Skip after form submission
    applyFilters()
  },
  { deep: true }
)

// Handle sorting
const handleSort = (event) => {
  filterForm.sort_by = event.sortField || 'created_at'
  filterForm.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

// Export functionality
const exportInvoices = () => {
  window.location.href = route('invoices.export', filterForm.data())
}
</script>

<template>
  <Head title="Invoices" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing System" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <Link :href="route('invoices.create')">
          <Button label="Create Invoice" icon="pi pi-plus" severity="primary" />
        </Link>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Invoices</h1>
          <p class="text-gray-600 dark:text-gray-400">Manage and track your invoices</p>
        </div>
        <div class="flex items-center gap-2">
          <Button 
            label="Export" 
            icon="pi pi-download" 
            severity="secondary" 
            outlined 
            @click="exportInvoices"
          />
          <Button 
            label="Refresh" 
            icon="pi pi-refresh" 
            severity="secondary" 
            @click="applyFilters"
          />
        </div>
      </div>

      <!-- Filters Section -->
      <Card class="p-4">
        <template #title>
          <div class="flex items-center justify-between">
            <span>Filters</span>
            <Button 
              label="Clear Filters" 
              icon="pi pi-times" 
              size="small" 
              severity="secondary" 
              outlined 
              @click="clearFilters"
              v-if="filterForm.isDirty"
            />
          </div>
        </template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Search
              </label>
              <InputText
                v-model="filterForm.search"
                placeholder="Search invoices by number, customer, notes..."
                class="w-full"
                fluid
              />
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Status
              </label>
              <Dropdown
                v-model="filterForm.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Statuses"
                class="w-full"
                fluid
                showClear
              />
            </div>

            <!-- Customer -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Customer
              </label>
              <Dropdown
                v-model="filterForm.customer_id"
                :options="customers"
                optionLabel="name"
                optionValue="id"
                placeholder="All Customers"
                class="w-full"
                fluid
                showClear
              />
            </div>

            <!-- Currency -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Currency
              </label>
              <Dropdown
                v-model="filterForm.currency_id"
                :options="currencies"
                optionLabel="code"
                optionValue="id"
                placeholder="All Currencies"
                class="w-full"
                fluid
                showClear
              />
            </div>

            <!-- Date Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                From Date
              </label>
              <Calendar
                v-model="filterForm.date_from"
                placeholder="Select date"
                class="w-full"
                fluid
                showClear
                dateFormat="yy-mm-dd"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                To Date
              </label>
              <Calendar
                v-model="filterForm.date_to"
                placeholder="Select date"
                class="w-full"
                fluid
                showClear
                dateFormat="yy-mm-dd"
              />
            </div>

            <!-- Sorting -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Sort By
              </label>
              <Dropdown
                v-model="filterForm.sort_by"
                :options="[
                  { label: 'Created Date', value: 'created_at' },
                  { label: 'Invoice Date', value: 'invoice_date' },
                  { label: 'Due Date', value: 'due_date' },
                  { label: 'Total Amount', value: 'total_amount' },
                  { label: 'Status', value: 'status' }
                ]"
                optionLabel="label"
                optionValue="value"
                class="w-full"
                fluid
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Direction
              </label>
              <Dropdown
                v-model="filterForm.sort_direction"
                :options="[
                  { label: 'Descending', value: 'desc' },
                  { label: 'Ascending', value: 'asc' }
                ]"
                optionLabel="label"
                optionValue="value"
                class="w-full"
                fluid
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Invoices Table -->
      <Card>
        <template #content>
          <DataTable
            :value="invoices.data"
            :loading="invoices.loading"
            :paginator="true"
            :rows="invoices.per_page"
            :totalRecords="invoices.total"
            :lazy="true"
            @sort="handleSort"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            stripedRows
            responsiveLayout="scroll"
            class="w-full"
          >
            <Column 
              field="invoice_number" 
              header="Invoice #" 
              sortable 
              style="width: 120px"
            >
              <template #body="slotProps">
                <Link 
                  :href="route('invoices.show', slotProps.data.id)"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                >
                  {{ slotProps.data.invoice_number }}
                </Link>
              </template>
            </Column>

            <Column field="customer.name" header="Customer" sortable>
              <template #body="slotProps">
                <div>
                  <div class="font-medium">{{ slotProps.data.customer?.name }}</div>
                  <div class="text-sm text-gray-500" v-if="slotProps.data.customer?.email">
                    {{ slotProps.data.customer.email }}
                  </div>
                </div>
              </template>
            </Column>

            <Column field="invoice_date" header="Invoice Date" sortable style="width: 120px">
              <template #body="slotProps">
                {{ formatDate(slotProps.data.invoice_date) }}
              </template>
            </Column>

            <Column field="due_date" header="Due Date" sortable style="width: 120px">
              <template #body="slotProps">
                <div>
                  <div>{{ formatDate(slotProps.data.due_date) }}</div>
                  <div 
                    v-if="slotProps.data.status !== 'paid' && new Date(slotProps.data.due_date) < new Date()"
                    class="text-xs text-red-600 dark:text-red-400"
                  >
                    Overdue
                  </div>
                </div>
              </template>
            </Column>

            <Column field="total_amount" header="Total Amount" sortable style="width: 120px">
              <template #body="slotProps">
                {{ formatCurrency(slotProps.data.total_amount, slotProps.data.currency) }}
              </template>
            </Column>

            <Column field="paid_amount" header="Paid" sortable style="width: 120px">
              <template #body="slotProps">
                {{ formatCurrency(slotProps.data.paid_amount, slotProps.data.currency) }}
              </template>
            </Column>

            <Column field="balance_due" header="Balance Due" sortable style="width: 120px">
              <template #body="slotProps">
                <div :class="slotProps.data.balance_due > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-green-600 dark:text-green-400'">
                  {{ formatCurrency(slotProps.data.balance_due, slotProps.data.currency) }}
                </div>
              </template>
            </Column>

            <Column field="status" header="Status" sortable style="width: 100px">
              <template #body="slotProps">
                <Tag :value="slotProps.data.status" :severity="getStatusSeverity(slotProps.data.status)" />
              </template>
            </Column>

            <Column header="Actions" style="width: 200px">
              <template #body="slotProps">
                <div class="flex items-center gap-1">
                  <Link :href="route('invoices.show', slotProps.data.id)">
                    <Button 
                      icon="pi pi-eye" 
                      size="small" 
                      severity="secondary" 
                      outlined 
                      v-tooltip="'View Invoice'"
                    />
                  </Link>

                  <Link 
                    :href="route('invoices.edit', slotProps.data.id)"
                    v-if="slotProps.data.status === 'draft'"
                  >
                    <Button 
                      icon="pi pi-pencil" 
                      size="small" 
                      severity="primary" 
                      outlined 
                      v-tooltip="'Edit Invoice'"
                    />
                  </Link>

                  <Link :href="route('invoices.generate-pdf', slotProps.data.id)" target="_blank">
                    <Button 
                      icon="pi pi-file-pdf" 
                      size="small" 
                      severity="info" 
                      outlined 
                      v-tooltip="'Download PDF'"
                    />
                  </Link>

                  <!-- Status-based actions -->
                  <template v-if="slotProps.data.status === 'draft'">
                    <Button 
                      icon="pi pi-send" 
                      size="small" 
                      severity="warning" 
                      outlined 
                      v-tooltip="'Mark as Sent'"
                      @click="router.post(route('invoices.send', slotProps.data.id))"
                    />
                  </template>

                  <template v-else-if="slotProps.data.status === 'sent'">
                    <Button 
                      icon="pi pi-check" 
                      size="small" 
                      severity="success" 
                      outlined 
                      v-tooltip="'Post to Ledger'"
                      @click="router.post(route('invoices.post', slotProps.data.id))"
                    />
                  </template>
                </div>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <SvgIcon name="file-text" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                <p>No invoices found.</p>
                <p class="text-sm">Try adjusting your filters or create your first invoice.</p>
              </div>
            </template>

            <template #loading>
              <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                Loading invoices...
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </div>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>