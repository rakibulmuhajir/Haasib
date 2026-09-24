<script setup lang="ts">
import DailyCloseNav from '../../../components/DailyCloseNav.vue'
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogClose,
} from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import {
  Calculator,
  Calendar,
  Lock,
  Unlock,
  ArrowLeft,
  CheckCircle,
  XCircle,
  RotateCcw,
  Fuel,
  Wallet,
  ArrowDownRight,
} from 'lucide-vue-next'
import MoneyText from '@/components/MoneyText.vue'
import { useLexicon } from '@/composables/useLexicon'
const { t } = useLexicon()

interface TransactionData {
  id: string
  transaction_number: string
  transaction_date: string
  created_at: string
  status: 'posted' | 'locked' | 'reversed' | 'reversal' | 'correction'
  is_locked: boolean
  lock_reason: string | null
  locked_at: string | null
  metadata: {
    date?: string
    opening_cash?: number
    closing_cash?: number
    total_revenue?: number
    total_cogs?: number
    variance?: number
    expected_closing?: number
    fuel_sales?: Record<string, { liters: number; revenue: number; cogs: number }>
    other_sales?: number
    bank_withdrawals?: number
    bank_deposits?: number
    partner_withdrawals?: number
    employee_advances?: number
    payroll_payouts?: number
    expenses?: number
    partner_deposits?: number
    amanat_deposits?: number
    other_deposits?: number
    cash_bill_payments?: number
    amanat_disbursements?: number
    credit_sales_total?: number
    credit_sale_details?: Array<{ invoice_id: string; invoice_number: string; customer_name: string; amount: number }>
    payment_receipt_postings?: Array<{ channel_code: string; channel_label: string; channel_type: string; account_id: string | null; amount: number }>
  }
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  transaction: TransactionData
  expenseAccounts?: Array<{ id: string; name: string }>
  canAddActivity?: boolean
  canCorrectReadings?: boolean
  correctableReadings?: {
    tank: Array<{ id: string; label: string; current_value: number }>
    nozzle: Array<{ id: string; label: string; current_value: number }>
  }
  reconciliation?: { has_post_close_activity?: boolean; audit_events?: any[]; snapshot?: any; current?: any; activity: any[]; corrections?: any[] }
  unlockHistory?: Array<{ id: string; unlocked_at: string | null; unlocked_by: string | null; reason: string; previously_locked_at: string | null }>
  permissions: {
    canLock: boolean
    canUnlock: boolean
  }
}>()

const expense = useForm({ account_id: '', description: '', amount: 0 })
const addExpense = () => expense.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/expenses`, {
  preserveScroll: true,
  onSuccess: (page) => { if ((page.props as any).flash?.success) expense.reset() },
  onError: (errors) => toast.error(String(Object.values(errors)[0])),
})

const correction = useForm({ reading_type: 'tank', reading_id: '', corrected_value: 0, reason: '', expected_revision: 0 })
const correctableOptions = computed(() => {
  const options = correction.reading_type === 'nozzle' ? (props.correctableReadings?.nozzle ?? []) : (props.correctableReadings?.tank ?? [])
  return options.map(option => {
    const latest = (props.reconciliation?.corrections ?? []).filter(row => row.reading_id === option.id)
      .sort((a, b) => Number(b.revision) - Number(a.revision))[0]
    return { ...option, label: latest ? `${option.label} (current: ${latest.corrected_value}L)` : option.label }
  })
})
const addCorrection = () => {
  correction.expected_revision = Math.max(0, ...(props.reconciliation?.corrections ?? [])
    .filter(row => row.reading_id === correction.reading_id).map(row => Number(row.revision)))
  correction.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/corrections`, {
  preserveScroll: true,
  onSuccess: (page) => { if ((page.props as any).flash?.success) { toast.success('Correction recorded'); correction.reset('reading_id', 'corrected_value', 'reason') } },
  onError: (errors) => toast.error(String(Object.values(errors)[0])),
})
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close', href: `/${props.company.slug}/fuel/daily-close/history` },
  { title: props.transaction.transaction_number, href: `/${props.company.slug}/fuel/daily-close/${props.transaction.id}` },
])

const currency = computed(() => props.company.base_currency || 'PKR')

