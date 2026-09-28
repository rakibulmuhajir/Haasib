<script setup lang="ts">
import DailyCloseNav from '../../../components/DailyCloseNav.vue'
import DailyCloseDaySheet from '../../../components/DailyCloseDaySheet.vue'
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
    /** Fuel run through a nozzle for a pump-calibration test and poured back into the tank. */
    pump_tests?: Array<{ nozzle_id: string; fuel: string; liters: number }>
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
    cash_pay_suppliers?: number
    pay_suppliers_total?: number
    pay_supplier_details?: Array<{ payment_id: string | null; vendor_id: string; vendor_name: string; amount: number; applied_to_bills: number; advance_amount: number; payment_account_id: string; payment_account_name: string; affects_cash_drawer: boolean; reference: string | null }>
    amanat_disbursements?: number
    credit_sales_total?: number
    credit_sale_details?: Array<{ invoice_id: string; invoice_number: string; customer_name: string; amount: number; source?: string; discount_amount?: number; net_amount?: number }>
    accounting_invoices_included?: Array<{ invoice_id: string; invoice_number: string; customer: string; amount: number }>
    payment_receipt_postings?: Array<{ channel_code: string; channel_label: string; channel_type: string; account_id: string | null; amount: number }>
    channel_supplier_settlements?: Array<{ channel_code: string; channel_label: string; vendor_id: string; vendor_name: string; clearing_account_id: string; clearing_account_name: string; card_sales: number; amount_paid: number; applied_to_bills: number; advance_amount: number; bill_payment_id: string | null }>
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
  // Current, post-any-discount figures for each of this close's credit-sale invoices --
  // not the frozen metadata.credit_sale_details, which is the snapshot as it stood the
  // moment this close posted and never changes after that.
  creditSaleInvoices?: Array<{
    invoice_id: string
    invoice_number: string
    customer_id: string | null
    customer_name: string | null
    source?: string
    amount: number
    discount_amount: number
    total_amount: number | null
    balance: number | null
  }>
  fuelItems?: Array<{ id: string; name: string }>
  customerFuelDiscounts?: Array<{ customer_id: string; item_id: string; discount_type: 'percent' | 'per_litre'; value: number }>
  canApplyPostCloseDiscount?: boolean
  canEditDay?: boolean
  editDayDisabledReason?: string | null
  editDayLaterDates?: string[]
  revisionHistory?: Array<{ id: string; created_at: string; reason: string; reopened_by_name: string | null }>
  accountNames?: Record<string, string>
  nozzleNames?: Record<string, { name: string; tank_id: string | null }>
  previousTankDips?: Record<string, number>
  paymentSources?: Record<string, { direct: boolean; invoices: string }>
  permissions: {
    canLock: boolean
    canUnlock: boolean
  }
}>()

