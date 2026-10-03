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
interface SaleLine { item_id: string; name: string; liters: number; amount: number }
interface Money {
  as_of: string
  cash: number | null
  banks: { id: string; name: string; balance: number }[]
  receivable: number
  payable: number
  amanat: number | null
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
}
interface Purchases { liters: number; amount: number }
interface Today {
  as_of: string
  close: {
    last_date: string | null
    last_id: string | null
    first_date: string | null
    next_date: string
    next_has_close: boolean
    next_is_due: boolean
    parked_dates: string[]
    missing_dates: string[]
  }
  payroll: { enabled: boolean; reminder: { month: string; label: string; drafts: number } | null; owed_count: number; owed_total: number }
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
    links: { month_summary: string; stock_statement: string; expenses: string; profit: string }
  }
  rates: { item_id: string; name: string; sale_rate: number | null; purchase_rate: number | null; margin: number | null; effective_date: string | null }[]
  attention: { label: string; detail: string | null; href: string }[]
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
  stock: { item_id: string; name: string; unit: string | null; has_tank: boolean; opening: number | null; bought: number | null; sold: number; closing: number | null; variance: number | null }[]
  money: Money
  closes: { count: number; days: number }
  links: { month_summary: string; stock_statement: string; expenses: string; profit_loss: string; profit: string }
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

const breadcrumbs = computed(() => [{ title: 'Dashboard', href: `/${slug.value}` }])

// ---- Today ----
const close = computed(() => props.today.close)
const closeStatus = computed(() => {
  const c = close.value
  if (!c.last_date) return 'No close yet'
  if (c.next_is_due && !c.next_has_close) return `${dayMonth(c.next_date)} not closed`
  return `${dayMonth(c.last_date)} closed`
})
const closeAttention = computed(() => close.value.next_is_due && !close.value.next_has_close)

