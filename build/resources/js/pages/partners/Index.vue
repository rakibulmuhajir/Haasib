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
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { UsersRound, Plus, Eye, Pencil, Search, TrendingUp, TrendingDown, Wallet } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface PartnerRow {
  id: string
  name: string
  phone: string | null
  email: string | null
  profit_share_percentage: number
  drawing_limit_period: string
  drawing_limit_amount: number | null
  total_invested: number
  total_withdrawn: number
  net_capital: number
  remaining_drawing_limit: number | null
  current_period_withdrawn: number
  is_active: boolean
  transactions_count: number
}

interface Stats {
  total_partners: number
  active_partners: number
  total_capital: number
  total_invested: number
  total_withdrawn: number
}

const props = defineProps<{
  partners: PartnerRow[]
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
  { title: 'Partners', href: `/${companySlug.value}/partners` },
])

const currency = computed(() => currencySymbol(props.currency))

const search = ref('')
const activeOnly = ref(false)

const filteredPartners = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.partners.filter((partner) => {
    if (activeOnly.value && !partner.is_active) return false
    if (!q) return true
    return (
      partner.name.toLowerCase().includes(q) ||
      partner.phone?.toLowerCase().includes(q) ||
      partner.email?.toLowerCase().includes(q)
    )
  })
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const columns = [
  { key: 'name', label: 'Partner' },
  { key: 'profit_share', label: 'Profit Share' },
  { key: 'net_capital', label: 'Net Capital' },
  { key: 'drawing_limit', label: 'Drawing Limit' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredPartners.value.map((partner) => ({
    id: partner.id,
    name: partner.name,
    profit_share: `${partner.profit_share_percentage}%`,
    net_capital: `${currency.value} ${formatCurrency(partner.net_capital)}`,
    drawing_limit: partner.drawing_limit_period === 'none'
      ? 'No Limit'
      : `${currency.value} ${formatCurrency(partner.remaining_drawing_limit ?? 0)} left`,
    status: partner.is_active ? 'Active' : 'Inactive',
    _actions: partner.id,
    _raw: partner,
  }))
})

const goToShow = (row: any) => {
  router.get(`/${companySlug.value}/partners/${row.id}`)
}

const goToCreate = () => {
  router.get(`/${companySlug.value}/partners/create`)
}
</script>

<template>
  <Head title="Partners" />

  <PageShell
    title="Partners"
    description="Manage business partners, their capital contributions, and profit sharing arrangements."
    :icon="UsersRound"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="goToCreate">
        <Plus class="mr-2 h-4 w-4" />
        Add Partner
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Partners</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_partners }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <UsersRound class="h-4 w-4 text-sky-600" />
            <span>{{ stats.active_partners }} active</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Capital</CardDescription>
          <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(stats.total_capital) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>Net investment</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl text-emerald-600">{{ currency }} {{ formatCurrency(stats.total_invested) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-emerald-600" />
            <span>Capital contributions</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Withdrawn</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ currency }} {{ formatCurrency(stats.total_withdrawn) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingDown class="h-4 w-4 text-amber-600" />
            <span>Partner drawings</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Partner List</CardTitle>
            <CardDescription>View and manage all business partners.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[280px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search partners..." class="pl-9" />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="activeOnly" v-model:checked="activeOnly" />
              <Label for="activeOnly" class="text-sm text-text-secondary">Active only</Label>
            </div>
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
              title="No partners yet"
              description="Add your first business partner to track capital contributions and profit sharing."
            >
              <template #actions>
                <Button @click="goToCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Partner
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-name="{ row }">
            <div>
              <div class="font-medium">{{ row._raw.name }}</div>
              <div v-if="row._raw.phone" class="text-sm text-muted-foreground">{{ row._raw.phone }}</div>
            </div>
          </template>

          <template #cell-net_capital="{ row }">
            <span :class="row._raw.net_capital >= 0 ? 'text-emerald-600 font-medium' : 'text-red-600 font-medium'">
              {{ currency }} {{ formatCurrency(row._raw.net_capital) }}
            </span>
          </template>

          <template #cell-drawing_limit="{ row }">
            <div v-if="row._raw.drawing_limit_period === 'none'" class="text-muted-foreground">
              No Limit
            </div>
            <div v-else>
              <div class="font-medium">{{ currency }} {{ formatCurrency(row._raw.remaining_drawing_limit ?? 0) }}</div>
              <div class="text-xs text-muted-foreground">
                of {{ currency }} {{ formatCurrency(row._raw.drawing_limit_amount ?? 0) }} {{ row._raw.drawing_limit_period }}
              </div>
            </div>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.is_active ? 'bg-emerald-600 text-white hover:bg-emerald-600' : 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200'"
            >
              {{ row._raw.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-_actions="{ row }">
            <div class="flex items-center justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                @click.stop="goToShow(row)"
              >
                <Eye class="h-4 w-4" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                @click.stop="router.get(`/${companySlug}/partners/${row.id}/edit`)"
              >
                <Pencil class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
