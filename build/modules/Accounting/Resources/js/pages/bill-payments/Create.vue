<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { CreditCard, Save } from 'lucide-vue-next'
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

interface BillRef {
  id: string
  bill_number: string
  balance: number
  currency: string
  due_date?: string
}

interface SelectedBill {
  id: string
  bill_number: string
  balance: number
  currency: string
  vendor_id: string
  vendor?: VendorRef
}

interface AccountOption {
  id: string
  code: string
  name: string
  subtype?: string
}

const props = defineProps<{
  company: CompanyRef
  vendors: VendorRef[]
  unpaidBills: BillRef[]
  selectedBill?: SelectedBill | null
  filters?: {
    vendor_id?: string | null
    bill_id?: string | null
  }
  bankAccounts?: AccountOption[]
  apAccounts?: AccountOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
  { title: 'Create', href: `/${props.company.slug}/bill-payments/create` },
]

const { showSuccess, showError } = useFormFeedback()

const form = useForm({
  vendor_id: props.selectedBill?.vendor_id ?? props.filters?.vendor_id ?? '',
  payment_date: new Date().toISOString().slice(0, 10),
  amount: props.selectedBill?.balance ?? 0,
  currency: props.selectedBill?.currency ?? props.company.base_currency,
  base_currency: props.company.base_currency,
  payment_method: 'bank_transfer',
  reference_number: '',
  notes: props.selectedBill ? `Payment for ${props.selectedBill.bill_number}` : '',
  payment_account_id: '',
  ap_account_id: '',
  allocations: props.selectedBill
    ? [{ bill_id: props.selectedBill.id, amount_allocated: props.selectedBill.balance }]
    : [] as { bill_id: string; amount_allocated: number }[],
})

const unpaidBills = ref<BillRef[]>(props.unpaidBills ?? [])

const resetAllocations = (bills: BillRef[]) => {
  // Only reset allocations if we don't have a selected bill with pre-filled allocation
  if (props.selectedBill && form.allocations.length > 0) {
    return
  }
  form.allocations = bills.map((bill) => ({
    bill_id: bill.id,
    amount_allocated: 0,
  }))
}

watch(
  () => props.unpaidBills,
  (bills) => {
    unpaidBills.value = bills ?? []
    resetAllocations(unpaidBills.value)
  },
  { immediate: true }
)

const fetchUnpaidBills = (vendorId: string) => {
  form.vendor_id = vendorId
  if (!vendorId) {
    unpaidBills.value = []
    form.allocations = []
    return
  }

  router.get(
    `/${props.company.slug}/bill-payments/create`,
    { vendor_id: vendorId },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['unpaidBills'],
      onSuccess: (page) => {
        const bills = ((page.props as any).unpaidBills as BillRef[]) ?? []
        unpaidBills.value = bills
        resetAllocations(unpaidBills.value)
      },
    }
  )
}

const totalAllocated = computed(() =>
  form.allocations.reduce((sum, a) => sum + (Number(a.amount_allocated) || 0), 0)
)

const updateAllocation = (billId: string, amount: number) => {
  const existing = form.allocations.find((a) => a.bill_id === billId)
  if (existing) {
    existing.amount_allocated = amount
    return
  }
  form.allocations.push({ bill_id: billId, amount_allocated: amount })
}

const currentAllocation = (billId: string) => {
  return form.allocations.find((a) => a.bill_id === billId)?.amount_allocated ?? 0
}

const formatNumber = (value: number, decimals: number = 2): string => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value)
}

const handleSubmit = () => {
  if (totalAllocated.value - form.amount > 0.000001) {
    showError('Allocations exceed payment amount')
    return
  }

  // Transform empty strings to null for optional fields
  const data = {
    vendor_id: form.vendor_id,
    payment_date: form.payment_date,
    amount: form.amount,
    currency: form.currency,
    base_currency: form.base_currency,
    payment_method: form.payment_method,
    reference_number: form.reference_number || null,
    notes: form.notes || null,
    payment_account_id: form.payment_account_id,
    ap_account_id: form.ap_account_id || null,
    allocations: form.allocations.filter(a => a.amount_allocated > 0),
  }

  form.transform(() => data).post(`/${props.company.slug}/bill-payments`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess('Bill payment recorded successfully')
      router.visit(`/${props.company.slug}/bill-payments`)
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
      showError(errors)
    }
  })
}
</script>

<template>
  <Head title="Record Bill Payment" />
  <PageShell
    title="Record Bill Payment"
    :breadcrumbs="breadcrumbs"
    :icon="CreditCard"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="vendor_id">Vendor</Label>
          <Select :model-value="form.vendor_id" @update:modelValue="fetchUnpaidBills">
            <SelectTrigger>
              <SelectValue placeholder="Select vendor" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">
                {{ v.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="payment_date">Payment Date</Label>
          <Input id="payment_date" v-model="form.payment_date" type="date" required />
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
          <Label for="payment_account_id">Pay From *</Label>
          <Select v-model="form.payment_account_id" required>
            <SelectTrigger id="payment_account_id">
              <SelectValue placeholder="Select bank/cash account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="acct in props.bankAccounts || []"
                :key="acct.id"
                :value="acct.id"
              >
                {{ acct.code }} — {{ acct.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="ap_account_id">AP Account</Label>
          <Select v-model="form.ap_account_id">
            <SelectTrigger id="ap_account_id">
              <SelectValue placeholder="Use company default" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">Use company default</SelectItem>
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
          <Label for="payment_method">Method</Label>
          <Select v-model="form.payment_method">
            <SelectTrigger>
              <SelectValue placeholder="Select method" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="cash">Cash</SelectItem>
              <SelectItem value="check">Check</SelectItem>
              <SelectItem value="card">Card</SelectItem>
              <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
              <SelectItem value="ach">ACH</SelectItem>
              <SelectItem value="wire">Wire</SelectItem>
              <SelectItem value="other">Other</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="reference_number">Reference</Label>
          <Input id="reference_number" v-model="form.reference_number" />
        </div>
        <div class="md:col-span-2">
          <Label for="notes">Notes</Label>
          <Textarea id="notes" v-model="form.notes" />
        </div>
      </div>

      <div class="space-y-3">
        <div class="text-lg font-semibold">Allocations</div>
        <div v-if="!unpaidBills.length" class="text-sm text-muted-foreground">
          Select a vendor to load unpaid bills.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="bill in unpaidBills"
            :key="bill.id"
            class="flex items-center justify-between rounded border p-3"
          >
            <div>
              <div class="font-medium">{{ bill.bill_number }}</div>
              <div class="text-xs text-muted-foreground">Balance {{ formatNumber(bill.balance) }} {{ bill.currency }}</div>
            </div>
            <Input
              class="w-32"
              type="number"
              min="0"
              step="0.01"
              :value="currentAllocation(bill.id)"
              placeholder="Allocate"
              @input="(e: any) => updateAllocation(bill.id, parseFloat(e.target.value) || 0)"
            />
          </div>
        </div>
      </div>

      <div class="flex justify-between text-sm">
        <span>Total Allocated</span>
        <span>{{ totalAllocated.toFixed(2) }}</span>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Payment' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
