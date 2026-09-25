<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import InputError from '@/components/InputError.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import { localToday } from '@/composables/useEntryDate'
import {
  FileText,
  Plus,
  Eye,
  CheckCircle,
  DollarSign,
  RotateCcw,
  Trash2,
  Ban,
  MoreHorizontal,
} from 'lucide-vue-next'
import { formatMoneyText } from '@/lib/money'

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
  paid_at: string | null
}

interface PaymentAccount {
  id: string
  code: string
  name: string
  subtype: string
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
  canDeletePayslips: boolean
  paymentAccounts: PaymentAccount[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payslips', href: `/${props.company.slug}/payslips` },
]

const columns = [
  { key: '_select', label: '', sortable: false },
  { key: 'payslip_number', label: 'Number', kind: 'ref' as const },
  { key: 'employee', label: 'Employee', kind: 'text' as const },
  { key: 'period', label: 'Period', kind: 'date' as const },
  { key: 'net_pay', label: 'Net Pay', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: '_actions', label: '', sortable: false },
]

const formatDate = (date: string) => {
  return formatSharedDateTime(date, { mode: 'date' })
}

const formatCurrency = (amount: number, currency: string) => {
  return formatMoneyText(amount, currency || 'USD')
}

// There is no fixed pay day, so payslips are grouped by the month they belong to, not by a
// payment date - each one is paid whenever it is paid, shown separately below once it is.
const formatMonth = (date: string) => {
  return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
}

const tableData = computed(() => {
  return props.payslips.data.map((payslip) => ({
    id: payslip.id,
    payslip_number: payslip.payslip_number,
    employee: `${payslip.employee.first_name} ${payslip.employee.last_name}`,
    period: formatMonth(payslip.payroll_period.period_start),
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

const paymentMethods = [
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'cash', label: 'Cash' },
  { value: 'check', label: 'Check' },
  { value: 'cheque', label: 'Cheque' },
]

const showMarkPaidDialog = ref(false)
const markPaidPayslipId = ref<string | null>(null)
const markPaidForm = useForm({
  paid_on: localToday(),
  payment_method: 'bank_transfer',
  payment_account_id: '',
  payment_reference: '',
})

const handleMarkPaid = (id: string) => {
  markPaidPayslipId.value = id
  markPaidForm.reset()
  markPaidForm.paid_on = localToday()
  showMarkPaidDialog.value = true
}

const submitMarkPaid = () => {
  if (!markPaidPayslipId.value) return
  markPaidForm.post(`/${props.company.slug}/payslips/${markPaidPayslipId.value}/mark-paid`, {
    preserveScroll: true,
    onSuccess: () => {
      showMarkPaidDialog.value = false
      markPaidPayslipId.value = null
    },
  })
}

const showUndoPaymentDialog = ref(false)
const undoPaymentPayslipId = ref<string | null>(null)
const undoPaymentForm = useForm({
  reason: '',
})

const handleUndoPayment = (id: string) => {
  undoPaymentPayslipId.value = id
  undoPaymentForm.reset()
  showUndoPaymentDialog.value = true
}

const submitUndoPayment = () => {
  if (!undoPaymentPayslipId.value) return
  undoPaymentForm.post(`/${props.company.slug}/payslips/${undoPaymentPayslipId.value}/reverse-payment`, {
    preserveScroll: true,
    onSuccess: () => {
      showUndoPaymentDialog.value = false
      undoPaymentPayslipId.value = null
    },
  })
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this payslip?')) {
    router.delete(`/${props.company.slug}/payslips/${id}`)
  }
}

// Row selection for bulk delete: which rows on the page currently showing are
// checked. Paid payslips can be selected too - the server skips them and
// reports the skip count rather than the page trying to predict that itself.
const selectedIds = ref<Set<string>>(new Set())

const allSelected = computed(
  () => props.payslips.data.length > 0 && props.payslips.data.every((p) => selectedIds.value.has(p.id)),
)
const someSelected = computed(() => props.payslips.data.some((p) => selectedIds.value.has(p.id)))
const headerCheckboxState = computed<boolean | 'indeterminate'>(() => {
  if (allSelected.value) return true
  if (someSelected.value) return 'indeterminate'
  return false
})

const toggleSelectAll = (value: boolean | 'indeterminate') => {
  selectedIds.value = value === true ? new Set(props.payslips.data.map((p) => p.id)) : new Set()
}

const toggleRowSelected = (id: string, value: boolean | 'indeterminate') => {
  const next = new Set(selectedIds.value)
  if (value === true) next.add(id)
  else next.delete(id)
  selectedIds.value = next
}

const showBulkDeleteDialog = ref(false)
const bulkDeleteForm = useForm<{ ids: string[] }>({ ids: [] })

const openBulkDeleteDialog = () => {
  bulkDeleteForm.ids = Array.from(selectedIds.value)
  showBulkDeleteDialog.value = true
}

const submitBulkDelete = () => {
  bulkDeleteForm.post(`/${props.company.slug}/payslips/bulk-delete`, {
    preserveScroll: true,
    onSuccess: () => {
      showBulkDeleteDialog.value = false
      selectedIds.value = new Set()
    },
  })
}

const handleVoid = (id: string) => {
  const reason = window.prompt('Why is this payslip being voided?')?.trim()
  if (reason) {
    router.post(`/${props.company.slug}/payslips/${id}/void`, { reason }, { preserveScroll: true })
  }
}

</script>

<template>
  <Head title="Payslips" />

  <PageShell
    title="Payslips"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button
        v-if="canDeletePayslips && selectedIds.size > 0"
        variant="destructive"
        @click="openBulkDeleteDialog"
      >
        <Trash2 class="mr-2 h-4 w-4" />
        Delete selected ({{ selectedIds.size }})
      </Button>
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
    <LedgerRegister
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
      <template v-if="canDeletePayslips" #header-_select>
        <Checkbox
          :model-value="headerCheckboxState"
          aria-label="Select all payslips shown"
          @click.stop
          @update:model-value="toggleSelectAll"
        />
      </template>

      <template #cell-_select="{ row }">
        <Checkbox
          v-if="canDeletePayslips"
          :model-value="selectedIds.has(row.id)"
          :aria-label="`Select payslip ${row.payslip_number}`"
          @click.stop
          @update:model-value="(value) => toggleRowSelected(row.id, value)"
        />
      </template>

      <template #cell-status="{ row }">
        <StatusBadge :status="row._raw.status" />
        <p v-if="row._raw.status === 'paid' && row._raw.paid_at" class="mt-1 text-xs text-muted-foreground">
          Paid on {{ formatDate(row._raw.paid_at) }}
        </p>
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
              v-if="row._raw.status === 'paid'"
              @click="handleUndoPayment(row.id)"
            >
              <RotateCcw class="mr-2 h-4 w-4" />
              Undo Payment
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="canDeletePayslips && ['draft', 'approved'].includes(row._raw.status)"
              class="text-destructive"
              @click="handleDelete(row.id)"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="canDeletePayslips && ['approved', 'paid'].includes(row._raw.status)"
              class="text-destructive"
              @click="handleVoid(row.id)"
            >
              <Ban class="mr-2 h-4 w-4" />
              Void Payslip
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
    </LedgerRegister>
  </PageShell>

  <Dialog v-model:open="showMarkPaidDialog">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Mark Payslip Paid</DialogTitle>
        <DialogDescription>
          Record the payment and post it to accounting.
        </DialogDescription>
      </DialogHeader>
      <div class="space-y-4">
        <div>
          <Label for="list_paid_on">Paid on</Label>
          <Input
            id="list_paid_on"
            v-model="markPaidForm.paid_on"
            type="date"
            :max="localToday()"
            required
          />
          <InputError :message="markPaidForm.errors.paid_on" />
        </div>
        <div>
          <Label for="list_payment_account_id">Account</Label>
          <Select v-model="markPaidForm.payment_account_id">
            <SelectTrigger id="list_payment_account_id">
              <SelectValue placeholder="Select cash or bank account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="account in paymentAccounts"
                :key="account.id"
                :value="account.id"
              >
                {{ account.code }} — {{ account.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="markPaidForm.errors.payment_account_id" />
        </div>
        <div>
          <Label for="list_payment_method">Payment method</Label>
          <Select v-model="markPaidForm.payment_method">
            <SelectTrigger id="list_payment_method">
              <SelectValue placeholder="Select method" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="method in paymentMethods"
                :key="method.value"
                :value="method.value"
              >
                {{ method.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="markPaidForm.errors.payment_method" />
        </div>
        <div>
          <Label for="list_payment_reference">Reference (optional)</Label>
          <Input id="list_payment_reference" v-model="markPaidForm.payment_reference" />
          <InputError :message="markPaidForm.errors.payment_reference" />
        </div>
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" @click="showMarkPaidDialog = false">
          Cancel
        </Button>
        <Button type="button" :disabled="markPaidForm.processing" @click="submitMarkPaid">
          {{ markPaidForm.processing ? 'Saving...' : 'Mark Paid' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <Dialog v-model:open="showUndoPaymentDialog">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Undo Payment</DialogTitle>
        <DialogDescription>
          Reverses the payment journal and puts this payslip back to approved and unpaid.
        </DialogDescription>
      </DialogHeader>
      <div>
        <Label for="list_undo_reason">Reason (optional)</Label>
        <Input id="list_undo_reason" v-model="undoPaymentForm.reason" />
        <InputError :message="undoPaymentForm.errors.reason" />
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" @click="showUndoPaymentDialog = false">
          Cancel
        </Button>
        <Button
          type="button"
          variant="destructive"
          :disabled="undoPaymentForm.processing"
          @click="submitUndoPayment"
        >
          {{ undoPaymentForm.processing ? 'Undoing...' : 'Undo Payment' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <Dialog v-model:open="showBulkDeleteDialog">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Delete {{ bulkDeleteForm.ids.length }} payslips?</DialogTitle>
        <DialogDescription>
          Paid ones are skipped.
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button type="button" variant="outline" @click="showBulkDeleteDialog = false">
          Cancel
        </Button>
        <Button
          type="button"
          variant="destructive"
          :disabled="bulkDeleteForm.processing"
          @click="submitBulkDelete"
        >
          {{ bulkDeleteForm.processing ? 'Deleting...' : 'Delete selected' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
