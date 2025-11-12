<template>
  <LayoutShell>
    <Head title="Bills" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Bills</h1>
          <p class="mt-1 text-sm text-gray-500">Manage your vendor bills and invoices</p>
        </div>
        
        <Link :href="route('bills.create')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          New Bill
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input
              type="text"
              id="search"
              v-model="form.search"
              @input="debouncedSearch"
              placeholder="Bill number, vendor bill, notes..."
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            />
          </div>

          <!-- Status Filter -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select
              id="status"
              v-model="form.status"
              @change="search"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="approved">Approved</option>
              <option value="partial">Partial</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <!-- Vendor Filter -->
          <div>
            <label for="vendor_id" class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
            <select
              id="vendor_id"
              v-model="form.vendor_id"
              @change="search"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="">All Vendors</option>
              <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
                {{ vendor.display_name || vendor.legal_name }}
              </option>
            </select>
          </div>

          <!-- Bill Date Range -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Bill Date</label>
            <div class="flex gap-2">
              <input
                type="date"
                v-model="form.date_from"
                @change="search"
                placeholder="From"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
              />
              <input
                type="date"
                v-model="form.date_to"
                @change="search"
                placeholder="To"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
              />
            </div>
          </div>
        </div>

        <!-- Clear Filters -->
        <div class="mt-4 flex justify-end">
          <button
            @click="clearFilters"
            class="text-sm text-blue-600 hover:text-blue-500 font-medium"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Bills Table -->
      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <DataTable
          :value="bills.data"
          :paginator="true"
          :rows="bills.per_page"
          :totalRecords="bills.total"
          :lazy="true"
          @page="onPage"
          :first="(bills.current_page - 1) * bills.per_page"
          dataKey="id"
          :loading="loading"
          class="p-datatable-sm"
          stripedRows
        >
          <!-- Bill Number -->
          <Column field="bill_number" header="Bill #" sortable>
            <template #body="{ data }">
              <Link :href="route('bills.show', data.id)" class="text-blue-600 hover:text-blue-900 font-medium">
                {{ data.bill_number }}
              </Link>
            </template>
          </Column>

          <!-- Vendor -->
          <Column field="vendor.display_name" header="Vendor" sortable>
            <template #body="{ data }">
              {{ data.vendor?.display_name || data.vendor?.legal_name }}
            </template>
          </Column>

          <!-- Status -->
          <Column field="status" header="Status" sortable>
            <template #body="{ data }">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusClass(data.status)"
              >
                {{ getStatusLabel(data.status) }}
              </span>
            </template>
          </Column>

          <!-- Bill Date -->
          <Column field="bill_date" header="Bill Date" sortable>
            <template #body="{ data }">
              {{ formatDate(data.bill_date) }}
            </template>
          </Column>

          <!-- Due Date -->
          <Column field="due_date" header="Due Date" sortable>
            <template #body="{ data }">
              <span :class="{ 'text-red-600 font-medium': isOverdue(data) }">
                {{ formatDate(data.due_date) }}
              </span>
            </template>
          </Column>

          <!-- Total Amount -->
          <Column field="total_amount" header="Total" sortable>
            <template #body="{ data }">
              <div class="text-right">
                <div>{{ formatCurrency(data.total_amount) }}</div>
                <div v-if="data.amount_paid > 0" class="text-xs text-green-600">
                  Paid: {{ formatCurrency(data.amount_paid) }}
                </div>
              </div>
            </template>
          </Column>

          <!-- Balance Due -->
          <Column field="balance_due" header="Balance Due" sortable>
            <template #body="{ data }">
              <div class="text-right font-medium" :class="{ 'text-red-600': data.balance_due > 0 && isOverdue(data) }">
                {{ formatCurrency(data.balance_due) }}
              </div>
            </template>
          </Column>

          <!-- Actions -->
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <Link :href="route('bills.show', data.id)" class="text-blue-600 hover:text-blue-900">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </Link>

                <Link v-if="data.can_be_edited" :href="route('bills.edit', data.id)" class="text-indigo-600 hover:text-indigo-900">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </Link>

                <button
                  v-if="data.can_be_approved"
                  @click="approveBill(data)"
                  class="text-green-600 hover:text-green-900"
                  title="Approve"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
              </div>
            </template>
          </Column>

          <!-- Selection for bulk actions -->
          <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
        </DataTable>
      </div>

      <!-- Bulk Actions -->
      <div v-if="selectedBills.length > 0" class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-700">
            {{ selectedBills.length }} bill(s) selected
          </span>
          <div class="flex gap-3">
            <button
              @click="bulkApprove"
              class="px-3 py-2 text-sm font-medium text-green-700 bg-green-100 border border-green-300 rounded-md hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              Approve Selected
            </button>
            <button
              @click="bulkCancel"
              class="px-3 py-2 text-sm font-medium text-red-700 bg-red-100 border border-red-300 rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              Cancel Selected
            </button>
          </div>
        </div>
      </div>
    </div>
  </LayoutShell>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { formatDate, formatCurrency } from '@/utils/format'

