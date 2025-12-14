<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { FileText, Save, Plus, Trash2, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  name: string
  payment_terms?: number
  base_currency?: string
}

interface AccountOption {
  id: string
  code: string
  name: string
  type?: string
  subtype?: string
}

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
  expenseAccounts?: AccountOption[]
  apAccounts?: AccountOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bills', href: `/${props.company.slug}/bills` },
  { title: 'Create' },
]

const lineItemTemplate = () => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
  expense_account_id: 'company_default',
})

const form = useForm({
  vendor_id: '',
  bill_number: '',
  vendor_invoice_number: '',
  bill_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  status: 'draft',
  currency: props.company.base_currency,
  base_currency: props.company.base_currency,
  exchange_rate: null as number | null,
  payment_terms: 30,
  notes: '',
  internal_notes: '',
  ap_account_id: 'company_default',
  line_items: [lineItemTemplate()],
})

// Watch vendor selection to auto-fill payment terms
watch(() => form.vendor_id, (newVendorId) => {
  const vendor = props.vendors.find(v => v.id === newVendorId)
  if (vendor?.payment_terms) {
    form.payment_terms = vendor.payment_terms
  }
})

// Auto-calculate due date when bill_date or payment_terms changes
watch([() => form.bill_date, () => form.payment_terms], () => {
  if (form.bill_date && form.payment_terms) {
    const billDate = new Date(form.bill_date)
    billDate.setDate(billDate.getDate() + form.payment_terms)
    form.due_date = billDate.toISOString().slice(0, 10)
  }
})

const totals = computed(() => {
  const subtotal = form.line_items.reduce((sum, li) => sum + (Number(li.quantity) || 0) * (Number(li.unit_price) || 0), 0)
  const tax = form.line_items.reduce((sum, li) => {
    const lineTotal = (Number(li.quantity) || 0) * (Number(li.unit_price) || 0)
    return sum + lineTotal * ((Number(li.tax_rate) || 0) / 100)
  }, 0)
  const discount = form.line_items.reduce((sum, li) => {
    const lineTotal = (Number(li.quantity) || 0) * (Number(li.unit_price) || 0)
    return sum + lineTotal * ((Number(li.discount_rate) || 0) / 100)
  }, 0)
  const total = subtotal + tax - discount
  return { subtotal, tax, discount, total }
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: form.currency || 'USD',
  }).format(amount)
}

const addLine = () => form.line_items.push(lineItemTemplate())
const removeLine = (idx: number) => {
  if (form.line_items.length > 1) {
    form.line_items.splice(idx, 1)
  }
}

