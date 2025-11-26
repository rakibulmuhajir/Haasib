<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { TrendingUp, TrendingDown, Download, RefreshCw, AlertTriangle } from 'lucide-vue-next'
import { format } from 'date-fns'

interface AccountLine {
  account_id: string
  account_code: string
  account_name: string
  account_type: 'revenue' | 'expense' | 'cost_of_goods_sold'
  original_currency: string
  original_amount: number
  base_currency_amount: number
  exchange_gains_losses: number
  transaction_count: number
  percentage_of_category: number
}

interface ProfitLossSection {
  title: string
  accounts: AccountLine[]
  total_original: Record<string, number>
  total_base: number
  percentage_of_revenue: number
}

interface ProfitLossSummary {
  revenue: ProfitLossSection
  cost_of_goods_sold: ProfitLossSection
  operating_expenses: ProfitLossSection
  gross_profit: number
  operating_profit: number
  net_profit: number
  gross_margin_percentage: number
  operating_margin_percentage: number
  net_margin_percentage: number
  total_exchange_gains_losses: number
  currency_breakdown: Record<string, {
    currency_name: string
    currency_symbol: string
    revenue: number
    expenses: number
    net_impact: number
  }>
}

interface ProfitLossReportProps {
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

const props = defineProps<ProfitLossReportProps>()

const loading = ref(false)
const selectedCurrency = ref('all')
const showCurrencyBreakdown = ref(true)
const data = ref<ProfitLossSummary>({
  revenue: {
    title: 'Revenue',
    accounts: [],
    total_original: {},
    total_base: 0,
    percentage_of_revenue: 100
  },
  cost_of_goods_sold: {
    title: 'Cost of Goods Sold',
    accounts: [],
    total_original: {},
    total_base: 0,
    percentage_of_revenue: 0
  },
  operating_expenses: {
    title: 'Operating Expenses',
    accounts: [],
    total_original: {},
    total_base: 0,
    percentage_of_revenue: 0
  },
  gross_profit: 0,
  operating_profit: 0,
  net_profit: 0,
  gross_margin_percentage: 0,
  operating_margin_percentage: 0,
  net_margin_percentage: 0,
  total_exchange_gains_losses: 0,
  currency_breakdown: {}
})

// Mock data - in real implementation, this would come from API
const mockRevenue: AccountLine[] = [
  {
    account_id: '4000',
    account_code: '4000',
    account_name: 'Sales Revenue',
    account_type: 'revenue',
    original_currency: 'USD',
    original_amount: 250000.00,
    base_currency_amount: 250000.00,
    exchange_gains_losses: 0,
    transaction_count: 45,
    percentage_of_category: 62.5
  },
  {
    account_id: '4010',
    account_code: '4010',
    account_name: 'Service Revenue',
    account_type: 'revenue',
    original_currency: 'EUR',
    original_amount: 120000.00,
    base_currency_amount: 131400.00,
    exchange_gains_losses: 2400.00,
    transaction_count: 28,
    percentage_of_category: 30.0
  },
  {
    account_id: '4020',
    account_code: '4020',
    account_name: 'Consulting Revenue',
    account_type: 'revenue',
    original_currency: 'GBP',
    original_amount: 25000.00,
    base_currency_amount: 32000.00,
    exchange_gains_losses: 800.00,
    transaction_count: 12,
    percentage_of_category: 7.5
  }
]

const mockExpenses: AccountLine[] = [
  {
    account_id: '5000',
    account_code: '5000',
    account_name: 'Cost of Goods Sold',
    account_type: 'cost_of_goods_sold',
    original_currency: 'USD',
    original_amount: 125000.00,
    base_currency_amount: 125000.00,
    exchange_gains_losses: 0,
    transaction_count: 22,
    percentage_of_category: 70.0
  },
  {
    account_id: '5010',
    account_code: '5010',
    account_name: 'Direct Materials',
    account_type: 'cost_of_goods_sold',
    original_currency: 'EUR',
    original_amount: 45000.00,
    base_currency_amount: 49275.00,
    exchange_gains_losses: -675.00,
    transaction_count: 18,
    percentage_of_category: 30.0
  },
  {
    account_id: '6000',
    account_code: '6000',
    account_name: 'Salaries & Wages',
    account_type: 'expense',
    original_currency: 'USD',
    original_amount: 85000.00,
    base_currency_amount: 85000.00,
    exchange_gains_losses: 0,
    transaction_count: 12,
    percentage_of_category: 45.0
  },
  {
    account_id: '6100',
    account_code: '6100',
    account_name: 'Office Rent',
    account_type: 'expense',
    original_currency: 'USD',
    original_amount: 36000.00,
    base_currency_amount: 36000.00,
    exchange_gains_losses: 0,
    transaction_count: 12,
    percentage_of_category: 19.0
  },
  {
    account_id: '6200',
    account_code: '6200',
    account_name: 'Marketing & Advertising',
    account_type: 'expense',
    original_currency: 'EUR',
    original_amount: 25000.00,
    base_currency_amount: 27375.00,
    exchange_gains_losses: -375.00,
    transaction_count: 15,
    percentage_of_category: 14.5
  },
  {
    account_id: '6300',
    account_code: '6300',
    account_name: 'Professional Services',
    account_type: 'expense',
    original_currency: 'GBP',
    original_amount: 15000.00,
    base_currency_amount: 19200.00,
    exchange_gains_losses: 200.00,
    transaction_count: 8,
    percentage_of_category: 10.2
  },
  {
    account_id: '6400',
    account_code: '6400',
    account_name: 'Travel & Entertainment',
    account_type: 'expense',
    original_currency: 'USD',
    original_amount: 22000.00,
    base_currency_amount: 22000.00,
    exchange_gains_losses: 0,
    transaction_count: 25,
    percentage_of_category: 11.7
  }
]

const currencyOptions = [
  { value: 'all', label: 'All Currencies' },
  { value: 'USD', label: 'US Dollar' },
  { value: 'EUR', label: 'Euro' },
  { value: 'GBP', label: 'British Pound' }
]

const formatAmount = (amount: number, currencySymbol: string = props.baseCurrency.symbol) => {
  const decimals = 2
  return currencySymbol + amount.toLocaleString(undefined, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  })
}

