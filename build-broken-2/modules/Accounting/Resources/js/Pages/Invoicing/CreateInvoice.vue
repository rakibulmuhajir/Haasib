<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import CurrencySelector from '@/components/currency/CurrencySelector.vue'
import { Trash2, Plus } from 'lucide-vue-next'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', href: '/accounting' },
  { label: 'Invoices', href: '/accounting/invoices' },
  { label: 'Create Invoice', active: true },
]

interface Customer {
  id: string
  display_name: string
  name: string
  customer_number: string
  email?: string
  preferred_currency_code?: string
}

interface Currency {
  code: string
  name: string
  symbol: string
  display_name: string
  is_base: boolean
}

interface LineItem {
  description: string
  quantity: number
  unit_price: number
  total: number
}

const props = defineProps<{
  customers: Customer[]
  currencies: Currency[]
  baseCurrency?: Currency
  isMultiCurrencyEnabled?: boolean
}>()

// Form state
const form = useForm({
  customer_id: '',
  issue_date: new Date().toISOString().split('T')[0],
  due_date: '',
  currency_code: props.baseCurrency?.code || '',
  exchange_rate: 1.0,
  line_items: [
    { description: '', quantity: 1, unit_price: 0, total: 0 }
  ] as LineItem[],
  subtotal: 0,
  discount_amount: 0,
  shipping_amount: 0,
  total_amount: 0,
  notes: '',
})

// Selected customer and currency
const selectedCustomer = computed(() => {
  return props.customers.find(c => c.id === form.customer_id) || null
})

const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.code === form.currency_code) || null
})

// Auto-calculate totals
const calculateLineItemTotal = (index: number) => {
  const item = form.line_items[index]
  item.total = Number((item.quantity * item.unit_price).toFixed(2))
  calculateTotals()
}

const calculateTotals = () => {
  const subtotal = form.line_items.reduce((sum, item) => sum + item.total, 0)
  form.subtotal = Number(subtotal.toFixed(2))
  form.total_amount = Number((subtotal - form.discount_amount + form.shipping_amount).toFixed(2))
}

// Line item management
const addLineItem = () => {
  form.line_items.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    total: 0
  })
}

const removeLineItem = (index: number) => {
  if (form.line_items.length > 1) {
    form.line_items.splice(index, 1)
    calculateTotals()
  }
}

// Customer change handler
const onCustomerChange = (customerId: string) => {
  const customer = props.customers.find(c => c.id === customerId)
  if (customer?.preferred_currency_code) {
    form.currency_code = customer.preferred_currency_code
  }
}

// Currency change handler
const onCurrencyChange = (currencyCode: string) => {
  // Update exchange rate when currency changes
  if (currencyCode !== props.baseCurrency?.code) {
    // In real implementation, fetch exchange rate from API
    form.exchange_rate = 1.0 // Placeholder
  } else {
    form.exchange_rate = 1.0
  }
}

// Set default due date (30 days from issue date)
watch(() => form.issue_date, (newDate) => {
  if (newDate) {
    const issueDate = new Date(newDate)
    const dueDate = new Date(issueDate)
    dueDate.setDate(dueDate.getDate() + 30)
    form.due_date = dueDate.toISOString().split('T')[0]
  }
}, { immediate: true })

// Watch for discount and shipping changes
watch([() => form.discount_amount, () => form.shipping_amount], calculateTotals)

const submit = () => {
  form.post('/accounting/invoices', {
    onSuccess: () => {
      // Redirect handled by controller
    },
  })
}
</script>

