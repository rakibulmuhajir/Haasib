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
import { Wallet, Eye, Search, Users, Banknote } from 'lucide-vue-next'

interface AmanatCustomer {
  id: string
  customer_id: string
  customer_name: string
  customer_phone?: string | null
  cnic?: string | null
  amanat_balance: number
  is_credit_customer: boolean
  relationship?: string | null
}

const props = defineProps<{
  customers: AmanatCustomer[]
  summary: {
    total_holders: number
    total_balance: number
  }
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
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Amanat', href: `/${companySlug.value}/fuel/amanat` },
])

const search = ref('')

const filteredCustomers = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.customers
  return props.customers.filter((c) =>
    c.customer_name.toLowerCase().includes(q) ||
    (c.customer_phone ?? '').toLowerCase().includes(q) ||
    (c.cnic ?? '').toLowerCase().includes(q)
  )
})

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const columns = [
  { key: 'name', label: 'Customer' },
  { key: 'phone', label: 'Phone' },
  { key: 'relationship', label: 'Type' },
  { key: 'balance', label: 'Balance', align: 'right' as const },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredCustomers.value.map((c) => ({
    id: c.customer_id,
    name: c.customer_name,
    phone: c.customer_phone ?? '-',
    relationship: c.relationship ?? 'External',
    balance: formatCurrency(c.amanat_balance),
    _actions: c.customer_id,
    _raw: c,
  }))
})

const goToShow = (row: any) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/amanat/${row.id}`)
}

const getRelationshipBadge = (relationship: string | null | undefined) => {
  switch (relationship) {
    case 'owner':
      return { class: 'bg-purple-100 text-purple-800', label: 'Owner' }
    case 'employee':
      return { class: 'bg-sky-100 text-sky-800', label: 'Employee' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', label: 'External' }
  }
}
</script>

<template>
  <Head title="Amanat Deposits" />

  <PageShell
    title="Amanat Deposits"
    description="Manage trust deposits (amanat) for customers who prepay for fuel."
    :icon="Wallet"
    :breadcrumbs="breadcrumbs"
  >
    <div class="grid gap-4 md:grid-cols-2">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-purple-500/10 via-indigo-500/5 to-sky-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Balance</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_balance) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Banknote class="h-4 w-4 text-purple-600" />
            <span>Liability to customers</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Active Holders</CardDescription>
          <CardTitle class="text-2xl">{{ props.summary.total_holders }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Users class="h-4 w-4 text-sky-600" />
            <span>Customers with balance</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Amanat Holders</CardTitle>
            <CardDescription>Click on a customer to view transactions and manage deposits.</CardDescription>
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
              title="No amanat holders"
              description="Customers with trust deposits will appear here."
            />
          </template>

          <template #cell-relationship="{ row }">
            <Badge :class="getRelationshipBadge(row._raw.relationship).class" class="hover:opacity-100">
              {{ getRelationshipBadge(row._raw.relationship).label }}
            </Badge>
          </template>

          <template #cell-balance="{ row }">
            <span class="font-medium" :class="row._raw.amanat_balance > 0 ? 'text-emerald-600' : ''">
              {{ row.balance }}
            </span>
          </template>

          <template #cell-_actions="{ row }">
            <Button
              variant="outline"
              size="sm"
              @click.stop="router.get(`/${companySlug}/fuel/amanat/${row.id}`)"
            >
              <Eye class="h-4 w-4" />
            </Button>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
