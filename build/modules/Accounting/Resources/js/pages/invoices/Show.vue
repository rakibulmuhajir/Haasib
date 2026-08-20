<script setup lang="ts">
/**
 * An invoice, shown as an invoice.
 *
 * This page used to be three Cards — a header card, a hand-built line-item
 * grid, and a stack of TotalRows in a sidebar. It held the same facts, but it
 * did not look like the thing the customer receives, so what the sender saw and
 * what the recipient saw were two different artefacts.
 *
 * Now the page IS the document. LedgerDocument decides the letterhead, the logo
 * position, the party blocks, the line table and the reckoning; this file only
 * says which of the invoice's fields go where. Everything that is *about* the
 * document rather than *on* it — the actions, the history, the currency
 * conversion — sits beside it in the rail.
 */
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import RelatedActions from '@/components/RelatedActions.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer, DocumentLine, DocumentTotal } from '@/components/LedgerDocument.vue'
import MoneyText from '@/components/MoneyText.vue'
import MetaChip from '@/components/MetaChip.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DefinitionList from '@/components/DefinitionList.vue'
import Explain from '@/components/Explain.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import {
  ArrowLeft,
  Copy,
  DollarSign,
  Download,
  Edit,
  MoreHorizontal,
  Send,
  Trash2,
} from 'lucide-vue-next'

interface LineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  tax_rate?: number
  discount_amount?: number
  total: number
}

interface Customer {
  id: string
  name: string
  email?: string
  phone?: string
  billing_address?: Record<string, unknown>
  tax_number?: string
}

interface Invoice {
  id: string
  invoice_number: string
  customer: Customer
  status: string
  currency: string
  base_currency: string
  exchange_rate: number
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  paid_amount: number
  balance: number
  invoice_date: string
  due_date: string
  description?: string
  reference?: string
  payment_terms?: number
  notes?: string
  line_items: LineItem[]
  sent_at?: string
  viewed_at?: string
  paid_at?: string
  created_at: string
}

/**
 * `letterhead` is assembled server-side by CompanyLetterhead, so every document
 * in the application names its issuer from one place and one set of rules.
 */
interface CompanyRef {
  id: string
  name: string
  slug: string
  letterhead: DocumentIssuer
}

const props = defineProps<{
  company: CompanyRef
  invoice: Invoice
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
  { title: props.invoice.invoice_number },
])

const formatDate = (value?: string) => (value ? formatDateTime(value, { mode: 'date' }) : null)

const SETTLED = ['paid', 'cancelled', 'void', 'reversed']

const isSettled = computed(() => SETTLED.includes(props.invoice.status))

const isOverdue = computed(
  () =>
    !isSettled.value &&
    props.invoice.balance > 0 &&
    new Date(props.invoice.due_date) < new Date(),
)

const daysLate = computed(() => {
  if (!isOverdue.value) return 0
  const ms = Date.now() - new Date(props.invoice.due_date).getTime()
  return Math.floor(ms / 86_400_000)
})

/* The status the server stores and the status the reader needs are not always
   the same word. An invoice still marked `sent` six weeks past its due date is
   overdue to everyone except the database. */
const displayStatus = computed(() =>
  isOverdue.value && props.invoice.status === 'sent' ? 'overdue' : props.invoice.status,
)

/** Only worth showing when the invoice is not already in the company's money. */
const isForeign = computed(
  () => !!props.invoice.base_currency && props.invoice.currency !== props.invoice.base_currency,
)

const inBase = (amount: number) => amount * (props.invoice.exchange_rate || 1)

/**
 * An address arrives as a loose object whose shape varies by how it was
 * captured. Pull the parts a postal address is made of, in the order they are
 * written, and drop whatever is absent rather than printing an empty line.
 */
const ADDRESS_PARTS = ['line1', 'line2', 'street', 'city', 'state', 'postal_code', 'country']

const addressLines = (address?: Record<string, unknown> | null): string[] => {
  if (!address) return []
  return ADDRESS_PARTS.map((part) => address[part])
    .filter((value): value is string => typeof value === 'string' && value.trim().length > 0)
}

/* The issuer arrives assembled. It used to be built here from four company
   fields, three of which the controller never sent — so the letterhead was a
   name and nothing else on every invoice ever rendered. */
