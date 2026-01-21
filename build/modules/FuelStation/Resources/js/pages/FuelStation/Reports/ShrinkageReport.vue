<script setup lang="ts">
import { computed, ref } from 'vue'
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
import { Droplets, Download, AlertTriangle, TrendingDown, Calendar, DollarSign } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface ShrinkageEntry {
  date: string
  tank_id: string
  tank_name: string
  fuel_id: string
  fuel_name: string
  opening: number
  closing: number
  receipts: number
  sales: number
  expected: number
  shrinkage: number
  rate: number
  value: number
}

interface DailyShrinkage {
  date: string
  total_shrinkage: number
  total_value: number
  tanks: ShrinkageEntry[]
}

interface FuelSummary {
  fuel_name: string
  total_shrinkage: number
  total_value: number
  count: number
}

interface Fuel {
  id: string
  name: string
  code: string | null
}

interface Stats {
  total_shrinkage: number
  total_value: number
  days_with_shrinkage: number
  avg_daily_shrinkage: number
}

interface Filters {
  start_date: string
  end_date: string
  fuel_id: string
}

const props = defineProps<{
  shrinkageData: ShrinkageEntry[]
  dailyShrinkage: DailyShrinkage[]
  fuelSummary: FuelSummary[]
  fuels: Fuel[]
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
  { title: 'Reports', href: `/${companySlug.value}/fuel/reports/sales` },
  { title: 'Shrinkage Report', href: `/${companySlug.value}/fuel/reports/shrinkage` },
])

const currency = computed(() => currencySymbol(props.currency))

// Local filter state
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const fuelId = ref(props.filters.fuel_id)

const applyFilters = () => {
  router.get(`/${companySlug.value}/fuel/reports/shrinkage`, {
    start_date: startDate.value,
    end_date: endDate.value,
    fuel_id: fuelId.value,
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
    case 'week':
      start = new Date(today)
      start.setDate(start.getDate() - 7)
      break
    case 'month':
      start = new Date(today.getFullYear(), today.getMonth(), 1)
      break
    case 'quarter':
      start = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1)
      break
    case 'year':
      start = new Date(today.getFullYear(), 0, 1)
      break
    default:
      return
  }

  startDate.value = start.toISOString().split('T')[0]
  endDate.value = end.toISOString().split('T')[0]
  applyFilters()
}

const formatNumber = (num: number, decimals = 2) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'tank', label: 'Tank' },
  { key: 'fuel', label: 'Fuel' },
  { key: 'opening', label: 'Opening' },
  { key: 'receipts', label: 'Receipts' },
  { key: 'sales', label: 'Sales' },
  { key: 'expected', label: 'Expected' },
  { key: 'closing', label: 'Closing' },
  { key: 'shrinkage', label: 'Shrinkage' },
  { key: 'value', label: 'Value' },
]

const tableData = computed(() => {
  return props.shrinkageData.map((s, i) => ({
    id: `${s.date}-${s.tank_id}-${i}`,
    date: formatDate(s.date),
    tank: s.tank_name,
    fuel: s.fuel_name,
    opening: s.opening,
    receipts: s.receipts,
    sales: s.sales,
    expected: s.expected,
    closing: s.closing,
    shrinkage: s.shrinkage,
    value: s.value,
    _raw: s,
  }))
})

const exportReport = () => {
  const params = new URLSearchParams({
    start_date: startDate.value,
    end_date: endDate.value,
    fuel_id: fuelId.value,
  })
  window.location.href = `/${companySlug.value}/fuel/reports/shrinkage/export?${params.toString()}`
}
</script>

