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
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Save, Receipt } from 'lucide-vue-next'

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
  total_amount: number
  currency: string
}

interface CurrencyRef {
  currency_code: string
  is_base: boolean
}

const props = defineProps<{
  company: CompanyRef
  customers: Array<{ id: string; name: string }>
  invoices: InvoiceRef[]
  currencies: CurrencyRef[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
  { title: 'Create' },
])

const form = useForm({
  customer_id: '',
  invoice_id: '',
  amount: 0,
  base_currency: props.company.base_currency,
  reason: '',
  status: 'draft',
  credit_date: new Date().toISOString().split('T')[0],
  notes: '',
  terms: '',
})

const statusOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'issued', label: 'Issued' },
]

// Filter invoices by selected customer
const customerInvoices = computed(() => {
  if (!form.customer_id) return []
  return props.invoices.filter(inv => inv.customer_id === form.customer_id)
})

// When customer changes, reset invoice selection
watch(() => form.customer_id, () => {
  form.invoice_id = ''
})

const formatCurrency = (amount: number, currencyCode?: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currencyCode || form.base_currency || 'USD',
  }).format(amount)
}

const submit = () => {
  form.post(`/${props.company.slug}/credit-notes`)
}
</script>

<template>
  <Head title="Create Credit Note" />

  <PageShell
    title="Create Credit Note"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/credit-notes`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="submit" :disabled="form.processing">
        <Save class="mr-2 h-4 w-4" />
        Create Credit Note
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

      <!-- Customer Information -->
      <Card>
        <CardHeader>
          <CardTitle>Customer Information</CardTitle>
          <CardDescription>Select the customer for this credit note</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">Customer *</Label>
            <Select v-model="form.customer_id" required>
              <SelectTrigger :class="{ 'border-destructive': form.errors.customer_id }">
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
            <p v-if="form.errors.customer_id" class="text-sm text-destructive mt-1">{{ form.errors.customer_id }}</p>
          </div>
          <div>
            <Label for="invoice_id">Apply to Invoice (Optional)</Label>
            <Select v-model="form.invoice_id" :disabled="!form.customer_id">
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
                    {{ invoice.invoice_number }} - {{ formatCurrency(invoice.total_amount, invoice.currency) }}
                  </SelectItem>
                </template>
                <template v-else>
                  <SelectItem value="none" disabled>
                    {{ form.customer_id ? 'No invoices for this customer' : 'Select a customer first' }}
                  </SelectItem>
                </template>
              </SelectContent>
            </Select>
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
              :class="{ 'border-destructive': form.errors.amount }"
            />
            <p v-if="form.errors.amount" class="text-sm text-destructive mt-1">{{ form.errors.amount }}</p>
            <p v-else class="text-sm text-muted-foreground mt-1">
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
            />
          </div>
          <div>
            <Label for="status">Status *</Label>
            <Select v-model="form.status" required>
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
            <Select v-model="form.base_currency" required>
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
              :class="{ 'border-destructive': form.errors.reason }"
            />
            <p v-if="form.errors.reason" class="text-sm text-destructive mt-1">{{ form.errors.reason }}</p>
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
            />
          </div>
          <div>
            <Label for="notes">Internal Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Internal notes about this credit note..."
              rows="3"
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
            <span class="font-bold">{{ formatCurrency(form.amount) }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Customer:</span>
            <span>{{ form.customer_id ? 'Selected' : 'Not selected' }}</span>
          </div>
          <div class="flex justify-between text-sm text-muted-foreground">
            <span>Status:</span>
            <span>{{ statusOptions.find(s => s.value === form.status)?.label }}</span>
          </div>
          <div v-if="form.reason" class="flex justify-between text-sm text-muted-foreground">
            <span>Reason:</span>
            <span>{{ form.reason }}</span>
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>