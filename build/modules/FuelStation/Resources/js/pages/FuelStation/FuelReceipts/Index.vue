<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { Droplets, Plus, Eye, Search, TrendingUp, Calendar, Truck } from 'lucide-vue-next'
import MoneyText from '@/components/MoneyText.vue'

interface Receipt {
  id: string
  transaction_date: string
  reference: string | null
  description: string | null
  total_amount: number
  status: string
  metadata: {
    total_liters?: number
    lines?: Array<{
      tank_name: string
      fuel_name: string
      liters: number
      rate: number
      amount: number
    }>
  }
}

interface Tank {
  id: string
  name: string
  capacity: number | null
  fuel_name: string | null
  fuel_category: string | null
}

interface Vendor {
  id: string
  name: string
  code: string | null
}

interface Stats {
  total_receipts: number
  total_liters: number
  total_amount: number
  this_month: number
}

const props = defineProps<{
  receipts: Receipt[]
  tanks: Tank[]
  vendors: Vendor[]
  stats: Stats
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel Receipts', href: `/${companySlug.value}/fuel/receipts` },
])

const search = ref('')

const filteredReceipts = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.receipts
  return props.receipts.filter((r) =>
    r.reference?.toLowerCase().includes(q) ||
    r.description?.toLowerCase().includes(q)
  )
})

/*
 * Litres, not money. This was called formatCurrency and was doing both jobs --
 * grouping tank volumes and grouping rupees -- which is how a litre count ends
 * up looking like a price. Money goes through MoneyText; this only ever
 * groups a quantity, and the " L" that follows it in the template is the unit.
 */
const formatLiters = (liters: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(liters)
}

const formatDate = (dateStr: string) => {
  return formatDateTime(dateStr, { mode: 'date' })
}

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'reference', label: 'Reference', kind: 'ref' as const },
  { key: 'liters', label: 'Liters', kind: 'amount' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredReceipts.value.map((r) => ({
    id: r.id,
    date: formatDate(r.transaction_date),
    reference: r.reference || '-',
    liters: `${formatLiters(r.metadata.total_liters || 0)} L`,
    amount: r.total_amount,
    status: r.status,
    _raw: r,
  }))
})

const goToCreate = () => {
  router.get(`/${companySlug.value}/fuel/receipts/create`)
}

const goToShow = (row: any) => {
  router.get(`/${companySlug.value}/fuel/receipts/${row.id}`)
}
</script>

<template>
  <Head title="Fuel Receipts" />

  <PageShell
    title="Fuel Receipts"
    description="Record fuel deliveries from tankers and track inventory increases."
    :icon="Droplets"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="goToCreate">
        <Plus class="mr-2 h-4 w-4" />
        Record Receipt
      </Button>
    </template>

    <!-- Stats -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-surface-sunken">
        <CardHeader class="pb-2">
          <CardDescription>Total Receipts</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_receipts }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Truck class="h-4 w-4 text-status-info" />
            <span>All time</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Liters</CardDescription>
          <CardTitle class="text-2xl">{{ formatLiters(stats.total_liters) }} L</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Droplets class="h-4 w-4 text-status-info" />
            <span>Received</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Amount</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="stats.total_amount" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-status-success" />
            <span>Purchases</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>This Month</CardDescription>
          <CardTitle class="text-2xl">{{ stats.this_month }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Calendar class="h-4 w-4 text-status-info" />
            <span>Deliveries</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- List -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Receipt History</CardTitle>
            <CardDescription>All fuel deliveries from vendors.</CardDescription>
          </div>

          <div class="relative w-full sm:w-[280px]">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
            <Input v-model="search" placeholder="Search by reference..." class="pl-9" />
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <LedgerRegister
          :data="tableData"
          :columns="columns"
          clickable
          @row-click="goToShow"
        >
          <template #empty>
            <EmptyState
              title="No fuel receipts yet"
              description="Record your first fuel delivery when a tanker arrives."
            >
              <template #actions>
                <Button @click="goToCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Record Receipt
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-liters="{ row }">
            <span class="font-medium text-status-info">{{ row.liters }}</span>
          </template>

          <template #cell-amount="{ row }">
            <span class="font-medium"><MoneyText :amount="row.amount" :currency="props.currency" /></span>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.status === 'posted' ? 'bg-status-success/10 text-status-success' : 'bg-status-attention/10 text-status-attention'"
            >
              {{ row._raw.status === 'posted' ? 'Posted' : row._raw.status }}
            </Badge>
          </template>

          <template #cell-_actions="{ row }">
            <Button variant="outline" size="sm" @click.stop="goToShow(row)">
              <Eye class="h-4 w-4" />
            </Button>
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
