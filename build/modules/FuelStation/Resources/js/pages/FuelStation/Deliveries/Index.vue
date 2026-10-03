<script setup lang="ts">
/**
 * Fuel deliveries (FuelReceiptController@index): every bill line that went into a tank, newest
 * first, each linked to its bill and to the close of its date. Deliveries are entered in the
 * close that receives them, so "New delivery" opens the next close at its Purchases.
 */
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { Droplets, Plus } from 'lucide-vue-next'

interface Row {
  bill_id: string
  bill_number: string
  date: string
  supplier: string | null
  fuel: string | null
  tank: string
  litres: number
  into_tank: number
  direct: number
  received: boolean
  rate: number
  amount: number
  bill_status: string
  bill_balance: number
  close_id: string | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { start_date: string; end_date: string }
  rows: Row[]
  totals: { litres: number; into_tank: number; direct: number; amount: number; owed: number }
  nextCloseDate: string
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const base = `/${props.company.slug}`
const apply = () => router.get(`${base}/fuel/receipts`, { start_date: startDate.value, end_date: endDate.value }, { preserveState: true, preserveScroll: true })

const litres = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)
const rate = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)
const shortDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: base },
  { title: 'Fuel deliveries', href: `${base}/fuel/receipts` },
]
</script>

<template>
  <Head title="Fuel deliveries" />

  <PageShell title="Fuel deliveries" :icon="Droplets" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button as-child>
        <Link :href="`${base}/fuel/daily-close?date=${nextCloseDate}#purchases`"><Plus class="mr-2 h-4 w-4" />New delivery</Link>
      </Button>
    </template>

    <div class="space-y-4">
      <Card>
        <CardContent class="pt-6">
          <div class="flex flex-wrap items-end gap-3">
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

      <div class="overflow-x-auto rounded-md border border-rule-default">
        <table class="w-full text-sm tabular-nums">
          <thead class="text-xs text-muted-foreground">
            <tr>
              <th class="px-3 py-2 text-left font-normal">Date</th>
              <th class="px-3 py-2 text-left font-normal">Supplier · bill</th>
              <th class="px-3 py-2 text-left font-normal">Fuel · tank</th>
              <th class="px-3 py-2 text-right font-normal">Litres</th>
              <th class="px-3 py-2 text-right font-normal">Amount</th>
              <th class="px-3 py-2 text-left font-normal">Received</th>
              <th class="px-3 py-2 text-left font-normal">Paid</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.bill_id + r.tank + r.litres" class="border-t border-rule-default">
              <td class="px-3 py-1.5 whitespace-nowrap">
                <Link v-if="r.close_id" :href="`${base}/fuel/daily-close/${r.close_id}`" class="underline-offset-2 hover:underline">{{ shortDate(r.date) }}</Link>
                <template v-else>{{ shortDate(r.date) }}</template>
              </td>
              <td class="px-3 py-1.5">
                {{ r.supplier ?? 'Supplier' }} ·
                <Link :href="`${base}/bills/${r.bill_id}`" class="underline-offset-2 hover:underline">{{ r.bill_number }}</Link>
              </td>
              <td class="px-3 py-1.5">{{ r.fuel ?? 'Fuel' }} <span class="text-muted-foreground">· {{ r.tank }}</span></td>
              <td class="px-3 py-1.5 text-right">
                <Hint v-if="r.direct > 0" side="left">
                  {{ litres(r.litres) }}
                  <template #content>{{ litres(r.into_tank) }} L into {{ r.tank }} · {{ litres(r.direct) }} L sold straight off the tanker.</template>
                </Hint>
                <template v-else>{{ litres(r.litres) }}</template>
              </td>
              <td class="px-3 py-1.5 text-right">
                <Hint side="left">
                  <MoneyText :amount="r.amount" :currency="company.base_currency" :fraction-digits="0" />
                  <template #content>{{ litres(r.litres) }} L @ {{ rate(r.rate) }}</template>
                </Hint>
              </td>
              <td class="px-3 py-1.5">
                <span v-if="r.into_tank <= 0" class="text-muted-foreground">Off tanker</span>
                <span v-else-if="r.received">Received</span>
                <span v-else class="text-status-attention">Not yet</span>
              </td>
              <td class="px-3 py-1.5">
                <span v-if="r.bill_status === 'paid' || r.bill_balance <= 0.005">Paid</span>
                <Hint v-else side="left">
                  <span class="text-status-attention">Owed</span>
                  <template #content>Bill balance <MoneyText :amount="r.bill_balance" :currency="company.base_currency" :fraction-digits="0" /></template>
                </Hint>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="7" class="px-3 py-6 text-center text-muted-foreground">No deliveries in this period.</td>
            </tr>
            <tr v-else class="border-t-2 border-rule-default font-semibold">
              <td class="px-3 py-2" colspan="3">Total · {{ rows.length }} deliveries</td>
              <td class="px-3 py-2 text-right">
                <Hint side="left">
                  {{ litres(totals.litres) }}
                  <template #content>{{ litres(totals.into_tank) }} L into tanks · {{ litres(totals.direct) }} L off the tanker</template>
                </Hint>
              </td>
              <td class="px-3 py-2 text-right"><MoneyText :amount="totals.amount" :currency="company.base_currency" :fraction-digits="0" /></td>
              <td></td>
              <td class="px-3 py-2">
                <Link v-if="totals.owed > 0.005" :href="`${base}/reports/payables-aging`" class="text-status-attention underline-offset-2 hover:underline">
                  <MoneyText :amount="totals.owed" :currency="company.base_currency" :fraction-digits="0" /> owed
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PageShell>
</template>
