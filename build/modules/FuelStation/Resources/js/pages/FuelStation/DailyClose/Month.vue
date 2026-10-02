<script setup lang="ts">
/**
 * Every posted Daily Close of one month added up into the day sheet's four parts. The service
 * does all the grouping and labelling; this page only renders it.
 */
import DailyCloseNav from '../../../components/DailyCloseNav.vue'
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface Source {
  close_id: string
  date: string
  amount: number
  quantity?: number
}

interface Line {
  label: string
  detail?: string | null
  amount: number
  days?: number
  item_id?: string | null
  sources?: Source[]
}

interface TankRow {
  name: string
  item_id?: string | null
  opening: number | null
  delivered: number | null
  sold: number
  expected: number | null
  closing: number | null
  variance: number
  daily_variance: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  summary: {
    month: string
    label: string
    prev_month: string
    next_month: string | null
    days_in_month: number
    close_count: number
    missing_dates: string[]
    closes: Array<{ id: string; transaction_number: string; date: string }>
    cash: { opening_close_id?: string; closing_close_id?: string; opening: number; money_in: number; money_out: number; short_over: number; closing: number; short_days: number; over_days: number; variance_days: Array<{ id: string; date: string; amount: number }> }
    sales: Line[]
    sales_total: number
    tanks: TankRow[]
    money_in: Line[]
    money_in_total: number
    money_out: Line[]
    money_out_total: number
  }
}>()

const currency = computed(() => props.company.base_currency || 'PKR')
const base = computed(() => `/${props.company.slug}/fuel/daily-close/month`)
const s = computed(() => props.summary)
const litres = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)
const dash = (v: number | null | undefined) => (v === null || v === undefined ? '—' : litres(v))
const shortDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
const closeUrl = (id: string) => `/${props.company.slug}/fuel/daily-close/${id}`
const monthStart = computed(() => `${s.value.month}-01`)
const monthEnd = computed(() => `${s.value.month}-${String(s.value.days_in_month).padStart(2, '0')}`)
const statementUrl = (itemId: string) =>
  `/${props.company.slug}/fuel/reports/stock-statement?item=${itemId}&start_date=${monthStart.value}&end_date=${monthEnd.value}`
// One line's source list open at a time: key is section + index.
const openLine = ref<string | null>(null)
const toggleLine = (key: string) => { openLine.value = openLine.value === key ? null : key }
// Detail for a grouped line: what it is, plus how many days it spans when more than one.
const detailOf = (l: Line) => [l.detail, (l.days ?? 0) > 1 ? `${l.days} days` : null].filter(Boolean).join(' · ')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close history', href: `/${props.company.slug}/fuel/daily-close/history` },
  { title: s.value.label, href: `${base.value}?month=${s.value.month}` },
])

const shortOver = computed(() => s.value.cash.short_over)
// Which days made up the month's short/over: 'short' or 'over' shows that list, clicking again hides it.
const varianceFilter = ref<'short' | 'over' | null>(null)
const toggleVariance = (kind: 'short' | 'over') => { varianceFilter.value = varianceFilter.value === kind ? null : kind }
const varianceDays = computed(() =>
    (s.value.cash.variance_days ?? []).filter((d) => (varianceFilter.value === 'short' ? d.amount < 0 : d.amount > 0)),
)
</script>

