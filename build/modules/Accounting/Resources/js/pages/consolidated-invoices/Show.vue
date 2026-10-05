<script setup lang="ts">
/**
 * A saved consolidated invoice: exactly what was sent, printed or downloaded again. It cannot be
 * changed -- a new one is made from the customer's statement. See ConsolidatedInvoiceService.
 */
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer } from '@/components/LedgerDocument.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Download, Printer } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Line {
  invoice_number: string
  date: string
  reference: string
  physical_invoice?: string
  vehicle?: string | null
  is_subtotal?: boolean
  unit?: string | null
  item?: string
  description: string
  quantity: number | null
  rate: number | null
  amount: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  document: {
    id: string
    number: string
    customer_id: string
    customer_name: string
    period_from: string
    period_to: string
    date: string
    created_by_name: string | null
    title: string
    bill_to: { name: string; attention?: string; phone?: string; address?: string; lines?: string[] }
    billed_by: { name?: string; designation?: string; phone?: string; address?: string }
    lines: Line[]
    total: number
    currency: string
    show_reference?: boolean
    show_physical?: boolean
    show_vehicle?: boolean
    labels: Record<string, string>
    file_name: string
    issuer: DocumentIssuer
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Consolidated invoices', href: `/${props.company.slug}/consolidated-invoices` },
  { title: props.document.number, href: `/${props.company.slug}/consolidated-invoices/${props.document.id}` },
]

const billTo = computed(() => ({
  name: props.document.bill_to.name,
  // Older documents kept the address as separate lines; newer ones as one line.
  lines: [props.document.bill_to.attention, props.document.bill_to.address, ...(props.document.bill_to.lines ?? [])].filter(Boolean) as string[],
  phone: props.document.bill_to.phone || undefined,
}))
const billedBy = computed(() => props.document.billed_by ?? {})
const hasBilledBy = computed(() => Object.values(billedBy.value).some((v) => (v ?? '').trim()))
const number = (n: number | null) => (n === null ? '' : n.toLocaleString(undefined, { maximumFractionDigits: 2 }))
const print = () => window.print()
</script>

<template>
  <!-- Print > Save as PDF suggests the page title as the file name. -->
  <Head :title="document.file_name" />

  <PageShell :title="`${document.title} ${document.number}`" :description="document.customer_name" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="print"><Printer class="mr-2 h-4 w-4" />Print</Button>
      <Button as-child>
        <a :href="`/${company.slug}/consolidated-invoices/${document.id}/pdf`"><Download class="mr-2 h-4 w-4" />Download PDF</a>
      </Button>
    </template>

    <p class="mb-4 text-sm text-muted-foreground print:hidden">
      Saved {{ document.date }}<span v-if="document.created_by_name"> by {{ document.created_by_name }}</span> ·
      <Link :href="`/${company.slug}/reports/statements?kind=customer&id=${document.customer_id}`" class="text-primary underline-offset-2 hover:underline">{{ document.customer_name }} statement</Link>
    </p>

    <LedgerDocument
      :doc-type="document.title"
      :doc-number="document.number"
      :issuer="document.issuer"
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
          <thead>
            <tr>
              <th>{{ document.labels.date }}</th>
              <th v-if="document.show_reference">{{ document.labels.reference }}</th>
              <th v-if="document.show_vehicle">Vehicle</th>
              <th v-if="document.show_physical">{{ document.labels.physical }}</th>
              <th>{{ document.labels.item }}</th>
              <th class="num">{{ document.labels.quantity }}</th>
              <th class="num">{{ document.labels.rate }}</th>
              <th class="num">{{ document.labels.amount }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, i) in document.lines" :key="i" :class="{ 'font-semibold': line.is_subtotal }">
              <template v-if="line.is_subtotal">
                <td :colspan="2 + (document.show_reference ? 1 : 0) + (document.show_vehicle ? 1 : 0) + (document.show_physical ? 1 : 0)">{{ line.unit || '—' }} subtotal</td>
                <td class="num">{{ number(line.quantity) }}</td>
                <td></td>
                <td class="num"><MoneyText :amount="line.amount" :currency="document.currency" :show-currency="false" /></td>
              </template>
              <template v-else>
              <td>{{ line.date }}</td>
              <td v-if="document.show_reference">{{ line.reference }}</td>
              <td v-if="document.show_vehicle">{{ line.vehicle }}</td>
              <td v-if="document.show_physical">{{ line.physical_invoice }}</td>
              <td>{{ line.item }}</td>
              <td class="num">{{ number(line.quantity) }}</td>
              <td class="num">{{ number(line.rate) }}</td>
              <td class="num"><MoneyText :amount="line.amount" :currency="document.currency" :show-currency="false" /></td>
              </template>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-if="hasBilledBy" #footer>
        <div class="ci-billed-by">
          <div class="ci-billed-by__label">Billed by</div>
          <div v-if="billedBy.name" class="ci-billed-by__name">{{ billedBy.name }}</div>
          <div v-if="billedBy.designation">{{ billedBy.designation }}</div>
          <div v-if="billedBy.phone">{{ billedBy.phone }}</div>
          <div v-if="billedBy.address">{{ billedBy.address }}</div>
        </div>
      </template>
    </LedgerDocument>
  </PageShell>
</template>

<style scoped>
.ci-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ci-table th { text-align: left; font-weight: 600; border-bottom: 1px solid currentColor; padding: 5px 6px; }
.ci-table td { padding: 4px 6px; border-bottom: 1px solid var(--color-rule-default, rgb(0 0 0 / 0.12)); vertical-align: top; }
.ci-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ci-billed-by { margin-top: 40px; width: 240px; border-top: 1px solid currentColor; padding-top: 4px; font-size: 12px; }
.ci-billed-by__label { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; opacity: .7; }
.ci-billed-by__name { font-weight: 600; }
</style>
