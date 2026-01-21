<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import TankLevelGauge from '../../../components/TankLevelGauge.vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import {
  AlertTriangle,
  ArrowRight,
  Plus,
  Warehouse,
} from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface TankRef {
  id: string
  name: string
  capacity?: number | null
  linked_item?: FuelItemRef | null
}

type TankReadingStatus = 'draft' | 'confirmed' | 'posted'
type VarianceType = 'loss' | 'gain' | 'none'

type Paginated<T> = {
  data: T[]
  current_page: number
  per_page: number
  total: number
}

interface TankReadingRow {
  id: string
  tank_id: string
  reading_date: string
  reading_type: 'opening' | 'closing' | 'spot_check'
  dip_measurement_liters: number
  system_calculated_liters: number
  variance_liters: number
  variance_type: VarianceType
  variance_reason?: string | null
  status: TankReadingStatus
  notes?: string | null
  tank?: TankRef | null
  item?: FuelItemRef | null
  journal_entry_id?: string | null
}

const props = defineProps<{
  readings: Paginated<TankReadingRow>
  tanks: TankRef[]
  varianceReasons: string[]
  readingTypes: string[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/tank-readings` },
  { title: 'Tank Readings', href: `/${companySlug.value}/fuel/tank-readings` },
])

const formatLiters = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n ?? 0)

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

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

const statusVariant = (s: TankReadingStatus) => {
  switch (s) {
    case 'posted':
      return 'bg-emerald-600 text-white hover:bg-emerald-600'
    case 'confirmed':
      return 'bg-sky-100 text-sky-800 hover:bg-sky-100'
    default:
      return 'bg-amber-100 text-amber-800 hover:bg-amber-100'
  }
}

const varianceBadge = (t: VarianceType, liters: number) => {
  if (t === 'none' || liters === 0) return { label: 'No variance', cls: 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200' }
  if (t === 'gain') return { label: `Gain • ${formatLiters(Math.abs(liters))}L`, cls: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100' }
  return { label: `Loss • ${formatLiters(Math.abs(liters))}L`, cls: 'bg-red-100 text-red-800 hover:bg-red-100' }
}

const readingsPage = computed(() => props.readings ?? ({ data: [], current_page: 1, per_page: 50, total: 0 } as Paginated<TankReadingRow>))
const rows = computed(() => readingsPage.value.data ?? [])
const tanks = computed(() => props.tanks ?? [])

const stats = computed(() => {
  const list = rows.value
  const draft = list.filter((r) => r.status === 'draft').length
  const confirmed = list.filter((r) => r.status === 'confirmed').length
  const posted = list.filter((r) => r.status === 'posted').length
  const losses = list.filter((r) => r.variance_type === 'loss' && r.variance_liters !== 0).length
  const gains = list.filter((r) => r.variance_type === 'gain' && r.variance_liters !== 0).length
  return { total: list.length, draft, confirmed, posted, losses, gains }
})

const statusFilter = ref<string>('all')
const tankFilter = ref<string>('all')
const typeFilter = ref<string>('all')

const filtered = computed(() => {
  return rows.value.filter((r) => {
    if (statusFilter.value !== 'all' && r.status !== statusFilter.value) return false
    if (tankFilter.value !== 'all' && r.tank_id !== tankFilter.value) return false
    if (typeFilter.value !== 'all' && r.reading_type !== typeFilter.value) return false
    return true
  })
})

const columns = [
  { key: 'reading_date', label: 'Date' },
  { key: 'tank', label: 'Tank' },
  { key: 'level', label: 'Level' },
  { key: 'reading_type', label: 'Type' },
  { key: 'dip', label: 'Dip (L)' },
  { key: 'system', label: 'System (L)' },
  { key: 'variance', label: 'Variance' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const sortedFiltered = computed(() =>
  filtered.value.slice().sort((a, b) => new Date(b.reading_date).getTime() - new Date(a.reading_date).getTime())
)

const tableData = computed(() =>
  sortedFiltered.value.map((r) => {
    const capacity = Number(r.tank?.capacity ?? 0)
    const dip = Number(r.dip_measurement_liters ?? 0)
    const level = capacity > 0 ? Math.min(100, Math.max(0, Math.round((dip / capacity) * 100))) : 0

    return {
      id: r.id,
      reading_date: formatDate(r.reading_date),
      tank: r.tank?.name ?? '—',
      level: `${level}%`,
      level_percent: level,
      reading_type: r.reading_type,
      dip: formatLiters(r.dip_measurement_liters),
      system: formatLiters(r.system_calculated_liters),
      variance: r.variance_liters,
      status: r.status,
      _raw: r,
    }
  })
)

const pagination = computed(() => ({
  currentPage: readingsPage.value.current_page,
  perPage: readingsPage.value.per_page,
  total: readingsPage.value.total,
}))

const handlePageChange = (pageNum: number) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/tank-readings`, { page: pageNum }, { preserveScroll: true, preserveState: true })
}

