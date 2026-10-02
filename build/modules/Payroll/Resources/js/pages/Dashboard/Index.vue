<script setup lang="ts">
/**
 * Payroll, one month on one page (PayrollDashboardController@index): every employee's salary,
 * the advances they took that month and that month's payslip, with the month's actions on top --
 * run payroll, approve, pay. Replaces the overview, periods, payslips list and salary report.
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import Hint from '@/components/Hint.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toast } from 'vue-sonner'
import { Banknote, CheckCircle2, ChevronLeft, ChevronRight, Play, Wallet, X } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Row {
  id: string
  name: string
  employee_number: string | null
  salary: number
  pay_frequency: string
  hours: number
  advances: number
  advance_count: number
  deduction_lines?: Array<{ id: string; type: string | null; description: string | null; amount: number; is_advance: boolean }>
  payslip: { id: string; number: string; gross: number; deductions: number; net: number; status: string; paid_at: string | null } | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  month: string
  period: { id: string; status: string } | null
  rows: Row[]
  counts: { employees: number; payslips: number; draft: number; approved: number; paid: number }
  deductionTypes?: Array<{ id: string; code: string; name: string }>
  paymentAccounts?: Array<{ id: string; code: string; name: string; subtype: string }>
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
// No payslip yet: anyone on a salary, or on daily wages who was paid something this month.
const missing = computed(() => props.rows.filter((r) => !r.payslip && (r.salary > 0 || r.advances > 0 || r.hours > 0)).length)
const total = (pick: (r: Row) => number) => props.rows.reduce((sum, r) => sum + pick(r), 0)

const run = () => router.post(`${base.value}/payroll/run-monthly`, { month: props.month }, { preserveScroll: true })
const approveAll = () => props.period && router.post(`${base.value}/payroll-periods/${props.period.id}/approve-payslips`, {}, { preserveScroll: true })
// Paying asks when and from where first: it posts every approved payslip's net from that
// account, so it must never pick one silently. Staff paid cash through the daily close instead
// ("Salary paid") do not need this at all.
const paying = ref(false)
const today = new Date().toISOString().slice(0, 10)
const paidOn = ref(today)
const paidFrom = ref('')
const openPay = () => {
  paidOn.value = today
  paidFrom.value = props.paymentAccounts?.[0]?.id ?? ''
  paying.value = true
}
const payAll = () => {
  if (!props.period) return
  router.post(`${base.value}/payroll-periods/${props.period.id}/pay-payslips`, {
    paid_on: paidOn.value,
    payment_account_id: paidFrom.value || null,
    payment_method: props.paymentAccounts?.find((a) => a.id === paidFrom.value)?.subtype === 'cash' ? 'cash' : 'bank_transfer',
  }, {
    preserveScroll: true,
    onSuccess: () => { paying.value = false },
    onError: (errors) => { toast.error(Object.values(errors)[0] ?? 'Not paid') },
  })
}

// Undo the month's approval (approved, unpaid payslips are voided and reversed) to redo it.
const undoing = ref(false)
const undoApproval = () => {
  if (!props.period) return
  router.post(`${base.value}/payroll-periods/${props.period.id}/unapprove-payslips`, {}, {
    preserveScroll: true,
    onSuccess: () => { undoing.value = false },
    onError: (errors) => { toast.error(Object.values(errors)[0] ?? 'Not undone') },
  })
}

// Take pay off a draft payslip: leave, absence, damage, a fine. Advance recovery is re-worked
// from what is left, server side.
const deducting = ref<Row | null>(null)
const deductType = ref('')
const deductAmount = ref('')
const deductNote = ref('')
const openDeduct = (row: Row) => {
  deductType.value = ''
  deductAmount.value = ''
  deductNote.value = ''
  deducting.value = row
}
const addDeduction = () => {
  const payslipId = deducting.value?.payslip?.id
  if (!payslipId) return
  router.post(`${base.value}/payslips/${payslipId}/deductions`, {
    deduction_type_id: deductType.value,
    amount: deductAmount.value,
    description: deductNote.value || null,
  }, {
    preserveScroll: true,
    onSuccess: () => { deducting.value = null },
    onError: (errors) => { toast.error(Object.values(errors)[0] ?? 'Not added') },
  })
}
const removeDeduction = (id: string) => {
  router.delete(`${base.value}/payslip-lines/${id}`, {
    preserveScroll: true,
    onError: (errors) => { toast.error(Object.values(errors)[0] ?? 'Not removed') },
  })
}

const statusVariant = (status: string): 'default' | 'secondary' | 'outline' => (status === 'paid' ? 'default' : status === 'approved' ? 'outline' : 'secondary')
</script>

<template>
  <Head title="Payroll" />

  <PageShell title="Payroll" :description="monthLabel" :icon="Banknote" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" size="icon" aria-label="Previous month" @click="shift(-1)"><ChevronLeft class="h-4 w-4" /></Button>
      <Button variant="outline" size="icon" aria-label="Next month" @click="shift(1)"><ChevronRight class="h-4 w-4" /></Button>
      <Button v-if="open && (missing > 0 || counts.draft > 0)" :variant="missing > 0 ? 'default' : 'outline'" @click="run"><Play class="mr-2 h-4 w-4" />{{ missing > 0 ? 'Run payroll' : 'Update drafts' }}</Button>
      <Button v-if="counts.draft > 0" variant="outline" @click="approveAll"><CheckCircle2 class="mr-2 h-4 w-4" />Approve {{ counts.draft }}</Button>
      <Button v-if="counts.approved > 0" variant="ghost" @click="undoing = true">Undo approval</Button>
      <Button v-if="counts.approved > 0" @click="openPay"><Wallet class="mr-2 h-4 w-4" />Pay {{ counts.approved }}</Button>
    </template>

    <Dialog v-model:open="undoing">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader><DialogTitle>Undo approval?</DialogTitle></DialogHeader>
        <p class="text-sm text-muted-foreground">{{ counts.approved }} payslips are voided and their entries reversed. Run payroll again to redo the month.</p>
        <DialogFooter>
          <Button variant="outline" @click="undoing = false">Cancel</Button>
          <Button variant="destructive" @click="undoApproval">Undo approval</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="paying">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader><DialogTitle>Pay {{ counts.approved }} payslips</DialogTitle></DialogHeader>
        <div class="space-y-3">
          <div class="space-y-1.5">
            <Label for="paid_on">Paid on</Label>
            <Input id="paid_on" v-model="paidOn" type="date" :max="today" />
          </div>
          <div class="space-y-1.5">
            <Label>Paid from</Label>
            <Select v-model="paidFrom">
              <SelectTrigger><SelectValue placeholder="Account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in paymentAccounts ?? []" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="paying = false">Cancel</Button>
          <Button :disabled="!paidOn || !paidFrom" @click="payAll">Pay</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="!!deducting" @update:open="(v: boolean) => { if (!v) deducting = null }">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader><DialogTitle>Deduct from {{ deducting?.name }}</DialogTitle></DialogHeader>
        <div class="space-y-3">
          <div class="space-y-1.5">
            <Label>Type</Label>
            <Select v-model="deductType">
              <SelectTrigger><SelectValue placeholder="Type" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in deductionTypes ?? []" :key="t.id" :value="t.id">{{ t.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-1.5">
            <Label for="deduct_amount">Amount</Label>
            <Input id="deduct_amount" v-model="deductAmount" type="number" min="0" step="0.01" />
          </div>
          <div class="space-y-1.5">
            <Label for="deduct_note">Note</Label>
            <Input id="deduct_note" v-model="deductNote" maxlength="255" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="deducting = null">Cancel</Button>
          <Button :disabled="!deductType || !(Number(deductAmount) > 0)" @click="addDeduction">Add</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

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
            <td class="px-3 py-2 text-right tabular-nums">
              <span v-if="row.pay_frequency === 'hourly'">{{ row.hours }} h</span>
              <MoneyText v-else :amount="row.salary" :currency="currency" :show-currency="false" :fraction-digits="0" />
            </td>
            <td class="px-3 py-2 text-right tabular-nums" :class="row.advances > row.salary && row.salary > 0 ? 'text-status-critical' : ''">
              <MoneyText :amount="row.advances" :currency="currency" :show-currency="false" :fraction-digits="0" dash-zero />
              <Link v-if="row.advance_count" :href="`${base}/salary-advances?employee_id=${row.id}&month=${month}`" class="ml-1 text-xs text-primary underline-offset-2 hover:underline">({{ row.advance_count }})</Link>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">
              <template v-if="row.payslip">
                <Hint v-if="row.deduction_lines?.length">
                  <MoneyText :amount="row.payslip.deductions" :currency="currency" :show-currency="false" :fraction-digits="0" dash-zero />
                  <template #content>
                    <div v-for="line in row.deduction_lines" :key="line.id" class="flex items-center justify-between gap-3">
                      <span>{{ [line.type, line.description !== line.type ? line.description : null].filter(Boolean).join(' · ') }} · {{ line.amount.toLocaleString() }}</span>
                      <button
                        v-if="row.payslip.status === 'draft' && !line.is_advance"
                        type="button"
                        class="rounded p-0.5 hover:bg-muted"
                        aria-label="Remove deduction"
                        @click.stop="removeDeduction(line.id)"
                      ><X class="h-3 w-3" /></button>
                    </div>
                  </template>
                </Hint>
                <MoneyText v-else :amount="row.payslip.deductions" :currency="currency" :show-currency="false" :fraction-digits="0" dash-zero />
              </template>
              <span v-else class="text-muted-foreground">—</span>
            </td>
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
              <Button v-if="row.payslip && row.payslip.status === 'draft'" variant="ghost" size="sm" class="ml-2 h-6 px-2 text-xs" @click="openDeduct(row)">Deduct</Button>
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
