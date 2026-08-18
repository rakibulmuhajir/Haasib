<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { EntitySearch, QuickAddModal } from '@/components/forms'
import MoneyText from '@/components/MoneyText.vue'
import TotalRow from '@/components/TotalRow.vue'
import InputError from '@/components/InputError.vue'
import Explain from '@/components/Explain.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import type { BreadcrumbItem } from '@/types'
import { Plus, ArrowLeft, Save, Trash2, AlertTriangle } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface LineItem {
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

const props = defineProps<{
  company: CompanyRef
  customers: Array<{ id: string; name: string }>
  revenueAccounts?: AccountOption[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
  { title: 'New' },
])

const emptyLine = (): LineItem => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
})

const form = useForm({
  customer_id: '',
  line_items: [emptyLine()],
  currency: props.company.base_currency,
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  internal_notes: '',
  payment_terms: 30,
  notes: '',
})

const showQuickAdd = ref(false)
const quickAddQuery = ref('')

/* One source of truth. The old version kept a parallel `lineItems` ref and
   re-assigned `form.line_items` after every keystroke — to the same array it
   already held, since both names pointed at one object. Three ways to write a
   line item is two ways to disagree about what a line item says. */
const lines = computed(() => form.line_items)

const lineNet = (item: LineItem) =>
  item.quantity * item.unit_price * (1 - (item.discount_rate || 0) / 100)

const subtotal = computed(() => lines.value.reduce((sum, item) => sum + lineNet(item), 0))

const taxAmount = computed(() =>
  lines.value.reduce((sum, item) => sum + lineNet(item) * ((item.tax_rate || 0) / 100), 0),
)

const totalAmount = computed(() => subtotal.value + taxAmount.value)

const addLine = () => form.line_items.push(emptyLine())

/* The last line is never removable. A blank first row is how a fresh invoice
   looks; an invoice with no rows at all is a state the form cannot recover
   from without a second button that only appears in that one case. */
const removeLine = (index: number) => {
  if (form.line_items.length === 1) form.line_items.splice(index, 1, emptyLine())
  else form.line_items.splice(index, 1)
}

/** Laravel returns these as `line_items.0.description`. */
const lineError = (index: number, field: string) =>
  (form.errors as Record<string, string>)[`line_items.${index}.${field}`]

/* Errors on fields the form cannot scroll to — a rejected line item, a rule
   about the invoice as a whole — would otherwise be invisible. */
const hasErrors = computed(() => Object.keys(form.errors).length > 0)

const handleQuickAddClick = (query: string) => {
  quickAddQuery.value = query
  showQuickAdd.value = true
}

const handleCustomerCreated = (customer: { id: string }) => {
  form.customer_id = customer.id
  showQuickAdd.value = false
}

const submit = () => form.post(`/${props.company.slug}/invoices`)
</script>

<template>
  <Head title="New invoice" />

  <PageShell title="New invoice" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button :disabled="form.processing" @click="submit">
        <Save class="mr-2 h-4 w-4" />
        {{ form.processing ? 'Saving…' : 'Save invoice' }}
      </Button>
    </template>

    <form class="space-y-6" @submit.prevent="submit">
      <Alert v-if="hasErrors" variant="destructive">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>This invoice was not saved</AlertTitle>
        <AlertDescription>
          Some fields need attention. Each one is marked below.
        </AlertDescription>
      </Alert>

      <Card>
        <CardHeader>
          <CardTitle>Who this is for</CardTitle>
          <CardDescription>The customer being billed.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">Customer</Label>
            <EntitySearch
              v-model="form.customer_id"
              entity-type="customer"
              placeholder="Search, or type a new name to add one"
              @quick-add-click="handleQuickAddClick"
            />
            <QuickAddModal
              v-model:open="showQuickAdd"
              entity-type="customer"
              :initial-name="quickAddQuery"
              @created="handleCustomerCreated"
            />
            <InputError :message="form.errors.customer_id" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Dates and terms</CardTitle>
        </CardHeader>
        <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <Label for="invoice_date">Invoice date</Label>
            <Input id="invoice_date" v-model="form.invoice_date" type="date" required />
            <InputError :message="form.errors.invoice_date" />
          </div>
          <div>
            <Label for="due_date">Due date</Label>
            <Input id="due_date" v-model="form.due_date" type="date" />
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
              />
              <span class="text-sm text-text-secondary">days</span>
            </div>
            <InputError :message="form.errors.payment_terms" />
          </div>
        </CardContent>
      </Card>

      <!-- Line items are dense work: a register, at the compact contract. -->
      <Card>
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
                  required
                />
                <InputError :message="lineError(index, 'unit_price')" />
              </div>
              <div>
                <Select v-model="item.income_account_id">
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

              <!-- Calculated, not entered. It sits on the sunken ground with no
                   border, because a value you cannot type into should not look
                   like a value you can. -->
              <div class="items__calc">
                <MoneyText :amount="lineNet(item)" :currency="form.currency" locale="en-PK" />
              </div>

              <div>
                <Button
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

          <Button type="button" variant="outline" class="mt-4 w-full" @click="addLine">
            <Plus class="mr-2 h-4 w-4" />
            Add a line
          </Button>

          <!-- The totals belong on the same sheet as the register they total.
               A separate card put a rule between a figure and its own working. -->
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


      <Card>
        <CardHeader>
          <CardTitle>Notes</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="internal_notes">Internal note</Label>
            <Textarea
              id="internal_notes"
              v-model="form.internal_notes"
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
.totals {
  margin-left: auto;
  margin-top: var(--space-5, 1.5rem);
  max-width: 24rem;
  border-top: 1px solid var(--rule-default);
  padding-top: var(--space-3, 0.75rem);
}

/* The line-item register. Column widths are declared once for the head and the
   rows so the two can never drift, which is what a 12-column utility grid
   restated on every row eventually does. */
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
