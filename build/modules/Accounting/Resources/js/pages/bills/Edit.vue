<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { FileText, Save, Plus, Trash2, Info } from 'lucide-vue-next'

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

interface LineItem {
  item_id?: string | null
  warehouse_id?: string | null
  description: string
  quantity: number
  direct_quantity?: number
  unit_price: number
  line_total?: number | string | null
  tax_rate: number
  discount_rate: number
  account_id?: string
  expense_account_id?: string
}

interface BillRef {
  id: string
  vendor_id: string
  bill_date: string
  due_date: string
  currency: string
  base_currency: string
  payment_terms: number
  notes: string | null
  internal_notes: string | null
  line_items: LineItem[]
  status: string
  paid_amount: number
  ap_account_id?: string | null
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
  asset_account_id?: string | null
  expense_account_id?: string | null
  preferred_warehouse_id?: string | null
  preferred_line_account_id?: string | null
}

interface WarehouseOption {
  id: string
  code: string
  name: string
  is_primary: boolean
  warehouse_type?: string | null
  linked_item_id?: string | null
}

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
  bill: BillRef
  expenseAccounts?: AccountOption[]
  apAccounts?: AccountOption[]
  inventoryEnabled?: boolean
  items?: ItemOption[]
  warehouses?: WarehouseOption[]
}>()

const { t } = useLexicon()

const breadcrumbs: BreadcrumbItem[] = [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('bills'), href: `/${props.company.slug}/bills` },
  { title: props.bill.id, href: `/${props.company.slug}/bills/${props.bill.id}` },
  { title: t('edit'), href: `/${props.company.slug}/bills/${props.bill.id}/edit` },
]

// Get default warehouse (primary or first)
const defaultWarehouseId = computed(() => {
  if (!props.warehouses?.length) return null
  const primary = props.warehouses.find(w => w.is_primary)
  return primary?.id ?? props.warehouses[0]?.id ?? null
})

const form = useForm({
  vendor_id: props.bill.vendor_id,
  bill_date: props.bill.bill_date,
  due_date: props.bill.due_date,
  currency: props.bill.currency,
  base_currency: props.bill.base_currency,
  payment_terms: props.bill.payment_terms,
  notes: props.bill.notes ?? '',
  internal_notes: props.bill.internal_notes ?? '',
  ap_account_id: props.bill.ap_account_id ?? '',
  // Loaded amount-driven, with the stored line_total as the Amount: re-saving an
  // untouched line must reproduce the exact figure already posted, not a total
  // recomputed from quantity * a rate that only carries 6 decimals of precision.
  line_items: props.bill.line_items.map((li) => ({
    ...li,
    item_id: li.item_id ?? null,
    warehouse_id: li.warehouse_id ?? null,
    direct_quantity: li.direct_quantity ?? 0,
    unit_price: Number(li.unit_price) || 0,
    line_total: Number(li.line_total) || 0,
    amount_driven: true,
    expense_account_id: li.expense_account_id ?? '__none'
  })),
})

/**
 * Same Amount/Rate driver as Create.vue: each line tracks which of the two was
 * typed last (amount_driven), and that one drives the other when Quantity
 * changes. Amount is what totals sum and what is sent to the server as
 * line_total -- only when amount_driven, so a rate-driven line behaves exactly
 * as before.
 */
const parseFieldValue = (v: string | number): string | number => {
  if (typeof v === 'number') return v
  const n = Number.parseFloat(v)
  return Number.isNaN(n) ? v : n
}

const recomputeAmountFromRate = (line: (typeof form.line_items)[number]) => {
  const qty = Number(line.quantity) || 0
  const rate = Number(line.unit_price) || 0
  line.line_total = Math.round(qty * rate * 100) / 100
}

const recomputeRateFromAmount = (line: (typeof form.line_items)[number]) => {
  const qty = Number(line.quantity) || 0
  const amount = Number(line.line_total) || 0
  line.unit_price = qty > 0 ? Math.round((amount / qty) * 10000) / 10000 : 0
}

const onQuantityChange = (idx: number, v: string | number) => {
  const line = form.line_items[idx]
  line.quantity = parseFieldValue(v) as number
  if (line.amount_driven) {
    recomputeRateFromAmount(line)
  } else {
    recomputeAmountFromRate(line)
  }
}

const onRateChange = (idx: number, v: string | number) => {
  const line = form.line_items[idx]
  line.unit_price = parseFieldValue(v) as number
  line.amount_driven = false
  recomputeAmountFromRate(line)
}

const onAmountChange = (idx: number, v: string | number) => {
  const line = form.line_items[idx]
  line.line_total = parseFieldValue(v)
  line.amount_driven = true
  recomputeRateFromAmount(line)
}

// Only a tracked item's litres can go straight to a customer instead of the tank.
const isTrackedItem = (itemId: string | null | undefined) => {
  if (!itemId || !props.items) return false
  return props.items.find(i => i.id === itemId)?.track_inventory === true
}

