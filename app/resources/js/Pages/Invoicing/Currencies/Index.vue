<template>
  <LayoutShell :title="pageMeta.title">
    <template #title>
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Currencies</h1>
        <div class="flex items-center gap-3">
          <Button 
            :label="'Export Currencies'" 
            icon="pi pi-download" 
            outlined 
            size="small"
            @click="exportCurrencies"
          />
          <Button 
            :label="'Exchange Rates'" 
            icon="pi pi-sync" 
            size="small"
            @click="navigateTo(route('currencies.exchange-rates'))"
          />
        </div>
      </div>
    </template>

    <template #filters>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Status</label>
          <Dropdown 
            v-model="filterForm.status" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value"
            placeholder="All Currencies"
            class="w-full"
            @change="applyFilters"
          />
        </div>
      </div>

      <div class="p-4 border-t">
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-600">Search:</span>
          <InputText 
            v-model="filterForm.search" 
            placeholder="Search currencies..."
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
            :value="currencies.data"
            :loading="currencies.loading"
            :paginator="true"
            :rows="currencies.per_page"
            :totalRecords="currencies.total"
            :lazy="true"
            @sort="handleSort"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            stripedRows
            responsiveLayout="scroll"
            class="w-full"
          >
          <Column
            field="code"
            header="Code"
            :sortable="true"
            style="width: 80px"
          >
            <template #body="{ data }">
              <div class="font-medium">{{ data.code }}</div>
            </template>
          </Column>
          
          <Column
            field="name"
            header="Currency Name"
            :sortable="true"
            style="width: 200px"
          >
            <template #body="{ data }">
              {{ data.name }}
            </template>
          </Column>
          
          <Column
            field="symbol"
            header="Symbol"
            style="width: 80px"
          >
            <template #body="{ data }">
              <span class="text-lg">{{ data.symbol }}</span>
            </template>
          </Column>
          
          <Column 
            field="status" 
            header="Status" 
            style="width: 100px"
          >
            <template #body="{ data }">
              <Badge 
                :value="getCurrencyStatus(data)"
                :severity="getStatusSeverity(data)"
                size="small"
              />
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
                  v-if="!isCurrencyEnabled(data)"
                  icon="pi pi-plus"
                  size="small"
                  text
                  rounded
                  @click="enableCurrency(data)"
                  v-tooltip.bottom="'Enable Currency'"
                  severity="success"
                />
                
                <Button
                  v-else
                  icon="pi pi-minus"
                  size="small"
                  text
                  rounded
                  @click="confirmDisable(data)"
                  v-tooltip.bottom="'Disable Currency'"
                  severity="danger"
                />
                
                <Button
                  icon="pi pi-sync"
                  size="small"
                  text
                  rounded
                  @click="navigateTo(route('currencies.exchange-rates'))"
                  v-tooltip.bottom="'Exchange Rates'"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-8">
              <i class="pi pi-coins text-4xl text-gray-300 mb-3"></i>
              <p class="text-gray-500">No currencies found</p>
            </div>
          </template>

          <template #footer>
            <div class="flex items-center justify-between text-sm text-gray-600">
              <span>
                Showing {{ currencies.from }} to {{ currencies.to }} of {{ currencies.total }} currencies
              </span>
              <span>
                Enabled: {{ companyCurrencies.length }}
              </span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Enabled Currencies Summary -->
    <Card class="mt-6">
      <template #title>Enabled Currencies Summary</template>
      <template #content>
        <div v-if="companyCurrencies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="currency in companyCurrencies"
            :key="currency.id"
            class="flex items-center justify-between p-3 border rounded-lg bg-green-50"
          >
            <div>
              <div class="font-medium">{{ currency.code }}</div>
              <div class="text-sm text-gray-600">{{ currency.name }}</div>
            </div>
            <div class="text-lg">{{ currency.symbol }}</div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="pi pi-coins text-4xl text-gray-300 mb-3"></i>
          <p>No currencies enabled for your company.</p>
          <p class="text-sm">Enable currencies from the list above to start using them.</p>
        </div>
      </template>
    </Card>
  </template>

  <!-- Disable Confirmation Dialog -->
  <Dialog 
    v-model:visible="disableDialog" 
    :header="'Disable Currency'" 
    :style="{ width: '500px' }"
    modal
  >
    <div class="space-y-4">
      <div class="text-gray-600">
        Are you sure you want to disable currency <strong>{{ currencyToDisable?.name }}</strong> ({{ currencyToDisable?.code }})?
      </div>
      
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-exclamation-triangle text-yellow-600"></i>
          <span class="text-sm text-yellow-800">
            Disabling this currency will prevent it from being used in new transactions.
          </span>
        </div>
      </div>
      
      <div class="text-sm text-gray-500">
        Existing transactions using this currency will remain unaffected.
      </div>
    </div>

    <template #footer>
      <Button 
        label="Cancel" 
        text 
        @click="disableDialog = false"
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

interface Currency {
  id: number
  code: string
  name: string
  symbol: string
}

interface FilterForm {
  status: string | null
  search: string | null
  sort_by: string
  sort_direction: string
}

const props = defineProps<{
  currencies: any
  companyCurrencies: Currency[]
  filters: any
}>()

const filterForm = reactive<FilterForm>({
  status: props.filters.status || null,
  search: props.filters.search || null,
  sort_by: props.filters.sort_by || 'name',
  sort_direction: props.filters.sort_direction || 'asc'
})

const disableDialog = ref({
  visible: false,
  currency: null as Currency | null,
  loading: false
})

const pageMeta = computed(() => ({
  title: 'Currencies'
}))

const exportCurrencies = () => {
  const params = new URLSearchParams()
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, String(value))
    }
  })
  
  const url = route('currencies.export') + '?' + params.toString()
  window.open(url, '_blank')
}

const statusOptions = [
  { label: 'All Currencies', value: null },
  { label: 'Enabled', value: 'enabled' },
  { label: 'Disabled', value: 'disabled' }
]

const applyFilters = () => {
  router.get(route('currencies.index'), filterForm, {
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
      preserveScroll: true
    }
  )
}

const confirmDisable = (currency: Currency) => {
  disableDialog.value.currency = currency
  disableDialog.value.visible = true
}

const disableCurrency = () => {
  if (!disableDialog.value.currency) return

  disableDialog.value.loading = true
  router.post(
    route('currencies.disable'),
    { currency_id: disableDialog.value.currency.id },
    {
      onSuccess: () => {
        disableDialog.value.visible = false
        disableDialog.value.currency = null
      },
      onFinish: () => {
        disableDialog.value.loading = false
      }
    }
  )
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