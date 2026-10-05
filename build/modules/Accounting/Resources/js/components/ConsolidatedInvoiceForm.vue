<script setup lang="ts">
/**
 * New consolidated invoice (its own page, consolidated-invoices/Create): pick which invoice lines go on it
 * (not every sale in the period is ready to bill), name it (Invoice, Reminder ...) and address it.
 * One standard layout -- date, coupon no., fuel, litres, rate, amount -- nothing to set up.
 * Saving keeps it exactly as sent (ConsolidatedInvoiceService) and opens it for print / PDF.
 */
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Save } from 'lucide-vue-next'

export interface InvoiceRow {
  key: string
  invoice_id: string
  invoice_number: string
  date: string
  // The books' date; date is the customer's slip date when the sale was logged on another day.
  booked_date?: string
  reference: string | null
  vehicle?: string | null
  paid: boolean
  balance: number
  sent_in: { number: string; date: string } | null
  item: string
  description: string
  quantity: number | null
  rate: number | null
  amount: number
}

export interface BillToDefaults { name: string; attention: string; phone: string; address: string }
export interface BilledByDefaults { name: string; designation: string; phone: string; address: string }

const props = defineProps<{
  rows: InvoiceRow[]
  billTo: BillToDefaults | null
  billedBy: BilledByDefaults | null
  currency: string
  from: string
  to: string
  companySlug: string
  customerId: string
  labels?: Record<string, string> | null
}>()

const form = useForm({
  customer_id: '',
  from: '',
  to: '',
  title: 'Invoice',
  keys: [] as string[],
  references: {} as Record<string, string>,
  // The station's own physical invoice / coupon number per line, typed here (optional column).
  physical: {} as Record<string, string>,
  // Per line, as the customer's copy should read: the date and the detail.
  dates: {} as Record<string, string>,
  items: {} as Record<string, string>,
  // Cells the invoice left blank (fuel, litres, rate), filled in here.
  fills: {} as Record<string, { item?: string; quantity?: number | null; rate?: number | null }>,
  // Column headings, renamable; they print as typed.
  headings: {} as Record<string, string>,
  bill_to: { name: '', attention: '', phone: '', address: '' },
  billed_by: { name: '', designation: '', phone: '', address: '' },
})
const picked = ref<Record<string, boolean>>({})
const vehicle = ref('all')

// Start from the customer and the company's signer, nothing ticked: the user picks what to bill.
watch(() => [props.rows, props.billTo, props.billedBy], () => {
  vehicle.value = 'all'
  picked.value = Object.fromEntries(props.rows.map((r) => [r.key, false]))
  form.references = Object.fromEntries(props.rows.map((r) => [r.key, r.reference ?? '']))
  form.physical = {}
  form.dates = Object.fromEntries(props.rows.map((r) => [r.key, r.date]))
  form.items = Object.fromEntries(props.rows.map((r) => [r.key, r.item ?? '']))
  form.fills = Object.fromEntries(props.rows.map((r) => [r.key, {}]))
  form.headings = { ...(props.labels ?? {}) }
  form.bill_to = { name: props.billTo?.name ?? '', attention: props.billTo?.attention ?? '', phone: props.billTo?.phone ?? '', address: props.billTo?.address ?? '' }
  form.billed_by = { name: props.billedBy?.name ?? '', designation: props.billedBy?.designation ?? '', phone: props.billedBy?.phone ?? '', address: props.billedBy?.address ?? '' }
}, { immediate: true })

// One vehicle's lines only (e.g. everything the generator took): choosing it lists and ticks
// just those lines; 'none' is the lines that name no vehicle.
const vehicles = computed(() => [...new Set(props.rows.map((r) => r.vehicle).filter((v): v is string => !!v))].sort())
const hasUnnamed = computed(() => props.rows.some((r) => !r.vehicle))
const shown = computed(() => (vehicle.value === 'all' ? props.rows : props.rows.filter((r) => (r.vehicle || 'none') === vehicle.value)))
const pickVehicle = (value: string) => {
  vehicle.value = value
  const keys = new Set(shown.value.map((r) => r.key))
  picked.value = Object.fromEntries(props.rows.map((r) => [r.key, value !== 'all' && keys.has(r.key)]))
}

const selected = computed(() => props.rows.filter((r) => picked.value[r.key]))
const total = computed(() => selected.value.reduce((sum, r) => sum + r.amount, 0))
const allPicked = computed(() => shown.value.length > 0 && shown.value.every((r) => picked.value[r.key]))
const pickAll = (on: boolean) => shown.value.forEach((r) => { picked.value[r.key] = on })

// Heading order on the paper; the numeric ones sit on the right.
const headingKeys = ['date', 'reference', 'item', 'quantity', 'rate', 'amount']
const numeric = ['quantity', 'rate', 'amount']

const number = (n: number | null) => (n === null ? '' : n.toLocaleString(undefined, { maximumFractionDigits: 2 }))

const save = () => {
  form.customer_id = props.customerId
  form.from = props.from
  form.to = props.to
  form.keys = selected.value.map((r) => r.key)
  form.post(`/${props.companySlug}/consolidated-invoices`)
}
</script>

