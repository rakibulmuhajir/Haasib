<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import {
  BarChart3,
  Download,
  Filter,
  TrendingUp,
  Droplets,
  Calendar,
  DollarSign,
  Fuel,
} from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface DailySale {
  date: string
  formatted_date: string
  day_name: string
  total_liters: number
  total_amount: number
  transaction_id?: string
  days_count?: number
}

interface FuelSummary {
  fuel_name: string
  total_liters: number
  total_amount: number
  avg_rate: number
  days_sold: number
}

interface Totals {
  total_days: number
  total_liters: number
  total_amount: number
  avg_daily_liters: number
  avg_daily_amount: number
}

interface Filters {
  start_date: string
  end_date: string
  fuel_type: string
  group_by: string
}

const props = defineProps<{
  dailySales: DailySale[]
  fuelSummary: FuelSummary[]
  fuelTypes: string[]
  totals: Totals
  trendData: DailySale[]
  filters: Filters
  currency: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Sales Report', href: `/${companySlug.value}/fuel/reports/sales` },
])

const currency = computed(() => currencySymbol(props.currency))

// Local filter state
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const fuelType = ref(props.filters.fuel_type)
const groupBy = ref(props.filters.group_by)

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatNumber = (num: number, decimals = 0) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

const applyFilters = () => {
  router.get(`/${companySlug.value}/fuel/reports/sales`, {
    start_date: startDate.value,
    end_date: endDate.value,
    fuel_type: fuelType.value,
    group_by: groupBy.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const exportCsv = () => {
  window.location.href = `/${companySlug.value}/fuel/reports/sales/export?start_date=${startDate.value}&end_date=${endDate.value}&fuel_type=${fuelType.value}`
}

// Calculate max for chart scaling
const maxAmount = computed(() => {
  return Math.max(...props.trendData.map(d => d.total_amount), 1)
})

// Quick date filters
const setDateRange = (range: 'today' | 'week' | 'month' | 'quarter') => {
  const today = new Date()
  endDate.value = today.toISOString().split('T')[0]

  switch (range) {
    case 'today':
      startDate.value = endDate.value
      break
    case 'week':
      const weekStart = new Date(today)
      weekStart.setDate(today.getDate() - 7)
      startDate.value = weekStart.toISOString().split('T')[0]
      break
    case 'month':
      const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
      startDate.value = monthStart.toISOString().split('T')[0]
      break
    case 'quarter':
      const quarterStart = new Date(today)
      quarterStart.setMonth(today.getMonth() - 3)
      startDate.value = quarterStart.toISOString().split('T')[0]
      break
  }
  applyFilters()
}
</script>

<template>
  <Head title="Sales Report" />

  <PageShell
    title="Sales Report"
    description="Analyze fuel sales performance over time."
    :icon="BarChart3"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="exportCsv">
        <Download class="mr-2 h-4 w-4" />
        Export CSV
      </Button>
    </template>

    <div class="space-y-6">
      <!-- Filters -->
      <Card>
        <CardHeader class="pb-3">
          <div class="flex items-center gap-2">
            <Filter class="h-4 w-4 text-muted-foreground" />
            <CardTitle class="text-base">Filters</CardTitle>
          </div>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-4">
            <!-- Quick Date Filters -->
            <div class="flex gap-2">
              <Button variant="outline" size="sm" @click="setDateRange('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setDateRange('week')">Last 7 Days</Button>
              <Button variant="outline" size="sm" @click="setDateRange('month')">This Month</Button>
              <Button variant="outline" size="sm" @click="setDateRange('quarter')">Last 3 Months</Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <div class="flex items-center gap-2">
              <Label class="text-sm">From</Label>
              <Input
                v-model="startDate"
                type="date"
                class="w-40"
              />
            </div>

            <div class="flex items-center gap-2">
              <Label class="text-sm">To</Label>
              <Input
                v-model="endDate"
                type="date"
                class="w-40"
              />
            </div>

            <div class="flex items-center gap-2">
              <Label class="text-sm">Fuel Type</Label>
              <Select v-model="fuelType">
                <SelectTrigger class="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Fuels</SelectItem>
                  <SelectItem v-for="ft in fuelTypes" :key="ft" :value="ft">
                    {{ ft }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex items-center gap-2">
              <Label class="text-sm">Group By</Label>
              <Select v-model="groupBy">
                <SelectTrigger class="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">Day</SelectItem>
                  <SelectItem value="week">Week</SelectItem>
                  <SelectItem value="month">Month</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Button @click="applyFilters">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <!-- Summary Cards -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card class="border-border/80 bg-gradient-to-br from-sky-500/10 via-transparent to-transparent">
          <CardHeader class="pb-2">
            <CardDescription>Total Sales</CardDescription>
            <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(totals.total_amount) }}</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <DollarSign class="h-4 w-4 text-sky-600" />
              <span>{{ totals.total_days }} days</span>
            </div>
          </CardContent>
        </Card>

        <Card class="border-border/80">
          <CardHeader class="pb-2">
            <CardDescription>Total Volume</CardDescription>
            <CardTitle class="text-2xl">{{ formatCurrency(totals.total_liters) }} L</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <Droplets class="h-4 w-4 text-sky-600" />
              <span>Liters sold</span>
            </div>
          </CardContent>
        </Card>

        <Card class="border-border/80">
          <CardHeader class="pb-2">
            <CardDescription>Avg Daily Sales</CardDescription>
            <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(totals.avg_daily_amount) }}</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <TrendingUp class="h-4 w-4 text-emerald-600" />
              <span>Per day average</span>
            </div>
          </CardContent>
        </Card>

        <Card class="border-border/80">
          <CardHeader class="pb-2">
            <CardDescription>Avg Daily Volume</CardDescription>
            <CardTitle class="text-2xl">{{ formatCurrency(totals.avg_daily_liters) }} L</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <Fuel class="h-4 w-4 text-indigo-600" />
              <span>Liters per day</span>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Trend Chart (Simple Bar) -->
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle class="text-base">Sales Trend</CardTitle>
            <CardDescription>Revenue over the selected period</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="trendData.length === 0" class="text-center py-12 text-muted-foreground">
              No sales data for the selected period.
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(item, index) in trendData"
                :key="index"
                class="flex items-center gap-4"
              >
                <div class="w-24 text-sm text-muted-foreground truncate">
                  {{ item.formatted_date }}
                </div>
                <div class="flex-1 h-8 bg-muted/30 rounded-md overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-sky-500 to-sky-600 rounded-md transition-all duration-300"
                    :style="{ width: `${(item.total_amount / maxAmount) * 100}%` }"
                  />
                </div>
                <div class="w-32 text-right font-medium">
                  {{ currency }} {{ formatCurrency(item.total_amount) }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Fuel Breakdown -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">By Fuel Type</CardTitle>
            <CardDescription>Sales breakdown by product</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="fuelSummary.length === 0" class="text-center py-8 text-muted-foreground">
              No fuel sales data.
            </div>
            <div v-else class="space-y-4">
              <div
                v-for="fuel in fuelSummary"
                :key="fuel.fuel_name"
                class="p-3 rounded-lg border bg-muted/30"
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-medium">{{ fuel.fuel_name }}</span>
                  <span class="text-sm text-muted-foreground">
                    {{ currency }} {{ formatNumber(fuel.avg_rate, 2) }}/L
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <span class="text-muted-foreground">Volume:</span>
                    <span class="ml-1 font-medium text-sky-600">{{ formatCurrency(fuel.total_liters) }} L</span>
                  </div>
                  <div class="text-right">
                    <span class="text-muted-foreground">Amount:</span>
                    <span class="ml-1 font-medium">{{ currency }} {{ formatCurrency(fuel.total_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Daily Sales Table -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Sales Details</CardTitle>
          <CardDescription>
            {{ groupBy === 'day' ? 'Daily' : groupBy === 'week' ? 'Weekly' : 'Monthly' }} breakdown
          </CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="dailySales.length === 0" class="text-center py-12 text-muted-foreground">
            <Calendar class="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>No sales data for the selected period.</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-muted/50">
                <tr>
                  <th class="text-left px-4 py-3 text-sm font-medium">Period</th>
                  <th v-if="groupBy !== 'day'" class="text-center px-4 py-3 text-sm font-medium">Days</th>
                  <th class="text-right px-4 py-3 text-sm font-medium">Volume (L)</th>
                  <th class="text-right px-4 py-3 text-sm font-medium">Amount</th>
                  <th v-if="groupBy !== 'day'" class="text-right px-4 py-3 text-sm font-medium">Avg/Day</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(sale, index) in dailySales"
                  :key="index"
                  class="border-t hover:bg-muted/30 transition-colors"
                >
                  <td class="px-4 py-3">
                    <div class="font-medium">{{ sale.formatted_date }}</div>
                    <div v-if="sale.day_name" class="text-sm text-muted-foreground">{{ sale.day_name }}</div>
                  </td>
                  <td v-if="groupBy !== 'day'" class="text-center px-4 py-3">
                    {{ sale.days_count }}
                  </td>
                  <td class="text-right px-4 py-3 font-medium text-sky-600">
                    {{ formatCurrency(sale.total_liters) }}
                  </td>
                  <td class="text-right px-4 py-3 font-medium">
                    {{ currency }} {{ formatCurrency(sale.total_amount) }}
                  </td>
                  <td v-if="groupBy !== 'day'" class="text-right px-4 py-3 text-muted-foreground">
                    {{ currency }} {{ formatCurrency(sale.total_amount / (sale.days_count || 1)) }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-muted/30">
                <tr class="border-t font-semibold">
                  <td class="px-4 py-3">Total</td>
                  <td v-if="groupBy !== 'day'" class="text-center px-4 py-3">{{ totals.total_days }}</td>
                  <td class="text-right px-4 py-3 text-sky-600">{{ formatCurrency(totals.total_liters) }}</td>
                  <td class="text-right px-4 py-3">{{ currency }} {{ formatCurrency(totals.total_amount) }}</td>
                  <td v-if="groupBy !== 'day'" class="text-right px-4 py-3">
                    {{ currency }} {{ formatCurrency(totals.avg_daily_amount) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
