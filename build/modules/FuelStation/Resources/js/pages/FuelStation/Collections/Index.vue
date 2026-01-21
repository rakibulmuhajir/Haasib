<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
import { Receipt, Plus, Eye, Wallet, Banknote, CreditCard, TrendingUp } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Collection {
  id: string
  date: string
  reference: string | null
  customer_id: string | null
  customer_name: string
  payment_method: string
  amount: number
  notes: string | null
  status: string
}

interface Customer {
  id: string
  name: string
  code: string | null
}

interface Stats {
  total_collections: number
  total_amount: number
  cash_amount: number
  bank_amount: number
}

interface Filters {
  start_date: string
  end_date: string
  customer_id: string
}

const props = defineProps<{
  collections: Collection[]
  customers: Customer[]
  stats: Stats
  filters: Filters
  currency: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Collections', href: `/${companySlug.value}/fuel/collections` },
])

const currency = computed(() => currencySymbol(props.currency))

// Local filter state
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const customerId = ref(props.filters.customer_id)

const applyFilters = () => {
  router.get(`/${companySlug.value}/fuel/collections`, {
    start_date: startDate.value,
    end_date: endDate.value,
    customer_id: customerId.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Quick date presets
const setDatePreset = (preset: string) => {
  const today = new Date()
  let start: Date
  let end: Date = today

  switch (preset) {
    case 'today':
      start = today
      break
    case 'yesterday':
      start = new Date(today)
      start.setDate(start.getDate() - 1)
      end = start
      break
    case 'week':
      start = new Date(today)
      start.setDate(start.getDate() - 7)
      break
    case 'month':
      start = new Date(today.getFullYear(), today.getMonth(), 1)
      break
    default:
      return
  }

  startDate.value = start.toISOString().split('T')[0]
  endDate.value = end.toISOString().split('T')[0]
  applyFilters()
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const paymentMethodLabel = (method: string) => {
  const labels: Record<string, string> = {
    cash: 'Cash',
    bank: 'Bank',
    transfer: 'Transfer',
    cheque: 'Cheque',
  }
  return labels[method] || method
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'reference', label: 'Reference' },
  { key: 'customer', label: 'Customer' },
  { key: 'method', label: 'Method' },
  { key: 'amount', label: 'Amount' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.collections.map((c) => ({
    id: c.id,
    date: formatDate(c.date),
    reference: c.reference || '-',
    customer: c.customer_name,
    method: c.payment_method,
    amount: c.amount,
    _raw: c,
  }))
})

const goToCreate = () => {
  router.get(`/${companySlug.value}/fuel/collections/create`)
}

const goToShow = (row: any) => {
  router.get(`/${companySlug.value}/fuel/collections/${row.id}`)
}

const goToCustomer = (customerId: string) => {
  if (customerId) {
    router.get(`/${companySlug.value}/fuel/credit-customers/${customerId}`)
  }
}
</script>

<template>
  <Head title="Collections" />

  <PageShell
    title="Collections"
    description="Record and track credit collection payments."
    :icon="Receipt"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="goToCreate">
        <Plus class="mr-2 h-4 w-4" />
        New Collection
      </Button>
    </template>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Collections</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_collections }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-emerald-600" />
            <span>Payments received</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Amount</CardDescription>
          <CardTitle class="text-2xl text-emerald-600">{{ currency }} {{ formatCurrency(stats.total_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>Collected in period</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Cash Collections</CardDescription>
          <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(stats.cash_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Banknote class="h-4 w-4 text-green-600" />
            <span>Cash payments</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Bank Collections</CardDescription>
          <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(stats.bank_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <CreditCard class="h-4 w-4 text-blue-600" />
            <span>Bank/transfer payments</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Filters</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="flex flex-wrap items-end gap-4">
          <div class="space-y-2">
            <Label>Quick Select</Label>
            <div class="flex gap-2">
              <Button variant="outline" size="sm" @click="setDatePreset('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('yesterday')">Yesterday</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('week')">Last 7 Days</Button>
              <Button variant="outline" size="sm" @click="setDatePreset('month')">This Month</Button>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="start_date">Start Date</Label>
            <Input
              id="start_date"
              v-model="startDate"
              type="date"
              class="w-[160px]"
              @change="applyFilters"
            />
          </div>

          <div class="space-y-2">
            <Label for="end_date">End Date</Label>
            <Input
              id="end_date"
              v-model="endDate"
              type="date"
              class="w-[160px]"
              @change="applyFilters"
            />
          </div>

          <div class="space-y-2">
            <Label>Customer</Label>
            <Select v-model="customerId" @update:model-value="applyFilters">
              <SelectTrigger class="w-[200px]">
                <SelectValue placeholder="All Customers" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Customers</SelectItem>
                <SelectItem v-for="c in customers" :key="c.id" :value="c.id">
                  {{ c.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Collections Table -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Collection Records</CardTitle>
        <CardDescription>Payments collected from credit customers.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable
          :data="tableData"
          :columns="columns"
          clickable
          @row-click="goToShow"
        >
          <template #empty>
            <EmptyState
              title="No collections found"
              description="No collections match the current filters."
            >
              <template #action>
                <Button @click="goToCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Record Collection
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-customer="{ row }">
            <button
              v-if="row._raw.customer_id"
              class="text-left hover:underline font-medium"
              @click.stop="goToCustomer(row._raw.customer_id)"
            >
              {{ row._raw.customer_name }}
            </button>
            <span v-else class="font-medium">{{ row._raw.customer_name }}</span>
          </template>

          <template #cell-method="{ row }">
            <Badge
              :class="{
                'bg-green-100 text-green-800': row._raw.payment_method === 'cash',
                'bg-blue-100 text-blue-800': row._raw.payment_method !== 'cash',
              }"
            >
              {{ paymentMethodLabel(row._raw.payment_method) }}
            </Badge>
          </template>

          <template #cell-amount="{ row }">
            <span class="font-medium text-emerald-600">
              {{ currency }} {{ formatCurrency(row._raw.amount) }}
            </span>
          </template>

          <template #cell-_actions="{ row }">
            <Button variant="outline" size="sm" @click.stop="goToShow(row)">
              <Eye class="h-4 w-4" />
            </Button>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
