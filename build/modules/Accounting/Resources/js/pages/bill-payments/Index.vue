<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { formatDateTime } from '@/lib/datetime'
import type { BreadcrumbItem } from '@/types'
import { CreditCard, Eye, Plus } from 'lucide-vue-next'

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

interface PaymentRow {
  id: string
  payment_group_id?: string
  payment_group_number?: string
  payment_number: string
  vendor: VendorRef | null
  payment_date: string
  amount: number
  currency: string
  payment_method: string
  reference_number: string | null
  source_count?: number
}

interface PaginatedPayments {
  data: PaymentRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  payments: PaginatedPayments
  vendors: VendorRef[]
  filters: {
    vendor_id?: string
    from_date?: string
    to_date?: string
  }
}>()

const allVendorsValue = '__all_vendors'
const vendorId = ref(props.filters.vendor_id ?? allVendorsValue)
const fromDate = ref(props.filters.from_date ?? '')
const toDate = ref(props.filters.to_date ?? '')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
]

const columns = [
  { key: 'payment_number', label: 'Payment #' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'payment_date', label: 'Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'reference_number', label: 'Reference' },
  { key: 'actions', label: 'Actions' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    currencyDisplay: 'narrowSymbol',
  }).format(val)

const formatPaymentMethod = (method: string) => {
  switch (method) {
    case 'cash':
      return 'Cash'
    case 'bank_transfer':
      return 'Bank Transfer'
    case 'card':
      return 'Card'
    case 'fuel_card':
      return 'Fuel Card'
    case 'split':
      return 'Split sources'
    case 'cheque':
    case 'check':
      return 'Cheque'
    default:
      return method
  }
}

const tableData = computed(() =>
  props.payments.data.map((p) => ({
    id: p.id,
    payment_number: p.payment_group_number ?? p.payment_number,
    vendor: p.vendor?.name ?? '—',
    payment_date: formatDateTime(p.payment_date, { mode: 'date' }),
    amount: formatMoney(p.amount, p.currency),
    payment_method: p.source_count && p.source_count > 1
      ? `${formatPaymentMethod(p.payment_method)} (${p.source_count})`
      : formatPaymentMethod(p.payment_method),
    reference_number: p.reference_number ?? '—',
    source_count: p.source_count ?? 1,
  }))
)

const openPayment = (id: string) => {
  router.get(`/${props.company.slug}/bill-payments/${id}`)
}

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/bill-payments`,
    {
      vendor_id: vendorId.value === allVendorsValue ? '' : vendorId.value,
      from_date: fromDate.value,
      to_date: toDate.value,
    },
    { preserveState: true }
  )
}
</script>

<template>
  <Head title="Bill Payments" />
  <PageShell
    title="Bill Payments"
    :breadcrumbs="breadcrumbs"
    :icon="CreditCard"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/bill-payments/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Record Payment
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-4">
      <Select v-model="vendorId" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All vendors" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="allVendorsValue">All vendors</SelectItem>
          <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Input v-model="fromDate" type="date" @change="handleSearch" />
      <Input v-model="toDate" type="date" @change="handleSearch" />
    </div>

    <div v-if="!payments.data.length">
      <EmptyState
        title="No bill payments yet"
        description="Record your first bill payment."
        cta-text="Record Payment"
        @click="router.get(`/${company.slug}/bill-payments/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="payments"
        clickable
        hoverable
        @row-click="(row) => openPayment(row.id)"
      >
        <template #cell-payment_number="{ row }">
          <Button
            variant="link"
            class="h-auto p-0 font-medium"
            @click.stop="openPayment(row.id)"
          >
            {{ row.payment_number }}
          </Button>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8"
              title="Open payment"
              @click.stop="openPayment(row.id)"
            >
              <Eye class="h-4 w-4" />
            </Button>
          </div>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
