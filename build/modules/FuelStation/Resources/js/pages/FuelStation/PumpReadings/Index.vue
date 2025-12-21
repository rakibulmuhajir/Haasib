<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, Gauge, Moon, Plus, SunMedium } from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface PumpRef {
  id: string
  name: string
  is_active: boolean
  tank?: { name: string; linked_item?: FuelItemRef | null } | null
}

type Shift = 'day' | 'night'

type Paginated<T> = {
  data: T[]
  current_page: number
  per_page: number
  total: number
}

interface PumpReadingRow {
  id: string
  pump_id: string
  reading_date: string
  shift: Shift
  opening_meter: number
  closing_meter: number
  liters_dispensed: number
  pump?: PumpRef | null
  item?: FuelItemRef | null
}

const props = withDefaults(
  defineProps<{
    readings: Paginated<PumpReadingRow>
    pumps: PumpRef[]
    shifts: Shift[]
  }>(),
  {
    pumps: () => [],
    shifts: () => ['day', 'night'],
  }
)

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/pump-readings` },
  { title: 'Pump Readings', href: `/${companySlug.value}/fuel/pump-readings` },
])

const formatQty = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n ?? 0)

const readingsPage = computed(
  () => props.readings ?? ({ data: [], current_page: 1, per_page: 50, total: 0 } as Paginated<PumpReadingRow>)
)
const rows = computed(() => readingsPage.value.data ?? [])

const stats = computed(() => {
  const list = rows.value
  const day = list.filter((r) => r.shift === 'day').length
  const night = list.filter((r) => r.shift === 'night').length
  const liters = list.reduce((sum, r) => sum + Number(r.liters_dispensed ?? 0), 0)
  return { total: list.length, day, night, liters }
})

const pumpFilter = ref<string>('all')
const shiftFilter = ref<string>('all')

const filtered = computed(() => {
  return rows.value.filter((r) => {
    if (pumpFilter.value !== 'all' && r.pump_id !== pumpFilter.value) return false
    if (shiftFilter.value !== 'all' && r.shift !== shiftFilter.value) return false
    return true
  })
})

const columns = [
  { key: 'reading_date', label: 'Date' },
  { key: 'pump', label: 'Pump' },
  { key: 'shift', label: 'Shift' },
  { key: 'opening', label: 'Opening' },
  { key: 'closing', label: 'Closing' },
  { key: 'liters', label: 'Liters' },
]

const tableData = computed(() =>
  filtered.value
    .slice()
    .sort((a, b) => new Date(b.reading_date).getTime() - new Date(a.reading_date).getTime())
    .map((r) => ({
      id: r.id,
      reading_date: r.reading_date,
      pump: r.pump?.name ?? '—',
      shift: r.shift,
      opening: formatQty(r.opening_meter),
      closing: formatQty(r.closing_meter),
      liters: formatQty(r.liters_dispensed),
      _raw: r,
    }))
)

const pagination = computed(() => ({
  currentPage: readingsPage.value.current_page,
  perPage: readingsPage.value.per_page,
  total: readingsPage.value.total,
}))

const handlePageChange = (pageNum: number) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/pump-readings`, { page: pageNum }, { preserveScroll: true, preserveState: true })
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

const form = useForm<{
  pump_id: string
  reading_date: string
  shift: Shift
  opening_meter: number | null
  closing_meter: number | null
}>({
  pump_id: '',
  reading_date: new Date().toISOString().slice(0, 10),
  shift: 'day',
  opening_meter: null,
  closing_meter: null,
})

const litersPreview = computed(() => {
  const o = Number(form.opening_meter ?? 0)
  const c = Number(form.closing_meter ?? 0)
  const diff = c - o
  if (!Number.isFinite(diff) || diff < 0) return null
  return diff
})

const submit = () => {
  const slug = companySlug.value
  if (!slug) return
  form.post(`/${slug}/fuel/pump-readings`, {
    preserveScroll: true,
    onSuccess: () => closeDialog(),
  })
}
</script>

