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
import { Progress } from '@/components/ui/progress'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { User, Plus, Wallet, TrendingUp, Banknote, Package, ArrowLeft } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import MoneyText from '@/components/MoneyText.vue'

interface InvestorLot {
  id: string
  deposit_date: string
  investment_amount: number
  entitlement_rate: number
  commission_rate: number
  units_entitled: number
  units_remaining: number
  commission_earned: number
  status: 'active' | 'depleted' | 'withdrawn'
}

interface Investor {
  id: string
  name: string
  phone?: string | null
  cnic?: string | null
  total_invested: number
  total_commission_earned: number
  total_commission_paid: number
  outstanding_commission: number
  is_active: boolean
}

const props = defineProps<{
  investor: Investor
  lots: InvestorLot[]
  currentRate?: {
    purchase_rate: number
    sale_rate: number
    margin: number
  } | null
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
  { title: 'Investors', href: `/${companySlug.value}/fuel/investors` },
  { title: props.investor.name, href: `/${companySlug.value}/fuel/investors/${props.investor.id}` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')
const currency = computed(() => currencySymbol(currencyCode.value))

const formatNumber = (value: number, decimals = 2) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value)
}

const formatDate = (date: string) => {
  return formatDateTime(date, { mode: 'date' })
}

// Lot dialog
const lotDialogOpen = ref(false)
const lotForm = useForm<{
  investment_amount: number | null
  deposit_date: string
}>({
  investment_amount: null,
  deposit_date: new Date().toISOString().split('T')[0],
})

const openAddLot = () => {
  lotForm.reset()
  lotForm.clearErrors()
  lotForm.deposit_date = new Date().toISOString().split('T')[0]
  lotDialogOpen.value = true
}

const submitLot = () => {
  const slug = companySlug.value
  if (!slug) return

  lotForm.post(`/${slug}/fuel/investors/${props.investor.id}/lots`, {
    preserveScroll: true,
    onSuccess: () => {
      lotDialogOpen.value = false
      lotForm.reset()
    },
  })
}

// Commission payment dialog
const commissionDialogOpen = ref(false)
const commissionForm = useForm<{
  amount: number | null
  payment_date: string
}>({
  amount: null,
  payment_date: new Date().toISOString().split('T')[0],
})

const openPayCommission = () => {
  commissionForm.reset()
  commissionForm.clearErrors()
  commissionForm.amount = props.investor.outstanding_commission
  commissionForm.payment_date = new Date().toISOString().split('T')[0]
  commissionDialogOpen.value = true
}

const submitCommission = () => {
  const slug = companySlug.value
  if (!slug) return

  commissionForm.post(`/${slug}/fuel/investors/${props.investor.id}/pay-commission`, {
    preserveScroll: true,
    onSuccess: () => {
      commissionDialogOpen.value = false
      commissionForm.reset()
    },
  })
}

// Lots table
const lotColumns = [
  { key: 'date', label: 'Date' },
  { key: 'amount', label: 'Amount', align: 'right' as const },
  { key: 'rate', label: 'Rate', align: 'right' as const },
  { key: 'units', label: 'Units', align: 'right' as const },
  { key: 'remaining', label: 'Remaining', align: 'right' as const },
  { key: 'progress', label: 'Progress' },
  { key: 'status', label: 'Status' },
]

const lotTableData = computed(() => {
  return props.lots.map((lot) => {
    const consumed = lot.units_entitled - lot.units_remaining
    const progress = lot.units_entitled > 0 ? (consumed / lot.units_entitled) * 100 : 0

    return {
      id: lot.id,
      date: formatDate(lot.deposit_date),
      amount: lot.investment_amount,
      rate: `${formatNumber(lot.entitlement_rate)} + ${formatNumber(lot.commission_rate)}`,
      units: formatNumber(lot.units_entitled),
      remaining: formatNumber(lot.units_remaining),
      progress: progress,
      status: lot.status,
      _raw: lot,
    }
  })
})

const getStatusBadgeClass = (status: string) => {
  switch (status) {
    case 'active':
      return 'bg-status-success text-status-success-contrast hover:bg-status-success'
    case 'depleted':
      return 'bg-surface-sunken text-text-primary hover:bg-surface-sunken'
    case 'withdrawn':
      return 'bg-status-attention/10 text-status-attention hover:bg-status-attention/10'
    default:
      return 'bg-surface-sunken text-text-primary'
  }
}
</script>

