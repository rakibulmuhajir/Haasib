<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Save, DollarSign, CreditCard, Building, Smartphone, FileText } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface InvoiceRef {
  id: string
  customer_id: string
  invoice_number: string
  balance: number
  currency: string
}

interface CurrencyRef {
  currency_code: string
  is_base: boolean
}

interface AccountOption {
  id: string
  code: string
  name: string
  subtype?: string
}

const props = defineProps<{
  company: CompanyRef
  customers: Array<{ id: string; name: string }>
  invoices: InvoiceRef[]
  currencies: CurrencyRef[]
  depositAccounts?: AccountOption[]
  arAccounts?: AccountOption[]
  preselect?: {
    customer_id?: string | null
    invoice_id?: string | null
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Payments', href: `/${props.company.slug}/payments` },
  { title: 'Record Payment' },
])

// Get preselected invoice details for initial amount
const preselectedInvoice = props.preselect?.invoice_id
  ? props.invoices.find(inv => inv.id === props.preselect?.invoice_id)
  : null

const form = useForm({
  customer_id: props.preselect?.customer_id || '',
  invoice_id: props.preselect?.invoice_id || '',
  amount: preselectedInvoice?.balance || 0,
  currency: preselectedInvoice?.currency || props.company.base_currency,
  payment_method: 'bank_transfer',
  reference_number: '',
  payment_date: new Date().toISOString().split('T')[0],
  notes: '',
  deposit_account_id: '',
  ar_account_id: 'company_default',
})

const paymentMethods = [
  { value: 'cash', label: 'Cash', icon: DollarSign },
  { value: 'bank_transfer', label: 'Bank Transfer', icon: Building },
  { value: 'card', label: 'Card', icon: CreditCard },
  { value: 'cheque', label: 'Cheque', icon: FileText },
  { value: 'other', label: 'Other', icon: DollarSign },
]

// Filter invoices by selected customer
const customerInvoices = computed(() => {
  if (!form.customer_id) return []
  return props.invoices.filter(inv => inv.customer_id === form.customer_id)
})

// Selected invoice details
const selectedInvoice = computed(() => {
  if (!form.invoice_id) return null
  return props.invoices.find(inv => inv.id === form.invoice_id)
})

// When customer changes, reset invoice selection
watch(() => form.customer_id, () => {
  form.invoice_id = ''
  // Auto-select first invoice if only one available
  if (customerInvoices.value.length === 1) {
    form.invoice_id = customerInvoices.value[0].id
    form.currency = customerInvoices.value[0].currency
  }
})

// When invoice is selected, set currency and max amount
watch(() => form.invoice_id, () => {
  if (selectedInvoice.value) {
    form.currency = selectedInvoice.value.currency
    if (form.amount === 0) {
      form.amount = selectedInvoice.value.balance
    }
  }
})

const formatCurrency = (amount: number, currencyCode?: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currencyCode || form.currency || 'USD',
  }).format(amount)
}

const submit = () => {
  form.post(`/${props.company.slug}/payments`)
}
</script>

