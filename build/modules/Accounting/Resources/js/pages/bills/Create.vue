<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { EntitySearch, QuickAddModal } from '@/components/forms'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import type { BreadcrumbItem } from '@/types'
import { FileText, Save, Plus, Trash2, ArrowLeft, Info } from 'lucide-vue-next'

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

interface ItemOption {
  id: string
  sku: string
  name: string
  cost_price: number
  unit_of_measure: string
  track_inventory: boolean
}

interface WarehouseOption {
  id: string
  code: string
  name: string
  is_primary: boolean
}

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
  expenseAccounts?: AccountOption[]
  apAccounts?: AccountOption[]
  defaultExpenseAccountId?: string | null
  inventoryEnabled?: boolean
  items?: ItemOption[]
  warehouses?: WarehouseOption[]
  selectedVendorId?: string | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bills', href: `/${props.company.slug}/bills` },
  { title: 'Create' },
]

// Get default warehouse (primary or first)
const defaultWarehouseId = computed(() => {
  if (!props.warehouses?.length) return null
  const primary = props.warehouses.find(w => w.is_primary)
  return primary?.id ?? props.warehouses[0]?.id ?? null
})

const lineItemTemplate = () => ({
  item_id: null as string | null,
  warehouse_id: defaultWarehouseId.value,
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
  expense_account_id: props.defaultExpenseAccountId || 'company_default',
})

const showQuickAdd = ref(false)
const quickAddQuery = ref('')

const form = useForm({
  vendor_id: props.selectedVendorId ?? '',
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

const handleQuickAddClick = (query: string) => {
  quickAddQuery.value = query
  showQuickAdd.value = true
}

const handleVendorCreated = (vendor: { id: string }) => {
  form.vendor_id = vendor.id
  showQuickAdd.value = false
}

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
    currencyDisplay: 'narrowSymbol',
    currency: form.currency || 'USD',
  }).format(amount)
}

const addLine = () => form.line_items.push(lineItemTemplate())
const removeLine = (idx: number) => {
  if (form.line_items.length > 1) {
    form.line_items.splice(idx, 1)
  }
}

// Handle item selection - auto-fill description and cost price
const handleItemSelect = (idx: number, itemId: string | null) => {
  const line = form.line_items[idx]
  line.item_id = itemId

  if (itemId && props.items) {
    const item = props.items.find(i => i.id === itemId)
    if (item) {
      line.description = item.name
      line.unit_price = Number(item.cost_price) || 0
    }
  }
}

const handleSubmit = () => {
  // Prepare data - convert 'company_default' to null for backend
  const data = {
    ...form.data(),
    ap_account_id: form.ap_account_id === 'company_default' ? null : form.ap_account_id,
    line_items: form.line_items.map(item => ({
      ...item,
      item_id: item.item_id || null,
      warehouse_id: item.warehouse_id || null,
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
            <EntitySearch
              v-model="form.vendor_id"
              entity-type="vendor"
              placeholder="Select or create a vendor"
              @quick-add-click="handleQuickAddClick"
            />
            <QuickAddModal
              v-model:open="showQuickAdd"
              entity-type="vendor"
              :initial-name="quickAddQuery"
              @created="handleVendorCreated"
            />
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
          <div class="rounded-md border border-muted bg-muted/40 p-3 text-xs text-muted-foreground">
            For fuel purchases, select the fuel item and set the line account to Fuel Inventory.
            Use the default expense for general operating bills.
          </div>
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="rounded-lg border p-4 space-y-3"
          >
            <!-- Item & Warehouse Row (if inventory enabled) -->
            <div v-if="inventoryEnabled && items?.length" class="grid gap-3 md:grid-cols-3">
              <div>
                <Label>Item (Optional)</Label>
                <Select
                  :model-value="line.item_id ?? 'none'"
                  @update:model-value="(v) => handleItemSelect(idx, v === 'none' ? null : v)"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select item or enter manually" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">— Manual entry —</SelectItem>
                    <SelectItem
                      v-for="item in items"
                      :key="item.id"
                      :value="item.id"
                    >
                      {{ item.sku }} — {{ item.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-if="line.item_id && warehouses?.length">
                <Label>Warehouse</Label>
                <Select v-model="line.warehouse_id">
                  <SelectTrigger>
                    <SelectValue placeholder="Select warehouse" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="wh in warehouses"
                      :key="wh.id"
                      :value="wh.id"
                    >
                      {{ wh.code }} — {{ wh.name }}
                      <span v-if="wh.is_primary" class="text-muted-foreground ml-1">(Primary)</span>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- Main Line Item Fields -->
            <div class="grid gap-3 md:grid-cols-7">
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
                <div class="flex items-center gap-2">
                  <Label>Expense Account</Label>
                  <TooltipProvider :delay-duration="0">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button type="button" variant="ghost" size="icon" class="h-6 w-6 text-muted-foreground">
                          <Info class="h-3.5 w-3.5" />
                          <span class="sr-only">Expense account help</span>
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        Use Fuel Inventory for fuel purchases. Use default expense for general bills.
                      </TooltipContent>
                    </Tooltip>
                  </TooltipProvider>
                </div>
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
            </div>

            <!-- Delete Button -->
            <div class="flex justify-end">
              <Button
                type="button"
                variant="destructive"
                size="sm"
                @click="removeLine(idx)"
                :disabled="form.line_items.length === 1"
              >
                <Trash2 class="h-4 w-4 mr-1" />
                Remove
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
