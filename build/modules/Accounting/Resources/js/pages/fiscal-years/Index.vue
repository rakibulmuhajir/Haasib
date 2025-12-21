<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
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

const badgeVariant = (fy: FiscalYearRow) => {
  if (fy.is_closed) return 'outline'
  if (fy.is_current) return 'success'
  return 'secondary'
}

const badgeLabel = (fy: FiscalYearRow) => {
  if (fy.is_closed) return 'Closed'
  if (fy.is_current) return 'Current'
  return 'Open'
}
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
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Dates</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Periods</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="fy in rows" :key="fy.id">
              <TableCell class="font-medium">{{ fy.name }}</TableCell>
              <TableCell>
                {{ new Date(fy.start_date).toLocaleDateString() }} → {{ new Date(fy.end_date).toLocaleDateString() }}
              </TableCell>
              <TableCell>
                <Badge :variant="badgeVariant(fy)">{{ badgeLabel(fy) }}</Badge>
              </TableCell>
              <TableCell class="text-right">
                {{ fy.closed_period_count }}/{{ fy.period_count }}
              </TableCell>
              <TableCell class="text-right space-x-2">
                <Button
                  size="sm"
                  variant="outline"
                  @click="router.get(`/${company.slug}/fiscal-years/${fy.id}`)"
                >
                  <Eye class="mr-2 h-4 w-4" />
                  View
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  @click="router.get(`/${company.slug}/fiscal-years/${fy.id}/edit`)"
                >
                  <Pencil class="mr-2 h-4 w-4" />
                  Edit
                </Button>
              </TableCell>
            </TableRow>
            <TableRow v-if="rows.length === 0">
              <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                No fiscal years yet.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </PageShell>
</template>

