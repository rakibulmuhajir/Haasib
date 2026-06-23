<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Fuel, Package, Percent, TrendingUp, WalletCards } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Filters {
  start_date: string
  end_date: string
  group_by: 'day' | 'week' | 'month'
  product: string
}

interface Totals {
  product_count: number
  quantity: number
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin_percent: number
  margin_per_unit: number
  stock_loss_quantity: number
  stock_loss_value: number
  stock_gain_quantity: number
  stock_gain_value: number
}

interface ProductRow {
  key: string
  name: string
  unit: string
  quantity: number
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin_percent: number
  avg_rate: number
  avg_cost: number
  margin_per_unit: number
  estimated_cogs: boolean
  stock_loss_quantity: number
  stock_loss_value: number
  stock_gain_quantity: number
  stock_gain_value: number
}

interface PeriodRow {
  key: string
  label: string
  quantity: number
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin_percent: number
  margin_per_unit: number
  daily_close_numbers: string[]
  daily_close_count: number
  detail_url_id: string | null
}

interface RateChangeRow {
  date_label: string
  transaction_id: string
  transaction_number: string
  product_name: string
  old_rate: number
  new_rate: number
  old_rate_liters: number
  new_rate_liters: number
  fallback_liters: number
  revenue: number
  estimated_rate_change_effect: number
}

interface ProductOption {
  key: string
  name: string
}

const props = defineProps<{
  company: Company
  filters: Filters
  totals: Totals
  productRows: ProductRow[]
  periodRows: PeriodRow[]
  rateChangeRows: RateChangeRow[]
  productOptions: ProductOption[]
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const groupBy = ref(props.filters.group_by)
const product = ref(props.filters.product)

const symbol = computed(() => currencySymbol(props.company.base_currency))
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Product Profitability', href: `/${props.company.slug}/fuel/reports/product-profitability` },
])

const money = (amount: number) => `${symbol.value} ${new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
}).format(amount || 0)}`

const qty = (amount: number, decimals = 0) => new Intl.NumberFormat('en-US', {
  minimumFractionDigits: decimals,
  maximumFractionDigits: decimals,
}).format(amount || 0)

const percent = (amount: number) => `${qty(amount, 1)}%`

