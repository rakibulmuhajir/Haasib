<script setup lang="ts">
/**
 * Edit an invoice. Deliberately the same form as Create — same sections, same
 * register, same totals — because an invoice being amended is the same document
 * as an invoice being written, and a person who has learned one should not have
 * to learn the other.
 *
 * What Create does not have: an invoice can reach a state where amending it is
 * no longer honest. Posted, paid, voided and reversed invoices are shown here
 * read-only rather than hidden behind a redirect, so the figures stay legible
 * and the page can say what to do instead.
 */
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { EntitySearch } from '@/components/forms'
import MoneyText from '@/components/MoneyText.vue'
import TotalRow from '@/components/TotalRow.vue'
import InputError from '@/components/InputError.vue'
import Explain from '@/components/Explain.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import type { BreadcrumbItem } from '@/types'
import { Plus, ArrowLeft, Save, Trash2, AlertTriangle, Lock } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface LineItem {
  id?: string
  description: string
  quantity: number
  unit_price: number
  tax_rate?: number
  discount_rate?: number
  income_account_id?: string
}

interface AccountOption {
  id: string
  code: string
  name: string
}

interface Invoice {
  id: string
  invoice_number: string
  customer: { id: string; name: string }
  status: string
  currency: string
  invoice_date: string
  due_date: string
  internal_notes?: string
  payment_terms?: number
  notes?: string
  line_items: LineItem[]
}

const props = defineProps<{
  company: CompanyRef
  invoice: Invoice
  revenueAccounts?: AccountOption[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
  { title: props.invoice.invoice_number, href: `/${props.company.slug}/invoices/${props.invoice.id}` },
  { title: 'Edit' },
])

/* The API returns dates as ISO datetimes ("2026-08-18T00:00:00.000000Z").
   `<input type="date">` accepts only YYYY-MM-DD and silently renders empty for
   anything else — so both dates on this form arrived blank and a save would
   have quietly cleared them. */
const toDateInput = (value?: string | null) => (value ? value.slice(0, 10) : '')

const emptyLine = (): LineItem => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
})

/* A stored invoice with no line items is not a state the form can represent,
   so it opens on a blank one rather than on nothing. */
const initialLines = props.invoice.line_items.length
  ? props.invoice.line_items.map((item) => ({ ...item }))
  : [emptyLine()]

const form = useForm({
  customer_id: props.invoice.customer.id,
  line_items: initialLines,
  currency: props.invoice.currency,
  invoice_date: toDateInput(props.invoice.invoice_date),
  due_date: toDateInput(props.invoice.due_date),
  internal_notes: props.invoice.internal_notes || '',
  payment_terms: props.invoice.payment_terms ?? 30,
  notes: props.invoice.notes || '',
})

/* One source of truth, as in Create. The old version held a parallel
   `lineItems` ref and re-assigned `form.line_items` from a deep watcher on
   every keystroke — to the same array object it already pointed at. */
const lines = computed(() => form.line_items)

const lineNet = (item: LineItem) =>
  item.quantity * item.unit_price * (1 - (item.discount_rate || 0) / 100)

const subtotal = computed(() => lines.value.reduce((sum, item) => sum + lineNet(item), 0))

const taxAmount = computed(() =>
  lines.value.reduce((sum, item) => sum + lineNet(item) * ((item.tax_rate || 0) / 100), 0),
)

const totalAmount = computed(() => subtotal.value + taxAmount.value)

const addLine = () => form.line_items.push(emptyLine())

const removeLine = (index: number) => {
  if (form.line_items.length === 1) form.line_items.splice(index, 1, emptyLine())
  else form.line_items.splice(index, 1)
}

/** Laravel returns these as `line_items.0.description`. */
const lineError = (index: number, field: string) =>
  (form.errors as Record<string, string>)[`line_items.${index}.${field}`]

const hasErrors = computed(() => Object.keys(form.errors).length > 0)

/* The old rule was `status !== 'paid' && status !== 'cancelled'`, which left a
   posted, voided or reversed invoice fully editable. Those are exactly the
   states where the figures have already been reported somewhere else. */
const IMMUTABLE = ['paid', 'partially_paid', 'posted', 'void', 'cancelled', 'reversed', 'locked', 'closed']

const isEditable = computed(() => !IMMUTABLE.includes(props.invoice.status))

