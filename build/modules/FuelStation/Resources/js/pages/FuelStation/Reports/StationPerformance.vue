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
import Hint from '@/components/Hint.vue'
import type { BreadcrumbItem } from '@/types'
import { Banknote, BarChart3, ClipboardCheck, Droplets, Fuel, ReceiptText, WalletCards } from 'lucide-vue-next'

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
  days: number
  liters: number
  revenue: number
  fuel_revenue: number
  other_sales: number
  cogs: number
  gross_profit: number
  gross_margin_percent: number
  expenses: number
  payroll_payouts: number
  net_station_profit: number
  other: number
  cash_variance: number
  stock_loss: number
  stock_gain: number
  purchases_paid: number
  closing_cash: number
}

interface ReportRow {
  key: string
  label: string
  days_count: number
  daily_close_numbers: string[]
  liters: number
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin_percent: number
  expenses: number
  payroll_payouts: number
  net_station_profit: number
  other: number
  cash_variance: number
  stock_loss: number
  stock_gain: number
  purchases_paid: number
  closing_cash: number
  daily_close_count: number
  detail_url_id: string | null
}

interface ProductRow {
  key: string
  name: string
  liters: number
  revenue: number
  cogs: number
  gross_profit: number
  avg_rate: number
  margin_per_liter: number
  gross_margin_percent: number
}

interface ProductOption {
  key: string
  name: string
}

interface CashRow {
  date: string
  label: string
  transaction_id: string
  transaction_number: string
  opening_cash: number
  cash_sales: number
  money_in: number
  money_out: number
  expected_closing: number
  closing_cash: number
  variance: number
}

interface MovementTotals {
  partner_deposits: number
  amanat_deposits: number
  other_deposits: number
  payment_receipts: number
  bank_deposits: number
  partner_withdrawals: number
  employee_advances: number
  payroll_payouts: number
  amanat_disbursements: number
  expenses: number
  bill_payments: number
}

const props = defineProps<{
  company: Company
  filters: Filters
  totals: Totals
  rows: ReportRow[]
  productRows: ProductRow[]
  productOptions: ProductOption[]
  cashRows: CashRow[]
  movementTotals: MovementTotals
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const groupBy = ref(props.filters.group_by)
const product = ref(props.filters.product)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Profit by day', href: `/${props.company.slug}/fuel/reports/performance` },
])

const number = (amount: number, decimals = 0) => new Intl.NumberFormat('en-US', {
  minimumFractionDigits: decimals,
  maximumFractionDigits: decimals,
}).format(amount || 0)

const percent = (amount: number) => `${number(amount, 1)}%`

/**
 * A variance is a variance whichever way it points.
 *
 * This used to paint a cash surplus green and a shortfall red, which reads as
 * "over is good news". It is not: a till that is over is the same control
 * failure as one that is short, and someone has to find out why either way.
 * Colour here means "look at this", the sign says which way it went, and a
 * till that balanced gets no colour at all because there is nothing to see.
 */
const varianceTone = (amount: number) =>
  amount === 0 ? 'text-text-secondary' : 'text-status-attention'

