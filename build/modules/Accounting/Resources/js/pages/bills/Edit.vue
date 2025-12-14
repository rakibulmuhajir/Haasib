<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { FileText, Save, Plus, Trash2 } from 'lucide-vue-next'

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
  description: string
  quantity: number
  unit_price: number
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
  ap_account_id?: string | null
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
  bill: BillRef
  expenseAccounts?: AccountOption[]
  apAccounts?: AccountOption[]
}>()

const { t } = useLexicon()

const breadcrumbs: BreadcrumbItem[] = [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('bills'), href: `/${props.company.slug}/bills` },
  { title: props.bill.id, href: `/${props.company.slug}/bills/${props.bill.id}` },
  { title: t('edit'), href: `/${props.company.slug}/bills/${props.bill.id}/edit` },
]

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
  line_items: props.bill.line_items.map((li) => ({ ...li, expense_account_id: li.expense_account_id ?? '' })),
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

const addLine = () => form.line_items.push({ description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount_rate: 0, account_id: '' })
const removeLine = (idx: number) => {
  if (form.line_items.length > 1) {
    form.line_items.splice(idx, 1)
  }
}

const handleSubmit = () => {
  form.put(`/${props.company.slug}/bills/${props.bill.id}`, {
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
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="vendor_id">{{ t('vendor') }}</Label>
          <select
            id="vendor_id"
            v-model="form.vendor_id"
            class="w-full rounded border px-3 py-2"
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
        </div>
        <div>
          <Label for="bill_date">{{ t('billDate') }}</Label>
          <Input id="bill_date" v-model="form.bill_date" type="date" :disabled="!['draft','received'].includes(bill.status)" />
        </div>
        <div>
          <Label for="due_date">{{ t('dueDate') }}</Label>
          <Input id="due_date" v-model="form.due_date" type="date" />
        </div>
        <div>
          <Label for="currency">{{ t('currency') }}</Label>
          <Input id="currency" v-model="form.currency" maxlength="3" disabled />
        </div>
        <div>
          <Label for="payment_terms">{{ t('paymentTerms') }} ({{ t('days') }})</Label>
          <Input id="payment_terms" v-model.number="form.payment_terms" type="number" min="0" max="365" />
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
        </div>
        <div>
          <Label for="notes">{{ t('notes') }}</Label>
          <Input id="notes" v-model="form.notes" />
        </div>
        <div>
          <Label for="internal_notes">{{ t('internalNotes') }}</Label>
          <Input id="internal_notes" v-model="form.internal_notes" />
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
        <div class="space-y-4">
          <div
            v-for="(line, idx) in form.line_items"
            :key="idx"
            class="grid gap-3 rounded border p-3 md:grid-cols-6"
          >
            <div class="md:col-span-2">
              <Label>{{ t('description') }}</Label>
              <Input v-model="line.description" required />
            </div>
            <div>
              <Label>{{ t('quantity') }}</Label>
              <Input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required />
            </div>
            <div>
              <Label>{{ t('unitPrice') }}</Label>
              <Input v-model.number="line.unit_price" type="number" min="0" step="0.01" required />
            </div>
            <div>
              <Label>{{ t('taxPercent') }}</Label>
              <Input v-model.number="line.tax_rate" type="number" min="0" max="100" step="0.01" />
            </div>
            <div>
              <Label>{{ t('discountPercent') }}</Label>
              <Input v-model.number="line.discount_rate" type="number" min="0" max="100" step="0.01" />
            </div>
            <div>
              <Label>{{ t('expenseAccount') }}</Label>
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
            </div>
            <div class="flex items-end justify-between gap-2">
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
          <span>{{ totals.subtotal.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>{{ t('tax') }}</span>
          <span>{{ totals.tax.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>{{ t('discount') }}</span>
          <span>{{ totals.discount.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-base font-semibold">
          <span>{{ t('total') }}</span>
          <span>{{ totals.total.toFixed(2) }}</span>
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
