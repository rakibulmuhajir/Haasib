<script setup lang="ts">
/**
 * Payroll, one month on one page (PayrollDashboardController@index): every employee's salary,
 * the advances they took that month and that month's payslip, with the month's actions on top --
 * run payroll, approve, pay. Replaces the overview, periods, payslips list and salary report.
 */
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Banknote, CheckCircle2, ChevronLeft, ChevronRight, Play, Wallet } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Row {
  id: string
  name: string
  employee_number: string | null
  salary: number
  advances: number
  advance_count: number
  payslip: { id: string; number: string; gross: number; deductions: number; net: number; status: string; paid_at: string | null } | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  month: string
  period: { id: string; status: string } | null
  rows: Row[]
  counts: { employees: number; payslips: number; draft: number; approved: number; paid: number }
}>()

const base = computed(() => `/${props.company.slug}`)
const currency = computed(() => props.company.base_currency || 'PKR')
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payroll', href: `/${props.company.slug}/payroll` },
]

const monthLabel = computed(() => new Date(`${props.month}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }))
const shift = (step: number) => {
  const d = new Date(`${props.month}-01T00:00:00`)
  d.setMonth(d.getMonth() + step)
  router.get(`${base.value}/payroll`, { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true })
}

const monthEnd = computed(() => {
  const d = new Date(`${props.month}-01T00:00:00`)
  return `${props.month}-${String(new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()).padStart(2, '0')}`
})
const open = computed(() => !props.period || ['open', 'processing'].includes(props.period.status))
const missing = computed(() => props.rows.filter((r) => !r.payslip && r.salary > 0).length)
const total = (pick: (r: Row) => number) => props.rows.reduce((sum, r) => sum + pick(r), 0)

const run = () => router.post(`${base.value}/payroll/run-monthly`, { month: props.month }, { preserveScroll: true })
const approveAll = () => props.period && router.post(`${base.value}/payroll-periods/${props.period.id}/approve-payslips`, {}, { preserveScroll: true })
const payAll = () => props.period && router.post(`${base.value}/payroll-periods/${props.period.id}/pay-payslips`, {}, { preserveScroll: true })

const statusVariant = (status: string): 'default' | 'secondary' | 'outline' => (status === 'paid' ? 'default' : status === 'approved' ? 'outline' : 'secondary')
</script>

<template>
  <Head title="Payroll" />

  <PageShell title="Payroll" :description="monthLabel" :icon="Banknote" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" size="icon" aria-label="Previous month" @click="shift(-1)"><ChevronLeft class="h-4 w-4" /></Button>
      <Button variant="outline" size="icon" aria-label="Next month" @click="shift(1)"><ChevronRight class="h-4 w-4" /></Button>
      <Button v-if="open && missing > 0" @click="run"><Play class="mr-2 h-4 w-4" />Run payroll</Button>
      <Button v-if="counts.draft > 0" variant="outline" @click="approveAll"><CheckCircle2 class="mr-2 h-4 w-4" />Approve {{ counts.draft }}</Button>
      <Button v-if="counts.approved > 0" @click="payAll"><Wallet class="mr-2 h-4 w-4" />Pay {{ counts.approved }}</Button>
    </template>

    <p v-if="period && !open" class="mb-4 text-sm text-muted-foreground">This month is closed.</p>

    <div class="overflow-x-auto rounded-lg border">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40 text-left text-xs text-muted-foreground">
          <tr>
            <th class="px-4 py-2">Employee</th>
            <th class="px-3 py-2 text-right">Salary</th>
            <th class="px-3 py-2 text-right">Advances this month</th>
            <th class="px-3 py-2 text-right">Deductions</th>
            <th class="px-3 py-2 text-right">Net to pay</th>
            <th class="px-3 py-2">Payslip</th>
            <th class="px-4 py-2 text-right">Statement</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b last:border-0">
            <td class="px-4 py-2">
              <Link :href="`${base}/employees/${row.id}`" class="font-medium text-primary underline-offset-2 hover:underline">{{ row.name }}</Link>
              <span v-if="row.employee_number" class="ml-2 text-xs text-muted-foreground">{{ row.employee_number }}</span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText :amount="row.salary" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            <td class="px-3 py-2 text-right tabular-nums" :class="row.advances > row.salary && row.salary > 0 ? 'text-status-critical' : ''">
              <MoneyText :amount="row.advances" :currency="currency" :show-currency="false" :fraction-digits="0" dash-zero />
              <Link v-if="row.advance_count" :href="`${base}/salary-advances?employee_id=${row.id}&month=${month}`" class="ml-1 text-xs text-primary underline-offset-2 hover:underline">({{ row.advance_count }})</Link>
            </td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText v-if="row.payslip" :amount="row.payslip.deductions" :currency="currency" :show-currency="false" :fraction-digits="0" dash-zero /><span v-else class="text-muted-foreground">—</span></td>
            <td class="px-3 py-2 text-right font-medium tabular-nums">
              <MoneyText v-if="row.payslip" :amount="row.payslip.net" :currency="currency" :show-currency="false" :fraction-digits="0" />
              <span v-else class="text-muted-foreground" title="Before payroll runs: salary less this month's advances">
                <MoneyText :amount="Math.max(0, row.salary - row.advances)" :currency="currency" :show-currency="false" :fraction-digits="0" />
              </span>
            </td>
            <td class="px-3 py-2">
              <Link v-if="row.payslip" :href="`${base}/payslips/${row.payslip.id}`" class="inline-flex items-center gap-2">
                <span class="text-xs text-primary underline-offset-2 hover:underline">{{ row.payslip.number }}</span>
                <Badge :variant="statusVariant(row.payslip.status)" class="capitalize">{{ row.payslip.status }}</Badge>
              </Link>
              <span v-else class="text-xs text-muted-foreground">Not run</span>
            </td>
            <td class="px-4 py-2 text-right">
              <Link :href="`${base}/reports/statements?kind=employee&id=${row.id}&from=${month}-01&to=${monthEnd}`" class="text-xs text-primary underline-offset-2 hover:underline">Statement</Link>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">No employees yet. <Link :href="`${base}/employees/create`" class="text-primary underline-offset-2 hover:underline">Add one</Link>.</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length" class="border-t bg-muted/20 font-medium">
          <tr>
            <td class="px-4 py-2">Total</td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText :amount="total((r) => r.salary)" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText :amount="total((r) => r.advances)" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText :amount="total((r) => r.payslip?.deductions ?? 0)" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            <td class="px-3 py-2 text-right tabular-nums"><MoneyText :amount="total((r) => (r.payslip ? r.payslip.net : Math.max(0, r.salary - r.advances)))" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
            <td class="px-3 py-2 text-xs text-muted-foreground" colspan="2">{{ counts.paid }} paid · {{ counts.approved }} to pay · {{ counts.draft }} to approve</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </PageShell>
</template>
