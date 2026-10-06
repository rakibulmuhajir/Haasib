<script setup lang="ts">
/**
 * A consolidated invoice as it prints: letterhead, bill to, the lines (grouped under each
 * vehicle's heading, with its column names, when lines name vehicles), total, signer and stamp.
 * Used by the saved document (consolidated-invoices/Show) and by the editor's preview before saving,
 * so what is previewed is what prints.
 */
import { computed } from 'vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentStampData } from '@/components/DocumentStamp.vue'
import type { DocumentIssuer } from '@/components/LedgerDocument.vue'
import MoneyText from '@/components/MoneyText.vue'

export interface ConsolidatedLine {
  invoice_number?: string
  date?: string
  reference?: string
  physical_invoice?: string
  vehicle?: string | null
  is_subtotal?: boolean
  unit?: string | null
  item?: string
  description?: string
  quantity: number | null
  rate?: number | null
  amount: number
}

export interface ConsolidatedDocumentData {
  number: string
  date: string
  title: string
  bill_to: { name: string; attention?: string; phone?: string; address?: string; lines?: string[] }
  billed_by: { name?: string; designation?: string; phone?: string; address?: string }
  lines: ConsolidatedLine[]
  total: number
  currency: string
  show_reference?: boolean
  show_physical?: boolean
  show_vehicle?: boolean
  // The columns it prints before Amount, in order (ConsolidatedInvoiceService::printedColumns).
  columns?: Array<{ key: string; label: string; num: boolean }>
  group_by_vehicle?: boolean
  labels: Record<string, string>
  issuer: DocumentIssuer
  stamp?: DocumentStampData | null
}

// preview: the editor's preview, which shows the stamp on screen as it will print.
const props = defineProps<{ document: ConsolidatedDocumentData; preview?: boolean }>()

// Documents saved before columns could be left off carry no list: the full set, as they printed then.
const columns = computed(() => props.document.columns ?? [
  { key: 'date', label: props.document.labels.date, num: false },
  ...(props.document.show_reference ? [{ key: 'reference', label: props.document.labels.reference, num: false }] : []),
  ...(props.document.show_vehicle ? [{ key: 'vehicle', label: 'Vehicle', num: false }] : []),
  ...(props.document.show_physical ? [{ key: 'physical', label: props.document.labels.physical, num: false }] : []),
  { key: 'item', label: props.document.labels.item, num: false },
  { key: 'quantity', label: props.document.labels.quantity, num: true },
  { key: 'rate', label: props.document.labels.rate, num: true },
])
const groupByVehicle = computed(() => props.document.group_by_vehicle ?? !!props.document.show_vehicle)
const quantityAt = computed(() => columns.value.findIndex((c) => c.key === 'quantity'))
const number = (n: number | null | undefined) => (n === null || n === undefined ? '' : n.toLocaleString(undefined, { maximumFractionDigits: 2 }))
const cell = (line: any, key: string) => {
  switch (key) {
    case 'physical': return line.physical_invoice
    case 'quantity': return number(line.quantity)
    case 'rate': return number(line.rate)
    default: return line[key]
  }
}

const billTo = computed(() => ({
  name: props.document.bill_to.name,
  // Older documents kept the address as separate lines; newer ones as one line.
  lines: [props.document.bill_to.attention, props.document.bill_to.address, ...(props.document.bill_to.lines ?? [])].filter(Boolean) as string[],
  phone: props.document.bill_to.phone || undefined,
}))
const billedBy = computed(() => props.document.billed_by ?? {})
const hasBilledBy = computed(() => Object.values(billedBy.value).some((v) => (v ?? '').trim()))
// Stamp and signature sit over the Billed by line: one signing block, bottom right. They print
// only; the editor's preview shows them on screen as they will print.
const marks = computed(() => (props.document.stamp?.stampUrl || props.document.stamp?.signatureUrl ? props.document.stamp : null))
// With no Billed by set, the line names the signer from the stamp settings instead.
const signer = computed(() => (hasBilledBy.value ? null : props.document.stamp))
</script>

