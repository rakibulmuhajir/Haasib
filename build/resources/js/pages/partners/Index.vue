<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import Hint from '@/components/Hint.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { UsersRound, Plus, Eye, Pencil, Search, TrendingUp, TrendingDown, Wallet } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'

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

interface SharePreview {
  month: string
  net_profit: number
  partners: { partner_id: string; name: string; percent: number; amount: number }[]
  allocated: number
  unallocated: number
  shared_before: boolean
  unchanged: boolean
  locked: boolean
}

const props = defineProps<{
  partners: PartnerRow[]
  stats: Stats & { profit_share_total: number }
  currency: string
  shareMonth: string
  preview?: SharePreview
}>()

const { companySlug } = useCompanyRoute()

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

// Share a month's profit: pick the month, check each partner's share, confirm.
const shareOpen = ref(false)
const month = ref(props.shareMonth)
const loadingPreview = ref(false)
const shareForm = useForm({ month: props.shareMonth })

const loadPreview = () => {
  loadingPreview.value = true
  router.reload({
    only: ['preview', 'shareMonth'],
    data: { month: month.value },
    onFinish: () => {
      loadingPreview.value = false
    },
  })
}

const openShare = () => {
  month.value = props.shareMonth
  shareOpen.value = true
  loadPreview()
}

const canShare = computed(
  () =>
    !!props.preview &&
    props.preview.month === month.value &&
    !loadingPreview.value &&
    !props.preview.unchanged &&
    props.preview.partners.length > 0 &&
    !(props.preview.locked && props.preview.shared_before),
)

const submitShare = () => {
  shareForm.month = month.value
  shareForm.post(`/${companySlug.value}/partners/share-profit`, {
    preserveScroll: true,
    onSuccess: () => {
      shareOpen.value = false
    },
  })
}

const monthLabel = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  return new Date(y, (m ?? 1) - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' })
})

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
      <Button v-if="partners.length > 0" variant="outline" @click="openShare">
        Share {{ new Date(Number(shareMonth.slice(0, 4)), Number(shareMonth.slice(5)) - 1, 1).toLocaleString('en-US', { month: 'long' }) }}'s profit
      </Button>
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
            <StatusBadge :status="row._raw.is_active ? 'active' : 'inactive'" />
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
    <Dialog v-model:open="shareOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Share {{ monthLabel }}'s profit</DialogTitle>
          <DialogDescription>Net profit by each partner's share.</DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label for="share_month">Month</Label>
            <Input id="share_month" v-model="month" type="month" @change="loadPreview" />
          </div>

          <div v-if="preview && preview.month === month" class="space-y-3">
            <div class="flex items-baseline justify-between text-sm">
              <span class="text-text-secondary">Net profit</span>
              <MoneyText class="font-medium" :amount="preview.net_profit" :currency="props.currency" />
            </div>
            <div v-for="row in preview.partners" :key="row.partner_id" class="flex items-baseline justify-between text-sm">
              <span>{{ row.name }} <span class="text-text-secondary">{{ row.percent }}%</span></span>
              <MoneyText class="font-medium" :amount="row.amount" :currency="props.currency" />
            </div>
            <div v-if="Math.abs(preview.unallocated) >= 0.005" class="flex items-baseline justify-between text-sm text-text-secondary">
              <span>Not shared</span>
              <MoneyText :amount="preview.unallocated" :currency="props.currency" />
            </div>
            <Hint v-if="preview.unchanged">Already shared. Nothing to change.</Hint>
            <Hint v-else-if="preview.shared_before && preview.locked">Month is locked. The share can't change.</Hint>
            <Hint v-else-if="preview.shared_before">Replaces the earlier share.</Hint>
          </div>
          <p v-else class="text-sm text-text-secondary">Loading…</p>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" :disabled="shareForm.processing" @click="shareOpen = false">Cancel</Button>
          <Button type="button" :disabled="!canShare || shareForm.processing" @click="submitShare">Share profit</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
