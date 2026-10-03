<script setup lang="ts">
/**
 * Fuel deliveries (FuelReceiptController@index): every bill line that went into a tank, newest
 * first, each linked to its bill and to the close of its date. Deliveries are entered in the
 * close that receives them, so "New delivery" opens the next close at its Purchases.
 */
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import InputError from '@/components/InputError.vue'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
  suppliers: Array<{ id: string; name: string }>
  products: Array<{ item_id: string; item_name: string; tank_id: string; tank_name: string }>
  paymentAccounts: Array<{ id: string; code: string; name: string }>
}>()

// Add a delivery on its own: it becomes a bill whose litres wait for that day's close, which shows
// them as delivered and receives them on posting.
const adding = ref(false)
const form = useForm({
  date: props.nextCloseDate,
  supplier_id: '',
  supplier_invoice_number: '',
  tank: '', // "item_id|tank_id"
  item_id: '',
  tank_id: '',
  quantity: null as number | null,
  direct_quantity: null as number | null,
  unit_cost: null as number | null,
  line_total: null as number | null,
  paid_now: false,
  payment_account_id: props.paymentAccounts[0]?.id ?? '',
})
const openAdd = () => {
  form.reset()
  form.clearErrors()
  form.date = props.nextCloseDate
  form.payment_account_id = props.paymentAccounts[0]?.id ?? ''
  adding.value = true
}
const total = computed(() => {
  if (form.line_total) return Number(form.line_total)
  return Number(form.quantity || 0) * Number(form.unit_cost || 0)
})
const save = () => {
  const [itemId, tankId] = form.tank.split('|')
  form.item_id = itemId ?? ''
  form.tank_id = tankId ?? ''
  form.post(`${base}/fuel/receipts`, {
    preserveScroll: true,
    onSuccess: () => { adding.value = false },
  })
}

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
      <Button @click="openAdd"><Plus class="mr-2 h-4 w-4" />Add delivery</Button>
    </template>

    <Dialog v-model:open="adding">
      <DialogContent class="sm:max-w-md">
        <DialogHeader><DialogTitle>Add delivery</DialogTitle></DialogHeader>
        <div class="grid gap-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
              <Label for="d_date">Date</Label>
              <Input id="d_date" v-model="form.date" type="date" />
              <InputError :message="form.errors.date" />
            </div>
            <div class="grid gap-1.5">
              <Label for="d_inv">Supplier invoice</Label>
              <Input id="d_inv" v-model="form.supplier_invoice_number" placeholder="Optional" />
            </div>
          </div>
          <div class="grid gap-1.5">
            <Label>Supplier</Label>
            <Select v-model="form.supplier_id">
              <SelectTrigger><SelectValue placeholder="Supplier" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.supplier_id" />
          </div>
          <div class="grid gap-1.5">
            <Label>Fuel · tank</Label>
            <Select v-model="form.tank">
              <SelectTrigger><SelectValue placeholder="Fuel and tank" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="p in products" :key="p.tank_id" :value="`${p.item_id}|${p.tank_id}`">{{ p.item_name }} · {{ p.tank_name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.item_id || form.errors.tank_id" />
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="grid gap-1.5">
              <Label for="d_qty">Litres</Label>
              <Input id="d_qty" v-model.number="form.quantity" type="number" min="0" />
              <InputError :message="form.errors.quantity" />
            </div>
            <div class="grid gap-1.5">
              <Label for="d_rate">Rate</Label>
              <Input id="d_rate" v-model.number="form.unit_cost" type="number" step="any" min="0" />
              <InputError :message="form.errors.unit_cost" />
            </div>
            <div class="grid gap-1.5">
              <Label for="d_total">Total</Label>
              <Input id="d_total" v-model.number="form.line_total" type="number" min="0" :placeholder="total ? String(Math.round(total)) : ''" />
            </div>
          </div>
          <div class="grid gap-1.5">
            <Label for="d_direct">
              <Hint>
                Sold off the tanker (L)
                <template #content>Litres sold straight to a customer from the tanker; they never go into the tank.</template>
              </Hint>
            </Label>
            <Input id="d_direct" v-model.number="form.direct_quantity" type="number" min="0" placeholder="0" />
            <InputError :message="form.errors.direct_quantity" />
          </div>
          <div class="flex items-center gap-2">
            <Checkbox id="d_paid" v-model="form.paid_now" />
            <Label for="d_paid" class="font-normal">Paid now</Label>
          </div>
          <div v-if="form.paid_now" class="grid gap-1.5">
            <Label>Paid from</Label>
            <Select v-model="form.payment_account_id">
              <SelectTrigger><SelectValue placeholder="Account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in paymentAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.payment_account_id" />
          </div>
          <p class="text-xs text-muted-foreground">
            <Hint>
              Shows on that day's close
              <template #content>Listed under the tank as delivered; received into the tank when that day's close is posted. Paid from cash, it counts in that day's cash too.</template>
            </Hint>
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="adding = false">Cancel</Button>
          <Button :disabled="form.processing" @click="save">Add</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <div class="space-y-4">
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
