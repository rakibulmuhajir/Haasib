<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { TrendingUp, TrendingDown, Download, RefreshCw } from 'lucide-vue-next'
import { format } from 'date-fns'

interface CurrencyData {
  currency_code: string
  currency_name: string
  currency_symbol: string
  total_amount: number
  base_currency_amount: number
  transaction_count: number
  exchange_rate_used: number
  last_updated: string
}

interface ConversionSummary {
  total_foreign_amount: number
  total_base_amount: number
  total_transactions: number
  currencies_count: number
  exchange_gains_losses: number
}

interface CurrencyConversionReportProps {
  dateRange: {
    start: string
    end: string
  }
  baseCurrency: {
    code: string
    name: string
    symbol: string
  }
}

const props = defineProps<CurrencyConversionReportProps>()

const loading = ref(false)
const selectedPeriod = ref('current_month')
const currencyData = ref<CurrencyData[]>([])
const summary = ref<ConversionSummary>({
  total_foreign_amount: 0,
  total_base_amount: 0,
  total_transactions: 0,
  currencies_count: 0,
  exchange_gains_losses: 0
})

// Mock data - in real implementation, this would come from API
const mockData: CurrencyData[] = [
  {
    currency_code: 'USD',
    currency_name: 'US Dollar',
    currency_symbol: '$',
    total_amount: 150000.00,
    base_currency_amount: 150000.00,
    transaction_count: 45,
    exchange_rate_used: 1.0,
    last_updated: '2024-03-15T10:30:00Z'
  },
  {
    currency_code: 'EUR',
    currency_name: 'Euro',
    currency_symbol: '€',
    total_amount: 75000.00,
    base_currency_amount: 82125.00,
    transaction_count: 23,
    exchange_rate_used: 1.095,
    last_updated: '2024-03-15T09:45:00Z'
  },
  {
    currency_code: 'GBP',
    currency_name: 'British Pound',
    currency_symbol: '£',
    total_amount: 45000.00,
    base_currency_amount: 57600.00,
    transaction_count: 18,
    exchange_rate_used: 1.28,
    last_updated: '2024-03-14T16:20:00Z'
  }
]

const periodOptions = [
  { value: 'current_month', label: 'Current Month' },
  { value: 'last_month', label: 'Last Month' },
  { value: 'current_quarter', label: 'Current Quarter' },
  { value: 'last_quarter', label: 'Last Quarter' },
  { value: 'current_year', label: 'Current Year' },
  { value: 'custom', label: 'Custom Range' }
]

const sortedCurrencyData = computed(() => {
  return [...currencyData.value].sort((a, b) => {
    // Base currency first
    if (a.currency_code === props.baseCurrency.code) return -1
    if (b.currency_code === props.baseCurrency.code) return 1
    
    // Then by base currency amount (highest first)
    return b.base_currency_amount - a.base_currency_amount
  })
})

const formatAmount = (amount: number, currencySymbol: string) => {
  return currencySymbol + amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const formatRate = (rate: number) => {
  return rate === 1.0 ? 'Base' : rate.toFixed(6)
}

const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMM dd, yyyy HH:mm')
}

const isRateStale = (lastUpdated: string, hours = 24) => {
  const lastUpdate = new Date(lastUpdated)
  const now = new Date()
  const diffInHours = (now.getTime() - lastUpdate.getTime()) / (1000 * 60 * 60)
  return diffInHours > hours
}

const getExchangeGainLoss = (currency: CurrencyData) => {
  // Mock calculation - in real app, this would be based on actual vs current rates
  const currentRate = currency.exchange_rate_used
  const mockHistoricalRate = currentRate * 0.98 // Simulate 2% difference
  return (currency.total_amount * currentRate) - (currency.total_amount * mockHistoricalRate)
}

const refreshData = async () => {
  loading.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    currencyData.value = mockData
    
    // Calculate summary
    summary.value = {
      total_foreign_amount: currencyData.value
        .filter(c => c.currency_code !== props.baseCurrency.code)
        .reduce((sum, c) => sum + c.total_amount, 0),
      total_base_amount: currencyData.value
        .reduce((sum, c) => sum + c.base_currency_amount, 0),
      total_transactions: currencyData.value
        .reduce((sum, c) => sum + c.transaction_count, 0),
      currencies_count: currencyData.value.length,
      exchange_gains_losses: currencyData.value
        .reduce((sum, c) => sum + getExchangeGainLoss(c), 0)
    }
  } finally {
    loading.value = false
  }
}

