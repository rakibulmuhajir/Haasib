<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import DateTimeText from '@/components/DateTimeText.vue'
import type { BreadcrumbItem } from '@/types'
import { User, Wallet, ArrowDownCircle, ArrowUpCircle, ArrowLeft, Fuel } from 'lucide-vue-next'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import MoneyText from '@/components/MoneyText.vue'

interface AmanatTransaction {
  id: string
  transaction_type: 'deposit' | 'withdrawal' | 'fuel_purchase'
  amount: number
  fuel_item_name?: string | null
  fuel_quantity?: number | null
  reference?: string | null
  notes?: string | null
  created_at: string
  transaction_date?: string | null
  payment_account?: { code: string; name: string; subtype: string } | null
  payment_account_id?: string | null
  detail?: MovementDetail
}

interface MovementDetail {
  voided: boolean
  replaced_by_id: string | null
  recorded_by_name: string | null
  source: { kind: 'close' | 'standalone' | 'other'; label: string; href: string | null }
  journal: { id: string; number: string; href: string } | null
  can_change_holder: boolean
  can_edit: boolean
}

interface Holder {
  id: string
  name: string
}

interface CustomerProfile {
  id: string
  customer_id: string
  is_credit_customer: boolean
  is_amanat_holder: boolean
  is_investor: boolean
  relationship?: string | null
  cnic?: string | null
  amanat_balance: number
  allow_amanat_borrowing?: boolean
}

interface Customer {
  id: string
  name: string
  email?: string | null
  phone?: string | null
}

interface PaymentAccount {
  id: string
  code: string
  name: string
  subtype: string
}

interface PaginatedTransactions {
  data: AmanatTransaction[]
}

const props = withDefaults(defineProps<{
  customer: Customer
  profile: CustomerProfile
  canRecordMovement?: boolean
  canUpdateMovement?: boolean
  holders?: Holder[]
  transactions: AmanatTransaction[] | PaginatedTransactions
  paymentAccounts?: PaymentAccount[]
  // This holder's opening balance line; null when the user may not manage opening balances.
  opening?: { kind: 'holds' | 'owes'; amount: number; as_of_date: string | null; locked: boolean } | null
}>(), {
  transactions: () => [],
  paymentAccounts: () => [],
  holders: () => [],
})

const page = usePage()
const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Amanat', href: `/${companySlug.value}/fuel/amanat` },
  { title: props.customer.name, href: `/${companySlug.value}/fuel/amanat/${props.customer.id}` },
])

const openingForm = useForm({
  opening_kind: props.opening?.kind ?? 'holds',
  opening_amount: props.opening?.amount ?? 0,
  opening_date: props.opening?.as_of_date ?? '',
})
const saveOpening = () => openingForm.post(`/${companySlug.value}/fuel/amanat/${props.customer.id}/opening`, { preserveScroll: true })

const movement = useForm({ business_date: '', amount: 0, payment_account_id: '', reference: '' })
const recordMovement = (kind: 'deposit' | 'withdraw') => movement.post(`/${companySlug.value}/fuel/amanat/${props.customer.id}/${kind}`, {
  preserveScroll: true,
  onSuccess: (page) => { if ((page.props as any).flash?.success) movement.reset('amount', 'reference') },
})

// Opening a movement: details, change holder, and (standalone entries) edit.
const selected = ref<AmanatTransaction | null>(null)
const sheetOpen = computed({
  get: () => selected.value !== null,
  set: (open: boolean) => { if (!open) selected.value = null },
})
const holderForm = useForm({ customer_id: '' })
const amendForm = useForm({ business_date: '', amount: 0, payment_account_id: '', reference: '', notes: '' })
const movementUrl = (action: 'holder' | 'amend') =>
  `/${companySlug.value}/fuel/amanat/${props.customer.id}/movements/${selected.value?.id}/${action}`

