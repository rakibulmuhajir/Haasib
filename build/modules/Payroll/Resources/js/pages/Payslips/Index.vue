<script setup lang="ts">
import { computed } from 'vue'
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
  FileText,
  Plus,
  Eye,
  CheckCircle,
  DollarSign,
  Trash2,
  MoreHorizontal,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Employee {
  id: string
  first_name: string
  last_name: string
  employee_number: string
}

interface Period {
  id: string
  period_start: string
  period_end: string
}

interface PayslipRow {
  id: string
  payslip_number: string
  employee: Employee
  payroll_period: Period
  currency: string
  gross_pay: number
  net_pay: number
  status: string
}

interface PaginatedPayslips {
  data: PayslipRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  payslips: PaginatedPayslips
  filters: {
    search: string
    status: string
    period_id: string
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payslips', href: `/${props.company.slug}/payslips` },
]

const columns = [
  { key: 'payslip_number', label: 'Number' },
  { key: 'employee', label: 'Employee' },
  { key: 'period', label: 'Period' },
  { key: 'net_pay', label: 'Net Pay' },
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

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency || 'USD',
  }).format(amount)
}

const tableData = computed(() => {
  return props.payslips.data.map((payslip) => ({
    id: payslip.id,
    payslip_number: payslip.payslip_number,
    employee: `${payslip.employee.first_name} ${payslip.employee.last_name}`,
    period: `${formatDate(payslip.payroll_period.period_start)} - ${formatDate(payslip.payroll_period.period_end)}`,
    net_pay: formatCurrency(payslip.net_pay, payslip.currency),
    status: payslip.status,
    _raw: payslip,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/payslips/${row.id}`)
}

const handleApprove = (id: string) => {
  router.post(`/${props.company.slug}/payslips/${id}/approve`)
}

const handleMarkPaid = (id: string) => {
  router.post(`/${props.company.slug}/payslips/${id}/mark-paid`)
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this payslip?')) {
    router.delete(`/${props.company.slug}/payslips/${id}`)
  }
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    approved: 'secondary',
    paid: 'success',
    cancelled: 'destructive',
  }
  return variants[status] || 'secondary'
}

const formatStatus = (status: string) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}
</script>

<template>
  <Head title="Payslips" />

  <PageShell
    title="Payslips"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/payslips/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Create Payslip
      </Button>
    </template>

    <!-- Empty State -->
    <EmptyState
      v-if="payslips.data.length === 0"
      title="No payslips yet"
      description="Create payslips to process employee payments."
      :icon="FileText"
    >
      <Button @click="router.get(`/${company.slug}/payslips/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Create Payslip
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: payslips.current_page,
        lastPage: payslips.last_page,
        perPage: payslips.per_page,
        total: payslips.total,
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
            <DropdownMenuItem @click="router.get(`/${company.slug}/payslips/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="row._raw.status === 'draft'"
              @click="handleApprove(row.id)"
            >
              <CheckCircle class="mr-2 h-4 w-4" />
              Approve
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="row._raw.status === 'approved'"
              @click="handleMarkPaid(row.id)"
            >
              <DollarSign class="mr-2 h-4 w-4" />
              Mark Paid
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="row._raw.status === 'draft'"
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
