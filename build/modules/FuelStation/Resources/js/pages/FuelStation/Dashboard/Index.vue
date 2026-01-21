<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import TankLevelGauge from '../../../components/TankLevelGauge.vue'
import type { BreadcrumbItem } from '@/types'
import {
  ArrowRight,
  BarChart3,
  Droplet,
  Fuel,
  Gauge,
  HandCoins,
  Receipt,
  TrendingUp,
  Warehouse,
  FileText,
} from 'lucide-vue-next'

type DashboardData = {
  summary?: {
    active_pumps?: number
    today_readings?: number
    pending_tank_readings?: number
  }
  tankLevels?: Array<{
    tank?: { id: string; name: string; capacity?: number | null; linked_item?: any; linkedItem?: any } | null
    item_name?: string
    capacity?: number
    current_level?: number
    fill_percentage?: number
    last_reading_date?: string | null
  }>
  currentRates?: Array<{
    item?: { id: string; name: string; fuel_category?: string | null } | null
    purchase_rate?: number
    sale_rate?: number
    margin?: number
    effective_date?: string
  }>
  todaySales?: {
    total?: number
    by_type?: Record<string, { count: number; total: number }>
  }
  monthlySales?: {
    total_sales?: number
    total_liters?: number
  }
  pendingHandovers?: {
    total_amount?: number
    items?: Array<any>
  }
  outstandingInvestorCommissions?: {
    total?: number
    investors?: Array<any>
  }
  amanatSummary?: {
    total_holders?: number
    total_balance?: number
  }
  vendorCardReceivable?: number
}

const props = defineProps<{
  data: DashboardData
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
  { title: 'Dashboard', href: `/${companySlug.value}/fuel/dashboard` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const formatMoney = (n: number) => {
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currencyDisplay: 'narrowSymbol',
      currency: currencyCode.value,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(n ?? 0)
  } catch (_e) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
  }
}

const formatLiters = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n ?? 0)

const quickLinks = computed(() => {
  const slug = companySlug.value
  return [
    {
      title: 'Pumps',
      description: 'Manage pumps and tank links',
      href: `/${slug}/fuel/pumps`,
      icon: Gauge,
      accent: 'from-sky-500/15 via-indigo-500/10 to-emerald-500/15',
    },
    {
      title: 'Rates',
      description: 'Track margins and rate history',
      href: `/${slug}/fuel/rates`,
      icon: TrendingUp,
      accent: 'from-emerald-500/15 via-sky-500/10 to-indigo-500/15',
    },
    {
      title: 'Tank Readings',
      description: 'Draft → Confirm → Post workflow',
      href: `/${slug}/fuel/tank-readings`,
      icon: Warehouse,
      accent: 'from-amber-500/15 via-rose-500/10 to-sky-500/15',
    },
    {
      title: 'Pump Readings',
      description: 'Day/night shift meter captures',
      href: `/${slug}/fuel/pump-readings`,
      icon: FileText,
      accent: 'from-indigo-500/15 via-sky-500/10 to-amber-500/15',
    },
  ]
})

const summary = computed(() => props.data?.summary ?? {})
const data = computed(() => props.data ?? ({} as DashboardData))

const tankLevels = computed(() => {
  const levels = (props.data?.tankLevels ?? []).map((l) => {
    const tank = l.tank || null
    const capacity = Number((l as any).capacity ?? (tank as any)?.capacity ?? (tank as any)?.capacity_liters ?? 0)
    const current = Number(l.current_level ?? 0)
    const fill = capacity > 0 ? Math.round((current / capacity) * 1000) / 10 : 0
    const percent = Number(l.fill_percentage ?? fill)
    const itemName = l.item_name ?? (tank as any)?.linked_item?.name ?? (tank as any)?.linkedItem?.name ?? 'Fuel'
    return {
      tank,
      itemName,
      capacity,
      current,
      percent: Math.min(Math.max(percent, 0), 100),
      lastReading: l.last_reading_date ?? null,
    }
  })

  return levels.sort((a, b) => (a.tank?.name || '').localeCompare(b.tank?.name || ''))
})