<template>
  <Head title="Shrinkage Report" />

  <PageShell
    title="Shrinkage Report"
    description="Analyze fuel shrinkage and losses across tanks."
    :icon="Droplets"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="exportReport">
        <Download class="mr-2 h-4 w-4" />
        Export CSV
      </Button>
    </template>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-red-500/10 via-orange-500/5 to-amber-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Shrinkage</CardDescription>
          <CardTitle class="text-2xl" :class="stats.total_shrinkage < 0 ? 'text-red-600' : 'text-emerald-600'">
            {{ formatNumber(stats.total_shrinkage) }} L
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingDown class="h-4 w-4 text-red-600" />
            <span>{{ stats.total_shrinkage < 0 ? 'Loss' : 'Gain' }} in period</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Value</CardDescription>
          <CardTitle class="text-2xl text-red-600">{{ currency }} {{ formatCurrency(stats.total_value) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <DollarSign class="h-4 w-4 text-red-600" />
            <span>Monetary impact</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Days with Shrinkage</CardDescription>
          <CardTitle class="text-2xl">{{ stats.days_with_shrinkage }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Calendar class="h-4 w-4 text-amber-600" />
            <span>Out of selected period</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Avg Daily Shrinkage</CardDescription>
          <CardTitle class="text-2xl">{{ formatNumber(stats.avg_daily_shrinkage) }} L</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <AlertTriangle class="h-4 w-4 text-amber-600" />
            <span>Per day with shrinkage</span>
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
              <Button variant="outline" size="sm" @click="setDatePreset('week')">Last 7 Days</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('month')">This Month</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('quarter')">This Quarter</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('year')">This Year</Button>
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
            <Label>Fuel Type</Label>
            <Select v-model="fuelId" @update:model-value="applyFilters">
              <SelectTrigger class="w-[180px]">
                <SelectValue placeholder="All Fuels" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Fuels</SelectItem>
                <SelectItem v-for="f in fuels" :key="f.id" :value="f.id">
                  {{ f.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Fuel Summary -->
    <div v-if="fuelSummary.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <Card v-for="summary in fuelSummary" :key="summary.fuel_name" class="border-border/80">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">{{ summary.fuel_name }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-muted-foreground">Total Shrinkage</span>
              <span class="font-medium" :class="summary.total_shrinkage < 0 ? 'text-red-600' : 'text-emerald-600'">
                {{ formatNumber(summary.total_shrinkage) }} L
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Value Lost</span>
              <span class="font-medium text-red-600">{{ currency }} {{ formatCurrency(summary.total_value) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Occurrences</span>
              <span class="font-medium">{{ summary.count }}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Detailed Table -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Detailed Shrinkage Records</CardTitle>
        <CardDescription>Daily shrinkage by tank with value calculations.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="tableData" :columns="columns">
          <template #empty>
            <EmptyState
              title="No shrinkage recorded"
              description="No shrinkage found for the selected period and filters."
            />
          </template>

          <template #cell-fuel="{ row }">
            <Badge variant="outline" class="bg-amber-50">
              {{ row._raw.fuel_name }}
            </Badge>
          </template>

          <template #cell-opening="{ row }">
            {{ formatNumber(row._raw.opening) }} L
          </template>

          <template #cell-receipts="{ row }">
            <span class="text-emerald-600">+{{ formatNumber(row._raw.receipts) }} L</span>
          </template>

          <template #cell-sales="{ row }">
            <span class="text-amber-600">-{{ formatNumber(row._raw.sales) }} L</span>
          </template>

          <template #cell-expected="{ row }">
            {{ formatNumber(row._raw.expected) }} L
          </template>

          <template #cell-closing="{ row }">
            {{ formatNumber(row._raw.closing) }} L
          </template>

          <template #cell-shrinkage="{ row }">
            <span
              class="font-semibold"
              :class="row._raw.shrinkage < 0 ? 'text-red-600' : 'text-emerald-600'"
            >
              {{ row._raw.shrinkage < 0 ? '' : '+' }}{{ formatNumber(row._raw.shrinkage) }} L
            </span>
          </template>

          <template #cell-value="{ row }">
            <span class="font-medium text-red-600">
              {{ currency }} {{ formatCurrency(row._raw.value) }}
            </span>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
