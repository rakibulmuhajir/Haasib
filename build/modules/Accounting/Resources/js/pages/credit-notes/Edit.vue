<script setup lang="ts">
import { computed } from 'vue'
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
import { ArrowLeft, Save, Receipt } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Customer {
  id: string
  name: string
}

interface Invoice {
  id: string
  customer_id: string
  invoice_number: string
  total_amount: number
  currency: string
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

interface CurrencyRef {
  currency_code: string
  is_base: boolean
}

const props = defineProps<{
  company: CompanyRef
  credit_note: CreditNote
  customers: Customer[]
  invoices: Invoice[]
  currencies: CurrencyRef[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
  { title: props.credit_note.credit_note_number, href: `/${props.company.slug}/credit-notes/${props.credit_note.id}` },
  { title: 'Edit' },
])

const form = useForm({
  customer_id: props.credit_note.customer.id,
  invoice_id: props.credit_note.invoice?.id || 'company_default',
  amount: props.credit_note.amount,
  base_currency: props.credit_note.base_currency,
  reason: props.credit_note.reason,
  status: props.credit_note.status,
  credit_date: props.credit_note.credit_date,
  notes: props.credit_note.notes || '',
  terms: props.credit_note.terms || '',
})

// Filter invoices based on selected customer
const availableInvoices = computed(() => {
  if (!form.customer_id) return []
  return props.invoices.filter(invoice => invoice.customer_id === form.customer_id)
})

const statusOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'issued', label: 'Issued' },
  { value: 'partial', label: 'Partially Applied' },
  { value: 'applied', label: 'Applied' },
  { value: 'void', label: 'Void' },
]

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: form.base_currency || 'USD',
  }).format(amount)
}

const submit = () => {
  // Convert 'company_default' back to null for the backend
  const submitData = { ...form }
  if (submitData.invoice_id === 'company_default') {
    submitData.invoice_id = null
  }

  form.transform((data) => {
    const transformed = { ...data }
    if (transformed.invoice_id === 'company_default') {
      transformed.invoice_id = null
    }
    return transformed
  }).put(`/${props.company.slug}/credit-notes/${props.credit_note.id}`)
}

const isEditable = computed(() => {
  return ['draft', 'issued'].includes(props.credit_note.status)
})
</script>

