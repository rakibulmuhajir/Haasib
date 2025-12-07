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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  DollarSign,
  Plus,
  ArrowLeft,
  Eye,
  Pencil,
  Trash2,
  MoreHorizontal,
  Search,
  CreditCard,
  Building,
  Smartphone,
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
      return DollarSign
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
      return 'Cheque'
    default:
      return 'Other'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const columns = [
  { key: 'payment_number', label: 'Payment #' },
  { key: 'customer', label: 'Customer' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'payment_date', label: 'Date' },
  { key: 'actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.payments.data.map((payment) => {
    const icon = getPaymentMethodIcon(payment.payment_method)
    return {
      id: payment.id,
      payment_number: payment.payment_number,
      customer: payment.customer.name,
      amount: formatCurrency(payment.amount, payment.currency),
      payment_method: getPaymentMethodLabel(payment.payment_method),
      payment_date: new Date(payment.payment_date).toLocaleDateString(),
      actions: payment.id, // Only store the ID for fallback
      _paymentObject: payment, // Store full object for template
      icon,
    }
  })
})
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
          <SelectItem value="cash">Cash</SelectItem>
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
      <template #payment_method="{ value, row }">
        <div class="flex items-center gap-2">
          <component :is="row.icon" class="h-4 w-4" />
          <span>{{ value }}</span>
        </div>
      </template>

      <template #actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" class="h-8 w-8 p-0">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${row._paymentObject.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${row._paymentObject.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>

      <!-- Mobile Card Template -->
      <template #mobile-card="{ row }">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="space-y-3">
            <!-- Header with payment number and method -->
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-zinc-900">{{ row.payment_number }}</h3>
                <p class="text-sm text-zinc-500">{{ row.customer }}</p>
              </div>
              <div class="flex items-center gap-2">
                <component :is="row.icon" class="h-4 w-4 text-zinc-500" />
                <span class="text-sm text-zinc-600">{{ row.payment_method }}</span>
              </div>
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

            <!-- Actions -->
            <div class="pt-2 border-t border-zinc-100">
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" class="w-full justify-between">
                    Actions
                    <MoreHorizontal class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${row._paymentObject.id}`)">
                    <Eye class="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${row._paymentObject.id}/edit`)">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
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