const handleSubmit = () => {
  // Prepare data - convert 'company_default' to null for backend
  const data = {
    ...form.data(),
    ap_account_id: form.ap_account_id === 'company_default' ? null : form.ap_account_id,
    line_items: form.line_items.map(item => ({
      ...item,
      expense_account_id: item.expense_account_id === 'company_default' ? null : item.expense_account_id,
    })),
  }

  form.transform(() => data).post(`/${props.company.slug}/bills`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Create Bill" />
  <PageShell
    title="Create Bill"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/bills`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="handleSubmit" :disabled="form.processing">
        <Save class="mr-2 h-4 w-4" />
        Save Bill
      </Button>
    </template>

    <form class="space-y-6" @submit.prevent="handleSubmit">
      <!-- General Errors -->
      <div v-if="Object.keys(form.errors).length > 0" class="rounded-md bg-destructive/15 p-4">
        <div class="text-sm text-destructive">
          <p class="font-medium">Please fix the following errors:</p>
          <ul class="list-disc list-inside mt-2">
            <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
          </ul>
        </div>
      </div>

      <!-- Bill Information -->
      <Card>
        <CardHeader>
          <CardTitle>Bill Information</CardTitle>
          <CardDescription>Enter the basic bill details</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label for="vendor_id">Vendor *</Label>
            <Select v-model="form.vendor_id" required>
              <SelectTrigger id="vendor_id">
                <SelectValue placeholder="Select a vendor" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="v in vendors"
                  :key="v.id"
                  :value="v.id"
                >
                  {{ v.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.vendor_id" class="text-sm text-destructive mt-1">{{ form.errors.vendor_id }}</p>
          </div>

          <div>
            <Label for="bill_number">Bill Number</Label>
            <Input
              id="bill_number"
              v-model="form.bill_number"
              placeholder="Auto-generated if left blank"
            />
            <p class="text-xs text-muted-foreground mt-1">Leave blank to auto-generate</p>
            <p v-if="form.errors.bill_number" class="text-sm text-destructive mt-1">{{ form.errors.bill_number }}</p>
          </div>

          <div>
            <Label for="vendor_invoice_number">Vendor Invoice Number</Label>
            <Input
              id="vendor_invoice_number"
              v-model="form.vendor_invoice_number"
              placeholder="Vendor's invoice #"
            />
          </div>

          <div>
            <Label for="bill_date">Bill Date *</Label>
            <Input id="bill_date" v-model="form.bill_date" type="date" required />
          </div>

          <div>
            <Label for="payment_terms">Payment Terms (days) *</Label>
            <Input
              id="payment_terms"
              v-model.number="form.payment_terms"
              type="number"
              min="0"
              max="365"
              required
            />
          </div>

          <div>
            <Label for="due_date">Due Date</Label>
            <Input id="due_date" v-model="form.due_date" type="date" />
            <p class="text-xs text-muted-foreground mt-1">Auto-calculated from payment terms</p>
          </div>

          <div>
            <Label for="currency">Currency *</Label>
            <Input
              id="currency"
              v-model="form.currency"
              maxlength="3"
              placeholder="USD"
              required
            />
          </div>

          <div>
            <Label for="ap_account_id">AP Account</Label>
            <Select v-model="form.ap_account_id">
              <SelectTrigger id="ap_account_id">
                <SelectValue placeholder="Use company default" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="company_default">Use company default</SelectItem>
                <SelectItem
                  v-for="acct in props.apAccounts || []"
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

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>Line Items</CardTitle>
              <CardDescription>Add items to this bill</CardDescription>
            </div>
            <Button type="button" variant="outline" @click="addLine">
              <Plus class="mr-2 h-4 w-4" />
              Add Line
            </Button>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="grid gap-3 rounded-lg border p-4 md:grid-cols-7"
          >
            <div class="md:col-span-2">
              <Label>Description *</Label>
              <Input v-model="line.description" placeholder="Item description" required />
            </div>
            <div>
              <Label>Quantity *</Label>
              <Input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required />
            </div>
            <div>
              <Label>Unit Price *</Label>
              <Input v-model.number="line.unit_price" type="number" min="0" step="0.01" required />
            </div>
            <div>
              <Label>Tax %</Label>
              <Input v-model.number="line.tax_rate" type="number" min="0" max="100" step="0.01" placeholder="0" />
            </div>
            <div>
              <Label>Discount %</Label>
              <Input v-model.number="line.discount_rate" type="number" min="0" max="100" step="0.01" placeholder="0" />
            </div>
            <div>
              <Label>Expense Account</Label>
              <Select v-model="line.expense_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="company_default">Use default</SelectItem>
                  <SelectItem
                    v-for="acct in props.expenseAccounts || []"
                    :key="acct.id"
                    :value="acct.id"
                  >
                    {{ acct.code }} — {{ acct.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="flex items-end">
              <Button
                type="button"
                variant="destructive"
                size="icon"
                @click="removeLine(idx)"
                :disabled="form.line_items.length === 1"
              >
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Totals -->
      <Card>
        <CardHeader>
          <CardTitle>Bill Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span>Subtotal:</span>
              <span>{{ formatCurrency(totals.subtotal) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Tax:</span>
              <span>{{ formatCurrency(totals.tax) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Discount:</span>
              <span class="text-destructive">-{{ formatCurrency(totals.discount) }}</span>
            </div>
            <div class="flex justify-between text-lg font-semibold pt-2 border-t">
              <span>Total:</span>
              <span>{{ formatCurrency(totals.total) }}</span>
            </div>
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
            <Label for="notes">Notes (visible to vendor)</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Any notes about this bill..."
              rows="3"
            />
          </div>
          <div>
            <Label for="internal_notes">Internal Notes (private)</Label>
            <Textarea
              id="internal_notes"
              v-model="form.internal_notes"
              placeholder="Internal notes (not visible to vendor)..."
              rows="3"
            />
          </div>
        </CardContent>
      </Card>
    </form>
  </PageShell>
</template>