const formatPercentage = (percentage: number) => {
  return percentage.toFixed(1) + '%'
}

const getCurrencySymbol = (currencyCode: string) => {
  const symbols: Record<string, string> = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£'
  }
  return symbols[currencyCode] || currencyCode
}

const refreshData = async () => {
  loading.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    // Organize data by sections
    const revenue = mockRevenue
    const costOfGoodsSold = mockExpenses.filter(exp => exp.account_type === 'cost_of_goods_sold')
    const operatingExpenses = mockExpenses.filter(exp => exp.account_type === 'expense')
    
    // Calculate totals
    const totalRevenue = revenue.reduce((sum, acc) => sum + acc.base_currency_amount, 0)
    const totalCOGS = costOfGoodsSold.reduce((sum, acc) => sum + acc.base_currency_amount, 0)
    const totalOpEx = operatingExpenses.reduce((sum, acc) => sum + acc.base_currency_amount, 0)
    
    const grossProfit = totalRevenue - totalCOGS
    const operatingProfit = grossProfit - totalOpEx
    const netProfit = operatingProfit
    
    // Calculate exchange gains/losses
    const totalExchangeGL = [...revenue, ...mockExpenses.filter(exp => exp.account_type !== 'cost_of_goods_sold')]
      .reduce((sum, acc) => sum + acc.exchange_gains_losses, 0)
    
    // Currency breakdown
    const currencyBreakdown: Record<string, any> = {}
    ;[...revenue, ...mockExpenses].forEach(acc => {
      if (!currencyBreakdown[acc.original_currency]) {
        currencyBreakdown[acc.original_currency] = {
          currency_name: acc.original_currency,
          currency_symbol: getCurrencySymbol(acc.original_currency),
          revenue: 0,
          expenses: 0,
          net_impact: 0
        }
      }
      
      if (acc.account_type === 'revenue') {
        currencyBreakdown[acc.original_currency].revenue += acc.base_currency_amount
      } else {
        currencyBreakdown[acc.original_currency].expenses += acc.base_currency_amount
      }
      
      currencyBreakdown[acc.original_currency].net_impact = 
        currencyBreakdown[acc.original_currency].revenue - 
        currencyBreakdown[acc.original_currency].expenses
    })
    
    data.value = {
      revenue: {
        title: 'Revenue',
        accounts: revenue,
        total_original: revenue.reduce((acc, curr) => {
          acc[curr.original_currency] = (acc[curr.original_currency] || 0) + curr.original_amount
          return acc
        }, {} as Record<string, number>),
        total_base: totalRevenue,
        percentage_of_revenue: 100
      },
      cost_of_goods_sold: {
        title: 'Cost of Goods Sold',
        accounts: costOfGoodsSold,
        total_original: costOfGoodsSold.reduce((acc, curr) => {
          acc[curr.original_currency] = (acc[curr.original_currency] || 0) + curr.original_amount
          return acc
        }, {} as Record<string, number>),
        total_base: totalCOGS,
        percentage_of_revenue: totalRevenue > 0 ? (totalCOGS / totalRevenue) * 100 : 0
      },
      operating_expenses: {
        title: 'Operating Expenses',
        accounts: operatingExpenses,
        total_original: operatingExpenses.reduce((acc, curr) => {
          acc[curr.original_currency] = (acc[curr.original_currency] || 0) + curr.original_amount
          return acc
        }, {} as Record<string, number>),
        total_base: totalOpEx,
        percentage_of_revenue: totalRevenue > 0 ? (totalOpEx / totalRevenue) * 100 : 0
      },
      gross_profit: grossProfit,
      operating_profit: operatingProfit,
      net_profit: netProfit,
      gross_margin_percentage: totalRevenue > 0 ? (grossProfit / totalRevenue) * 100 : 0,
      operating_margin_percentage: totalRevenue > 0 ? (operatingProfit / totalRevenue) * 100 : 0,
      net_margin_percentage: totalRevenue > 0 ? (netProfit / totalRevenue) * 100 : 0,
      total_exchange_gains_losses: totalExchangeGL,
      currency_breakdown: currencyBreakdown
    }
  } finally {
    loading.value = false
  }
}