interface Props {
  bills: any
  vendors: any[]
  filters: any
}

const props = defineProps<Props>()
const page = usePage()

const loading = ref(false)
const selectedBills = ref([])

const form = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
  vendor_id: props.filters.vendor_id || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
})

const debouncedSearch = debounce(() => {
  search()
}, 300)

function search() {
  loading.value = true
  
  router.get(
    route('bills.index'),
    {
      ...form.value,
      page: 1,
    },
    {
      preserveState: true,
      onFinish: () => loading.value = false,
    }
  )
}

function clearFilters() {
  form.value = {
    search: '',
    status: '',
    vendor_id: '',
    date_from: '',
    date_to: '',
  }
  search()
}

function onPage(event) {
  const page = Math.floor(event.first / event.rows) + 1
  
  router.get(
    route('bills.index'),
    {
      ...form.value,
      page,
    },
    {
      preserveState: true,
    }
  )
}

function getStatusClass(status: string): string {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    pending_approval: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    partial: 'bg-orange-100 text-orange-800',
    paid: 'bg-green-100 text-green-800',
    overdue: 'bg-red-100 text-red-800',
    cancelled: 'bg-red-100 text-red-800',
  }
  
  return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

function getStatusLabel(status: string): string {
  const labels = {
    draft: 'Draft',
    pending_approval: 'Pending Approval',
    approved: 'Approved',
    partial: 'Partial',
    paid: 'Paid',
    overdue: 'Overdue',
    cancelled: 'Cancelled',
  }
  
  return labels[status as keyof typeof labels] || status
}

function isOverdue(bill: any): boolean {
  return new Date(bill.due_date) < new Date() && ['approved', 'partial'].includes(bill.status)
}

function approveBill(bill: any) {
  if (confirm(`Are you sure you want to approve bill ${bill.bill_number}?`)) {
    router.post(
      route('bills.approve', bill.id),
      {},
      {
        onSuccess: () => {
          // Flash message handled by backend
        },
      }
    )
  }
}

function bulkApprove() {
  if (confirm(`Are you sure you want to approve ${selectedBills.value.length} bill(s)?`)) {
    router.post(
      route('bills.bulk'),
      {
        action: 'approve',
        bills: selectedBills.value,
      },
      {
        onSuccess: () => {
          selectedBills.value = []
        },
      }
    )
  }
}

function bulkCancel() {
  if (confirm(`Are you sure you want to cancel ${selectedBills.value.length} bill(s)? This action cannot be undone.`)) {
    router.post(
      route('bills.bulk'),
      {
        action: 'cancel',
        bills: selectedBills.value,
      },
      {
        onSuccess: () => {
          selectedBills.value = []
        },
      }
    )
  }
}
</script>
