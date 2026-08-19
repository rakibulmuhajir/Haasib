<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { Banknote, Calendar, CheckCircle, FileText, UserCog, WalletCards } from 'lucide-vue-next'
import MoneyText from '@/components/MoneyText.vue'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface PayrollAccount {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

interface PayslipRow {
  id: string
  payslip_number: string
  employee_name: string
  period: { start: string | null; end: string | null }
  net_pay: number
  currency: string
  status: string
}

interface AdvanceEmployee {
  id: string
  name: string
  employee_number: string
  outstanding_advances: number
}

const props = defineProps<{
  company: CompanyRef
  currentPeriod: {
    id: string
    period_start: string
    period_end: string
    payment_date: string
    status: string
  } | null
  summary: {
    active_employees: number
    draft_payslips: number
    approved_unpaid_count: number
    approved_unpaid_amount: number
    paid_this_month: number
    salary_expense_this_month: number
    outstanding_advances: number
    recovered_this_month: number
  }
  accounts: Record<string, PayrollAccount>
  recentPayslips: PayslipRow[]
  employeesWithAdvances: AdvanceEmployee[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payroll', href: `/${props.company.slug}/payroll` },
]

const formatDate = (date: string | null | undefined) => formatDateTime(date, { mode: 'date', fallback: '-' })

const currentPeriodLabel = computed(() => {
  if (!props.currentPeriod) return 'No open period'
  return `${formatDate(props.currentPeriod.period_start)} - ${formatDate(props.currentPeriod.period_end)}`
})

const payslipColumns = [
  { key: 'payslip_number', label: 'Payslip', kind: 'ref' as const },
  { key: 'employee_name', label: 'Employee', kind: 'text' as const },
  { key: 'period', label: 'Period', kind: 'date' as const },
  { key: 'net_pay', label: 'Net Pay', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
]

const payslipRows = computed(() => props.recentPayslips.map((payslip) => ({
  id: payslip.id,
  payslip_number: payslip.payslip_number,
  employee_name: payslip.employee_name,
  period: `${formatDate(payslip.period.start)} - ${formatDate(payslip.period.end)}`,
  net_pay: payslip.net_pay,
  status: payslip.status,
  _raw: payslip,
})))

const advanceColumns = [
  { key: 'employee', label: 'Employee', kind: 'text' as const },
  { key: 'outstanding', label: 'Remaining Advance', kind: 'amount' as const },
]

const advanceRows = computed(() => props.employeesWithAdvances.map((employee) => ({
  id: employee.id,
  employee: `${employee.name} · ${employee.employee_number}`,
  outstanding: employee.outstanding_advances,
})))

const accountRows = computed(() => [
  { label: 'Salary expense', account: props.accounts.salary_expense, hint: 'Payroll approval increases this expense.' },
  { label: 'Payroll payable', account: props.accounts.payroll_payable, hint: 'Approved unpaid salaries stay here until paid.' },
  { label: 'Employee advances', account: props.accounts.employee_advances, hint: 'Advances increase this; salary recovery reduces it.' },
  { label: 'Deduction payable', account: props.accounts.deduction_payable, hint: 'Taxes and other deductions wait here until remitted.' },
  { label: 'Default payment', account: props.accounts.payment, hint: 'Salary payments reduce this cash or bank account.' },
])

const runMonthlyPayroll = () => {
  router.post(`/${props.company.slug}/payroll/run-monthly`, {}, { preserveScroll: true })
}
</script>

<template>
  <Head title="Payroll" />

  <PageShell title="Payroll" description="Run salaries from employee profiles. Use Daily Close only for actual cash advances." :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/employees`)">
        <UserCog class="mr-2 h-4 w-4" />
        Employees
      </Button>
      <Button @click="runMonthlyPayroll">
        <Calendar class="mr-2 h-4 w-4" />
        Run Monthly Payroll
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm text-muted-foreground">Open period</p>
              <p class="mt-1 font-semibold">{{ currentPeriodLabel }}</p>
              <p class="mt-1 text-xs text-muted-foreground">Payment: {{ formatDate(currentPeriod?.payment_date) }}</p>
            </div>
            <Calendar class="h-5 w-5 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm text-muted-foreground">Salary due</p>
              <p class="mt-1 text-2xl font-semibold"><MoneyText :amount="summary.approved_unpaid_amount" :currency="company.base_currency" /></p>
              <p class="mt-1 text-xs text-muted-foreground">{{ summary.approved_unpaid_count }} approved payslips unpaid</p>
            </div>
            <Banknote class="h-5 w-5 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm text-muted-foreground">Outstanding advances</p>
              <p class="mt-1 text-2xl font-semibold"><MoneyText :amount="summary.outstanding_advances" :currency="company.base_currency" /></p>
              <p class="mt-1 text-xs text-muted-foreground"><MoneyText :amount="summary.recovered_this_month" :currency="company.base_currency" /> recovered this month</p>
            </div>
            <WalletCards class="h-5 w-5 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm text-muted-foreground">This month</p>
              <p class="mt-1 text-2xl font-semibold"><MoneyText :amount="summary.salary_expense_this_month" :currency="company.base_currency" /></p>
              <p class="mt-1 text-xs text-muted-foreground"><MoneyText :amount="summary.paid_this_month" :currency="company.base_currency" /> paid</p>
            </div>
            <CheckCircle class="h-5 w-5 text-muted-foreground" />
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
      <Card>
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <CardTitle>Recent Payslips</CardTitle>
              <CardDescription>Drafts are not posted. Approved payslips create salary payable.</CardDescription>
            </div>
            <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/payslips`)">
              <FileText class="mr-2 h-4 w-4" />
              Open
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <LedgerRegister :columns="payslipColumns" :data="payslipRows" @row-click="(row) => router.get(`/${company.slug}/payslips/${row.id}`)">
            <template #cell-net_pay="{ row }">
              <MoneyText :amount="row.net_pay" :currency="row._raw.currency" />
            </template>
            <template #cell-status="{ row }">
              <StatusBadge :status="row.status" />
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <CardTitle>Advance Balances</CardTitle>
              <CardDescription>Remaining recoverable amounts by employee.</CardDescription>
            </div>
            <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/salary-advances`)">
              <WalletCards class="mr-2 h-4 w-4" />
              Open
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <LedgerRegister :columns="advanceColumns" :data="advanceRows" @row-click="(row) => router.get(`/${company.slug}/employees/${row.id}`)">
            <template #cell-outstanding="{ row }">
              <MoneyText :amount="row.outstanding" :currency="company.base_currency" />
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Payroll Accounts</CardTitle>
        <CardDescription>These are maintained automatically and used when payroll posts to accounting.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          <div v-for="row in accountRows" :key="row.label" class="rounded-lg border p-3">
            <p class="text-sm font-medium">{{ row.label }}</p>
            <p class="mt-2 text-sm">{{ row.account.code }} — {{ row.account.name }}</p>
            <p class="mt-2 text-xs text-muted-foreground">{{ row.hint }}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
