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
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ScrollText } from 'lucide-vue-next'

interface Bill {
  id: string
  bill_number: string | null
  quantity: number
  direct?: number
  amount?: number
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
  close_id?: string | null
  product?: string
  unit?: string | null
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
  opening_rate: number | null
  opening_value: number | null
  available: number | null
  available_value: number | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { item: string; items?: string[]; start_date: string; end_date: string }
  includeOpeningDefault?: boolean
  item: { id: string; name: string }
  combined?: boolean
  has_tank?: boolean
  rows: Row[]
  totals: Totals
  products: Array<{ id: string; name: string; unit?: string | null; has_tank?: boolean }>
}>()

const itemId = ref(props.filters.item)
const picked = ref<string[]>(props.filters.items ?? [])
const picking = ref(false)
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const currency = computed(() => props.company.base_currency || 'PKR')
const fmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 })
// Off: opening stock sits on its own line and Available = opening + bought shows under the total.
// On: the opening counts as the first purchase, so Bought and Purchases start from it -- what a
// month's paperwork shows when last month's stock is carried in.
const includeOpening = ref(Boolean(props.includeOpeningDefault))
const openingValue = computed(() => (includeOpening.value ? props.totals.opening_value ?? 0 : 0))
const boughtTotal = computed(() => (includeOpening.value ? props.totals.available : props.totals.received))
const purchaseTotal = computed(() => (includeOpening.value ? props.totals.available_value ?? props.totals.purchase_amount : props.totals.purchase_amount))
const purchaseRateTotal = computed(() => {
  const litres = boughtTotal.value ?? 0
  return litres > 0 ? purchaseTotal.value / litres : null
})
const litres = (v: number | null | undefined) => (v === null || v === undefined ? '—' : fmt.format(v))
const signed = (v: number) => `${v > 0 ? '+' : ''}${fmt.format(v)}`
const rateText = (r: number[] | undefined) => (r && r.length ? r.map((x) => fmt.format(x)).join(' → ') : '—')
const shortDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
const lastClosed = computed(() => [...props.rows].reverse().find((r) => !r.missing && r.close_id))

const combined = computed(() => Boolean(props.combined))
// Items without a tank have no dip: the balance is a book balance and there is no variance.
const showVariance = computed(() => combined.value || props.has_tank !== false)
const unitLabel = computed(() => {
  if (combined.value) return 'units'
  if (props.has_tank !== false) return 'L'
  return props.products.find((p) => p.id === props.item.id)?.unit || 'units'
})
const rowUnit = (r: Row) => (combined.value ? r.unit || 'unit' : unitLabel.value)
const colCount = computed(() => 9 + (combined.value ? 1 : 0) - (showVariance.value ? 0 : 1))
const pickedLabel = computed(() => `${picked.value.length} product${picked.value.length === 1 ? '' : 's'}`)

const changeItem = (value: string) => {
  if (value === 'some') {
    itemId.value = 'some'
    picking.value = true
    return
  }
  itemId.value = value
  picked.value = []
  picking.value = false
  apply()
}
const togglePick = (id: string, on: boolean) => {
  picked.value = on ? [...new Set([...picked.value, id])] : picked.value.filter((p) => p !== id)
}
const showPicked = () => {
  picking.value = false
  apply()
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Statements', href: `/${props.company.slug}/reports/statements` },
  { title: 'Stock statement', href: `/${props.company.slug}/fuel/reports/stock-statement` },
])

