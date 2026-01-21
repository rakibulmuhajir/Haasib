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
import type { BreadcrumbItem } from '@/types'
import { UsersRound, Eye, Search, AlertTriangle, Wallet, Ban, TrendingUp } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Customer {
  id: string
  name: string
  code: string | null
  phone: string | null
  email: string | null
  credit_limit: number
  current_balance: number
  is_credit_blocked: boolean
}

interface Stats {
  total_customers: number
  total_receivable: number
  over_limit: number
  blocked: number
}

const props = defineProps<{
  customers: Customer[]
  stats: Stats
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
  { title: 'Credit Customers', href: `/${companySlug.value}/fuel/credit-customers` },
])

const currency = computed(() => currencySymbol(props.currency))

const search = ref('')

const filteredCustomers = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.customers
  return props.customers.filter((c) =>
    c.name.toLowerCase().includes(q) ||
    c.code?.toLowerCase().includes(q) ||
    c.phone?.toLowerCase().includes(q)
  )
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const columns = [
  { key: 'name', label: 'Customer' },
  { key: 'balance', label: 'Balance' },
  { key: 'limit', label: 'Credit Limit' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredCustomers.value.map((c) => ({
    id: c.id,
    name: c.name,
    balance: c.current_balance,
    limit: c.credit_limit,
    status: c.is_credit_blocked ? 'blocked' : (c.credit_limit > 0 && c.current_balance > c.credit_limit ? 'over_limit' : 'active'),
    _raw: c,
  }))
})

const goToShow = (row: any) => {
  router.get(`/${companySlug.value}/fuel/credit-customers/${row.id}`)
}
</script>

<template>
  <Head title="Credit Customers" />

  <PageShell
    title="Credit Customers"
    description="Manage customers with credit accounts for fuel purchases."
    :icon="UsersRound"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Stats -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Customers</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_customers }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <UsersRound class="h-4 w-4 text-sky-600" />
            <span>With credit accounts</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Receivable</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ currency }} {{ formatCurrency(stats.total_receivable) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-amber-600" />
            <span>Outstanding balance</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Over Limit</CardDescription>
          <CardTitle class="text-2xl text-red-600">{{ stats.over_limit }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <AlertTriangle class="h-4 w-4 text-red-600" />
            <span>Exceeded credit limit</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Blocked</CardDescription>
          <CardTitle class="text-2xl">{{ stats.blocked }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Ban class="h-4 w-4 text-zinc-500" />
            <span>Credit blocked</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Customer List -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Customer List</CardTitle>
            <CardDescription>View and manage credit customers.</CardDescription>
          </div>

          <div class="relative w-full sm:w-[280px]">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
            <Input v-model="search" placeholder="Search customers..." class="pl-9" />
          </div>
        </div>
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
              title="No credit customers yet"
              description="Customers with credit accounts will appear here."
            />
          </template>

          <template #cell-name="{ row }">
            <div>
              <div class="font-medium">{{ row._raw.name }}</div>
              <div v-if="row._raw.code || row._raw.phone" class="text-sm text-muted-foreground">
                {{ row._raw.code || row._raw.phone }}
              </div>
            </div>
          </template>

          <template #cell-balance="{ row }">
            <span :class="row._raw.current_balance > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">
              {{ currency }} {{ formatCurrency(row._raw.current_balance) }}
            </span>
          </template>

          <template #cell-limit="{ row }">
            <span v-if="row._raw.credit_limit > 0" class="font-medium">
              {{ currency }} {{ formatCurrency(row._raw.credit_limit) }}
            </span>
            <span v-else class="text-muted-foreground">No limit</span>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="{
                'bg-red-100 text-red-800': row.status === 'blocked',
                'bg-amber-100 text-amber-800': row.status === 'over_limit',
                'bg-emerald-100 text-emerald-800': row.status === 'active',
              }"
            >
              {{ row.status === 'blocked' ? 'Blocked' : row.status === 'over_limit' ? 'Over Limit' : 'Active' }}
            </Badge>
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