<template>
  <LedgerDocument
    :doc-type="document.title"
    :doc-number="document.number"
    :issuer="document.issuer"
    :stamp="null"
    :bill-to="billTo"
    bill-to-label="Bill to"
    :dates="[{ label: 'Date', value: document.date }]"
    :lines="[]"
    grand-total-label="Total"
    :grand-total-amount="document.total"
    :currency="document.currency"
    locale="en-PK"
  >
    <template #lines>
      <table class="ci-table">
        <thead v-if="!groupByVehicle">
          <tr>
            <th v-for="col in columns" :key="col.key" :class="{ num: col.num }">{{ col.label }}</th>
            <th class="num">{{ document.labels.amount }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(line, i) in document.lines" :key="i">
          <!-- Each vehicle's lines: its name as a heading, then the column names, as on the customer's own sheets. -->
          <template v-if="groupByVehicle && !line.is_subtotal && (i === 0 || document.lines[i - 1]?.is_subtotal)">
            <tr class="ci-group">
              <td :colspan="columns.length + 1">{{ line.vehicle || '—' }}</td>
            </tr>
            <tr class="ci-names">
              <th v-for="col in columns" :key="col.key" :class="{ num: col.num }">{{ col.label }}</th>
              <th class="num">{{ document.labels.amount }}</th>
            </tr>
          </template>
          <tr :class="{ 'font-semibold': line.is_subtotal }">
            <template v-if="line.is_subtotal">
              <!-- The label runs up to Litres (or across everything when Litres is left off). -->
              <td :colspan="Math.max(1, quantityAt < 0 ? columns.length : quantityAt)">{{ line.unit || '—' }} subtotal</td>
              <template v-if="quantityAt >= 0">
                <td class="num">{{ number(line.quantity) }}</td>
                <td v-for="n in columns.length - quantityAt - 1" :key="n"></td>
              </template>
              <td class="num"><MoneyText :amount="line.amount" :currency="document.currency" :show-currency="false" /></td>
            </template>
            <template v-else>
              <td v-for="col in columns" :key="col.key" :class="{ num: col.num }">{{ cell(line, col.key) }}</td>
              <td class="num"><MoneyText :amount="line.amount" :currency="document.currency" :show-currency="false" /></td>
            </template>
          </tr>
          </template>
        </tbody>
      </table>
    </template>
    <template v-if="hasBilledBy || marks" #footer>
      <div class="ci-sign">
        <div v-if="marks" class="ci-sign__marks" :class="{ 'ci-sign__marks--screen': preview }">
          <img v-if="marks.stampUrl" :src="marks.stampUrl" alt="Company stamp" class="ci-sign__stamp" />
          <img v-if="marks.signatureUrl" :src="marks.signatureUrl" alt="Signature" class="ci-sign__signature" />
        </div>
        <div class="ci-billed-by">
          <template v-if="hasBilledBy">
            <div class="ci-billed-by__label">Billed by</div>
            <div v-if="billedBy.name" class="ci-billed-by__name">{{ billedBy.name }}</div>
            <div v-if="billedBy.designation">{{ billedBy.designation }}</div>
            <div v-if="billedBy.phone">{{ billedBy.phone }}</div>
            <div v-if="billedBy.address">{{ billedBy.address }}</div>
          </template>
          <template v-else-if="signer">
            <div v-if="signer.signerName" class="ci-billed-by__name">{{ signer.signerName }}</div>
            <div v-if="signer.signerTitle">{{ signer.signerTitle }}</div>
          </template>
        </div>
      </div>
    </template>
  </LedgerDocument>
</template>

<style scoped>
.ci-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ci-table th { text-align: left; font-weight: 600; border-bottom: 1px solid currentColor; padding: 5px 6px; }
.ci-table td { padding: 4px 6px; border-bottom: 1px solid var(--color-rule-default, rgb(0 0 0 / 0.12)); vertical-align: top; }
.ci-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ci-sign { margin-top: 32px; margin-left: auto; width: 240px; display: flex; flex-direction: column; align-items: center; break-inside: avoid; text-align: center; }
.ci-sign__marks { display: none; flex-direction: column; align-items: center; gap: 6px; margin-bottom: 6px; }
.ci-sign__marks--screen { display: flex; }
.ci-sign__stamp { max-width: 120px; max-height: 120px; object-fit: contain; opacity: .85; }
.ci-sign__signature { max-width: 160px; max-height: 60px; object-fit: contain; }
@media print {
  .ci-sign__marks { display: flex; }
  .ci-sign__stamp, .ci-sign__signature { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
.ci-billed-by { width: 100%; border-top: 1px solid currentColor; padding-top: 4px; font-size: 12px; }
.ci-billed-by__label { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; opacity: .7; }
.ci-billed-by__name { font-weight: 600; }
.ci-group td {
  text-align: center;
  font-weight: 700;
  font-size: 15px;
  padding-top: 14px;
  border-bottom: none;
}
</style>
