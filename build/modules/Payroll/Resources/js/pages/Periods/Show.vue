<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Lock, Calendar, FileText, Plus } from 'lucide-vue-next'
import { computed } from 'vue'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface PayslipSummary {
  id: string
  payslip_number: string
  employee_name: string
  net_pay: number
  currency: string
  status: string
}

interface Period {
  id: string
  period_start: string
  period_end: string
  payment_date: string
  status: string
  payslips: PayslipSummary[]
  payslips_count: number
  total_gross: number
  total_net: number
  currency: string
}

const props = defineProps<{
  company: CompanyRef
  period: Period
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payroll Periods', href: `/${props.company.slug}/payroll-periods` },
  { title: formatPeriodLabel(props.period), href: `/${props.company.slug}/payroll-periods/${props.period.id}` },
]

function formatPeriodLabel(period: Period) {
  const start = new Date(period.period_start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  const end = new Date(period.period_end).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  return `${start} - ${end}`
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
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

const getPayslipStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    approved: 'secondary',
    paid: 'success',
    cancelled: 'destructive',
  }
  return variants[status] || 'secondary'
}

const columns = [
  { key: 'payslip_number', label: 'Number' },
  { key: 'employee_name', label: 'Employee' },
  { key: 'net_pay', label: 'Net Pay' },
  { key: 'status', label: 'Status' },
]

const tableData = computed(() => {
  return props.period.payslips.map((ps) => ({
    id: ps.id,
    payslip_number: ps.payslip_number,
    employee_name: ps.employee_name,
    net_pay: formatCurrency(ps.net_pay, ps.currency),
    status: ps.status,
    _raw: ps,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/payslips/${row.id}`)
}

const handleClose = () => {
  if (confirm('Are you sure you want to close this payroll period? This action cannot be undone.')) {
    router.post(`/${props.company.slug}/payroll-periods/${props.period.id}/close`)
  }
}
</script>

<template>
  <Head :title="`Payroll Period - ${formatPeriodLabel(period)}`" />

  <PageShell
    :title="formatPeriodLabel(period)"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payroll-periods`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button
        v-if="period.status === 'open'"
        variant="outline"
        @click="router.get(`/${company.slug}/payslips/create?period_id=${period.id}`)"
      >
        <Plus class="mr-2 h-4 w-4" />
        Create Payslip
      </Button>
      <Button v-if="period.status === 'open'" @click="handleClose">
        <Lock class="mr-2 h-4 w-4" />
        Close Period
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Summary Cards -->
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Status</p>
              <Badge :variant="getStatusVariant(period.status)" class="mt-1">
                {{ formatStatus(period.status) }}
              </Badge>
            </div>
            <Calendar class="h-8 w-8 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Payslips</p>
              <p class="text-2xl font-bold">{{ period.payslips_count }}</p>
            </div>
            <FileText class="h-8 w-8 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div>
            <p class="text-sm text-muted-foreground">Total Gross</p>
            <p class="text-2xl font-bold">{{ formatCurrency(period.total_gross, period.currency) }}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div>
            <p class="text-sm text-muted-foreground">Total Net</p>
            <p class="text-2xl font-bold text-primary">{{ formatCurrency(period.total_net, period.currency) }}</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Period Details -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Period Details</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <p class="text-muted-foreground">Period Start</p>
            <p class="font-medium">{{ formatDate(period.period_start) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Period End</p>
            <p class="font-medium">{{ formatDate(period.period_end) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Payment Date</p>
            <p class="font-medium">{{ formatDate(period.payment_date) }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Payslips Table -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Payslips in this Period</CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="period.payslips.length === 0" class="text-center py-8 text-muted-foreground">
          No payslips created for this period yet.
          <Button
            v-if="period.status === 'open'"
            variant="link"
            @click="router.get(`/${company.slug}/payslips/create?period_id=${period.id}`)"
          >
            Create the first payslip
          </Button>
        </div>
        <DataTable
          v-else
          :columns="columns"
          :data="tableData"
          @row-click="handleRowClick"
        >
          <template #cell-status="{ row }">
            <Badge :variant="getPayslipStatusVariant(row._raw.status)">
              {{ formatStatus(row.status) }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
