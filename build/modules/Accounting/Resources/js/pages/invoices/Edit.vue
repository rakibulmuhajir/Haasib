<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
import { Plus, ArrowLeft, Save, Trash2 } from 'lucide-vue-next'
import { useLexicon } from '@/composables/useLexicon'

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
  discount_amount?: number
}

interface Customer {
  id: string
  name: string
}

interface Invoice {
  id: string
  invoice_number: string
  customer: Customer
  status: string
  currency: string
  invoice_date: string
  due_date: string
  description?: string
  reference?: string
  payment_terms?: number
  notes?: string
  ar_account_id?: string | null
  line_items: LineItem[]
}

interface AccountOption {
  id: string
  code: string
  name: string
}

const props = defineProps<{
  company: CompanyRef
  invoice: Invoice
  revenueAccounts?: AccountOption[]
  arAccounts?: AccountOption[]
}>()

const { t } = useLexicon()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: t('dashboard'), href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: t('invoices'), href: `/${props.company.slug}/invoices` },
  { title: props.invoice.invoice_number, href: `/${props.company.slug}/invoices/${props.invoice.id}` },
  { title: t('edit') },
])

const lineItems = ref<LineItem[]>([
  ...props.invoice.line_items.map(item => ({ ...item }))
])

// Add empty line item if none exist
if (lineItems.value.length === 0) {
  lineItems.value.push({ description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount_amount: 0 })
}

const form = useForm({
  customer_id: props.invoice.customer.id,
  line_items: lineItems.value,
  ar_account_id: props.invoice.ar_account_id || '',
  currency: props.invoice.currency,
  invoice_date: props.invoice.invoice_date,
  due_date: props.invoice.due_date,
  description: props.invoice.description || '',
  reference: props.invoice.reference || '',
  payment_terms: props.invoice.payment_terms || 30,
  notes: props.invoice.notes || '',
})

// Watch line items and update form
watch(lineItems, (newItems) => {
  form.line_items = newItems
}, { deep: true })

const subtotal = computed(() => {
  return lineItems.value.reduce((sum, item) => {
    const itemTotal = item.quantity * item.unit_price
    const discount = item.discount_amount || 0
    return sum + (itemTotal - discount)
  }, 0)
})

const taxAmount = computed(() => {
  return lineItems.value.reduce((sum, item) => {
    const itemTotal = item.quantity * item.unit_price
    const discount = item.discount_amount || 0
    const taxableAmount = itemTotal - discount
    const tax = (item.tax_rate || 0) / 100
    return sum + (taxableAmount * tax)
  }, 0)
})

const totalAmount = computed(() => subtotal.value + taxAmount.value)

const addLineItem = () => {
  lineItems.value.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
    discount_amount: 0
  })
}

const removeLineItem = (index: number) => {
  lineItems.value.splice(index, 1)
}

const updateLineItem = (index: number, field: keyof LineItem, value: any) => {
  lineItems.value[index][field] = value
}

const submit = () => {
  form.put(`/${props.company.slug}/invoices/${props.invoice.id}`)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: form.currency || 'USD',
  }).format(amount)
}

const isEditable = computed(() => {
  return props.invoice.status !== 'paid' && props.invoice.status !== 'cancelled'
})
</script>