<template>
  <Head :title="`Edit Credit Note ${credit_note.credit_note_number}`" />

  <PageShell
    :title="`Edit Credit Note`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Cancel
      </Button>
      <Button @click="submit" :disabled="form.processing || !isEditable">
        <Save class="mr-2 h-4 w-4" />
        Save Changes
      </Button>
    </template>

    <!-- Credit Note Header with Clickable Number -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <Receipt class="h-5 w-5 text-muted-foreground" />
              <span class="text-sm font-medium text-muted-foreground">Credit Note</span>
              <Badge :variant="credit_note.status === 'applied' ? 'default' : 'secondary'">
                {{ credit_note.status }}
              </Badge>
            </div>
            <button
              @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}`)"
              class="text-2xl font-bold hover:text-primary transition-colors"
            >
              {{ credit_note.credit_note_number }}
            </button>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold text-green-600">{{ formatCurrency(credit_note.amount) }}</div>
            <div class="text-sm text-muted-foreground">{{ credit_note.base_currency }}</div>
          </div>
        </div>
      </CardContent>
    </Card>

    <div v-if="!isEditable" class="mb-6">
      <Card class="border-yellow-200 bg-yellow-50">
        <CardContent class="pt-6">
          <div class="flex items-center">
            <Badge variant="secondary" class="mr-2">{{ credit_note.status }}</Badge>
            <span class="text-sm">This credit note cannot be edited in its current status.</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Customer Information -->
      <Card>
        <CardHeader>
          <CardTitle>Customer Information</CardTitle>
          <CardDescription>Select the customer for this credit note</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">Customer *</Label>
            <Select v-model="form.customer_id" required :disabled="!isEditable" @update:modelValue="form.invoice_id = 'company_default'">
              <SelectTrigger>
                <SelectValue placeholder="Select a customer" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="customer in customers"
                  :key="customer.id"
                  :value="customer.id"
                >
                  {{ customer.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="invoice_id">Apply to Invoice (Optional)</Label>
            <Select v-model="form.invoice_id" :disabled="!isEditable || !form.customer_id">
              <SelectTrigger>
                <SelectValue placeholder="Select an invoice" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="company_default">No specific invoice</SelectItem>
                <SelectItem
                  v-for="invoice in availableInvoices"
                  :key="invoice.id"
                  :value="invoice.id"
                >
                  {{ invoice.invoice_number }} - {{ formatCurrency(invoice.total_amount) }} {{ invoice.currency }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.customer_id && availableInvoices.length === 0" class="text-sm text-muted-foreground mt-1">
              No invoices found for this customer
            </p>
            <p v-if="!form.customer_id" class="text-sm text-muted-foreground mt-1">
              Select a customer first to see available invoices
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Credit Note Details -->
      <Card>
        <CardHeader>
          <CardTitle>Credit Note Details</CardTitle>
          <CardDescription>Basic information about the credit note</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
              :disabled="!isEditable"
            />
            <p class="text-sm text-muted-foreground mt-1">
              {{ formatCurrency(form.amount) }}
            </p>
          </div>
          <div>
            <Label for="credit_date">Credit Date *</Label>
            <Input
              id="credit_date"
              v-model="form.credit_date"
              type="date"
              required
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="status">Status *</Label>
            <Select v-model="form.status" required :disabled="!isEditable">
              <SelectTrigger>
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="base_currency">Currency *</Label>
            <Select v-model="form.base_currency" required :disabled="!isEditable">
              <SelectTrigger>
                <SelectValue placeholder="Select currency" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="currency in currencies"
                  :key="currency.currency_code"
                  :value="currency.currency_code"
                >
                  {{ currency.currency_code }}{{ currency.is_base ? ' (Base)' : '' }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <!-- Reason -->
      <Card>
        <CardHeader>
          <CardTitle>Reason for Credit</CardTitle>
          <CardDescription>Explain why this credit note is being issued</CardDescription>
        </CardHeader>
        <CardContent>
          <div>
            <Label for="reason">Reason *</Label>
            <Input
              id="reason"
              v-model="form.reason"
              placeholder="e.g., Product return, Service discount, Billing error"
              required
              :disabled="!isEditable"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Additional Information -->
      <Card>
        <CardHeader>
          <CardTitle>Additional Information</CardTitle>
          <CardDescription>Optional terms and notes</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="terms">Terms</Label>
            <Textarea
              id="terms"
              v-model="form.terms"
              placeholder="Credit note terms and conditions..."
              rows="3"
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="notes">Internal Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Internal notes about this credit note..."
              rows="3"
              :disabled="!isEditable"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Summary -->
      <Card>
        <CardHeader>
          <CardTitle>Credit Note Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="flex justify-between">
            <span>Credit Amount:</span>
            <span class="font-bold text-green-600">{{ formatCurrency(form.amount) }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Customer:</span>
            <span>{{ customers.find(c => c.id === form.customer_id)?.name || credit_note.customer.name }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Status:</span>
            <span>{{ statusOptions.find(s => s.value === form.status)?.label }}</span>
          </div>
          <div v-if="form.reason" class="flex justify-between text-sm text-muted-foreground">
            <span>Reason:</span>
            <span>{{ form.reason }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Applied to:</span>
            <span v-if="form.invoice_id === 'company_default'">No specific invoice</span>
            <span v-else>{{ availableInvoices.find(i => i.id === form.invoice_id)?.invoice_number || credit_note.invoice?.invoice_number || 'Unknown invoice' }}</span>
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>