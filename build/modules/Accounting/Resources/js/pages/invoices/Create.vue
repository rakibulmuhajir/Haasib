<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { EntitySearch, QuickAddModal } from '@/components/forms'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { Plus, ArrowLeft, Save, Trash2, DollarSign } from 'lucide-vue-next'

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
  discount_amount?: number
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
  arAccounts?: AccountOption[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
  { title: 'Create' },
])

const lineItems = ref<LineItem[]>([
  { description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount_amount: 0 }
])

const showQuickAdd = ref(false)
const quickAddQuery = ref('')

const form = useForm({
  customer_id: '',
  line_items: lineItems.value,
  ar_account_id: '',
  currency: props.company.base_currency,
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  description: '',
  reference: '',
  payment_terms: 30,
  notes: '',
})

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
  form.line_items = lineItems.value
}

const removeLineItem = (index: number) => {
  lineItems.value.splice(index, 1)
  form.line_items = lineItems.value
}

const updateLineItem = (index: number, field: keyof LineItem, value: any) => {
  lineItems.value[index][field] = value
  form.line_items = lineItems.value
}

const handleQuickAddClick = (query: string) => {
  quickAddQuery.value = query
  showQuickAdd.value = true
}

const handleCustomerCreated = (customer: { id: string }) => {
  form.customer_id = customer.id
  showQuickAdd.value = false
}

const submit = () => {
  form.post(`/${props.company.slug}/invoices`)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: form.currency || 'USD',
  }).format(amount)
}
</script>

<template>
  <Head title="Create Invoice" />

  <PageShell
    title="Create Invoice"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="submit" :disabled="form.processing">
        <Save class="mr-2 h-4 w-4" />
        Create Invoice
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Customer Information -->
      <Card>
        <CardHeader>
          <CardTitle>Customer Information</CardTitle>
          <CardDescription>Select the customer for this invoice</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="customer_id">Customer *</Label>
            <EntitySearch
              v-model="form.customer_id"
              entity-type="customer"
              placeholder="Select or create a customer"
              @quick-add-click="handleQuickAddClick"
            />
            <QuickAddModal
              v-model:open="showQuickAdd"
              entity-type="customer"
              :initial-name="quickAddQuery"
              @created="handleCustomerCreated"
            />
            <p v-if="form.errors.customer_id" class="text-sm text-red-600 dark:text-red-400">
              {{ form.errors.customer_id }}
            </p>
          </div>
          <div>
            <Label for="ar_account_id">AR Account</Label>
            <Select v-model="form.ar_account_id">
              <SelectTrigger id="ar_account_id">
                <SelectValue placeholder="Use company default" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">Use company default</SelectItem>
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
          <CardTitle>Invoice Details</CardTitle>
          <CardDescription>Basic information about the invoice</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label for="invoice_date">Invoice Date *</Label>
            <Input
              id="invoice_date"
              v-model="form.invoice_date"
              type="date"
              required
            />
          </div>
          <div>
            <Label for="due_date">Due Date</Label>
            <Input
              id="due_date"
              v-model="form.due_date"
              type="date"
            />
          </div>
          <div>
            <Label for="reference">Reference</Label>
            <Input
              id="reference"
              v-model="form.reference"
              placeholder="PO number or reference"
            />
          </div>
          <div>
            <Label for="payment_terms">Payment Terms (days)</Label>
            <Input
              id="payment_terms"
              v-model.number="form.payment_terms"
              type="number"
              min="0"
              max="365"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <CardTitle>Line Items</CardTitle>
          <CardDescription>Add products or services to invoice</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-12 gap-2 text-sm text-muted-foreground font-medium">
            <div class="col-span-5">Description</div>
            <div class="col-span-2">Quantity</div>
            <div class="col-span-2">Unit Price</div>
            <div class="col-span-2">Income Account</div>
            <div class="col-span-1">Total</div>
          </div>

          <div v-for="(item, index) in lineItems" :key="index" class="grid grid-cols-12 gap-2">
            <div class="col-span-5">
              <Input
                v-model="item.description"
                placeholder="Item description"
                @input="updateLineItem(index, 'description', $event.target.value)"
                required
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
              />
            </div>
            <div class="col-span-2">
              <Select v-model="item.income_account_id">
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

          <Button type="button" variant="outline" @click="addLineItem" class="w-full">
            <Plus class="mr-2 h-4 w-4" />
            Add Line Item
          </Button>
        </CardContent>
      </Card>

      <!-- Summary -->
      <Card>
        <CardHeader>
          <CardTitle>Invoice Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
          <div class="flex justify-between text-sm">
            <span>Subtotal:</span>
            <span>{{ formatCurrency(subtotal) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span>Tax:</span>
            <span>{{ formatCurrency(taxAmount) }}</span>
          </div>
          <div class="flex justify-between text-lg font-bold">
            <span>Total:</span>
            <span>{{ formatCurrency(totalAmount) }}</span>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Additional Information</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              v-model="form.description"
              placeholder="Invoice description or internal notes"
              rows="3"
            />
          </div>
          <div>
            <Label for="notes">Customer Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Notes that will be visible to the customer"
              rows="3"
            />
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>
