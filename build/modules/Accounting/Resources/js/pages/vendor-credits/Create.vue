<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Save, Plus, Trash2 } from 'lucide-vue-next'

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

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
  { title: 'Create', href: `/${props.company.slug}/vendor-credits/create` },
]

const lineItemTemplate = () => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount_rate: 0,
  account_id: '',
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
  form.post(`/${props.company.slug}/vendor-credits`, {
    preserveScroll: true,
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
          <select
            id="vendor_id"
            v-model="form.vendor_id"
            class="w-full rounded border px-3 py-2"
            required
          >
            <option value="">Select vendor</option>
            <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
          </select>
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
        <div class="space-y-4">
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="grid gap-3 rounded border p-3 md:grid-cols-5"
          >
            <div class="md:col-span-2">
              <Label>Description</Label>
              <Input v-model="line.description" />
            </div>
            <div>
              <Label>Qty</Label>
              <Input v-model.number="line.quantity" type="number" min="0.01" step="0.01" />
            </div>
            <div>
              <Label>Unit Price</Label>
              <Input v-model.number="line.unit_price" type="number" min="0" step="0.01" />
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
            <div class="md:col-span-2">
              <Label>Account ID (optional)</Label>
              <Input v-model="line.account_id" placeholder="Account UUID" />
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
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Credit
        </Button>
      </div>
    </form>
  </PageShell>
</template>