const applyFilters = () => {
  router.get(`/${props.company.slug}/fuel/reports/product-profitability`, {
    start_date: startDate.value,
    end_date: endDate.value,
    group_by: groupBy.value,
    product: product.value,
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const isoDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const setRange = (range: 'today' | 'last7' | 'month' | 'lastMonth') => {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  if (range === 'today') {
    startDate.value = isoDate(today)
    endDate.value = isoDate(today)
  } else if (range === 'last7') {
    const start = new Date(today)
    start.setDate(today.getDate() - 6)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  } else if (range === 'lastMonth') {
    const start = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const end = new Date(today.getFullYear(), today.getMonth(), 0)
    startDate.value = isoDate(start)
    endDate.value = isoDate(end)
  } else {
    const start = new Date(today.getFullYear(), today.getMonth(), 1)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  }

  applyFilters()
}
</script>

<template>
  <Head title="Product Profitability" />

  <PageShell
    title="Product Profitability"
    description="Product-wise sales, cost, margin, stock variance, and rate-change snapshot impact."
    :icon="Package"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-5">
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Filters</CardTitle>
          <CardDescription>Use this for fuel, lubricants, and other products recorded through Daily Close.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" @click="setRange('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setRange('last7')">Last 7 days</Button>
              <Button variant="outline" size="sm" @click="setRange('month')">This month</Button>
              <Button variant="outline" size="sm" @click="setRange('lastMonth')">Last month</Button>
            </div>

            <div class="grid gap-1.5">
              <Label for="start_date">From</Label>
              <Input id="start_date" v-model="startDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label for="end_date">To</Label>
              <Input id="end_date" v-model="endDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label>Group</Label>
              <Select v-model="groupBy">
                <SelectTrigger class="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">Daily</SelectItem>
                  <SelectItem value="week">Weekly</SelectItem>
                  <SelectItem value="month">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Product</Label>
              <Select v-model="product">
                <SelectTrigger class="w-56">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All products</SelectItem>
                  <SelectItem v-for="option in productOptions" :key="option.key" :value="option.key">
                    {{ option.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Button @click="applyFilters">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Revenue</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.revenue) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Fuel class="h-4 w-4 text-sky-600" />
            {{ qty(totals.quantity) }} units/L sold
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Gross profit</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.gross_profit) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Percent class="h-4 w-4 text-emerald-700" />
            {{ percent(totals.gross_margin_percent) }} margin
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Margin per unit</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.margin_per_unit) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <TrendingUp class="h-4 w-4 text-violet-700" />
            Across selected products
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Stock variance value</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.stock_gain_value - totals.stock_loss_value) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <WalletCards class="h-4 w-4 text-amber-700" />
            Loss {{ money(totals.stock_loss_value) }} · Gain {{ money(totals.stock_gain_value) }}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Products</CardTitle>
          <CardDescription>Sales, cost, margin, and stock variance by product.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="productRows.length === 0" class="py-12 text-center text-muted-foreground">
            No product sales or stock variance found for this range.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead class="text-right">Qty</TableHead>
                  <TableHead class="text-right">Revenue</TableHead>
                  <TableHead class="text-right">COGS</TableHead>
                  <TableHead class="text-right">Gross profit</TableHead>
                  <TableHead class="text-right">Margin/unit</TableHead>
                  <TableHead class="text-right">Margin %</TableHead>
                  <TableHead class="text-right">Stock variance</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in productRows" :key="row.key">
                  <TableCell>
                    <div class="font-medium">{{ row.name }}</div>
                    <div class="mt-1 flex flex-wrap gap-2">
                      <Badge v-if="row.estimated_cogs" variant="outline">Estimated cost</Badge>
                      <span class="text-xs text-muted-foreground">{{ row.unit }}</span>
                    </div>
                  </TableCell>
                  <TableCell class="text-right">{{ qty(row.quantity) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.revenue) }}</TableCell>
                  <TableCell class="text-right">
                    <div>{{ money(row.cogs) }}</div>
                    <div class="text-xs text-muted-foreground">{{ money(row.avg_cost) }}/{{ row.unit }}</div>
                  </TableCell>
                  <TableCell class="text-right font-medium">{{ money(row.gross_profit) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.margin_per_unit) }}</TableCell>
                  <TableCell class="text-right">{{ percent(row.gross_margin_percent) }}</TableCell>
                  <TableCell class="text-right">
                    <div v-if="row.stock_loss_quantity || row.stock_gain_quantity" class="space-y-1">
                      <div v-if="row.stock_loss_quantity" class="text-red-700">
                        -{{ qty(row.stock_loss_quantity) }} {{ row.unit }} · {{ money(row.stock_loss_value) }}
                      </div>
                      <div v-if="row.stock_gain_quantity" class="text-emerald-700">
                        +{{ qty(row.stock_gain_quantity) }} {{ row.unit }} · {{ money(row.stock_gain_value) }}
                      </div>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Trend by {{ groupBy }}</CardTitle>
          <CardDescription>Use product filter above to see one product over time.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="periodRows.length === 0" class="py-12 text-center text-muted-foreground">
            No trend data found for this range.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Period</TableHead>
                  <TableHead class="text-right">Qty</TableHead>
                  <TableHead class="text-right">Revenue</TableHead>
                  <TableHead class="text-right">COGS</TableHead>
                  <TableHead class="text-right">Profit</TableHead>
                  <TableHead class="text-right">Margin/unit</TableHead>
                  <TableHead class="text-right">Daily closes</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in periodRows" :key="row.key">
                  <TableCell class="font-medium">{{ row.label }}</TableCell>
                  <TableCell class="text-right">{{ qty(row.quantity) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.revenue) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.cogs) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.gross_profit) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.margin_per_unit) }}</TableCell>
                  <TableCell class="text-right">
                    <Link
                      v-if="row.detail_url_id"
                      :href="`/${company.slug}/fuel/daily-close/${row.detail_url_id}`"
                      class="text-primary underline-offset-4 hover:underline"
                    >
                      {{ row.daily_close_numbers[0] }}
                    </Link>
                    <span v-else>{{ row.daily_close_count }}</span>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Rate Change Snapshots</CardTitle>
          <CardDescription>When a rate change included meter snapshots, sales are split between old and new rates.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="rateChangeRows.length === 0" class="py-12 text-center text-muted-foreground">
            No rate-change snapshot sales found for this range.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Product</TableHead>
                  <TableHead class="text-right">Old rate</TableHead>
                  <TableHead class="text-right">New rate</TableHead>
                  <TableHead class="text-right">Old-rate L</TableHead>
                  <TableHead class="text-right">New-rate L</TableHead>
                  <TableHead class="text-right">Effect</TableHead>
                  <TableHead class="text-right">Daily Close</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in rateChangeRows" :key="`${row.transaction_id}-${row.product_name}`">
                  <TableCell>{{ row.date_label }}</TableCell>
                  <TableCell class="font-medium">{{ row.product_name }}</TableCell>
                  <TableCell class="text-right">{{ money(row.old_rate) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.new_rate) }}</TableCell>
                  <TableCell class="text-right">{{ qty(row.old_rate_liters) }}</TableCell>
                  <TableCell class="text-right">{{ qty(row.new_rate_liters) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.estimated_rate_change_effect) }}</TableCell>
                  <TableCell class="text-right">
                    <Link :href="`/${company.slug}/fuel/daily-close/${row.transaction_id}`" class="text-primary underline-offset-4 hover:underline">
                      {{ row.transaction_number }}
                    </Link>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