const exportData = () => {
  // In real app, this would trigger a CSV/PDF export
  console.log('Exporting P&L report...')
}

const renderSection = (section: ProfitLossSection, isExpense = false) => {
  return section.accounts.filter(acc => 
    selectedCurrency.value === 'all' || acc.original_currency === selectedCurrency.value
  )
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
        <h2 class="text-2xl font-semibold">Profit & Loss Statement</h2>
        <p class="text-muted-foreground">Multi-currency income statement for {{ format(new Date(dateRange.start), 'MMM dd') }} - {{ format(new Date(dateRange.end), 'MMM dd, yyyy') }}</p>
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
        
        <Button variant="outline" @click="showCurrencyBreakdown = !showCurrencyBreakdown">
          Currency Details
        </Button>
        
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

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Total Revenue</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-green-600">{{ formatAmount(data.revenue.total_base) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            Base currency equivalent
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Gross Profit</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatAmount(data.gross_profit) }}</div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ formatPercentage(data.gross_margin_percentage) }} margin
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Operating Profit</CardTitle>
        </CardHeader>
        <CardContent>
          <div :class="[
            'text-2xl font-bold',
            data.operating_profit >= 0 ? 'text-green-600' : 'text-red-600'
          ]">
            {{ formatAmount(data.operating_profit) }}
          </div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ formatPercentage(data.operating_margin_percentage) }} margin
          </p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Net Profit</CardTitle>
        </CardHeader>
        <CardContent>
          <div :class="[
            'text-2xl font-bold flex items-center gap-1',
            data.net_profit >= 0 ? 'text-green-600' : 'text-red-600'
          ]">
            <TrendingUp v-if="data.net_profit >= 0" class="h-4 w-4" />
            <TrendingDown v-else class="h-4 w-4" />
            {{ formatAmount(Math.abs(data.net_profit)) }}
          </div>
          <p class="text-xs text-muted-foreground mt-1">
            {{ formatPercentage(data.net_margin_percentage) }} margin
          </p>
        </CardContent>
      </Card>
    </div>

    <!-- Exchange Rate Impact -->
    <Card v-if="Math.abs(data.total_exchange_gains_losses) > 0">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <AlertTriangle class="h-4 w-4" />
          Exchange Rate Impact
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-muted-foreground">Total Exchange Gains/Losses</div>
            <div class="text-sm">Impact from currency conversions during the period</div>
          </div>
          <div :class="[
            'text-lg font-semibold flex items-center gap-1',
            data.total_exchange_gains_losses >= 0 ? 'text-green-600' : 'text-red-600'
          ]">
            <TrendingUp v-if="data.total_exchange_gains_losses >= 0" class="h-4 w-4" />
            <TrendingDown v-else class="h-4 w-4" />
            {{ formatAmount(Math.abs(data.total_exchange_gains_losses)) }}
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Currency Breakdown -->
    <Card v-if="showCurrencyBreakdown">
      <CardHeader>
        <CardTitle>Currency Breakdown</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div 
            v-for="(breakdown, currencyCode) in data.currency_breakdown" 
            :key="currencyCode"
            class="border rounded-lg p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="font-medium">{{ currencyCode }}</div>
              <span class="font-mono text-lg">{{ breakdown.currency_symbol }}</span>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Revenue:</span>
                <span class="text-green-600 font-mono">{{ formatAmount(breakdown.revenue) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Expenses:</span>
                <span class="text-red-600 font-mono">{{ formatAmount(breakdown.expenses) }}</span>
              </div>
              <Separator />
              <div class="flex justify-between font-medium">
                <span>Net Impact:</span>
                <span :class="[
                  'font-mono',
                  breakdown.net_impact >= 0 ? 'text-green-600' : 'text-red-600'
                ]">
                  {{ formatAmount(breakdown.net_impact) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Profit & Loss Statement -->
    <Card>
      <CardHeader>
        <CardTitle>Detailed Statement ({{ baseCurrency.code }})</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-6">
          <!-- Revenue Section -->
          <div>
            <h3 class="text-lg font-semibold mb-3 text-green-700">{{ data.revenue.title }}</h3>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Account</TableHead>
                  <TableHead>Currency</TableHead>
                  <TableHead>Original Amount</TableHead>
                  <TableHead>{{ baseCurrency.code }} Amount</TableHead>
                  <TableHead>% of Revenue</TableHead>
                  <TableHead>FX Impact</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="account in renderSection(data.revenue)" :key="account.account_id">
                  <TableCell>
                    <div>
                      <div class="font-medium">{{ account.account_name }}</div>
                      <div class="text-sm text-muted-foreground">{{ account.account_code }}</div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ account.original_currency }}</Badge>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">{{ formatAmount(account.original_amount, getCurrencySymbol(account.original_currency)) }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">{{ formatAmount(account.base_currency_amount) }}</div>
                  </TableCell>
                  <TableCell>
                    <div>{{ formatPercentage(account.percentage_of_category) }}</div>
                  </TableCell>
                  <TableCell>
                    <div v-if="account.exchange_gains_losses !== 0" :class="[
                      'font-mono text-sm',
                      account.exchange_gains_losses > 0 ? 'text-green-600' : 'text-red-600'
                    ]">
                      {{ formatAmount(account.exchange_gains_losses) }}
                    </div>
                    <div v-else class="text-muted-foreground text-sm">-</div>
                  </TableCell>
                </TableRow>
                <TableRow class="border-t-2 font-semibold bg-green-50">
                  <TableCell colspan="3">Total Revenue</TableCell>
                  <TableCell>
                    <div class="font-mono">{{ formatAmount(data.revenue.total_base) }}</div>
                  </TableCell>
                  <TableCell>100.0%</TableCell>
                  <TableCell></TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <Separator />

          <!-- Cost of Goods Sold -->
          <div>
            <h3 class="text-lg font-semibold mb-3 text-orange-700">{{ data.cost_of_goods_sold.title }}</h3>
            <Table>
              <TableBody>
                <TableRow v-for="account in renderSection(data.cost_of_goods_sold)" :key="account.account_id">
                  <TableCell>
                    <div>
                      <div class="font-medium">{{ account.account_name }}</div>
                      <div class="text-sm text-muted-foreground">{{ account.account_code }}</div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ account.original_currency }}</Badge>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(account.original_amount, getCurrencySymbol(account.original_currency)) }})</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(account.base_currency_amount) }})</div>
                  </TableCell>
                  <TableCell>
                    <div>{{ formatPercentage(account.percentage_of_category) }}</div>
                  </TableCell>
                  <TableCell>
                    <div v-if="account.exchange_gains_losses !== 0" :class="[
                      'font-mono text-sm',
                      account.exchange_gains_losses > 0 ? 'text-green-600' : 'text-red-600'
                    ]">
                      {{ formatAmount(account.exchange_gains_losses) }}
                    </div>
                    <div v-else class="text-muted-foreground text-sm">-</div>
                  </TableCell>
                </TableRow>
                <TableRow class="border-t-2 font-semibold bg-orange-50">
                  <TableCell colspan="3">Total COGS</TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(data.cost_of_goods_sold.total_base) }})</div>
                  </TableCell>
                  <TableCell>{{ formatPercentage(data.cost_of_goods_sold.percentage_of_revenue) }}</TableCell>
                  <TableCell></TableCell>
                </TableRow>
              </TableBody>
            </Table>
            
            <!-- Gross Profit -->
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
              <div class="flex justify-between items-center">
                <span class="text-lg font-semibold">Gross Profit</span>
                <div class="text-right">
                  <div class="text-lg font-bold">{{ formatAmount(data.gross_profit) }}</div>
                  <div class="text-sm text-muted-foreground">{{ formatPercentage(data.gross_margin_percentage) }} margin</div>
                </div>
              </div>
            </div>
          </div>

          <Separator />

          <!-- Operating Expenses -->
          <div>
            <h3 class="text-lg font-semibold mb-3 text-red-700">{{ data.operating_expenses.title }}</h3>
            <Table>
              <TableBody>
                <TableRow v-for="account in renderSection(data.operating_expenses)" :key="account.account_id">
                  <TableCell>
                    <div>
                      <div class="font-medium">{{ account.account_name }}</div>
                      <div class="text-sm text-muted-foreground">{{ account.account_code }}</div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ account.original_currency }}</Badge>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(account.original_amount, getCurrencySymbol(account.original_currency)) }})</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(account.base_currency_amount) }})</div>
                  </TableCell>
                  <TableCell>
                    <div>{{ formatPercentage(account.percentage_of_category) }}</div>
                  </TableCell>
                  <TableCell>
                    <div v-if="account.exchange_gains_losses !== 0" :class="[
                      'font-mono text-sm',
                      account.exchange_gains_losses > 0 ? 'text-green-600' : 'text-red-600'
                    ]">
                      {{ formatAmount(account.exchange_gains_losses) }}
                    </div>
                    <div v-else class="text-muted-foreground text-sm">-</div>
                  </TableCell>
                </TableRow>
                <TableRow class="border-t-2 font-semibold bg-red-50">
                  <TableCell colspan="3">Total Operating Expenses</TableCell>
                  <TableCell>
                    <div class="font-mono">({{ formatAmount(data.operating_expenses.total_base) }})</div>
                  </TableCell>
                  <TableCell>{{ formatPercentage(data.operating_expenses.percentage_of_revenue) }}</TableCell>
                  <TableCell></TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <Separator class="border-2" />

          <!-- Net Profit -->
          <div class="p-6 bg-gradient-to-r from-blue-50 to-green-50 rounded-lg">
            <div class="flex justify-between items-center">
              <div>
                <h3 class="text-xl font-bold">Net Profit</h3>
                <p class="text-sm text-muted-foreground">Total profit for the period</p>
              </div>
              <div class="text-right">
                <div :class="[
                  'text-3xl font-bold flex items-center gap-2',
                  data.net_profit >= 0 ? 'text-green-600' : 'text-red-600'
                ]">
                  <TrendingUp v-if="data.net_profit >= 0" class="h-6 w-6" />
                  <TrendingDown v-else class="h-6 w-6" />
                  {{ formatAmount(Math.abs(data.net_profit)) }}
                </div>
                <div class="text-lg text-muted-foreground">{{ formatPercentage(data.net_margin_percentage) }} net margin</div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>