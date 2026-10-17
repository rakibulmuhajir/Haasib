<script setup lang="ts">
import { localToday } from '@/composables/useEntryDate'
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { CreditCard, AlertTriangle, CheckCircle, Clock, Search, Banknote } from 'lucide-vue-next'
import { formatMoneyText } from '@/lib/money'
import MoneyText from '@/components/MoneyText.vue'
import InputError from '@/components/InputError.vue'

interface VendorCardSale {
  id: string
  invoice_id: string
  customer_name: string
  invoice_number: string
  invoice_date: string
  amount: number
  settled_amount: number
  outstanding: number
  status: 'pending' | 'settled' | 'overdue'
}

interface VendorCardSummary {
  total_pending: number
  total_settled_today: number
  total_outstanding: number
  count_pending: number
  total_clearing_outstanding?: number
}

interface ClearingAccountSummary {
  channel_code: string
  channel_label: string
  channel_type: string
  clearing_account_id: string
  clearing_account_name: string
  bank_account_id: string | null
  balance: number
}

interface BankAccount {
  id: string
  code: string
  name: string
}

interface CardChargeChannel {
  code: string
  label: string
  settles_to: 'clearing' | 'bank' | 'supplier'
  vendor_name: string | null
  account_name: string | null
}

interface CardChargeRow {
  id: string
  date: string
  channel: string
  amount: number
  period_from: string
  period_to: string
  sales: number
  rate: number | null
  reference: string | null
  bill_id: string | null
  undone: boolean
}

const props = defineProps<{
  pendingSales: VendorCardSale[]
  clearingAccounts: ClearingAccountSummary[]
  summary: VendorCardSummary
  todaySettlements: VendorCardSale[]
  bankAccounts: BankAccount[]
  cardChargeChannels?: CardChargeChannel[]
  cardCharges?: CardChargeRow[]
  canRecordCharge?: boolean
  // The chosen card's sales over the dialog's period (partial reload), for a % charge.
  cardChargeSales?: number | null
}>()

// The charge the bank or card supplier kept, entered by hand -- usually at month end, so the
// date starts on the last day of last month (or today, when today is month end).
const monthEnd = () => {
  const today = localToday()
  const [y, m, d] = today.split('-').map(Number)
  if (new Date(y, m - 1, d + 1).getDate() === 1) return today
  const last = new Date(y, m - 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
// The card sales a charge covers: the month of its date unless changed.
const monthOf = (date: string) => {
  const [y, m] = date.split('-').map(Number)
  const last = new Date(y, m, 0).getDate()
  return { from: `${y}-${String(m).padStart(2, '0')}-01`, to: `${y}-${String(m).padStart(2, '0')}-${String(last).padStart(2, '0')}` }
}
const showChargeDialog = ref(false)
const chargeForm = useForm({
  channel_code: '',
  charge_date: monthEnd(),
  period_from: monthOf(monthEnd()).from,
  period_to: monthOf(monthEnd()).to,
  amount: null as number | null,
  percent: null as number | null,
  reference: '',
  notes: '',
})
// A charge is entered as rupees from the statement, or as a % of the card's sales in the period.
const chargeBy = ref<'amount' | 'percent'>('amount')
const loadChargeSales = () => {
  if (!chargeForm.channel_code || !chargeForm.period_from || !chargeForm.period_to) return
  router.reload({
    only: ['cardChargeSales'],
    data: { charge_channel: chargeForm.channel_code, charge_from: chargeForm.period_from, charge_to: chargeForm.period_to },
  })
}
watch(() => [chargeForm.channel_code, chargeForm.period_from, chargeForm.period_to, showChargeDialog.value], () => {
  if (showChargeDialog.value) loadChargeSales()
})
const chargeSales = computed(() => Number(props.cardChargeSales ?? 0))
const chargeFromPercent = computed(() => Math.round(chargeSales.value * Number(chargeForm.percent || 0)) / 100)
const chargeRate = computed(() => (chargeSales.value > 0 && chargeForm.amount ? (Number(chargeForm.amount) / chargeSales.value) * 100 : null))
const chargeChannel = computed(() => (props.cardChargeChannels ?? []).find((c) => c.code === chargeForm.channel_code))
const chargeComesFrom = (c: CardChargeChannel | undefined) =>
  !c ? '' : c.settles_to === 'supplier' ? `Added to what you owe ${c.vendor_name}` : `Taken from ${c.account_name}`
const openChargeDialog = () => {
  chargeForm.reset()
  chargeForm.clearErrors()
  chargeForm.channel_code = props.cardChargeChannels?.[0]?.code ?? ''
  chargeForm.charge_date = monthEnd()
  chargeForm.period_from = monthOf(chargeForm.charge_date).from
  chargeForm.period_to = monthOf(chargeForm.charge_date).to
  showChargeDialog.value = true
}
const submitCharge = () => {
  chargeForm.transform((data) => ({
    ...data,
    amount: chargeBy.value === 'amount' ? data.amount : null,
    percent: chargeBy.value === 'percent' ? data.percent : null,
  })).post(`/${companySlug.value}/fuel/card-charges`, {
    preserveScroll: true,
    onSuccess: () => { showChargeDialog.value = false },
  })
}
const undoingCharge = ref<string | null>(null)
const undoCharge = (charge: CardChargeRow) => {
  undoingCharge.value = charge.id
  router.delete(`/${companySlug.value}/fuel/card-charges/${charge.id}`, {
    preserveScroll: true,
    onFinish: () => { undoingCharge.value = null },
  })
}

const page = usePage()
const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Vendor Cards', href: `/${companySlug.value}/fuel/vendor-cards/pending` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const search = ref('')
const selectedSales = ref<Set<string>>(new Set())

const filteredPendingSales = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.pendingSales.filter(sale =>
    sale.customer_name.toLowerCase().includes(q) ||
    sale.invoice_number.toLowerCase().includes(q)
  )
})

