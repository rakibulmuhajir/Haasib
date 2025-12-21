<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Droplet, Fuel, Gauge, Moon, SunMedium } from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface TankRef {
  id: string
  name: string
  linked_item?: FuelItemRef | null
}

interface PumpReadingRow {
  id: string
  reading_date: string
  shift: 'day' | 'night'
  opening_meter: number
  closing_meter: number
  liters_dispensed: number
}

interface Pump {
  id: string
  name: string
  tank_id: string
  current_meter_reading: number
  is_active: boolean
  tank?: TankRef | null
  pump_readings?: PumpReadingRow[]
}

const props = defineProps<{
  pump: Pump
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
  { title: 'Fuel', href: `/${companySlug.value}/fuel/pumps` },
  { title: 'Pumps', href: `/${companySlug.value}/fuel/pumps` },
  { title: props.pump.name },
])

const formatQty = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)

const columns = [
  { key: 'reading_date', label: 'Date' },
  { key: 'shift', label: 'Shift' },
  { key: 'opening_meter', label: 'Opening' },
  { key: 'closing_meter', label: 'Closing' },
  { key: 'liters_dispensed', label: 'Liters' },
]

const tableData = computed(() =>
  (props.pump.pump_readings ?? []).map((r) => ({
    id: r.id,
    reading_date: r.reading_date,
    shift: r.shift,
    opening_meter: formatQty(r.opening_meter),
    closing_meter: formatQty(r.closing_meter),
    liters_dispensed: formatQty(r.liters_dispensed),
    _raw: r,
  }))
)

const fuelLabel = computed(() => props.pump.tank?.linked_item?.name ?? props.pump.tank?.linked_item?.fuel_category ?? '-')
</script>

<template>
  <Head :title="`${pump.name} • Pump`" />

  <PageShell
    :title="pump.name"
    description="Pump details and recent meter readings."
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/fuel/pumps`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Pumps
      </Button>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
      <Card class="border-border/80 lg:col-span-2">
        <CardHeader>
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle class="flex items-center gap-2">
                <Gauge class="h-5 w-5 text-sky-600" />
                Meter Snapshot
              </CardTitle>
              <CardDescription>Current meter and linkage context.</CardDescription>
            </div>

            <Badge
              :class="pump.is_active ? 'bg-emerald-600 text-white hover:bg-emerald-600' : 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200'"
            >
              {{ pump.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </div>
        </CardHeader>

        <CardContent class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-sky-500/10 to-indigo-500/5 p-4">
            <p class="text-sm font-medium text-text-tertiary">Current meter</p>
            <p class="mt-2 text-2xl font-semibold text-text-primary">
              {{ formatQty(pump.current_meter_reading) }}
            </p>
            <p class="mt-1 text-sm text-text-secondary">Units: liters on pump counter</p>
          </div>

          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-emerald-500/10 to-sky-500/5 p-4">
            <p class="text-sm font-medium text-text-tertiary">Fuel item</p>
            <div class="mt-2 flex items-center gap-2">
              <Droplet class="h-4 w-4 text-emerald-600" />
              <p class="text-lg font-semibold text-text-primary">
                {{ fuelLabel }}
              </p>
            </div>
            <p class="mt-1 text-sm text-text-secondary">
              Tank: {{ pump.tank?.name ?? '-' }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Shift Legend</CardTitle>
          <CardDescription>Reading labels used on this pump.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <SunMedium class="h-4 w-4 text-amber-600" />
              <span class="text-sm text-text-secondary">Day shift</span>
            </div>
            <Badge class="bg-amber-100 text-amber-800 hover:bg-amber-100">day</Badge>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Moon class="h-4 w-4 text-indigo-600" />
              <span class="text-sm text-text-secondary">Night shift</span>
            </div>
            <Badge class="bg-indigo-100 text-indigo-800 hover:bg-indigo-100">night</Badge>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Recent Readings</CardTitle>
        <CardDescription>Latest 20 readings captured for this pump.</CardDescription>
      </CardHeader>
      <CardContent class="p-0">
        <DataTable :data="tableData" :columns="columns">
          <template #cell-shift="{ row }">
            <Badge
              :class="row._raw.shift === 'day' ? 'bg-amber-100 text-amber-800 hover:bg-amber-100' : 'bg-indigo-100 text-indigo-800 hover:bg-indigo-100'"
            >
              {{ row._raw.shift }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>