const applyFilters = () => {
  router.get(`/${props.company.slug}/fuel/reports/performance`, {
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

const setRange = (range: 'today' | 'yesterday' | 'last7' | 'month' | 'lastMonth') => {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  if (range === 'today') {
    startDate.value = isoDate(today)
    endDate.value = isoDate(today)
  } else if (range === 'yesterday') {
    const yesterday = new Date(today)
    yesterday.setDate(today.getDate() - 1)
    startDate.value = isoDate(yesterday)
    endDate.value = isoDate(yesterday)
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

// Each money column opens into the accounts it is made of, from the books (all products only).
interface BookLine { account_id: string; code: string; name: string; type: string; amount: number }
type Part = 'sales' | 'cost' | 'expenses' | 'other'
const open = ref<{ key: string; part: Part } | null>(null)
const toggle = (key: string, part: Part) => {
  open.value = open.value?.key === key && open.value.part === part ? null : { key, part }
}
const isOpen = (row: any) => open.value?.key === row.key
const linesOf = (row: any): BookLine[] => {
  const part = open.value?.part
  if (!part) return []
  return (row[{ sales: 'sales_lines', cost: 'cost_lines', expenses: 'expense_lines', other: 'other_lines' }[part]] ?? []) as BookLine[]
}
const partTitle: Record<Part, string> = { sales: 'Sales by product account', cost: 'Cost of sales', expenses: 'Expenses', other: 'Other income and costs' }
const rowRange = (row: any) => {
  // A day row's key is its date; a week/month row spans to its end, clipped to the report range.
  const from = props.filters.group_by === 'month' ? `${row.key}-01` : row.key
  const start = new Date(`${from}T00:00:00`)
  const end = props.filters.group_by === 'day' ? start
    : props.filters.group_by === 'week' ? new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6)
    : new Date(start.getFullYear(), start.getMonth() + 1, 0)
  const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const f = iso(start) < props.filters.start_date ? props.filters.start_date : iso(start)
  const t = iso(end) > props.filters.end_date ? props.filters.end_date : iso(end)
  return { f, t }
}
const lineHref = (row: any, l: BookLine) => {
  const { f, t } = rowRange(row)
  return l.type === 'expense'
    ? `/${props.company.slug}/reports/statements?kind=expense&id=${l.account_id}&from=${f}&to=${t}`
    : `/${props.company.slug}/accounts/${l.account_id}`
}
const hasLines = (row: any, key: string) => Array.isArray(row[key]) && row[key].length > 0
const cellBtn = 'tabular-nums underline decoration-dotted underline-offset-2 hover:decoration-solid'

const performanceColumns = [
  { key: 'label', label: 'Period', kind: 'text' as const },
  { key: 'liters', label: 'Liters', kind: 'amount' as const },
  { key: 'revenue', label: 'Sales', kind: 'amount' as const },
  { key: 'cogs', label: 'Cost of sales', kind: 'amount' as const },
  { key: 'gross_profit', label: 'Gross profit', kind: 'amount' as const },
  { key: 'expenses', label: 'Expenses', kind: 'amount' as const },
  { key: 'other', label: 'Other', kind: 'amount' as const },
  { key: 'net_station_profit', label: 'Net', kind: 'amount' as const },
  { key: 'cash_variance', label: 'Cash variance', kind: 'amount' as const },
]

const cashColumns = [
  { key: 'label', label: 'Date', kind: 'text' as const },
  { key: 'opening_cash', label: 'Opening', kind: 'amount' as const },
  { key: 'cash_in', label: 'In', kind: 'amount' as const },
  { key: 'money_out', label: 'Out', kind: 'amount' as const },
  { key: 'expected_closing', label: 'Expected', kind: 'amount' as const },
  { key: 'closing_cash', label: 'Counted', kind: 'amount' as const },
  { key: 'variance', label: 'Over/short', kind: 'amount' as const },
]

const movementCards = computed(() => [
  { label: 'Partner deposits', value: props.movementTotals.partner_deposits },
  { label: 'Amanat received', value: props.movementTotals.amanat_deposits },
  { label: 'Other deposits', value: props.movementTotals.other_deposits },
  { label: 'Non-cash receipts', value: props.movementTotals.payment_receipts },
  { label: 'Bank deposits', value: props.movementTotals.bank_deposits },
  { label: 'Employee advances', value: props.movementTotals.employee_advances },
  { label: 'Payroll paid', value: props.movementTotals.payroll_payouts },
  { label: 'Bill payments', value: props.movementTotals.bill_payments },
])
</script>

<template>
  <Head title="Profit by day" />

  <PageShell
    title="Profit by day"
    description="Sales, profit and cash from each daily close."
    :icon="BarChart3"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-5">
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Filters</CardTitle>
          <CardDescription>Use the same date range for every number on this report.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" @click="setRange('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setRange('yesterday')">Yesterday</Button>
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
                <SelectTrigger class="w-44">
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
            {{ number(totals.liters) }} L sold
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Gross profit</CardDescription>
            <CardTitle class="text-2xl"><MoneyText :amount="totals.gross_profit" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Droplets class="h-4 w-4 text-status-success" />
            {{ percent(totals.gross_margin_percent) }} margin
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Net profit</CardDescription>
            <CardTitle class="text-2xl"><MoneyText :amount="totals.net_station_profit" :currency="company.base_currency" /></CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <ReceiptText class="h-4 w-4 text-status-info" />
            Same as Profit &amp; Loss
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Cash variance</CardDescription>
            <CardTitle class="text-2xl" :class="varianceTone(totals.cash_variance)">
              <MoneyText :amount="totals.cash_variance" :currency="company.base_currency" />
            </CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <WalletCards class="h-4 w-4 text-status-attention" />
            Closing cash <MoneyText :amount="totals.closing_cash" :currency="company.base_currency" />
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-5">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Performance by {{ groupBy }}</CardTitle>
            <CardDescription>
              From the books; click a figure to see its accounts.
              <Hint>
                What adds up
                <template #content>
                  <p>Sales - cost of sales = gross profit.</p>
                  <p>Gross profit - expenses + other = net, the Profit &amp; Loss figure.</p>
                  <p>Expenses: entered under Money out › Expenses. Other: tank gains and losses, salaries, discounts, card charges, rental and other income.</p>
                </template>
              </Hint>
              <Link :href="`/${company.slug}/fuel/reports/product-profitability`" class="text-primary underline-offset-4 hover:underline">Profit by fuel</Link>
            </CardDescription>
          </CardHeader>
          <CardContent class="p-0">
            <LedgerRegister :data="rows" :columns="performanceColumns" key-field="key" :expanded="(row: any) => isOpen(row)">
              <template #empty>No posted Daily Close records found for this range.</template>

              <template #cell-label="{ row }">
                <div class="font-medium">{{ row.label }}</div>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                  <MetaChip tone="neutral" bare>{{ row.daily_close_count }} close{{ row.daily_close_count === 1 ? '' : 's' }}</MetaChip>
                  <Link
                    v-if="row.detail_url_id"
                    :href="`/${company.slug}/fuel/daily-close/${row.detail_url_id}`"
                    class="text-primary underline-offset-4 hover:underline"
                  >
                    {{ row.daily_close_numbers[0] }}
                  </Link>
                </div>
              </template>

              <template #cell-liters="{ row }">{{ number(row.liters) }}</template>
              <template #cell-revenue="{ row }">
                <button v-if="hasLines(row, 'sales_lines')" type="button" :class="cellBtn" @click="toggle(row.key, 'sales')"><MoneyText :amount="row.revenue" :currency="company.base_currency" /></button>
                <MoneyText v-else :amount="row.revenue" :currency="company.base_currency" />
              </template>
              <template #cell-cogs="{ row }">
                <button v-if="hasLines(row, 'cost_lines')" type="button" :class="cellBtn" @click="toggle(row.key, 'cost')"><MoneyText :amount="row.cogs" :currency="company.base_currency" /></button>
                <MoneyText v-else :amount="row.cogs" :currency="company.base_currency" />
              </template>
              <template #cell-gross_profit="{ row }">
                <div class="font-medium"><MoneyText :amount="row.gross_profit" :currency="company.base_currency" /></div>
                <div class="text-xs text-muted-foreground">{{ percent(row.gross_margin_percent) }}</div>
              </template>
              <template #cell-expenses="{ row }">
                <button v-if="hasLines(row, 'expense_lines')" type="button" :class="cellBtn" @click="toggle(row.key, 'expenses')"><MoneyText :amount="row.expenses" :currency="company.base_currency" /></button>
                <MoneyText v-else :amount="row.expenses" :currency="company.base_currency" />
              </template>
              <template #cell-other="{ row }">
                <button v-if="hasLines(row, 'other_lines')" type="button" :class="cellBtn" @click="toggle(row.key, 'other')"><MoneyText :amount="row.other" :currency="company.base_currency" /></button>
                <MoneyText v-else :amount="row.other" :currency="company.base_currency" />
              </template>
              <template #cell-net_station_profit="{ row }"><MoneyText :amount="row.net_station_profit" :currency="company.base_currency" /></template>
              <template #cell-cash_variance="{ row }">
                <span :class="varianceTone(row.cash_variance)"><MoneyText :amount="row.cash_variance" :currency="company.base_currency" /></span>
              </template>
              <!-- The opened column's accounts, each linked to its statement. -->
              <template #row-detail="{ row }">
                <div class="px-4 py-2 text-sm">
                  <p class="mb-1 text-xs font-medium text-muted-foreground">{{ open ? partTitle[open.part] : '' }} · {{ row.label }}</p>
                  <ul class="grid gap-x-8 sm:grid-cols-2">
                    <li v-for="l in linesOf(row)" :key="l.account_id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1">
                      <span>{{ l.name }} <span class="text-xs text-muted-foreground">{{ l.code }}</span></span>
                      <Link :href="lineHref(row, l)" class="tabular-nums underline-offset-2 hover:underline" :class="open?.part === 'other' && l.amount < 0 ? 'text-status-attention' : ''">
                        <MoneyText :amount="l.amount" :currency="company.base_currency" :fraction-digits="0" />
                      </Link>
                    </li>
                  </ul>
                </div>
              </template>
            </LedgerRegister>
          </CardContent>
        </Card>

      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Cash Control</CardTitle>
            <CardDescription>Expected and counted cash by Daily Close.</CardDescription>
          </CardHeader>
          <CardContent class="p-0">
            <LedgerRegister :data="cashRows" :columns="cashColumns" key-field="transaction_id">
              <template #empty>No cash records in this range.</template>

              <template #cell-label="{ row }">
                <Link :href="`/${company.slug}/fuel/daily-close/${row.transaction_id}`" class="font-medium text-primary underline-offset-4 hover:underline">
                  {{ row.label }}
                </Link>
                <div class="text-xs text-muted-foreground">{{ row.transaction_number }}</div>
              </template>

              <template #cell-opening_cash="{ row }"><MoneyText :amount="row.opening_cash" :currency="company.base_currency" /></template>
              <template #cell-cash_in="{ row }"><MoneyText :amount="row.cash_sales + row.money_in" :currency="company.base_currency" /></template>
              <template #cell-money_out="{ row }"><MoneyText :amount="row.money_out" :currency="company.base_currency" /></template>
              <template #cell-expected_closing="{ row }"><MoneyText :amount="row.expected_closing" :currency="company.base_currency" /></template>
              <template #cell-closing_cash="{ row }"><MoneyText :amount="row.closing_cash" :currency="company.base_currency" /></template>
              <template #cell-variance="{ row }">
                <span :class="varianceTone(row.variance)"><MoneyText :amount="row.variance" :currency="company.base_currency" /></span>
              </template>
            </LedgerRegister>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-base">Station Movements</CardTitle>
            <CardDescription>Money and obligations captured through Daily Close.</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-3 sm:grid-cols-2">
              <div v-for="item in movementCards" :key="item.label" class="rounded-md border p-3">
                <div class="text-sm text-muted-foreground">{{ item.label }}</div>
                <div class="mt-1 text-lg font-semibold"><MoneyText :amount="item.value" :currency="company.base_currency" /></div>
              </div>
            </div>
            <div class="mt-4 rounded-md border p-3">
              <div class="flex items-center gap-2 text-sm font-medium">
                <ClipboardCheck class="h-4 w-4 text-muted-foreground" />
                Stock variance
              </div>
              <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                  <div class="text-sm text-muted-foreground">Loss</div>
                  <div class="text-lg font-semibold text-status-critical">{{ number(totals.stock_loss) }} L</div>
                </div>
                <div>
                  <div class="text-sm text-muted-foreground">Gain</div>
                  <div class="text-lg font-semibold text-status-success">{{ number(totals.stock_gain) }} L</div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

    </div>
  </PageShell>
</template>