<template>
  <Head :title="`${t('edit')} ${t('invoices')} ${invoice.invoice_number}`" />

  <PageShell
    :title="`${t('edit')} ${t('invoices')} ${invoice.invoice_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices/${invoice.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        {{ t('cancel') }}
      </Button>
      <Button @click="submit" :disabled="form.processing || !isEditable">
        <Save class="mr-2 h-4 w-4" />
        {{ t('saveChanges') }}
      </Button>
    </template>

    <div v-if="!isEditable" class="mb-6">
      <Card class="border-yellow-200 bg-yellow-50">
        <CardContent class="pt-6">
          <div class="flex items-center">
            <Badge variant="secondary" class="mr-2">{{ invoice.status }}</Badge>
            <span class="text-sm">{{ t('invoiceLocked') }}</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Customer Information -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('customerInformation') }}</CardTitle>
          <CardDescription>{{ t('selectCustomerForInvoice') }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">{{ t('customer') }} *</Label>
            <Select v-model="form.customer_id" required :disabled="!isEditable">
              <SelectTrigger>
                <SelectValue placeholder="Select a customer" />
              </SelectTrigger>
              <SelectContent>
                <!-- This would be populated from API -->
                <SelectItem :value="invoice.customer.id">{{ invoice.customer.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label for="ar_account_id">{{ t('arAccount') }}</Label>
            <Select v-model="form.ar_account_id" :disabled="!isEditable">
              <SelectTrigger id="ar_account_id">
                <SelectValue :placeholder="t('useCompanyDefault')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">{{ t('useCompanyDefault') }}</SelectItem>
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
        </CardContent>
      </Card>

      <!-- Invoice Details -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('invoiceDetails') }}</CardTitle>
          <CardDescription>{{ t('invoiceDetailsHelper') }}</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label for="invoice_date">{{ t('invoiceDate') }} *</Label>
            <Input
              id="invoice_date"
              v-model="form.invoice_date"
              type="date"
              required
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="due_date">{{ t('dueDate') }}</Label>
            <Input
              id="due_date"
              v-model="form.due_date"
              type="date"
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="reference">{{ t('reference') }}</Label>
            <Input
              id="reference"
              v-model="form.reference"
              placeholder="PO number or reference"
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="payment_terms">{{ t('paymentTerms') }} ({{ t('days') }})</Label>
            <Input
              id="payment_terms"
              v-model.number="form.payment_terms"
              type="number"
              min="0"
              max="365"
              :disabled="!isEditable"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('items') }}</CardTitle>
          <CardDescription>{{ t('lineItemsHelper') }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-12 gap-2 text-sm text-muted-foreground font-medium">
            <div class="col-span-5">{{ t('description') }}</div>
            <div class="col-span-2">{{ t('quantity') }}</div>
            <div class="col-span-2">{{ t('unitPrice') }}</div>
            <div class="col-span-2">{{ t('incomeAccount') }}</div>
            <div class="col-span-1">{{ t('total') }}</div>
          </div>

          <div v-for="(item, index) in lineItems" :key="index" class="grid grid-cols-12 gap-2">
            <div class="col-span-5">
              <Input
                v-model="item.description"
                placeholder="Item description"
                @input="updateLineItem(index, 'description', $event.target.value)"
                required
                :disabled="!isEditable"
              />
            </div>
            <div class="col-span-2">
              <Input
                v-model.number="item.quantity"
                type="number"
                min="0.01"
                step="0.01"
                @input="updateLineItem(index, 'quantity', parseFloat($event.target.value) || 0)"
                required
                :disabled="!isEditable"
              />
            </div>
            <div class="col-span-2">
              <Input
                v-model.number="item.unit_price"
                type="number"
                min="0"
                step="0.01"
                @input="updateLineItem(index, 'unit_price', parseFloat($event.target.value) || 0)"
                required
                :disabled="!isEditable"
              />
            </div>
            <div class="col-span-2">
              <Select v-model="item.income_account_id" :disabled="!isEditable">
                <SelectTrigger>
                  <SelectValue placeholder="Income acct" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">Use default</SelectItem>
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
            <div class="col-span-2 flex items-center text-sm">
              {{ formatCurrency(item.quantity * item.unit_price) }}
            </div>
          </div>

          <Button type="button" variant="outline" @click="addLineItem" class="w-full" :disabled="!isEditable">
            <Plus class="mr-2 h-4 w-4" />
            {{ t('addLineItem') }}
          </Button>
        </CardContent>
      </Card>

      <!-- Summary -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('amountSummary') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
          <div class="flex justify-between text-sm">
            <span>{{ t('subtotal') }}:</span>
            <span>{{ formatCurrency(subtotal) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span>{{ t('tax') }}:</span>
            <span>{{ formatCurrency(taxAmount) }}</span>
          </div>
          <div class="flex justify-between text-lg font-bold">
            <span>{{ t('total') }}:</span>
            <span>{{ formatCurrency(totalAmount) }}</span>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('additionalInformation') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="description">{{ t('description') }}</Label>
            <Textarea
              id="description"
              v-model="form.description"
              placeholder="Invoice description or internal notes"
              rows="3"
              :disabled="!isEditable"
            />
          </div>
          <div>
            <Label for="notes">{{ t('customerNotes') }}</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Notes that will be visible to the customer"
              rows="3"
              :disabled="!isEditable"
            />
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>
