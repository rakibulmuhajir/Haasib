<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
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
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { User, Wallet, ArrowDownCircle, ArrowUpCircle, ArrowLeft, Fuel } from 'lucide-vue-next'

interface AmanatTransaction {
  id: string
  transaction_type: 'deposit' | 'withdrawal' | 'fuel_purchase'
  amount: number
  fuel_item_name?: string | null
  fuel_quantity?: number | null
  reference?: string | null
  notes?: string | null
  created_at: string
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
}

interface Customer {
  id: string
  name: string
  email?: string | null
  phone?: string | null
}

const props = defineProps<{
  customer: Customer
  profile: CustomerProfile
  transactions: AmanatTransaction[]
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
  { title: 'Amanat', href: `/${companySlug.value}/fuel/amanat` },
  { title: props.customer.name, href: `/${companySlug.value}/fuel/amanat/${props.customer.id}` },
])

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Deposit dialog
const depositDialogOpen = ref(false)
const depositForm = useForm<{
  amount: number | null
  reference: string
  notes: string
}>({
  amount: null,
  reference: '',
  notes: '',
})

const openDeposit = () => {
  depositForm.reset()
  depositForm.clearErrors()
  depositDialogOpen.value = true
}

const submitDeposit = () => {
  const slug = companySlug.value
  if (!slug) return

  depositForm.post(`/${slug}/fuel/amanat/${props.customer.id}/deposit`, {
    preserveScroll: true,
    onSuccess: () => {
      depositDialogOpen.value = false
      depositForm.reset()
    },
  })
}

// Withdrawal dialog
const withdrawDialogOpen = ref(false)
const withdrawForm = useForm<{
  amount: number | null
  reference: string
  notes: string
}>({
  amount: null,
  reference: '',
  notes: '',
})

const openWithdraw = () => {
  withdrawForm.reset()
  withdrawForm.clearErrors()
  withdrawDialogOpen.value = true
}

const submitWithdraw = () => {
  const slug = companySlug.value
  if (!slug) return

  withdrawForm.post(`/${slug}/fuel/amanat/${props.customer.id}/withdraw`, {
    preserveScroll: true,
    onSuccess: () => {
      withdrawDialogOpen.value = false
      withdrawForm.reset()
    },
  })
}

// Transaction table
const txColumns = [
  { key: 'date', label: 'Date' },
  { key: 'type', label: 'Type' },
  { key: 'details', label: 'Details' },
  { key: 'amount', label: 'Amount', align: 'right' as const },
]