const issuer = computed(() => props.company.letterhead)

const billTo = computed(() => ({
  name: props.invoice.customer.name,
  lines: addressLines(props.invoice.customer.billing_address),
  email: props.invoice.customer.email,
  phone: props.invoice.customer.phone,
  taxId: props.invoice.customer.tax_number,
}))

const documentDates = computed(() =>
  [
    { label: 'Issued', value: formatDate(props.invoice.invoice_date) },
    { label: 'Due', value: formatDate(props.invoice.due_date) },
    {
      label: 'Terms',
      value: props.invoice.payment_terms ? `${props.invoice.payment_terms} days` : null,
    },
    { label: 'Reference', value: props.invoice.reference ?? null },
  ].filter((date): date is { label: string; value: string } => Boolean(date.value)),
)

const documentLines = computed<DocumentLine[]>(() =>
  props.invoice.line_items.map((item) => ({
    description: item.description,
    quantity: item.quantity,
    unitPrice: item.unit_price,
    amount: item.total,
  })),
)

/* Everything between the lines and the answer. Zero-value rows are left out —
   a discount of nothing is not a fact about this invoice. */
const documentTotals = computed<DocumentTotal[]>(() => {
  const totals: DocumentTotal[] = [{ label: 'Subtotal', amount: props.invoice.subtotal }]
  if (props.invoice.discount_amount > 0) {
    totals.push({ label: 'Discount', amount: props.invoice.discount_amount, sign: '−' })
  }
  if (props.invoice.tax_amount > 0) {
    totals.push({ label: 'Sales tax', amount: props.invoice.tax_amount, sign: '+' })
  }
  if (props.invoice.paid_amount > 0) {
    totals.push({ label: 'Paid', amount: props.invoice.paid_amount, sign: '−', muted: true })
  }
  return totals
})

/**
 * Stamped across the sheet when the document's standing is in question. A paid
 * invoice gets one too — it is the difference between a bill and a receipt, and
 * the reader should not have to work it out from the balance.
 */
const overprint = computed(() => {
  if (['void', 'cancelled', 'reversed'].includes(props.invoice.status)) return 'Void'
  if (props.invoice.status === 'draft') return 'Draft'
  if (props.invoice.status === 'paid' || props.invoice.balance === 0) return 'Paid'
  return null
})

const history = computed(() =>
  [
    { term: 'Created', value: formatDate(props.invoice.created_at) },
    { term: 'Sent', value: formatDate(props.invoice.sent_at) },
    { term: 'Opened by customer', value: formatDate(props.invoice.viewed_at) },
    { term: 'Paid', value: formatDate(props.invoice.paid_at) },
  ].filter((item) => item.value),
)

/* `window` is not in template scope, and the browser's own print dialog is
   also the "save as PDF" dialog — one control, both jobs. */
const printDocument = () => window.print()

const sendInvoice = () => router.post(`/${props.company.slug}/invoices/${props.invoice.id}/send`)
const duplicateInvoice = () =>
  router.post(`/${props.company.slug}/invoices/${props.invoice.id}/duplicate`)

/* Voiding was a native confirm(), which blocks the whole page, cannot be
   styled, cannot be keyboard-tabbed like the rest of the app, and says
   "127.0.0.1:8899 says" above the question. */
const confirmingVoid = ref(false)
const voiding = ref(false)

const voidInvoice = () => {
  voiding.value = true
  router.post(
    `/${props.company.slug}/invoices/${props.invoice.id}/void`,
    {},
    {
      onFinish: () => {
        voiding.value = false
        confirmingVoid.value = false
      },
    },
  )
}
</script>

