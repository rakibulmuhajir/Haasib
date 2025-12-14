<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Calendar,
  Plus,
  Eye,
  Lock,
  Trash2,
  MoreHorizontal,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface PeriodRow {
  id: string
  period_start: string
  period_end: string
  payment_date: string
  status: string
  payslips_count: number
}

interface PaginatedPeriods {
  data: PeriodRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  periods: PaginatedPeriods
  filters: {
    search: string
    status: string
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payroll Periods', href: `/${props.company.slug}/payroll-periods` },
]

const columns = [
  { key: 'period', label: 'Period' },
  { key: 'payment_date', label: 'Payment Date' },
  { key: 'payslips_count', label: 'Payslips' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const tableData = computed(() => {
  return props.periods.data.map((period) => ({
    id: period.id,
    period: `${formatDate(period.period_start)} - ${formatDate(period.period_end)}`,
    payment_date: formatDate(period.payment_date),
    payslips_count: period.payslips_count,
    status: period.status,
    _raw: period,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/payroll-periods/${row.id}`)
}

const handleClose = (id: string) => {
  if (confirm('Are you sure you want to close this payroll period?')) {
    router.post(`/${props.company.slug}/payroll-periods/${id}/close`)
  }
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this payroll period?')) {
    router.delete(`/${props.company.slug}/payroll-periods/${id}`)
  }
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    open: 'outline',
    processing: 'secondary',
    closed: 'success',
    posted: 'success',
  }
  return variants[status] || 'secondary'
}

const formatStatus = (status: string) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}
</script>

<template>
  <Head title="Payroll Periods" />

  <PageShell
    title="Payroll Periods"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/payroll-periods/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Period
      </Button>
    </template>

    <!-- Empty State -->
    <EmptyState
      v-if="periods.data.length === 0"
      title="No payroll periods yet"
      description="Create a payroll period to start processing payroll."
      :icon="Calendar"
    >
      <Button @click="router.get(`/${company.slug}/payroll-periods/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Period
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: periods.current_page,
        lastPage: periods.last_page,
        perPage: periods.per_page,
        total: periods.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-status="{ row }">
        <Badge :variant="getStatusVariant(row._raw.status)">
          {{ formatStatus(row.status) }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/payroll-periods/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="row._raw.status === 'open'"
              @click="handleClose(row.id)"
            >
              <Lock class="mr-2 h-4 w-4" />
              Close Period
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="row._raw.status === 'open'"
              class="text-destructive"
              @click="handleDelete(row.id)"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
    </DataTable>
  </PageShell>
</template>
