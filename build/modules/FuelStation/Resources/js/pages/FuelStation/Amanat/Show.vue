<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { User, Wallet, ArrowDownCircle, ArrowUpCircle, ArrowLeft, Fuel } from 'lucide-vue-next'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'

interface AmanatTransaction {
  id: string
  transaction_type: 'deposit' | 'withdrawal' | 'fuel_purchase'
  amount: number
  fuel_item_name?: string | null
  fuel_quantity?: number | null
  reference?: string | null
  notes?: string | null
  created_at: string
}

interface CustomerProfile {
  id: string
  customer_id: string
  is_credit_customer: boolean
  is_amanat_holder: boolean
  is_investor: boolean
  relationship?: string | null
  cnic?: string | null
  amanat_balance: number
}

interface Customer {
  id: string
  name: string
  email?: string | null
  phone?: string | null
}

interface PaginatedTransactions {
  data: AmanatTransaction[]
}

const props = withDefaults(defineProps<{
  customer: Customer
  profile: CustomerProfile
  transactions: AmanatTransaction[] | PaginatedTransactions
}>(), {
  transactions: () => [],
})

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Amanat', href: `/${companySlug.value}/fuel/amanat` },
  { title: props.customer.name, href: `/${companySlug.value}/fuel/amanat/${props.customer.id}` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currencyCode.value,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDateTime = (date: string) => {
  return formatSharedDateTime(date, { mode: 'datetime', locale: 'en-PK' })
}

// Transaction table
const txColumns = [
  { key: 'date', label: 'Date' },
  { key: 'type', label: 'Type' },
  { key: 'details', label: 'Details' },
  { key: 'amount', label: 'Amount', align: 'right' as const },
]

const transactionRows = computed(() => {
  if (Array.isArray(props.transactions)) return props.transactions
  return props.transactions?.data ?? []
})

const txTableData = computed(() => {
  return transactionRows.value.map((tx) => {
    let details = tx.reference ?? ''
    if (tx.transaction_type === 'fuel_purchase' && tx.fuel_item_name) {
      details = `${tx.fuel_quantity?.toFixed(2) ?? '?'} L ${tx.fuel_item_name}`
    }

    return {
      id: tx.id,
      date: formatDateTime(tx.created_at),
      type: tx.transaction_type,
      details: details || '-',
      amount: tx.amount,
      _raw: tx,
    }
  })
})

const getTypeBadge = (type: string) => {
  switch (type) {
    case 'deposit':
      return { class: 'bg-emerald-100 text-emerald-800', icon: ArrowDownCircle, label: 'Deposit' }
    case 'withdrawal':
      return { class: 'bg-amber-100 text-amber-800', icon: ArrowUpCircle, label: 'Withdrawal' }
    case 'fuel_purchase':
      return { class: 'bg-sky-100 text-sky-800', icon: Fuel, label: 'Fuel Purchase' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Wallet, label: type }
  }
}
</script>

<template>
  <Head :title="`Amanat: ${customer.name}`" />

  <PageShell
    :title="customer.name"
    :description="`Phone: ${customer.phone ?? 'N/A'} | CNIC: ${profile.cnic ?? 'N/A'}`"
    :icon="User"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/fuel/amanat`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10 md:col-span-2">
        <CardHeader class="pb-2">
          <CardDescription>Current Balance</CardDescription>
          <CardTitle class="text-3xl">{{ formatCurrency(profile.amanat_balance) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>Available for fuel purchases</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Customer Type</CardDescription>
          <CardTitle class="text-lg capitalize">{{ profile.relationship ?? 'External' }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0 space-y-2">
          <Badge v-if="profile.is_credit_customer" class="bg-purple-100 text-purple-800 hover:bg-purple-100">
            Credit Customer
          </Badge>
          <Badge v-if="profile.is_investor" class="bg-sky-100 text-sky-800 hover:bg-sky-100 ml-1">
            Investor
          </Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Transaction History</CardTitle>
        <CardDescription>Deposits and withdrawals are recorded from Daily Close.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="txTableData" :columns="txColumns">
          <template #empty>
            <EmptyState
              title="No transactions"
              description="Daily Close deposits, withdrawals, and fuel purchases will appear here."
            />
          </template>

          <template #cell-type="{ row }">
            <Badge :class="getTypeBadge(row.type).class" class="hover:opacity-100">
              <component :is="getTypeBadge(row.type).icon" class="mr-1 h-3 w-3" />
              {{ getTypeBadge(row.type).label }}
            </Badge>
          </template>

          <template #cell-amount="{ row }">
            <span
              class="font-medium"
              :class="{
                'text-emerald-600': row.type === 'deposit',
                'text-red-600': row.type === 'withdrawal' || row.type === 'fuel_purchase',
              }"
            >
              {{ row.type === 'deposit' ? '+' : '-' }}{{ formatCurrency(Math.abs(row.amount)) }}
            </span>
          </template>
        </DataTable>
      </CardContent>
    </Card>

  </PageShell>
</template>
