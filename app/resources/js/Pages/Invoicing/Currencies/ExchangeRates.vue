<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Exchange Rates</h1>
        <p class="text-gray-600 dark:text-gray-400">
          Manage currency exchange rates (Base: {{ baseCurrency.code }})
        </p>
      </div>
      <div class="flex space-x-2">
        <Button
          label="Sync Rates"
          icon="fas fa-sync"
          :loading="syncing"
          @click="syncRates"
        />
        <Link :href="route('currencies.index')">
          <Button
            icon="fas fa-arrow-left"
            label="Back to Currencies"
            class="p-button-outlined p-button-secondary"
          />
        </Link>
      </div>
    </div>

    <!-- Exchange Rates Table -->
    <Card>
      <template #content>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            Current Exchange Rates
          </h3>
          <Button
            label="Add Rate"
            icon="fas fa-plus"
            @click="showAddRateDialog = true"
          />
        </div>

        <DataTable
          :value="exchangeRates.data"
          :loading="exchangeRates.loading"
          :paginator="true"
          :rows="exchangeRates.per_page"
          :totalRecords="exchangeRates.total"
          :lazy="true"
          stripedRows
          responsiveLayout="scroll"
          class="w-full"
        >
          <Column
            field="toCurrency.code"
            header="Currency"
            style="width: 100px"
          >
            <template #body="{ data }">
              <div class="font-medium">{{ data.toCurrency.code }}</div>
              <div class="text-sm text-gray-500">{{ data.toCurrency.name }}</div>
            </template>
          </Column>
          
          <Column
            field="rate"
            header="Exchange Rate"
            style="width: 120px"
          >
            <template #body="{ data }">
              <div class="font-medium">1 {{ baseCurrency.code }} = {{ data.rate }}</div>
              <div class="text-sm text-gray-500">
                {{ (1 / data.rate).toFixed(6) }} {{ data.toCurrency.code }}
              </div>
            </template>
          </Column>
          
          <Column
            field="date"
            header="Effective Date"
            style="width: 120px"
          >
            <template #body="{ data }">
              {{ formatDate(data.date) }}
            </template>
          </Column>
          
          <Column
            field="created_at"
            header="Updated"
            style="width: 120px"
          >
            <template #body="{ data }">
              {{ formatDateTime(data.created_at) }}
            </template>
          </Column>
          
          <Column
            field="actions"
            header="Actions"
            style="width: 100px"
          >
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="fas fa-edit"
                  class="p-button-text p-button-sm"
                  @click="editRate(data)"
                  v-tooltip="'Edit Rate'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Rate History -->
    <Card class="mt-6">
      <template #title>Exchange Rate Trends</template>
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="currency in companyCurrencies"
            :key="currency.id"
            class="p-4 border rounded-lg"
          >
            <div class="flex justify-between items-center mb-2">
              <h4 class="font-medium">{{ currency.code }}</h4>
              <span class="text-sm text-gray-500">{{ currency.symbol }}</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span>Current Rate:</span>
                <span class="font-medium">{{ getCurrentRate(currency.id) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span>Previous Rate:</span>
                <span>{{ getPreviousRate(currency.id) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span>Last Updated:</span>
                <span>{{ getLastUpdated(currency.id) }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Add/Edit Rate Dialog -->
    <Dialog
      v-model:visible="showAddRateDialog"
      :header="editingRate ? 'Edit Exchange Rate' : 'Add Exchange Rate'"
      :style="{ width: '500px' }"
      :modal="true"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            From Currency
          </label>
          <Dropdown
            v-model="rateForm.from_currency_id"
            :options="availableCurrencies"
            optionLabel="code"
            optionValue="id"
            placeholder="Select from currency"
            class="w-full"
            disabled
          />
          <div class="text-xs text-gray-500 mt-1">
            Base currency: {{ baseCurrency.code }}
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            To Currency
          </label>
          <Dropdown
            v-model="rateForm.to_currency_id"
            :options="targetCurrencies"
            optionLabel="code"
            optionValue="id"
            placeholder="Select to currency"
            class="w-full"
            :class="{ 'p-invalid': errors.to_currency_id }"
          />
          <div v-if="errors.to_currency_id" class="text-red-500 text-sm mt-1">
            {{ errors.to_currency_id }}
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Exchange Rate
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
              1 {{ baseCurrency.code }} =
            </span>
            <InputText
              v-model="rateForm.rate"
              type="number"
              step="0.000001"
              min="0.000001"
              max="999999.999999"
              class="w-full pl-24"
              :class="{ 'p-invalid': errors.rate }"
            />
          </div>
          <div v-if="errors.rate" class="text-red-500 text-sm mt-1">
            {{ errors.rate }}
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Effective Date
          </label>
          <InputText
            v-model="rateForm.date"
            type="date"
            class="w-full"
            :class="{ 'p-invalid': errors.date }"
          />
          <div v-if="errors.date" class="text-red-500 text-sm mt-1">
            {{ errors.date }}
          </div>
        </div>
      </div>
      
      <template #footer>
        <Button
          label="Cancel"
          @click="closeRateDialog"
          class="p-button-text"
        />
        <Button
          :label="editingRate ? 'Update' : 'Add'"
          @click="saveRate"
        />
      </template>
    </Dialog>

    <!-- Sync Confirmation Dialog -->
    <Dialog
      v-model:visible="syncDialog"
      header="Confirm Sync"
      :style="{ width: '450px' }"
      :modal="true"
    >
      <div class="confirmation-content">
        <i class="fas fa-sync text-blue-500 mr-3"></i>
        <span>Are you sure you want to sync exchange rates from external source?</span>
        <div class="mt-2 text-sm text-gray-600">
          This will update all exchange rates for your enabled currencies.
        </div>
      </div>
      <template #footer>
        <Button
          label="No"
          @click="syncDialog = false"
          class="p-button-text"
        />
        <Button
          label="Yes"
          :loading="syncing"
          @click="confirmSync"
        />
      </template>
    </Dialog>
  </div>
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
import Dialog from 'primevue/dialog'
import { formatDate, formatDateTime } from '@/Utils/formatting'

interface Currency {
  id: number
  code: string
  name: string
  symbol: string
}

interface ExchangeRate {
  id: number
  from_currency_id: number
  to_currency_id: number
  rate: number
  date: string
  created_at: string
  fromCurrency: Currency
  toCurrency: Currency
}

const props = defineProps<{
  exchangeRates: any
  baseCurrency: Currency
  companyCurrencies: Currency[]
  availableCurrencies: Currency[]
}>()

const showAddRateDialog = ref(false)
const syncDialog = ref(false)
const syncing = ref(false)
const editingRate = ref<ExchangeRate | null>(null)

const errors = reactive({
  to_currency_id: '',
  rate: '',
  date: ''
})

const rateForm = reactive({
  from_currency_id: props.baseCurrency.id,
  to_currency_id: null as number | null,
  rate: '',
  date: new Date().toISOString().split('T')[0]
})

const targetCurrencies = computed(() => {
  return props.companyCurrencies.filter(c => c.id !== props.baseCurrency.id)
})

const getCurrentRate = (currencyId: number): string => {
  const rate = props.exchangeRates.data.find((r: ExchangeRate) => r.to_currency_id === currencyId)
  return rate ? rate.rate.toString() : 'N/A'
}

const getPreviousRate = (currencyId: number): string => {
  // This would typically fetch the previous rate from history
  // For now, we'll return a placeholder
  return 'N/A'
}

const getLastUpdated = (currencyId: number): string => {
  const rate = props.exchangeRates.data.find((r: ExchangeRate) => r.to_currency_id === currencyId)
  return rate ? formatDate(rate.created_at) : 'N/A'
}

const editRate = (rate: ExchangeRate) => {
  editingRate.value = rate
  rateForm.from_currency_id = rate.from_currency_id
  rateForm.to_currency_id = rate.to_currency_id
  rateForm.rate = rate.rate.toString()
  rateForm.date = rate.date
  showAddRateDialog.value = true
}

const closeRateDialog = () => {
  showAddRateDialog.value = false
  editingRate.value = null
  resetRateForm()
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = ''
  })
}

const resetRateForm = () => {
  rateForm.from_currency_id = props.baseCurrency.id
  rateForm.to_currency_id = null
  rateForm.rate = ''
  rateForm.date = new Date().toISOString().split('T')[0]
}

const saveRate = () => {
  // Clear previous errors
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = ''
  })

  const data = {
    from_currency_id: rateForm.from_currency_id,
    to_currency_id: rateForm.to_currency_id,
    rate: parseFloat(rateForm.rate),
    date: rateForm.date
  }

  router.post(
    route('currencies.update-rate'),
    data,
    {
      onSuccess: () => {
        closeRateDialog()
      },
      onError: (pageErrors) => {
        if (pageErrors) {
          Object.assign(errors, pageErrors)
        }
      }
    }
  )
}

const syncRates = () => {
  syncDialog.value = true
}

const confirmSync = () => {
  syncing.value = true
  
  router.post(
    route('currencies.sync-rates'),
    {},
    {
      onSuccess: () => {
        syncing.value = false
        syncDialog.value = false
      },
      onError: () => {
        syncing.value = false
      }
    }
  )
}

onMounted(() => {
  // Set default date
  rateForm.date = new Date().toISOString().split('T')[0]
})
</script>