<template>
  <Head title="Record Payment" />

  <PageShell
    title="Record Payment"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payments`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="submit" :disabled="form.processing">
        <Save class="mr-2 h-4 w-4" />
        Record Payment
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- General Errors -->
      <div v-if="Object.keys(form.errors).length > 0" class="rounded-md bg-destructive/15 p-4">
        <div class="text-sm text-destructive">
          <p class="font-medium">Please fix the following errors:</p>
          <ul class="list-disc list-inside mt-2">
            <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
          </ul>
        </div>
      </div>

      <!-- Payment Information -->
      <Card>
        <CardHeader>
          <CardTitle>Payment Information</CardTitle>
          <CardDescription>Enter the basic payment details</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label for="customer_id">Customer *</Label>
            <Select v-model="form.customer_id" required>
              <SelectTrigger>
                <SelectValue placeholder="Select a customer" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="customer in customers.filter(c => c.id && c.id !== '')"
                  :key="customer.id"
                  :value="customer.id"
                >
                  {{ customer.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="ar_account_id">AR Account</Label>
            <Select v-model="form.ar_account_id">
              <SelectTrigger id="ar_account_id">
                <SelectValue placeholder="Use company default" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="company_default">Use company default</SelectItem>
                <SelectItem
                  v-for="acct in props.arAccounts || []"
                  :key="acct.id"
                  :value="acct.id"
                >
                  {{ acct.code }} — {{ acct.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="invoice_id">Apply to Invoice *</Label>
            <Select v-model="form.invoice_id" :disabled="!form.customer_id" required>
              <SelectTrigger>
                <SelectValue :placeholder="form.customer_id ? 'Select an invoice' : 'Select a customer first'" />
              </SelectTrigger>
              <SelectContent>
                <template v-if="customerInvoices.length > 0">
                  <SelectItem
                    v-for="invoice in customerInvoices"
                    :key="invoice.id"
                    :value="invoice.id"
                  >
                    {{ invoice.invoice_number }} - {{ formatCurrency(invoice.balance, invoice.currency) }} due
                  </SelectItem>
                </template>
                <template v-else>
                  <SelectItem value="none" disabled>
                    {{ form.customer_id ? 'No unpaid invoices' : 'Select a customer first' }}
                  </SelectItem>
                </template>
              </SelectContent>
            </Select>
            <p v-if="form.errors.invoice_id" class="text-sm text-destructive mt-1">{{ form.errors.invoice_id }}</p>
          </div>
          <div>
            <Label for="amount">Amount *</Label>
            <Input
              id="amount"
              v-model.number="form.amount"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="0.00"
              required
            />
            <p class="text-sm text-muted-foreground mt-1">
              {{ formatCurrency(form.amount) }}
            </p>
          </div>
          <div>
            <Label for="payment_date">Payment Date *</Label>
            <Input
              id="payment_date"
              v-model="form.payment_date"
              type="date"
              required
            />
          </div>
          <div>
            <Label for="deposit_account_id">Deposit To *</Label>
            <Select v-model="form.deposit_account_id" required>
              <SelectTrigger id="deposit_account_id">
                <SelectValue placeholder="Select bank/cash account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="acct in props.depositAccounts || []"
                  :key="acct.id"
                  :value="acct.id"
                >
                  {{ acct.code }} — {{ acct.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="currency">Currency *</Label>
            <Select v-model="form.currency" required>
              <SelectTrigger>
                <SelectValue placeholder="Select currency" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="curr in currencies"
                  :key="curr.currency_code"
                  :value="curr.currency_code"
                >
                  {{ curr.currency_code }}{{ curr.is_base ? ' (Base)' : '' }}
                </SelectItem>
                <SelectItem v-if="currencies.length === 0" value="USD">USD</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="reference_number">Reference Number</Label>
            <Input
              id="reference_number"
              v-model="form.reference_number"
              placeholder="Check #, transaction ID, etc."
            />
          </div>
        </CardContent>
      </Card>

      <!-- Payment Method -->
      <Card>
        <CardHeader>
          <CardTitle>Payment Method</CardTitle>
          <CardDescription>How was this payment made?</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div
              v-for="method in paymentMethods"
              :key="method.value"
              class="relative"
            >
              <input
                :id="method.value"
                v-model="form.payment_method"
                :value="method.value"
                type="radio"
                class="peer sr-only"
              />
              <label
                :for="method.value"
                class="flex flex-col items-center justify-center rounded-lg border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground cursor-pointer"
              >
                <component :is="method.icon" class="h-6 w-6 mb-2" />
                <span class="text-sm font-medium">{{ method.label }}</span>
              </label>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Additional Information</CardTitle>
          <CardDescription>Any additional notes about this payment</CardDescription>
        </CardHeader>
        <CardContent>
          <div>
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Additional payment notes..."
              rows="3"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Summary -->
      <Card>
        <CardHeader>
          <CardTitle>Payment Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="flex justify-between">
            <span>Payment Amount:</span>
            <span class="font-bold">{{ formatCurrency(form.amount) }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Payment Method:</span>
            <span>{{ paymentMethods.find(m => m.value === form.payment_method)?.label }}</span>
          </div>
          <div v-if="form.reference_number" class="flex justify-between text-sm text-muted-foreground">
            <span>Reference:</span>
            <span>{{ form.reference_number }}</span>
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>
