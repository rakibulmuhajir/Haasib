<script setup lang="ts">
/**
 * A fuel station's home: Today (where the close stands, money, tanks, the month, rates, what needs
 * doing) and History (the same figures for any past range). Every figure comes from FuelHomeService,
 * which reads the report that owns it -- each block links to that report.
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ClipboardCheck, Droplets, Receipt, TrendingUp, ArrowRight, Home } from 'lucide-vue-next'

interface Company { id: string; name: string; slug: string; base_currency: string }
interface SaleLine { item_id: string; name: string; liters: number; amount: number; href: string }
interface Money {
  as_of: string
  cash: number | null
  cash_href: string | null
  banks: { id: string; name: string; code: string | null; balance: number; href: string }[]
  receivable: number
  receivable_href: string
  receivable_aging_href: string
  receivable_customers: number
  overdue: { count: number; total: number }
  payable: number
  payable_href: string
  payable_aging_href: string
  unpaid_bills: number
  oldest_bill: { number: string; date: string; href: string } | null
  amanat: number | null
  amanat_href: string
  amanat_holders: number
}
interface Tank {
  id: string
  name: string
  item_name: string | null
  capacity: number | null
  level: number | null
  percent: number | null
  avg_daily_sold: number | null
  days_left: number | null
  pending_liters: number
  pending_bills: { number: string; liters: number; href: string }[]
  level_href: string | null
  stock_href: string | null
}
interface Purchases { liters: number; amount: number; bills: number }
interface Today {
  as_of: string
  close: {
    last_date: string | null
    last_id: string | null
    last_posted_at: string | null
    last_posted_by: string | null
    last_counted_cash: number | null
    first_date: string | null
    next_date: string
    next_has_close: boolean
    next_is_due: boolean
    parked_dates: string[]
    missing_dates: string[]
  }
  payroll: { enabled: boolean; reminder: { month: string; label: string; drafts: number } | null; owed_count: number; owed_total: number; owed: { name: string; amount: number }[]; owed_month: string | null }
  money: Money
  tanks: Tank[]
  month: {
    label: string
    sales: SaleLine[]
    sales_total: number
    gross_profit: number
    expenses: number
    short_over: number
    purchases: Purchases
    revenue: number
    cogs: number
    short_days: number
    over_days: number
    links: { month_summary: string; stock_statement: string; expenses: string; profit: string }
  }
  rates: { item_id: string; name: string; sale_rate: number | null; purchase_rate: number | null; margin: number | null; effective_date: string | null; sale_href: string; cost_bill: { number: string; date: string; rate: number; href: string } | null }[]
  attention: { label: string; detail: string | null; href: string; hint?: string[] }[]
}
interface History {
  from: string
  to: string
  sales: SaleLine[]
  sales_total: number
  gross_profit: number
  expenses: number
  short_over: number
  purchases: Purchases
  revenue: number
  cogs: number
  short_days: number
  over_days: number
  stock: { item_id: string; name: string; unit: string | null; has_tank: boolean; opening: number | null; bought: number | null; sold: number; closing: number | null; variance: number | null; href: string; closing_href: string | null }[]
  money: Money
  closes: { count: number; days: number }
  links: { short_over: string | null; stock_variance: string; month_summary: string; stock_statement: string; expenses: string; profit_loss: string; profit: string }
}

const props = defineProps<{
  company: Company
  tab: 'today' | 'history'
  today: Today
  history?: History
  range?: { from: string; to: string }
}>()

const slug = computed(() => props.company.slug)
const currency = computed(() => props.company.base_currency || 'PKR')
const tab = ref<string>(props.tab)

const litreFmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
const litres = (v: number | null | undefined) => (v === null || v === undefined ? '—' : litreFmt.format(v))
const signedLitres = (v: number | null | undefined) => (v === null || v === undefined ? '—' : `${v > 0 ? '+' : ''}${litreFmt.format(v)}`)
const shortDate = (d: string | null) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—')
const dayMonth = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })

const numFmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
const amt = (v: number) => numFmt.format(Math.round(v))
const lnk = 'tabular-nums hover:underline'

interface Row { key: string; label: string; amount: number; href: string | null; hint: string[]; extras: { label: string; href: string }[] }

// The money rows, shared by Today and History: the figure links to its statement, the label carries the working.
const moneyRows = (m: Money, today: boolean): Row[] => {
  const rows: Row[] = []
  if (m.cash !== null) {
    const hint = [`Cash on Hand in the books on ${shortDate(m.as_of)}.`]
    if (today && props.today.close.last_counted_cash !== null && props.today.close.last_date) {
      hint[0] = `Cash on Hand in the books on ${shortDate(m.as_of)}; the last count was ${amt(props.today.close.last_counted_cash)} on ${shortDate(props.today.close.last_date)}.`
    }
    rows.push({ key: 'cash', label: 'Cash on hand', amount: m.cash, href: m.cash_href, hint, extras: [] })
  }
  for (const b of m.banks) {
    rows.push({ key: b.id, label: b.name, amount: b.balance, href: b.href, hint: [b.code ? `Account ${b.code}` : 'Bank account'], extras: [] })
  }
  const overdue = m.overdue.count > 0 ? `${m.overdue.count} overdue 30+ days (${amt(m.overdue.total)}).` : 'None overdue 30+ days.'
  rows.push({
    key: 'receivable', label: 'Customers owe us', amount: m.receivable, href: m.receivable_href,
    hint: [`${m.receivable_customers} ${m.receivable_customers === 1 ? 'customer owes' : 'customers owe'} us; ${overdue}`],
    extras: [{ label: 'Ageing', href: m.receivable_aging_href }],
  })
  const extras = [{ label: 'Ageing', href: m.payable_aging_href }]
  if (m.oldest_bill) extras.push({ label: 'Oldest bill', href: m.oldest_bill.href })
  rows.push({
    key: 'payable', label: 'We owe suppliers', amount: m.payable, href: m.payable_href,
    hint: [`${m.unpaid_bills} unpaid ${m.unpaid_bills === 1 ? 'bill' : 'bills'}.`, ...(m.oldest_bill ? [`Oldest: ${m.oldest_bill.number}, ${shortDate(m.oldest_bill.date)}.`] : [])],
    extras,
  })
  if (m.amanat !== null) {
    rows.push({ key: 'amanat', label: 'Amanat held', amount: m.amanat, href: m.amanat_href, hint: [`Held for ${m.amanat_holders} ${m.amanat_holders === 1 ? 'holder' : 'holders'}.`], extras: [] })
  }
  return rows
}

interface Metric { key: string; label: string; amount: number; href: string | null; hint: string[] }

// Sales, gross profit, expenses and short/over for a period, shared by Today and History.
const metricsFor = (d: { sales_total: number; gross_profit: number; expenses: number; short_over: number; revenue: number; cogs: number; short_days: number; over_days: number }, links: { sales: string; profit: string; expenses: string; short_over: string | null }, wording: string): Metric[] => [
  { key: 'sales', label: 'Sales', amount: d.sales_total, href: links.sales, hint: [`Everything sold ${wording}, as posted at each daily close.`] },
  { key: 'gp', label: 'Gross profit', amount: d.gross_profit, href: links.profit, hint: [`Revenue ${amt(d.revenue)} - cost of fuel sold ${amt(d.cogs)} = ${amt(d.gross_profit)}.`] },
  { key: 'expenses', label: 'Expenses', amount: d.expenses, href: links.expenses, hint: ['Entered under Daily Close > Money out > Expenses.'] },
  { key: 'short', label: 'Short / over', amount: d.short_over, href: links.short_over, hint: [`${d.short_days} ${d.short_days === 1 ? 'day' : 'days'} short, ${d.over_days} ${d.over_days === 1 ? 'day' : 'days'} over.`, 'Counted cash against what the books expect.'] },
]

const purchasesHint = (p: Purchases) => [`${litres(p.liters)} L of fuel on ${p.bills} ${p.bills === 1 ? 'bill' : 'bills'}.`]

const breadcrumbs = computed(() => [{ title: 'Dashboard', href: `/${slug.value}` }])

// ---- Today ----
const close = computed(() => props.today.close)
const closeStatus = computed(() => {
  const c = close.value
  if (!c.last_date) return 'No close yet'
  if (c.next_is_due && !c.next_has_close) return `${dayMonth(c.next_date)} not closed`
  return `${dayMonth(c.last_date)} closed`
})
const closeHref = computed(() => {
  const c = close.value
  if (!c.last_date) return `/${slug.value}/fuel/daily-close`
  if (c.next_is_due && !c.next_has_close) return `/${slug.value}/fuel/daily-close?date=${c.next_date}`
  return c.last_id ? `/${slug.value}/fuel/daily-close/${c.last_id}` : `/${slug.value}/fuel/daily-close`
})
const closeDateHref = (d: string) => `/${slug.value}/fuel/daily-close?date=${d}`
const lastCloseHint = computed(() => {
  const c = close.value
  if (!c.last_posted_at && !c.last_posted_by) return ['Posted close.']
  return [`Posted ${c.last_posted_at ?? ''}${c.last_posted_by ? ` by ${c.last_posted_by}` : ''}.`]
})
const closeAttention = computed(() => close.value.next_is_due && !close.value.next_has_close)

const daysText = (t: Tank) => {
  if (t.days_left === null) return 'No sales yet'
  if (t.days_left < 1) return 'Under 1 day'
  return `~${Math.round(t.days_left)} ${Math.round(t.days_left) === 1 ? 'day' : 'days'}`
}
const daysHint = (t: Tank) => {
  if (t.days_left === null || t.level === null || t.avg_daily_sold === null) return ['No litres pumped in the last 7 closes.']
  return [`${litres(t.level)} L / ${litres(t.avg_daily_sold)} L a day (pumped, last 7 closes).`]
}
const lowStock = (t: Tank) => t.days_left !== null && t.days_left < 1
const barClass = (t: Tank) => (lowStock(t) ? 'bg-status-attention' : 'bg-status-info')

// ---- History ----
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const base = computed(() => new Date(`${props.today.as_of}T00:00:00`))
const presets = computed(() => {
  const t = base.value
  const monthStart = new Date(t.getFullYear(), t.getMonth(), 1)
  return [
    { key: 'week', label: 'Last 7 days', from: iso(new Date(t.getFullYear(), t.getMonth(), t.getDate() - 6)), to: iso(t) },
    { key: 'month', label: 'This month', from: iso(monthStart), to: iso(t) },
    { key: 'last', label: 'Last month', from: iso(new Date(t.getFullYear(), t.getMonth() - 1, 1)), to: iso(new Date(t.getFullYear(), t.getMonth(), 0)) },
    { key: '3m', label: 'Last 3 months', from: iso(new Date(t.getFullYear(), t.getMonth() - 2, 1)), to: iso(t) },
    { key: 'all', label: 'All time', from: props.today.close.first_date ?? iso(monthStart), to: iso(t) },
  ]
})

const lastMonth = () => {
  const t = base.value
  return { from: iso(new Date(t.getFullYear(), t.getMonth() - 1, 1)), to: iso(new Date(t.getFullYear(), t.getMonth(), 0)) }
}
const from = ref(props.range?.from ?? lastMonth().from)
const to = ref(props.range?.to ?? lastMonth().to)
const activePreset = computed(() => presets.value.find((p) => p.from === from.value && p.to === to.value)?.key ?? null)

const load = (f: string, t: string) => {
  if (!f || !t) return
  from.value = f
  to.value = t
  router.get(`/${slug.value}`, { tab: 'history', from: f, to: t }, {
    preserveState: true,
    preserveScroll: true,
    only: ['tab', 'history', 'range'],
  })
}
const onTab = (value: string | number) => {
  tab.value = String(value)
  if (tab.value === 'history' && !props.history) load(from.value, to.value)
}

const h = computed(() => props.history)
const closesText = computed(() => (h.value ? `${h.value.closes.count} of ${h.value.closes.days} days closed` : ''))

const todayMoney = computed(() => moneyRows(props.today.money, true))
const histMoney = computed(() => (h.value ? moneyRows(h.value.money, false) : []))
const todayMetrics = computed(() => {
  const m = props.today.month
  return metricsFor(
    { ...m, short_over: m.short_over },
    { sales: m.links.month_summary, profit: m.links.profit, expenses: m.links.expenses, short_over: m.links.month_summary },
    'this month',
  )
})
const histMetrics = computed(() => {
  const d = h.value
  if (!d) return []
  return metricsFor(d, { sales: d.links.profit, profit: d.links.profit, expenses: d.links.expenses, short_over: d.links.short_over }, 'in this period')
})
const rateHint = (r: Today['rates'][number]) => {
  const out: string[] = []
  if (r.sale_rate !== null && r.purchase_rate !== null && r.margin !== null) out.push(`${r.sale_rate} - ${r.purchase_rate} = ${r.margin} per litre.`)
  if (r.effective_date) out.push(`Sale rate set ${shortDate(r.effective_date)}.`)
  if (r.cost_bill) out.push(`Cost: last bill ${r.cost_bill.number}, ${shortDate(r.cost_bill.date)}, at ${r.cost_bill.rate}.`)
  return out.length ? out : ['No rate set.']
}

const quick = computed(() => [
  { label: 'Start close', href: `/${slug.value}/fuel/daily-close`, icon: ClipboardCheck },
  // Deliveries are entered in the close that receives them: open the next close at its Purchases.
  { label: 'Fuel delivery', href: `/${slug.value}/fuel/daily-close?date=${props.today.close.next_date}#purchases`, icon: Droplets },
  { label: 'Record expense', href: `/${slug.value}/expenses`, icon: Receipt },
  { label: 'Change rate', href: `/${slug.value}/fuel/rates`, icon: TrendingUp },
])
</script>

<template>
  <Head :title="company.name" />

  <PageShell :title="company.name" :icon="Home" :breadcrumbs="breadcrumbs">
    <Tabs :model-value="tab" class="space-y-5" @update:model-value="onTab">
      <TabsList>
        <TabsTrigger value="today">Today</TabsTrigger>
        <TabsTrigger value="history">History</TabsTrigger>
      </TabsList>

      <!-- ===================== TODAY ===================== -->
      <TabsContent value="today" class="space-y-5">
        <Card>
          <CardHeader><CardTitle class="text-base">Today</CardTitle></CardHeader>
          <CardContent class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-lg font-medium" :class="closeAttention ? 'text-status-attention' : ''">
                  <Link :href="closeHref" class="hover:underline">{{ closeStatus }}</Link>
                </p>
                <p v-if="close.last_date" class="text-sm text-text-secondary">
                  <Hint>Last close<template #content><p v-for="(l, i) in lastCloseHint" :key="i">{{ l }}</p></template></Hint>
                  <Link v-if="close.last_id" :href="`/${slug}/fuel/daily-close/${close.last_id}`" :class="lnk"> {{ shortDate(close.last_date) }}</Link>
                  <span v-else> {{ shortDate(close.last_date) }}</span>
                </p>
              </div>
              <Button as-child>
                <Link :href="`/${slug}/fuel/daily-close`"><ClipboardCheck class="mr-2 h-4 w-4" />Start close</Link>
              </Button>
            </div>
            <ul v-if="close.missing_dates.length || close.parked_dates.length || today.payroll.reminder" class="space-y-1 text-sm">
              <li v-if="close.missing_dates.length" class="text-status-attention">
                Missing:
                <template v-for="(d, i) in close.missing_dates.slice(0, 6)" :key="d"><Link :href="closeDateHref(d)" class="hover:underline">{{ dayMonth(d) }}</Link><span v-if="i < Math.min(close.missing_dates.length, 6) - 1">, </span></template>
                <span v-if="close.missing_dates.length > 6"> +{{ close.missing_dates.length - 6 }}</span>
              </li>
              <li v-if="close.parked_dates.length" class="text-status-attention">
                Parked:
                <template v-for="(d, i) in close.parked_dates" :key="d"><Link :href="closeDateHref(d)" class="hover:underline">{{ dayMonth(d) }}</Link><span v-if="i < close.parked_dates.length - 1">, </span></template>
              </li>
              <li v-if="today.payroll.reminder" class="text-status-attention">
                <Link :href="`/${slug}/payroll?month=${today.payroll.reminder.month}`" class="hover:underline">Payroll due: {{ today.payroll.reminder.label }}</Link>
              </li>
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">Money</CardTitle></CardHeader>
          <CardContent>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
              <div v-for="r in todayMoney" :key="r.key" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="truncate text-text-secondary">
                  <Hint>{{ r.label }}<template #content><p v-for="(l, i) in r.hint" :key="i">{{ l }}</p></template></Hint>
                </dt>
                <dd class="flex items-baseline gap-3">
                  <Link v-for="x in r.extras" :key="x.label" :href="x.href" class="text-xs text-status-info hover:underline">{{ x.label }}</Link>
                  <Link v-if="r.href" :href="r.href" :class="lnk"><MoneyText :amount="r.amount" :currency="currency" :fraction-digits="0" /></Link>
                  <MoneyText v-else :amount="r.amount" :currency="currency" :fraction-digits="0" />
                </dd>
              </div>
            </dl>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-base">
              <Hint>
                Tanks
                <template #content>Level is the last close's dip. Days left is that level divided by the average litres sold per day over the last 7 closes.</template>
              </Hint>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p v-if="!today.tanks.length" class="text-sm text-text-secondary">No tanks.</p>
            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-for="t in today.tanks" :key="t.id" class="space-y-1.5">
                <div class="flex items-baseline justify-between gap-3">
                  <span class="font-medium">
                    <Link v-if="t.stock_href" :href="t.stock_href" class="hover:underline">{{ t.name }}</Link><template v-else>{{ t.name }}</template><span v-if="t.item_name" class="font-normal text-text-secondary"> · {{ t.item_name }}</span>
                  </span>
                  <span class="text-sm tabular-nums" :class="lowStock(t) ? 'font-medium text-status-attention' : 'text-text-secondary'">
                    <Hint>{{ daysText(t) }}<template #content><p v-for="(l, i) in daysHint(t)" :key="i">{{ l }}</p></template></Hint>
                  </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded bg-surface-sunken" role="img" :aria-label="`${t.name} ${t.percent ?? 0}% full`">
                  <div class="h-full rounded" :class="barClass(t)" :style="{ width: `${t.percent ?? 0}%` }" />
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs tabular-nums text-text-secondary">
                  <span>
                    <Link v-if="t.level_href" :href="t.level_href" :class="lnk">{{ litres(t.level) }} L</Link><template v-else>{{ litres(t.level) }} L</template><span v-if="t.capacity"> of {{ litres(t.capacity) }} L · {{ t.percent === null ? '—' : Math.round(t.percent) }}%</span>
                  </span>
                  <span v-if="t.pending_liters > 0">
                    {{ litres(t.pending_liters) }} L on the way
                    <template v-for="b in t.pending_bills" :key="b.href"> · <Link :href="b.href" class="hover:underline">{{ b.number }}</Link></template>
                  </span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">This month · {{ today.month.label }}</CardTitle></CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
              <div v-for="m in todayMetrics" :key="m.key">
                <p class="text-xs text-text-secondary">
                  <Hint>{{ m.label }}<template #content><p v-for="(l, i) in m.hint" :key="i">{{ l }}</p></template></Hint>
                </p>
                <Link v-if="m.href" :href="m.href" :class="lnk"><MoneyText :amount="m.amount" :currency="currency" :fraction-digits="0" /></Link>
                <MoneyText v-else :amount="m.amount" :currency="currency" :fraction-digits="0" />
              </div>
            </div>
            <ul v-if="today.month.sales.length" class="grid grid-cols-1 gap-x-8 text-sm sm:grid-cols-2">
              <li v-for="s in today.month.sales" :key="s.item_id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <span class="text-text-secondary">{{ s.name }}</span>
                <Link :href="s.href" :class="lnk">{{ litres(s.liters) }} L · <MoneyText :amount="s.amount" :currency="currency" :fraction-digits="0" /></Link>
              </li>
            </ul>
            <p class="text-sm tabular-nums text-text-secondary">
              <Hint>Bought<template #content><p v-for="(l, i) in purchasesHint(today.month.purchases)" :key="i">{{ l }}</p></template></Hint>
              <Link :href="today.month.links.stock_statement" :class="lnk"> {{ litres(today.month.purchases.liters) }} L ·
              <MoneyText :amount="today.month.purchases.amount" :currency="currency" :fraction-digits="0" /></Link>
            </p>
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
              <Link :href="today.month.links.month_summary" class="text-status-info hover:underline">Month summary</Link>
              <Link :href="today.month.links.stock_statement" class="text-status-info hover:underline">Stock statement</Link>
              <Link :href="today.month.links.profit" class="text-status-info hover:underline">Fuel profit</Link>
              <Link :href="today.month.links.expenses" class="text-status-info hover:underline">Expenses</Link>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">Rates</CardTitle></CardHeader>
          <CardContent>
            <p v-if="!today.rates.length" class="text-sm text-text-secondary">No fuel rates.</p>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm tabular-nums">
                <thead class="text-xs text-text-secondary">
                  <tr>
                    <th class="py-1.5 pr-3 text-left font-normal">Fuel</th>
                    <th class="px-3 py-1.5 text-right font-normal">Sale</th>
                    <th class="px-3 py-1.5 text-right font-normal">Purchase</th>
                    <th class="py-1.5 pl-3 text-right font-normal">Margin</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="r in today.rates" :key="r.item_id" class="border-t border-rule-subtle">
                    <td class="py-1.5 pr-3"><Hint>{{ r.name }}<template #content><p v-for="(l, i) in rateHint(r)" :key="i">{{ l }}</p></template></Hint></td>
                    <td class="px-3 py-1.5 text-right"><Link v-if="r.sale_rate !== null" :href="r.sale_href" :class="lnk"><MoneyText :amount="r.sale_rate" :currency="currency" :show-currency="false" /></Link><span v-else>—</span></td>
                    <td class="px-3 py-1.5 text-right">
                      <Link v-if="r.purchase_rate !== null && r.cost_bill" :href="r.cost_bill.href" :class="lnk"><MoneyText :amount="r.purchase_rate" :currency="currency" :show-currency="false" /></Link>
                      <MoneyText v-else-if="r.purchase_rate !== null" :amount="r.purchase_rate" :currency="currency" :show-currency="false" />
                      <span v-else>—</span>
                    </td>
                    <td class="py-1.5 pl-3 text-right"><MoneyText v-if="r.margin !== null" :amount="r.margin" :currency="currency" :show-currency="false" /><span v-else>—</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">Needs attention</CardTitle></CardHeader>
          <CardContent>
            <p v-if="!today.attention.length" class="text-sm text-text-secondary">All clear.</p>
            <ul v-else class="divide-y divide-rule-subtle">
              <li v-for="(a, i) in today.attention" :key="i" class="flex items-center justify-between gap-3 py-2 text-sm">
                <span class="font-medium text-status-attention">
                  <Hint v-if="a.hint && a.hint.length">{{ a.label }}<template #content><p v-for="(l, j) in a.hint" :key="j">{{ l }}</p></template></Hint>
                  <template v-else>{{ a.label }}</template>
                </span>
                <Link :href="a.href" class="flex items-center gap-2 text-text-secondary hover:bg-surface-band hover:underline">
                  <span v-if="a.detail" class="tabular-nums">{{ a.detail }}</span>
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </li>
            </ul>
          </CardContent>
        </Card>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Button v-for="q in quick" :key="q.label" as-child :variant="q.label === 'Start close' ? 'default' : 'outline'">
            <Link :href="q.href"><component :is="q.icon" class="mr-2 h-4 w-4" />{{ q.label }}</Link>
          </Button>
        </div>
      </TabsContent>

      <!-- ===================== HISTORY ===================== -->
      <TabsContent value="history" class="space-y-5">
        <Card>
          <CardContent class="space-y-4 pt-6">
            <div class="flex flex-wrap gap-2">
              <Button
                v-for="p in presets"
                :key="p.key"
                size="sm"
                :variant="activePreset === p.key ? 'default' : 'outline'"
                @click="load(p.from, p.to)"
              >{{ p.label }}</Button>
            </div>
            <div class="flex flex-wrap items-end gap-3">
              <div class="grid gap-1.5">
                <Label for="home_from">From</Label>
                <Input id="home_from" v-model="from" type="date" class="w-40" />
              </div>
              <div class="grid gap-1.5">
                <Label for="home_to">To</Label>
                <Input id="home_to" v-model="to" type="date" class="w-40" />
              </div>
              <Button variant="outline" @click="load(from, to)">Apply</Button>
            </div>
          </CardContent>
        </Card>

        <p v-if="!h" class="py-8 text-center text-sm text-text-secondary">Loading…</p>

        <template v-else>
          <Card>
            <CardHeader>
              <CardTitle class="text-base">{{ shortDate(h.from) }} – {{ shortDate(h.to) }}</CardTitle>
              <p class="text-sm" :class="h.closes.count < h.closes.days ? 'text-status-attention' : 'text-text-secondary'">{{ closesText }}</p>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="m in histMetrics" :key="m.key">
                  <p class="text-xs text-text-secondary">
                    <Hint>{{ m.label }}<template #content><p v-for="(l, i) in m.hint" :key="i">{{ l }}</p></template></Hint>
                  </p>
                  <Link v-if="m.href" :href="m.href" :class="lnk"><MoneyText :amount="m.amount" :currency="currency" :fraction-digits="0" /></Link>
                  <MoneyText v-else :amount="m.amount" :currency="currency" :fraction-digits="0" />
                </div>
              </div>
              <ul v-if="h.sales.length" class="grid grid-cols-1 gap-x-8 text-sm sm:grid-cols-2">
                <li v-for="s in h.sales" :key="s.item_id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <span class="text-text-secondary">{{ s.name }}</span>
                  <Link :href="s.href" :class="lnk">{{ litres(s.liters) }} L · <MoneyText :amount="s.amount" :currency="currency" :fraction-digits="0" /></Link>
                </li>
              </ul>
              <p class="text-sm tabular-nums text-text-secondary">
                <Hint>Bought<template #content><p v-for="(l, i) in purchasesHint(h.purchases)" :key="i">{{ l }}</p></template></Hint>
                <Link :href="h.links.stock_statement" :class="lnk"> {{ litres(h.purchases.liters) }} L ·
                <MoneyText :amount="h.purchases.amount" :currency="currency" :fraction-digits="0" /></Link>
              </p>
              <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                <Link :href="h.links.month_summary" class="text-status-info hover:underline">Month summary</Link>
                <Link :href="h.links.profit" class="text-status-info hover:underline">Fuel profit</Link>
                <Link :href="h.links.profit_loss" class="text-status-info hover:underline">Profit &amp; Loss</Link>
                <Link :href="h.links.expenses" class="text-status-info hover:underline">Expenses</Link>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Stock</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <p v-if="!h.stock.length" class="text-sm text-text-secondary">No stock movement.</p>
              <div v-else class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                  <thead class="text-xs text-text-secondary">
                    <tr>
                      <th class="py-1.5 pr-3 text-left font-normal">Product</th>
                      <th class="px-3 py-1.5 text-right font-normal">Opening</th>
                      <th class="px-3 py-1.5 text-right font-normal">Bought</th>
                      <th class="px-3 py-1.5 text-right font-normal">Sold</th>
                      <th class="px-3 py-1.5 text-right font-normal"><Hint>Closing<template #content>Stock at the end of the period; the figure links to the last close in it.</template></Hint></th>
                      <th class="py-1.5 pl-3 text-right font-normal"><Hint>Variance<template #content>Dip reading against what the books expected, summed over the period.</template></Hint></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in h.stock" :key="r.item_id" class="border-t border-rule-subtle">
                      <td class="py-1.5 pr-3"><Link :href="r.href" class="hover:underline">{{ r.name }}</Link><span v-if="r.unit" class="text-text-secondary"> ({{ r.unit }})</span></td>
                      <td class="px-3 py-1.5 text-right"><Link :href="r.href" :class="lnk">{{ litres(r.opening) }}</Link></td>
                      <td class="px-3 py-1.5 text-right"><Link :href="r.href" :class="lnk">{{ litres(r.bought) }}</Link></td>
                      <td class="px-3 py-1.5 text-right"><Link :href="r.href" :class="lnk">{{ litres(r.sold) }}</Link></td>
                      <td class="px-3 py-1.5 text-right"><Link v-if="r.closing_href" :href="r.closing_href" :class="lnk">{{ litres(r.closing) }}</Link><template v-else>{{ litres(r.closing) }}</template></td>
                      <td class="py-1.5 pl-3 text-right"><Link v-if="r.variance !== null" :href="h.links.stock_variance" :class="lnk">{{ signedLitres(r.variance) }}</Link><template v-else>{{ signedLitres(r.variance) }}</template></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <Link :href="h.links.stock_statement" class="inline-block text-sm text-status-info hover:underline">Stock statement</Link>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">
                <Hint>
                  Money · {{ shortDate(h.money.as_of) }}
                  <template #content>Cash, banks and amanat are the ledger balance on this date. Owed amounts are the invoices and bills dated up to it that are still unpaid today.</template>
                </Hint>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div v-for="r in histMoney" :key="r.key" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="truncate text-text-secondary">
                    <Hint>{{ r.label }}<template #content><p v-for="(l, i) in r.hint" :key="i">{{ l }}</p></template></Hint>
                  </dt>
                  <dd class="flex items-baseline gap-3">
                    <Link v-for="x in r.extras" :key="x.label" :href="x.href" class="text-xs text-status-info hover:underline">{{ x.label }}</Link>
                    <Link v-if="r.href" :href="r.href" :class="lnk"><MoneyText :amount="r.amount" :currency="currency" :fraction-digits="0" /></Link>
                    <MoneyText v-else :amount="r.amount" :currency="currency" :fraction-digits="0" />
                  </dd>
                </div>
              </dl>
            </CardContent>
          </Card>
        </template>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