<template>
  <Head :title="`Invoice ${invoice.invoice_number}`" />

  <PageShell :title="`Invoice ${invoice.invoice_number}`" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline">
            <MoreHorizontal class="mr-2 h-4 w-4" />
            More
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${invoice.id}/edit`)">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
          <DropdownMenuItem @click="duplicateInvoice">
            <Copy class="mr-2 h-4 w-4" />
            Duplicate
          </DropdownMenuItem>
          <DropdownMenuItem :disabled="isSettled" @click="confirmingVoid = true">
            <Trash2 class="mr-2 h-4 w-4" />
            Void
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Button :disabled="isSettled" @click="sendInvoice">
        <Send class="mr-2 h-4 w-4" />
        Mark as sent
      </Button>
    </template>

    <div class="page">
      <!-- The document itself, exactly as the customer will receive it. -->
      <LedgerDocument
        doc-type="Invoice"
        :doc-number="invoice.invoice_number"
        :issuer="issuer"
        :bill-to="billTo"
        :dates="documentDates"
        :lines="documentLines"
        :totals="documentTotals"
        grand-total-label="Invoice total"
        :grand-total-amount="invoice.total_amount"
        amount-due-label="Still owed"
        :amount-due-amount="invoice.balance"
        :currency="invoice.currency"
        locale="en-PK"
        :overprint="overprint"
      >
        <template v-if="invoice.notes" #terms>
          <p dir="auto">{{ invoice.notes }}</p>
        </template>
      </LedgerDocument>

      <!-- The rail: what is true *about* this invoice but does not belong on
           the sheet the customer receives. -->
      <aside class="rail">
        <Card variant="detail">
          <CardContent class="space-y-3 pt-6">
            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge :status="displayStatus" explain />
              <MetaChip v-if="isOverdue" tone="late">
                {{ daysLate }} {{ daysLate === 1 ? 'day' : 'days' }} late
              </MetaChip>
            </div>

            <p v-if="isForeign" class="text-sm text-text-metadata">
              In your <Explain term="baseCurrency" />:
              <MoneyText
                :amount="inBase(invoice.balance)"
                :currency="invoice.base_currency"
                locale="en-PK"
                tone="estimated"
              />
              at {{ invoice.exchange_rate }}
            </p>
          </CardContent>
        </Card>

        <Card variant="detail">
          <CardContent class="space-y-2 pt-6">
            <Button
              v-if="invoice.balance > 0 && !isSettled"
              class="w-full"
              @click="
                router.get(
                  `/${company.slug}/payments/create?customer_id=${invoice.customer.id}&invoice_id=${invoice.id}`,
                )
              "
            >
              <DollarSign class="mr-2 h-4 w-4" />
              Record a payment
            </Button>
            <Button class="w-full" variant="outline" :disabled="isSettled" @click="sendInvoice">
              <Send class="mr-2 h-4 w-4" />
              Send to customer
            </Button>
            <Button class="w-full" variant="outline" @click="printDocument">
              <Download class="mr-2 h-4 w-4" />
              Print or save as PDF
            </Button>
            <Button
              class="w-full"
              variant="outline"
              :disabled="invoice.status === 'paid'"
              @click="router.get(`/${company.slug}/invoices/${invoice.id}/edit`)"
            >
              <Edit class="mr-2 h-4 w-4" />
              Edit invoice
            </Button>
          </CardContent>
        </Card>

        <Card v-if="history.length" variant="detail">
          <CardHeader>
            <CardTitle>What happened when</CardTitle>
          </CardHeader>
          <CardContent>
            <DefinitionList :items="history" />
          </CardContent>
        </Card>

        <!-- Internal notes are for the company, so they stay off the sheet. -->
        <Card v-if="invoice.description" variant="detail">
          <CardHeader>
            <CardTitle>Internal note</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-text-secondary" dir="auto">{{ invoice.description }}</p>
          </CardContent>
        </Card>
      </aside>
    </div>

    <ConfirmDialog
      v-model:open="confirmingVoid"
      variant="destructive"
      title="Void this invoice?"
      :description="`Invoice ${invoice.invoice_number} stays on the record, struck through, and its amount stops counting toward what you are owed. Voiding cannot be undone — issue a credit note instead if the customer has already paid.`"
      confirm-text="Void invoice"
      cancel-text="Keep it"
      :loading="voiding"
      @confirm="voidInvoice"
    />

    <RelatedActions screen="invoice.show" :slug="company.slug" :subject="invoice" />
  </PageShell>
</template>

<style scoped>
.page {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 24px;
}

@media (min-width: 1100px) {
  .page {
    grid-template-columns: minmax(0, 1fr) 300px;
    align-items: start;
  }
}

.rail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Printing an invoice should produce the invoice, not the application around
   it. The document keeps its own print rules; this hides the rest of the page. */
@media print {
  .rail {
    display: none;
  }

  .page {
    display: block;
  }
}
</style>
