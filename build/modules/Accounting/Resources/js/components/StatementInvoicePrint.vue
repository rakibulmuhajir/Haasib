<script setup lang="ts">
/**
 * Print one document from a customer's statement: pick which invoices go on it (not every
 * sale in the period is ready to bill), name it (Invoice, Reminder ...), add any columns the
 * customer asks for -- vehicle no., driver, PO -- and fill them in on the spot. Nothing is
 * saved; the page prints and forgets. Each row is one invoice line: date, invoice, reference,
 * what, litres, rate, amount.
 */
import { computed, nextTick, ref, watch } from 'vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer, DocumentParty } from '@/components/LedgerDocument.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Download, Plus, Printer, X } from 'lucide-vue-next'

export interface InvoiceRow {
  key: string
  invoice_id: string
  invoice_number: string
  date: string
  reference: string | null
  paid: boolean
  balance: number
  description: string
  quantity: number | null
  rate: number | null
  amount: number
}

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  rows: InvoiceRow[]
  billTo: DocumentParty | null
  letterhead: DocumentIssuer | null
  currency: string
  from: string
  to: string
  companySlug: string
  customerId: string
}>()

const title = ref('Invoice')
const picked = ref<Record<string, boolean>>({})
const references = ref<Record<string, string>>({})
const customColumns = ref<Array<{ id: number; label: string; values: Record<string, string> }>>([])
let nextColumnId = 1

// Fresh picks whenever the list changes (another customer or period): unpaid ones ticked.
watch(() => props.rows, (rows) => {
  picked.value = Object.fromEntries(rows.map((r) => [r.key, !r.paid]))
  references.value = Object.fromEntries(rows.map((r) => [r.key, r.reference ?? '']))
}, { immediate: true })

const selected = computed(() => props.rows.filter((r) => picked.value[r.key]))
const total = computed(() => selected.value.reduce((sum, r) => sum + r.amount, 0))
const allPicked = computed(() => props.rows.length > 0 && props.rows.every((r) => picked.value[r.key]))
const pickAll = (on: boolean) => props.rows.forEach((r) => { picked.value[r.key] = on })
const pickUnpaid = () => props.rows.forEach((r) => { picked.value[r.key] = !r.paid })

const addColumn = () => customColumns.value.push({ id: nextColumnId++, label: '', values: {} })
const removeColumn = (id: number) => { customColumns.value = customColumns.value.filter((c) => c.id !== id) }

// On paper, a column nobody filled in is just noise.
const showReference = computed(() => selected.value.some((r) => references.value[r.key]?.trim()))
const showQuantity = computed(() => selected.value.some((r) => r.quantity !== null))
const printedColumns = computed(() => customColumns.value.filter((c) => c.label.trim() || selected.value.some((r) => c.values[r.key]?.trim())))

const number = (n: number | null) => (n === null ? '' : n.toLocaleString(undefined, { maximumFractionDigits: 2 }))
const today = new Date().toISOString().slice(0, 10)

// The same document as a PDF file from the server: a plain form post, so the browser saves
// the file (an Inertia visit expects a page back, not a download).
const downloadPdf = () => {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = `/${props.companySlug}/reports/statements/invoice-pdf`
  const field = (name: string, value: string) => {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }
  field('_token', document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '')
  field('payload', JSON.stringify({
    customer_id: props.customerId,
    from: props.from,
    to: props.to,
    title: title.value,
    keys: selected.value.map((r) => r.key),
    references: references.value,
    columns: customColumns.value.map((c) => ({ label: c.label, values: c.values })),
  }))
  document.body.appendChild(form)
  form.submit()
  form.remove()
}

