<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import TotalRow from '@/components/TotalRow.vue'
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

interface CompanyRef {
  id: string
  name: string
  slug: string
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

const details = computed(() => [
  { term: 'Reference', value: props.invoice.reference },
  { term: 'Invoice date', value: formatDate(props.invoice.invoice_date) },
  { term: 'Due date', value: formatDate(props.invoice.due_date) },
  {
    term: 'Payment terms',
    value: props.invoice.payment_terms ? `${props.invoice.payment_terms} days` : null,
  },
])

const history = computed(() =>
  [
    { term: 'Created', value: formatDate(props.invoice.created_at) },
    { term: 'Sent', value: formatDate(props.invoice.sent_at) },
    { term: 'Opened by customer', value: formatDate(props.invoice.viewed_at) },
    { term: 'Paid', value: formatDate(props.invoice.paid_at) },
  ].filter((item) => item.value),
)

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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <Card>
          <CardContent class="pt-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="reference">{{ invoice.invoice_number }}</div>
                <p class="mt-1 text-lg" dir="auto">{{ invoice.customer.name }}</p>
                <p v-if="invoice.customer.email" class="text-sm text-text-secondary">
                  {{ invoice.customer.email }}
                </p>
                <p v-if="invoice.customer.phone" class="text-sm text-text-secondary">
                  {{ invoice.customer.phone }}
                </p>
              </div>

              <div class="text-right">
                <StatusBadge :status="displayStatus" explain />
                <!-- The one place a number is allowed to raise its voice, and
                     it earns it: this is what the reader came to find out. -->
                <p v-if="isOverdue" class="mt-2 text-sm text-status-critical">
                  {{ daysLate }} {{ daysLate === 1 ? 'day' : 'days' }} late
                </p>
              </div>
            </div>

            <div class="mt-6 border-t border-border pt-4">
              <DefinitionList :items="details" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>What was billed</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="lines" data-density="compact">
              <div class="lines__head">
                <span>Description</span>
                <span class="lines__num">Quantity</span>
                <span class="lines__num">Unit price</span>
                <span class="lines__num">Total</span>
              </div>

              <div v-for="item in invoice.line_items" :key="item.id" class="lines__row">
                <span dir="auto">{{ item.description }}</span>
                <span class="lines__num lines__figure">{{ item.quantity }}</span>
                <span class="lines__num">
                  <MoneyText
                    :amount="item.unit_price"
                    :currency="invoice.currency"
                    locale="en-PK"
                    :show-currency="false"
                  />
                </span>
                <span class="lines__num">
                  <MoneyText
                    :amount="item.total"
                    :currency="invoice.currency"
                    locale="en-PK"
                    :show-currency="false"
                  />
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card v-if="invoice.description || invoice.notes">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="invoice.notes">
              <h4 class="text-sm font-medium">Note to the customer</h4>
              <p class="mt-1 text-sm text-text-secondary" dir="auto">{{ invoice.notes }}</p>
            </div>
            <div v-if="invoice.description">
              <h4 class="text-sm font-medium">Internal note</h4>
              <p class="mt-1 text-sm text-text-secondary" dir="auto">{{ invoice.description }}</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-6">
        <!-- The derivation. Every figure that produced the balance is here, in
             the order it was applied, so the answer can be checked rather than
             believed. -->
        <Card>
          <CardContent class="pt-6">
            <TotalRow
              level="line"
              label="Subtotal"
              :amount="invoice.subtotal"
              :currency="invoice.currency"
              locale="en-PK"
            />
            <TotalRow
              v-if="invoice.discount_amount > 0"
              level="line"
              label="Discount"
              direction="outflow"
              :amount="invoice.discount_amount"
              :currency="invoice.currency"
              locale="en-PK"
            />
            <TotalRow
              v-if="invoice.tax_amount > 0"
              level="line"
              label="Sales tax"
              :amount="invoice.tax_amount"
              :currency="invoice.currency"
              locale="en-PK"
            />
            <TotalRow
              level="total"
              label="Invoice total"
              :amount="invoice.total_amount"
              :currency="invoice.currency"
              locale="en-PK"
            />
            <TotalRow
              level="line"
              label="Paid"
              direction="outflow"
              :amount="invoice.paid_amount"
              :currency="invoice.currency"
              locale="en-PK"
            />
            <TotalRow
              level="grand"
              label="Still owed"
              :amount="invoice.balance"
              :currency="invoice.currency"
              locale="en-PK"
              :tone="isOverdue ? 'overdue' : invoice.balance === 0 ? 'muted' : 'default'"
            />

            <p v-if="isForeign" class="mt-3 text-sm text-text-metadata">
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

        <Card v-if="history.length">
          <CardHeader>
            <CardTitle>What happened when</CardTitle>
          </CardHeader>
          <CardContent>
            <DefinitionList :items="history" />
          </CardContent>
        </Card>

        <Card>
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
            <Button class="w-full" variant="outline">
              <Download class="mr-2 h-4 w-4" />
              Download PDF
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
      </div>
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
  </PageShell>
</template>

<style scoped>
.reference {
  font-family: var(--mono-family);
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
}

.lines__head,
.lines__row {
  display: grid;
  grid-template-columns: 1fr 5rem 8rem 8rem;
  gap: var(--space-3, 12px);
  align-items: baseline;
}

.lines__head {
  padding-bottom: var(--cell-py);
  border-bottom: 1px solid var(--rule-default);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-metadata);
}

.lines__row {
  padding-block: var(--cell-py);
  border-bottom: 1px solid var(--rule-subtle);
}

.lines__row:last-child {
  border-bottom: 0;
}

.lines__num {
  text-align: right;
}

/* Quantities are figures too, and they sit in a column with the rest. */
.lines__figure {
  font-variant-numeric: tabular-nums;
}

@media (max-width: 40rem) {
  .lines__head {
    display: none;
  }

  .lines__row {
    grid-template-columns: 1fr auto;
    row-gap: 2px;
  }

  .lines__row > :nth-child(2),
  .lines__row > :nth-child(3) {
    display: none;
  }
}
</style>
