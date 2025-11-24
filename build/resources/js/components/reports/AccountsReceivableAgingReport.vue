<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { AlertCircle, Calendar, Download, RefreshCw } from 'lucide-vue-next'
import { format } from 'date-fns'

interface AgingData {
  customer_id: string
  customer_name: string
  customer_code: string
  currency_code: string
  currency_symbol: string
  current: number
  days_1_30: number
  days_31_60: number
  days_61_90: number
  days_over_90: number
  total_outstanding: number
  base_currency_total: number
  overdue_amount: number
  largest_invoice_amount: number
  largest_invoice_date: string
  last_payment_date?: string
  invoice_count: number
}

interface AgingSummary {
  total_customers: number
  total_outstanding_original: number
  total_outstanding_base: number
  current_percentage: number
  overdue_percentage: number
  average_days_outstanding: number
  by_currency: Record<string, {
    currency_name: string
    currency_symbol: string
    total_amount: number
    customer_count: number
  }>
}

interface AccountsReceivableAgingReportProps {
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

const props = defineProps<AccountsReceivableAgingReportProps>()

const loading = ref(false)
const selectedCurrency = ref('all')
const selectedAging = ref('all')
const agingData = ref<AgingData[]>([])
const summary = ref<AgingSummary>({
  total_customers: 0,
  total_outstanding_original: 0,
  total_outstanding_base: 0,
  current_percentage: 0,
  overdue_percentage: 0,
  average_days_outstanding: 0,
  by_currency: {}
})

// Mock data - in real implementation, this would come from API
const mockData: AgingData[] = [
  {
    customer_id: '1',
    customer_name: 'Acme Corporation',
    customer_code: 'ACME001',
    currency_code: 'USD',
    currency_symbol: '$',
    current: 15000.00,
    days_1_30: 8500.00,
    days_31_60: 3200.00,
    days_61_90: 1800.00,
    days_over_90: 2500.00,
    total_outstanding: 31000.00,
    base_currency_total: 31000.00,
    overdue_amount: 16000.00,
    largest_invoice_amount: 12000.00,
    largest_invoice_date: '2024-03-01T00:00:00Z',
    last_payment_date: '2024-03-10T00:00:00Z',
    invoice_count: 8
  },
  {
    customer_id: '2',
    customer_name: 'Global Trading Ltd',
    customer_code: 'GLOB001',
    currency_code: 'EUR',
    currency_symbol: '€',
    current: 22000.00,
    days_1_30: 15000.00,
    days_31_60: 8000.00,
    days_61_90: 0.00,
    days_over_90: 5000.00,
    total_outstanding: 50000.00,
    base_currency_total: 54750.00,
    overdue_amount: 28000.00,
    largest_invoice_amount: 18000.00,
    largest_invoice_date: '2024-02-15T00:00:00Z',
    last_payment_date: '2024-02-28T00:00:00Z',
    invoice_count: 12
  },
  {
    customer_id: '3',
    customer_name: 'Tech Innovations UK',
    customer_code: 'TECH001',
    currency_code: 'GBP',
    currency_symbol: '£',
    current: 8500.00,
    days_1_30: 4200.00,
    days_31_60: 2800.00,
    days_61_90: 1500.00,
    days_over_90: 0.00,
    total_outstanding: 17000.00,
    base_currency_total: 21760.00,
    overdue_amount: 8500.00,
    largest_invoice_amount: 6500.00,
    largest_invoice_date: '2024-03-08T00:00:00Z',
    invoice_count: 6
  }
]

const currencyOptions = [
  { value: 'all', label: 'All Currencies' },
  { value: 'USD', label: 'US Dollar' },
  { value: 'EUR', label: 'Euro' },
  { value: 'GBP', label: 'British Pound' }
]

const agingOptions = [
  { value: 'all', label: 'All Ages' },
  { value: 'current', label: 'Current Only' },
  { value: 'overdue', label: 'Overdue Only' },
  { value: '30+', label: '30+ Days' },
  { value: '60+', label: '60+ Days' },
  { value: '90+', label: '90+ Days' }
]

const filteredData = computed(() => {
  let data = [...agingData.value]
  
  if (selectedCurrency.value !== 'all') {
    data = data.filter(item => item.currency_code === selectedCurrency.value)
  }
  
  if (selectedAging.value !== 'all') {
    switch (selectedAging.value) {
      case 'current':
        data = data.filter(item => item.overdue_amount === 0)
        break
      case 'overdue':
        data = data.filter(item => item.overdue_amount > 0)
        break
      case '30+':
        data = data.filter(item => (item.days_31_60 + item.days_61_90 + item.days_over_90) > 0)
        break
      case '60+':
        data = data.filter(item => (item.days_61_90 + item.days_over_90) > 0)
        break
      case '90+':
        data = data.filter(item => item.days_over_90 > 0)
        break
    }
  }
  
  return data.sort((a, b) => b.total_outstanding - a.total_outstanding)
})

const formatAmount = (amount: number, currencySymbol: string) => {
  return currencySymbol + amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const formatDate = (dateString?: string) => {
  if (!dateString) return 'Never'
  return format(new Date(dateString), 'MMM dd, yyyy')
}

const getRiskLevel = (agingData: AgingData) => {
  const overduePercentage = (agingData.overdue_amount / agingData.total_outstanding) * 100
  
  if (overduePercentage >= 50) return 'high'
  if (overduePercentage >= 25) return 'medium'
  return 'low'
}

const getRiskBadgeVariant = (risk: string) => {
  switch (risk) {
    case 'high': return 'destructive'
    case 'medium': return 'secondary'
    case 'low': return 'default'
    default: return 'outline'
  }
}

const refreshData = async () => {
  loading.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    agingData.value = mockData
    
    // Calculate summary
    const totalCustomers = agingData.value.length
    const totalOriginal = agingData.value.reduce((sum, item) => sum + item.total_outstanding, 0)
    const totalBase = agingData.value.reduce((sum, item) => sum + item.base_currency_total, 0)
    const totalCurrent = agingData.value.reduce((sum, item) => sum + item.current, 0)
    const totalOverdue = agingData.value.reduce((sum, item) => sum + item.overdue_amount, 0)
    
    // Group by currency
    const byCurrency: Record<string, any> = {}
    agingData.value.forEach(item => {
      if (!byCurrency[item.currency_code]) {
        byCurrency[item.currency_code] = {
          currency_name: item.currency_code,
          currency_symbol: item.currency_symbol,
          total_amount: 0,
          customer_count: 0
        }
      }
      byCurrency[item.currency_code].total_amount += item.total_outstanding
      byCurrency[item.currency_code].customer_count += 1
    })
    
    summary.value = {
      total_customers: totalCustomers,
      total_outstanding_original: totalOriginal,
      total_outstanding_base: totalBase,
      current_percentage: totalOriginal > 0 ? (totalCurrent / totalOriginal) * 100 : 0,
      overdue_percentage: totalOriginal > 0 ? (totalOverdue / totalOriginal) * 100 : 0,
      average_days_outstanding: 45, // Mock calculation
      by_currency: byCurrency
    }
  } finally {
    loading.value = false
  }
}

const exportData = () => {
  // In real app, this would trigger a CSV/PDF export
  console.log('Exporting aging report...')
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
        <h2 class="text-2xl font-semibold">Accounts Receivable Aging</h2>
        <p class="text-muted-foreground">Outstanding customer balances by age periods</p>
      </div>
      
      <div class="flex items-center gap-2">
        <Select v-model="selectedCurrency">
          <SelectTrigger class="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in currencyOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        
        <Select v-model="selectedAging">
          <SelectTrigger class="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in agingOptions" :key="option.value" :value="option.value">
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
          <CardTitle class="text-sm font-medium">Total Outstanding</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatAmount(summary.total_outstanding_base, baseCurrency.symbol) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ summary.total_customers }} customers
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Current %</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-green-600">{{ summary.current_percentage.toFixed(1) }}%</div>
          <p class="text-xs text-muted-foreground mt-1">
            Not yet due
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Overdue %</CardTitle>
        </CardHeader>
        <CardContent>
          <div :class="[
            'text-2xl font-bold',
            summary.overdue_percentage > 25 ? 'text-red-600' : 'text-yellow-600'
          ]">
            {{ summary.overdue_percentage.toFixed(1) }}%
          </div>
          <p class="text-xs text-muted-foreground mt-1">
            Past due date
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Avg Days Outstanding</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ summary.average_days_outstanding }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            Days on average
          </p>
        </CardContent>
      </Card>
    </div>

    <!-- Currency Breakdown -->
    <Card v-if="Object.keys(summary.by_currency).length > 1">
      <CardHeader>
        <CardTitle>By Currency</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div 
            v-for="(data, currencyCode) in summary.by_currency" 
            :key="currencyCode"
            class="flex items-center justify-between p-3 border rounded-lg"
          >
            <div>
              <div class="font-medium">{{ currencyCode }}</div>
              <div class="text-sm text-muted-foreground">{{ data.customer_count }} customers</div>
            </div>
            <div class="text-right">
              <div class="font-medium">{{ formatAmount(data.total_amount, data.currency_symbol) }}</div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Aging Details Table -->
    <Card>
      <CardHeader>
        <CardTitle>Customer Aging Details</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="border rounded-md">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Currency</TableHead>
                <TableHead>Current</TableHead>
                <TableHead>1-30 Days</TableHead>
                <TableHead>31-60 Days</TableHead>
                <TableHead>61-90 Days</TableHead>
                <TableHead>90+ Days</TableHead>
                <TableHead>Total Outstanding</TableHead>
                <TableHead>Risk</TableHead>
                <TableHead>Last Payment</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="loading">
                <TableCell colspan="10" class="text-center py-8">
                  Loading aging data...
                </TableCell>
              </TableRow>
              <TableRow v-else-if="filteredData.length === 0">
                <TableCell colspan="10" class="text-center py-8 text-muted-foreground">
                  No data available for selected filters
                </TableCell>
              </TableRow>
              <TableRow v-else v-for="customer in filteredData" :key="customer.customer_id">
                <TableCell>
                  <div>
                    <div class="font-medium">{{ customer.customer_name }}</div>
                    <div class="text-sm text-muted-foreground">{{ customer.customer_code }}</div>
                    <div class="text-xs text-muted-foreground">{{ customer.invoice_count }} invoices</div>
                  </div>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-1">
                    <span class="font-mono text-sm">{{ customer.currency_symbol }}</span>
                    <span class="font-medium">{{ customer.currency_code }}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <div class="font-mono">{{ formatAmount(customer.current, customer.currency_symbol) }}</div>
                </TableCell>
                <TableCell>
                  <div :class="[
                    'font-mono',
                    customer.days_1_30 > 0 ? 'text-yellow-600' : ''
                  ]">
                    {{ formatAmount(customer.days_1_30, customer.currency_symbol) }}
                  </div>
                </TableCell>
                <TableCell>
                  <div :class="[
                    'font-mono',
                    customer.days_31_60 > 0 ? 'text-orange-600' : ''
                  ]">
                    {{ formatAmount(customer.days_31_60, customer.currency_symbol) }}
                  </div>
                </TableCell>
                <TableCell>
                  <div :class="[
                    'font-mono',
                    customer.days_61_90 > 0 ? 'text-red-500' : ''
                  ]">
                    {{ formatAmount(customer.days_61_90, customer.currency_symbol) }}
                  </div>
                </TableCell>
                <TableCell>
                  <div :class="[
                    'font-mono',
                    customer.days_over_90 > 0 ? 'text-red-700' : ''
                  ]">
                    {{ formatAmount(customer.days_over_90, customer.currency_symbol) }}
                  </div>
                </TableCell>
                <TableCell>
                  <div>
                    <div class="font-mono font-medium">{{ formatAmount(customer.total_outstanding, customer.currency_symbol) }}</div>
                    <div v-if="customer.currency_code !== baseCurrency.code" class="text-xs text-muted-foreground">
                      {{ formatAmount(customer.base_currency_total, baseCurrency.symbol) }}
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge :variant="getRiskBadgeVariant(getRiskLevel(customer))">
                    {{ getRiskLevel(customer) }}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-1 text-sm">
                    <Calendar class="h-3 w-3" />
                    <span>{{ formatDate(customer.last_payment_date) }}</span>
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