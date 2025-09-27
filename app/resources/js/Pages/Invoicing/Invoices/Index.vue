<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import DataTablePro from '@/Components/DataTablePro.vue'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Dialog from 'primevue/dialog'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useLookups } from '@/composables/useLookups'
import { useDeleteConfirmation } from '@/composables/useDeleteConfirmation'
import { formatMoney, formatDate } from '@/Utils/formatting'

const props = defineProps({
  invoices: Object,
  filters: Object,
  customers: Array,
  currencies: Array,
  statusOptions: Array,
})

const { setActions, clearActions } = usePageActions()
const { getInvoiceStatusSeverity } = useLookups()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
])

// DataTablePro columns and filters
const columns = [
  { field: 'invoice_number', header: 'Invoice #', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 160px' },
  { field: 'customer', header: 'Customer', filterField: 'customer_name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 220px' },
  { field: 'invoice_date', header: 'Invoice Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'due_date', header: 'Due Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'total_amount', header: 'Total', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 120px; text-align: right' },
  { field: 'balance_due', header: 'Balance', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 120px; text-align: right' },
  { field: 'status', header: 'Status', filter: { type: 'select', matchMode: FilterMatchMode.EQUALS, options: props.statusOptions }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 200px; text-align: center' },
]

const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'invoices.index',
  filterLookups: {
    status: {
      options: props.statusOptions || [],
    }
  }
})

const deleteConfirmation = useDeleteConfirmation({
  deleteRouteName: 'invoices.destroy',
})

// Export functionality
const exportInvoices = () => {
  window.location.href = route('invoices.export', table.filterForm.data())
}

// Page Actions for Invoices
async function bulkRemind() {
  if (!table.selectedRows.value.length) return
  await router.post(route('invoices.bulk'), { action: 'remind', invoice_ids: table.selectedRows.value.map((r:any) => r.invoice_id ?? r.id) }, { preserveState: true, preserveScroll: true })
}
async function bulkCancel() {
  if (!table.selectedRows.value.length) return
  await router.post(route('invoices.bulk'), { action: 'cancel', invoice_ids: table.selectedRows.value.map((r:any) => r.invoice_id ?? r.id) }, { preserveState: true, preserveScroll: true })
}

setActions([
  { key: 'create', label: 'Create Invoice', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('invoices.create')) },
  { key: 'remind', label: 'Send Reminders', icon: 'pi pi-bell', severity: 'secondary', disabled: () => table.selectedRows.value.length === 0, click: bulkRemind },
  { key: 'cancel', label: 'Cancel', icon: 'pi pi-ban', severity: 'danger', disabled: () => table.selectedRows.value.length === 0, click: bulkCancel },
  { key: 'export', label: 'Export', icon: 'pi pi-download', severity: 'secondary', outlined: true, click: () => exportInvoices() },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => table.fetchData() },
])

