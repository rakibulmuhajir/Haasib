<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Save, Plus, Trash2 } from 'lucide-vue-next'
import { useFormFeedback } from '@/composables/useFormFeedback'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  name: string
}

interface AccountRef {
  id: string
  code: string
  name: string
}

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
  expenseAccounts: AccountRef[]
  apAccounts: AccountRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
  { title: 'Create', href: `/${props.company.slug}/vendor-credits/create` },
]

const { showSuccess, showError } = useFormFeedback()

const lineItemTemplate = () => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
  expense_account_id: '',
})

const form = useForm({
  vendor_id: '',
  bill_id: '',
  credit_date: new Date().toISOString().slice(0, 10),
  amount: 0,
  currency: props.company.base_currency,
  base_currency: props.company.base_currency,
  exchange_rate: '',
  reason: '',
  notes: '',
  ap_account_id: '',
  status: 'received',
  line_items: [lineItemTemplate()],
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

const addLine = () => form.line_items.push(lineItemTemplate())
const removeLine = (idx: number) => {
  if (form.line_items.length > 1) {
    form.line_items.splice(idx, 1)
  }
}

const handleSubmit = () => {
  // Validate amount is greater than zero
  if (!form.amount || form.amount <= 0) {
    showError('Amount must be greater than zero')
    return
  }

  // Check if line items have meaningful content (description + quantity + price)
  const validLineItems = form.line_items.filter(item => {
    const hasDescription = item.description && item.description.trim() !== ''
    const hasQuantity = item.quantity && Number(item.quantity) > 0
    const hasPrice = item.unit_price && Number(item.unit_price) >= 0

    // Only include line items that have at least a description
    return hasDescription
  })

  // Build submission data
  const data: any = {
    vendor_id: form.vendor_id === '__none' ? null : form.vendor_id,
    bill_id: form.bill_id || null,
    credit_date: form.credit_date,
    amount: form.amount,
    currency: form.currency,
    base_currency: form.base_currency,
    exchange_rate: form.exchange_rate || null,
    reason: form.reason,
    notes: form.notes || null,
    ap_account_id: form.ap_account_id === '__none' ? null : (form.ap_account_id || null),
    status: form.status,
  }

  // Only add line_items if there are valid ones
  if (validLineItems.length > 0) {
    data.line_items = validLineItems.map(item => ({
      description: item.description.trim(),
      quantity: Number(item.quantity) || 1,
      unit_price: Number(item.unit_price) || 0,
      tax_rate: Number(item.tax_rate) || 0,
      discount_rate: Number(item.discount_rate) || 0,
      expense_account_id: item.expense_account_id === '__none' ? null : (item.expense_account_id || null),
    }))
  }

  // Use router.post directly instead of form.transform
  router.post(`/${props.company.slug}/vendor-credits`, data, {
    preserveScroll: true,
    onStart: () => {
      form.processing = true
    },
    onFinish: () => {
      form.processing = false
    },
    onSuccess: () => {
      showSuccess('Vendor credit created successfully')
      router.visit(`/${props.company.slug}/vendor-credits`)
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
      showError(errors)
      // Set form errors for inline display
      form.errors = errors
    },
  })
}
</script>

<template>
  <Head title="Create Vendor Credit" />
  <PageShell
    title="Create Vendor Credit"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="vendor_id">Vendor</Label>
          <Select v-model="form.vendor_id" required>
            <SelectTrigger id="vendor_id">
              <SelectValue placeholder="Select vendor" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">Select vendor</SelectItem>
              <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">
                {{ v.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="credit_date">Credit Date</Label>
          <Input id="credit_date" v-model="form.credit_date" type="date" required />
        </div>
        <div>
          <Label for="amount">Amount</Label>
          <Input id="amount" v-model.number="form.amount" type="number" min="0.01" step="0.01" required />
        </div>
        <div>
          <Label for="currency">Currency</Label>
          <Input id="currency" v-model="form.currency" maxlength="3" />
        </div>
        <div>
          <Label for="exchange_rate">Exchange Rate</Label>
          <Input id="exchange_rate" v-model="form.exchange_rate" placeholder="Required if currency != base" />
        </div>
        <div>
          <Label for="reason">Reason</Label>
          <Input id="reason" v-model="form.reason" required />
        </div>
        <div>
          <Label for="ap_account_id">AP Account</Label>
          <Select v-model="form.ap_account_id">
            <SelectTrigger id="ap_account_id">
              <SelectValue placeholder="Default AP" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">Use vendor default</SelectItem>
              <SelectItem
                v-for="acct in props.apAccounts"
                :key="acct.id"
                :value="acct.id"
              >
                {{ acct.code }} — {{ acct.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="md:col-span-2">
          <Label for="notes">Notes</Label>
          <Input id="notes" v-model="form.notes" />
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="text-lg font-semibold">Line Items (optional)</div>
          <Button type="button" variant="outline" @click="addLine">
            <Plus class="mr-2 h-4 w-4" />
            Add Line
          </Button>
        </div>
        <p class="text-sm text-muted-foreground">
          Add line items for detailed tracking. Items without descriptions will be excluded.
        </p>
        <div class="space-y-4">
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="grid gap-3 rounded border p-3 md:grid-cols-5"
          >
            <div class="md:col-span-2">
              <Label>Description <span class="text-red-500">*</span></Label>
              <Input
                v-model="line.description"
                :class="{ 'border-red-300': !line.description || line.description.trim() === '' }"
                placeholder="Required for line item to be included"
              />
              <p v-if="!line.description || line.description.trim() === ''" class="text-xs text-red-500 mt-1">
                Description required - item will be excluded
              </p>
            </div>
            <div>
              <Label>Qty</Label>
              <Input v-model.number="line.quantity" type="number" min="0.01" step="0.01" />
            </div>
            <div>
              <Label>Unit Price</Label>
              <Input v-model.number="line.unit_price" type="number" min="0" step="0.01" />
            </div>
            <div class="md:col-span-2">
              <Label>Expense Account</Label>
              <Select v-model="line.expense_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">Default</SelectItem>
                  <SelectItem
                    v-for="acct in props.expenseAccounts"
                    :key="acct.id"
                    :value="acct.id"
                  >
                    {{ acct.code }} — {{ acct.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="flex items-end justify-between gap-2">
              <Button type="button" variant="destructive" size="icon" @click="removeLine(idx)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
            <div>
              <Label>Tax %</Label>
              <Input v-model.number="line.tax_rate" type="number" min="0" max="100" step="0.01" />
            </div>
            <div>
              <Label>Discount %</Label>
              <Input v-model.number="line.discount_rate" type="number" min="0" max="100" step="0.01" />
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-2 md:w-1/2">
        <div class="flex justify-between text-sm">
          <span>Subtotal</span>
          <span>{{ totals.subtotal.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>Tax</span>
          <span>{{ totals.tax.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>Discount</span>
          <span>{{ totals.discount.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-base font-semibold">
          <span>Estimated Total</span>
          <span>{{ totals.total.toFixed(2) }}</span>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Credit' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