const exportData = () => {
  // In real app, this would trigger a CSV/PDF export
  console.log('Exporting currency conversion report...')
}

onMounted(() => {
  refreshData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header and Controls -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-semibold">Currency Conversion Report</h2>
        <p class="text-muted-foreground">Multi-currency transaction analysis in {{ baseCurrency.name }} ({{ baseCurrency.code }})</p>
      </div>
      
      <div class="flex items-center gap-2">
        <Select v-model="selectedPeriod" @update:model-value="refreshData">
          <SelectTrigger class="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in periodOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        
        <Button variant="outline" @click="refreshData" :disabled="loading">
          <RefreshCw :class="['h-4 w-4 mr-2', { 'animate-spin': loading }]" />
          Refresh
        </Button>
        
        <Button variant="outline" @click="exportData">
          <Download class="h-4 w-4 mr-2" />
          Export
        </Button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ summary.total_transactions.toLocaleString() }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            Across {{ summary.currencies_count }} currencies
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Total Value ({{ baseCurrency.code }})</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatAmount(summary.total_base_amount, baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            Base currency equivalent
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Foreign Currency Total</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatAmount(summary.total_foreign_amount, '$') }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            Combined foreign currencies
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Exchange Gains/Losses</CardTitle>
        </CardHeader>
        <CardContent>
          <div :class="[
            'text-2xl font-bold flex items-center gap-1',
            summary.exchange_gains_losses >= 0 ? 'text-green-600' : 'text-red-600'
          ]">
            <TrendingUp v-if="summary.exchange_gains_losses >= 0" class="h-4 w-4" />
            <TrendingDown v-else class="h-4 w-4" />
            {{ formatAmount(Math.abs(summary.exchange_gains_losses), baseCurrency.symbol) }}
          </div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ summary.exchange_gains_losses >= 0 ? 'Unrealized gains' : 'Unrealized losses' }}
          </p>
        </CardContent>
      </Card>
    </div>

    <!-- Currency Breakdown Table -->
    <Card>
      <CardHeader>
        <CardTitle>Currency Breakdown</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="border rounded-md">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Currency</TableHead>
                <TableHead>Transactions</TableHead>
                <TableHead>Original Amount</TableHead>
                <TableHead>{{ baseCurrency.code }} Equivalent</TableHead>
                <TableHead>Exchange Rate</TableHead>
                <TableHead>Last Updated</TableHead>
                <TableHead>Gain/Loss</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="loading">
                <TableCell colspan="7" class="text-center py-8">
                  Loading currency data...
                </TableCell>
              </TableRow>
              <TableRow v-else v-for="currency in sortedCurrencyData" :key="currency.currency_code">
                <TableCell>
                  <div class="flex items-center gap-2">
                    <span class="font-mono w-8">{{ currency.currency_symbol }}</span>
                    <div>
                      <div class="font-medium">{{ currency.currency_code }}</div>
                      <div class="text-sm text-muted-foreground">{{ currency.currency_name }}</div>
                    </div>
                    <Badge v-if="currency.currency_code === baseCurrency.code" variant="default">Base</Badge>
                  </div>
                </TableCell>
                <TableCell>
                  <div class="font-medium">{{ currency.transaction_count }}</div>
                </TableCell>
                <TableCell>
                  <div class="font-mono">{{ formatAmount(currency.total_amount, currency.currency_symbol) }}</div>
                </TableCell>
                <TableCell>
                  <div class="font-mono">{{ formatAmount(currency.base_currency_amount, baseCurrency.symbol) }}</div>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-2">
                    <span class="font-mono">{{ formatRate(currency.exchange_rate_used) }}</span>
                    <Badge 
                      v-if="isRateStale(currency.last_updated)" 
                      variant="destructive" 
                      class="text-xs"
                    >
                      Stale
                    </Badge>
                  </div>
                </TableCell>
                <TableCell>
                  <div class="text-sm">{{ formatDate(currency.last_updated) }}</div>
                </TableCell>
                <TableCell>
                  <div :class="[
                    'font-medium flex items-center gap-1',
                    getExchangeGainLoss(currency) >= 0 ? 'text-green-600' : 'text-red-600'
                  ]">
                    <TrendingUp v-if="getExchangeGainLoss(currency) >= 0" class="h-3 w-3" />
                    <TrendingDown v-else class="h-3 w-3" />
                    {{ formatAmount(Math.abs(getExchangeGainLoss(currency)), baseCurrency.symbol) }}
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>