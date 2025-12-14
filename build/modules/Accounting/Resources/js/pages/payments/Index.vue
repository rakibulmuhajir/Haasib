<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import {
  DollarSign,
  Plus,
  Search,
  CreditCard,
  Building,
  FileText,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Customer {
  id: string
  name: string
}

interface PaymentAllocation {
  id: string
  invoice_id: string
  amount: number
}

interface PaymentRow {
  id: string
  payment_number: string
  customer_id: string
  customer: Customer
  amount: number
  currency: string
  payment_method: string
  reference_number?: string
  payment_date: string
  created_at: string
  payment_allocations: PaymentAllocation[]
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
  filters: {
    search: string
    customer_id: string
    payment_method: string
  }
}>()

const search = ref(props.filters.search)
const customerId = ref(props.filters.customer_id)
const paymentMethod = ref(props.filters.payment_method || 'all')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payments', href: `/${props.company.slug}/payments` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/payments`,
    {
      search: search.value,
      customer_id: customerId.value,
      payment_method: paymentMethod.value === 'all' ? '' : paymentMethod.value,
    },
    { preserveState: true }
  )
}

const getPaymentMethodIcon = (method: string) => {
  switch (method) {
    case 'cash':
      return DollarSign
    case 'bank_transfer':
      return Building
    case 'card':
      return CreditCard
    case 'cheque':
    case 'check':
      return FileText
    default:
      return DollarSign
  }
}

const getPaymentMethodLabel = (method: string) => {
  switch (method) {
    case 'cash':
      return 'Cash'
    case 'bank_transfer':
      return 'Bank Transfer'
    case 'card':
      return 'Card'
    case 'cheque':
    case 'check':
      return 'Cheque'
    default:
      return 'Other'
  }
}

const getPaymentMethodVariant = (method: string): 'default' | 'secondary' | 'outline' => {
  switch (method) {
    case 'cash':
      return 'default'
    case 'bank_transfer':
      return 'secondary'
    case 'card':
      return 'outline'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const columns = [
  { key: 'payment_number', label: 'Payment #' },
  { key: 'customer', label: 'Customer' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'payment_date', label: 'Date' },
]

const tableData = computed(() => {
  return props.payments.data.map((payment) => {
    const icon = getPaymentMethodIcon(payment.payment_method)
    return {
      id: payment.id,
      payment_number: payment.payment_number,
      customer: payment.customer.name,
      customer_id: payment.customer_id,
      amount: formatCurrency(payment.amount, payment.currency),
      payment_method: payment.payment_method,
      payment_date: formatDate(payment.payment_date),
      _paymentObject: payment,
      icon,
    }
  })
})

const navigateToPayment = (paymentId: string) => {
  router.get(`/${props.company.slug}/payments/${paymentId}`)
}

const navigateToCustomer = (customerId: string) => {
  router.get(`/${props.company.slug}/customers/${customerId}`)
}

const filterByMethod = (method: string) => {
  paymentMethod.value = method
  handleSearch()
}
</script>

<template>
  <Head title="Payments" />

  <PageShell
    :title="`Payments`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/payments/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Record Payment
      </Button>
    </template>

    <!-- Filters -->
    <div class="flex flex-col gap-4 md:flex-row mb-6">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search payments..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="paymentMethod" @update:modelValue="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Methods" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Methods</SelectItem>
          <SelectItem value="cashe">Cash</SelectItem>
          <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
          <SelectItem value="card">Card</SelectItem>
          <SelectItem value="cheque">Cheque</SelectItem>
          <SelectItem value="other">Other</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Data Table -->
    <DataTable
      :columns="columns"
      :data="tableData"
      :pagination="payments"
    >
      <!-- Payment Number - Clickable Link -->
      <template #cell-payment_number="{ value, row }">
        <button
          @click="navigateToPayment(row.id)"
          class="font-medium text-primary hover:underline focus:outline-none focus:underline"
        >
          {{ value }}
        </button>
      </template>

      <!-- Customer - Clickable Link -->
      <template #cell-customer="{ value, row }">
        <button
          @click="navigateToCustomer(row.customer_id)"
          class="text-foreground hover:text-primary hover:underline focus:outline-none focus:underline transition-colors"
        >
          {{ value }}
        </button>
      </template>

      <!-- Payment Method - Clickable Badge -->
      <template #cell-payment_method="{ value, row }">
        <button
          @click="filterByMethod(value)"
          class="inline-flex items-center gap-2 transition-opacity hover:opacity-70 focus:outline-none"
        >
          <Badge :variant="getPaymentMethodVariant(value)">
            <component :is="row.icon" class="h-3 w-3 mr-1" />
            {{ getPaymentMethodLabel(value) }}
          </Badge>
        </button>
      </template>

      <!-- Mobile Card Template -->
      <template #mobile-card="{ row }">
        <div
          @click="navigateToPayment(row.id)"
          class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
        >
          <div class="space-y-3">
            <!-- Header with payment number and method -->
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-primary">{{ row.payment_number }}</h3>
                <button
                  @click.stop="navigateToCustomer(row.customer_id)"
                  class="text-sm text-zinc-500 hover:text-primary hover:underline"
                >
                  {{ row.customer }}
                </button>
              </div>
              <button
                @click.stop="filterByMethod(row.payment_method)"
                class="transition-opacity hover:opacity-70"
              >
                <Badge :variant="getPaymentMethodVariant(row.payment_method)">
                  <component :is="row.icon" class="h-3 w-3 mr-1" />
                  {{ getPaymentMethodLabel(row.payment_method) }}
                </Badge>
              </button>
            </div>

            <!-- Amount and date -->
            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Amount</span>
              <span class="font-medium">{{ row.amount }}</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Date</span>
              <span class="font-medium">{{ row.payment_date }}</span>
            </div>
          </div>
        </div>
      </template>
    </DataTable>

    <!-- Empty State -->
    <EmptyState
      v-if="payments.data.length === 0"
      icon="DollarSign"
      title="No payments yet"
      description="Record your first payment to get started."
      :action-label="'Record Payment'"
      @action="router.get(`/${company.slug}/payments/create`)"
    />
  </PageShell>
</template>