<template>
  <Head title="Pump Readings" />

  <PageShell
    title="Pump Readings"
    description="Capture day/night shift meters and liters dispensed."
    :icon="Gauge"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        New reading
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total readings</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-sky-200 text-sky-700">
            {{ props.pumps.length }} pump(s)
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Day shift</CardDescription>
          <CardTitle class="text-2xl">{{ stats.day }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-amber-100 text-amber-800 hover:bg-amber-100">
            <SunMedium class="mr-1 h-3.5 w-3.5" /> day
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Night shift</CardDescription>
          <CardTitle class="text-2xl">{{ stats.night }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-indigo-100 text-indigo-800 hover:bg-indigo-100">
            <Moon class="mr-1 h-3.5 w-3.5" /> night
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Liters captured</CardDescription>
          <CardTitle class="text-2xl">{{ formatQty(stats.liters) }}L</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="secondary" class="bg-emerald-100 text-emerald-800 hover:bg-emerald-100">
            Meter delta
          </Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Reading List</CardTitle>
            <CardDescription>Unique per pump + date + shift.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Select v-model="pumpFilter">
              <SelectTrigger class="w-[220px]">
                <SelectValue placeholder="All pumps" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All pumps</SelectItem>
                <SelectItem v-for="p in pumps" :key="p.id" :value="p.id">
                  {{ p.name }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="shiftFilter">
              <SelectTrigger class="w-[160px]">
                <SelectValue placeholder="All shifts" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All shifts</SelectItem>
                <SelectItem v-for="s in props.shifts" :key="s" :value="s">{{ s }}</SelectItem>
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
          @page-change="handlePageChange"
        >
          <template #empty>
            <EmptyState
              title="No pump readings yet"
              description="Capture the first shift reading to start reconciliation."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  New reading
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-pump="{ row }">
            <div class="space-y-1">
              <p class="font-medium text-text-primary">{{ row._raw.pump?.name ?? row.pump }}</p>
              <p class="text-xs text-text-tertiary">
                <span v-if="row._raw.pump?.tank?.linked_item?.name">{{ row._raw.pump.tank.linked_item.name }}</span>
                <span v-else-if="row._raw.item?.name">{{ row._raw.item.name }}</span>
                <span v-else>Fuel item</span>
              </p>
            </div>
          </template>

          <template #cell-shift="{ row }">
            <Badge
              :class="row._raw.shift === 'day' ? 'bg-amber-100 text-amber-800 hover:bg-amber-100' : 'bg-indigo-100 text-indigo-800 hover:bg-indigo-100'"
            >
              {{ row._raw.shift }}
            </Badge>
          </template>

          <template #cell-reading_date="{ row }">
            <div class="flex items-center gap-2">
              <CalendarDays class="h-4 w-4 text-text-tertiary" />
              <span class="font-medium text-text-primary">{{ row.reading_date }}</span>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Gauge class="h-5 w-5 text-sky-600" />
            New pump reading
          </DialogTitle>
          <DialogDescription>
            Liters dispensed are calculated as closing − opening.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="pump_id">Pump</Label>
              <Select v-model="form.pump_id">
                <SelectTrigger id="pump_id" :class="{ 'border-destructive': form.errors.pump_id }">
                  <SelectValue placeholder="Select a pump..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="p in pumps" :key="p.id" :value="p.id">
                    {{ p.name }}
                    <span v-if="p.tank?.linked_item?.name"> • {{ p.tank.linked_item.name }}</span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.pump_id" class="text-sm text-destructive">{{ form.errors.pump_id }}</p>
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

          <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
              <Label for="shift">Shift</Label>
              <Select v-model="form.shift">
                <SelectTrigger id="shift" :class="{ 'border-destructive': form.errors.shift }">
                  <SelectValue placeholder="Select shift..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="s in props.shifts" :key="s" :value="s">{{ s }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.shift" class="text-sm text-destructive">{{ form.errors.shift }}</p>
            </div>

            <div class="space-y-2">
              <Label for="opening_meter">Opening meter</Label>
              <Input
                id="opening_meter"
                v-model.number="form.opening_meter"
                type="number"
                min="0"
                step="0.01"
                :class="{ 'border-destructive': form.errors.opening_meter }"
              />
              <p v-if="form.errors.opening_meter" class="text-sm text-destructive">{{ form.errors.opening_meter }}</p>
            </div>

            <div class="space-y-2">
              <Label for="closing_meter">Closing meter</Label>
              <Input
                id="closing_meter"
                v-model.number="form.closing_meter"
                type="number"
                min="0"
                step="0.01"
                :class="{ 'border-destructive': form.errors.closing_meter }"
              />
              <p v-if="form.errors.closing_meter" class="text-sm text-destructive">{{ form.errors.closing_meter }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-border/70 bg-muted/30 p-4">
            <p class="text-sm font-medium text-text-primary">Preview</p>
            <p class="mt-1 text-sm text-text-secondary">
              {{ litersPreview === null ? 'Enter meters to preview liters dispensed.' : `Liters dispensed: ${formatQty(litersPreview)}L` }}
            </p>
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
              Save
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
