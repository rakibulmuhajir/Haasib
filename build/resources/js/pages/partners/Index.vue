<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
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
import MoneyText from '@/components/MoneyText.vue'

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
  { key: 'name', label: 'Partner', kind: 'text' as const },
  { key: 'profit_share', label: 'Profit Share', kind: 'amount' as const },
  { key: 'net_capital', label: 'Net Capital', kind: 'amount' as const },
  { key: 'drawing_limit', label: 'Drawing Limit', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
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
      <Card class="relative overflow-hidden border-border/80 bg-surface-sunken">
        <CardHeader class="pb-2">
          <CardDescription>Total Partners</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total_partners }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <UsersRound class="h-4 w-4 text-status-info" />
            <span>{{ stats.active_partners }} active</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Capital</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="stats.total_capital" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-status-success" />
            <span>Net investment</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl text-status-success"><MoneyText :amount="stats.total_invested" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-status-success" />
            <span>Capital contributions</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Withdrawn</CardDescription>
          <CardTitle class="text-2xl text-status-attention"><MoneyText :amount="stats.total_withdrawn" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingDown class="h-4 w-4 text-status-attention" />
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
        <LedgerRegister
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
<!-- A partner whose capital account is overdrawn is a fact about the
                 books, not an emergency, and one in credit is not good news --
                 it is money the business owes them. The sign says which. -->
            <span class="font-medium">
              <MoneyText :amount="row._raw.net_capital" :currency="props.currency" />
            </span>
          </template>

          <template #cell-drawing_limit="{ row }">
            <div v-if="row._raw.drawing_limit_period === 'none'" class="text-muted-foreground">
              No Limit
            </div>
            <div v-else>
              <div class="font-medium"><MoneyText :amount="row._raw.remaining_drawing_limit ?? 0" :currency="props.currency" /></div>
              <div class="text-xs text-muted-foreground">
                of <MoneyText :amount="row._raw.drawing_limit_amount ?? 0" :currency="props.currency" /> {{ row._raw.drawing_limit_period }}
              </div>
            </div>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.is_active ? 'bg-status-success text-status-success-contrast hover:bg-status-success' : 'bg-surface-sunken text-text-primary hover:bg-surface-sunken'"
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
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