onUnmounted(() => clearActions())
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
      </div>
    </template>

    <div class="space-y-6">
      <PageHeader
        title="Invoices"
        subtitle="Manage and track your invoices"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="table.filterForm.search"
              placeholder="Search invoices..."
              @keyup.enter="table.fetchData()"
              class="w-64"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Invoices Table -->
      <Card>
        <template #content>
          <!-- Active Filters Chips -->
          <div v-if="table.activeFilters.value.length" class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-xs text-gray-500">Filters:</span>
            <span
              v-for="f in table.activeFilters.value"
              :key="f.key"
              class="inline-flex items-center text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"
            >
              <span class="mr-1">{{ f.display }}</span>
              <button
                type="button"
                class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                @click="table.clearTableFilterField(table.tableFilters.value, f.field)"
                aria-label="Clear filter"
              >
                ×
              </button>
            </span>
            <Button label="Clear all" size="small" text @click="table.clearFilters()" />
          </div>
          <DataTablePro
            :value="invoices.data"
            :loading="invoices.loading"
            :paginator="true"
            :rows="invoices.per_page"
            :totalRecords="invoices.total"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            v-model:filters="table.tableFilters.value"
            v-model:selection="table.selectedRows.value"
            selectionMode="multiple"
            dataKey="invoice_id"
            :showSelectionColumn="true"
            @page="table.onPage"
            @sort="table.onSort"
            @filter="table.onFilter"
          >
            <template #cell-invoice_number="{ data }">
              <Link :href="route('invoices.show', { invoice: data.invoice_id })" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                {{ data.invoice_number }}
              </Link>
            </template>

            <template #cell-customer="{ data }">
              <div>
                <div class="font-medium">{{ data.customer?.name }}</div>
                <div class="text-sm text-gray-500" v-if="data.customer?.email">{{ data.customer.email }}</div>
              </div>
            </template>

            <template #cell-invoice_date="{ data }">{{ formatDate(data.invoice_date) }}</template>

            <template #cell-due_date="{ data }">
              <div>
                <div>{{ formatDate(data.due_date) }}</div>
                <div v-if="data.status !== 'paid' && new Date(data.due_date) < new Date()" class="text-xs text-red-600 dark:text-red-400">Overdue</div>
              </div>
            </template>

            <template #cell-total_amount="{ data }">{{ formatMoney(data.total_amount, data.currency?.code) }}</template>

            <template #cell-paid_amount="{ data }">{{ formatMoney(data.paid_amount, data.currency?.code) }}</template>

            <template #cell-balance_due="{ data }">
              <div :class="data.balance_due > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-green-600 dark:text-green-400'">
                {{ formatMoney(data.balance_due, data.currency?.code) }}
              </div>
            </template>

            <template #cell-status="{ data }">
              <Tag :value="data.status" :severity="getInvoiceStatusSeverity(data.status)" />
            </template>

            <template #cell-actions="{ data }">
                <div class="flex items-center gap-1">
                  <Link :href="route('invoices.show', { invoice: data.invoice_id })">
                    <Button
                      icon="pi pi-eye"
                      size="small"
                      severity="secondary"
                      outlined
                      v-tooltip="'View Invoice'"
                    />
                  </Link>

                  <Link
                    :href="route('invoices.edit', { invoice: data.invoice_id })"
                    v-if="data.status === 'draft'"
                  >
                    <Button
                      icon="pi pi-pencil"
                      size="small"
                      severity="primary"
                      outlined
                      v-tooltip="'Edit Invoice'"
                    />
                  </Link>

                  <Link :href="route('invoices.generate-pdf', { invoice: data.invoice_id })" target="_blank">
                    <Button
                      icon="pi pi-file-pdf"
                      size="small"
                      severity="info"
                      outlined
                      v-tooltip="'Download PDF'"
                    />
                  </Link>

                  <!-- Status-based actions -->
                  <template v-if="data.status === 'draft'">
                    <Button
                      icon="pi pi-send"
                      size="small"
                      severity="warning"
                      outlined
                      v-tooltip="'Mark as Sent'"
                      @click="router.post(route('invoices.send', { invoice: data.invoice_id }))"
                    />
                  </template>

                  <template v-else-if="data.status === 'sent'">
                    <Button
                      icon="pi pi-check"
                      size="small"
                      severity="success"
                      outlined
                      v-tooltip="'Post to Ledger'"
                      @click="router.post(route('invoices.post', { invoice: data.invoice_id }))"
                    />
                  </template>

                  <Button
                    v-if="data.status === 'draft'"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    outlined
                    v-tooltip="'Delete Invoice'"
                    @click="deleteConfirmation.show(data)"
                  />
                </div>
            </template>

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
          </DataTablePro>
        </template>
      </Card>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog
      v-model:visible="deleteConfirmation.isVisible.value"
      :header="'Delete Invoice'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to delete invoice <strong>{{ deleteConfirmation.itemToDelete.value?.invoice_number }}</strong>?
        </div>

        <div class="text-sm text-gray-500">
          This action can only be performed on draft invoices and cannot be undone.
        </div>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          text
          @click="deleteConfirmation.hide()"
        />
        <Button
          label="Delete Invoice"
          severity="danger"
          :loading="deleteConfirmation.isLoading.value"
          @click="deleteConfirmation.confirm()"
        />
      </template>
    </Dialog>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>
