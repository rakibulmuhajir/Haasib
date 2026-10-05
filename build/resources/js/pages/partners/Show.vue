<script setup lang="ts">
import { localToday } from '@/composables/useEntryDate'
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
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
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { UsersRound, Pencil, ArrowLeft, TrendingUp, TrendingDown, Wallet } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import MoneyText from '@/components/MoneyText.vue'

interface Partner {
  id: string
  name: string
  phone: string | null
  email: string | null
  cnic: string | null
  address: string | null
  profit_share_percentage: number
  drawing_limit_period: string
  drawing_limit_amount: number | null
  total_invested: number
  total_withdrawn: number
  net_capital: number
  profit_shares: number
  remaining_drawing_limit: number | null
  current_period_withdrawn: number
  is_active: boolean
}

interface CashAccount {
  id: string
  code: string
  name: string
  subtype: string
}

interface Transaction {
  id: string
  transaction_date: string
  transaction_type: string
  amount: number
  description: string | null
  reference: string | null
  payment_method: string
  balance: number
}

const props = defineProps<{
  partner: Partner
  transactions: Transaction[]
  cashAccounts: CashAccount[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Partners', href: `/${companySlug.value}/partners` },
  { title: props.partner.name, href: `/${companySlug.value}/partners/${props.partner.id}` },
])

const currency = computed(() => currencySymbol(props.currency))

const formatDate = (dateStr: string) => {
  return formatDateTime(dateStr, { mode: 'date' })
}

// Investment dialog
const investDialogOpen = ref(false)
const investForm = useForm({
  amount: null as number | null,
  transaction_date: localToday(),
  description: '',
  reference: '',
  account_id: '',
})

const defaultAccount = () => props.cashAccounts.find((a) => a.subtype === 'cash')?.id ?? props.cashAccounts[0]?.id ?? ''

const openInvestDialog = () => {
  investForm.reset()
  investForm.account_id = defaultAccount()
  investForm.transaction_date = localToday()
  investDialogOpen.value = true
}

const submitInvestment = () => {
  investForm.post(`/${companySlug.value}/partners/${props.partner.id}/invest`, {
    preserveScroll: true,
    onSuccess: () => {
      investDialogOpen.value = false
    },
  })
}

// Withdrawal dialog
const withdrawDialogOpen = ref(false)
const withdrawForm = useForm({
  amount: null as number | null,
  transaction_date: localToday(),
  description: '',
  reference: '',
  account_id: '',
})

const openWithdrawDialog = () => {
  withdrawForm.reset()
  withdrawForm.account_id = defaultAccount()
  withdrawForm.transaction_date = localToday()
  withdrawDialogOpen.value = true
}

const submitWithdrawal = () => {
  withdrawForm.post(`/${companySlug.value}/partners/${props.partner.id}/withdraw`, {
    preserveScroll: true,
    onSuccess: () => {
      withdrawDialogOpen.value = false
    },
  })
}

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'type', label: 'Type', kind: 'text' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
  { key: 'balance', label: 'Balance', kind: 'amount' as const },
]

const tableData = computed(() => {
  return props.transactions.map((t) => ({
    id: t.id,
    date: formatDate(t.transaction_date),
    type: t.transaction_type,
    description: t.description || '-',
    amount: t.amount,
    balance: t.balance,
    _raw: t,
  }))
})

const typeLabel = (type: string) =>
  ({ investment: 'Investment', withdrawal: 'Withdrawal', profit_share: 'Profit share', adjustment: 'Adjustment' })[type] ?? type

const goBack = () => {
  router.get(`/${companySlug.value}/partners`)
}
</script>

<template>
  <Head :title="partner.name" />

  <PageShell
    :title="partner.name"
    :description="partner.phone || partner.email || 'Partner details and transaction history'"
    :icon="UsersRound"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button variant="outline" @click="router.get(`/${companySlug}/partners/${partner.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
    </template>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Net Capital</CardDescription>