<template>
  <PageShell
    :title="summary.label"
    :description="`${summary.close_count} of ${summary.days_in_month} days closed`"
    :icon="Calendar"
    :breadcrumbs="breadcrumbs"
  >
    <DailyCloseNav :company="company" history />

    <template #actions>
      <div class="flex items-center gap-2">
        <Button variant="outline" as-child>
          <Link :href="`${base}?month=${summary.prev_month}`">
            <ChevronLeft class="mr-1 h-4 w-4" />
            Previous
          </Link>
        </Button>
        <Button v-if="summary.next_month" variant="outline" as-child>
          <Link :href="`${base}?month=${summary.next_month}`">
            Next
            <ChevronRight class="ml-1 h-4 w-4" />
          </Link>
        </Button>
      </div>
    </template>

    <p v-if="summary.missing_dates.length" class="mb-4 text-sm text-status-attention">
      Not closed:
      <template v-for="(d, i) in summary.missing_dates" :key="d">
        <Link :href="`/${company.slug}/fuel/daily-close?date=${d}`" class="underline underline-offset-2">{{ shortDate(d) }}</Link><template v-if="i < summary.missing_dates.length - 1">, </template>
      </template>
    </p>

    <p v-if="summary.close_count === 0" class="py-8 text-center text-sm text-muted-foreground">No closes posted in {{ summary.label }}.</p>

    <div v-else class="space-y-6">
      <!-- Cash -->
      <section class="rounded-md border border-rule-default p-4">
        <h3 class="mb-3 font-semibold">Cash</h3>
        <dl class="grid gap-x-8 gap-y-1 text-sm tabular-nums sm:grid-cols-2 lg:grid-cols-3">
          <div class="flex justify-between"><dt><Link v-if="summary.cash.opening_close_id" :href="closeUrl(summary.cash.opening_close_id)" class="underline decoration-dotted underline-offset-2 hover:decoration-solid">Opening</Link><template v-else>Opening</template></dt><dd><MoneyText :amount="summary.cash.opening" :currency="currency" :fraction-digits="0" /></dd></div>
          <div class="flex justify-between"><dt>+ Money in</dt><dd><MoneyText :amount="summary.cash.money_in" :currency="currency" :fraction-digits="0" /></dd></div>
          <div class="flex justify-between"><dt>− Money out</dt><dd><MoneyText :amount="summary.cash.money_out" :currency="currency" :fraction-digits="0" /></dd></div>
          <div class="flex justify-between font-semibold">
            <dt>
              {{ Math.round(shortOver) === 0 ? 'Balanced' : shortOver < 0 ? 'Short' : 'Over' }}
              <span class="ml-1 text-xs font-normal">
                <button
                  v-if="summary.cash.short_days"
                  type="button"
                  class="underline decoration-dotted underline-offset-2 hover:decoration-solid"
                  :class="varianceFilter === 'short' ? 'text-foreground' : 'text-muted-foreground'"
                  :aria-expanded="varianceFilter === 'short'"
                  @click="toggleVariance('short')"
                >{{ summary.cash.short_days }} short</button>
                <template v-if="summary.cash.short_days && summary.cash.over_days"> · </template>
                <button
                  v-if="summary.cash.over_days"
                  type="button"
                  class="underline decoration-dotted underline-offset-2 hover:decoration-solid"
                  :class="varianceFilter === 'over' ? 'text-foreground' : 'text-muted-foreground'"
                  :aria-expanded="varianceFilter === 'over'"
                  @click="toggleVariance('over')"
                >{{ summary.cash.over_days }} over</button>
              </span>
            </dt>
            <dd :class="Math.round(shortOver) !== 0 ? 'text-status-attention' : ''"><MoneyText :amount="Math.abs(shortOver)" :currency="currency" :fraction-digits="0" /></dd>
          </div>
          <div class="flex justify-between font-medium"><dt><Link v-if="summary.cash.closing_close_id" :href="closeUrl(summary.cash.closing_close_id)" class="underline decoration-dotted underline-offset-2 hover:decoration-solid">= Closing (counted)</Link><template v-else>= Closing (counted)</template></dt><dd><MoneyText :amount="summary.cash.closing" :currency="currency" :fraction-digits="0" /></dd></div>
        </dl>
        <div v-if="varianceFilter && varianceDays.length" class="mt-3 border-t border-rule-default pt-3">
          <p class="mb-1 text-xs font-medium text-muted-foreground">{{ varianceFilter === 'short' ? 'Short days' : 'Over days' }}</p>
          <ul class="grid gap-x-8 gap-y-1 text-sm tabular-nums sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="d in varianceDays" :key="d.id" class="flex justify-between gap-3">
              <Link :href="`/${company.slug}/fuel/daily-close/${d.id}`" class="underline-offset-2 hover:underline">{{ shortDate(d.date) }}</Link>
              <span :class="d.amount < 0 ? 'text-status-attention' : ''">
                {{ d.amount < 0 ? '−' : '+' }}<MoneyText :amount="Math.abs(d.amount)" :currency="currency" :fraction-digits="0" />
              </span>
            </li>
            <li class="flex justify-between gap-3 border-t border-rule-default pt-1 font-medium sm:col-span-2 lg:col-span-3">
              <span>Total</span>
              <span>{{ varianceFilter === 'short' ? '−' : '+' }}<MoneyText :amount="Math.abs(varianceDays.reduce((t, d) => t + d.amount, 0))" :currency="currency" :fraction-digits="0" /></span>
            </li>
          </ul>
        </div>
      </section>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Fuel sales -->
        <section class="rounded-md border border-rule-default p-4">
          <h3 class="mb-3 font-semibold">Fuel sales</h3>
          <ul class="space-y-2 text-sm tabular-nums">
            <li v-for="(line, i) in summary.sales" :key="'s' + i">
              <div class="flex justify-between gap-3">
                <span>
                  <button v-if="line.sources?.length" type="button" class="text-left underline decoration-dotted underline-offset-2 hover:decoration-solid" :aria-expanded="openLine === 's' + i" @click="toggleLine('s' + i)">
                    <span class="font-medium">{{ line.label }}</span> <span v-if="line.detail" class="text-muted-foreground">{{ line.detail }}</span>
                  </button>
                  <template v-else><span class="font-medium">{{ line.label }}</span> <span v-if="line.detail" class="text-muted-foreground">{{ line.detail }}</span></template>
                  <Link v-if="line.item_id" :href="statementUrl(line.item_id)" class="ml-2 text-xs text-muted-foreground underline underline-offset-2">Statement</Link>
                </span>
                <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
              </div>
              <ul v-if="openLine === 's' + i" class="mt-1 space-y-0.5 border-l border-rule-default pl-3 text-xs">
                <li v-for="src in line.sources" :key="src.date" class="flex justify-between gap-3">
                  <Link :href="closeUrl(src.close_id)" class="underline-offset-2 hover:underline">{{ shortDate(src.date) }}</Link>
                  <span><span v-if="src.quantity" class="mr-2 text-muted-foreground">{{ litres(src.quantity) }}<template v-if="line.item_id"> L</template></span><MoneyText :amount="src.amount" :currency="currency" :fraction-digits="0" /></span>
                </li>
              </ul>
            </li>
            <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
              <span>Total sales</span>
              <MoneyText :amount="summary.sales_total" :currency="currency" :fraction-digits="0" />
            </li>
          </ul>
        </section>

        <!-- Tanks -->
        <section class="rounded-md border border-rule-default p-4">
          <h3 class="mb-3 font-semibold">Tanks</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm tabular-nums">
              <thead class="text-xs text-muted-foreground">
                <tr>
                  <th class="pb-1 text-left font-normal">Tank</th>
                  <th class="pb-1 text-right font-normal">Opening</th>
                  <th class="pb-1 text-right font-normal">+ Bought</th>
                  <th class="pb-1 text-right font-normal">− Sold</th>
                  <th class="pb-1 text-right font-normal">= Expected</th>
                  <th class="pb-1 text-right font-normal">Closing dip</th>
                  <th class="pb-1 text-right font-normal">Variance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in summary.tanks" :key="t.name" class="border-t border-rule-default">
                  <td class="py-1">
                    {{ t.name }}
                    <Link v-if="t.item_id" :href="statementUrl(t.item_id)" class="ml-1 text-xs text-muted-foreground underline underline-offset-2">Statement</Link>
                  </td>
                  <td class="py-1 text-right">{{ dash(t.opening) }}</td>
                  <td class="py-1 text-right">{{ dash(t.delivered) }}</td>
                  <td class="py-1 text-right">{{ dash(t.sold) }}</td>
                  <td class="py-1 text-right">{{ dash(t.expected) }}</td>
                  <td class="py-1 text-right">{{ dash(t.closing) }}</td>
                  <td class="py-1 text-right font-medium" :class="Math.abs(t.variance) >= 1 ? '' : 'text-muted-foreground'">
                    <Hint side="left">
                      {{ t.variance > 0 ? '+' : '' }}{{ litres(t.variance) }}
                      <template #content>Closing dip − expected. The daily dips posted {{ t.daily_variance > 0 ? '+' : '' }}{{ litres(t.daily_variance) }} L.</template>
                    </Hint>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-2 text-xs text-muted-foreground">Litres · variance + gain, − loss</p>
        </section>

        <!-- Money in -->
        <section class="rounded-md border border-rule-default p-4">
          <h3 class="mb-3 font-semibold">Money in</h3>
          <ul class="space-y-1.5 text-sm tabular-nums">
            <li v-for="(line, i) in summary.money_in" :key="'i' + i">
              <div class="flex justify-between gap-3">
                <button v-if="line.sources?.length" type="button" class="text-left underline decoration-dotted underline-offset-2 hover:decoration-solid" :aria-expanded="openLine === 'i' + i" @click="toggleLine('i' + i)">
                  <span class="font-medium">{{ line.label }}</span> <span v-if="detailOf(line)" class="text-muted-foreground">· {{ detailOf(line) }}</span>
                </button>
                <span v-else><span class="font-medium">{{ line.label }}</span> <span v-if="detailOf(line)" class="text-muted-foreground">· {{ detailOf(line) }}</span></span>
                <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
              </div>
              <ul v-if="openLine === 'i' + i" class="mt-1 space-y-0.5 border-l border-rule-default pl-3 text-xs">
                <li v-for="src in line.sources" :key="src.date" class="flex justify-between gap-3">
                  <Link :href="closeUrl(src.close_id)" class="underline-offset-2 hover:underline">{{ shortDate(src.date) }}</Link>
                  <MoneyText :amount="src.amount" :currency="currency" :fraction-digits="0" />
                </li>
              </ul>
            </li>
            <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
              <span>Total money in</span>
              <MoneyText :amount="summary.money_in_total" :currency="currency" :fraction-digits="0" />
            </li>
          </ul>
        </section>

        <!-- Money out -->
        <section class="rounded-md border border-rule-default p-4">
          <h3 class="mb-3 font-semibold">Money out</h3>
          <ul class="space-y-1.5 text-sm tabular-nums">
            <li v-for="(line, i) in summary.money_out" :key="'o' + i">
              <div class="flex justify-between gap-3">
                <button v-if="line.sources?.length" type="button" class="text-left underline decoration-dotted underline-offset-2 hover:decoration-solid" :aria-expanded="openLine === 'o' + i" @click="toggleLine('o' + i)">
                  <span class="font-medium">{{ line.label }}</span> <span v-if="detailOf(line)" class="text-muted-foreground">· {{ detailOf(line) }}</span>
                </button>
                <span v-else><span class="font-medium">{{ line.label }}</span> <span v-if="detailOf(line)" class="text-muted-foreground">· {{ detailOf(line) }}</span></span>
                <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
              </div>
              <ul v-if="openLine === 'o' + i" class="mt-1 space-y-0.5 border-l border-rule-default pl-3 text-xs">
                <li v-for="src in line.sources" :key="src.date" class="flex justify-between gap-3">
                  <Link :href="closeUrl(src.close_id)" class="underline-offset-2 hover:underline">{{ shortDate(src.date) }}</Link>
                  <MoneyText :amount="src.amount" :currency="currency" :fraction-digits="0" />
                </li>
              </ul>
            </li>
            <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
              <span>Total money out</span>
              <MoneyText :amount="summary.money_out_total" :currency="currency" :fraction-digits="0" />
            </li>
          </ul>
        </section>
      </div>
    </div>
  </PageShell>
</template>