const formatDate = (date: string) => {
  return formatSharedDateTime(date, { mode: 'date', locale: 'en-PK' })
}

const formatDateTime = (datetime: string | null) => {
  return formatSharedDateTime(datetime, { mode: 'datetime', locale: 'en-PK', fallback: '' })
}

const statusConfig = computed(() => {
  const configs: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof CheckCircle }> = {
    posted: { label: 'Posted', variant: 'default', icon: CheckCircle },
    locked: { label: 'Locked', variant: 'secondary', icon: Lock },
    reversed: { label: 'Reversed', variant: 'destructive', icon: XCircle },
    reversal: { label: 'Reversal', variant: 'outline', icon: RotateCcw },
    correction: { label: 'Correction', variant: 'default', icon: CheckCircle },
  }
  return configs[props.transaction.status] || configs.posted
})

const metadata = computed(() => props.transaction.metadata || {})

// Sales that were paid by card/transfer/fuel card went straight to a bank or
// clearing account, so they never reached the drawer — they belong under Money Out.
const channelOutRows = computed(() => {
  const postings = metadata.value.payment_receipt_postings ?? []
  return postings.filter(p => Number(p.amount) > 0)
})
const totalChannelOut = computed(() => channelOutRows.value.reduce((s, p) => s + Number(p.amount), 0))

const totalMoneyIn = computed(() => {
  if (props.reconciliation?.snapshot) return Number(props.reconciliation.snapshot.totals.money_in || 0)
  const m = metadata.value
  // Opening cash is shown separately below. This total must contain only
  // current-day inflows, otherwise the detail view double-counts the opening
  // drawer balance while the reconciliation table correctly reports money_in.
  return Number(m.bank_withdrawals || 0) + Number(m.partner_deposits || 0) + Number(m.amanat_deposits || 0) + Number(m.other_deposits || 0) + Number(m.total_revenue || 0)
})

const totalMoneyOut = computed(() => {
  if (props.reconciliation?.snapshot) return Number(props.reconciliation.snapshot.totals.money_out || 0)
  const m = metadata.value
  return totalChannelOut.value + Number(m.credit_sales_total || 0) + Number(m.bank_deposits || 0) + Number(m.partner_withdrawals || 0) + Number(m.employee_advances || 0)
    + Number(m.payroll_payouts || 0) + Number(m.cash_bill_payments || 0) + Number(m.amanat_disbursements || 0) + Number(m.expenses || 0)
})

const fuelSalesEntries = computed(() => {
  const sales = metadata.value.fuel_sales || {}
  return Object.entries(sales).map(([category, data]) => ({
    category,
    ...data,
  }))
})

const lockTransaction = () => {
  router.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/lock`, {}, {
    preserveScroll: true,
    onError: () => toast.error('Failed to lock daily close'),
  })
}

// Reopening a settled day is kept forever in fuel.daily_close_unlocks, so the reason is
// required server-side. Inline error on the field, per the error-handling contract.
const unlockForm = useForm({ reason: '' })
const unlockOpen = ref(false)

const unlockTransaction = () => {
  unlockForm.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/unlock`, {
    preserveScroll: true,
    onSuccess: () => {
      unlockOpen.value = false
      unlockForm.reset()
    },
  })
}
</script>

