<script setup lang="ts">
/**
 * New consolidated invoice, from a customer's statement: pick which invoice lines go on it
 * (not every sale in the period is ready to bill), name it (Invoice, Reminder ...), address it,
 * add columns the customer asks for (vehicle no., driver, PO) and fill them in. Saving keeps it
 * exactly as sent -- see ConsolidatedInvoiceService -- and opens it for print / PDF.
 */
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Plus, Save, X } from 'lucide-vue-next'

export interface InvoiceRow {
  key: string
  invoice_id: string
  invoice_number: string
  date: string
  reference: string | null
  paid: boolean
  balance: number
  sent_in: { number: string; date: string } | null
  description: string
  quantity: number | null
  rate: number | null
  amount: number
}

export interface BillToDefaults { name: string; attention: string; phone: string }
export interface BilledByDefaults { name: string; designation: string; phone: string }

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
}>()

const form = useForm({
  customer_id: '',
  from: '',
  to: '',
  title: 'Invoice',
  keys: [] as string[],
  references: {} as Record<string, string>,
  columns: [] as Array<{ label: string; values: Record<string, string> }>,
  bill_to: { name: '', attention: '', phone: '' },
  billed_by: { name: '', designation: '', phone: '' },
})
const picked = ref<Record<string, boolean>>({})

// Start from the customer, the company's signer and the unpaid lines whenever the list changes.
watch(() => [props.rows, props.billTo, props.billedBy], () => {
  picked.value = Object.fromEntries(props.rows.map((r) => [r.key, !r.paid]))
  form.references = Object.fromEntries(props.rows.map((r) => [r.key, r.reference ?? '']))
  form.bill_to = { name: props.billTo?.name ?? '', attention: props.billTo?.attention ?? '', phone: props.billTo?.phone ?? '' }
  form.billed_by = { name: props.billedBy?.name ?? '', designation: props.billedBy?.designation ?? '', phone: props.billedBy?.phone ?? '' }
}, { immediate: true })

const selected = computed(() => props.rows.filter((r) => picked.value[r.key]))
const total = computed(() => selected.value.reduce((sum, r) => sum + r.amount, 0))
const allPicked = computed(() => props.rows.length > 0 && props.rows.every((r) => picked.value[r.key]))
const pickAll = (on: boolean) => props.rows.forEach((r) => { picked.value[r.key] = on })
const pickUnpaid = () => props.rows.forEach((r) => { picked.value[r.key] = !r.paid })

const addColumn = () => form.columns.push({ label: '', values: {} })
const removeColumn = (index: number) => form.columns.splice(index, 1)

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
    <DialogContent class="max-h-[92vh] overflow-hidden sm:max-w-6xl">
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
        </fieldset>
        <fieldset class="space-y-1.5">
          <legend class="mb-1 text-xs font-medium text-muted-foreground">Billed by</legend>
          <Input v-model="form.billed_by.name" class="h-8" placeholder="Name" aria-label="Billed by name" />
          <div class="flex gap-1.5">
            <Input v-model="form.billed_by.designation" class="h-8" placeholder="Designation" aria-label="Billed by designation" />
            <Input v-model="form.billed_by.phone" class="h-8 w-36" placeholder="Phone" aria-label="Billed by phone" />
          </div>
        </fieldset>
      </div>

      <div class="flex items-center gap-2">
        <Button size="sm" variant="ghost" @click="pickUnpaid">Unpaid only</Button>
        <Button size="sm" variant="ghost" :disabled="form.columns.length >= 6" @click="addColumn"><Plus class="mr-1 h-4 w-4" />Column</Button>
      </div>

      <div class="max-h-[42vh] overflow-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="sticky top-0 z-10 bg-background text-left text-xs text-muted-foreground">
            <tr class="border-b">
              <th class="w-10 px-3 py-2"><Checkbox :model-value="allPicked" aria-label="Pick all" @update:model-value="(v) => pickAll(v === true)" /></th>
              <th class="px-2 py-2">Date</th>
              <th class="px-2 py-2">Invoice</th>
              <th class="px-2 py-2">Reference</th>
              <th class="px-2 py-2">Description</th>
              <th class="px-2 py-2 text-right">Qty</th>
              <th class="px-2 py-2 text-right">Rate</th>
              <th class="px-2 py-2 text-right">Amount</th>
              <th v-for="(col, c) in form.columns" :key="c" class="min-w-32 px-2 py-1">
                <div class="flex items-center gap-1">
                  <Input v-model="col.label" class="h-7 text-xs" placeholder="Column name" />
                  <button type="button" class="text-muted-foreground hover:text-foreground" aria-label="Remove column" @click="removeColumn(c)"><X class="h-3.5 w-3.5" /></button>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.key" class="border-b last:border-0" :class="picked[row.key] ? '' : 'text-muted-foreground'">
              <td class="px-3 py-1.5"><Checkbox v-model="picked[row.key]" :aria-label="`Include ${row.invoice_number}`" /></td>
              <td class="whitespace-nowrap px-2 py-1.5 tabular-nums">{{ row.date }}</td>
              <td class="whitespace-nowrap px-2 py-1.5">
                {{ row.invoice_number }}
                <span v-if="row.paid" class="ml-1 text-xs text-status-success">paid</span>
                <div v-if="row.sent_in" class="text-xs text-muted-foreground">sent in {{ row.sent_in.number }} · {{ row.sent_in.date }}</div>
              </td>
              <td class="px-2 py-1"><Input v-model="form.references[row.key]" class="h-7 w-28 text-xs" /></td>
              <td class="px-2 py-1.5">{{ row.description }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums">{{ number(row.quantity) }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums">{{ number(row.rate) }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums"><MoneyText :amount="row.amount" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
              <td v-for="(col, c) in form.columns" :key="c" class="px-2 py-1"><Input v-model="col.values[row.key]" class="h-7 text-xs" /></td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="8 + form.columns.length" class="px-3 py-6 text-center text-muted-foreground">No invoices in this period.</td>
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
