<template>
  <Head title="Currencies" />

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
        title="Currencies"
        subtitle="Manage and configure currencies for your company"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="table.filterForm.search"
              placeholder="Search currencies..."
              class="w-64"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Currencies Table -->
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
            :value="currencies?.data || []"
            :loading="currencies?.loading || false"
            :paginator="true"
            :rows="currencies?.per_page || 10"
            :totalRecords="currencies?.total || 0"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            :virtualScroll="(currencies?.total || 0) > 200"
            scrollHeight="500px"
            responsiveLayout="stack"
            breakpoint="960px"
            v-model:filters="table.tableFilters.value"
            v-model:selection="table.selectedRows.value"
            selectionMode="multiple"
            dataKey="id"
            :showSelectionColumn="true"
            @page="table.onPage"
            @sort="table.onSort"
            @filter="table.onFilter"
          >
            <template #cell-code="{ data }">
              <div class="font-medium">{{ data.code }}</div>
            </template>

            <template #cell-name="{ data }">
              {{ data.name }}
            </template>

            <template #cell-symbol="{ data }">
              <span class="text-lg">{{ data.symbol }}</span>
            </template>

            <template #cell-status="{ data }">
              <Badge 
                :value="getCurrencyStatus(data)"
                :severity="getStatusSeverity(data)"
                size="small"
              />
            </template>

            <template #cell-actions="{ data }">
              <div class="flex items-center justify-center gap-2">
                <!-- Enable/Disable -->
                <button
                  v-if="!isCurrencyEnabled(data)"
                  @click="enableCurrency(data)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Enable Currency"
                >
                  <i class="fas fa-plus text-green-600 dark:text-green-400"></i>
                </button>
                
                <button
                  v-else
                  @click="confirmDisable(data)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Disable Currency"
                >
                  <i class="fas fa-minus text-red-600 dark:text-red-400"></i>
                </button>
                
                <!-- Exchange Rates -->
                <Link :href="route('currencies.exchange-rates')">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Exchange Rates"
                  >
                    <i class="fas fa-sync text-blue-600 dark:text-blue-400"></i>
                  </button>
                </Link>
              </div>
            </template>

            <template #empty>
              <div class="text-center py-8">
                <i class="fas fa-coins text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No currencies found</p>
                <p class="text-sm">Try adjusting your filters or enable currencies for your company.</p>
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>
                  Showing {{ currencies?.from || 0 }} to {{ currencies?.to || 0 }} of {{ currencies?.total || 0 }} currencies
                </span>
                <span>
                  Enabled: {{ companyCurrencies?.length || 0 }}
                </span>
              </div>
            </template>
          </DataTablePro>
        </template>
      </Card>

      <!-- Enabled Currencies Summary -->
      <Card>
        <template #title>Enabled Currencies Summary</template>
        <template #content>
          <div v-if="companyCurrencies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="currency in companyCurrencies"
              :key="currency.id"
              class="flex items-center justify-between p-3 border rounded-lg bg-green-50 dark:bg-green-900/20"
            >
              <div>
                <div class="font-medium">{{ currency.code }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ currency.name }}</div>
              </div>
              <div class="text-lg">{{ currency.symbol }}</div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
            <i class="fas fa-coins text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
            <p>No currencies enabled for your company.</p>
            <p class="text-sm">Enable currencies from the list above to start using them.</p>
          </div>
        </template>
      </Card>
    </div>

    <!-- Disable Confirmation Dialog -->
    <Dialog 
      v-model:visible="disableDialog.visible" 
      :header="'Disable Currency'" 
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600 dark:text-gray-400">
          Are you sure you want to disable currency <strong>{{ disableDialog.currency?.name }}</strong> ({{ disableDialog.currency?.code }})?
        </div>
        
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
          <div class="flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400"></i>
            <span class="text-sm text-yellow-800 dark:text-yellow-200">
              Disabling this currency will prevent it from being used in new transactions.
            </span>
          </div>
        </div>
        
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Existing transactions using this currency will remain unaffected.
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          text 
          @click="disableDialog.visible = false"
        />
        <Button 
          label="Disable Currency" 
          severity="danger" 
          :loading="disableDialog.loading"
          @click="disableCurrency"
        />
      </template>
    </Dialog>

  </LayoutShell>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, reactive, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from 'primevue/card'
import DataTablePro from '@/Components/DataTablePro.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Dialog from 'primevue/dialog'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useToast } from 'primevue/usetoast'

interface Currency {
  id: number
  code: string
  name: string
  symbol: string
}