/** What to do instead, per state — an explanation without a next step is a wall. */
const lockedAdvice = computed(() => {
  switch (props.invoice.status) {
    case 'paid':
    case 'partially_paid':
      return 'Money has been received against it. Issue a credit note to change what the customer owes.'
    case 'void':
    case 'cancelled':
    case 'reversed':
      return 'It has been withdrawn from the ledger. Raise a new invoice instead.'
    default:
      return 'It has been posted to the ledger. Raise a credit note or a journal entry to correct it.'
  }
})

const submit = () => {
  if (!isEditable.value) return
  form.put(`/${props.company.slug}/invoices/${props.invoice.id}`)
}
</script>

<template>
  <Head :title="`Edit ${invoice.invoice_number}`" />

  <PageShell :title="`Edit ${invoice.invoice_number}`" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices/${invoice.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        {{ isEditable ? 'Cancel' : 'Back' }}
      </Button>
      <Button v-if="isEditable" :disabled="form.processing" @click="submit">
        <Save class="mr-2 h-4 w-4" />
        {{ form.processing ? 'Saving…' : 'Save changes' }}
      </Button>
    </template>

    <form novalidate class="space-y-6" @submit.prevent="submit">
      <!-- Locked is a state that needs attention, not an adverse one: amber,
           with the status itself as the non-colour indicator. -->
      <Alert v-if="!isEditable" class="locked">
        <Lock class="h-4 w-4" />
        <AlertTitle class="flex items-center gap-2">
          This invoice can no longer be changed
          <StatusBadge :status="invoice.status" />
        </AlertTitle>
        <AlertDescription>{{ lockedAdvice }}</AlertDescription>
      </Alert>

      <Alert v-if="hasErrors" variant="destructive">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>Your changes were not saved</AlertTitle>
        <AlertDescription>
          Some fields need attention. Each one is marked below.
        </AlertDescription>
      </Alert>

      <Card variant="form">
        <CardHeader>
          <CardTitle>Who this is for</CardTitle>
          <CardDescription>The customer being billed.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">Customer</Label>
            <EntitySearch
              v-if="isEditable"
              v-model="form.customer_id"
              :initial-entity="invoice.customer"
              entity-type="customer"
              placeholder="Search customers"
            />
            <p v-else class="posted">{{ invoice.customer.name }}</p>
            <InputError :message="form.errors.customer_id" />
          </div>
        </CardContent>
      </Card>

      <Card variant="form">
        <CardHeader>
          <CardTitle>Dates and terms</CardTitle>
        </CardHeader>
        <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <Label for="invoice_date">Invoice date</Label>
            <Input id="invoice_date" v-model="form.invoice_date" type="date" :disabled="!isEditable" required />
            <InputError :message="form.errors.invoice_date" />
          </div>
          <div>
            <Label for="due_date">Due date</Label>
            <Input id="due_date" v-model="form.due_date" type="date" :disabled="!isEditable" />
            <InputError :message="form.errors.due_date" />
          </div>
          <div>
            <Label for="payment_terms">Payment terms</Label>
            <div class="flex items-center gap-2">
              <Input
                id="payment_terms"
                v-model.number="form.payment_terms"
                type="number"
                min="0"
                max="365"
                class="w-24"
                :disabled="!isEditable"
              />
              <span class="text-sm text-text-secondary">days</span>
            </div>
            <InputError :message="form.errors.payment_terms" />
          </div>
        </CardContent>
      </Card>

      <!-- Line items are dense work: a register, at the compact contract. -->
      <Card variant="form">
        <CardHeader>
          <CardTitle>What is being billed</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="items" data-density="compact">
            <div class="items__head" aria-hidden="true">
              <span>Description</span>
              <span class="items__num">Quantity</span>
              <span class="items__num">Unit price</span>
              <span>Income account</span>
              <span class="items__num">Line total</span>
              <span class="sr-only">Remove</span>
            </div>

            <div v-for="(item, index) in lines" :key="index" class="items__row">
              <div>
                <Input
                  v-model="item.description"
                  :aria-label="`Description, line ${index + 1}`"
                  :disabled="!isEditable"
                  placeholder="What was sold"
                  required
                />
                <InputError :message="lineError(index, 'description')" />
              </div>
              <div>
                <Input
                  v-model.number="item.quantity"
                  :aria-label="`Quantity, line ${index + 1}`"
                  class="num"
                  type="number"
                  min="0.01"
                  step="0.01"
                  :disabled="!isEditable"
                  required
                />
                <InputError :message="lineError(index, 'quantity')" />
              </div>
              <div>
                <Input
                  v-model.number="item.unit_price"
                  :aria-label="`Unit price, line ${index + 1}`"
                  class="num"
                  type="number"
                  min="0"
                  step="0.01"
                  :disabled="!isEditable"
                  required
                />
                <InputError :message="lineError(index, 'unit_price')" />
              </div>
              <div>
                <Select v-model="item.income_account_id" :disabled="!isEditable">
                  <SelectTrigger :aria-label="`Income account, line ${index + 1}`">
                    <SelectValue placeholder="Default" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">Default</SelectItem>
                    <SelectItem
                      v-for="acct in props.revenueAccounts || []"
                      :key="acct.id"
                      :value="acct.id"
                    >
                      {{ acct.code }} — {{ acct.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <!-- Calculated, not entered. It sits with no border, because a
                   value you cannot type into should not look like one you can. -->
              <div class="items__calc">
                <MoneyText :amount="lineNet(item)" :currency="form.currency" locale="en-PK" />
              </div>

              <div>
                <Button
                  v-if="isEditable"
                  type="button"
                  variant="ghost"
                  class="h-9 w-9 p-0"
                  @click="removeLine(index)"
                >
                  <Trash2 class="h-4 w-4" />
                  <span class="sr-only">Remove line {{ index + 1 }}</span>
                </Button>
              </div>
            </div>
          </div>

          <InputError class="mt-2" :message="form.errors.line_items" />

          <Button
            v-if="isEditable"
            type="button"
            variant="outline"
            class="mt-4 w-full"
            @click="addLine"
          >
            <Plus class="mr-2 h-4 w-4" />
            Add a line
          </Button>

          <!-- The totals belong on the same sheet as the register they total. -->
          <div class="totals">
            <TotalRow
              level="line"
              label="Subtotal"
              :amount="subtotal"
              :currency="form.currency"
              locale="en-PK"
            />
            <TotalRow
              level="line"
              label="Sales tax"
              :amount="taxAmount"
              :currency="form.currency"
              locale="en-PK"
            />
            <TotalRow
              level="grand"
              label="Invoice total"
              :note="`${lines.length} ${lines.length === 1 ? 'line' : 'lines'}`"
              :amount="totalAmount"
              :currency="form.currency"
              locale="en-PK"
            />
          </div>
        </CardContent>
      </Card>

      <Card variant="form">
        <CardHeader>
          <CardTitle>Notes</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="internal_notes">Internal note</Label>
            <Textarea
              id="internal_notes"
              v-model="form.internal_notes"
              :disabled="!isEditable"
              placeholder="Only your team sees this"
              rows="3"
            />
            <InputError :message="form.errors.internal_notes" />
          </div>
          <div>
            <Label for="notes">Note to the customer</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              :disabled="!isEditable"
              placeholder="Printed on the invoice"
              rows="3"
            />
            <InputError :message="form.errors.notes" />
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>

<style scoped>
/* Attention, not alarm: this invoice is fine, it is simply finished. */
.locked {
  border-color: var(--status-attention);
  background: color-mix(in oklab, var(--status-attention) 8%, transparent);
}

.locked :deep(svg) {
  color: var(--status-attention);
}

/* A posted value shown where a field would be. No border and no ground: it is
   a fact on the page, not an empty box someone might try to type into. */
.posted {
  padding-block: 0.5rem;
  color: var(--text-primary);
}

.totals {
  margin-left: auto;
  margin-top: var(--space-5, 1.5rem);
  max-width: 24rem;
  border-top: 1px solid var(--rule-default);
  padding-top: var(--space-3, 0.75rem);
}

/* The line-item register. Column widths are declared once for the head and the
   rows so the two can never drift. */
.items__head,
.items__row {
  display: grid;
  grid-template-columns: minmax(10rem, 3fr) 6rem 8rem minmax(8rem, 2fr) 8rem 2.5rem;
  gap: var(--space-2, 8px);
  align-items: start;
}

.items__head {
  padding-bottom: var(--space-2, 8px);
  border-bottom: 1px solid var(--rule-default);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-metadata);
}

.items__row {
  padding-block: var(--cell-py);
  border-bottom: 1px solid var(--rule-subtle);
}

.items__num {
  text-align: right;
}

.items :deep(.num) {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/* System-calculated: no field, no border, no ground of its own. */
.items__calc {
  padding-top: 0.5rem;
  text-align: right;
}

/* Under 60rem the six-column register stops being a register and becomes a
   stack of labelled fields — the same information, still enterable. */
@media (max-width: 60rem) {
  .items__head {
    display: none;
  }

  .items__row {
    grid-template-columns: 1fr 1fr;
    padding-block: var(--space-4, 16px);
  }

  .items__calc {
    grid-column: 1 / -1;
    text-align: right;
  }
}
</style>