const totals = computed(() => {
  const subtotal = form.line_items.reduce((sum, li) => sum + (Number(li.line_total) || 0), 0)
  const tax = form.line_items.reduce((sum, li) => {
    const lineTotal = Number(li.line_total) || 0
    return sum + lineTotal * ((Number(li.tax_rate) || 0) / 100)
  }, 0)
  const discount = form.line_items.reduce((sum, li) => {
    const lineTotal = Number(li.line_total) || 0
    return sum + lineTotal * ((Number(li.discount_rate) || 0) / 100)
  }, 0)
  const total = subtotal + tax - discount
  return { subtotal, tax, discount, total }
})

const addLine = () => form.line_items.push({
  item_id: null,
  warehouse_id: null,
  description: '',
  quantity: 1,
  direct_quantity: 0,
  unit_price: 0,
  line_total: 0,
  amount_driven: false,
  tax_rate: 0,
  discount_rate: 0,
  expense_account_id: '__none'
})

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
      // Fill only what the line doesn't already say: the price on the supplier's bill is the
      // real one, and replacing it with the item's stored cost silently changed the total.
      if (!line.description?.trim()) line.description = item.name
      if (!(Number(line.unit_price) > 0)) {
        line.unit_price = Number(item.cost_price) || 0
        recomputeAmountFromRate(line)
      }
      line.warehouse_id = item.preferred_warehouse_id ?? defaultWarehouseId.value
      line.expense_account_id = item.preferred_line_account_id ?? '__none'
    }
  } else {
    line.warehouse_id = null
    line.expense_account_id = '__none'
  }
  if (!isTrackedItem(itemId)) {
    line.direct_quantity = 0
  }
}