<template>
  <div class="space-y-4">

      <div class="grid gap-3 md:grid-cols-3">
        <fieldset class="space-y-1.5">
          <legend class="mb-1 text-xs font-medium text-muted-foreground">Title</legend>
          <Input v-model="form.title" class="h-8" aria-label="Title" />
          <div class="flex gap-1">
            <Button size="sm" variant="ghost" class="h-7" @click="form.title = 'Invoice'">Invoice</Button>
            <Button size="sm" variant="ghost" class="h-7" @click="form.title = 'Reminder'">Reminder</Button>
          </div>
        </fieldset>
        <fieldset class="space-y-1.5">
          <legend class="mb-1 text-xs font-medium text-muted-foreground">Bill to</legend>
          <Input v-model="form.bill_to.name" class="h-8" placeholder="Name" aria-label="Bill to name" />
          <div class="flex gap-1.5">
            <Input v-model="form.bill_to.attention" class="h-8" placeholder="Person / office" aria-label="Bill to person or office" />
            <Input v-model="form.bill_to.phone" class="h-8 w-36" placeholder="Phone" aria-label="Bill to phone" />
          </div>
          <Input v-model="form.bill_to.address" class="h-8" placeholder="Address" aria-label="Bill to address" />
        </fieldset>
        <fieldset class="space-y-1.5">
          <legend class="mb-1 text-xs font-medium text-muted-foreground">Billed by</legend>
          <Input v-model="form.billed_by.name" class="h-8" placeholder="Name" aria-label="Billed by name" />
          <div class="flex gap-1.5">
            <Input v-model="form.billed_by.designation" class="h-8" placeholder="Designation" aria-label="Billed by designation" />
            <Input v-model="form.billed_by.phone" class="h-8 w-36" placeholder="Phone" aria-label="Billed by phone" />
          </div>
          <Input v-model="form.billed_by.address" class="h-8" placeholder="Address" aria-label="Billed by address" />
        </fieldset>
      </div>

      <div v-if="vehicles.length" class="flex items-center gap-2">
        <Label for="ci-vehicle" class="text-sm">Vehicle</Label>
        <Select :model-value="vehicle" @update:model-value="(v) => pickVehicle(String(v))">
          <SelectTrigger id="ci-vehicle" class="h-8 w-48"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All vehicles</SelectItem>
            <SelectItem v-for="v in vehicles" :key="v" :value="v">{{ v }}</SelectItem>
            <SelectItem v-if="hasUnnamed" value="none">No vehicle</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="overflow-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="sticky top-0 z-10 bg-background text-left text-xs text-muted-foreground">
            <tr class="border-b">
              <th class="w-10 px-3 py-2"><Checkbox :model-value="allPicked" aria-label="Pick all" @update:model-value="(v) => pickAll(v === true)" /></th>
              <th v-for="col in headingKeys" :key="col" class="px-1 py-1" :class="numeric.includes(col) ? 'text-right' : ''">
                <Input v-model="form.headings[col]" class="h-7 min-w-16 border-dashed text-xs font-medium" :class="numeric.includes(col) ? 'text-right' : ''" :aria-label="`Heading for ${col}`" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in shown" :key="row.key" class="border-b last:border-0" :class="picked[row.key] ? '' : 'text-muted-foreground'">
              <td class="px-3 py-1.5"><Checkbox v-model="picked[row.key]" :aria-label="`Include ${row.invoice_number}`" /></td>
              <td class="whitespace-nowrap px-2 py-1">
                <Input v-model="form.dates[row.key]" type="date" class="h-7 w-36 text-xs tabular-nums" :aria-label="`${form.headings.date} for ${row.invoice_number}`" />
                <div v-if="row.booked_date && form.dates[row.key] !== row.booked_date" class="text-xs text-status-attention">Booked {{ row.booked_date }}</div>
                <div class="text-xs text-muted-foreground">
                  {{ row.invoice_number }}
                  <span v-if="row.vehicle"> · {{ row.vehicle }}</span>
                  <span v-if="row.sent_in"> · sent in {{ row.sent_in.number }}</span>
                </div>
              </td>
              <td class="px-2 py-1"><Input v-model="form.references[row.key]" class="h-7 w-28 text-xs" :aria-label="`Reference for ${row.invoice_number}`" /></td>
              <td class="px-2 py-1">
                <Input v-model="form.items[row.key]" class="h-7 w-28 text-xs" :aria-label="`${form.headings.item} for ${row.invoice_number}`" />
              </td>
              <td class="px-2 py-1 text-right tabular-nums">
                <span v-if="row.quantity !== null">{{ number(row.quantity) }}</span>
                <Input v-else v-model.number="form.fills[row.key].quantity" type="number" min="0" step="0.01" class="ml-auto h-7 w-20 text-right text-xs" :aria-label="`${form.headings.quantity} for ${row.invoice_number}`" />
              </td>
              <td class="px-2 py-1 text-right tabular-nums">
                <span v-if="row.rate !== null">{{ number(row.rate) }}</span>
                <Input v-else v-model.number="form.fills[row.key].rate" type="number" min="0" step="0.01" class="ml-auto h-7 w-20 text-right text-xs" :aria-label="`Rate for ${row.invoice_number}`" />
              </td>
              <td class="px-3 py-1.5 text-right tabular-nums"><MoneyText :amount="row.amount" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="8" class="px-3 py-6 text-center text-muted-foreground">No unpaid invoices in this period.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <span class="text-sm">
          {{ selected.length }} line(s) · Total <MoneyText class="font-semibold" :amount="total" :currency="currency" :fraction-digits="0" />
          <span v-if="form.errors.keys" class="ml-2 text-destructive">{{ form.errors.keys }}</span>
        </span>
        <Button :disabled="!selected.length || form.processing" @click="save"><Save class="mr-2 h-4 w-4" />Save</Button>
      </div>
  </div>
</template>
