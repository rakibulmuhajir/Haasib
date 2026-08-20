<script setup lang="ts">
/**
 * A payment is a receipt, and a receipt is a document.
 *
 * This page used to build one by hand: a logo block, a heading, a coloured
 * badge, a grid of labelled paragraphs, and a list of allocations as bordered
 * rows. It was the fourth different way this application drew the same sheet.
 * It now goes through LedgerDocument like the invoice, the bill and the credit
 * note, so the letterhead, the party blocks, the figures and the total are all
 * decided in one place.
 *
 * The allocations are the line items. That is what they are: this much of the
 * money went against that invoice. Whatever is left over is a line too --
 * unapplied credit is a real position a customer can be in, and a receipt that
 * silently omits it does not add up to the amount printed at the bottom.
 */
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer, DocumentLine } from '@/components/LedgerDocument.vue'
import DefinitionList from '@/components/DefinitionList.vue'
import RelatedActions from '@/components/RelatedActions.vue'
import MoneyText from '@/components/MoneyText.vue'
import MetaChip from '@/components/MetaChip.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Edit, MoreHorizontal } from 'lucide-vue-next'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'

interface Invoice {
  id: string
  invoice_number: string
}

interface PaymentAllocation {
  id: string
  invoice_id: string
  invoice?: Invoice
  amount_allocated: number
}

interface Customer {
  id: string
  name: string
  email?: string
}

interface Payment {
  id: string
  payment_number: string
  customer: Customer
  amount: number
  currency: string
  payment_method: string
  reference_number?: string
  payment_date: string
  notes?: string
  payment_allocations: PaymentAllocation[]
  created_at: string
}

interface CompanyRef {
  id: string
  name: string
  slug: string
  /** Assembled server-side by CompanyLetterhead — see the invoice page. */
  letterhead: DocumentIssuer
}

const props = defineProps<{
  company: CompanyRef
  payment: Payment
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Payments', href: `/${props.company.slug}/payments` },
  { title: props.payment.payment_number },
])

/**
 * Written as 'cheque' by the form, stored as 'check' by the column's check
 * constraint. Both spellings arrive here.
 */
const paymentMethodLabels: Record<string, string> = {
  cash: 'Cash',
  bank_transfer: 'Bank transfer',
  card: 'Card',
  cheque: 'Cheque',
  check: 'Cheque',
}

const methodLabel = computed(
  () => paymentMethodLabels[props.payment.payment_method] ?? 'Other',
)

const formatDate = (dateString: string) => formatSharedDateTime(dateString, { mode: 'date' })

const issuer = computed(() => props.company.letterhead)

const receivedFrom = computed(() => ({
  name: props.payment.customer.name,
  email: props.payment.customer.email,
}))

const documentDates = computed(() =>
  [
    { label: 'Received', value: formatDate(props.payment.payment_date) },
    { label: 'Method', value: methodLabel.value },
    { label: 'Reference', value: props.payment.reference_number ?? null },
  ].filter((date): date is { label: string; value: string } => Boolean(date.value)),
)

const allocatedTotal = computed(() =>
  props.payment.payment_allocations.reduce(
    (sum, allocation) => sum + Number(allocation.amount_allocated),
    0,
  ),
)

const unapplied = computed(() => Number(props.payment.amount) - allocatedTotal.value)

/**
 * One line per invoice the money went against, then the remainder if the
 * customer paid more than they owed on those invoices. The lines add up to the
 * total or the receipt is wrong, so the remainder is never left off.
 */
const documentLines = computed<DocumentLine[]>(() => {
  const lines: DocumentLine[] = props.payment.payment_allocations.map((allocation) => ({
    description: allocation.invoice?.invoice_number
      ? `Applied to ${allocation.invoice.invoice_number}`
      : 'Applied to invoice',
    amount: allocation.amount_allocated,
  }))

  if (unapplied.value > 0.005) {
    lines.push({
      description: lines.length ? 'Unapplied credit' : 'Payment on account',
      detail: lines.length ? 'Held against future invoices' : undefined,
      amount: unapplied.value,
    })
  }

  return lines
})

const summaryItems = computed(() => [
  { term: 'Method', value: methodLabel.value },
  { term: 'Reference', value: props.payment.reference_number ?? null },
  { term: 'Recorded', value: formatDate(props.payment.created_at) },
])
</script>

<template>
  <Head :title="`Payment ${payment.payment_number}`" />

  <PageShell
    :title="`Payment ${payment.payment_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payments`)">
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
          <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${payment.id}/edit`)">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <LedgerDocument
          doc-type="Receipt"
          :doc-number="payment.payment_number"
          :issuer="issuer"
          :bill-to="receivedFrom"
          bill-to-label="Received from"
          :dates="documentDates"
          :lines="documentLines"
          grand-total-label="Amount received"
          :grand-total-amount="payment.amount"
          :currency="payment.currency"
          locale="en-PK"
          :show-quantity="false"
        />

        <Card v-if="payment.notes">
          <CardHeader>
            <CardTitle>Internal notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm" dir="auto">{{ payment.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>How it was paid</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <DefinitionList :items="summaryItems" />
          </CardContent>
        </Card>

        <!-- What the money did. A receipt whose allocations are hidden in a
             sidebar total is a receipt nobody can reconcile against. -->
        <Card>
          <CardHeader>
            <CardTitle>Where it went</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-for="allocation in payment.payment_allocations"
              :key="allocation.id"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <button
                type="button"
                class="text-left underline-offset-2 hover:underline focus-visible:underline focus-visible:outline-none"
                @click="router.get(`/${company.slug}/invoices/${allocation.invoice_id}`)"
              >
                {{ allocation.invoice?.invoice_number || 'Invoice' }}
              </button>
              <MoneyText
                :amount="allocation.amount_allocated"
                :currency="payment.currency"
                locale="en-PK"
              />
            </div>

            <div v-if="unapplied > 0.005" class="flex items-center justify-between gap-3 text-sm">
              <MetaChip>On account</MetaChip>
              <MoneyText :amount="unapplied" :currency="payment.currency" locale="en-PK" />
            </div>

            <p
              v-if="!payment.payment_allocations.length && unapplied <= 0.005"
              class="text-sm text-muted-foreground"
            >
              Not applied to any invoice yet.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>

    <RelatedActions screen="payment.show" :slug="company.slug" :subject="payment" />
  </PageShell>
</template>
