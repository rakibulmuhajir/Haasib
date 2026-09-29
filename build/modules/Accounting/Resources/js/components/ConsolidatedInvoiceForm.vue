<script setup lang="ts">
/**
 * New consolidated invoice, from a customer's statement: pick which invoice lines go on it
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
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Save } from 'lucide-vue-next'

export interface InvoiceRow {
  key: string
  invoice_id: string
  invoice_number: string
  date: string
  reference: string | null
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

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  rows: InvoiceRow[]
  billTo: BillToDefaults | null
  billedBy: BilledByDefaults | null
  currency: string
  from: string
  to: string
  companySlug: string
  customerId: string
  labels?: { item: string; quantity: string } | null
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
  // Cells the invoice left blank (fuel, litres, rate), filled in here.
  fills: {} as Record<string, { item?: string; quantity?: number | null; rate?: number | null }>,
  bill_to: { name: '', attention: '', phone: '', address: '' },
  billed_by: { name: '', designation: '', phone: '', address: '' },
})
const picked = ref<Record<string, boolean>>({})

// Start from the customer, the company's signer and every line (all unpaid) whenever the list changes.
watch(() => [props.rows, props.billTo, props.billedBy], () => {
  picked.value = Object.fromEntries(props.rows.map((r) => [r.key, true]))
  form.references = Object.fromEntries(props.rows.map((r) => [r.key, r.reference ?? '']))
  form.physical = {}
  form.fills = Object.fromEntries(props.rows.map((r) => [r.key, {}]))
  form.bill_to = { name: props.billTo?.name ?? '', attention: props.billTo?.attention ?? '', phone: props.billTo?.phone ?? '', address: props.billTo?.address ?? '' }
  form.billed_by = { name: props.billedBy?.name ?? '', designation: props.billedBy?.designation ?? '', phone: props.billedBy?.phone ?? '', address: props.billedBy?.address ?? '' }
}, { immediate: true })

const selected = computed(() => props.rows.filter((r) => picked.value[r.key]))
const total = computed(() => selected.value.reduce((sum, r) => sum + r.amount, 0))
const allPicked = computed(() => props.rows.length > 0 && props.rows.every((r) => picked.value[r.key]))
const pickAll = (on: boolean) => props.rows.forEach((r) => { picked.value[r.key] = on })

const number = (n: number | null) => (n === null ? '' : n.toLocaleString(undefined, { maximumFractionDigits: 2 }))

const save = () => {
  form.customer_id = props.customerId
  form.from = props.from
  form.to = props.to
  form.keys = selected.value.map((r) => r.key)
  form.post(`/${props.companySlug}/consolidated-invoices`, {
    onSuccess: () => { open.value = false },
  })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-h-[92vh] overflow-hidden sm:max-w-5xl">
      <DialogHeader>
        <DialogTitle>Consolidated invoice</DialogTitle>
      </DialogHeader>

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

      <div class="max-h-[42vh] overflow-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="sticky top-0 z-10 bg-background text-left text-xs text-muted-foreground">
            <tr class="border-b">
              <th class="w-10 px-3 py-2"><Checkbox :model-value="allPicked" aria-label="Pick all" @update:model-value="(v) => pickAll(v === true)" /></th>
              <th class="px-2 py-2">Date</th>
              <th class="px-2 py-2">Reference</th>
              <th class="px-2 py-2">Invoice no.</th>
              <th class="px-2 py-2">{{ labels?.item ?? 'Item' }}</th>
              <th class="px-2 py-2 text-right">{{ labels?.quantity ?? 'Qty' }}</th>
              <th class="px-2 py-2 text-right">Rate</th>
              <th class="px-3 py-2 text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.key" class="border-b last:border-0" :class="picked[row.key] ? '' : 'text-muted-foreground'">
              <td class="px-3 py-1.5"><Checkbox v-model="picked[row.key]" :aria-label="`Include ${row.invoice_number}`" /></td>
              <td class="whitespace-nowrap px-2 py-1.5">
                <span class="tabular-nums">{{ row.date }}</span>
                <div class="text-xs text-muted-foreground">
                  {{ row.invoice_number }}
                  <span v-if="row.sent_in"> · sent in {{ row.sent_in.number }}</span>
                </div>
              </td>
              <td class="px-2 py-1"><Input v-model="form.references[row.key]" class="h-7 w-28 text-xs" :aria-label="`Reference for ${row.invoice_number}`" /></td>
              <td class="px-2 py-1"><Input v-model="form.physical[row.key]" class="h-7 w-28 text-xs" placeholder="Optional" :aria-label="`Physical invoice number for ${row.invoice_number}`" /></td>
              <td class="px-2 py-1">
                <span v-if="row.item">{{ row.item }}</span>
                <Input v-else v-model="form.fills[row.key].item" class="h-7 w-24 text-xs" :aria-label="`${labels?.item ?? 'Item'} for ${row.invoice_number}`" />
              </td>
              <td class="px-2 py-1 text-right tabular-nums">
                <span v-if="row.quantity !== null">{{ number(row.quantity) }}</span>
                <Input v-else v-model.number="form.fills[row.key].quantity" type="number" min="0" step="0.01" class="ml-auto h-7 w-20 text-right text-xs" :aria-label="`${labels?.quantity ?? 'Qty'} for ${row.invoice_number}`" />
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

      <DialogFooter class="items-center gap-3 sm:justify-between">
        <span class="text-sm">
          {{ selected.length }} line(s) · Total <MoneyText class="font-semibold" :amount="total" :currency="currency" :fraction-digits="0" />
          <span v-if="form.errors.keys" class="ml-2 text-destructive">{{ form.errors.keys }}</span>
        </span>
        <Button :disabled="!selected.length || form.processing" @click="save"><Save class="mr-2 h-4 w-4" />Save</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