<template>
  <Head :title="`Daily Close - ${transaction.transaction_number}`" />

  <PageShell
    :title="transaction.transaction_number"
    :description="`Daily close for ${formatDate(transaction.transaction_date)}`"
    :icon="Calculator"
    :breadcrumbs="breadcrumbs"
  >
    <DailyCloseNav :company="company" history />

    <Card v-if="unlockHistory?.length" class="mb-6 border-status-attention/40">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Unlock class="h-4 w-4" />
          This day has been reopened {{ unlockHistory.length }} time{{ unlockHistory.length === 1 ? '' : 's' }}
        </CardTitle>
        <CardDescription>
          A settled day was unlocked so it could be changed. Each reopening is kept permanently.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ul class="space-y-3">
          <li v-for="entry in unlockHistory" :key="entry.id" class="border-l-2 border-status-attention/50 pl-3">
            <p class="text-sm font-medium">
              {{ entry.unlocked_by || 'Unknown user' }}
              <span class="font-normal text-muted-foreground">· {{ formatDateTime(entry.unlocked_at) }}</span>
            </p>
            <p class="text-sm text-muted-foreground">{{ entry.reason }}</p>
          </li>
        </ul>
      </CardContent>
    </Card>

    <Card v-if="reconciliation" class="mb-6">
      <CardHeader>
        <CardTitle>Daily Close reconciliation <Badge v-if="reconciliation.has_post_close_activity" variant="destructive">Post-close activity</Badge></CardTitle>
        <CardDescription>Posted {{ formatDateTime(reconciliation.snapshot?.posted_at) }} by {{ reconciliation.snapshot?.posted_by_name || reconciliation.snapshot?.posted_by }} · Business date {{ transaction.transaction_date }}</CardDescription>
        <div v-if="reconciliation.snapshot?.zero_sales_confirmed" class="mt-2 rounded-md border border-status-info/30 bg-status-info/10 px-3 py-2 text-sm">
          <span class="font-medium">Zero-sales day confirmed.</span>
          <span v-if="reconciliation.snapshot?.zero_sales_reason" class="text-muted-foreground"> {{ reconciliation.snapshot.zero_sales_reason }}</span>
        </div>
      </CardHeader>
      <CardContent class="space-y-6">
        <table class="w-full text-sm">
          <thead><tr class="border-b text-left"><th class="py-2">Cash</th><th>POSTED SNAPSHOT</th><th>CURRENT / RECONCILED</th></tr></thead>
          <tbody>
            <tr v-for="[key, label] in [['total_revenue','Sales'],['money_in','Money In'],['money_out','Money Out'],['expected_closing','Expected closing cash'],['closing_cash','Physical closing cash'],['variance','Cash variance']]" :key="key" class="border-b">
              <td class="py-2">{{ label }}</td>
              <td><MoneyText :amount="reconciliation.snapshot?.totals[key] || 0" :currency="currency" /></td>
              <td><MoneyText :amount="reconciliation.current?.[key] || 0" :currency="currency" /></td>
            </tr>
          </tbody>
        </table>
        <table class="w-full text-sm">
          <thead><tr class="border-b text-left"><th>Channel movement</th><th>Posted snapshot</th><th>Current / reconciled</th></tr></thead>
          <tbody><tr v-for="(label, accountId) in reconciliation.snapshot?.channel_accounts" :key="accountId" class="border-b"><td class="py-2">{{ label }}</td><td><MoneyText :amount="reconciliation.snapshot?.account_effects?.[accountId] || 0" :currency="currency" /></td><td><MoneyText :amount="reconciliation.current?.account_effects?.[accountId] || 0" :currency="currency" /></td></tr></tbody>
        </table>
        <table v-if="reconciliation.snapshot?.tanks?.length" class="w-full text-sm">
          <thead><tr><th class="text-left">Tank</th><th>Declared physical litres</th><th>Original variance</th><th>Reconciled variance</th></tr></thead>
          <tbody><tr v-for="(tank, index) in reconciliation.snapshot.tanks" :key="tank.tank_id"><td>{{ tank.tank_name }}</td><td>{{ tank.physical_liters }}</td><td>{{ tank.variance_liters }}</td><td>{{ reconciliation.current?.tanks?.[index]?.variance_liters }}</td></tr></tbody>
        </table>
        <div>
          <h3 class="mb-2 font-semibold">POST-CLOSE ACTIVITY</h3>
          <form v-if="canAddActivity" @submit.prevent="addExpense" class="mb-4 space-y-3 rounded border p-4">
            <p class="font-medium">Record a forgotten cash expense for {{ transaction.transaction_date }}</p>
            <Label>Expense account</Label>
            <Select v-model="expense.account_id"><SelectTrigger><SelectValue placeholder="Choose expense account" /></SelectTrigger><SelectContent><SelectItem v-for="account in expenseAccounts" :key="account.id" :value="account.id">{{ account.name }}</SelectItem></SelectContent></Select>
            <Label>Description</Label><Input v-model="expense.description" required />
            <Label>Amount</Label><Input v-model.number="expense.amount" type="number" min="0.01" step="0.01" required />
            <p v-for="(error, field) in expense.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
            <Button type="submit" :disabled="expense.processing">{{ expense.processing ? 'Recording…' : 'Record expense' }}</Button>
          </form>
          <p v-if="!reconciliation.has_post_close_activity" class="text-sm text-muted-foreground">No changes since posting.</p>
          <div v-for="row in reconciliation.activity" :key="row.type + row.id" class="border-b py-3 text-sm">
            <p class="font-medium">{{ row.activity }} · {{ row.type }} · <Link v-if="!row.type.startsWith('stock:')" :href="`/${company.slug}/journals/${row.id}`" class="underline">{{ row.reference }}</Link><span v-else>{{ row.reference }}</span></p>
            <p>Business date {{ row.business_date }} · Entered {{ formatDateTime(row.entered_at) }} by {{ row.entered_by_name }}</p>
            <p v-if="row.before">Updated {{ formatDateTime(row.updated_at) }} · {{ row.updated_by_name || 'Actor unavailable' }}</p>
            <p>{{ row.source_type }} · {{ row.source_id || row.id }}</p>
            <p>Amount <MoneyText :amount="row.amount" :currency="currency" /> · Cash reconciliation effect <MoneyText :amount="row.reconciliation_effect" :currency="currency" /></p>
            <p v-if="row.quantity_effect">Stock effect: {{ row.quantity_effect }} litres · Tank {{ row.warehouse_id }}</p>
          </div>
        </div>
        <div>
          <h3 class="mb-2 font-semibold">READING CORRECTIONS</h3>
          <form v-if="canCorrectReadings" @submit.prevent="addCorrection" class="mb-4 space-y-3 rounded border p-4">
            <p class="font-medium">Correct a tank or nozzle reading for {{ transaction.transaction_date }}</p>
            <p class="text-sm text-muted-foreground">The original reading is never changed. This records the correction and its journal/stock adjustments. Counted cash and the posted snapshot stay unchanged.</p>
            <Label>Reading type</Label>
            <Select v-model="correction.reading_type" @update:model-value="correction.reading_id = ''">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tank">Tank reading</SelectItem>
                <SelectItem value="nozzle">Nozzle reading</SelectItem>
              </SelectContent>
            </Select>
            <Label>Reading</Label>
            <Select v-model="correction.reading_id">
              <SelectTrigger><SelectValue placeholder="Choose reading" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in correctableOptions" :key="option.id" :value="option.id">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
            <Label>Corrected value (litres)</Label><Input v-model.number="correction.corrected_value" type="number" min="0" step="0.001" required />
            <Label>Reason</Label><Input v-model="correction.reason" required />
            <p v-for="(error, field) in correction.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
            <Button type="submit" :disabled="correction.processing">{{ correction.processing ? 'Recording…' : 'Record correction' }}</Button>
          </form>
          <p v-if="!reconciliation.corrections?.length" class="text-sm text-muted-foreground">No corrections recorded.</p>
          <div v-for="row in reconciliation.corrections" :key="row.id" class="border-b py-3 text-sm">
            <p class="font-medium">{{ row.reading_type === 'tank' ? 'Tank reading' : 'Nozzle reading' }} correction</p>
            <p>{{ row.original_value }}L → {{ row.corrected_value }}L · {{ formatDateTime(row.created_at) }} by {{ row.created_by_name }}</p>
            <p>Reason: {{ row.reason }}</p>
            <p v-if="row.revenue_effect !== undefined">Revenue effect <MoneyText :amount="row.revenue_effect" :currency="currency" /></p>
          </div>
        </div>
        <details v-if="reconciliation.audit_events?.length" class="rounded border p-3">
          <summary class="cursor-pointer font-medium">Audit history ({{ reconciliation.audit_events.length }} events)</summary>
          <div v-for="event in reconciliation.audit_events" :key="event.id" class="border-b py-2 text-sm">
            <p>{{ event.operation }} · {{ event.source_table }} · {{ event.source_id }}</p>
            <p>{{ formatDateTime(event.occurred_at) }} · {{ event.actor_name || 'Actor unavailable' }}</p>
              <p>Business date {{ event.after_data?.transaction_date || event.after_data?.movement_date || event.after_data?.invoice_date || event.after_data?.bill_date || event.before_data?.transaction_date || transaction.transaction_date }}</p>
              <p>Reference {{ event.after_data?.transaction_number || event.before_data?.transaction_number || event.source_id }}</p>
              <p v-if="event.before_data">Before amount: {{ event.before_data.total_amount ?? event.before_data.total_debit ?? event.before_data.amount ?? event.before_data.total_cost ?? event.before_data.debit_amount ?? '—' }}</p>
              <p v-if="event.after_data">After amount: {{ event.after_data.total_amount ?? event.after_data.total_debit ?? event.after_data.amount ?? event.after_data.total_cost ?? event.after_data.debit_amount ?? '—' }}</p>
          </div>
        </details>
      </CardContent>
    </Card>
    <template #actions>
      <div class="flex items-center gap-2">
        <Button variant="outline" as-child>
          <Link :href="`/${company.slug}/fuel/daily-close/history`">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to History
          </Link>
        </Button>

        <template v-if="permissions.canLock && !transaction.is_locked && transaction.status === 'posted'">
          <Dialog>
            <DialogTrigger as-child>
              <Button variant="outline">
                <Lock class="h-4 w-4 mr-2" />
                Lock
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Lock Daily Close?</DialogTitle>
                <DialogDescription>
                  Locking this daily close will prevent post-close corrections. Only an owner can unlock it later.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <DialogClose as-child>
                  <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button @click="lockTransaction">Lock</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </template>

        <template v-if="permissions.canUnlock && transaction.is_locked">
          <Dialog v-model:open="unlockOpen">
            <DialogTrigger as-child>
              <Button variant="outline">
                <Unlock class="h-4 w-4 mr-2" />
                Unlock
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Reopen this daily close?</DialogTitle>
                <DialogDescription>
                  Post-close corrections become possible again. This reopening is recorded permanently
                  against the day, with your name and the reason below.
                </DialogDescription>
              </DialogHeader>
              <div class="space-y-2">
                <Label for="unlock-reason">Reason for reopening</Label>
                <Textarea
                  id="unlock-reason"
                  v-model="unlockForm.reason"
                  rows="3"
                  placeholder="e.g. Attendant reported nozzle 1 closing reading was transposed."
                />
                <p v-if="unlockForm.errors.reason" class="text-sm text-status-critical">
                  {{ unlockForm.errors.reason }}
                </p>
              </div>
              <DialogFooter>
                <DialogClose as-child>
                  <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button :disabled="unlockForm.processing" @click="unlockTransaction">Reopen day</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </template>
      </div>
    </template>

    <!-- Status Banner -->
    <div v-if="transaction.status !== 'posted' || transaction.is_locked" class="mb-6">
      <div
        :class="[
          'rounded-lg border p-4 flex items-center gap-3',
          transaction.status === 'reversed' ? 'bg-status-critical/10 border-status-critical/30' : '',
          transaction.status === 'locked' || transaction.is_locked ? 'bg-status-attention/10 border-status-attention/30' : '',
          transaction.status === 'correction' ? 'bg-status-info/10 border-status-info/30' : '',
        ]"
      >
        <component
          :is="statusConfig.icon"
          :class="[
            'h-5 w-5',
            transaction.status === 'reversed' ? 'text-status-critical' : '',
            transaction.status === 'locked' || transaction.is_locked ? 'text-status-attention' : '',
            transaction.status === 'correction' ? 'text-status-info' : '',
          ]"
        />
        <div class="flex-1">
          <div class="font-medium">
            <template v-if="transaction.is_locked">
              This entry is locked
              <span v-if="transaction.locked_at" class="text-sm font-normal text-muted-foreground">
                ({{ formatDateTime(transaction.locked_at) }})
              </span>
            </template>
            <template v-else-if="transaction.status === 'reversed'">
              This entry has been reversed
            </template>
            <template v-else-if="transaction.status === 'correction'">
              This is a correction entry
            </template>
          </div>
        </div>
        <Badge :variant="statusConfig.variant">
          <component :is="statusConfig.icon" class="h-3 w-3 mr-1" />
          {{ statusConfig.label }}
        </Badge>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left Column: Sales Summary -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Fuel class="h-5 w-5" />
            Sales Summary
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <!-- Fuel Sales by Category -->
          <div v-if="fuelSalesEntries.length > 0" class="space-y-2">
            <div v-for="entry in fuelSalesEntries" :key="entry.category" class="flex justify-between items-center py-2 border-b last:border-0">
              <div>
                <span class="font-medium capitalize">{{ entry.category }}</span>
                <span class="text-sm text-muted-foreground ml-2">{{ entry.liters?.toFixed(0) || 0 }} L</span>
              </div>
              <span class="font-semibold"><MoneyText :amount="entry.revenue" :currency="currency" :fraction-digits="0" /></span>
            </div>
          </div>

          <!-- Other Sales -->
          <div v-if="metadata.other_sales" class="flex justify-between items-center py-2 border-b">
            <span>Other Sales (Lubricants, etc.)</span>
            <span class="font-semibold"><MoneyText :amount="metadata.other_sales" :currency="currency" :fraction-digits="0" /></span>
          </div>

          <Separator />

          <!-- Total Revenue -->
          <div class="flex justify-between items-center text-lg font-bold">
            <span>Total Revenue</span>
            <span><MoneyText :amount="metadata.total_revenue" :currency="currency" :fraction-digits="0" /></span>
          </div>

          <!-- COGS -->
          <div class="flex justify-between items-center text-muted-foreground">
            <span>Cost of Goods Sold</span>
            <span><MoneyText :amount="metadata.total_cogs" :currency="currency" :fraction-digits="0" /></span>
          </div>

          <!-- Gross Profit -->
          <div class="flex justify-between items-center text-status-success font-semibold">
            <span>Gross Profit</span>
            <span><MoneyText :amount="(metadata.total_revenue || 0) - (metadata.total_cogs || 0)" :currency="currency" :fraction-digits="0" /></span>
          </div>
        </CardContent>
      </Card>

      <!-- Right Column: Cash Summary -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Wallet class="h-5 w-5" />
            Cash Summary
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <!-- Cash In -->
          <div class="space-y-2">
            <div class="flex justify-between items-center py-2">
              <span>Opening Cash</span>
              <span class="font-semibold"><MoneyText :amount="metadata.opening_cash" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.partner_deposits" class="flex justify-between items-center py-2">
              <span>Partner Deposits</span>
              <span class="font-semibold text-status-success">+<MoneyText :amount="metadata.partner_deposits" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.amanat_deposits" class="flex justify-between items-center py-2">
              <span>Amanat Deposits</span>
              <span class="font-semibold text-status-success">+<MoneyText :amount="metadata.amanat_deposits" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.other_deposits" class="flex justify-between items-center py-2">
              <span>Other Cash In</span>
              <span class="font-semibold text-status-success">+<MoneyText :amount="metadata.other_deposits" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span>Total Sales</span>
              <span class="font-semibold text-status-success">+<MoneyText :amount="metadata.total_revenue" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.bank_withdrawals" class="flex justify-between items-center py-2">
              <span>Cash Withdrawn from Bank</span>
              <MoneyText :amount="metadata.bank_withdrawals" :currency="currency" />
            </div>
            <div class="flex justify-between items-center py-2 font-semibold">
              <span>Total Money In</span>
              <span><MoneyText :amount="totalMoneyIn" :currency="currency" :fraction-digits="0" /></span>
            </div>
          </div>

          <Separator />

          <!-- Cash Out -->
          <div class="space-y-2">
            <div v-for="credit in metadata.credit_sale_details || []" :key="credit.invoice_id" class="flex justify-between items-center py-2">
              <Link :href="`/${company.slug}/invoices/${credit.invoice_id}`" class="underline">{{ t('meterCreditSales') }} · {{ credit.customer_name }} · {{ credit.invoice_number }}</Link>
              <span>-<MoneyText :amount="credit.amount" :currency="currency" /></span>
            </div>
            <div v-for="row in channelOutRows" :key="row.channel_code" class="flex justify-between items-center py-2">
              <span>{{ row.channel_label }} → bank / card account</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="row.amount" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.bank_deposits" class="flex justify-between items-center py-2">
              <span>Bank Deposits</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.bank_deposits" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.partner_withdrawals" class="flex justify-between items-center py-2">
              <span>Partner Withdrawals</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.partner_withdrawals" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.employee_advances" class="flex justify-between items-center py-2">
              <span>Employee Advances</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.employee_advances" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.payroll_payouts" class="flex justify-between items-center py-2">
              <span>Approved Salaries</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.payroll_payouts" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.cash_bill_payments" class="flex justify-between items-center py-2">
              <span>Supplier Bill Payments (station cash)</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.cash_bill_payments" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.amanat_disbursements" class="flex justify-between items-center py-2">
              <span>Amanat Disbursements</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.amanat_disbursements" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div v-if="metadata.expenses" class="flex justify-between items-center py-2">
              <span>Expenses</span>
              <span class="font-semibold text-status-critical">-<MoneyText :amount="metadata.expenses" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div class="flex justify-between items-center py-2 font-semibold">
              <span>Total Money Out</span>
              <span class="text-status-critical">-<MoneyText :amount="totalMoneyOut" :currency="currency" :fraction-digits="0" /></span>
            </div>
          </div>

          <Separator />

          <!-- Closing -->
          <div class="space-y-2">
            <div class="flex justify-between items-center py-2 text-muted-foreground">
              <span>Expected Closing</span>
              <span><MoneyText :amount="metadata.expected_closing" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <div class="flex justify-between items-center py-2 text-lg font-bold">
              <span>Actual Closing Cash</span>
              <span><MoneyText :amount="metadata.closing_cash" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <!-- Over and short are both variances, and the label beside the
                 figure already says which. Blue for one and red for the other
                 said the till being over was merely informational. -->
            <div
              v-if="metadata.variance !== undefined && metadata.variance !== 0"
              class="flex justify-between items-center py-2 font-semibold text-status-attention"
            >
              <span>{{ metadata.variance > 0 ? 'Cash Over' : 'Cash Short' }}</span>
              <span><MoneyText :amount="Math.abs(metadata.variance)" :currency="currency" :fraction-digits="0" /></span>
            </div>
            <!-- A till that balanced is the ordinary outcome, not an achievement.
                 The tick is the indicator; green on top of it is celebration. -->
            <div v-else-if="metadata.variance === 0" class="flex justify-between items-center py-2 font-semibold">
              <span>Variance</span>
              <span class="flex items-center gap-1">
                <CheckCircle class="h-4 w-4" />
                Balanced
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Fuel Deliveries Received on Posting -->
    <Card v-if="(metadata.deliveries_received || []).length > 0" class="mt-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Fuel class="h-5 w-5" />
          Fuel Deliveries Received
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground mb-3">
          These bills were entered before their goods were received. Posting this close received them,
          dated on each bill's own date, so the litres are real stock rather than a dip "gain".
        </p>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left py-1">Bill</th>
              <th class="text-left py-1">Date</th>
              <th class="text-left py-1">Tank</th>
              <th class="text-right py-1">Litres</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="delivery in metadata.deliveries_received" :key="delivery.bill_id + delivery.line_id" class="border-b last:border-0">
              <td class="py-1">
                <Link :href="`/${company.slug}/bills/${delivery.bill_id}`" class="underline hover:no-underline">
                  {{ delivery.bill_number }}
                </Link>
              </td>
              <td class="py-1">{{ formatDate(delivery.bill_date) }}</td>
              <td class="py-1">{{ delivery.tank }}</td>
              <td class="py-1 text-right">{{ Number(delivery.litres).toFixed(0) }} L</td>
            </tr>
          </tbody>
        </table>
      </CardContent>
    </Card>

    <!-- Transaction Details -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Transaction Details</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-muted-foreground">Transaction Number</span>
            <p class="font-mono font-semibold">{{ transaction.transaction_number }}</p>
          </div>
          <div>
            <span class="text-muted-foreground">Transaction Date</span>
            <p class="font-semibold">{{ formatDate(transaction.transaction_date) }}</p>
          </div>
          <div>
            <span class="text-muted-foreground">Created At</span>
            <p class="font-semibold">{{ formatDateTime(transaction.created_at) }}</p>
          </div>
          <div>
            <span class="text-muted-foreground">Status</span>
            <div class="mt-1">
              <Badge :variant="statusConfig.variant">
                {{ statusConfig.label }}
              </Badge>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