const handleSubmit = () => {
  const data = {
    ...form.data(),
    ap_account_id: form.ap_account_id === '__none' ? null : form.ap_account_id,
    line_items: form.line_items.map(({ amount_driven, line_total, ...item }) => ({
      ...item,
      item_id: item.item_id || null,
      warehouse_id: item.warehouse_id || null,
      expense_account_id: item.expense_account_id === '__none' ? null : item.expense_account_id,
      // Only an amount-driven line resends its line_total -- a rate-driven line, or a
      // line untouched since load, behaves exactly as before (line_total from
      // quantity * rate), except an existing line loads amount-driven precisely so
      // an untouched save reposts the exact figure already on the bill.
      ...(amount_driven ? { line_total } : {}),
    })),
  }

  form.transform(() => data).put(`/${props.company.slug}/bills/${props.bill.id}`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`${t('edit')} ${t('bills')}`" />
  <PageShell
    :title="`${t('edit')} ${t('bills')}`"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <form novalidate class="space-y-6" @submit.prevent="handleSubmit">
      <div
        v-if="bill.status === 'paid'"
        class="rounded-md border border-status-attention/30 bg-status-attention/10 px-3 py-2 text-xs text-status-attention"
      >
        This bill is paid ({{ bill.currency }} {{ Number(bill.paid_amount).toFixed(2) }}). The total can't go below that.
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="vendor_id">{{ t('vendor') }}</Label>
          <select
            id="vendor_id"
            v-model="form.vendor_id"
            class="w-full rounded-sm border px-3 py-2"
            :disabled="!['draft','received'].includes(bill.status)"
          >
            <option value="">{{ t('selectVendor') }}</option>
            <option
              v-for="v in vendors"
              :key="v.id"
              :value="v.id"
            >
              {{ v.name }}
            </option>
          </select>
          <InputError :message="form.errors.vendor_id" />
        </div>
        <div>
          <Label for="bill_date">{{ t('billDate') }}</Label>
          <Input id="bill_date" v-model="form.bill_date" type="date" :disabled="!['draft','received'].includes(bill.status)" />
          <InputError :message="form.errors.bill_date" />
        </div>
        <div>
          <Label for="due_date">{{ t('dueDate') }}</Label>
          <Input id="due_date" v-model="form.due_date" type="date" />
          <InputError :message="form.errors.due_date" />
        </div>
        <div>
          <Label for="currency">{{ t('currency') }}</Label>
          <Input id="currency" v-model="form.currency" maxlength="3" disabled />
          <InputError :message="form.errors.currency" />
        </div>
        <div>
          <Label for="payment_terms">{{ t('paymentTerms') }} ({{ t('days') }})</Label>
          <Input id="payment_terms" v-model.number="form.payment_terms" type="number" min="0" max="365" />
          <InputError :message="form.errors.payment_terms" />
        </div>
        <div>
          <Label for="ap_account_id">{{ t('apAccount') }}</Label>
          <Select v-model="form.ap_account_id">
            <SelectTrigger id="ap_account_id">
              <SelectValue :placeholder="t('useCompanyDefault')" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">{{ t('useCompanyDefault') }}</SelectItem>
              <SelectItem
                v-for="acct in props.apAccounts || []"
                :key="acct.id"
                :value="acct.id"
              >
                {{ acct.code }} — {{ acct.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.ap_account_id" />
        </div>
        <div>
          <Label for="notes">{{ t('notes') }}</Label>
          <Input id="notes" v-model="form.notes" />
          <InputError :message="form.errors.notes" />
        </div>
        <div>
          <Label for="internal_notes">{{ t('internalNotes') }}</Label>
          <Input id="internal_notes" v-model="form.internal_notes" />
          <InputError :message="form.errors.internal_notes" />
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="text-lg font-semibold">{{ t('lineItems') }}</div>
          <Button type="button" variant="outline" @click="addLine">
            <Plus class="mr-2 h-4 w-4" />
            {{ t('addLineItem') }}
          </Button>
        </div>
        <div class="rounded-md border border-muted bg-muted/40 p-3 text-xs text-muted-foreground">
          Select the product. Haasib will choose its tank/warehouse and inventory account automatically.
          Use the default account only for non-inventory bills.
        </div>
        <div class="space-y-4">
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="rounded-sm border p-3 space-y-3"
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
                <InputError :message="form.errors[`line_items.${idx}.item_id`]" />
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
                <InputError :message="form.errors[`line_items.${idx}.warehouse_id`]" />
              </div>
            </div>

            <!-- Main Line Item Fields -->
            <div class="grid gap-3 md:grid-cols-7">
              <div class="md:col-span-2">
                <Label>{{ t('description') }}</Label>
                <Input v-model="line.description" required />
                <InputError :message="form.errors[`line_items.${idx}.description`]" />
              </div>
              <div>
                <Label>{{ t('quantity') }}</Label>
                <Input
                  :model-value="line.quantity"
                  type="number" min="0.01" step="0.01" required
                  @update:model-value="(v) => onQuantityChange(idx, v)"
                />
                <InputError :message="form.errors[`line_items.${idx}.quantity`]" />
              </div>
              <div v-if="isTrackedItem(line.item_id)">
                <Label>Sold directly (L)</Label>
                <Input
                  v-model.number="line.direct_quantity"
                  type="number"
                  min="0"
                  step="0.001"
                  placeholder="0"
                  title="Litres that went straight to a customer, not into the tank."
                />
                <p class="text-xs text-muted-foreground mt-1">Litres that went straight to a customer, not into the tank.</p>
                <InputError :message="form.errors[`line_items.${idx}.direct_quantity`]" />
              </div>
              <div>
                <Label>Rate</Label>
                <Input
                  :model-value="line.unit_price"
                  type="number" min="0" step="any" required
                  @update:model-value="(v) => onRateChange(idx, v)"
                />
                <InputError :message="form.errors[`line_items.${idx}.unit_price`]" />
              </div>
              <div>
                <Label>Amount</Label>
                <Input
                  :model-value="line.line_total"
                  type="number" min="0" step="0.01"
                  title="What the supplier actually billed for this line. Typing here derives the rate."
                  @update:model-value="(v) => onAmountChange(idx, v)"
                />
                <InputError :message="form.errors[`line_items.${idx}.line_total`]" />
              </div>
              <div>
                <Label>{{ t('taxPercent') }}</Label>
                <Input v-model.number="line.tax_rate" type="number" min="0" max="100" step="0.01" />
                <InputError :message="form.errors[`line_items.${idx}.tax_rate`]" />
              </div>
              <div>
                <Label>{{ t('discountPercent') }}</Label>
                <Input v-model.number="line.discount_rate" type="number" min="0" max="100" step="0.01" />
                <InputError :message="form.errors[`line_items.${idx}.discount_rate`]" />
              </div>
            </div>

            <!-- Account & Delete Row -->
            <div class="flex items-end justify-between gap-3">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <Label>Line Account</Label>
                  <TooltipProvider :delay-duration="0">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button type="button" variant="ghost" size="icon" class="h-6 w-6 text-muted-foreground">
                          <Info class="h-3.5 w-3.5" />
                          <span class="sr-only">Line account help</span>
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        Inventory products use their own stock account. General bills can use the company default.
                      </TooltipContent>
                    </Tooltip>
                  </TooltipProvider>
                </div>
                <Select v-model="line.expense_account_id">
                  <SelectTrigger>
                    <SelectValue :placeholder="t('selectAccount')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">{{ t('useDefault') }}</SelectItem>
                    <SelectItem
                      v-for="acct in props.expenseAccounts || []"
                      :key="acct.id"
                      :value="acct.id"
                    >
                      {{ acct.code }} — {{ acct.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="form.errors[`line_items.${idx}.expense_account_id`]" />
              </div>
              <Button type="button" variant="destructive" size="icon" @click="removeLine(idx)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-2 md:w-1/2">
        <div class="flex justify-between text-sm">
          <span>{{ t('subtotal') }}</span>
          <MoneyText :amount="totals.subtotal" :currency="form.currency" />
        </div>
        <div class="flex justify-between text-sm">
          <span>{{ t('tax') }}</span>
          <MoneyText :amount="totals.tax" :currency="form.currency" />
        </div>
        <div class="flex justify-between text-sm">
          <span>{{ t('discount') }}</span>
          <MoneyText :amount="totals.discount" :currency="form.currency" />
        </div>
        <div class="flex justify-between text-base font-semibold">
          <span>{{ t('total') }}</span>
          <MoneyText :amount="totals.total" :currency="form.currency" />
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          {{ t('saveChanges') }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
