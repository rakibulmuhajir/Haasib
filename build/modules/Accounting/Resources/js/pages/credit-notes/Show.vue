<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer, DocumentLine } from '@/components/LedgerDocument.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import {
  ArrowLeft,
  Edit,
  MoreHorizontal,
  Send,
} from 'lucide-vue-next'

interface Customer {
  id: string
  name: string
  email?: string
}

interface Invoice {
  id: string
  invoice_number: string
}

interface CreditNote {
  id: string
  credit_note_number: string
  customer: Customer
  invoice?: Invoice
  amount: number
  base_currency: string
  reason: string
  status: string
  credit_date: string
  notes?: string
  terms?: string
  sent_at?: string
  posted_at?: string
  voided_at?: string
  created_at: string
}

interface CompanyRef {
  id: string
  name: string
  slug: string
  letterhead: DocumentIssuer
}

const props = defineProps<{
  company: CompanyRef
  credit_note: CreditNote
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
  { title: props.credit_note.credit_note_number },
])

const formatDate = (dateString: string) => {
  return formatSharedDateTime(dateString, { mode: 'date' })
}

/**
 * A credit note is a document, and until now it was a Card with a big green
 * number in it. Green said "good news"; a credit note is a reduction, and the
 * grammar reserves colour for whether something needs attention, never for
 * which direction money moved. It goes through LedgerDocument for the same
 * reason invoices and bills do -- it is the same sheet with different words.
 *
 * There are no line items to show: a credit note carries one amount and the
 * reason for it, so the reason IS the line. Quantities and unit rates are
 * suppressed rather than padded with a quantity of one.
 */
const issuer = computed(() => props.company.letterhead)

const creditTo = computed(() => ({
  name: props.credit_note.customer.name,
  email: props.credit_note.customer.email,
}))

const documentDates = computed(() =>
  [
    { label: 'Issued', value: formatDate(props.credit_note.credit_date) },
    {
      label: 'Against',
      value: props.credit_note.invoice?.invoice_number ?? null,
    },
  ].filter((date): date is { label: string; value: string } => Boolean(date.value)),
)

const documentLines = computed<DocumentLine[]>(() => [
  {
    description: props.credit_note.reason || 'Credit',
    amount: props.credit_note.amount,
  },
])

const overprint = computed(() => {
  if (['void', 'cancelled', 'reversed'].includes(props.credit_note.status)) return 'Void'
  if (props.credit_note.status === 'draft') return 'Draft'
  return null
})

const isEditable = computed(() => {
  return ['draft', 'issued'].includes(props.credit_note.status)
})
</script>

<template>
  <Head :title="`Credit Note ${credit_note.credit_note_number}`" />

  <PageShell
    :title="`Credit Note ${credit_note.credit_note_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/credit-notes`)">
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
          <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}/edit`)" :disabled="!isEditable">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
          <DropdownMenuItem @click="router.post(`/${company.slug}/credit-notes/${credit_note.id}/send`)" v-if="credit_note.status === 'draft'">
            <Send class="mr-2 h-4 w-4" />
            Send
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Credit Note Content -->
      <div class="lg:col-span-2 space-y-6">
        <LedgerDocument
          doc-type="Credit Note"
          :doc-number="credit_note.credit_note_number"
          :issuer="issuer"
          :bill-to="creditTo"
          bill-to-label="Credit to"
          :dates="documentDates"
          :lines="documentLines"
          grand-total-label="Credit total"
          :grand-total-amount="credit_note.amount"
          :currency="credit_note.base_currency"
          locale="en-PK"
          :overprint="overprint"
          :show-quantity="false"
        >
          <template v-if="credit_note.terms" #terms>
            <p dir="auto">{{ credit_note.terms }}</p>
          </template>
        </LedgerDocument>

        <!-- Notes -->
        <Card v-if="credit_note.notes">
          <CardHeader>
            <CardTitle>Internal Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm">{{ credit_note.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Credit Note Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Credit Note Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between">
              <span>Credit amount</span>
              <MoneyText
                :amount="credit_note.amount"
                :currency="credit_note.base_currency"
                locale="en-PK"
              />
            </div>
            <div class="flex justify-between text-sm">
              <span>Status</span>
              <StatusBadge :status="credit_note.status" explain />
            </div>
            <div v-if="credit_note.invoice" class="flex justify-between text-sm">
              <span>Applied to:</span>
              <span>{{ credit_note.invoice.invoice_number }}</span>
            </div>
            <Separator />
            <div class="flex justify-between text-sm text-muted-foreground">
              <span>Credit Date:</span>
              <span>{{ formatDate(credit_note.credit_date) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Timeline -->
        <Card>
          <CardHeader>
            <CardTitle>Timeline</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between text-sm">
              <span>Created:</span>
              <span>{{ formatDate(credit_note.created_at) }}</span>
            </div>
            <div v-if="credit_note.sent_at" class="flex justify-between text-sm">
              <span>Sent:</span>
              <span>{{ formatDate(credit_note.sent_at) }}</span>
            </div>
            <div v-if="credit_note.posted_at" class="flex justify-between text-sm">
              <span>Posted:</span>
              <span>{{ formatDate(credit_note.posted_at) }}</span>
            </div>
            <div v-if="credit_note.voided_at" class="flex justify-between text-sm">
              <span>Voided:</span>
              <span>{{ formatDate(credit_note.voided_at) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <Button class="w-full" variant="outline" @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}/edit`)" :disabled="!isEditable">
              <Edit class="mr-2 h-4 w-4" />
              Edit Credit Note
            </Button>
            <Button v-if="credit_note.status === 'draft'" class="w-full" variant="outline" @click="router.post(`/${company.slug}/credit-notes/${credit_note.id}/send`)">
              <Send class="mr-2 h-4 w-4" />
              Send to Customer
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