const currentRates = computed(() => (props.data?.currentRates ?? []).slice().sort((a, b) => {
  const an = a.item?.name || ''
  const bn = b.item?.name || ''
  return an.localeCompare(bn)
}))

const saleTypesToday = computed(() => {
  const byType = props.data?.todaySales?.by_type || {}
  const entries = Object.entries(byType).map(([key, val]) => ({
    type: key,
    count: val.count,
    total: val.total,
  }))
  return entries.sort((a, b) => (b.total ?? 0) - (a.total ?? 0))
})
</script>

<template>
  <Head title="Fuel Dashboard" />

  <PageShell
    title="Fuel Dashboard"
    description="Everything you need to run daily fuel operations."
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Pumps</CardDescription>
          <CardTitle class="text-2xl">
            {{ summary.active_pumps ?? 0 }}
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center justify-between">
            <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">Operational</Badge>
            <Button variant="outline" size="sm" @click="router.get(`/${companySlug}/fuel/pumps`)">
              View <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Today Readings</CardDescription>
          <CardTitle class="text-2xl">
            {{ summary.today_readings ?? 0 }}
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center justify-between">
            <Badge variant="secondary" class="bg-sky-100 text-sky-800 hover:bg-sky-100">
              <Gauge class="mr-1 h-3.5 w-3.5" /> Meter captures
            </Badge>
            <Button variant="outline" size="sm" @click="router.get(`/${companySlug}/fuel/rates`)">
              Rates <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Draft Tank Readings</CardDescription>
          <CardTitle class="text-2xl">
            {{ summary.pending_tank_readings ?? 0 }}
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center justify-between">
            <Badge variant="secondary" class="bg-amber-100 text-amber-800 hover:bg-amber-100">Needs review</Badge>
            <Button variant="outline" size="sm" @click="router.get(`/${companySlug}/fuel/tank-readings`)">
              Review <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Pending Handovers</CardDescription>
          <CardTitle class="text-2xl">{{ formatMoney(data.pendingHandovers?.total_amount ?? 0) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center justify-between">
            <Badge variant="outline" class="border-indigo-200 text-indigo-700">Cash in transit</Badge>
            <Button variant="outline" size="sm" @click="router.get(`/${companySlug}/fuel/handovers`)">
              View <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Quick Links</CardTitle>
          <CardDescription>Jump straight into daily operations.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3 sm:grid-cols-2">
          <Button
            v-for="link in quickLinks"
            :key="link.title"
            type="button"
            variant="outline"
            class="group h-auto justify-start rounded-xl border-border/80 bg-surface-1 p-4 text-left transition hover:shadow-sm"
            @click="router.get(link.href)"
          >
            <div class="relative overflow-hidden rounded-lg border border-border/70 bg-gradient-to-br p-3" :class="link.accent">
              <component :is="link.icon" class="h-5 w-5 text-text-primary" />
            </div>
            <div class="mt-3">
              <p class="font-semibold text-text-primary group-hover:underline">{{ link.title }}</p>
              <p class="mt-1 text-sm text-text-secondary">{{ link.description }}</p>
            </div>
          </Button>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Today</CardTitle>
          <CardDescription>Sales mix and operational signals.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-border/70 bg-gradient-to-br from-emerald-500/10 to-sky-500/5 p-4">
              <p class="text-sm font-medium text-text-tertiary">Today sales</p>
              <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatMoney(data.todaySales?.total ?? 0) }}</p>
              <p class="mt-1 text-sm text-text-secondary">All sale types</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-gradient-to-br from-indigo-500/10 to-emerald-500/5 p-4">
              <p class="text-sm font-medium text-text-tertiary">This month</p>
              <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatMoney(data.monthlySales?.total_sales ?? 0) }}</p>
              <p class="mt-1 text-sm text-text-secondary">{{ formatLiters(data.monthlySales?.total_liters ?? 0) }}L dispensed</p>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-border/70 bg-gradient-to-br from-amber-500/10 to-rose-500/5 p-4">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-text-tertiary">Vendor card receivable</p>
                <Receipt class="h-4 w-4 text-amber-600" />
              </div>
              <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatMoney(data.vendorCardReceivable ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-gradient-to-br from-sky-500/10 to-indigo-500/5 p-4">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-text-tertiary">Amanat balance</p>
                <HandCoins class="h-4 w-4 text-sky-600" />
              </div>
              <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatMoney(data.amanatSummary?.total_balance ?? 0) }}</p>
              <p class="mt-1 text-sm text-text-secondary">{{ data.amanatSummary?.total_holders ?? 0 }} holder(s)</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-base">
            <Warehouse class="h-4 w-4 text-sky-600" />
            Tank Levels
          </CardTitle>
          <CardDescription>Latest dip reading per tank.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="tankLevels.length === 0" class="rounded-xl border border-border/70 bg-muted/30 p-4 text-sm text-text-secondary">
            No tank levels available yet.
          </div>

          <div
            v-for="l in tankLevels"
            :key="l.tank?.id ?? l.itemName"
            class="rounded-xl border border-border/70 bg-surface-1 p-4"
          >
            <div class="flex items-start gap-4">
              <TankLevelGauge :percent="l.percent" :size="44" />
              <div class="flex-1">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-semibold text-text-primary">{{ l.tank?.name ?? 'Tank' }}</p>
                    <p class="mt-1 text-sm text-text-secondary">
                      <span class="inline-flex items-center gap-2">
                        <Droplet class="h-4 w-4 text-emerald-600" />
                        {{ l.itemName }}
                      </span>
                    </p>
                  </div>
                  <Badge variant="secondary" class="bg-sky-100 text-sky-800 hover:bg-sky-100">
                    {{ l.percent }}%
                  </Badge>
                </div>

                <div class="mt-3">
                  <Progress :value="l.percent" class="h-2 bg-slate-200/70" />
                  <div class="mt-2 flex items-center justify-between text-xs text-text-tertiary">
                    <span>{{ formatLiters(l.current) }}L</span>
                    <span v-if="l.capacity > 0">{{ formatLiters(l.capacity) }}L capacity</span>
                    <span v-else>Capacity unknown</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-base">
            <BarChart3 class="h-4 w-4 text-sky-600" />
            Sales By Type (Today)
          </CardTitle>
          <CardDescription>Counts and totals grouped by sale type.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="saleTypesToday.length === 0" class="rounded-xl border border-border/70 bg-muted/30 p-4 text-sm text-text-secondary">
            No sales recorded today.
          </div>

          <div
            v-for="s in saleTypesToday"
            :key="s.type"
            class="flex items-center justify-between rounded-xl border border-border/70 bg-surface-1 p-4"
          >
            <div>
              <p class="font-semibold text-text-primary">{{ s.type.replace('_', ' ') }}</p>
              <p class="mt-1 text-sm text-text-secondary">{{ s.count }} sale(s)</p>
            </div>
            <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">
              {{ formatMoney(s.total) }}
            </Badge>
          </div>

          <div v-if="currentRates.length > 0" class="mt-4 rounded-xl border border-border/70 bg-gradient-to-br from-sky-500/10 to-emerald-500/10 p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <TrendingUp class="h-4 w-4 text-sky-700" />
                <p class="font-semibold text-text-primary">Current rates</p>
              </div>
              <Button variant="outline" size="sm" @click="router.get(`/${companySlug}/fuel/rates`)">
                Manage <ArrowRight class="ml-2 h-4 w-4" />
              </Button>
            </div>
            <div class="mt-3 space-y-2">
              <div v-for="r in currentRates" :key="r.item?.id ?? r.effective_date" class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                  <Badge variant="secondary" class="bg-sky-100 text-sky-800 hover:bg-sky-100">
                    {{ r.item?.fuel_category ?? 'fuel' }}
                  </Badge>
                  <span class="font-medium text-text-primary">{{ r.item?.name ?? 'Fuel' }}</span>
                </div>
                <span class="text-text-secondary">{{ formatMoney(r.sale_rate ?? 0) }} / L</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