const txTableData = computed(() => {
  return props.transactions.map((tx) => {
    let details = tx.reference ?? ''
    if (tx.transaction_type === 'fuel_purchase' && tx.fuel_item_name) {
      details = `${tx.fuel_quantity?.toFixed(2) ?? '?'} L ${tx.fuel_item_name}`
    }

    return {
      id: tx.id,
      date: formatDateTime(tx.created_at),
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
      return { class: 'bg-emerald-100 text-emerald-800', icon: ArrowDownCircle, label: 'Deposit' }
    case 'withdrawal':
      return { class: 'bg-amber-100 text-amber-800', icon: ArrowUpCircle, label: 'Withdrawal' }
    case 'fuel_purchase':
      return { class: 'bg-sky-100 text-sky-800', icon: Fuel, label: 'Fuel Purchase' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Wallet, label: type }
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
      <Button
        v-if="profile.amanat_balance > 0"
        variant="outline"
        class="border-amber-300 text-amber-700 hover:bg-amber-50"
        @click="openWithdraw"
      >
        <ArrowUpCircle class="mr-2 h-4 w-4" />
        Withdraw
      </Button>
      <Button class="bg-emerald-600 hover:bg-emerald-700" @click="openDeposit">
        <ArrowDownCircle class="mr-2 h-4 w-4" />
        Deposit
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10 md:col-span-2">
        <CardHeader class="pb-2">
          <CardDescription>Current Balance</CardDescription>
          <CardTitle class="text-3xl">{{ formatCurrency(profile.amanat_balance) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>Available for fuel purchases</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Customer Type</CardDescription>
          <CardTitle class="text-lg capitalize">{{ profile.relationship ?? 'External' }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0 space-y-2">
          <Badge v-if="profile.is_credit_customer" class="bg-purple-100 text-purple-800 hover:bg-purple-100">
            Credit Customer
          </Badge>
          <Badge v-if="profile.is_investor" class="bg-sky-100 text-sky-800 hover:bg-sky-100 ml-1">
            Investor
          </Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Transaction History</CardTitle>
        <CardDescription>All deposits, withdrawals, and fuel purchases.</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="txTableData" :columns="txColumns">
          <template #empty>
            <EmptyState
              title="No transactions"
              description="Deposit or fuel purchase transactions will appear here."
            />
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
                'text-emerald-600': row.type === 'deposit',
                'text-red-600': row.type === 'withdrawal' || row.type === 'fuel_purchase',
              }"
            >
              {{ row.type === 'deposit' ? '+' : '-' }}{{ formatCurrency(Math.abs(row.amount)) }}
            </span>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Deposit Dialog -->
    <Dialog :open="depositDialogOpen" @update:open="(v) => (depositDialogOpen = v)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <ArrowDownCircle class="h-5 w-5 text-emerald-600" />
            Deposit Funds
          </DialogTitle>
          <DialogDescription>
            Add funds to {{ customer.name }}'s amanat balance.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitDeposit">
          <div class="space-y-2">
            <Label for="deposit_amount">Amount (PKR) *</Label>
            <Input
              id="deposit_amount"
              v-model.number="depositForm.amount"
              type="number"
              min="100"
              step="100"
              placeholder="5000"
              :class="{ 'border-destructive': depositForm.errors.amount }"
            />
            <p v-if="depositForm.errors.amount" class="text-sm text-destructive">
              {{ depositForm.errors.amount }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="deposit_reference">Reference</Label>
            <Input
              id="deposit_reference"
              v-model="depositForm.reference"
              placeholder="Receipt number, etc."
            />
          </div>

          <div class="space-y-2">
            <Label for="deposit_notes">Notes</Label>
            <Textarea
              id="deposit_notes"
              v-model="depositForm.notes"
              placeholder="Optional notes..."
              rows="2"
            />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="depositForm.processing" @click="depositDialogOpen = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-emerald-600 hover:bg-emerald-700" :disabled="depositForm.processing">
              <span
                v-if="depositForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Deposit
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Withdraw Dialog -->
    <Dialog :open="withdrawDialogOpen" @update:open="(v) => (withdrawDialogOpen = v)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <ArrowUpCircle class="h-5 w-5 text-amber-600" />
            Withdraw Funds
          </DialogTitle>
          <DialogDescription>
            Current balance: {{ formatCurrency(profile.amanat_balance) }}
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitWithdraw">
          <div class="space-y-2">
            <Label for="withdraw_amount">Amount (PKR) *</Label>
            <Input
              id="withdraw_amount"
              v-model.number="withdrawForm.amount"
              type="number"
              min="1"
              :max="profile.amanat_balance"
              step="1"
              :class="{ 'border-destructive': withdrawForm.errors.amount }"
            />
            <p v-if="withdrawForm.errors.amount" class="text-sm text-destructive">
              {{ withdrawForm.errors.amount }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="withdraw_reference">Reference</Label>
            <Input
              id="withdraw_reference"
              v-model="withdrawForm.reference"
              placeholder="Receipt number, etc."
            />
          </div>

          <div class="space-y-2">
            <Label for="withdraw_notes">Notes</Label>
            <Textarea
              id="withdraw_notes"
              v-model="withdrawForm.notes"
              placeholder="Optional notes..."
              rows="2"
            />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="withdrawForm.processing" @click="withdrawDialogOpen = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-amber-600 hover:bg-amber-700" :disabled="withdrawForm.processing">
              <span
                v-if="withdrawForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Withdraw
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