const apply = () => {
  router.get(`/${props.company.slug}/fuel/reports/stock-statement`, {
    item: itemId.value === 'some' ? undefined : itemId.value,
    items: itemId.value === 'some' && picked.value.length ? picked.value.join(',') : undefined,
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
              <Select :model-value="itemId" @update:model-value="changeItem">
                <SelectTrigger class="w-48">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All products</SelectItem>
                  <SelectItem value="some">{{ itemId === 'some' && picked.length ? pickedLabel : 'Choose several…' }}</SelectItem>
                  <SelectItem v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                </SelectContent>
              </Select>
              <div v-if="picking" class="max-h-64 w-64 space-y-1 overflow-y-auto rounded-md border p-2">
                <label v-for="p in products" :key="p.id" class="flex items-center gap-2 rounded px-1 py-1 text-sm hover:bg-muted">
                  <Checkbox :model-value="picked.includes(p.id)" @update:model-value="(v) => togglePick(p.id, v === true)" />
                  <span class="truncate">{{ p.name }}</span>
                </label>
                <div class="sticky bottom-0 flex items-center justify-between gap-2 bg-background pt-2">
                  <span class="text-xs text-muted-foreground">{{ picked.length }} chosen</span>
                  <Button size="sm" :disabled="!picked.length" @click="showPicked">Show</Button>
                </div>
              </div>
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
            <div class="flex items-center gap-2 pb-2">
              <Checkbox id="include_opening" v-model="includeOpening" />
              <Label for="include_opening" class="text-sm font-normal">Include opening stock</Label>
            </div>
          </div>
        </CardContent>
      </Card>

      <p v-if="!products.length" class="py-8 text-center text-sm text-muted-foreground">No products.</p>

      <div v-else class="overflow-x-auto rounded-md border border-rule-default">
        <table class="w-full text-sm tabular-nums">
          <thead class="text-xs text-muted-foreground">
            <tr>
              <th class="px-3 py-2 text-left font-normal">Date</th>
              <th v-if="combined" class="px-3 py-2 text-left font-normal">Product</th>
              <th class="px-3 py-2 text-right font-normal">Bought (in)</th>
              <th class="px-3 py-2 text-right font-normal">Purchase amount</th>
              <th class="px-3 py-2 text-right font-normal">Purchases to date</th>
              <th class="px-3 py-2 text-right font-normal">Sold (out)</th>
              <th class="px-3 py-2 text-right font-normal">Sale amount</th>
              <th class="px-3 py-2 text-right font-normal">Sales to date</th>
              <th class="px-3 py-2 text-right font-normal">{{ showVariance ? 'Balance' : 'Balance (book)' }}</th>
              <th v-if="showVariance" class="px-3 py-2 text-right font-normal">Variance</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t border-rule-default text-muted-foreground">
              <td class="px-3 py-1.5">Opening</td>
              <td v-if="combined"></td>
              <template v-if="includeOpening">
                <td class="px-3 py-1.5 text-right text-foreground">{{ litres(totals.opening) }}</td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="totals.opening_value" side="left">
                    <MoneyText :amount="totals.opening_value" :currency="currency" :fraction-digits="0" />
                    <template #content>{{ litres(totals.opening) }} {{ unitLabel }} @ {{ fmt.format(totals.opening_rate ?? 0) }}</template>
                  </Hint>
                </td>
                <td class="px-3 py-1.5 text-right"><MoneyText v-if="totals.opening_value" :amount="totals.opening_value" :currency="currency" :fraction-digits="0" /></td>
                <td colspan="3"></td>
              </template>
              <td v-else colspan="6"></td>
              <td class="px-3 py-1.5 text-right">{{ litres(totals.opening) }}</td>
              <td v-if="showVariance"></td>
            </tr>
            <tr v-for="r in rows" :key="r.date" class="border-t border-rule-default">
              <template v-if="r.missing">
                <td class="px-3 py-1.5 text-muted-foreground">{{ shortDate(r.date) }}</td>
                <td class="px-3 py-1.5 text-right">{{ r.received ? litres(r.received) : '' }}</td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.purchase_amount" side="left">
                    <MoneyText :amount="r.purchase_amount" :currency="currency" :fraction-digits="0" />
                    <template #content>@ {{ fmt.format(r.purchase_rate ?? 0) }} / {{ rowUnit(r) }}</template>
                  </Hint>
                </td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="(r.purchase_running ?? 0) + openingValue" :currency="currency" :fraction-digits="0" /></td>
                <td colspan="5" class="px-3 py-1.5 text-muted-foreground">
                  No close
                  <Link :href="`/${company.slug}/fuel/daily-close?date=${r.date}`" class="ml-2 underline underline-offset-2">Close this day</Link>
                </td>
              </template>
              <template v-else>
                <td class="px-3 py-1.5">
                  <Link v-if="r.close_id" :href="`/${company.slug}/fuel/daily-close/${r.close_id}`" class="underline-offset-2 hover:underline">{{ shortDate(r.date) }}</Link>
                  <template v-else>{{ shortDate(r.date) }}</template>
                </td>
                <td v-if="combined" class="px-3 py-1.5">{{ r.product }}</td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.received_direct" side="left">
                    {{ litres(r.received) }}
                    <template #content>{{ litres(r.received_direct) }} {{ rowUnit(r) }} sold straight off the tanker.</template>
                  </Hint>
                  <template v-else>{{ r.received ? litres(r.received) : '' }}</template>
                </td>
                <!-- The rate and the bills behind the amount. -->
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.purchase_amount" side="left">
                    <MoneyText :amount="r.purchase_amount" :currency="currency" :fraction-digits="0" />
                    <template #content>
                      <p class="font-medium">@ {{ fmt.format(r.purchase_rate ?? 0) }} / {{ rowUnit(r) }}</p>
                      <div v-for="b in r.bills ?? []" :key="b.id" class="flex justify-between gap-4">
                        <Link :href="`/${company.slug}/bills/${b.id}`" class="underline underline-offset-2">{{ b.bill_number || 'Bill' }}</Link>
                        <span>{{ litres(b.quantity) }} {{ rowUnit(r) }} · <MoneyText :amount="b.amount ?? 0" :currency="currency" :fraction-digits="0" /></span>
                      </div>
                    </template>
                  </Hint>
                </td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="(r.purchase_running ?? 0) + openingValue" :currency="currency" :fraction-digits="0" /></td>
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.sold_direct" side="left">
                    {{ litres(r.sold) }}
                    <template #content>
                      <div class="flex justify-between gap-4"><span>Pumps</span><span>{{ litres(r.sold_pumps ?? 0) }} {{ rowUnit(r) }}</span></div>
                      <div class="flex justify-between gap-4"><span>Off the tanker</span><span>{{ litres(r.sold_direct) }} {{ rowUnit(r) }}</span></div>
                    </template>
                  </Hint>
                  <template v-else>{{ r.sold ? litres(r.sold) : '' }}</template>
                </td>
                <!-- The rate (both, on a day the rate changed) and the off-tanker invoices. -->
                <td class="px-3 py-1.5 text-right">
                  <Hint v-if="r.sale_amount" side="left">
                    <MoneyText :amount="r.sale_amount" :currency="currency" :fraction-digits="0" />
                    <template #content>
                      <p class="font-medium">@ {{ rateText(r.rates) }} / {{ rowUnit(r) }}</p>
                      <template v-if="r.direct_amount">
                        <div class="flex justify-between gap-4"><span>Pumps</span><MoneyText :amount="(r.sale_amount ?? 0) - r.direct_amount" :currency="currency" :fraction-digits="0" /></div>
                        <div v-for="inv in r.direct_invoices ?? []" :key="inv.id" class="flex justify-between gap-4">
                          <Link :href="`/${company.slug}/invoices/${inv.id}`" class="underline underline-offset-2">{{ inv.invoice_number }}</Link>
                          <MoneyText :amount="inv.amount" :currency="currency" :fraction-digits="0" />
                        </div>
                      </template>
                    </template>
                  </Hint>
                </td>
                <td class="px-3 py-1.5 text-right text-muted-foreground"><MoneyText :amount="r.sale_running ?? 0" :currency="currency" :fraction-digits="0" /></td>
                <td class="px-3 py-1.5 text-right">{{ litres(r.dip) }}</td>
                <td v-if="showVariance" class="px-3 py-1.5 text-right" :class="Math.abs(r.variance ?? 0) >= 1 ? 'text-status-attention' : 'text-muted-foreground'">
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
              <td :colspan="colCount" class="px-3 py-6 text-center text-muted-foreground">{{ combined || has_tank === false ? 'No activity.' : 'No closes.' }}</td>
            </tr>
            <tr class="border-t-2 border-rule-default font-semibold">
              <td class="px-3 py-2">Total</td>
              <td v-if="combined"></td>
              <td class="px-3 py-2 text-right">{{ litres(boughtTotal) }}</td>
              <td class="px-3 py-2 text-right">
                <Hint side="left">
                  <MoneyText :amount="purchaseTotal" :currency="currency" :fraction-digits="0" />
                  <template #content>Average @ {{ purchaseRateTotal === null ? '—' : fmt.format(purchaseRateTotal) }} / {{ unitLabel }}</template>
                </Hint>
              </td>
              <td></td>
              <td class="px-3 py-2 text-right">{{ litres(totals.sold) }}</td>
              <td class="px-3 py-2 text-right">
                <Hint side="left">
                  <MoneyText :amount="totals.sale_amount" :currency="currency" :fraction-digits="0" />
                  <template #content>Average @ {{ totals.rate === null ? '—' : fmt.format(totals.rate) }} / {{ unitLabel }}</template>
                </Hint>
              </td>
              <td></td>
              <td class="px-3 py-2 text-right">
                <Link v-if="lastClosed && !combined" :href="`/${company.slug}/fuel/daily-close/${lastClosed.close_id}`" class="underline-offset-2 hover:underline">{{ litres(totals.closing) }}</Link>
                <template v-else>{{ litres(totals.closing) }}</template>
              </td>
              <td v-if="showVariance" class="px-3 py-2 text-right" :class="Math.abs(totals.variance) >= 1 ? 'text-status-attention' : ''">{{ signed(totals.variance) }}</td>
            </tr>
            <!-- Opening + bought: what there was to sell. Shown when the opening is not already in the total. -->
            <tr v-if="!includeOpening && totals.available !== null" class="text-muted-foreground">
              <td class="px-3 py-1.5">
                <Hint>
                  Available
                  <template #content>Opening stock + bought.</template>
                </Hint>
              </td>
              <td v-if="combined"></td>
              <td class="px-3 py-1.5 text-right">{{ litres(totals.available) }}</td>
              <td class="px-3 py-1.5 text-right">
                <Hint v-if="totals.available_value" side="left">
                  <MoneyText :amount="totals.available_value" :currency="currency" :fraction-digits="0" />
                  <template #content>Average @ {{ fmt.format(totals.available_value / (totals.available || 1)) }} / {{ unitLabel }}</template>
                </Hint>
              </td>
              <td :colspan="showVariance ? 6 : 5"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="products.length" class="flex flex-wrap gap-x-4 text-xs text-muted-foreground">
        <span>{{ item.name }} · {{ combined ? 'units' : unitLabel === 'L' ? 'litres' : unitLabel }}</span>
        <Link :href="`/${company.slug}/fuel/reports/stock-variance?start_date=${filters.start_date}&end_date=${filters.end_date}`" class="underline underline-offset-2">Tank gains &amp; losses</Link>
      </p>
    </div>
  </PageShell>
</template>
