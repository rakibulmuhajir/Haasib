<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { CreditCard, Calendar, Fuel, Users, TrendingUp, Droplets } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface CreditSale {
  id: string
  transaction_id: string
  date: string
  customer_id: string | null
  customer_name: string
  fuel_name: string
  liters: number
  rate: number
  amount: number
  vehicle_number: string | null
  driver_name: string | null
}

interface Customer {
  id: string
  name: string
  code: string | null
}

interface Stats {
  total_sales: number
  total_liters: number
  total_amount: number
  unique_customers: number
}

interface Filters {
  start_date: string
  end_date: string
  customer_id: string
}

const props = defineProps<{
  sales: CreditSale[]
  customers: Customer[]
  stats: Stats
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
  { title: 'Credit Sales', href: `/${companySlug.value}/fuel/credit-sales` },
])

const currency = computed(() => currencySymbol(props.currency))

// Local filter state
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const customerId = ref(props.filters.customer_id)

const applyFilters = () => {
  router.get(`/${companySlug.value}/fuel/credit-sales`, {
    start_date: startDate.value,
    end_date: endDate.value,
    customer_id: customerId.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Quick date presets
const setDatePreset = (preset: string) => {
  const today = new Date()
  let start: Date
  let end: Date = today

  switch (preset) {
    case 'today':
      start = today
      break
    case 'yesterday':
      start = new Date(today)
      start.setDate(start.getDate() - 1)
      end = start
      break
    case 'week':
      start = new Date(today)
      start.setDate(start.getDate() - 7)
      break
    case 'month':
      start = new Date(today.getFullYear(), today.getMonth(), 1)
      break
    default:
      return
  }

  startDate.value = start.toISOString().split('T')[0]
  endDate.value = end.toISOString().split('T')[0]
  applyFilters()
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'customer', label: 'Customer' },
  { key: 'fuel', label: 'Fuel' },
  { key: 'liters', label: 'Liters' },
  { key: 'rate', label: 'Rate' },
  { key: 'amount', label: 'Amount' },
]

const tableData = computed(() => {
  return props.sales.map((s) => ({
    id: s.id,
    date: formatDate(s.date),
    customer: s.customer_name,
    fuel: s.fuel_name,
    liters: s.liters,
    rate: s.rate,
    amount: s.amount,
    _raw: s,
  }))
})

const goToCustomer = (customerId: string) => {
  if (customerId) {
    router.get(`/${companySlug.value}/fuel/credit-customers/${customerId}`)
  }
}
</script>

<template>
  <Head title="Credit Sales" />

  <PageShell
    title="Credit Sales"
    description="View credit sales from daily closes."
    :icon="CreditCard"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-red-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Sales</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_sales }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-amber-600" />
            <span>Credit transactions</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Liters</CardDescription>
          <CardTitle class="text-2xl text-sky-600">{{ formatNumber(stats.total_liters) }} L</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Droplets class="h-4 w-4 text-sky-600" />
            <span>Fuel dispensed on credit</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Amount</CardDescription>
          <CardTitle class="text-2xl text-emerald-600">{{ currency }} {{ formatCurrency(stats.total_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <CreditCard class="h-4 w-4 text-emerald-600" />
            <span>Credit sales value</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Unique Customers</CardDescription>
          <CardTitle class="text-2xl">{{ stats.unique_customers }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Users class="h-4 w-4 text-indigo-600" />
            <span>In selected period</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Filters</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="flex flex-wrap items-end gap-4">
          <div class="space-y-2">
            <Label>Quick Select</Label>
            <div class="flex gap-2">
              <Button variant="outline" size="sm" @click="setDatePreset('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('yesterday')">Yesterday</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('week')">Last 7 Days</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('month')">This Month</Button>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="start_date">Start Date</Label>
            <Input
              id="start_date"
              v-model="startDate"
              type="date"
              class="w-[160px]"
              @change="applyFilters"
            />
          </div>

          <div class="space-y-2">
            <Label for="end_date">End Date</Label>
            <Input
              id="end_date"
              v-model="endDate"
              type="date"
              class="w-[160px]"
              @change="applyFilters"
            />
          </div>

          <div class="space-y-2">
            <Label>Customer</Label>
            <Select v-model="customerId" @update:model-value="applyFilters">
              <SelectTrigger class="w-[200px]">
                <SelectValue placeholder="All Customers" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Customers</SelectItem>
                <SelectItem v-for="c in customers" :key="c.id" :value="c.id">
                  {{ c.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Sales Table -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Credit Sales</CardTitle>
        <CardDescription>Sales made on credit during the selected period.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="tableData" :columns="columns">
          <template #empty>
            <EmptyState
              title="No credit sales found"
              description="No credit sales match the current filters."
            />
          </template>

          <template #cell-customer="{ row }">
            <button
              v-if="row._raw.customer_id"
              class="text-left hover:underline"
              @click.stop="goToCustomer(row._raw.customer_id)"
            >
              <div class="font-medium">{{ row._raw.customer_name }}</div>
              <div v-if="row._raw.vehicle_number" class="text-sm text-muted-foreground">
                {{ row._raw.vehicle_number }}
              </div>
            </button>
            <div v-else>
              <div class="font-medium">{{ row._raw.customer_name }}</div>
              <div v-if="row._raw.vehicle_number" class="text-sm text-muted-foreground">
                {{ row._raw.vehicle_number }}
              </div>
            </div>
          </template>

          <template #cell-fuel="{ row }">
            <Badge variant="outline" class="bg-amber-50">
              <Fuel class="mr-1 h-3 w-3" />
              {{ row._raw.fuel_name }}
            </Badge>
          </template>

          <template #cell-liters="{ row }">
            <span class="font-medium">{{ formatNumber(row._raw.liters) }} L</span>
          </template>

          <template #cell-rate="{ row }">
            {{ currency }} {{ formatNumber(row._raw.rate) }}
          </template>

          <template #cell-amount="{ row }">
            <span class="font-medium text-emerald-600">
              {{ currency }} {{ formatCurrency(row._raw.amount) }}
            </span>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
