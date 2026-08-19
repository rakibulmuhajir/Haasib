<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MetaChip from '@/components/MetaChip.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'
import { Fuel, Package, Percent, TrendingUp, WalletCards } from 'lucide-vue-next'

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

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Product Profitability', href: `/${props.company.slug}/fuel/reports/product-profitability` },
])

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

const productColumns = [
  { key: 'name', label: 'Product', kind: 'text' as const },
  { key: 'quantity', label: 'Qty', kind: 'amount' as const },
  { key: 'revenue', label: 'Revenue', kind: 'amount' as const },
  { key: 'cogs', label: 'COGS', kind: 'amount' as const },
  { key: 'gross_profit', label: 'Gross profit', kind: 'amount' as const },
  { key: 'margin_per_unit', label: 'Margin/unit', kind: 'amount' as const },
  { key: 'gross_margin_percent', label: 'Margin %', kind: 'amount' as const },
  { key: 'stock_variance', label: 'Stock variance', kind: 'amount' as const },
]

const trendColumns = [
  { key: 'label', label: 'Period', kind: 'text' as const },
  { key: 'quantity', label: 'Qty', kind: 'amount' as const },
  { key: 'revenue', label: 'Revenue', kind: 'amount' as const },
  { key: 'cogs', label: 'COGS', kind: 'amount' as const },
  { key: 'gross_profit', label: 'Profit', kind: 'amount' as const },
  { key: 'margin_per_unit', label: 'Margin/unit', kind: 'amount' as const },
  { key: 'daily_close', label: 'Daily closes', kind: 'ref' as const },
]

