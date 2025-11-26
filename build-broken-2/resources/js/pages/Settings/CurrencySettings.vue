<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Settings, RefreshCw, Download } from 'lucide-vue-next'
import CurrencyManagementTable from '@/components/currency/CurrencyManagementTable.vue'

interface Currency {
  id: string
  currency_code: string
  currency_name: string
  currency_symbol: string
  is_base_currency: boolean
  default_exchange_rate: number
  is_active: boolean
  created_at: string
}

interface Props {
  companyCurrencies?: Currency[]
  baseCurrency?: {
    code: string
    name: string
    symbol: string
  } | null
  exchangeRates?: any[]
  availableCurrencies?: Array<{ code: string; name: string; symbol: string }>
  isMultiCurrencyEnabled?: boolean
  csrf_token?: string
}

const props = defineProps<Props>()

console.log('🔍 Currency Settings Props:', {
  companyCurrencies: props.companyCurrencies,
  baseCurrency: props.baseCurrency,
  isMultiCurrencyEnabled: props.isMultiCurrencyEnabled,
  availableCurrencies: props.availableCurrencies
})

// Map backend currency format to component format
const currencies = computed(() => {
  const mapped = (props.companyCurrencies || []).map(c => ({
    id: c.id,
    code: c.currency_code,
    name: c.currency_name,
    symbol: c.currency_symbol,
    is_base_currency: c.is_base_currency,
    default_exchange_rate: c.default_exchange_rate,
    is_active: c.is_active,
    created_at: c.created_at
  }))
  console.log('🔄 Mapped currencies:', mapped)
  return mapped
})
const isMultiCurrencyEnabled = ref(props.isMultiCurrencyEnabled || false)
const loading = ref(false)
const toggleLoading = ref(false)
const activeOverrides = ref<Record<string, boolean>>({})
const togglingStates = ref<Record<string, boolean>>({})

console.log('✅ Currency Settings loaded:', {
  currenciesCount: currencies.value.length,
  isMultiCurrencyEnabled: isMultiCurrencyEnabled.value
})

const baseCurrency = computed(() => props.baseCurrency)

const handleToggleMultiCurrency = async (enabled: boolean) => {
  console.log('🔄 Toggle multi-currency called:', enabled)
  
  // Immediately update UI for better UX
  const previousValue = isMultiCurrencyEnabled.value
  isMultiCurrencyEnabled.value = enabled
  console.log('👁️ UI updated immediately to:', enabled)
  
  toggleLoading.value = true
  
  try {
    const csrfToken = props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    console.log('🔐 CSRF Token:', csrfToken ? 'Found' : 'Missing')
    
    console.log('📡 Sending request to /api/settings/currencies/toggle-multi-currency')
    const response = await fetch('/api/settings/currencies/toggle-multi-currency', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ enabled })
    })
    
    console.log('📥 Response status:', response.status)
    const result = await response.json()
    console.log('📦 Response data:', result)
    
    if (response.ok) {
      // Confirm the state from server
      isMultiCurrencyEnabled.value = result.isMultiCurrencyEnabled
      console.log('✅ Success! Confirmed state:', result.isMultiCurrencyEnabled)
      console.log('🍞 Showing toast:', result.message)
      toast.success(result.message || 'Multi-currency settings updated')
      console.log('🍞 Toast called')
      
      // Reload to get updated data after a short delay
      setTimeout(() => {
        console.log('🔄 Reloading page data...')
        router.reload({ only: ['companyCurrencies', 'isMultiCurrencyEnabled'] })
      }, 500)
    } else {
      console.error('❌ Request failed:', result)
      // Revert to previous value on error
      isMultiCurrencyEnabled.value = previousValue
      toast.error(result.message || 'Failed to update currency settings')
    }
  } catch (error) {
    console.error('❌ Exception during toggle:', error)
    // Revert to previous value on error
    isMultiCurrencyEnabled.value = previousValue
    toast.error('Failed to update currency settings')
  } finally {
    toggleLoading.value = false
    console.log('🏁 Toggle complete. Final state:', isMultiCurrencyEnabled.value)
  }
}

const handleAddCurrency = async (currencyData: any) => {
  console.log('💰 Adding currency:', currencyData)
  try {
    const csrfToken = props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    
    const response = await fetch('/api/currencies', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify(currencyData)
    })
    
    console.log('📥 Add currency response:', response.status)
    const result = await response.json()
    console.log('📦 Add currency result:', result)
    
    if (response.ok) {
      toast.success('Currency added successfully')
      router.reload({ only: ['companyCurrencies', 'availableCurrencies'] })
    } else {
      toast.error(result.message || 'Failed to add currency')
    }
  } catch (error) {
    console.error('❌ Failed to add currency:', error)
    toast.error('Failed to add currency')
  }
}

