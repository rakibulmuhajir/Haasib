<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import DateTimeText from '@/components/DateTimeText.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import type { DocumentIssuer, DocumentLine } from '@/components/LedgerDocument.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import PageShell from '@/components/PageShell.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { formatDateTime } from '@/lib/datetime'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Edit, ArrowLeft, DollarSign, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  letterhead: DocumentIssuer
}

interface Application {
  bill_id: string
  amount_applied: number
  applied_at: string
  bill_balance_before: number
  bill_balance_after: number
  bill?: {
    bill_number: string
  }
}

interface CreditRef {
  id: string
  credit_number: string
  vendor?: {
    id: string
    name: string
    logo_url?: string | null
    address?: Record<string, unknown> | null
    email?: string | null
    phone?: string | null
    tax_id?: string | null
  }
  credit_date: string
  amount: number
  currency: string
  reason: string
  status: string
  notes: string | null
  applications?: Application[]
}

const props = defineProps<{
  company: CompanyRef
  credit: CreditRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
  { title: props.credit.credit_number, href: `/${props.company.slug}/vendor-credits/${props.credit.id}` },
]

const currency = computed(() => props.credit.currency || props.company.base_currency)

/**
 * A vendor credit is a document, and it was rendering as four grey panels with
 * the same amount restated in three of them. It is the mirror of a credit note:
 * the vendor issued it to us, so the vendor is the letterhead and we are the
 * party receiving -- exactly the party direction a bill uses.
 *
 * There are no line items. A credit carries one amount and the reason for it,
 * so the reason is the line, and quantity is suppressed rather than padded out
 * with a quantity of one.
 */
const ADDRESS_PARTS = ['line1', 'line2', 'street', 'city', 'state', 'postal_code', 'country']

const addressLines = (address?: Record<string, unknown> | null): string[] => {
  if (!address) return []
  return ADDRESS_PARTS.map((part) => address[part])
    .filter((value): value is string => typeof value === 'string' && value.trim().length > 0)
}

const issuer = computed<DocumentIssuer>(() => ({
  name: props.credit.vendor?.name ?? 'Vendor',
  logoUrl: props.credit.vendor?.logo_url ?? undefined,
  lines: addressLines(props.credit.vendor?.address),
  email: props.credit.vendor?.email ?? undefined,
  phone: props.credit.vendor?.phone ?? undefined,
  taxId: props.credit.vendor?.tax_id ?? undefined,
  taxIdLabel: 'NTN',
}))

/* The same identity block that heads our own invoices; on a document the
   vendor issued, it is the party receiving rather than the party sending. */
const creditTo = computed(() => props.company.letterhead)

const documentDates = computed(() => [
  { label: 'Issued', value: formatDateTime(props.credit.credit_date, { mode: 'date' }) },
])

const documentLines = computed<DocumentLine[]>(() => [
  {
    description: props.credit.reason || 'Vendor credit',
    amount: props.credit.amount,
  },
])

const overprint = computed(() => {
  if (['void', 'cancelled', 'reversed'].includes(props.credit.status)) return 'Void'
  if (props.credit.status === 'draft') return 'Draft'
  return null
})

/**
 * What the credit has been spent against. Applied and balance-after are two
 * readings of one event rather than two directions, so both are plain amounts
 * -- the register's in/out pair would claim a symmetry that is not there.
 */
const applicationColumns = [
  { key: 'bill_number', label: 'Bill', kind: 'ref' as const },
  { key: 'applied_at', label: 'Applied', kind: 'date' as const },
  { key: 'amount_applied', label: 'Amount', kind: 'amount' as const },
  { key: 'bill_balance_after', label: 'Bill balance after', kind: 'amount' as const },
]

const applicationRows = computed(() =>
  (props.credit.applications || []).map((application) => ({
    bill_id: application.bill_id,
    bill_number: application.bill?.bill_number || application.bill_id,
    amount_applied: application.amount_applied,
    bill_balance_after: application.bill_balance_after,
    applied_at: application.applied_at,
  })),
)

const amountApplied = computed(
  () => props.credit.applications?.reduce((sum, application) => sum + application.amount_applied, 0) || 0,
)

const amountRemaining = computed(() => props.credit.amount - amountApplied.value)

const isEditable = computed(() => ['draft', 'received'].includes(props.credit.status))
const isApplicable = computed(() => ['received', 'draft'].includes(props.credit.status))
const canDelete = computed(() => ['draft', 'received'].includes(props.credit.status))