const daysText = (t: Tank) => {
  if (t.days_left === null) return 'No sales yet'
  if (t.days_left < 1) return 'Under 1 day'
  return `~${Math.round(t.days_left)} ${Math.round(t.days_left) === 1 ? 'day' : 'days'}`
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

const quick = computed(() => [
  { label: 'Start close', href: `/${slug.value}/fuel/daily-close`, icon: ClipboardCheck },
  { label: 'Fuel delivery', href: `/${slug.value}/fuel/receipts`, icon: Droplets },
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
                <p class="text-lg font-medium" :class="closeAttention ? 'text-status-attention' : ''">{{ closeStatus }}</p>
                <p v-if="close.last_date" class="text-sm text-text-secondary">Last close {{ shortDate(close.last_date) }}</p>
              </div>
              <Button as-child>
                <Link :href="`/${slug}/fuel/daily-close`"><ClipboardCheck class="mr-2 h-4 w-4" />Start close</Link>
              </Button>
            </div>
            <ul v-if="close.missing_dates.length || close.parked_dates.length || today.payroll.reminder" class="space-y-1 text-sm">
              <li v-if="close.missing_dates.length" class="text-status-attention">
                Missing: {{ close.missing_dates.slice(0, 6).map(dayMonth).join(', ') }}<span v-if="close.missing_dates.length > 6"> +{{ close.missing_dates.length - 6 }}</span>
              </li>
              <li v-if="close.parked_dates.length" class="text-status-attention">
                Parked: {{ close.parked_dates.map(dayMonth).join(', ') }}
              </li>
              <li v-if="today.payroll.reminder" class="text-status-attention">
                <Link :href="`/${slug}/payroll`" class="hover:underline">Payroll due: {{ today.payroll.reminder.label }}</Link>
              </li>
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">Money</CardTitle></CardHeader>
          <CardContent>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
              <div v-if="today.money.cash !== null" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="text-text-secondary">Cash on hand</dt>
                <dd><MoneyText :amount="today.money.cash" :currency="currency" :fraction-digits="0" /></dd>
              </div>
              <div v-for="b in today.money.banks" :key="b.id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="truncate text-text-secondary">{{ b.name }}</dt>
                <dd><MoneyText :amount="b.balance" :currency="currency" :fraction-digits="0" /></dd>
              </div>
              <div class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="text-text-secondary">Customers owe us</dt>
                <dd><MoneyText :amount="today.money.receivable" :currency="currency" :fraction-digits="0" /></dd>
              </div>
              <div class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="text-text-secondary">We owe suppliers</dt>
                <dd><MoneyText :amount="today.money.payable" :currency="currency" :fraction-digits="0" /></dd>
              </div>
              <div v-if="today.money.amanat !== null" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <dt class="text-text-secondary">Amanat held</dt>
                <dd><MoneyText :amount="today.money.amanat" :currency="currency" :fraction-digits="0" /></dd>
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
                  <span class="font-medium">{{ t.name }}<span v-if="t.item_name" class="font-normal text-text-secondary"> · {{ t.item_name }}</span></span>
                  <span class="text-sm tabular-nums" :class="lowStock(t) ? 'font-medium text-status-attention' : 'text-text-secondary'">{{ daysText(t) }}</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded bg-surface-sunken" role="img" :aria-label="`${t.name} ${t.percent ?? 0}% full`">
                  <div class="h-full rounded" :class="barClass(t)" :style="{ width: `${t.percent ?? 0}%` }" />
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs tabular-nums text-text-secondary">
                  <span>{{ litres(t.level) }} L<span v-if="t.capacity"> of {{ litres(t.capacity) }} L · {{ t.percent === null ? '—' : Math.round(t.percent) }}%</span></span>
                  <span v-if="t.pending_liters > 0">{{ litres(t.pending_liters) }} L on the way</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">This month · {{ today.month.label }}</CardTitle></CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
              <div>
                <p class="text-xs text-text-secondary">Sales</p>
                <MoneyText :amount="today.month.sales_total" :currency="currency" :fraction-digits="0" />
              </div>
              <div>
                <p class="text-xs text-text-secondary">Gross profit</p>
                <MoneyText :amount="today.month.gross_profit" :currency="currency" :fraction-digits="0" />
              </div>
              <div>
                <p class="text-xs text-text-secondary">Expenses</p>
                <MoneyText :amount="today.month.expenses" :currency="currency" :fraction-digits="0" />
              </div>
              <div>
                <p class="text-xs text-text-secondary">Short / over</p>
                <MoneyText :amount="today.month.short_over" :currency="currency" :fraction-digits="0" />
              </div>
            </div>
            <ul v-if="today.month.sales.length" class="grid grid-cols-1 gap-x-8 text-sm sm:grid-cols-2">
              <li v-for="s in today.month.sales" :key="s.item_id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                <span class="text-text-secondary">{{ s.name }}</span>
                <span class="tabular-nums">{{ litres(s.liters) }} L · <MoneyText :amount="s.amount" :currency="currency" :fraction-digits="0" /></span>
              </li>
            </ul>
            <p class="text-sm tabular-nums text-text-secondary">
              Bought {{ litres(today.month.purchases.liters) }} L ·
              <MoneyText :amount="today.month.purchases.amount" :currency="currency" :fraction-digits="0" />
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
                    <td class="py-1.5 pr-3">{{ r.name }}</td>
                    <td class="px-3 py-1.5 text-right"><MoneyText v-if="r.sale_rate !== null" :amount="r.sale_rate" :currency="currency" :show-currency="false" /><span v-else>—</span></td>
                    <td class="px-3 py-1.5 text-right"><MoneyText v-if="r.purchase_rate !== null" :amount="r.purchase_rate" :currency="currency" :show-currency="false" /><span v-else>—</span></td>
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
              <li v-for="(a, i) in today.attention" :key="i">
                <Link :href="a.href" class="flex items-center justify-between gap-3 py-2 text-sm hover:bg-surface-band">
                  <span class="font-medium text-status-attention">{{ a.label }}</span>
                  <span class="flex items-center gap-2 text-text-secondary">
                    <span v-if="a.detail" class="tabular-nums">{{ a.detail }}</span>
                    <ArrowRight class="h-4 w-4" />
                  </span>
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
                <div>
                  <p class="text-xs text-text-secondary">Sales</p>
                  <MoneyText :amount="h.sales_total" :currency="currency" :fraction-digits="0" />
                </div>
                <div>
                  <p class="text-xs text-text-secondary">Gross profit</p>
                  <MoneyText :amount="h.gross_profit" :currency="currency" :fraction-digits="0" />
                </div>
                <div>
                  <p class="text-xs text-text-secondary">Expenses</p>
                  <MoneyText :amount="h.expenses" :currency="currency" :fraction-digits="0" />
                </div>
                <div>
                  <p class="text-xs text-text-secondary">Short / over</p>
                  <MoneyText :amount="h.short_over" :currency="currency" :fraction-digits="0" />
                </div>
              </div>
              <ul v-if="h.sales.length" class="grid grid-cols-1 gap-x-8 text-sm sm:grid-cols-2">
                <li v-for="s in h.sales" :key="s.item_id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <span class="text-text-secondary">{{ s.name }}</span>
                  <span class="tabular-nums">{{ litres(s.liters) }} L · <MoneyText :amount="s.amount" :currency="currency" :fraction-digits="0" /></span>
                </li>
              </ul>
              <p class="text-sm tabular-nums text-text-secondary">
                Bought {{ litres(h.purchases.liters) }} L ·
                <MoneyText :amount="h.purchases.amount" :currency="currency" :fraction-digits="0" />
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
                      <th class="px-3 py-1.5 text-right font-normal">Closing</th>
                      <th class="py-1.5 pl-3 text-right font-normal">Variance</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in h.stock" :key="r.item_id" class="border-t border-rule-subtle">
                      <td class="py-1.5 pr-3">{{ r.name }}<span v-if="r.unit" class="text-text-secondary"> ({{ r.unit }})</span></td>
                      <td class="px-3 py-1.5 text-right">{{ litres(r.opening) }}</td>
                      <td class="px-3 py-1.5 text-right">{{ litres(r.bought) }}</td>
                      <td class="px-3 py-1.5 text-right">{{ litres(r.sold) }}</td>
                      <td class="px-3 py-1.5 text-right">{{ litres(r.closing) }}</td>
                      <td class="py-1.5 pl-3 text-right">{{ signedLitres(r.variance) }}</td>
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
                <div v-if="h.money.cash !== null" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="text-text-secondary">Cash on hand</dt>
                  <dd><MoneyText :amount="h.money.cash" :currency="currency" :fraction-digits="0" /></dd>
                </div>
                <div v-for="b in h.money.banks" :key="b.id" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="truncate text-text-secondary">{{ b.name }}</dt>
                  <dd><MoneyText :amount="b.balance" :currency="currency" :fraction-digits="0" /></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="text-text-secondary">Customers owe us</dt>
                  <dd><MoneyText :amount="h.money.receivable" :currency="currency" :fraction-digits="0" /></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="text-text-secondary">We owe suppliers</dt>
                  <dd><MoneyText :amount="h.money.payable" :currency="currency" :fraction-digits="0" /></dd>
                </div>
                <div v-if="h.money.amanat !== null" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1.5">
                  <dt class="text-text-secondary">Amanat held</dt>
                  <dd><MoneyText :amount="h.money.amanat" :currency="currency" :fraction-digits="0" /></dd>
                </div>
              </dl>
            </CardContent>
          </Card>
        </template>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