const print = async () => {
  open.value = false
  await nextTick()
  // Let the dialog finish closing so it is not on the page being printed.
  await new Promise((resolve) => setTimeout(resolve, 200))
  document.body.classList.add('printing-statement-invoice')
  const done = () => {
    document.body.classList.remove('printing-statement-invoice')
    window.removeEventListener('afterprint', done)
  }
  window.addEventListener('afterprint', done)
  window.print()
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-h-[90vh] overflow-hidden sm:max-w-6xl">
      <DialogHeader>
        <DialogTitle>Print invoice</DialogTitle>
      </DialogHeader>

      <div class="flex flex-wrap items-center gap-2">
        <Input v-model="title" class="h-9 w-48" aria-label="Title" />
        <Button size="sm" variant="ghost" @click="title = 'Invoice'">Invoice</Button>
        <Button size="sm" variant="ghost" @click="title = 'Reminder'">Reminder</Button>
        <span class="mx-2 h-5 w-px bg-border" />
        <Button size="sm" variant="ghost" @click="pickUnpaid">Unpaid only</Button>
        <Button size="sm" variant="ghost" @click="addColumn"><Plus class="mr-1 h-4 w-4" />Column</Button>
      </div>

      <div class="max-h-[55vh] overflow-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-background text-left text-xs text-muted-foreground">
            <tr class="border-b">
              <th class="w-10 px-3 py-2"><Checkbox :model-value="allPicked" aria-label="Pick all" @update:model-value="(v) => pickAll(v === true)" /></th>
              <th class="px-2 py-2">Date</th>
              <th class="px-2 py-2">Invoice</th>
              <th class="px-2 py-2">Reference</th>
              <th class="px-2 py-2">Description</th>
              <th class="px-2 py-2 text-right">Qty</th>
              <th class="px-2 py-2 text-right">Rate</th>
              <th class="px-2 py-2 text-right">Amount</th>
              <th v-for="col in customColumns" :key="col.id" class="min-w-32 px-2 py-1">
                <div class="flex items-center gap-1">
                  <Input v-model="col.label" class="h-7 text-xs" placeholder="Column name" />
                  <button type="button" class="text-muted-foreground hover:text-foreground" aria-label="Remove column" @click="removeColumn(col.id)"><X class="h-3.5 w-3.5" /></button>
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
              </td>
              <td class="px-2 py-1"><Input v-model="references[row.key]" class="h-7 w-28 text-xs" /></td>
              <td class="px-2 py-1.5">{{ row.description }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums">{{ number(row.quantity) }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums">{{ number(row.rate) }}</td>
              <td class="px-2 py-1.5 text-right tabular-nums"><MoneyText :amount="row.amount" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
              <td v-for="col in customColumns" :key="col.id" class="px-2 py-1"><Input v-model="col.values[row.key]" class="h-7 text-xs" /></td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="8 + customColumns.length" class="px-3 py-6 text-center text-muted-foreground">No invoices in this period.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <DialogFooter class="items-center gap-3 sm:justify-between">
        <span class="text-sm">
          {{ selected.length }} line(s) · Total <MoneyText class="font-semibold" :amount="total" :currency="currency" :fraction-digits="0" />
        </span>
        <div class="flex gap-2">
          <Button variant="outline" :disabled="!selected.length" @click="print"><Printer class="mr-2 h-4 w-4" />Print</Button>
          <Button :disabled="!selected.length" @click="downloadPdf"><Download class="mr-2 h-4 w-4" />Download PDF</Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- The printed document: only visible while printing (see the style below). -->
  <Teleport to="body">
    <div v-if="letterhead" class="statement-invoice-sheet">
      <LedgerDocument
        :doc-type="title || 'Invoice'"
        :issuer="letterhead"
        :bill-to="billTo ?? undefined"
        bill-to-label="Bill to"
        :dates="[{ label: 'Period', value: `${from} to ${to}` }, { label: 'Date', value: today }]"
        :lines="[]"
        grand-total-label="Total"
        :grand-total-amount="total"
        :currency="currency"
      >
        <template #lines>
          <table class="sheet-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Invoice</th>
                <th v-if="showReference">Reference</th>
                <th>Description</th>
                <th v-if="showQuantity" class="num">Qty</th>
                <th v-if="showQuantity" class="num">Rate</th>
                <th v-for="col in printedColumns" :key="col.id">{{ col.label }}</th>
                <th class="num">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in selected" :key="row.key">
                <td>{{ row.date }}</td>
                <td>{{ row.invoice_number }}</td>
                <td v-if="showReference">{{ references[row.key] }}</td>
                <td>{{ row.description }}</td>
                <td v-if="showQuantity" class="num">{{ number(row.quantity) }}</td>
                <td v-if="showQuantity" class="num">{{ number(row.rate) }}</td>
                <td v-for="col in printedColumns" :key="col.id">{{ col.values[row.key] }}</td>
                <td class="num"><MoneyText :amount="row.amount" :currency="currency" :show-currency="false" /></td>
              </tr>
            </tbody>
          </table>
        </template>
      </LedgerDocument>
    </div>
  </Teleport>
</template>

<style>
.statement-invoice-sheet { display: none; }
@media print {
  body.printing-statement-invoice > *:not(.statement-invoice-sheet) { display: none !important; }
  body.printing-statement-invoice .statement-invoice-sheet { display: block; }
}
.sheet-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.sheet-table th { text-align: left; font-weight: 600; border-bottom: 1px solid currentColor; padding: 4px 6px; }
.sheet-table td { padding: 3px 6px; border-bottom: 1px solid rgb(0 0 0 / 0.12); vertical-align: top; }
.sheet-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
</style>
