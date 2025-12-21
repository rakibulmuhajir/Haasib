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
import { User, Plus, Wallet, TrendingUp, Banknote, Package, ArrowLeft } from 'lucide-vue-next'

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

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatNumber = (value: number, decimals = 2) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
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
      amount: formatCurrency(lot.investment_amount),
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
      return 'bg-emerald-600 text-white hover:bg-emerald-600'
    case 'depleted':
      return 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200'
    case 'withdrawn':
      return 'bg-amber-100 text-amber-800 hover:bg-amber-100'
    default:
      return 'bg-zinc-100 text-zinc-700'
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
        class="bg-amber-600 hover:bg-amber-700"
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
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(investor.total_invested) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>{{ lots.length }} lot(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Earned</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(investor.total_commission_earned) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-sky-100 text-sky-800 hover:bg-sky-100">
            <TrendingUp class="mr-1 h-3 w-3" />
            Lifetime
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Paid</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(investor.total_commission_paid) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="secondary" class="bg-zinc-100 text-zinc-700 hover:bg-zinc-100">
            <Banknote class="mr-1 h-3 w-3" />
            Disbursed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Outstanding</CardDescription>
          <CardTitle class="text-2xl" :class="investor.outstanding_commission > 0 ? 'text-amber-600' : ''">
            {{ formatCurrency(investor.outstanding_commission) }}
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge
            v-if="investor.outstanding_commission > 0"
            variant="outline"
            class="border-amber-200 text-amber-700"
          >
            Pending Payment
          </Badge>
          <Badge v-else variant="secondary" class="bg-emerald-100 text-emerald-700 hover:bg-emerald-100">
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
            <Package class="h-5 w-5 text-emerald-600" />
            Add Investment Lot
          </DialogTitle>
          <DialogDescription>
            <span v-if="currentRate">
              Current rate: {{ formatNumber(currentRate.purchase_rate) }} PKR/liter
              (+ {{ formatNumber(currentRate.margin) }} commission)
            </span>
            <span v-else>Rate will be locked at deposit time.</span>
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitLot">
          <div class="space-y-2">
            <Label for="investment_amount">Investment Amount (PKR) *</Label>
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
            <Banknote class="h-5 w-5 text-amber-600" />
            Pay Commission
          </DialogTitle>
          <DialogDescription>
            Outstanding commission: {{ formatCurrency(investor.outstanding_commission) }}
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitCommission">
          <div class="space-y-2">
            <Label for="commission_amount">Payment Amount (PKR) *</Label>
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
            <Button type="submit" class="bg-amber-600 hover:bg-amber-700" :disabled="commissionForm.processing">
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