<template>
  <Head :title="`Investor: ${investor.name}`" />

  <PageShell
    :title="investor.name"
    :description="`Phone: ${investor.phone ?? 'N/A'} | CNIC: ${investor.cnic ?? 'N/A'}`"
    :icon="User"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/fuel/investors`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button
        v-if="investor.outstanding_commission > 0"
        variant="default"
        class="bg-status-attention hover:bg-status-attention"
        @click="openPayCommission"
      >
        <Banknote class="mr-2 h-4 w-4" />
        Pay Commission
      </Button>
      <Button @click="openAddLot">
        <Plus class="mr-2 h-4 w-4" />
        Add Investment
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-surface-sunken">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="investor.total_invested" :currency="currencyCode" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-status-success" />
            <span>{{ lots.length }} lot(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Earned</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="investor.total_commission_earned" :currency="currencyCode" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-status-info/10 text-status-info hover:bg-status-info/10">
            <TrendingUp class="mr-1 h-3 w-3" />
            Lifetime
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Paid</CardDescription>
          <CardTitle class="text-2xl"><MoneyText :amount="investor.total_commission_paid" :currency="currencyCode" /></CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="secondary" class="bg-surface-sunken text-text-primary hover:bg-surface-sunken">
            <Banknote class="mr-1 h-3 w-3" />
            Disbursed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Outstanding</CardDescription>
          <CardTitle class="text-2xl" :class="investor.outstanding_commission > 0 ? 'text-status-attention' : ''">
            <MoneyText :amount="investor.outstanding_commission" :currency="currencyCode" />
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge
            v-if="investor.outstanding_commission > 0"
            variant="outline"
            class="border-status-attention/30 text-status-attention"
          >
            Pending Payment
          </Badge>
          <Badge v-else variant="secondary" class="bg-status-success/10 text-status-success hover:bg-status-success/10">
            All Paid
          </Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex items-center justify-between">
          <div>
            <CardTitle class="text-base">Investment Lots</CardTitle>
            <CardDescription>
              Each lot locks the entitlement rate at deposit time. Commission is calculated as fuel is consumed.
            </CardDescription>
          </div>
          <Button size="sm" @click="openAddLot">
            <Plus class="mr-2 h-4 w-4" />
            Add Lot
          </Button>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="lotTableData" :columns="lotColumns">
          <template #empty>
            <EmptyState
              title="No investment lots"
              description="Add the first investment lot for this investor."
            >
              <template #actions>
                <Button @click="openAddLot">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Investment
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-amount="{ row }">
            <MoneyText :amount="row.amount" :currency="currencyCode" />
          </template>

          <template #cell-progress="{ row }">
            <div class="flex items-center gap-2">
              <Progress :model-value="row.progress" class="h-2 w-20" />
              <span class="text-xs text-text-secondary">{{ Math.round(row.progress) }}%</span>
            </div>
          </template>

          <template #cell-status="{ row }">
            <Badge :class="getStatusBadgeClass(row.status)">
              {{ row.status.charAt(0).toUpperCase() + row.status.slice(1) }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Add Lot Dialog -->
    <Dialog :open="lotDialogOpen" @update:open="(v) => (lotDialogOpen = v)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Package class="h-5 w-5 text-status-success" />
            Add Investment Lot
          </DialogTitle>
          <DialogDescription>
            <span v-if="currentRate">
              Current rate: {{ formatNumber(currentRate.purchase_rate) }} {{ currency }}/liter
              (+ {{ formatNumber(currentRate.margin) }} commission)
            </span>
            <span v-else>Rate will be locked at deposit time.</span>
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitLot">
          <div class="space-y-2">
            <Label for="investment_amount">Investment Amount ({{ currency }}) *</Label>
            <Input
              id="investment_amount"
              v-model.number="lotForm.investment_amount"
              type="number"
              min="1000"
              step="1000"
              placeholder="100000"
              :class="{ 'border-destructive': lotForm.errors.investment_amount }"
            />
            <p v-if="lotForm.errors.investment_amount" class="text-sm text-destructive">
              {{ lotForm.errors.investment_amount }}
            </p>
            <p v-if="lotForm.investment_amount && currentRate" class="text-sm text-text-secondary">
              = {{ formatNumber(lotForm.investment_amount / currentRate.purchase_rate) }} liters entitled
            </p>
          </div>

          <div class="space-y-2">
            <Label for="deposit_date">Deposit Date</Label>
            <Input
              id="deposit_date"
              v-model="lotForm.deposit_date"
              type="date"
              :class="{ 'border-destructive': lotForm.errors.deposit_date }"
            />
            <p v-if="lotForm.errors.deposit_date" class="text-sm text-destructive">
              {{ lotForm.errors.deposit_date }}
            </p>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="lotForm.processing" @click="lotDialogOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="lotForm.processing">
              <span
                v-if="lotForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Add Lot
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Pay Commission Dialog -->
    <Dialog :open="commissionDialogOpen" @update:open="(v) => (commissionDialogOpen = v)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Banknote class="h-5 w-5 text-status-attention" />
            Pay Commission
          </DialogTitle>
          <DialogDescription>
            Outstanding commission: <MoneyText :amount="investor.outstanding_commission" :currency="currencyCode" />
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitCommission">
          <div class="space-y-2">
            <Label for="commission_amount">Payment Amount ({{ currency }}) *</Label>
            <Input
              id="commission_amount"
              v-model.number="commissionForm.amount"
              type="number"
              min="1"
              :max="investor.outstanding_commission"
              step="1"
              :class="{ 'border-destructive': commissionForm.errors.amount }"
            />
            <p v-if="commissionForm.errors.amount" class="text-sm text-destructive">
              {{ commissionForm.errors.amount }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="payment_date">Payment Date</Label>
            <Input
              id="payment_date"
              v-model="commissionForm.payment_date"
              type="date"
              :class="{ 'border-destructive': commissionForm.errors.payment_date }"
            />
            <p v-if="commissionForm.errors.payment_date" class="text-sm text-destructive">
              {{ commissionForm.errors.payment_date }}
            </p>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="commissionForm.processing" @click="commissionDialogOpen = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-status-attention hover:bg-status-attention" :disabled="commissionForm.processing">
              <span
                v-if="commissionForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Pay Commission
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