const openMovement = (row: { _raw: AmanatTransaction }) => {
  const tx = row._raw
  selected.value = tx
  holderForm.reset()
  holderForm.clearErrors()
  amendForm.clearErrors()
  amendForm.business_date = (tx.transaction_date ?? tx.created_at).slice(0, 10)
  amendForm.amount = Number(tx.amount)
  amendForm.payment_account_id = tx.payment_account_id ?? ''
  amendForm.reference = tx.reference ?? ''
  amendForm.notes = tx.notes ?? ''
}
const saveHolder = () => holderForm.post(movementUrl('holder'), {
  preserveScroll: true,
  onSuccess: () => { selected.value = null },
})
const saveAmend = () => amendForm.post(movementUrl('amend'), {
  preserveScroll: true,
  onSuccess: () => { selected.value = null },
})

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const formatDateTime = (date: string) => {
  return formatSharedDateTime(date, { mode: 'datetime', locale: 'en-PK' })
}

const formatDateOnly = (date: string) => {
  return formatSharedDateTime(date, { mode: 'date', locale: 'en-PK' })
}

// Transaction table
const txColumns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'type', label: 'Type', kind: 'status' as const },
  { key: 'details', label: 'Details', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const, align: 'right' as const },
]

const transactionRows = computed(() => {
  if (Array.isArray(props.transactions)) return props.transactions
  return props.transactions?.data ?? []
})

const txTableData = computed(() => {
  return transactionRows.value.map((tx) => {
    let details = tx.reference ?? ''
    if (tx.payment_account) {
      details = `${tx.payment_account.code} - ${tx.payment_account.name}${details ? ` · ${details}` : ''}`
    }
    if (tx.transaction_type === 'fuel_purchase' && tx.fuel_item_name) {
      details = `${tx.fuel_quantity?.toFixed(2) ?? '?'} L ${tx.fuel_item_name}`
    }

    return {
      id: tx.id,
      date: formatDateOnly(tx.transaction_date ?? tx.created_at),
      recordedAt: formatDateTime(tx.created_at),
      type: tx.transaction_type,
      details: details || '-',
      amount: tx.amount,
      _raw: tx,
    }
  })
})

const getTypeBadge = (type: string) => {
  switch (type) {
    case 'deposit':
      return { class: 'bg-status-success/10 text-status-success', icon: ArrowDownCircle, label: 'Deposit' }
    case 'withdrawal':
      return { class: 'bg-status-attention/10 text-status-attention', icon: ArrowUpCircle, label: 'Withdrawal' }
    case 'fuel_purchase':
      return { class: 'bg-status-info/10 text-status-info', icon: Fuel, label: 'Fuel Purchase' }
    default:
      return { class: 'bg-surface-sunken text-text-primary', icon: Wallet, label: type }
  }
}
</script>

