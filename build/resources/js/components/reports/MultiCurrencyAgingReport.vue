<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Download, RefreshCw, AlertTriangle } from 'lucide-vue-next'

interface AgingBucket {
  current: number // 0-30 days
  days_30: number // 31-60 days  
  days_60: number // 61-90 days
  days_90: number // 90+ days
}

interface CustomerAging {
  customer_id: string
  customer_name: string
  currency_code: string
  currency_symbol: string
  aging: AgingBucket
  total_outstanding: number
  base_currency_total: number
  exchange_rate: number
  overdue_amount: number
  last_payment_date?: string
  credit_limit?: number
}

interface CurrencyAgingSummary {
  currency_code: string
  currency_name: string
  currency_symbol: string
  customer_count: number
  aging: AgingBucket
  total_outstanding: number
  base_currency_total: number
  avg_exchange_rate: number
}

interface MultiCurrencyAgingReportProps {
  baseCurrency: {
    code: string
    name: string
    symbol: string
  }
}

const props = defineProps<MultiCurrencyAgingReportProps>()

const loading = ref(false)
const selectedView = ref('by_currency')
const selectedCurrency = ref('all')
const customerAging = ref<CustomerAging[]>([])
const currencySummary = ref<CurrencyAgingSummary[]>([])

// Mock data
const mockCustomerData: CustomerAging[] = [
  {
    customer_id: '1',
    customer_name: 'Acme Corp',
    currency_code: 'USD',
    currency_symbol: '$',
    aging: { current: 15000, days_30: 8000, days_60: 3000, days_90: 2000 },
    total_outstanding: 28000,
    base_currency_total: 28000,
    exchange_rate: 1.0,
    overdue_amount: 13000,
    last_payment_date: '2024-02-15',
    credit_limit: 50000
  },
  {
    customer_id: '2',
    customer_name: 'European Ltd',
    currency_code: 'EUR',
    currency_symbol: '€',
    aging: { current: 12000, days_30: 5000, days_60: 0, days_90: 1000 },
    total_outstanding: 18000,
    base_currency_total: 19710,
    exchange_rate: 1.095,
    overdue_amount: 6000,
    last_payment_date: '2024-02-28',
    credit_limit: 30000
  },
  {
    customer_id: '3',
    customer_name: 'UK Trading',
    currency_code: 'GBP',
    currency_symbol: '£',
    aging: { current: 8000, days_30: 4000, days_60: 2000, days_90: 0 },
    total_outstanding: 14000,
    base_currency_total: 17920,
    exchange_rate: 1.28,
    overdue_amount: 6000,
    last_payment_date: '2024-03-05',
    credit_limit: 25000
  }
]

const availableCurrencies = computed(() => {
  const currencies = [...new Set(customerAging.value.map(c => c.currency_code))]
  return currencies.map(code => {
    const customer = customerAging.value.find(c => c.currency_code === code)
    return {
      code,
      name: customer?.currency_code || code,
      symbol: customer?.currency_symbol || code
    }
  })
})

const filteredCustomers = computed(() => {
  if (selectedCurrency.value === 'all') {
    return customerAging.value
  }
  return customerAging.value.filter(c => c.currency_code === selectedCurrency.value)
})

const totalSummary = computed(() => {
  const totals = filteredCustomers.value.reduce(
    (acc, customer) => {
      acc.current += customer.aging.current
      acc.days_30 += customer.aging.days_30
      acc.days_60 += customer.aging.days_60
      acc.days_90 += customer.aging.days_90
      acc.total_outstanding += customer.total_outstanding
      acc.base_currency_total += customer.base_currency_total
      return acc
    },
    {
      current: 0,
      days_30: 0,
      days_60: 0,
      days_90: 0,
      total_outstanding: 0,
      base_currency_total: 0
    }
  )

  return {
    ...totals,
    overdue_amount: totals.days_30 + totals.days_60 + totals.days_90,
    overdue_percentage: totals.total_outstanding > 0 
      ? ((totals.days_30 + totals.days_60 + totals.days_90) / totals.total_outstanding) * 100 
      : 0
  }
})

const formatAmount = (amount: number, currencySymbol: string) => {
  return currencySymbol + amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const getAgingBadgeVariant = (days: string) => {
  switch (days) {
    case 'current': return 'default'
    case 'days_30': return 'secondary'
    case 'days_60': return 'destructive'
    case 'days_90': return 'destructive'
    default: return 'secondary'
  }
}

const getRiskLevel = (customer: CustomerAging) => {
  const overduePercentage = customer.total_outstanding > 0 
    ? (customer.overdue_amount / customer.total_outstanding) * 100 
    : 0

  if (overduePercentage > 50) return { level: 'High', variant: 'destructive' }
  if (overduePercentage > 25) return { level: 'Medium', variant: 'secondary' }
  return { level: 'Low', variant: 'default' }
}

const refreshData = async () => {
  loading.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 1000))
    customerAging.value = mockCustomerData

    // Calculate currency summary
    const currencyGroups = customerAging.value.reduce((groups, customer) => {
      const currency = customer.currency_code
      if (!groups[currency]) {
        groups[currency] = {
          currency_code: currency,
          currency_name: currency,
          currency_symbol: customer.currency_symbol,
          customers: [],
          aging: { current: 0, days_30: 0, days_60: 0, days_90: 0 },
          total_outstanding: 0,
          base_currency_total: 0,
          total_exchange_rate: 0
        }
      }
      
      groups[currency].customers.push(customer)
      groups[currency].aging.current += customer.aging.current
      groups[currency].aging.days_30 += customer.aging.days_30
      groups[currency].aging.days_60 += customer.aging.days_60
      groups[currency].aging.days_90 += customer.aging.days_90
      groups[currency].total_outstanding += customer.total_outstanding
      groups[currency].base_currency_total += customer.base_currency_total
      groups[currency].total_exchange_rate += customer.exchange_rate
      
      return groups
    }, {} as any)

    currencySummary.value = Object.values(currencyGroups).map((group: any) => ({
      currency_code: group.currency_code,
      currency_name: group.currency_name,
      currency_symbol: group.currency_symbol,
      customer_count: group.customers.length,
      aging: group.aging,
      total_outstanding: group.total_outstanding,
      base_currency_total: group.base_currency_total,
      avg_exchange_rate: group.total_exchange_rate / group.customers.length
    }))

  } finally {
    loading.value = false
  }
}

