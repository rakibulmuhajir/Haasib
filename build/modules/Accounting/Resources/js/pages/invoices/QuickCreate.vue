<script setup lang="ts">
/**
 * QuickInvoiceCreate - Simplified Invoice Creation (Owner Mode)
 *
 * "3-Click Invoice" experience:
 * 1. Who is this for? (Customer)
 * 2. What did you sell? (Description)
 * 3. How much? (Amount)
 *
 * @see docs/plans/invoice-bill-creation-ux.md
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useLexicon } from '@/composables/useLexicon'
import { useFormFeedback } from '@/composables/useFormFeedback'
import PageShell from '@/components/PageShell.vue'
import { EntitySearch, AmountInput, DueDatePicker, TaxToggle, QuickAddModal } from '@/components/forms'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Send, Save, ChevronRight, Plus, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  default_payment_terms?: number
}

interface Customer {
  id: string
  name: string
  email?: string
  payment_terms?: number
  tax_code_id?: string
}

interface TaxCode {
  id: string
  name: string
  code: string
  rate: number
}

interface LineItem {
  description: string
  quantity: number
  unit_price: number | null
}

const props = defineProps<{
  company: CompanyRef
  recentCustomers?: Customer[]
  defaultTaxCode?: TaxCode | null
  defaultTerms?: number
}>()

// Composables
const { t, tpl } = useLexicon()
const { showSuccess, showError } = useFormFeedback()

// Breadcrumbs
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: t('invoices'), href: `/${props.company.slug}/invoices` },
  { title: t('newInvoice') },
])

// State
const showQuickAdd = ref(false)
const selectedCustomer = ref<Customer | null>(null)
const resolvedTaxCode = ref<TaxCode | null>(props.defaultTaxCode || null)
const showMoreDetails = ref(false)

const lineItemTemplate = (): LineItem => ({
  description: '',
  quantity: 1,
  unit_price: null,
})

// Form
const form = useForm({
  customer_id: null as string | null,
  apply_tax: false,
  line_items: [lineItemTemplate()] as LineItem[],
  due_date: null as string | null,
  invoice_date: new Date().toISOString().split('T')[0],
  reference: '',
  notes: '',
})

// Computed
const defaultTerms = computed(() => {
  return selectedCustomer.value?.payment_terms
    || props.company.default_payment_terms
    || props.defaultTerms
    || 30
})

const lineItems = computed(() => form.line_items)

const subtotal = computed(() => {
  return lineItems.value.reduce((sum, item) => {
    const price = item.unit_price ?? 0
    return sum + item.quantity * price
  }, 0)
})

const taxAmount = computed(() => {
  if (!form.apply_tax || !resolvedTaxCode.value) return 0
  return subtotal.value * (resolvedTaxCode.value.rate / 100)
})

const totalAmount = computed(() => subtotal.value + taxAmount.value)

const isValid = computed(() => {
  return !!form.customer_id
    && lineItems.value.length > 0
    && lineItems.value.every((item) => item.description.trim().length > 0)
    && lineItems.value.every((item) => (item.unit_price ?? 0) > 0)
})

// Handlers
const handleCustomerSelected = (customer: Customer) => {
  selectedCustomer.value = customer
  form.customer_id = customer.id
}

const addLineItem = () => {
  form.line_items.push(lineItemTemplate())
}

const removeLineItem = (index: number) => {
  if (form.line_items.length <= 1) return
  form.line_items.splice(index, 1)
}

const handleQuickAddCreated = (customer: Customer) => {
  handleCustomerSelected(customer)
  showQuickAdd.value = false
}

const handleTaxCodeResolved = (taxCode: TaxCode | null) => {
  resolvedTaxCode.value = taxCode
}

const saveDraft = () => {
  form.transform((data) => ({
    ...data,
    status: 'draft',
    line_items: data.line_items.map((item) => ({
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price ?? 0,
      tax_rate: data.apply_tax && resolvedTaxCode.value ? resolvedTaxCode.value.rate : null,
      discount_amount: null,
      income_account_id: null,
    })),
  })).post(`/${props.company.slug}/invoices`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('invoiceSaved'))
    },
    onError: (errors) => showError(errors),
  })
}

const sendInvoice = () => {
  form.transform((data) => ({
    ...data,
    status: 'approved',
    send_immediately: true,
    line_items: data.line_items.map((item) => ({
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price ?? 0,
      tax_rate: data.apply_tax && resolvedTaxCode.value ? resolvedTaxCode.value.rate : null,
      discount_amount: null,
      income_account_id: null,
    })),
  })).post(`/${props.company.slug}/invoices`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('invoiceSent'))
    },
    onError: (errors) => showError(errors),
  })
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: props.company.base_currency || 'USD',
  }).format(amount)
}
</script>

<template>
  <Head :title="t('newInvoice')" />

  <PageShell
    :title="t('newInvoice')"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        {{ t('back') }}
      </Button>
    </template>

    <div class="mx-auto w-full max-w-5xl">
      <form @submit.prevent class="grid gap-5 lg:grid-cols-12 lg:gap-7">
        <div class="space-y-5 lg:col-span-7">
          <!-- Customer Selection -->
          <div class="space-y-1.5">
            <Label class="text-sm font-semibold text-foreground">{{ t('whoIsThisFor') }}</Label>
            <EntitySearch
              v-model="form.customer_id"
              entity-type="customer"
              :placeholder="t('searchCustomers')"
              @entity-selected="handleCustomerSelected"
              @quick-add-click="showQuickAdd = true"
              :error="form.errors.customer_id"
            />
          </div>

          <!-- Items -->
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-3">
              <Label class="text-sm font-semibold text-foreground">{{ t('whatDidYouSell') }}</Label>
              <Button type="button" variant="ghost" size="sm" class="-mr-2" @click="addLineItem">
                <Plus class="h-4 w-4" />
                {{ t('addLine') }}
              </Button>
            </div>

            <div class="space-y-3">
              <div
                v-for="(item, idx) in form.line_items"
                :key="idx"
                class="rounded-xl border border-border/70 bg-muted/10 p-3"
              >
                <div class="mb-3 flex items-center justify-between gap-2">
                  <div class="text-sm font-medium text-foreground">
                    {{ tpl('itemNumber', { number: idx + 1 }) }}
                  </div>
                  <Button
                    v-if="form.line_items.length > 1"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                    @click="removeLineItem(idx)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                  <div class="flex-1 space-y-1.5">
                    <Textarea
                      v-model="item.description"
                      :placeholder="t('descriptionPlaceholder')"
                      rows="2"
                    />
                  </div>

                  <div class="w-full space-y-1.5 sm:w-[220px]">
                    <Label class="text-xs font-medium text-muted-foreground">{{ t('howMuch') }}</Label>
                    <AmountInput
                      v-model="item.unit_price"
                      :currency="company.base_currency"
                      :size="idx === 0 ? 'lg' : 'md'"
                    />
                  </div>
                </div>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div class="flex items-center gap-2">
                    <Label class="text-xs font-medium text-muted-foreground">Qty</Label>
                    <Input v-model.number="item.quantity" type="number" min="0.01" step="0.01" class="h-9 w-24" />
                  </div>

                  <div v-if="idx === 0" class="flex items-center justify-between gap-3 sm:justify-end">
                    <TaxToggle
                      v-model="form.apply_tax"
                      entity-type="customer"
                      :entity-id="form.customer_id"
                      @tax-code-resolved="handleTaxCodeResolved"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Due Date -->
          <div class="space-y-1.5">
            <Label class="text-xs font-medium text-muted-foreground">{{ t('dueIn') }}</Label>
            <DueDatePicker
              v-model="form.due_date"
              :invoice-date="form.invoice_date"
              :default-terms="defaultTerms"
              :error="form.errors.due_date"
            />
          </div>

          <Separator class="opacity-60" />

          <!-- Advanced -->
          <Collapsible v-model:open="showMoreDetails">
            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-border/70 bg-muted/30 px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground">
              <span>{{ t('addMoreDetails') }}</span>
              <ChevronRight
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-90': showMoreDetails }"
              />
            </CollapsibleTrigger>
            <CollapsibleContent class="mt-4 space-y-4">
              <div class="grid gap-4 sm:grid-cols-2">
                <!-- Invoice Date -->
                <div class="space-y-2">
                  <Label class="text-xs font-medium text-muted-foreground">{{ t('invoiceDate') }}</Label>
                  <Input
                    type="date"
                    v-model="form.invoice_date"
                  />
                </div>
                <!-- Reference -->
                <div class="space-y-2">
                  <Label class="text-xs font-medium text-muted-foreground">{{ t('reference') }}</Label>
                  <Input
                    v-model="form.reference"
                    :placeholder="t('referencePlaceholder')"
                  />
                </div>
              </div>
              <!-- Notes -->
              <div class="space-y-2">
                <Label class="text-xs font-medium text-muted-foreground">{{ t('customerNotes') }}</Label>
                <Textarea
                  v-model="form.notes"
                  :placeholder="t('customerNotesPlaceholder')"
                  rows="2"
                />
              </div>
            </CollapsibleContent>
          </Collapsible>
        </div>

        <!-- Summary -->
        <div class="lg:col-span-5">
          <Card class="lg:sticky lg:top-20 overflow-hidden">
            <CardHeader class="space-y-1 pb-3">
              <CardTitle class="text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ t('total') }}</CardTitle>
              <div class="text-4xl font-semibold leading-none tabular-nums">
                {{ formatCurrency(totalAmount) }}
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="space-y-2 rounded-lg border border-border/70 bg-muted/15 p-3">
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">{{ t('subtotal') }}</span>
                  <span class="tabular-nums text-foreground/90">{{ formatCurrency(subtotal) }}</span>
                </div>
                <div v-if="form.apply_tax && resolvedTaxCode" class="flex justify-between text-sm animate-in fade-in duration-200">
                  <span class="text-muted-foreground">{{ t('tax') }} ({{ resolvedTaxCode.rate }}%)</span>
                  <span class="tabular-nums text-foreground/90">{{ formatCurrency(taxAmount) }}</span>
                </div>
              </div>

              <div class="flex flex-col gap-2.5">
                <Button
                  type="button"
                  @click="sendInvoice"
                  :disabled="form.processing || !isValid"
                  size="lg"
                  class="w-full bg-gradient-to-r from-teal-600 to-emerald-600 text-white shadow-md shadow-teal-600/20 hover:from-teal-700 hover:to-emerald-700 disabled:opacity-50"
                >
                  <Send class="mr-2 h-4 w-4" />
                  {{ t('sendInvoice') }}
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  @click="saveDraft"
                  :disabled="form.processing"
                  class="w-full"
                >
                  <Save class="mr-2 h-4 w-4" />
                  {{ t('saveDraft') }}
                </Button>
              </div>

              <p class="text-xs text-muted-foreground">
                {{ tpl('dueInDays', { days: defaultTerms }) }}
              </p>
            </CardContent>
          </Card>
        </div>
      </form>
    </div>

    <!-- Quick Add Customer Modal -->
    <QuickAddModal
      v-model:open="showQuickAdd"
      entity-type="customer"
      @created="handleQuickAddCreated"
    />
  </PageShell>
</template>