const formatCurrency = (value: number) => {
  return formatMoneyText(value, currencyCode.value, { locale: 'en-PK', fractionDigits: 0 })
}

const formatDate = (date: string) => {
  return formatDateTime(date, { mode: 'date' })
}

const selectedTotal = computed(() => {
  return Array.from(selectedSales.value).reduce((total, id) => {
    const sale = props.pendingSales.find(s => s.id === id)
    return total + (sale?.outstanding || 0)
  }, 0)
})

const settlementForm = useForm<{
  invoice_ids: string[]
  amount_received: number | null
  settlement_date: string
  bank_account_id: string | null
  reference: string
  notes: string
}>({
  invoice_ids: [],
  amount_received: null,
  settlement_date: localToday(),
  bank_account_id: null,
  reference: '',
  notes: '',
})

const showSettlementDialog = ref(false)
const showClearingDialog = ref(false)

const clearingSettlementForm = useForm({
  clearing_account_id: '',
  bank_account_id: '',
  amount_received: null as number | null,
  fees: 0,
  settlement_date: localToday(),
  reference: '',
  notes: '',
})

const selectedClearingAccount = computed(() =>
  props.clearingAccounts.find((account) => account.clearing_account_id === clearingSettlementForm.clearing_account_id)
)

const openSettlementDialog = () => {
  if (selectedSales.value.size === 0) return

  settlementForm.invoice_ids = Array.from(selectedSales.value).map((saleId) => {
    const sale = props.pendingSales.find((pending) => pending.id === saleId)
    return sale?.invoice_id || ''
  }).filter(Boolean)
  settlementForm.amount_received = selectedTotal.value
  settlementForm.reference = `Vendor Card Settlement ${localToday()}`
  showSettlementDialog.value = true
}

const submitSettlement = () => {
  const slug = companySlug.value
  if (!slug) return

  settlementForm.post(`/${slug}/fuel/vendor-cards/settle`, {
    preserveScroll: true,
    onSuccess: () => {
      showSettlementDialog.value = false
      settlementForm.reset()
      selectedSales.value.clear()
    },
  })
}