const editCredit = () => {
  router.get(`/${props.company.slug}/vendor-credits/${props.credit.id}/edit`)
}

const applyCredit = () => {
  router.get(`/${props.company.slug}/vendor-credits/${props.credit.id}/apply`)
}

const deleteCredit = () => {
  if (confirm('Are you sure you want to delete this vendor credit?')) {
    router.delete(`/${props.company.slug}/vendor-credits/${props.credit.id}`)
  }
}
</script>

<template>
  <Head :title="`Vendor Credit ${credit.credit_number}`" />
  <PageShell
    :title="`Credit ${credit.credit_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/vendor-credits`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Credits
      </Button>
      <Button v-if="isEditable" @click="editCredit">
        <Edit class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button v-if="isApplicable" variant="outline" @click="applyCredit">
        <DollarSign class="mr-2 h-4 w-4" />
        Apply to Bills
      </Button>
      <Button v-if="canDelete" variant="destructive" @click="deleteCredit">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </Button>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <LedgerDocument
          doc-type="Vendor Credit"
          :doc-number="credit.credit_number"
          :issuer="issuer"
          :bill-to="creditTo"
          bill-to-label="Credit to"
          :dates="documentDates"
          :lines="documentLines"
          grand-total-label="Credit total"
          :grand-total-amount="credit.amount"
          :currency="currency"
          locale="en-PK"
          :overprint="overprint"
          :show-quantity="false"
        >
          <template v-if="credit.notes" #terms>
            <p dir="auto">{{ credit.notes }}</p>
          </template>
        </LedgerDocument>
      </div>

      <div class="space-y-4">
        <!-- Where the credit stands. Three readings of one figure, ruled off
             the way a balance is ruled off, rather than three panels each
             restating the amount in a different order. -->
        <section class="border border-rule-default bg-card p-4">
          <h2 class="mb-3 font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-secondary">
            Where this credit stands
          </h2>
          <dl class="space-y-2 text-sm">
            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-text-secondary">Issued for</dt>
              <dd><MoneyText :amount="credit.amount" :currency="currency" /></dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-text-secondary">Applied</dt>
              <dd><MoneyText :amount="amountApplied" :currency="currency" /></dd>
            </div>
            <div class="flex items-baseline justify-between gap-4 border-t-2 border-rule-emphasis pt-2 font-semibold">
              <dt>Still available</dt>
              <dd><MoneyText :amount="amountRemaining" :currency="currency" /></dd>
            </div>
          </dl>
          <div class="mt-3 flex items-baseline justify-between gap-4 border-t border-rule-default pt-3">
            <span class="text-sm text-text-secondary">Status</span>
            <StatusBadge :status="credit.status" />
          </div>
        </section>

        <!-- An unapplied balance is an offer, not good news and not an alarm.
             It gets a rule and a verb; the green it used to wear said the
             company had gained something, which is not what it means. -->
        <section v-if="amountRemaining > 0 && isApplicable" class="border border-rule-emphasis bg-surface-band p-4">
          <p class="text-sm text-text-secondary">
            <MoneyText :amount="amountRemaining" :currency="currency" /> has not been set against a bill yet.
          </p>
          <Button variant="outline" class="mt-3 w-full" @click="applyCredit">
            <DollarSign class="mr-2 h-4 w-4" />
            Apply to Bills
          </Button>
        </section>

        <p v-if="!isEditable" class="border-l-2 border-status-attention bg-surface-band p-3 text-sm text-text-secondary">
          This credit can no longer be modified in its current status.
        </p>
      </div>
    </div>

    <section class="mt-8">
      <h2 class="mb-3 font-display text-xl font-bold">Applied to bills</h2>
      <LedgerRegister
        :data="applicationRows"
        :columns="applicationColumns"
        :key-field="(row: Record<string, unknown>, index: number) => `${String(row.bill_id)}-${index}`"
      >
        <template #empty>This credit has not been set against a bill yet.</template>

        <template #cell-applied_at="{ row }">
          <DateTimeText :value="String(row.applied_at || '')" mode="date" />
        </template>

        <template #cell-amount_applied="{ row }">
          <MoneyText :amount="Number(row.amount_applied)" :currency="currency" />
        </template>

        <template #cell-bill_balance_after="{ row }">
          <MoneyText :amount="Number(row.bill_balance_after)" :currency="currency" />
        </template>
      </LedgerRegister>
    </section>
  </PageShell>
</template>
