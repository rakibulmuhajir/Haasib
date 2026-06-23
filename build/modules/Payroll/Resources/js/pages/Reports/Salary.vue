<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { Banknote, Calendar, FileText, HandCoins, UserCog } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface SalaryRow {
  employee_id: string
  employee_number: string
  employee_name: string
  position: string | null
  base_salary: number
  payslip_count: number
  gross_pay: number
  deductions: number
  net_pay: number
  paid: number
  unpaid: number
  draft: number
  advance_given: number
  advance_recovered: number
  advance_outstanding: number
  payslips: Array<{
    id: string
    payslip_number: string
    status: string
    gross_pay: number
    deductions: number
    net_pay: number
    period_id: string
    period_start: string | null
    period_end: string | null
  }>
}

interface EmployeeOption {
  id: string
  label: string
}

const props = defineProps<{
  company: CompanyRef
  filters: {
    month: string
    start_date: string
    end_date: string
    employee_id: string
    status: string
  }
  summary: {
    employees: number
    gross_pay: number
    deductions: number
    net_pay: number
    paid: number
    unpaid: number
    draft: number
    advance_given: number
    advance_recovered: number
    advance_outstanding: number
  }
  employeeOptions: EmployeeOption[]
  rows: SalaryRow[]
}>()

const month = ref(props.filters.month)
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const employeeId = ref(props.filters.employee_id || 'all')
const status = ref(props.filters.status || 'all')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/daily-close/history` },
  { title: 'Salary Report', href: `/${props.company.slug}/payroll/reports/salary` },
]

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: props.company.base_currency || 'PKR',
  }).format(amount || 0)
}

const formatDate = (date: string) => formatDateTime(date, { mode: 'date' })

const applyFilter = () => {
  router.get(
    `/${props.company.slug}/payroll/reports/salary`,
    {
      month: month.value,
      start_date: startDate.value,
      end_date: endDate.value,
      employee_id: employeeId.value === 'all' ? '' : employeeId.value,
      status: status.value === 'all' ? '' : status.value,
    },
    { preserveState: true, preserveScroll: true }
  )
}

const columns = [
  { key: 'employee', label: 'Employee' },
  { key: 'base_salary', label: 'Base Salary' },
  { key: 'gross_pay', label: 'Gross' },
  { key: 'deductions', label: 'Deductions' },
  { key: 'net_pay', label: 'Net Salary' },
  { key: 'paid', label: 'Paid' },
  { key: 'unpaid', label: 'Unpaid' },
  { key: 'advance_outstanding', label: 'Advance Balance' },
]

const tableRows = computed(() => props.rows.map((row) => ({
  id: row.employee_id,
  employee: `${row.employee_name} · ${row.employee_number}`,
  base_salary: formatCurrency(row.base_salary),
  gross_pay: formatCurrency(row.gross_pay),
  deductions: formatCurrency(row.deductions),
  net_pay: formatCurrency(row.net_pay),
  paid: formatCurrency(row.paid),
  unpaid: formatCurrency(row.unpaid + row.draft),
  advance_outstanding: formatCurrency(row.advance_outstanding),
  _raw: row,
})))

const payslipRows = computed(() => props.rows.flatMap((row) =>
  row.payslips.map((payslip) => ({
    ...payslip,
    employee_id: row.employee_id,
    employee_name: row.employee_name,
    employee_number: row.employee_number,
  }))
))

const statusVariant = (value: string) => {
  if (value === 'paid') return 'success'
  if (value === 'approved') return 'secondary'
  if (value === 'draft') return 'outline'
  return 'secondary'
}

const openPayslips = (row: SalaryRow) => {
  router.get(`/${props.company.slug}/payslips`, {
    employee_id: row.employee_id,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    status: status.value === 'all' ? '' : status.value,
  })
}
</script>

<template>
  <Head title="Salary Report" />

  <PageShell
    title="Salary Report"
    description="Monthly salary, paid/unpaid amounts, deductions, and advance balances."
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payroll`)">
        <Banknote class="mr-2 h-4 w-4" />
        Payroll
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/employees`)">
        <UserCog class="mr-2 h-4 w-4" />
        Employees
      </Button>
    </template>

    <Card>
      <CardContent class="pt-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p class="text-sm text-muted-foreground">Report period</p>
            <p class="mt-1 font-medium">{{ formatDate(filters.start_date) }} - {{ formatDate(filters.end_date) }}</p>
          </div>
          <div class="grid gap-3 md:grid-cols-5">
            <div class="space-y-2">
              <Label for="month">Month</Label>
              <Input id="month" v-model="month" type="month" class="w-[180px]" />
            </div>
            <div class="space-y-2">
              <Label for="start_date">Start</Label>
              <Input id="start_date" v-model="startDate" type="date" />
            </div>
            <div class="space-y-2">
              <Label for="end_date">End</Label>
              <Input id="end_date" v-model="endDate" type="date" />
            </div>
            <div class="space-y-2">
              <Label>Employee</Label>
              <Select v-model="employeeId">
                <SelectTrigger>
                  <SelectValue placeholder="All employees" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All employees</SelectItem>
                  <SelectItem v-for="employee in employeeOptions" :key="employee.id" :value="employee.id">
                    {{ employee.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Status</Label>
              <Select v-model="status">
                <SelectTrigger>
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="approved">Approved</SelectItem>
                  <SelectItem value="paid">Paid</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button @click="applyFilter">
              <Calendar class="mr-2 h-4 w-4" />
              Apply
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <Card>
        <CardContent class="pt-6">
          <p class="text-sm text-muted-foreground">Gross salary</p>
          <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.gross_pay) }}</p>
          <p class="mt-1 text-xs text-muted-foreground">{{ summary.employees }} employees</p>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <p class="text-sm text-muted-foreground">Net salary</p>
          <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.net_pay) }}</p>
          <p class="mt-1 text-xs text-muted-foreground">{{ formatCurrency(summary.deductions) }} deductions</p>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <p class="text-sm text-muted-foreground">Paid / unpaid</p>
          <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.paid) }}</p>
          <p class="mt-1 text-xs text-muted-foreground">{{ formatCurrency(summary.unpaid + summary.draft) }} unpaid or draft</p>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <p class="text-sm text-muted-foreground">Advance balance</p>
          <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.advance_outstanding) }}</p>
          <p class="mt-1 text-xs text-muted-foreground">{{ formatCurrency(summary.advance_recovered) }} recovered this month</p>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader class="pb-3">
        <div class="flex items-center justify-between gap-3">
          <div>
            <CardTitle>Employee Salary Details</CardTitle>
            <CardDescription>Click an employee to open their statement.</CardDescription>
          </div>
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/payslips`)">
            <FileText class="mr-2 h-4 w-4" />
            Payslips
          </Button>
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/salary-advances`)">
            <HandCoins class="mr-2 h-4 w-4" />
            Advances
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :data="tableRows" @row-click="(row) => router.get(`/${company.slug}/employees/${row.id}`)">
          <template #cell-employee="{ row }">
            <div>
              <p class="font-medium">{{ row._raw.employee_name }}</p>
              <p class="text-xs text-muted-foreground">
                {{ row._raw.employee_number }}<span v-if="row._raw.position"> · {{ row._raw.position }}</span>
              </p>
              <div class="mt-2 flex gap-2">
                <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click.stop="router.get(`/${company.slug}/employees/${row.id}`)">
                  Employee
                </Button>
                <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click.stop="openPayslips(row._raw)">
                  Payslips
                </Button>
                <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click.stop="router.get(`/${company.slug}/salary-advances?employee_id=${row.id}`)">
                  Advances
                </Button>
              </div>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Payslips Included</CardTitle>
        <CardDescription>These are the payslips behind the salary totals for the selected filters.</CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="payslipRows.length === 0" class="py-6 text-sm text-muted-foreground">
          No payslips matched these filters.
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="payslip in payslipRows"
            :key="payslip.id"
            class="grid grid-cols-12 gap-3 rounded-md border p-3 text-sm"
          >
            <div class="col-span-3">
              <Button variant="link" class="h-auto p-0 font-medium" @click="router.get(`/${company.slug}/payslips/${payslip.id}`)">
                {{ payslip.payslip_number }}
              </Button>
              <p class="text-xs text-muted-foreground">{{ payslip.employee_name }} · {{ payslip.employee_number }}</p>
            </div>
            <div class="col-span-3">
              <Button
                v-if="payslip.period_id"
                variant="link"
                class="h-auto p-0"
                @click="router.get(`/${company.slug}/payroll-periods/${payslip.period_id}`)"
              >
                {{ payslip.period_start ? formatDate(payslip.period_start) : 'Period' }} - {{ payslip.period_end ? formatDate(payslip.period_end) : '' }}
              </Button>
              <span v-else class="text-muted-foreground">No period</span>
            </div>
            <div class="col-span-2">
              <Badge :variant="statusVariant(payslip.status)">
                {{ payslip.status }}
              </Badge>
            </div>
            <div class="col-span-2 text-right tabular-nums">{{ formatCurrency(payslip.deductions) }}</div>
            <div class="col-span-2 text-right font-medium tabular-nums">{{ formatCurrency(payslip.net_pay) }}</div>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>How To Read It</CardTitle>
      </CardHeader>
      <CardContent class="grid gap-3 text-sm md:grid-cols-3">
        <div class="rounded-lg border p-3">
          <p class="font-medium">Salary</p>
          <p class="mt-1 text-muted-foreground">Comes from employee profiles and generated payslips.</p>
        </div>
        <div class="rounded-lg border p-3">
          <p class="font-medium">Unpaid</p>
          <p class="mt-1 text-muted-foreground">Approved salaries still waiting for cash or bank payment.</p>
        </div>
        <div class="rounded-lg border p-3">
          <p class="font-medium">Advances</p>
          <p class="mt-1 text-muted-foreground">Daily Close advances are recovered automatically through payroll deductions.</p>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