const openClearingDialog = (account: ClearingAccountSummary) => {
  clearingSettlementForm.clearing_account_id = account.clearing_account_id
  clearingSettlementForm.bank_account_id = account.bank_account_id || props.bankAccounts[0]?.id || ''
  clearingSettlementForm.amount_received = account.balance
  clearingSettlementForm.fees = 0
  clearingSettlementForm.reference = `${account.channel_label} Settlement ${localToday()}`
  showClearingDialog.value = true
}

const submitClearingSettlement = () => {
  const slug = companySlug.value
  if (!slug) return

  clearingSettlementForm.post(`/${slug}/fuel/payment-channels/settle`, {
    preserveScroll: true,
    onSuccess: () => {
      showClearingDialog.value = false
      clearingSettlementForm.reset()
    },
  })
}

const toggleSaleSelection = (saleId: string) => {
  if (selectedSales.value.has(saleId)) {
    selectedSales.value.delete(saleId)
  } else {
    selectedSales.value.add(saleId)
  }
}

const selectAllPending = () => {
  if (selectedSales.value.size === props.pendingSales.length) {
    selectedSales.value.clear()
  } else {
    selectedSales.value.clear()
    props.pendingSales.forEach(sale => selectedSales.value.add(sale.id))
  }
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'pending':
      return { class: 'bg-status-attention/10 text-status-attention', icon: Clock, label: 'Pending' }
    case 'settled':
      return { class: 'bg-status-success/10 text-status-success', icon: CheckCircle, label: 'Settled' }
    case 'overdue':
      return { class: 'bg-status-critical/10 text-status-critical', icon: AlertTriangle, label: 'Overdue' }
    default:
      return { class: 'bg-surface-sunken text-text-primary', icon: Clock, label: status }
  }
}

const pendingColumns = [
  { key: 'select', label: '', sortable: false, width: '50px', kind: 'text' as const },
  { key: 'customer', label: 'Customer', kind: 'text' as const },
  { key: 'invoice', label: 'Invoice', kind: 'ref' as const },
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'outstanding', label: 'Outstanding', kind: 'amount' as const, align: 'right' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
]

const todayColumns = [
  { key: 'customer', label: 'Customer', kind: 'text' as const },
  { key: 'invoice', label: 'Invoice', kind: 'ref' as const },
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'amount', label: 'Settled', kind: 'amount' as const, align: 'right' as const },
]

const pendingTableData = computed(() => {
  return filteredPendingSales.value.map((sale) => ({
    id: sale.id,
    select: sale.id,
    customer: sale.customer_name,
    invoice: sale.invoice_number,
    date: formatDate(sale.invoice_date),
    outstanding: formatCurrency(sale.outstanding),
    status: sale.status,
    _raw: sale,
  }))
})

const todayTableData = computed(() => {
  return props.todaySettlements.map((sale) => ({
    id: sale.id,
    customer: sale.customer_name,
    invoice: sale.invoice_number,
    date: formatDate(sale.invoice_date),
    amount: formatCurrency(sale.settled_amount),
    _raw: sale,
  }))
})
</script>