const exportData = () => {
  console.log('Exporting aging report...')
}

onMounted(() => {
  refreshData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-semibold">Multi-Currency Aging Report</h2>
        <p class="text-muted-foreground">Customer aging analysis across all currencies</p>
      </div>
      
      <div class="flex items-center gap-2">
        <Select v-model="selectedCurrency" @update:model-value="refreshData">
          <SelectTrigger class="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Currencies</SelectItem>
            <SelectItem 
              v-for="currency in availableCurrencies" 
              :key="currency.code" 
              :value="currency.code"
            >
              {{ currency.code }} - {{ currency.symbol }}
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
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Total Outstanding</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatAmount(totalSummary.base_currency_total, props.baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">Base currency ({{ props.baseCurrency.code }})</p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Current (0-30 days)</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-green-600">{{ formatAmount(totalSummary.current, props.baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ ((totalSummary.current / totalSummary.total_outstanding) * 100).toFixed(1) }}% of total
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Overdue</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-red-600">{{ formatAmount(totalSummary.overdue_amount, props.baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ totalSummary.overdue_percentage.toFixed(1) }}% of total
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">90+ Days</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-red-800">{{ formatAmount(totalSummary.days_90, props.baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">Critical aging</p>
        </CardContent>
      </Card>
    </div>

    <!-- Tabbed Views -->
    <Tabs v-model="selectedView" class="space-y-4">
      <TabsList>
        <TabsTrigger value="by_customer">By Customer</TabsTrigger>
        <TabsTrigger value="by_currency">By Currency</TabsTrigger>
      </TabsList>

      <!-- Customer Detail View -->
      <TabsContent value="by_customer">
        <Card>
          <CardHeader>
            <CardTitle>Customer Aging Detail</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Customer</TableHead>
                  <TableHead>Currency</TableHead>
                  <TableHead>Current</TableHead>
                  <TableHead>31-60 Days</TableHead>
                  <TableHead>61-90 Days</TableHead>
                  <TableHead>90+ Days</TableHead>
                  <TableHead>Total Outstanding</TableHead>
                  <TableHead>{{ props.baseCurrency.code }} Equivalent</TableHead>
                  <TableHead>Risk</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="loading">
                  <TableCell colspan="9" class="text-center py-8">Loading aging data...</TableCell>
                </TableRow>
                <TableRow v-else v-for="customer in filteredCustomers" :key="customer.customer_id">
                  <TableCell>
                    <div>
                      <div class="font-medium">{{ customer.customer_name }}</div>
                      <div v-if="customer.last_payment_date" class="text-sm text-muted-foreground">
                        Last payment: {{ new Date(customer.last_payment_date).toLocaleDateString() }}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">
                      {{ customer.currency_symbol }} {{ customer.currency_code }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="text-green-600 font-mono">
                      {{ formatAmount(customer.aging.current, customer.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-yellow-600 font-mono">
                      {{ formatAmount(customer.aging.days_30, customer.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-orange-600 font-mono">
                      {{ formatAmount(customer.aging.days_60, customer.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-red-600 font-mono">
                      {{ formatAmount(customer.aging.days_90, customer.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono font-medium">
                      {{ formatAmount(customer.total_outstanding, customer.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">
                      {{ formatAmount(customer.base_currency_total, props.baseCurrency.symbol) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                      Rate: {{ customer.exchange_rate.toFixed(6) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="getRiskLevel(customer).variant">
                      {{ getRiskLevel(customer).level }}
                    </Badge>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Currency Summary View -->
      <TabsContent value="by_currency">
        <Card>
          <CardHeader>
            <CardTitle>Currency Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Currency</TableHead>
                  <TableHead>Customers</TableHead>
                  <TableHead>Current</TableHead>
                  <TableHead>31-60 Days</TableHead>
                  <TableHead>61-90 Days</TableHead>
                  <TableHead>90+ Days</TableHead>
                  <TableHead>Total Outstanding</TableHead>
                  <TableHead>{{ props.baseCurrency.code }} Equivalent</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="currency in currencySummary" :key="currency.currency_code">
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <span class="font-mono">{{ currency.currency_symbol }}</span>
                      <div>
                        <div class="font-medium">{{ currency.currency_code }}</div>
                        <div class="text-sm text-muted-foreground">{{ currency.currency_name }}</div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{{ currency.customer_count }}</Badge>
                  </TableCell>
                  <TableCell>
                    <div class="text-green-600 font-mono">
                      {{ formatAmount(currency.aging.current, currency.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-yellow-600 font-mono">
                      {{ formatAmount(currency.aging.days_30, currency.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-orange-600 font-mono">
                      {{ formatAmount(currency.aging.days_60, currency.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-red-600 font-mono">
                      {{ formatAmount(currency.aging.days_90, currency.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono font-medium">
                      {{ formatAmount(currency.total_outstanding, currency.currency_symbol) }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">
                      {{ formatAmount(currency.base_currency_total, props.baseCurrency.symbol) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                      Avg rate: {{ currency.avg_exchange_rate.toFixed(6) }}
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>