// "Edit day": removes everything this close posted and reopens the same date as a draft on
// the Create page. Editing the day replaces the old scattered post-close expense/correction
// inputs entirely, so those forms are gone from this page -- see DailyCloseReopenService.
const editDayForm = useForm({ reason: '' })
const editDayOpen = ref(false)
const editDay = () => {
  editDayForm.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/reopen`, {
    preserveScroll: true,
    onError: (errors) => toast.error(String(Object.values(errors)[0] ?? 'Failed to reopen day')),
  })
}

// Merges the frozen snapshot (customer name, reference, source) with each invoice's
// current figures, so a post-close discount shows up here without waiting for another
// close. Falls back to the snapshot's own figures for an older close rendered before
// creditSaleInvoices existed.
const creditRows = computed(() => {
  const fresh = new Map((props.creditSaleInvoices ?? []).map((row) => [row.invoice_id, row]))
  return (props.transaction.metadata.credit_sale_details || []).map((frozen) => {
    const current = fresh.get(frozen.invoice_id)
    const amount = current?.amount ?? frozen.amount
    const discountAmount = current?.discount_amount ?? frozen.discount_amount ?? 0
    const balance = current?.balance ?? current?.total_amount ?? frozen.net_amount ?? (frozen.amount - (frozen.discount_amount ?? 0))
    return {
      invoice_id: frozen.invoice_id,
      invoice_number: frozen.invoice_number,
      customer_id: current?.customer_id ?? null,
      customer_name: frozen.customer_name,
      source: frozen.source,
      amount,
      discount_amount: discountAmount,
      balance,
    }
  })
})

interface DiscountTarget {
  invoice_id: string
  invoice_number: string
  customer_id: string | null
  customer_name: string | null
  amount: number
  balance: number
}

const discountTarget = ref<DiscountTarget | null>(null)
const discountForm = useForm({ item_id: '', litres: null as number | null, discount_amount: 0 })

/** Prefills the discount from this customer's stored per-fuel discount, exactly as a
 *  fresh sale would price it (see CustomerFuelDiscountService). Still editable afterwards. */
const computeStoredDiscount = () => {
  if (!discountTarget.value || !discountForm.item_id) return
  const stored = (props.customerFuelDiscounts ?? []).find(
    (row) => row.customer_id === discountTarget.value!.customer_id && row.item_id === discountForm.item_id,
  )
  if (!stored) return
  const gross = discountTarget.value.amount
  const litres = Number(discountForm.litres ?? 0)
  const value = Number(stored.value)
  let amount = stored.discount_type === 'per_litre' ? litres * value : (gross * value) / 100
  amount = Math.min(Math.max(amount, 0), gross)
  discountForm.discount_amount = Math.round(amount * 100) / 100
}

const openDiscountDialog = (credit: (typeof creditRows.value)[number]) => {
  discountTarget.value = {
    invoice_id: credit.invoice_id,
    invoice_number: credit.invoice_number,
    customer_id: credit.customer_id,
    customer_name: credit.customer_name,
    amount: credit.amount,
    balance: credit.balance,
  }
  discountForm.reset()
  discountForm.clearErrors()
}

const submitDiscount = () => {
  if (!discountTarget.value) return
  discountForm.post(
    `/${props.company.slug}/fuel/daily-close/${props.transaction.id}/credit-sales/${discountTarget.value.invoice_id}/discount`,
    {
      preserveScroll: true,
      onSuccess: (page) => {
        if ((page.props as any).flash?.success) {
          toast.success('Discount applied')
          discountTarget.value = null
        }
      },
      onError: (errors) => toast.error(String(Object.values(errors)[0])),
    },
  )
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

// A supplier-settled channel (settles_to: 'supplier') pays the vendor the FULL card total
// out of clearing during this same close. Anything beyond what the vendor was actually owed
// on open bills is not left parked in clearing — it leaves clearing too, and sits with the
// vendor as an advance that its next bill draws down automatically, so this says so in
// plain words.
const supplierSettlementNotes = computed(() =>
  (metadata.value.channel_supplier_settlements ?? []).filter(s => Number(s.advance_amount) > 0.004)
)

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
    + Number(m.payroll_payouts || 0) + Number(m.cash_bill_payments || 0) + Number(m.cash_pay_suppliers || 0) + Number(m.amanat_disbursements || 0) + Number(m.expenses || 0)
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

        <template v-if="canEditDay || editDayDisabledReason">
          <Dialog v-model:open="editDayOpen">
            <DialogTrigger as-child>
              <Button variant="outline" :disabled="!canEditDay" :title="!canEditDay ? editDayDisabledReason ?? undefined : undefined">
                <RotateCcw class="h-4 w-4 mr-2" />
                Edit day
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Edit this day?</DialogTitle>
                <DialogDescription>
                  The day opens again as a draft in the same form, with everything as it was
                  entered. Re-post it when you are done and its entries are posted again. Nothing
                  is lost: this posted version is kept permanently in the day's history with your
                  name and the reason below, and invoices, bills or advances already settled on
                  other screens are kept as they are and linked from the draft.
                </DialogDescription>
              </DialogHeader>
              <p v-if="editDayLaterDates?.length" class="rounded-md border border-status-attention/40 bg-status-attention/10 px-3 py-2 text-sm">
                Later days ({{ editDayLaterDates.join(', ') }}) opened from this day's closing cash,
                meters and dips. If you change those, edit and re-post those days too.
              </p>
              <div class="space-y-2">
                <Label for="edit-day-reason">Reason for editing</Label>
                <Textarea
                  id="edit-day-reason"
                  v-model="editDayForm.reason"
                  rows="3"
                  placeholder="e.g. Forgot to record a customer payment during posting."
                />
                <p v-if="editDayForm.errors.reason" class="text-sm text-status-critical">
                  {{ editDayForm.errors.reason }}
                </p>
              </div>
              <DialogFooter>
                <DialogClose as-child>
                  <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button :disabled="editDayForm.processing" @click="editDay">Edit day</Button>
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

    <DailyCloseDaySheet
      :metadata="metadata"
      :currency="currency"
      :company-slug="company.slug"
      :fuel-items="fuelItems"
      :expense-accounts="expenseAccounts"
      :account-names="accountNames"
      :nozzle-names="nozzleNames"
      :previous-tank-dips="previousTankDips"
      :payment-sources="paymentSources"
      :credit-rows="creditRows"
      :can-apply-discount="canApplyPostCloseDiscount"
      @apply-discount="openDiscountDialog"
    />

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

    <!-- Accounting details: for checking the postings; opens itself if anything changed after posting -->
    <details class="mt-6 rounded-md border border-rule-default p-4">
      <summary class="cursor-pointer font-semibold">
        Accounting details
        <span class="ml-2 text-xs font-normal text-muted-foreground">reconciliation, ledger accounts, edit and audit history</span>
        <Badge v-if="reconciliation?.has_post_close_activity" variant="destructive" class="ml-2">Changed after posting</Badge>
      </summary>
      <div class="mt-4 space-y-6">
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

    <Card v-if="revisionHistory?.length" class="mb-6 border-status-attention/40">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <RotateCcw class="h-4 w-4" />
          This day has been edited {{ revisionHistory.length }} time{{ revisionHistory.length === 1 ? '' : 's' }}
        </CardTitle>
        <CardDescription>Each posted version before an edit is kept permanently.</CardDescription>
      </CardHeader>
      <CardContent>
        <ul class="space-y-3">
          <li v-for="entry in revisionHistory" :key="entry.id" class="border-l-2 border-status-attention/50 pl-3">
            <p class="text-sm font-medium">
              Edited {{ formatDateTime(entry.created_at) }}{{ entry.reopened_by_name ? ` by ${entry.reopened_by_name}` : '' }}
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
          <p class="mb-3 text-sm text-muted-foreground">
            Read-only. To add a forgotten transaction, use "Edit day" above instead of a separate
            post-close entry.
          </p>
          <p v-if="!reconciliation.has_post_close_activity" class="text-sm text-muted-foreground">No changes since posting.</p>
          <div v-for="row in reconciliation.activity" :key="row.type + row.id" class="border-b py-3 text-sm">
            <p class="font-medium">{{ row.activity }} · {{ row.type }} · <Link v-if="!row.type.startsWith('stock:')" :href="`/${company.slug}/journals/${row.id}`" class="underline">{{ row.reference }}</Link><span v-else>{{ row.reference }}</span></p>
            <p v-if="row.description">{{ row.description }}</p>
            <p>Business date {{ row.business_date }} · Entered {{ formatDateTime(row.entered_at) }} by {{ row.entered_by_name }}</p>
            <p v-if="row.before">Updated {{ formatDateTime(row.updated_at) }} · {{ row.updated_by_name || 'Actor unavailable' }}</p>
            <p>{{ row.source_type }} · {{ row.source_id || row.id }}</p>
            <p>Amount <MoneyText :amount="row.amount" :currency="currency" /> · Cash reconciliation effect <MoneyText :amount="row.reconciliation_effect" :currency="currency" /></p>
            <p v-if="row.quantity_effect">Stock effect: {{ row.quantity_effect }} litres · Tank {{ row.warehouse_id }}</p>
          </div>
        </div>
        <div>
          <h3 class="mb-2 font-semibold">READING CORRECTIONS</h3>
          <p class="mb-3 text-sm text-muted-foreground">
            Read-only. To correct a tank or nozzle reading, use "Edit day" above instead of a
            separate post-close correction.
          </p>
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

      </div>
    </details>

    <Dialog :open="!!discountTarget" @update:open="(open) => { if (!open) discountTarget = null }">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Apply a discount</DialogTitle>
          <DialogDescription v-if="discountTarget">
            {{ discountTarget.invoice_number }} · {{ discountTarget.customer_name }}
          </DialogDescription>
        </DialogHeader>
        <div v-if="discountTarget" class="space-y-3">
          <div>
            <Label>Fuel</Label>
            <Select v-model="discountForm.item_id" @update:model-value="computeStoredDiscount">
              <SelectTrigger><SelectValue placeholder="Choose fuel" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="item in fuelItems" :key="item.id" :value="item.id">{{ item.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Litres</Label>
            <Input v-model.number="discountForm.litres" type="number" min="0" step="0.001" @change="computeStoredDiscount" />
          </div>
          <div>
            <Label>Discount amount</Label>
            <Input v-model.number="discountForm.discount_amount" type="number" min="0.01" step="0.01" required />
          </div>
          <p class="text-sm text-muted-foreground">
            Amount <MoneyText :amount="discountTarget.amount" :currency="currency" /> ·
            Discount <MoneyText :amount="discountForm.discount_amount" :currency="currency" /> ·
            Owes <MoneyText :amount="Math.max(0, discountTarget.balance - (discountForm.discount_amount || 0))" :currency="currency" />
          </p>
          <p v-for="(error, field) in discountForm.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
        </div>
        <DialogFooter>
          <DialogClose as-child>
            <Button variant="outline">Cancel</Button>
          </DialogClose>
          <Button :disabled="discountForm.processing || !discountForm.item_id" @click="submitDiscount">
            {{ discountForm.processing ? 'Applying…' : 'Apply discount' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