const props = defineProps({
  currencies: {
    type: Object,
    default: () => ({
      data: [],
      current_page: 1,
      per_page: 10,
      total: 0,
      from: 0,
      to: 0,
      loading: false
    })
  },
  companyCurrencies: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => ({})
  },
})

const toasty = useToast()
const { setActions, clearActions } = usePageActions()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Currencies', url: '/currencies', icon: 'coins' },
])

// DataTablePro columns definition
const columns = [
  { field: 'code', header: 'Code', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'name', header: 'Currency Name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 250px' },
  { field: 'symbol', header: 'Symbol', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'status', header: 'Status', filter: { type: 'select', options: [{label:'Enabled', value:'enabled'},{label:'Disabled', value:'disabled'}] }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 140px; text-align: center' },
]

// Use the useDataTable composable
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'currencies.index',
  filterLookups: {
    status: {
      options: [{label:'Enabled', value:'enabled'},{label:'Disabled', value:'disabled'}],
      labelField: 'label',
      valueField: 'value'
    }
  }
})

// Dialog state
const disableDialog = reactive({
  visible: false,
  currency: null as Currency | null,
  loading: false
})

// Export functionality
const exportCurrencies = () => {
  window.location.href = route('currencies.export', table.filterForm.data())
}

// Currency helper functions
const isCurrencyEnabled = (currency: Currency): boolean => {
  return props.companyCurrencies.some(c => c.id === currency.id)
}

const getCurrencyStatus = (currency: Currency): string => {
  return isCurrencyEnabled(currency) ? 'Enabled' : 'Disabled'
}

const getStatusSeverity = (currency: Currency): string => {
  return isCurrencyEnabled(currency) ? 'success' : 'secondary'
}

const enableCurrency = (currency: Currency) => {
  router.post(
    route('currencies.enable'),
    { currency_id: currency.id },
    {
      preserveScroll: true,
      onSuccess: () => {
        toasty.add({
          severity: 'success',
          summary: 'Success',
          detail: `${currency.code} enabled successfully`,
          life: 3000
        })
      }
    }
  )
}

const confirmDisable = (currency: Currency) => {
  disableDialog.currency = currency
  disableDialog.visible = true
}

const disableCurrency = () => {
  if (!disableDialog.currency) return

  disableDialog.loading = true
  router.post(
    route('currencies.disable'),
    { currency_id: disableDialog.currency.id },
    {
      onSuccess: () => {
        disableDialog.visible = false
        disableDialog.currency = null
        toasty.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Currency disabled successfully',
          life: 3000
        })
      },
      onFinish: () => {
        disableDialog.loading = false
      }
    }
  )
}

// Bulk operations
async function bulkEnable() {
  if (!table.selectedRows.value.length) return
  if (!confirm(`Enable ${table.selectedRows.value.length} selected currencies?`)) return
  
  await router.post(route('currencies.bulk'), { 
    action: 'enable', 
    currency_ids: table.selectedRows.value.map((r: Currency) => r.id) 
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toasty.add({
        severity: 'success',
        summary: 'Success',
        detail: `${table.selectedRows.value.length} currencies enabled successfully`,
        life: 3000
      })
      table.selectedRows.value = []
    }
  })
}

async function bulkDisable() {
  if (!table.selectedRows.value.length) return
  if (!confirm(`Disable ${table.selectedRows.value.length} selected currencies?`)) return
  
  await router.post(route('currencies.bulk'), { 
    action: 'disable', 
    currency_ids: table.selectedRows.value.map((r: Currency) => r.id) 
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toasty.add({
        severity: 'success',
        summary: 'Success',
        detail: `${table.selectedRows.value.length} currencies disabled successfully`,
        life: 3000
      })
      table.selectedRows.value = []
    }
  })
}

// Page Actions
setActions([
  { key: 'exchange', label: 'Exchange Rates', icon: 'pi pi-sync', severity: 'secondary', click: () => router.visit(route('currencies.exchange-rates')) },
  { key: 'enable', label: 'Enable Selected', icon: 'pi pi-plus', severity: 'success', disabled: () => table.selectedRows.value.length === 0, click: bulkEnable },
  { key: 'disable', label: 'Disable Selected', icon: 'pi pi-minus', severity: 'danger', disabled: () => table.selectedRows.value.length === 0, click: bulkDisable },
  { key: 'export', label: 'Export', icon: 'pi pi-download', severity: 'secondary', outlined: true, click: () => exportCurrencies() },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => table.fetchData() },
])

onUnmounted(() => clearActions())
</script>