const handleUpdateCurrency = async (id: string, data: any) => {
  try {
    const csrfToken = props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

    const response = await fetch(`/api/currencies/${id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(data)
    })
    
    let result: any = null
    try {
      result = await response.json()
    } catch (parseError) {
      console.warn('⚠️ Could not parse response JSON', parseError)
    }
    
    if (response.ok) {
      toast.success(result?.message || 'Currency updated successfully')
      router.reload({ only: ['companyCurrencies'] })
      return true
    } else {
      toast.error(result?.message || 'Failed to update currency')
      return false
    }
  } catch (error) {
    console.error('Failed to update currency:', error)
    toast.error('Failed to update currency')
    return false
  }
}

const handleToggleCurrency = async (id: string, active: boolean) => {
  const original = activeOverrides.value[id] ?? currencies.value.find(c => c.id === id)?.is_active ?? !active
  activeOverrides.value = { ...activeOverrides.value, [id]: active }
  togglingStates.value = { ...togglingStates.value, [id]: true }

  const success = await handleUpdateCurrency(id, { is_active: active })

  if (!success) {
    activeOverrides.value = { ...activeOverrides.value, [id]: original }
  }

  togglingStates.value = { ...togglingStates.value, [id]: false }
}

const handleDeleteCurrency = async (id: string) => {
  try {
    const response = await fetch(`/api/currencies/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': props.csrf_token || ''
      }
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.success('Currency removed successfully')
      router.reload({ only: ['companyCurrencies', 'availableCurrencies'] })
    } else {
      toast.error(result.message || 'Failed to delete currency')
    }
  } catch (error) {
    console.error('Failed to delete currency:', error)
    toast.error('Failed to delete currency')
  }
}

const handleSetBaseCurrency = async (id: string) => {
  try {
    const response = await fetch(`/api/currencies/${id}/set-base`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': props.csrf_token || ''
      }
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.success('Base currency updated successfully')
      router.reload({ only: ['companyCurrencies', 'baseCurrency'] })
    } else {
      toast.error(result.message || 'Failed to set base currency')
    }
  } catch (error) {
    console.error('Failed to set base currency:', error)
    toast.error('Failed to set base currency')
  }
}

const refreshExchangeRates = () => {
  loading.value = true
  router.reload({ 
    only: ['exchangeRates', 'companyCurrencies'],
    onFinish: () => {
      loading.value = false
      toast.success('Exchange rates refreshed')
    }
  })
}

const exportExchangeRates = () => {
  const csvContent = 'Currency,Code,Rate,Status\n' + 
    currencies.value.map(c => `${c.currency_name},${c.currency_code},${c.default_exchange_rate},${c.is_active ? 'Active' : 'Inactive'}`).join('\n')
  
  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'exchange-rates.csv'
  a.click()
  window.URL.revokeObjectURL(url)
}

const currenciesWithOverrides = computed(() =>
  currencies.value.map(c => ({
    ...c,
    is_active: activeOverrides.value[c.id] ?? c.is_active
  }))
)
</script>

<template>
  <Head title="Currency Settings" />
  
  <AppSidebarLayout>
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
      <div class="space-y-6">
      <!-- Header Section -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Currency Settings</h1>
          <p class="text-muted-foreground">
            Manage your company's currencies and exchange rates
          </p>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-3 px-4 py-2 border rounded-lg bg-muted/50">
              <Settings class="h-4 w-4 text-muted-foreground" />
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Enable Multi-Currency</label>
                <p class="text-xs text-muted-foreground">Manage multiple currencies</p>
              </div>
              <Switch
                :model-value="isMultiCurrencyEnabled"
                @update:modelValue="handleToggleMultiCurrency"
                :disabled="toggleLoading"
                class="ml-2"
              />
            </div>
          </div>
      </div>

      <!-- Quick Stats -->
      <div
        class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 transition-opacity"
        :class="{ 'opacity-50 pointer-events-none select-none': !isMultiCurrencyEnabled }"
      >
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Total Currencies</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ currencies.length }}</div>
            <p class="text-xs text-muted-foreground">
              {{ currencies.filter(c => c.is_active).length }} active
            </p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Base Currency</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">
              {{ baseCurrency?.code || 'None' }}
            </div>
            <p class="text-xs text-muted-foreground">
              Reference currency
            </p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Active Rates</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ currencies.filter(c => c.is_active).length }}</div>
            <p class="text-xs text-muted-foreground">
              Ready for transactions
            </p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Actions</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent class="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              @click="refreshExchangeRates"
              :disabled="!isMultiCurrencyEnabled || loading"
            >
              <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': loading }" />
            </Button>
            <Button
              variant="outline"
              size="sm"
              @click="exportExchangeRates"
              :disabled="!isMultiCurrencyEnabled"
            >
              <Download class="h-3 w-3" />
            </Button>
          </CardContent>
        </Card>
      </div>

      <!-- Currency Management -->
      <div v-show="isMultiCurrencyEnabled" class="space-y-4">
        <CurrencyManagementTable
          :currencies="currenciesWithOverrides"
          :loading="loading"
          :toggling-states="togglingStates"
          @add="handleAddCurrency"
          @update="handleUpdateCurrency"
          @toggle="handleToggleCurrency"
          @delete="handleDeleteCurrency"
          @set-as-base="handleSetBaseCurrency"
        />
      </div>
      <div v-show="!isMultiCurrencyEnabled" class="flex items-center justify-center p-12 border rounded-lg bg-muted/30">
        <div class="text-center">
          <Settings class="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
          <h3 class="text-lg font-semibold mb-2">Multi-Currency Disabled</h3>
          <p class="text-muted-foreground mb-4">
            Enable multi-currency management using the toggle above to manage currencies
          </p>
          <p class="text-sm text-muted-foreground">
            Currently using single currency: {{ baseCurrency?.code || 'Not configured' }}
          </p>
        </div>
      </div>
      </div>
    </div>
  </AppSidebarLayout>
</template>
