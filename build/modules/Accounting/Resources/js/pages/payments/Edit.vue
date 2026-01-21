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
import { ArrowLeft, Save, DollarSign, CreditCard, Building, FileText } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface PaymentAllocation {
  id: string
  invoice_id: string
  amount: number
}

interface Customer {
  id: string
  name: string
}

interface Payment {
  id: string
  payment_number: string
  customer: Customer
  amount: number
  currency: string
  payment_method: string
  reference_number?: string
  payment_date: string
  notes?: string
  payment_allocations: PaymentAllocation[]
}

const props = defineProps<{
  company: CompanyRef
  payment: Payment
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Payments', href: `/${props.company.slug}/payments` },
  { title: props.payment.payment_number, href: `/${props.company.slug}/payments/${props.payment.id}` },
  { title: 'Edit' },
])

const form = useForm({
  customer_id: props.payment.customer.id,
  amount: props.payment.amount,
  currency: props.payment.currency,
  payment_method: props.payment.payment_method,
  reference_number: props.payment.reference_number || '',
  payment_date: props.payment.payment_date,
  notes: props.payment.notes || '',
})

const paymentMethods = [
  { value: 'cash', label: 'Cash', icon: DollarSign },
  { value: 'bank_transfer', label: 'Bank Transfer', icon: Building },
  { value: 'card', label: 'Card', icon: CreditCard },
  { value: 'cheque', label: 'Cheque', icon: FileText },
  { value: 'other', label: 'Other', icon: DollarSign },
]

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: form.currency || 'USD',
  }).format(amount)
}

const submit = () => {
  form.put(`/${props.company.slug}/payments/${props.payment.id}`)
}

const allocatedAmount = computed(() => {
  return props.payment.payment_allocations.reduce((sum, allocation) => sum + allocation.amount, 0)
})
</script>

<template>
  <Head :title="`Edit Payment ${payment.payment_number}`" />

  <PageShell
    :title="`Edit Payment ${payment.payment_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payments/${payment.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Cancel
      </Button>
      <Button @click="submit" :disabled="form.processing">
        <Save class="mr-2 h-4 w-4" />
        Save Changes
      </Button>
    </template>

    <div v-if="allocatedAmount > 0" class="mb-6">
      <Card class="border-yellow-200 bg-yellow-50">
        <CardContent class="pt-6">
          <div class="flex items-center">
            <Badge variant="secondary" class="mr-2">Allocated</Badge>
            <span class="text-sm">
              This payment has {{ payment.payment_allocations.length }} allocation(s) totaling {{ formatCurrency(allocatedAmount) }}.
              Editing may affect the allocations.
            </span>
          </div>
        </CardContent>
      </Card>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Payment Information -->
      <Card>
        <CardHeader>
          <CardTitle>Payment Information</CardTitle>
          <CardDescription>Update the basic payment details</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label for="customer_id">Customer *</Label>
            <Select v-model="form.customer_id" required>
              <SelectTrigger>
                <SelectValue placeholder="Select a customer" />
              </SelectTrigger>
              <SelectContent>
                <!-- This would be populated from API -->
                <SelectItem :value="payment.customer.id">{{ payment.customer.name }}</SelectItem>
              </SelectContent>
            </Select>
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
            <Label for="currency">Currency *</Label>
            <Select v-model="form.currency" required>
              <SelectTrigger>
                <SelectValue placeholder="Select currency" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="USD">USD - US Dollar</SelectItem>
                <SelectItem value="EUR">EUR - Euro</SelectItem>
                <SelectItem value="GBP">GBP - British Pound</SelectItem>
                <SelectItem value="JPY">JPY - Japanese Yen</SelectItem>
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

      <!-- Allocations Summary -->
      <Card v-if="payment.payment_allocations.length > 0">
        <CardHeader>
          <CardTitle>Current Allocations</CardTitle>
          <CardDescription>This payment is currently allocated to the following invoices</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-2">
            <div v-for="allocation in payment.payment_allocations" :key="allocation.id" class="flex justify-between items-center p-3 border rounded">
              <span class="font-medium">Invoice #{{ allocation.invoice_id }}</span>
              <span class="font-medium">{{ formatCurrency(allocation.amount) }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t">
              <span class="font-bold">Total Allocated:</span>
              <span class="font-bold">{{ formatCurrency(allocatedAmount) }}</span>
            </div>
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