<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { Banknote, Calendar, FileText, UserCog, WalletCards } from 'lucide-vue-next'

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
}

const props = defineProps<{
  company: CompanyRef
  filters: {
    month: string
    start_date: string
    end_date: string
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
  rows: SalaryRow[]
}>()

const month = ref(props.filters.month)

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
  router.get(`/${props.company.slug}/payroll/reports/salary`, { month: month.value }, { preserveState: true })
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
          <div class="flex items-end gap-3">
            <div class="space-y-2">
              <Label for="month">Month</Label>
              <Input id="month" v-model="month" type="month" class="w-[180px]" />
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
            </div>
          </template>
        </DataTable>
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