<template>
  <Head title="Vendor Card Settlements" />

  <PageShell
    title="Vendor Card Settlements"
    description="Manage vendor card payments and settlements"
    :icon="CreditCard"
    :breadcrumbs="breadcrumbs"
  >
    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-surface-sunken">
        <CardHeader class="pb-2">
          <CardDescription>Pending Settlements</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="props.summary.total_pending" :currency="currencyCode" :fraction-digits="0" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <AlertTriangle class="h-4 w-4 text-status-attention" />
            <span>{{ props.summary.count_pending }} transaction(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Settled Today</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="props.summary.total_settled_today" :currency="currencyCode" :fraction-digits="0" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-status-success/10 text-status-success hover:bg-status-success/10">
            <CheckCircle class="mr-1 h-3 w-3" />
            Completed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Outstanding</CardDescription>
          <CardTitle class="text-2xl text-status-attention"><MoneyText :amount="props.summary.total_outstanding" :currency="currencyCode" :fraction-digits="0" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-status-attention/30 text-status-attention">
            <Clock class="mr-1 h-3 w-3" />
            Awaiting Settlement
          </Badge>
        </CardContent>
      </Card>
    </div>

    <!-- Pending Settlements -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Pending Settlements</CardTitle>
            <CardDescription>Select transactions to settle with vendors</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[200px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search..." class="pl-9" />
            </div>
            <Button
              v-if="selectedSales.size > 0"
              class="bg-status-success hover:bg-status-success"
              @click="openSettlementDialog"
            >
              <Banknote class="mr-2 h-4 w-4" />
              Settle Selected (<MoneyText :amount="selectedTotal" :currency="currencyCode" :fraction-digits="0" />)
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <LedgerRegister :data="pendingTableData" :columns="pendingColumns">
          <template #empty>
            <EmptyState
              title="No pending settlements"
              description="All vendor card transactions have been settled."
            />
          </template>

          <template #cell-select="{ row }">
            <input
              type="checkbox"
              :checked="selectedSales.has(row.select)"
              @change="toggleSaleSelection(row.select)"
              class="rounded-sm border-border"
            />
          </template>

          <template #cell-status="{ row }">
            <Badge :class="getStatusBadge(row.status).class" class="hover:opacity-100">
              <component :is="getStatusBadge(row.status).icon" class="mr-1 h-3 w-3" />
              {{ getStatusBadge(row.status).label }}
            </Badge>
          </template>

          <template #header-select>
            <input
              type="checkbox"
              :checked="selectedSales.size === props.pendingSales.length && props.pendingSales.length > 0"
              :indeterminate="selectedSales.size > 0 && selectedSales.size < props.pendingSales.length"
              @change="selectAllPending"
              class="rounded-sm border-border"
            />
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>

    <!-- Clearing Account Settlements -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Payment Channel Clearing</CardTitle>
        <CardDescription>Outstanding POS, fuel-card, and wallet balances posted from daily close</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <EmptyState
          v-if="props.clearingAccounts.length === 0"
          title="No clearing balances"
          description="Mapped payment channels with pending balances will appear here."
        />
        <div
          v-for="account in props.clearingAccounts"
          v-else
          :key="account.clearing_account_id"
          class="flex items-center justify-between rounded-lg border p-3"
        >
          <div>
            <div class="font-medium">{{ account.channel_label }}</div>
            <div class="text-xs text-muted-foreground">{{ account.clearing_account_name }}</div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right font-semibold text-status-attention"><MoneyText :amount="account.balance" :currency="currencyCode" :fraction-digits="0" /></div>
            <Button size="sm" @click="openClearingDialog(account)">Settle</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- The charge the bank or the card's supplier kept on card sales -->
    <Card class="border-border/80">
      <CardHeader class="flex flex-row items-start justify-between gap-3">
        <div>
          <CardTitle class="text-base">Card charges</CardTitle>
          <CardDescription>Charges the bank or supplier kept</CardDescription>
        </div>
        <Button v-if="canRecordCharge && (cardChargeChannels ?? []).length" size="sm" @click="openChargeDialog">Record charge</Button>
        <p v-else-if="!(cardChargeChannels ?? []).length" class="text-xs text-muted-foreground">Set a card to manual charges in Station settings.</p>
      </CardHeader>
      <CardContent class="space-y-2">
        <p v-if="!(cardCharges ?? []).length" class="text-sm text-muted-foreground">None recorded yet.</p>
        <div
          v-for="charge in cardCharges ?? []"
          :key="charge.id"
          class="flex items-center justify-between gap-3 rounded-lg border p-3"
          :class="{ 'opacity-60': charge.undone }"
        >
          <div class="min-w-0">
            <div class="font-medium" :class="{ 'line-through': charge.undone }">{{ charge.channel }}</div>
            <div class="text-xs text-muted-foreground">
              {{ formatDate(charge.date) }} · sales {{ formatDate(charge.period_from) }} to {{ formatDate(charge.period_to) }}<template v-if="charge.rate !== null"> · {{ +charge.rate.toFixed(2) }}% of {{ formatCurrency(charge.sales) }}</template><template v-if="charge.reference"> · {{ charge.reference }}</template>
              <template v-if="charge.undone"> · Undone</template>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <MoneyText class="font-semibold" :amount="charge.amount" :currency="currencyCode" :fraction-digits="0" />
            <Button v-if="canRecordCharge && !charge.undone" size="sm" variant="ghost" :disabled="undoingCharge === charge.id" @click="undoCharge(charge)">Undo</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Today's Settlements -->
    <Card v-if="props.todaySettlements.length > 0" class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Today's Settlements</CardTitle>
        <CardDescription>Vendor card settlements processed today</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <LedgerRegister :data="todayTableData" :columns="todayColumns" />
      </CardContent>
    </Card>

    <!-- Settlement Dialog -->
    <Dialog :open="showSettlementDialog" @update:open="(v: boolean) => showSettlementDialog = v">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <CreditCard class="h-5 w-5 text-status-info" />
            Vendor Card Settlement
          </DialogTitle>
          <DialogDescription>
            Settle {{ selectedSales.size }} transaction(s) totaling <MoneyText :amount="selectedTotal" :currency="currencyCode" :fraction-digits="0" />
          </DialogDescription>
        </DialogHeader>

        <form novalidate class="space-y-4" @submit.prevent="submitSettlement">
          <div class="space-y-2">
            <Label>Settlement Amount *</Label>
            <Input
              v-model.number="settlementForm.amount_received"
              type="number"
              min="1"
              :max="selectedTotal"
              step="1"
              :class="{ 'border-destructive': settlementForm.errors.amount_received }"
            />
            <InputError :message="settlementForm.errors.amount_received" />
          </div>

          <div class="space-y-2">
            <Label>Settlement Date</Label>
            <Input v-model="settlementForm.settlement_date" type="date" />
            <InputError :message="settlementForm.errors.settlement_date" />
          </div>

          <div class="space-y-2">
            <Label>Reference</Label>
            <Input
              v-model="settlementForm.reference"
              placeholder="Settlement reference number"
            />
            <InputError :message="settlementForm.errors.reference" />
          </div>

          <div class="space-y-2">
            <Label>Notes</Label>
            <Input
              v-model="settlementForm.notes"
              placeholder="Optional notes..."
            />
            <InputError :message="settlementForm.errors.notes" />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="settlementForm.processing" @click="showSettlementDialog = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-status-info hover:bg-status-info" :disabled="settlementForm.processing">
              <span
                v-if="settlementForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Process Settlement
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Clearing Account Settlement Dialog -->
    <Dialog :open="showClearingDialog" @update:open="(v: boolean) => showClearingDialog = v">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Settle Payment Channel</DialogTitle>
          <DialogDescription>
            {{ selectedClearingAccount?.channel_label }} outstanding: <MoneyText :amount="selectedClearingAccount?.balance || 0" :currency="currencyCode" :fraction-digits="0" />
          </DialogDescription>
        </DialogHeader>

        <form novalidate class="space-y-4" @submit.prevent="submitClearingSettlement">
          <div class="space-y-2">
            <Label>Destination Bank *</Label>
            <Select v-model="clearingSettlementForm.bank_account_id">
              <SelectTrigger><SelectValue placeholder="Select bank" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="account in props.bankAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="clearingSettlementForm.errors.bank_account_id" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-2">
              <Label>Amount Received *</Label>
              <Input v-model.number="clearingSettlementForm.amount_received" type="number" min="0.01" step="0.01" />
              <InputError :message="clearingSettlementForm.errors.amount_received" />
            </div>
            <div class="space-y-2">
              <Label>Fees</Label>
              <Input v-model.number="clearingSettlementForm.fees" type="number" min="0" step="0.01" />
              <InputError :message="clearingSettlementForm.errors.fees" />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Settlement Date</Label>
            <Input v-model="clearingSettlementForm.settlement_date" type="date" />
            <InputError :message="clearingSettlementForm.errors.settlement_date" />
          </div>

          <div class="space-y-2">
            <Label>Reference</Label>
            <Input v-model="clearingSettlementForm.reference" />
            <InputError :message="clearingSettlementForm.errors.reference" />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="showClearingDialog = false">Cancel</Button>
            <Button type="submit" :disabled="clearingSettlementForm.processing">Process Settlement</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
    <Dialog v-model:open="showChargeDialog">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Record card charge</DialogTitle>
          <DialogDescription>What the bank or supplier kept from card sales.</DialogDescription>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitCharge">
          <div class="space-y-2">
            <Label>Card</Label>
            <Select v-model="chargeForm.channel_code">
              <SelectTrigger><SelectValue placeholder="Choose a card" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="c in cardChargeChannels ?? []" :key="c.code" :value="c.code">{{ c.label }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="chargeChannel" class="text-xs text-muted-foreground">{{ chargeComesFrom(chargeChannel) }}</p>
            <InputError :message="chargeForm.errors.channel_code" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Date</Label>
              <Input v-model="chargeForm.charge_date" type="date" @update:model-value="(v) => { const p = monthOf(String(v)); chargeForm.period_from = p.from; chargeForm.period_to = p.to }" />
              <InputError :message="chargeForm.errors.charge_date" />
            </div>
            <div class="space-y-2">
              <div class="flex items-center justify-between gap-2">
                <Label>Charge</Label>
                <div class="flex rounded-md border text-xs" role="group" aria-label="Enter the charge as">
                  <button type="button" class="px-2 py-0.5" :class="chargeBy === 'amount' ? 'bg-muted font-medium' : 'text-muted-foreground'" @click="chargeBy = 'amount'">Rs</button>
                  <button type="button" class="px-2 py-0.5" :class="chargeBy === 'percent' ? 'bg-muted font-medium' : 'text-muted-foreground'" @click="chargeBy = 'percent'">%</button>
                </div>
              </div>
              <Input v-if="chargeBy === 'amount'" v-model.number="chargeForm.amount" type="number" min="0.01" step="0.01" placeholder="From the statement" />
              <Input v-else v-model.number="chargeForm.percent" type="number" min="0.01" max="10" step="0.01" placeholder="e.g. 0.78" />
              <InputError :message="chargeForm.errors.amount || chargeForm.errors.percent" />
            </div>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Sales from</Label>
              <Input v-model="chargeForm.period_from" type="date" />
              <InputError :message="chargeForm.errors.period_from" />
            </div>
            <div class="space-y-2">
              <Label>Sales to</Label>
              <Input v-model="chargeForm.period_to" type="date" />
              <InputError :message="chargeForm.errors.period_to" />
            </div>
          </div>
          <p class="text-xs text-muted-foreground">
            <template v-if="cardChargeSales === null || cardChargeSales === undefined">Card sales loading…</template>
            <template v-else-if="chargeSales <= 0">No card sales in this period.</template>
            <template v-else>
              Card sales {{ formatCurrency(chargeSales) }}<template v-if="chargeBy === 'percent' && chargeForm.percent"> · charge {{ formatCurrency(chargeFromPercent) }}</template><template v-else-if="chargeBy === 'amount' && chargeRate !== null"> · {{ +chargeRate.toFixed(2) }}%</template>
            </template>
          </p>
          <div class="space-y-2">
            <Label>Reference</Label>
            <Input v-model="chargeForm.reference" maxlength="100" placeholder="Statement no." />
            <InputError :message="chargeForm.errors.reference" />
          </div>
          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" @click="showChargeDialog = false">Cancel</Button>
            <Button type="submit" :disabled="chargeForm.processing || !chargeForm.channel_code || !(chargeBy === 'amount' ? chargeForm.amount : chargeForm.percent)">Record charge</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