<template>
  <Head title="Create Invoice" />
  <UniversalLayout
    title="Create Invoice"
    subtitle="Create a new customer invoice"
    :breadcrumbs="breadcrumbs"
  >
    <form @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Invoice Details -->
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>Invoice Details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Customer Selection -->
              <div class="space-y-2">
                <Label for="customer">Customer *</Label>
                <Select 
                  v-model="form.customer_id" 
                  @update:model-value="onCustomerChange"
                  required
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select customer" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="customer in customers"
                      :key="customer.id"
                      :value="customer.id"
                    >
                      {{ customer.display_name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-if="form.errors.customer_id" class="text-sm text-destructive">
                  {{ form.errors.customer_id }}
                </div>
              </div>

              <!-- Currency Selection (only if multi-currency enabled) -->
              <div v-if="isMultiCurrencyEnabled" class="space-y-2">
                <Label for="currency">Currency *</Label>
                <CurrencySelector
                  v-model="form.currency_code"
                  :currencies="currencies"
                  @change="onCurrencyChange"
                  placeholder="Select currency"
                />
                <div v-if="form.errors.currency_code" class="text-sm text-destructive">
                  {{ form.errors.currency_code }}
                </div>
              </div>

              <!-- Issue Date -->
              <div class="space-y-2">
                <Label for="issue_date">Issue Date *</Label>
                <Input
                  id="issue_date"
                  v-model="form.issue_date"
                  type="date"
                  required
                />
                <div v-if="form.errors.issue_date" class="text-sm text-destructive">
                  {{ form.errors.issue_date }}
                </div>
              </div>

              <!-- Due Date -->
              <div class="space-y-2">
                <Label for="due_date">Due Date *</Label>
                <Input
                  id="due_date"
                  v-model="form.due_date"
                  type="date"
                  required
                />
                <div v-if="form.errors.due_date" class="text-sm text-destructive">
                  {{ form.errors.due_date }}
                </div>
              </div>
            </div>

            <!-- Exchange Rate (if not base currency and multi-currency enabled) -->
            <div v-if="isMultiCurrencyEnabled && selectedCurrency && !selectedCurrency.is_base" class="space-y-2">
              <Label for="exchange_rate">
                Exchange Rate ({{ selectedCurrency.code }} to {{ baseCurrency?.code }})
              </Label>
              <Input
                id="exchange_rate"
                v-model.number="form.exchange_rate"
                type="number"
                step="0.000001"
                min="0.000001"
                placeholder="1.000000"
              />
              <div class="text-xs text-muted-foreground">
                1 {{ selectedCurrency.code }} = {{ form.exchange_rate }} {{ baseCurrency?.code }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm">Subtotal:</span>
                <span class="text-sm">{{ selectedCurrency?.symbol || '$' }}{{ form.subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm">Discount:</span>
                <span class="text-sm text-red-600">-{{ selectedCurrency?.symbol || '$' }}{{ form.discount_amount.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm">Shipping:</span>
                <span class="text-sm">{{ selectedCurrency?.symbol || '$' }}{{ form.shipping_amount.toFixed(2) }}</span>
              </div>
              <div class="border-t pt-2">
                <div class="flex justify-between font-semibold">
                  <span>Total:</span>
                  <span>{{ selectedCurrency?.symbol || '$' }}{{ form.total_amount.toFixed(2) }}</span>
                </div>
              </div>
              
              <!-- Base currency conversion -->
              <div v-if="isMultiCurrencyEnabled && selectedCurrency && !selectedCurrency.is_base" class="text-xs text-muted-foreground border-t pt-2">
                Base Currency: {{ baseCurrency?.symbol }}{{ (form.total_amount * form.exchange_rate).toFixed(2) }} {{ baseCurrency?.code }}
              </div>
            </div>

            <div class="space-y-2">
              <Label for="discount_amount">Discount Amount</Label>
              <Input
                id="discount_amount"
                v-model.number="form.discount_amount"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
              />
            </div>

            <div class="space-y-2">
              <Label for="shipping_amount">Shipping Amount</Label>
              <Input
                id="shipping_amount"
                v-model.number="form.shipping_amount"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
              />
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <div class="flex justify-between items-center">
            <CardTitle>Line Items</CardTitle>
            <Button type="button" @click="addLineItem" variant="outline" size="sm">
              <Plus class="w-4 h-4 mr-2" />
              Add Item
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="border rounded-md">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Description *</TableHead>
                  <TableHead class="w-24">Quantity</TableHead>
                  <TableHead class="w-32">Unit Price</TableHead>
                  <TableHead class="w-32">Total</TableHead>
                  <TableHead class="w-16"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(item, index) in form.line_items" :key="index">
                  <TableCell>
                    <Input
                      v-model="item.description"
                      placeholder="Item description"
                      required
                    />
                    <div v-if="form.errors[`line_items.${index}.description`]" class="text-xs text-destructive mt-1">
                      {{ form.errors[`line_items.${index}.description`] }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Input
                      v-model.number="item.quantity"
                      type="number"
                      step="0.01"
                      min="0.01"
                      @input="calculateLineItemTotal(index)"
                      required
                    />
                    <div v-if="form.errors[`line_items.${index}.quantity`]" class="text-xs text-destructive mt-1">
                      {{ form.errors[`line_items.${index}.quantity`] }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Input
                      v-model.number="item.unit_price"
                      type="number"
                      step="0.01"
                      min="0"
                      @input="calculateLineItemTotal(index)"
                      required
                    />
                    <div v-if="form.errors[`line_items.${index}.unit_price`]" class="text-xs text-destructive mt-1">
                      {{ form.errors[`line_items.${index}.unit_price`] }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <span class="text-sm font-medium">
                      {{ selectedCurrency?.symbol || '$' }}{{ item.total.toFixed(2) }}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Button
                      v-if="form.line_items.length > 1"
                      type="button"
                      @click="removeLineItem(index)"
                      variant="ghost"
                      size="sm"
                    >
                      <Trash2 class="w-4 h-4 text-destructive" />
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <div v-if="form.errors.line_items" class="text-sm text-destructive mt-2">
            {{ form.errors.line_items }}
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Additional Notes</CardTitle>
        </CardHeader>
        <CardContent>
          <Textarea
            v-model="form.notes"
            placeholder="Add any additional notes or terms..."
            rows="3"
          />
          <div v-if="form.errors.notes" class="text-sm text-destructive mt-2">
            {{ form.errors.notes }}
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end space-x-4">
        <Button type="button" variant="outline" @click="$inertia.visit('/accounting/invoices')">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Creating...' : 'Create Invoice' }}
        </Button>
      </div>
    </form>
  </UniversalLayout>
</template>