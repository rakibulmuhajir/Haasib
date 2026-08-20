<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { CalendarDays, Plus, Eye, Pencil } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface AccountingPeriod {
  id: string
  period_number: number
  name: string
  start_date: string
  end_date: string
  is_closed: boolean
}

interface FiscalYearRow {
  id: string
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  is_closed: boolean
  periods: AccountingPeriod[]
}

const props = defineProps<{
  company: CompanyRef
  fiscalYears: FiscalYearRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fiscal Years', href: `/${props.company.slug}/fiscal-years` },
]

const rows = computed(() =>
  props.fiscalYears.map((fy) => ({
    ...fy,
    period_count: fy.periods?.length ?? 0,
    closed_period_count: fy.periods?.filter((p) => p.is_closed).length ?? 0,
  }))
)

/**
 * Three flags collapse to one state, named in the shared vocabulary so a closed
 * fiscal year and a closed period read identically wherever either appears.
 */
const yearStatus = (fy: FiscalYearRow) => {
  if (fy.is_closed) return 'closed'
  if (fy.is_current) return 'current'
  return 'open'
}

const columns = [
  { key: 'name', label: 'Name', kind: 'text' as const },
  { key: 'dates', label: 'Dates', kind: 'date' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'periods_closed', label: 'Periods closed', kind: 'amount' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })
</script>

<template>
  <Head title="Fiscal Years" />
  <PageShell
    title="Fiscal Years"
    :breadcrumbs="breadcrumbs"
    :icon="CalendarDays"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/fiscal-years/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Fiscal Year
      </Button>
    </template>

    <Card>
      <CardHeader>
        <CardTitle>Fiscal years and accounting periods</CardTitle>
      </CardHeader>
      <CardContent>
        <LedgerRegister :data="rows" :columns="columns">
          <template #empty>No fiscal years yet.</template>

          <template #cell-dates="{ row }">
            {{ formatDate(row.start_date) }} → {{ formatDate(row.end_date) }}
          </template>

          <template #cell-status="{ row }">
            <StatusBadge :status="yearStatus(row)" />
          </template>

          <template #cell-periods_closed="{ row }">
            {{ row.closed_period_count }}/{{ row.period_count }}
          </template>

          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Button size="sm" variant="outline" @click="router.get(`/${company.slug}/fiscal-years/${row.id}`)">
                <Eye class="mr-2 h-4 w-4" />
                View
              </Button>
              <Button size="sm" variant="secondary" @click="router.get(`/${company.slug}/fiscal-years/${row.id}/edit`)">
                <Pencil class="mr-2 h-4 w-4" />
                Edit
              </Button>
            </div>
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