<template>
  <Head :title="`Amanat: ${customer.name}`" />

  <PageShell
    :title="customer.name"
    :description="`Phone: ${customer.phone ?? 'N/A'} | CNIC: ${profile.cnic ?? 'N/A'}`"
    :icon="User"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/fuel/amanat`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-surface-sunken md:col-span-2">
        <CardContent v-if="canRecordMovement" class="space-y-2 pt-4">
          <Label>Business date</Label><Input v-model="movement.business_date" type="date" />
          <Label>Amount</Label><Input v-model.number="movement.amount" type="number" min="0.01" step="0.01" />
          <Label>Receive / pay from</Label>
          <Select v-model="movement.payment_account_id">
            <SelectTrigger><SelectValue placeholder="Cash on Hand" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="account in paymentAccounts" :key="account.id" :value="account.id">
                {{ account.code }} - {{ account.name }} ({{ account.subtype === 'bank' ? 'Bank' : 'Cash' }})
              </SelectItem>
            </SelectContent>
          </Select>
          <Label>Reference</Label><Input v-model="movement.reference" />
          <p v-for="(error, field) in movement.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
          <div class="flex gap-2"><Button :disabled="movement.processing" @click="recordMovement('deposit')">Record deposit</Button><Button variant="outline" :disabled="movement.processing" @click="recordMovement('withdraw')">Record withdrawal</Button></div>
        </CardContent>
        <CardHeader class="pb-2">
          <CardDescription>Current Balance</CardDescription>
          <CardTitle class="text-3xl"><MoneyText :amount="profile.amanat_balance" :currency="currencyCode" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-status-success" />
            <span>{{ Number(profile.amanat_balance) < 0 ? 'Borrowed from the company' : 'Available for fuel purchases' }}</span>
          </div>
          <div v-if="canRecordMovement" class="mt-3 flex items-center gap-2">
            <Checkbox
              id="allow-amanat-borrowing"
              :model-value="!!profile.allow_amanat_borrowing"
              @update:model-value="(v) => router.post(`/${companySlug}/fuel/amanat/${customer.id}/borrowing`, { allow_amanat_borrowing: v === true }, { preserveScroll: true })"
            />
            <Label for="allow-amanat-borrowing" class="text-sm font-normal">Allow borrowing — balance may go below zero</Label>
          </div>
        </CardContent>
      </Card>

      <Card v-if="opening" class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Opening balance</CardDescription>
          <CardTitle class="text-lg">
            <MoneyText :amount="opening.amount" :currency="currencyCode" />
            <span class="ml-1 text-sm font-normal text-muted-foreground">{{ opening.kind === 'owes' ? 'owed to us' : 'held with us' }}</span>
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 pt-0">
          <p v-if="opening.locked" class="text-sm text-muted-foreground">Opening balances are locked.</p>
          <template v-else>
            <Select v-model="openingForm.opening_kind">
              <SelectTrigger id="opening-kind"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="holds">Holds amanat with us</SelectItem>
                <SelectItem value="owes">Owes us</SelectItem>
              </SelectContent>
            </Select>
            <Label for="opening-amount">Amount</Label>
            <Input id="opening-amount" v-model.number="openingForm.opening_amount" type="number" min="0" step="1" />
            <Label for="opening-date">As of</Label>
            <!-- One opening date for the whole company: shown, not changed, once it is set. -->
            <Input id="opening-date" v-model="openingForm.opening_date" type="date" :disabled="!!opening.as_of_date" />
            <p v-for="(error, field) in openingForm.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
            <Button :disabled="openingForm.processing" @click="saveOpening">Save opening balance</Button>
          </template>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Customer Type</CardDescription>
          <CardTitle class="text-lg capitalize">{{ profile.relationship ?? 'External' }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0 space-y-2">
          <Badge v-if="profile.is_credit_customer" class="bg-status-info/10 text-status-info hover:bg-status-info/10">
            Credit Customer
          </Badge>
          <Badge v-if="profile.is_investor" class="bg-status-info/10 text-status-info hover:bg-status-info/10 ml-1">
            Investor
          </Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Transaction History</CardTitle>
        <CardDescription>Business-date movements appear in Daily Close automatically.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <LedgerRegister :data="txTableData" :columns="txColumns" @row-click="openMovement">
          <template #empty>
            <EmptyState
              title="No transactions"
              description="Daily Close deposits, withdrawals, and fuel purchases will appear here."
            />
          </template>

          <template #cell-date="{ row }">
            <span :class="{ 'text-muted-foreground': row._raw.detail?.voided }" :title="`recorded ${row.recordedAt}`">{{ row.date }}</span>
          </template>

          <template #cell-details="{ row }">
            <span :class="{ 'text-muted-foreground line-through': row._raw.detail?.voided }">{{ row.details }}</span>
            <span v-if="row._raw.detail?.voided" class="ml-2 text-xs text-muted-foreground">Corrected</span>
          </template>

          <template #cell-type="{ row }">
            <Badge :class="getTypeBadge(row.type).class" class="hover:opacity-100">
              <component :is="getTypeBadge(row.type).icon" class="mr-1 h-3 w-3" />
              {{ getTypeBadge(row.type).label }}
            </Badge>
          </template>

          <template #cell-amount="{ row }">
            <span
              class="font-medium"
              :class="{
                'text-muted-foreground line-through': row._raw.detail?.voided,
                'text-status-success': !row._raw.detail?.voided && row.type === 'deposit',
                'text-status-critical': !row._raw.detail?.voided && (row.type === 'withdrawal' || row.type === 'fuel_purchase'),
              }"
            >
              <MoneyText
                :amount="row.amount"
                :currency="currencyCode"
                :direction="row.type === 'deposit' ? 'inflow' : 'outflow'"
              />
            </span>
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>

    <Sheet v-model:open="sheetOpen">
      <SheetContent class="w-full overflow-y-auto sm:max-w-md">
        <template v-if="selected">
          <SheetHeader>
            <SheetTitle>{{ getTypeBadge(selected.transaction_type).label }}</SheetTitle>
            <SheetDescription v-if="selected.detail?.voided">Corrected. A new entry replaced this one.</SheetDescription>
          </SheetHeader>

          <dl class="space-y-2 px-4 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Date</dt><dd><DateTimeText :value="selected.transaction_date ?? selected.created_at" mode="date" locale="en-PK" /></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Amount</dt><dd><MoneyText :amount="selected.amount" :currency="currencyCode" /></dd></div>
            <div v-if="selected.payment_account" class="flex justify-between gap-4"><dt class="text-muted-foreground">Account</dt><dd>{{ selected.payment_account.code }} - {{ selected.payment_account.name }}</dd></div>
            <div v-if="selected.reference" class="flex justify-between gap-4"><dt class="text-muted-foreground">Reference</dt><dd>{{ selected.reference }}</dd></div>
            <div v-if="selected.notes" class="flex justify-between gap-4"><dt class="text-muted-foreground">Notes</dt><dd class="text-right">{{ selected.notes }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Recorded by</dt><dd>{{ selected.detail?.recorded_by_name ?? '-' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Recorded</dt><dd><DateTimeText :value="selected.created_at" locale="en-PK" /></dd></div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted-foreground">Source</dt>
              <dd>
                <Link v-if="selected.detail?.source.href" :href="selected.detail.source.href" class="underline underline-offset-2">{{ selected.detail.source.label }}</Link>
                <span v-else>{{ selected.detail?.source.label }}</span>
              </dd>
            </div>
            <div v-if="selected.detail?.journal" class="flex justify-between gap-4">
              <dt class="text-muted-foreground">Journal</dt>
              <dd><Link :href="selected.detail.journal.href" class="underline underline-offset-2">{{ selected.detail.journal.number }}</Link></dd>
            </div>
          </dl>

          <div v-if="canUpdateMovement && selected.detail?.can_change_holder" class="space-y-2 border-t px-4 pt-4">
            <Label>Change holder</Label>
            <Select v-model="holderForm.customer_id">
              <SelectTrigger><SelectValue placeholder="Pick a holder" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="holder in holders" :key="holder.id" :value="holder.id">{{ holder.name }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-for="(error, field) in holderForm.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
            <Button :disabled="holderForm.processing || !holderForm.customer_id" @click="saveHolder">Move</Button>
          </div>

          <div v-if="canUpdateMovement && selected.detail?.can_edit" class="space-y-2 border-t px-4 pt-4">
            <Label>Date</Label><Input v-model="amendForm.business_date" type="date" />
            <Label>Amount</Label><Input v-model.number="amendForm.amount" type="number" min="0.01" step="0.01" />
            <Label>Account</Label>
            <Select v-model="amendForm.payment_account_id">
              <SelectTrigger><SelectValue placeholder="Cash on Hand" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="account in paymentAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Label>Reference</Label><Input v-model="amendForm.reference" />
            <Label>Notes</Label><Input v-model="amendForm.notes" />
            <p v-for="(error, field) in amendForm.errors" :key="field" class="text-sm text-destructive">{{ error }}</p>
            <Button :disabled="amendForm.processing" @click="saveAmend">Save correction</Button>
          </div>

          <div v-else-if="canUpdateMovement && selected.detail?.source.kind === 'close' && !selected.detail?.voided" class="border-t px-4 pt-4 text-sm">
            <Link v-if="selected.detail.source.href" :href="selected.detail.source.href" class="underline underline-offset-2">Correct in Daily Close</Link>
          </div>
        </template>
      </SheetContent>
    </Sheet>

  </PageShell>
</template>
