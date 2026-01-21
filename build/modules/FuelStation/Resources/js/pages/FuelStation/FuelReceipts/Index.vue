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
import { Droplets, Plus, Eye, Search, TrendingUp, Calendar, Truck } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

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

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel Receipts', href: `/${companySlug.value}/fuel/receipts` },
])

const currency = computed(() => currencySymbol(props.currency))

const search = ref('')

const filteredReceipts = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.receipts
  return props.receipts.filter((r) =>
    r.reference?.toLowerCase().includes(q) ||
    r.description?.toLowerCase().includes(q)
  )
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'reference', label: 'Reference' },
  { key: 'liters', label: 'Liters' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredReceipts.value.map((r) => ({
    id: r.id,
    date: formatDate(r.transaction_date),
    reference: r.reference || '-',
    liters: `${formatCurrency(r.metadata.total_liters || 0)} L`,
    amount: `${currency.value} ${formatCurrency(r.total_amount)}`,
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
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Receipts</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_receipts }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Truck class="h-4 w-4 text-sky-600" />
            <span>All time</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Liters</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(stats.total_liters) }} L</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Droplets class="h-4 w-4 text-sky-600" />
            <span>Received</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Amount</CardDescription>
          <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(stats.total_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-emerald-600" />
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
            <Calendar class="h-4 w-4 text-indigo-600" />
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
        <DataTable
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
            <span class="font-medium text-sky-600">{{ row.liters }}</span>
          </template>

          <template #cell-amount="{ row }">
            <span class="font-medium">{{ row.amount }}</span>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ row._raw.status === 'posted' ? 'Posted' : row._raw.status }}
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