const dialogOpen = ref(false)

const openCreate = () => {
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

const closeDialog = () => {
  dialogOpen.value = false
  form.reset()
  form.clearErrors()
}

const readingTypes = computed(() => {
  const values = props.readingTypes?.length ? props.readingTypes : ['opening', 'closing', 'spot_check']
  return values.map((v) => ({
    value: v,
    label: v.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()),
  }))
})

const varianceReasons = computed(() => {
  const values = props.varianceReasons?.length ? props.varianceReasons : []
  return [
    { value: '', label: 'No reason' },
    ...values.map((v) => ({
      value: v,
      label: v.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()),
    })),
  ]
})

const form = useForm<{
  tank_id: string
  reading_date: string
  reading_type: 'opening' | 'closing' | 'spot_check'
  dip_measurement_liters: number | null
  variance_reason: string
  notes: string
}>({
  tank_id: '',
  reading_date: new Date().toISOString().slice(0, 10),
  reading_type: 'closing',
  dip_measurement_liters: null,
  variance_reason: '',
  notes: '',
})

const submit = () => {
  const slug = companySlug.value
  if (!slug) return
  form.post(`/${slug}/fuel/tank-readings`, {
    preserveScroll: true,
    onSuccess: () => closeDialog(),
  })
}

const goToShow = (row: any) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/tank-readings/${row.id}`)
}
</script>

<template>
  <Head title="Tank Readings" />

  <PageShell
    title="Tank Readings"
    description="View dip measurements and variance calculations. Readings are posted automatically during daily close."
    :icon="Warehouse"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        New reading
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
      <Card class="border-border/80 lg:col-span-2">
        <CardHeader class="pb-2">
          <CardDescription>Total readings</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-sky-200 text-sky-700">
            {{ tanks.length }} tank(s)
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Draft</CardDescription>
          <CardTitle class="text-2xl">{{ stats.draft }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-amber-100 text-amber-800 hover:bg-amber-100">Editable</Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Confirmed</CardDescription>
          <CardTitle class="text-2xl">{{ stats.confirmed }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-sky-100 text-sky-800 hover:bg-sky-100">Ready to post</Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Posted</CardDescription>
          <CardTitle class="text-2xl">{{ stats.posted }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">JE created</Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Variance</CardDescription>
          <CardTitle class="text-2xl">
            <span class="text-red-600">{{ stats.losses }}</span>
            <span class="text-text-tertiary">/</span>
            <span class="text-emerald-600">{{ stats.gains }}</span>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <p class="text-sm text-text-secondary">losses / gains</p>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Reading List</CardTitle>
            <CardDescription>Confirm and post to generate variance journals.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Select v-model="statusFilter">
              <SelectTrigger class="w-[180px]">
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="confirmed">Confirmed</SelectItem>
                <SelectItem value="posted">Posted</SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="tankFilter">
              <SelectTrigger class="w-[220px]">
                <SelectValue placeholder="All tanks" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All tanks</SelectItem>
                <SelectItem v-for="tank in tanks" :key="tank.id" :value="tank.id">
                  {{ tank.name }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="typeFilter">
              <SelectTrigger class="w-[180px]">
                <SelectValue placeholder="All types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All types</SelectItem>
                <SelectItem value="opening">Opening</SelectItem>
                <SelectItem value="closing">Closing</SelectItem>
                <SelectItem value="spot_check">Spot check</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable
          :data="tableData"
          :columns="columns"
          :pagination="pagination"
          clickable
          @row-click="goToShow"
          @page-change="handlePageChange"
        >
          <template #empty>
            <EmptyState
              title="No tank readings yet"
              description="Start with an opening or closing dip reading."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  New reading
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-reading_type="{ row }">
            <Badge variant="secondary" class="bg-indigo-100 text-indigo-800 hover:bg-indigo-100">
              {{ row._raw.reading_type.replace('_', ' ') }}
            </Badge>
          </template>

          <template #cell-level="{ row }">
            <div class="flex items-center gap-2">
              <TankLevelGauge :percent="row.level_percent" :size="30" />
              <span class="text-xs text-text-secondary">{{ row.level_percent }}%</span>
            </div>
          </template>

          <template #cell-variance="{ row }">
            <Badge :class="varianceBadge(row._raw.variance_type, row._raw.variance_liters).cls">
              {{ varianceBadge(row._raw.variance_type, row._raw.variance_liters).label }}
            </Badge>
          </template>

          <template #cell-status="{ row }">
            <Badge :class="statusVariant(row._raw.status)">
              {{ row._raw.status }}
            </Badge>
          </template>

          <template #cell-_actions="{ row }">
            <div class="flex items-center justify-end gap-2">
              <Button variant="outline" size="sm" @click.stop="goToShow(row)">
                <ArrowRight class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Warehouse class="h-5 w-5 text-sky-600" />
            New tank reading
          </DialogTitle>
          <DialogDescription>
            Record a manual dip reading. Variance is calculated and posted automatically during daily close.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="tank_id">Tank</Label>
            <Select v-model="form.tank_id">
              <SelectTrigger id="tank_id" :class="{ 'border-destructive': form.errors.tank_id }">
                <SelectValue placeholder="Select a tank..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="tank in tanks" :key="tank.id" :value="tank.id">
                  {{ tank.name }}<span v-if="tank.linked_item?.name"> • {{ tank.linked_item.name }}</span>
                </SelectItem>
              </SelectContent>
            </Select>
              <p v-if="form.errors.tank_id" class="text-sm text-destructive">{{ form.errors.tank_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="reading_date">Date</Label>
              <Input
                id="reading_date"
                v-model="form.reading_date"
                type="date"
                :class="{ 'border-destructive': form.errors.reading_date }"
              />
              <p v-if="form.errors.reading_date" class="text-sm text-destructive">{{ form.errors.reading_date }}</p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="reading_type">Reading type</Label>
            <Select v-model="form.reading_type">
              <SelectTrigger id="reading_type" :class="{ 'border-destructive': form.errors.reading_type }">
                <SelectValue placeholder="Select type..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in readingTypes" :key="t.value" :value="t.value">
                  {{ t.label }}
                </SelectItem>
              </SelectContent>
            </Select>
              <p v-if="form.errors.reading_type" class="text-sm text-destructive">{{ form.errors.reading_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="dip_measurement_liters">Dip measurement (liters)</Label>
              <Input
                id="dip_measurement_liters"
                v-model.number="form.dip_measurement_liters"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :class="{ 'border-destructive': form.errors.dip_measurement_liters }"
              />
              <p v-if="form.errors.dip_measurement_liters" class="text-sm text-destructive">
                {{ form.errors.dip_measurement_liters }}
              </p>
            </div>
          </div>

          <div class="rounded-xl border border-border/70 bg-muted/30 p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-2">
                <AlertTriangle class="mt-0.5 h-4 w-4 text-amber-600" />
                <div>
                  <p class="text-sm font-medium text-text-primary">Variance reason (optional)</p>
                  <p class="text-sm text-text-secondary">Use a reason code for audit clarity.</p>
                </div>
              </div>
              <Badge variant="outline" class="border-sky-200 text-sky-700">{{ currencyCode }}</Badge>
            </div>
            <div class="mt-3 space-y-2">
              <Label for="variance_reason">Reason</Label>
            <Select v-model="form.variance_reason">
              <SelectTrigger id="variance_reason" :class="{ 'border-destructive': form.errors.variance_reason }">
                <SelectValue placeholder="Select reason..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in varianceReasons" :key="r.value" :value="r.value">
                  {{ r.label }}
                </SelectItem>
              </SelectContent>
            </Select>
              <p v-if="form.errors.variance_reason" class="text-sm text-destructive">{{ form.errors.variance_reason }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              rows="3"
              placeholder="Optional notes (e.g., dip method, staff name, temperature)."
              :class="{ 'border-destructive': form.errors.notes }"
            />
            <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="closeDialog">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Save draft
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

  </PageShell>
</template>
