<script setup lang="ts">
/**
 * QuickBillCreate - Simplified Bill Creation (Owner Mode)
 *
 * "3-Click Bill" experience:
 * 1. Who is it from? (Vendor)
 * 2. What did you buy? (Description)
 * 3. How much? (Amount)
 * 4. Category? (Expense account - required)
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
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, CreditCard, Save, ChevronRight, Plus, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  default_payment_terms?: number
}

interface Vendor {
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

interface AccountOption {
  id: string
  code: string
  name: string
  type?: string
  subtype?: string
}

interface AccountGroup {
  label: string
  accounts: AccountOption[]
}

interface LineItem {
  description: string
  quantity: number
  unit_price: number | null
}

const props = defineProps<{
  company: CompanyRef
  recentVendors?: Vendor[]
  expenseAccounts?: AccountOption[]
  defaultExpenseAccountId?: string | null
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
  { title: t('bills'), href: `/${props.company.slug}/bills` },
  { title: t('newBill') },
])

// Group expense accounts by type for better UX
const expenseCategories = computed<AccountGroup[]>(() => {
  if (!props.expenseAccounts?.length) return []

  const groups: Record<string, AccountOption[]> = {}

  for (const account of props.expenseAccounts) {
    const groupKey = account.subtype || account.type || 'Other'
    if (!groups[groupKey]) {
      groups[groupKey] = []
    }
    groups[groupKey].push(account)
  }

  return Object.entries(groups).map(([label, accounts]) => ({
    label: label.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
    accounts,
  }))
})

// State
const showQuickAdd = ref(false)
const selectedVendor = ref<Vendor | null>(null)
const resolvedTaxCode = ref<TaxCode | null>(props.defaultTaxCode || null)
const showMoreDetails = ref(false)

const lineItemTemplate = (): LineItem => ({
  description: '',
  quantity: 1,
  unit_price: null,
})

// Form
const form = useForm({
  vendor_id: null as string | null,
  account_id: (props.defaultExpenseAccountId ?? null) as string | null,  // Expense category - required
  apply_tax: false,
  line_items: [lineItemTemplate()] as LineItem[],
  due_date: null as string | null,
  bill_date: new Date().toISOString().split('T')[0],
  bill_number: '',  // Vendor's invoice number
  reference: '',
  notes: '',
  currency: props.company.base_currency,
  base_currency: props.company.base_currency,
  exchange_rate: null as number | null,
  payment_terms: props.company.default_payment_terms ?? props.defaultTerms ?? 30,
  status: 'draft',
  pay_immediately: false,
})

// Computed
const defaultTerms = computed(() => {
  return selectedVendor.value?.payment_terms
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
  return !!form.vendor_id
    && lineItems.value.length > 0
    && lineItems.value.every((item) => item.description.trim().length > 0)
    && lineItems.value.every((item) => (item.unit_price ?? 0) > 0)
    && !!form.account_id
})

// Handlers
const handleVendorSelected = (vendor: Vendor) => {
  selectedVendor.value = vendor
  form.vendor_id = vendor.id
}

const handleQuickAddCreated = (vendor: Vendor) => {
  handleVendorSelected(vendor)
  showQuickAdd.value = false
}

const handleTaxCodeResolved = (taxCode: TaxCode | null) => {
  resolvedTaxCode.value = taxCode
}

const handleCategoryChange = (value: any) => {
  form.account_id = String(value)
}

const addLineItem = () => {
  form.line_items.push(lineItemTemplate())
}

const removeLineItem = (index: number) => {
  if (form.line_items.length <= 1) return
  form.line_items.splice(index, 1)
}

const saveDraft = () => {
  form.transform((data) => ({
    ...data,
    status: 'draft',
    pay_immediately: false,
    payment_terms: defaultTerms.value,
    line_items: data.line_items.map((item) => ({
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price ?? 0,
      tax_rate: data.apply_tax && resolvedTaxCode.value ? resolvedTaxCode.value.rate : null,
      discount_rate: null,
      expense_account_id: data.account_id,
    })),
  })).post(`/${props.company.slug}/bills`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('billSaved'))
    },
    onError: (errors) => showError(errors),
  })
}

const saveAndPay = () => {
  form.transform((data) => ({
    ...data,
    status: 'received',
    pay_immediately: true,
    payment_terms: defaultTerms.value,
    line_items: data.line_items.map((item) => ({
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price ?? 0,
      tax_rate: data.apply_tax && resolvedTaxCode.value ? resolvedTaxCode.value.rate : null,
      discount_rate: null,
      expense_account_id: data.account_id,
    })),
  })).post(`/${props.company.slug}/bills`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('billSavedAndPaid'))
    },
    onError: (errors) => showError(errors),
  })
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.company.base_currency || 'USD',
  }).format(amount)
}
</script>

<template>
  <Head :title="t('newBill')" />

  <PageShell
    :title="t('newBill')"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/bills`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        {{ t('back') }}
      </Button>
    </template>

    <div class="mx-auto w-full max-w-5xl">
      <form @submit.prevent class="grid gap-5 lg:grid-cols-12 lg:gap-7">
        <div class="space-y-5 lg:col-span-7">
          <!-- Vendor Selection -->
          <div class="space-y-1.5">
            <Label class="text-sm font-semibold text-foreground">{{ t('whoIsItFrom') }}</Label>
            <EntitySearch
              v-model="form.vendor_id"
              entity-type="vendor"
              :placeholder="t('searchVendors')"
              @entity-selected="handleVendorSelected"
              @quick-add-click="showQuickAdd = true"
              :error="form.errors.vendor_id"
            />
          </div>

          <!-- Items -->
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-3">
              <Label class="text-sm font-semibold text-foreground">{{ t('whatDidYouBuy') }}</Label>
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
                      :placeholder="t('billDescriptionPlaceholder')"
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
                      entity-type="vendor"
                      :entity-id="form.vendor_id"
                      @tax-code-resolved="handleTaxCodeResolved"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Expense Category (Required for bills) -->
          <div class="space-y-1.5">
            <Label class="text-sm font-semibold text-foreground">{{ t('expenseCategory') }}</Label>
            <Select :modelValue="form.account_id" @update:modelValue="handleCategoryChange">
              <SelectTrigger :class="{ 'border-destructive': form.errors.account_id }">
                <SelectValue :placeholder="t('selectCategory')" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup v-for="group in expenseCategories" :key="group.label">
                  <SelectLabel>{{ group.label }}</SelectLabel>
                  <SelectItem
                    v-for="account in group.accounts"
                    :key="account.id"
                    :value="account.id"
                  >
                    {{ account.code }} - {{ account.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <p v-if="form.errors.account_id" class="text-sm text-destructive">
              {{ form.errors.account_id }}
            </p>
          </div>

          <!-- Due Date -->
          <div class="space-y-1.5">
            <Label class="text-xs font-medium text-muted-foreground">{{ t('dueIn') }}</Label>
            <DueDatePicker
              v-model="form.due_date"
              :invoice-date="form.bill_date"
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
                <!-- Bill Date -->
                <div class="space-y-2">
                  <Label class="text-xs font-medium text-muted-foreground">{{ t('billDate') }}</Label>
                  <Input
                    type="date"
                    v-model="form.bill_date"
                  />
                </div>
                <!-- Vendor Invoice Number -->
                <div class="space-y-2">
                  <Label class="text-xs font-medium text-muted-foreground">{{ t('vendorInvoiceNumber') }}</Label>
                  <Input
                    v-model="form.bill_number"
                    :placeholder="t('vendorInvoiceNumberPlaceholder')"
                  />
                </div>
              </div>
              <!-- Reference -->
              <div class="space-y-2">
                <Label class="text-xs font-medium text-muted-foreground">{{ t('reference') }}</Label>
                <Input
                  v-model="form.reference"
                  :placeholder="t('referencePlaceholder')"
                />
              </div>
              <!-- Notes -->
              <div class="space-y-2">
                <Label class="text-xs font-medium text-muted-foreground">{{ t('internalNotes') }}</Label>
                <Textarea
                  v-model="form.notes"
                  :placeholder="t('internalNotesPlaceholder')"
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
                  @click="saveAndPay"
                  :disabled="form.processing || !isValid"
                  size="lg"
                  class="w-full bg-gradient-to-r from-teal-600 to-emerald-600 text-white shadow-md shadow-teal-600/20 hover:from-teal-700 hover:to-emerald-700 disabled:opacity-50"
                >
                  <CreditCard class="mr-2 h-4 w-4" />
                  {{ t('saveAndPayNow') }}
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

    <!-- Quick Add Vendor Modal -->
    <QuickAddModal
      v-model:open="showQuickAdd"
      entity-type="vendor"
      @created="handleQuickAddCreated"
    />
  </PageShell>
</template>
