<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
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

const props = defineProps<{
  pendingSales: VendorCardSale[]
  clearingAccounts: ClearingAccountSummary[]
  summary: VendorCardSummary
  todaySettlements: VendorCardSale[]
  bankAccounts: BankAccount[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

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
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currencyCode.value,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
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
  settlement_date: new Date().toISOString().split('T')[0],
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
  settlement_date: new Date().toISOString().split('T')[0],
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
  settlementForm.reference = `Vendor Card Settlement ${new Date().toISOString().slice(0, 10)}`
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
  clearingSettlementForm.reference = `${account.channel_label} Settlement ${new Date().toISOString().slice(0, 10)}`
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
      return { class: 'bg-amber-100 text-amber-800', icon: Clock, label: 'Pending' }
    case 'settled':
      return { class: 'bg-emerald-100 text-emerald-800', icon: CheckCircle, label: 'Settled' }
    case 'overdue':
      return { class: 'bg-red-100 text-red-800', icon: AlertTriangle, label: 'Overdue' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Clock, label: status }
  }
}

const pendingColumns = [
  { key: 'select', label: '', sortable: false, width: '50px' },
  { key: 'customer', label: 'Customer' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'date', label: 'Date' },
  { key: 'outstanding', label: 'Outstanding', align: 'right' as const },
  { key: 'status', label: 'Status' },
]

const todayColumns = [
  { key: 'customer', label: 'Customer' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'date', label: 'Date' },
  { key: 'amount', label: 'Settled', align: 'right' as const },
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
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-purple-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Pending Settlements</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_pending) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <AlertTriangle class="h-4 w-4 text-amber-600" />
            <span>{{ props.summary.count_pending }} transaction(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Settled Today</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_settled_today) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-emerald-100 text-emerald-800 hover:bg-emerald-100">
            <CheckCircle class="mr-1 h-3 w-3" />
            Completed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Outstanding</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ formatCurrency(props.summary.total_outstanding) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-amber-200 text-amber-700">
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
              class="bg-emerald-600 hover:bg-emerald-700"
              @click="openSettlementDialog"
            >
              <Banknote class="mr-2 h-4 w-4" />
              Settle Selected ({{ formatCurrency(selectedTotal) }})
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="pendingTableData" :columns="pendingColumns">
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
              class="rounded border-border"
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
              class="rounded border-border"
            />
          </template>
        </DataTable>
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
            <div class="text-right font-semibold text-amber-600">{{ formatCurrency(account.balance) }}</div>
            <Button size="sm" @click="openClearingDialog(account)">Settle</Button>
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
        <DataTable :data="todayTableData" :columns="todayColumns" />
      </CardContent>
    </Card>

    <!-- Settlement Dialog -->
    <Dialog :open="showSettlementDialog" @update:open="(v: boolean) => showSettlementDialog = v">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <CreditCard class="h-5 w-5 text-blue-600" />
            Vendor Card Settlement
          </DialogTitle>
          <DialogDescription>
            Settle {{ selectedSales.size }} transaction(s) totaling {{ formatCurrency(selectedTotal) }}
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitSettlement">
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
            <p v-if="settlementForm.errors.amount_received" class="text-sm text-destructive">
              {{ settlementForm.errors.amount_received[0] }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>Settlement Date</Label>
            <Input v-model="settlementForm.settlement_date" type="date" />
          </div>

          <div class="space-y-2">
            <Label>Reference</Label>
            <Input
              v-model="settlementForm.reference"
              placeholder="Settlement reference number"
            />
          </div>

          <div class="space-y-2">
            <Label>Notes</Label>
            <Input
              v-model="settlementForm.notes"
              placeholder="Optional notes..."
            />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="settlementForm.processing" @click="showSettlementDialog = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-blue-600 hover:bg-blue-700" :disabled="settlementForm.processing">
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
            {{ selectedClearingAccount?.channel_label }} outstanding: {{ formatCurrency(selectedClearingAccount?.balance || 0) }}
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitClearingSettlement">
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
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-2">
              <Label>Amount Received *</Label>
              <Input v-model.number="clearingSettlementForm.amount_received" type="number" min="0.01" step="0.01" />
            </div>
            <div class="space-y-2">
              <Label>Fees</Label>
              <Input v-model.number="clearingSettlementForm.fees" type="number" min="0" step="0.01" />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Settlement Date</Label>
            <Input v-model="clearingSettlementForm.settlement_date" type="date" />
          </div>

          <div class="space-y-2">
            <Label>Reference</Label>
            <Input v-model="clearingSettlementForm.reference" />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="showClearingDialog = false">Cancel</Button>
            <Button type="submit" :disabled="clearingSettlementForm.processing">Process Settlement</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