const rateChangeColumns = [
  { key: 'date_label', label: 'Date', kind: 'date' as const },
  { key: 'product_name', label: 'Product', kind: 'text' as const },
  { key: 'old_rate', label: 'Old rate', kind: 'amount' as const },
  { key: 'new_rate', label: 'New rate', kind: 'amount' as const },
  { key: 'old_rate_liters', label: 'Old-rate L', kind: 'amount' as const },
  { key: 'new_rate_liters', label: 'New-rate L', kind: 'amount' as const },
  { key: 'estimated_rate_change_effect', label: 'Effect', kind: 'amount' as const },
  { key: 'transaction_number', label: 'Daily Close', kind: 'ref' as const },
]
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
            <CardTitle class="text-2xl"><MoneyText :amount="totals.revenue" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Fuel class="h-4 w-4 text-status-info" />
            {{ qty(totals.quantity) }} units/L sold
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Gross profit</CardDescription>
            <CardTitle class="text-2xl"><MoneyText :amount="totals.gross_profit" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Percent class="h-4 w-4 text-status-success" />
            {{ percent(totals.gross_margin_percent) }} margin
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Margin per unit</CardDescription>
            <CardTitle class="text-2xl"><MoneyText :amount="totals.margin_per_unit" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <TrendingUp class="h-4 w-4 text-status-info" />
            Across selected products
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Stock variance value</CardDescription>
            <CardTitle class="text-2xl"><MoneyText :amount="totals.stock_gain_value - totals.stock_loss_value" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <WalletCards class="h-4 w-4 text-status-attention" />
            Loss <MoneyText :amount="totals.stock_loss_value" :currency="company.base_currency" /> · Gain <MoneyText :amount="totals.stock_gain_value" :currency="company.base_currency" />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Products</CardTitle>
          <CardDescription>Sales, cost, margin, and stock variance by product.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister :data="productRows" :columns="productColumns">
            <template #empty>No product sales or stock variance found for this range.</template>

            <template #cell-name="{ row }">
              <div class="font-medium">{{ row.name }}</div>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <MetaChip v-if="row.estimated_cogs" tone="neutral" bare>Estimated cost</MetaChip>
                <span class="text-xs text-muted-foreground">{{ row.unit }}</span>
              </div>
            </template>

            <template #cell-quantity="{ row }">{{ qty(row.quantity) }}</template>
            <template #cell-revenue="{ row }"><MoneyText :amount="row.revenue" :currency="company.base_currency" /></template>
            <template #cell-cogs="{ row }">
              <div><MoneyText :amount="row.cogs" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground"><MoneyText :amount="row.avg_cost" :currency="company.base_currency" />/{{ row.unit }}</div>
            </template>
            <template #cell-gross_profit="{ row }"><MoneyText :amount="row.gross_profit" :currency="company.base_currency" /></template>
            <template #cell-margin_per_unit="{ row }"><MoneyText :amount="row.margin_per_unit" :currency="company.base_currency" /></template>
            <template #cell-gross_margin_percent="{ row }">{{ percent(row.gross_margin_percent) }}</template>

            <!-- A variance is a variance whichever way it points. Fuel that went
                 missing and fuel that appeared are the same question -- why does
                 the dip not match the book -- so they get the same amber, and the
                 sign says which way it went. Red here would mean the reader has
                 to remember that this red is different from an overdue red. -->
            <template #cell-stock_variance="{ row }">
              <div v-if="row.stock_loss_quantity || row.stock_gain_quantity" class="space-y-1 text-status-attention">
                <div v-if="row.stock_loss_quantity">
                  -{{ qty(row.stock_loss_quantity) }} {{ row.unit }} · <MoneyText :amount="row.stock_loss_value" :currency="company.base_currency" />
                </div>
                <div v-if="row.stock_gain_quantity">
                  +{{ qty(row.stock_gain_quantity) }} {{ row.unit }} · <MoneyText :amount="row.stock_gain_value" :currency="company.base_currency" />
                </div>
              </div>
              <span v-else class="text-muted-foreground">—</span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Trend by {{ groupBy }}</CardTitle>
          <CardDescription>Use product filter above to see one product over time.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister :data="periodRows" :columns="trendColumns">
            <template #empty>No trend data found for this range.</template>

            <template #cell-quantity="{ row }">{{ qty(row.quantity) }}</template>
            <template #cell-revenue="{ row }"><MoneyText :amount="row.revenue" :currency="company.base_currency" /></template>
            <template #cell-cogs="{ row }"><MoneyText :amount="row.cogs" :currency="company.base_currency" /></template>
            <template #cell-gross_profit="{ row }"><MoneyText :amount="row.gross_profit" :currency="company.base_currency" /></template>
            <template #cell-margin_per_unit="{ row }"><MoneyText :amount="row.margin_per_unit" :currency="company.base_currency" /></template>

            <template #cell-daily_close="{ row }">
              <Link
                v-if="row.detail_url_id"
                :href="`/${company.slug}/fuel/daily-close/${row.detail_url_id}`"
                class="text-primary underline-offset-4 hover:underline"
              >
                {{ row.daily_close_numbers[0] }}
              </Link>
              <span v-else>{{ row.daily_close_count }}</span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Rate Change Snapshots</CardTitle>
          <CardDescription>When a rate change included meter snapshots, sales are split between old and new rates.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister
            :data="rateChangeRows.map((row) => ({ ...row, key: `${row.transaction_id}-${row.product_name}` }))"
            :columns="rateChangeColumns"
            key-field="key"
          >
            <template #empty>No rate-change snapshot sales found for this range.</template>

            <template #cell-old_rate="{ row }"><MoneyText :amount="row.old_rate" :currency="company.base_currency" /></template>
            <template #cell-new_rate="{ row }"><MoneyText :amount="row.new_rate" :currency="company.base_currency" /></template>
            <template #cell-old_rate_liters="{ row }">{{ qty(row.old_rate_liters) }}</template>
            <template #cell-new_rate_liters="{ row }">{{ qty(row.new_rate_liters) }}</template>
            <template #cell-estimated_rate_change_effect="{ row }"><MoneyText :amount="row.estimated_rate_change_effect" :currency="company.base_currency" /></template>

            <template #cell-transaction_number="{ row }">
              <Link :href="`/${company.slug}/fuel/daily-close/${row.transaction_id}`" class="text-primary underline-offset-4 hover:underline">
                {{ row.transaction_number }}
              </Link>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