<!-- Ink, like the same figure in the register. See partners/Index.vue. -->
          <CardTitle class="text-2xl">
            <MoneyText :amount="partner.net_capital" :currency="props.currency" />
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4" />
            <span>Capital less drawings</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl text-status-success"><MoneyText :amount="partner.total_invested" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-status-success" />
            <span>All time</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Profit Shares</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="partner.profit_shares" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4" />
            <span>Added monthly</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Withdrawn</CardDescription>
          <CardTitle class="text-2xl text-status-attention"><MoneyText :amount="partner.total_withdrawn" :currency="props.currency" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingDown class="h-4 w-4 text-status-attention" />
            <span>All time</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Drawing Limit</CardDescription>
          <CardTitle class="text-2xl">
            <template v-if="partner.drawing_limit_period === 'none'">No Limit</template>
            <template v-else-if="(partner.remaining_drawing_limit ?? 0) < 0"><span class="text-status-attention">Over by <MoneyText :amount="-(partner.remaining_drawing_limit ?? 0)" :currency="props.currency" /></span></template>
            <template v-else><MoneyText :amount="partner.remaining_drawing_limit ?? 0" :currency="props.currency" /></template>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div v-if="partner.drawing_limit_period !== 'none'" class="text-sm text-text-secondary">
            of <MoneyText :amount="partner.drawing_limit_amount ?? 0" :currency="props.currency" /> {{ partner.drawing_limit_period }}
          </div>
          <div v-else class="text-sm text-text-secondary">Unlimited withdrawals</div>
        </CardContent>
      </Card>
    </div>

    <!-- Partner Details -->
    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle class="text-base">Partner Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <div class="text-sm text-muted-foreground">Profit Share</div>
            <div class="font-medium">{{ partner.profit_share_percentage }}%</div>
          </div>
          <div v-if="partner.phone">
            <div class="text-sm text-muted-foreground">Phone</div>
            <div class="font-medium">{{ partner.phone }}</div>
          </div>
          <div v-if="partner.email">
            <div class="text-sm text-muted-foreground">Email</div>
            <div class="font-medium">{{ partner.email }}</div>
          </div>
          <div v-if="partner.address">
            <div class="text-sm text-muted-foreground">Address</div>
            <div class="font-medium">{{ partner.address }}</div>
          </div>
          <div>
            <div class="text-sm text-muted-foreground">Status</div>
            <Badge
              :class="partner.is_active ? 'bg-status-success text-status-success-contrast' : 'bg-surface-sunken text-text-primary'"
            >
              {{ partner.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </div>

          <div class="pt-4 flex flex-col gap-2">
            <Button class="w-full" variant="outline" @click="openInvestDialog">
              <TrendingUp class="mr-2 h-4 w-4 text-status-success" />
              Record Investment
            </Button>
            <Button class="w-full" variant="outline" @click="openWithdrawDialog">
              <TrendingDown class="mr-2 h-4 w-4 text-status-attention" />
              Record Withdrawal
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Transactions -->
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle class="text-base">Transaction History</CardTitle>
          <CardDescription>Money in, profit shares and money out.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister :data="tableData" :columns="columns">
            <template #empty>
              <div class="py-8 text-center text-muted-foreground">
                No transactions yet
              </div>
            </template>

            <template #cell-type="{ row }">
              <Badge
                :class="row._raw.transaction_type === 'withdrawal' ? 'bg-status-attention/15 text-status-attention' : 'bg-status-success/15 text-status-success'"
              >
                {{ typeLabel(row._raw.transaction_type) }}
              </Badge>
            </template>

            <template #cell-amount="{ row }">
              <span :class="row._raw.transaction_type === 'withdrawal' || row._raw.amount < 0 ? 'text-status-attention' : 'text-status-success'" class="font-medium">
                <MoneyText
                  :amount="Math.abs(row._raw.amount)"
                  :currency="props.currency"
                  :direction="row._raw.transaction_type === 'withdrawal' || row._raw.amount < 0 ? 'outflow' : 'inflow'"
                />
              </span>
            </template>

            <template #cell-balance="{ row }">
              <span class="font-medium"><MoneyText :amount="row._raw.balance" :currency="props.currency" /></span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>

    <!-- Investment Dialog -->
    <Dialog v-model:open="investDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record Investment</DialogTitle>
          <DialogDescription>Add a capital contribution from {{ partner.name }}.</DialogDescription>
        </DialogHeader>

        <form novalidate @submit.prevent="submitInvestment" class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="invest_amount">Amount <span class="text-destructive">*</span></Label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
                <Input
                  id="invest_amount"
                  v-model.number="investForm.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="pl-14"
                  :class="{ 'border-destructive': investForm.errors.amount }"
                />
              </div>
              <p v-if="investForm.errors.amount" class="text-sm text-destructive">{{ investForm.errors.amount }}</p>
            </div>

            <div class="space-y-2">
              <Label for="invest_date">Date <span class="text-destructive">*</span></Label>
              <Input
                id="invest_date"
                v-model="investForm.transaction_date"
                type="date"
                :class="{ 'border-destructive': investForm.errors.transaction_date }"
              />
              <p v-if="investForm.errors.transaction_date" class="text-sm text-destructive">{{ investForm.errors.transaction_date }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="invest_account">Paid into <span class="text-destructive">*</span></Label>
            <Select v-model="investForm.account_id">
              <SelectTrigger id="invest_account" :class="{ 'border-destructive': investForm.errors.account_id }">
                <SelectValue placeholder="Cash or bank account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="investForm.errors.account_id" class="text-sm text-destructive">{{ investForm.errors.account_id }}</p>
          </div>

          <div class="space-y-2">
            <Label for="invest_description">Description</Label>
            <Textarea
              id="invest_description"
              v-model="investForm.description"
              placeholder="Optional notes"
              rows="2"
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="investDialogOpen = false" :disabled="investForm.processing">
              Cancel
            </Button>
            <Button type="submit" :disabled="investForm.processing">
              <span v-if="investForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
              Record Investment
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Withdrawal Dialog -->
    <Dialog v-model:open="withdrawDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record Withdrawal</DialogTitle>
          <DialogDescription>Record a partner drawing from {{ partner.name }}.</DialogDescription>
        </DialogHeader>

        <form novalidate @submit.prevent="submitWithdrawal" class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="withdraw_amount">Amount <span class="text-destructive">*</span></Label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
                <Input
                  id="withdraw_amount"
                  v-model.number="withdrawForm.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="pl-14"
                  :class="{ 'border-destructive': withdrawForm.errors.amount }"
                />
              </div>
              <p v-if="withdrawForm.errors.amount" class="text-sm text-destructive">{{ withdrawForm.errors.amount }}</p>
            </div>

            <div class="space-y-2">
              <Label for="withdraw_date">Date <span class="text-destructive">*</span></Label>
              <Input
                id="withdraw_date"
                v-model="withdrawForm.transaction_date"
                type="date"
                :class="{ 'border-destructive': withdrawForm.errors.transaction_date }"
              />
              <p v-if="withdrawForm.errors.transaction_date" class="text-sm text-destructive">{{ withdrawForm.errors.transaction_date }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="withdraw_account">Paid from <span class="text-destructive">*</span></Label>
            <Select v-model="withdrawForm.account_id">
              <SelectTrigger id="withdraw_account" :class="{ 'border-destructive': withdrawForm.errors.account_id }">
                <SelectValue placeholder="Cash or bank account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="withdrawForm.errors.account_id" class="text-sm text-destructive">{{ withdrawForm.errors.account_id }}</p>
          </div>

          <div class="space-y-2">
            <Label for="withdraw_description">Description</Label>
            <Textarea
              id="withdraw_description"
              v-model="withdrawForm.description"
              placeholder="Optional notes"
              rows="2"
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="withdrawDialogOpen = false" :disabled="withdrawForm.processing">
              Cancel
            </Button>
            <Button type="submit" :disabled="withdrawForm.processing">
              <span v-if="withdrawForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
              Record Withdrawal
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
