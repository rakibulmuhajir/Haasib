<script setup lang="ts">
/**
 * One product's litres over a date range, read like a bank statement: opening balance, one line
 * per posted Daily Close (bought in, sold out, rate, sale amount, balance = the dip), totals.
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ScrollText } from 'lucide-vue-next'

interface Bill {
  id: string
  bill_number: string | null
  quantity: number
  direct?: number
}

interface DirectInvoice {
  id: string
  invoice_number: string
  quantity: number
  amount: number
}

interface Row {
  date: string
  missing?: boolean
  close_id?: string
  transaction_number?: string
  opening?: number | null
  received?: number | null
  sold?: number
  received_direct?: number
  sold_pumps?: number
  sold_direct?: number
  direct_amount?: number
  direct_invoices?: DirectInvoice[]
  rates?: number[]
  sale_amount?: number
  sale_running?: number
  purchase_amount?: number
  purchase_rate?: number | null
  purchase_running?: number
  expected?: number
  dip?: number
  variance?: number
  bills?: Bill[]
}

interface Totals {
  opening: number | null
  received: number | null
  purchase_amount: number
  purchase_rate: number | null
  sold: number
  sale_amount: number
  rate: number | null
  closing: number | null
  variance: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { item: string; start_date: string; end_date: string }
  item: { id: string; name: string }
  rows: Row[]
  totals: Totals
  products: Array<{ id: string; name: string }>
}>()

const itemId = ref(props.filters.item)
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const currency = computed(() => props.company.base_currency || 'PKR')
const fmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 })
const litres = (v: number | null | undefined) => (v === null || v === undefined ? '—' : fmt.format(v))
const signed = (v: number) => `${v > 0 ? '+' : ''}${fmt.format(v)}`
const rateText = (r: number[] | undefined) => (r && r.length ? r.map((x) => fmt.format(x)).join(' → ') : '—')
const shortDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
const lastClosed = computed(() => [...props.rows].reverse().find((r) => !r.missing))

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Stock statement', href: `/${props.company.slug}/fuel/reports/stock-statement` },
])

const apply = () => {
  router.get(`/${props.company.slug}/fuel/reports/stock-statement`, {
    item: itemId.value,
    start_date: startDate.value,
    end_date: endDate.value,
  }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <Head title="Stock statement" />

  <PageShell title="Stock statement" :icon="ScrollText" :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <Card>
        <CardContent class="pt-6">
          <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
              <Label>Product</Label>
              <Select v-model="itemId">
                <SelectTrigger class="w-48">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-1.5">
              <Label for="start_date">From</Label>
              <Input id="start_date" v-model="startDate" type="date" class="w-40" />
            </div>
            <div class="grid gap-1.5">
              <Label for="end_date">To</Label>
              <Input id="end_date" v-model="endDate" type="date" class="w-40" />
            </div>
            <Button @click="apply">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <p v-if="!products.length" class="py-8 text-center text-sm text-muted-foreground">No tank products.</p>

      <div v-else class="overflow-x-auto rounded-md border border-rule-default">
        <table class="w-full text-sm tabular-nums">
          <thead class="text-xs text-muted-foreground">
            <tr>
              <th class="px-3 py-2 text-left font-normal">Date</th>
              <th class="px-3 py-2 text-right font-normal">Bought (in)</th>
              <th class="px-3 py-2 text-right font-normal">Purchase rate</th>
              <th class="px-3 py-2 text-right font-normal">Purchases to date</th>
              <th class="px-3 py-2 text-right font-normal">Sold (out)</th>
              <th class="px-3 py-2 text-right font-normal">Sale rate</th>
              <th class="px-3 py-2 text-right font-normal">Sale amount</th>
              <th class="px-3 py-2 text-right font-normal">Sales to date</th>
              <th class="px-3 py-2 text-right font-normal">Balance</th>
              <th class="px-3 py-2 text-right font-normal">Variance</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t border-rule-default text-muted-foreground">
              <td class="px-3 py-1.5">Opening</td>
              <td colspan="7"></td>
              <td class="px-3 py-1.5 text-right">{{ litres(totals.opening) }}</td>
              <td></td>
            </tr>
            <tr v-for="r in rows" :key="r.date" class="border-t border-rule-default">
              <template v-if="r.missing">
                <td class="px-3 py-1.5 text-muted-foreground">{{ shortDate(r.date) }}</td>
                <td class="px-3 py-1.5 text-right">{{ r.received ? litres(r.received) : '' }}</td>
                <td class="px-3 py-1.5 text-right">{{ r.purchase_rate ? fmt.format(r.purchase_rate) : '' }}</td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="r.purchase_running ?? 0" :currency="currency" :fraction-digits="0" /></td>
                <td colspan="6" class="px-3 py-1.5 text-muted-foreground">
                  No close
                  <Link :href="`/${company.slug}/fuel/daily-close?date=${r.date}`" class="ml-2 underline underline-offset-2">Close this day</Link>
                </td>
              </template>
              <template v-else>
                <td class="px-3 py-1.5">
                  <Link :href="`/${company.slug}/fuel/daily-close/${r.close_id}`" class="underline-offset-2 hover:underline">{{ shortDate(r.date) }}</Link>
                </td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.bills && r.bills.length" side="left">
                    {{ litres(r.received) }}
                    <template #content>
                      <div v-for="b in r.bills" :key="b.id" class="flex justify-between gap-4">
                        <Link :href="`/${company.slug}/bills/${b.id}`" class="underline underline-offset-2">{{ b.bill_number || 'Bill' }}</Link>
                        <span>{{ litres(b.quantity) }} L<template v-if="b.direct"> · {{ litres(b.direct) }} sold off tanker</template></span>
                      </div>
                    </template>
                  </Hint>
                  <template v-else>{{ litres(r.received) }}</template>
                </td>
                <td class="px-3 py-1.5 text-right">{{ r.purchase_rate ? fmt.format(r.purchase_rate) : '' }}</td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="r.purchase_running ?? 0" :currency="currency" :fraction-digits="0" /></td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.sold_direct" side="left">
                    {{ litres(r.sold) }}
                    <template #content>
                      <div class="flex justify-between gap-4"><span>Pumps</span><span>{{ litres(r.sold_pumps ?? 0) }} L</span></div>
                      <div class="flex justify-between gap-4"><span>Off the tanker</span><span>{{ litres(r.sold_direct) }} L</span></div>
                      <div v-for="inv in r.direct_invoices ?? []" :key="inv.id" class="flex justify-between gap-4 pl-2">
                        <Link :href="`/${company.slug}/invoices/${inv.id}`" class="underline underline-offset-2">{{ inv.invoice_number }}</Link>
                        <MoneyText :amount="inv.amount" :currency="currency" :fraction-digits="0" />
                      </div>
                    </template>
                  </Hint>
                  <template v-else>{{ litres(r.sold) }}</template>
                </td>
                <td class="px-3 py-1.5 text-right">{{ rateText(r.rates) }}</td>
                <td class="px-3 py-1.5 text-right"><MoneyText :amount="r.sale_amount ?? 0" :currency="currency" :fraction-digits="0" /></td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="r.sale_running ?? 0" :currency="currency" :fraction-digits="0" /></td>
                <td class="px-3 py-1.5 text-right">{{ litres(r.dip) }}</td>
                <td class="px-3 py-1.5 text-right" :class="Math.abs(r.variance ?? 0) >= 1 ? 'text-status-attention' : 'text-muted-foreground'">
                  <Hint side="left">
                    {{ signed(r.variance ?? 0) }}
                    <template #content>
                      Opening {{ litres(r.opening) }} + bought {{ litres(r.received) }} − sold {{ litres(r.sold) }} = expected {{ litres(r.expected) }}; dip {{ litres(r.dip) }}
                    </template>
                  </Hint>
                </td>
              </template>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="10" class="px-3 py-6 text-center text-muted-foreground">No closes.</td>
            </tr>
            <tr class="border-t-2 border-rule-default font-semibold">
              <td class="px-3 py-2">Total</td>
              <td class="px-3 py-2 text-right">{{ litres(totals.received) }}</td>
              <td class="px-3 py-2 text-right">{{ totals.purchase_rate === null ? '—' : fmt.format(totals.purchase_rate) }}</td>
              <td class="px-3 py-2 text-right"><MoneyText :amount="totals.purchase_amount" :currency="currency" :fraction-digits="0" /></td>
              <td class="px-3 py-2 text-right">{{ litres(totals.sold) }}</td>
              <td class="px-3 py-2 text-right">{{ totals.rate === null ? '—' : fmt.format(totals.rate) }}</td>
              <td class="px-3 py-2 text-right"><MoneyText :amount="totals.sale_amount" :currency="currency" :fraction-digits="0" /></td>
              <td></td>
              <td class="px-3 py-2 text-right">
                <Link v-if="lastClosed" :href="`/${company.slug}/fuel/daily-close/${lastClosed.close_id}`" class="underline-offset-2 hover:underline">{{ litres(totals.closing) }}</Link>
                <template v-else>{{ litres(totals.closing) }}</template>
              </td>
              <td class="px-3 py-2 text-right" :class="Math.abs(totals.variance) >= 1 ? 'text-status-attention' : ''">{{ signed(totals.variance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="products.length" class="text-xs text-muted-foreground">{{ item.name }} · litres</p>
    </div>
  </PageShell>
</template>
