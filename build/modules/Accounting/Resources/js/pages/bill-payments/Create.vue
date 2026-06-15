<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { CreditCard, Plus, Save, Trash2 } from 'lucide-vue-next'
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
  balance: number | string
  currency: string
  due_date?: string
}

interface SelectedBill {
  id: string
  bill_number: string
  balance: number | string
  currency: string
  vendor_id: string
  vendor?: VendorRef
}

interface AccountOption {
  id: string
  code: string
  name: string
  subtype?: string
  normal_balance?: 'debit' | 'credit'
  estimated_balance?: number
}

interface PaymentSplit {
  payment_account_id: string
  amount: number
  payment_method: string
  reference_number: string
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
  defaults?: {
    ap_account_id?: string | null
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
const page = usePage()

const moneyNumber = (value: number | string | null | undefined): number => {
  return Number(Number(value ?? 0).toFixed(2))
}

const form = useForm({
  vendor_id: props.selectedBill?.vendor_id ?? props.filters?.vendor_id ?? '',
  payment_date: new Date().toISOString().slice(0, 10),
  amount: moneyNumber(props.selectedBill?.balance),
  currency: props.selectedBill?.currency ?? props.company.base_currency,
  base_currency: props.company.base_currency,
  payment_method: 'bank_transfer',
  reference_number: '',
  notes: props.selectedBill ? `Payment for ${props.selectedBill.bill_number}` : '',
  payment_account_id: '',
  ap_account_id: props.defaults?.ap_account_id ?? '',
  payment_splits: [
    {
      payment_account_id: '',
      amount: moneyNumber(props.selectedBill?.balance),
      payment_method: 'bank_transfer',
      reference_number: '',
    },
  ] as PaymentSplit[],
  allocations: props.selectedBill
    ? [{ bill_id: props.selectedBill.id, amount_allocated: moneyNumber(props.selectedBill.balance) }]
    : [] as { bill_id: string; amount_allocated: number }[],
})

const unpaidBills = ref<BillRef[]>(props.unpaidBills ?? [])
const hasDefaultApAccount = computed(() => Boolean(props.defaults?.ap_account_id))

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

const totalSplit = computed(() =>
  form.payment_splits.reduce((sum, split) => sum + (Number(split.amount) || 0), 0)
)

const selectedBillBalance = computed(() => moneyNumber(props.selectedBill?.balance))

const allocatedBillBalance = computed(() =>
  props.selectedBill
    ? selectedBillBalance.value
    : unpaidBills.value.reduce((sum, bill) => {
      const allocated = Number(form.allocations.find((row) => row.bill_id === bill.id)?.amount_allocated || 0)
      return allocated > 0 ? sum + Number(bill.balance || 0) : sum
    }, 0)
)

const remainingVendorCredit = computed(() =>
  Math.max(0, Number((allocatedBillBalance.value - totalAllocated.value).toFixed(2)))
)

const getAllocation = (billId: string) => {
  let allocation = form.allocations.find((a) => a.bill_id === billId)
  if (!allocation) {
    allocation = { bill_id: billId, amount_allocated: 0 }
    form.allocations.push(allocation)
  }
  return allocation
}

const hasAnyAllocation = computed(() =>
  form.allocations.some((a) => (Number(a.amount_allocated) || 0) > 0)
)

watch(totalAllocated, (total) => {
  if (!hasAnyAllocation.value) return
  form.amount = Number(total.toFixed(2))
  if (form.payment_splits.length === 1) {
    form.payment_splits[0].amount = Number(total.toFixed(2))
  }
})

watch(totalSplit, (total) => {
  if (!props.selectedBill) return

  const amount = Math.min(moneyNumber(total), selectedBillBalance.value)
  form.amount = amount
  form.allocations = [{ bill_id: props.selectedBill.id, amount_allocated: amount }]
})

const paymentMethods = [
  { value: 'cash', label: 'Cash' },
  { value: 'check', label: 'Check' },
  { value: 'card', label: 'Card' },
  { value: 'fuel_card', label: 'Fuel Card' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'ach', label: 'ACH' },
  { value: 'wire', label: 'Wire' },
  { value: 'other', label: 'Other' },
]

const formatNumber = (value: number | string | null | undefined, decimals: number = 2): string => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(Number(value ?? 0))
}

const accountLabel = (account: AccountOption) => `${account.code} — ${account.name}`

const accountById = (accountId: string) => {
  return (props.bankAccounts || []).find((account) => account.id === accountId)
}

const apAccountLabel = computed(() => {
  const accountId = form.ap_account_id === '__none' || !form.ap_account_id
    ? props.defaults?.ap_account_id
    : form.ap_account_id
  const account = (props.apAccounts || []).find((acct) => acct.id === accountId)

  return account ? `${account.code} — ${account.name}` : 'Accounts Payable'
})

const projectedBalance = (split: PaymentSplit) => {
  const account = accountById(split.payment_account_id)
  if (!account) return null

  const current = Number(account.estimated_balance || 0)
  const amount = Number(split.amount || 0)

  return account.normal_balance === 'credit'
    ? current + amount
    : current - amount
}

const billRemainingAfterAllocation = (bill: BillRef) => {
  const allocation = form.allocations.find((row) => row.bill_id === bill.id)
  return Math.max(0, moneyNumber(bill.balance) - moneyNumber(allocation?.amount_allocated))
}

const addSplit = () => {
  form.payment_splits.push({
    payment_account_id: '',
    amount: 0,
    payment_method: 'bank_transfer',
    reference_number: '',
  })
}

const removeSplit = (index: number) => {
  if (form.payment_splits.length === 1) return
  form.payment_splits.splice(index, 1)
}

const handleSubmit = () => {
  if (Math.abs(totalSplit.value - totalAllocated.value) > 0.000001) {
    showError('Payment sources must equal the amount allocated to bills')
    return
  }

  // Transform empty strings to null for optional fields
  const splits = form.payment_splits
    .filter((split) => Number(split.amount) > 0)
    .map((split) => ({
      payment_account_id: split.payment_account_id,
      amount: Number(split.amount),
      payment_method: split.payment_method,
      reference_number: split.reference_number || null,
    }))

  const data = {
    vendor_id: form.vendor_id,
    payment_date: form.payment_date,
    amount: totalAllocated.value,
    currency: form.currency,
    base_currency: form.base_currency,
    payment_method: splits[0]?.payment_method ?? form.payment_method,
    reference_number: form.reference_number || null,
    notes: form.notes || null,
    payment_account_id: splits[0]?.payment_account_id ?? null,
    payment_splits: splits,
    ap_account_id: form.ap_account_id === '__none' || !form.ap_account_id ? null : form.ap_account_id,
    allocations: form.allocations.filter(a => a.amount_allocated > 0),
  }

  form.transform(() => data).post(`/${props.company.slug}/bill-payments`, {
    preserveScroll: true,
    onSuccess: () => {
      const flash = (page.props as any)?.flash
      if (flash?.error) {
        showError(flash.error)
        return
      }
      showSuccess(flash?.success ?? 'Bill payment recorded successfully')
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
          <Label for="amount">Payment Amount</Label>
          <Input id="amount" v-model.number="form.amount" type="number" min="0.01" step="0.01" readonly />
          <p class="mt-1 text-xs text-muted-foreground">
            Calculated from the bill allocation below.
          </p>
        </div>
        <div>
          <Label for="currency">Currency</Label>
          <Input id="currency" v-model="form.currency" maxlength="3" />
        </div>
        <div>
          <Label for="ap_account_id">AP Account</Label>
          <Select v-model="form.ap_account_id">
            <SelectTrigger id="ap_account_id" :class="{ 'border-destructive': form.errors.ap_account_id }">
              <SelectValue :placeholder="hasDefaultApAccount ? 'Use company default' : 'Select AP account'" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-if="hasDefaultApAccount" value="__none">Use company default</SelectItem>
              <SelectItem
                v-for="acct in props.apAccounts || []"
                :key="acct.id"
                :value="acct.id"
              >
                {{ acct.code }} — {{ acct.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.ap_account_id" class="text-sm text-destructive mt-1">
            {{ form.errors.ap_account_id }}
          </p>
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
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-lg font-semibold">Payment Sources</div>
            <p class="text-sm text-muted-foreground">
              Add only the amount you are paying now. Any unpaid bill balance stays as vendor credit in Accounts Payable.
            </p>
          </div>
          <Button type="button" variant="outline" size="sm" @click="addSplit">
            <Plus class="mr-2 h-4 w-4" />
            Add source
          </Button>
        </div>

        <div class="space-y-3">
          <div
            v-for="(split, index) in form.payment_splits"
            :key="index"
            class="grid gap-3 rounded border p-3 md:grid-cols-[minmax(0,1.4fr)_minmax(120px,0.5fr)_minmax(130px,0.6fr)_minmax(130px,0.7fr)_auto]"
          >
            <div>
              <Label>Account</Label>
              <Select v-model="split.payment_account_id" required>
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="acct in props.bankAccounts || []"
                    :key="acct.id"
                    :value="acct.id"
                  >
                    {{ accountLabel(acct) }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="accountById(split.payment_account_id)" class="mt-1 text-xs text-muted-foreground">
                Est. balance {{ formatNumber(accountById(split.payment_account_id)?.estimated_balance ?? 0) }}
                · After payment {{ formatNumber(projectedBalance(split) ?? 0) }}
              </p>
            </div>

            <div>
              <Label>Amount</Label>
              <Input v-model.number="split.amount" type="number" min="0.01" step="0.01" required />
            </div>

            <div>
              <Label>Method</Label>
              <Select v-model="split.payment_method" required>
                <SelectTrigger>
                  <SelectValue placeholder="Method" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="method in paymentMethods" :key="method.value" :value="method.value">
                    {{ method.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div>
              <Label>Reference</Label>
              <Input v-model="split.reference_number" placeholder="Optional" />
            </div>

            <div class="flex items-end justify-end">
              <Button
                type="button"
                variant="ghost"
                size="icon"
                :disabled="form.payment_splits.length === 1"
                @click="removeSplit(index)"
              >
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        <div class="flex justify-between text-sm">
          <span>Total from sources</span>
          <span :class="{ 'text-destructive': Math.abs(totalSplit - totalAllocated) > 0.000001 }">
            {{ totalSplit.toFixed(2) }}
          </span>
        </div>
        <div
          v-if="Math.abs(totalSplit - totalAllocated) > 0.000001"
          class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
        >
          Sources cover {{ formatNumber(totalSplit) }} {{ form.currency }}. Allocations are
          {{ formatNumber(totalAllocated) }} {{ form.currency }}. The two must match before saving.
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
              <div class="text-xs text-muted-foreground">
                Balance {{ formatNumber(bill.balance) }} {{ bill.currency }}
              </div>
              <div class="text-xs text-muted-foreground">
                Remaining after this payment {{ formatNumber(billRemainingAfterAllocation(bill)) }} {{ bill.currency }}
              </div>
            </div>
            <Input
              class="w-32"
              type="number"
              min="0"
              step="0.01"
              v-model.number="getAllocation(bill.id).amount_allocated"
              placeholder="Allocate"
            />
          </div>
        </div>
      </div>

      <div class="flex justify-between text-sm">
        <span>Total Allocated</span>
        <span>{{ totalAllocated.toFixed(2) }}</span>
      </div>

      <div class="grid gap-3 rounded border bg-muted/30 p-4 text-sm md:grid-cols-3">
        <div>
          <div class="text-muted-foreground">Payment now</div>
          <div class="text-lg font-semibold">{{ formatNumber(totalAllocated) }} {{ form.currency }}</div>
        </div>
        <div>
          <div class="text-muted-foreground">Remaining vendor credit</div>
          <div class="text-lg font-semibold">{{ formatNumber(remainingVendorCredit) }} {{ form.currency }}</div>
        </div>
        <div>
          <div class="text-muted-foreground">Credit account</div>
          <div class="font-medium">{{ apAccountLabel }}</div>
          <p class="mt-1 text-xs text-muted-foreground">
            The remaining amount is already carried here from the bill posting.
          </p>
        </div>
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
