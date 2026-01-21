<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
import {
  Calculator,
  Calendar,
  Lock,
  Unlock,
  Edit,
  ArrowLeft,
  CheckCircle,
  XCircle,
  RotateCcw,
  GitBranch,
  Fuel,
  Wallet,
  ArrowDownRight,
} from 'lucide-vue-next'
import AmendmentChain from './AmendmentChain.vue'
import { currencySymbol } from '@/lib/utils'

interface TransactionData {
  id: string
  transaction_number: string
  transaction_date: string
  created_at: string
  status: 'posted' | 'locked' | 'reversed' | 'reversal' | 'correction'
  is_locked: boolean
  is_amendable: boolean
  lock_reason: string | null
  locked_at: string | null
  amendment_reason: string | null
  amended_at: string | null
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
    bank_deposits?: number
    partner_withdrawals?: number
    employee_advances?: number
    expenses?: number
    partner_deposits?: number
  }
}

interface ChainItem {
  id: string
  transaction_number: string
  transaction_date: string
  created_at: string
  type: 'original' | 'reversal' | 'correction'
  status: string
  metadata: Record<string, unknown>
  amendment_reason: string | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  transaction: TransactionData
  amendmentChain: ChainItem[]
  permissions: {
    canAmend: boolean
    canLock: boolean
    canUnlock: boolean
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close', href: `/${props.company.slug}/fuel/daily-close/history` },
  { title: props.transaction.transaction_number, href: `/${props.company.slug}/fuel/daily-close/${props.transaction.id}` },
])

const currency = computed(() => currencySymbol(props.company.base_currency || 'PKR'))

const formatCurrency = (amount: number | undefined) => {
  if (amount === undefined) return '0'
  return new Intl.NumberFormat('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PK', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const formatDateTime = (datetime: string | null) => {
  if (!datetime) return ''
  return new Date(datetime).toLocaleString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
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

const hasAmendmentChain = computed(() => props.amendmentChain.length > 1)

const metadata = computed(() => props.transaction.metadata || {})

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

const unlockTransaction = () => {
  router.post(`/${props.company.slug}/fuel/daily-close/${props.transaction.id}/unlock`, {}, {
    preserveScroll: true,
    onError: () => toast.error('Failed to unlock daily close'),
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
    <template #actions>
      <div class="flex items-center gap-2">
        <Button variant="outline" as-child>
          <Link :href="`/${company.slug}/fuel/daily-close/history`">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to History
          </Link>
        </Button>

        <template v-if="permissions.canAmend && transaction.is_amendable">
          <Button as-child>
            <Link :href="`/${company.slug}/fuel/daily-close/${transaction.id}/amend`">
              <Edit class="h-4 w-4 mr-2" />
              Amend
            </Link>
          </Button>
        </template>

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
                  Locking this daily close will prevent any amendments. Only an owner can unlock it later.
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
          <Dialog>
            <DialogTrigger as-child>
              <Button variant="outline">
                <Unlock class="h-4 w-4 mr-2" />
                Unlock
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Unlock Daily Close?</DialogTitle>
                <DialogDescription>
                  Unlocking this daily close will allow amendments again. Are you sure?
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <DialogClose as-child>
                  <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button @click="unlockTransaction">Unlock</Button>
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
          transaction.status === 'reversed' ? 'bg-red-50 border-red-200' : '',
          transaction.status === 'locked' || transaction.is_locked ? 'bg-amber-50 border-amber-200' : '',
          transaction.status === 'correction' ? 'bg-blue-50 border-blue-200' : '',
        ]"
      >
        <component
          :is="statusConfig.icon"
          :class="[
            'h-5 w-5',
            transaction.status === 'reversed' ? 'text-red-600' : '',
            transaction.status === 'locked' || transaction.is_locked ? 'text-amber-600' : '',
            transaction.status === 'correction' ? 'text-blue-600' : '',
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
          <p v-if="transaction.amendment_reason" class="text-sm text-muted-foreground mt-1">
            Reason: {{ transaction.amendment_reason }}
          </p>
        </div>
        <Badge :variant="statusConfig.variant">
          <component :is="statusConfig.icon" class="h-3 w-3 mr-1" />
          {{ statusConfig.label }}
        </Badge>
      </div>
    </div>

    <!-- Amendment Chain -->
    <template v-if="hasAmendmentChain">
      <Card class="mb-6">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <GitBranch class="h-5 w-5" />
            Amendment History
          </CardTitle>
          <CardDescription>
            This entry is part of an amendment chain
          </CardDescription>
        </CardHeader>
        <CardContent>
          <AmendmentChain :chain="amendmentChain" :current-id="transaction.id" :company-slug="company.slug" />
        </CardContent>
      </Card>
    </template>

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
              <span class="font-semibold">{{ currency }} {{ formatCurrency(entry.revenue) }}</span>
            </div>
          </div>

          <!-- Other Sales -->
          <div v-if="metadata.other_sales" class="flex justify-between items-center py-2 border-b">
            <span>Other Sales (Lubricants, etc.)</span>
            <span class="font-semibold">{{ currency }} {{ formatCurrency(metadata.other_sales) }}</span>
          </div>

          <Separator />

          <!-- Total Revenue -->
          <div class="flex justify-between items-center text-lg font-bold">
            <span>Total Revenue</span>
            <span>{{ currency }} {{ formatCurrency(metadata.total_revenue) }}</span>
          </div>

          <!-- COGS -->
          <div class="flex justify-between items-center text-muted-foreground">
            <span>Cost of Goods Sold</span>
            <span>{{ currency }} {{ formatCurrency(metadata.total_cogs) }}</span>
          </div>

          <!-- Gross Profit -->
          <div class="flex justify-between items-center text-green-600 font-semibold">
            <span>Gross Profit</span>
            <span>{{ currency }} {{ formatCurrency((metadata.total_revenue || 0) - (metadata.total_cogs || 0)) }}</span>
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
              <span class="font-semibold">{{ currency }} {{ formatCurrency(metadata.opening_cash) }}</span>
            </div>
            <div v-if="metadata.partner_deposits" class="flex justify-between items-center py-2">
              <span>Partner Deposits</span>
              <span class="font-semibold text-green-600">+{{ currency }} {{ formatCurrency(metadata.partner_deposits) }}</span>
            </div>
          </div>

          <Separator />

          <!-- Cash Out -->
          <div class="space-y-2">
            <div v-if="metadata.bank_deposits" class="flex justify-between items-center py-2">
              <span>Bank Deposits</span>
              <span class="font-semibold text-red-600">-{{ currency }} {{ formatCurrency(metadata.bank_deposits) }}</span>
            </div>
            <div v-if="metadata.partner_withdrawals" class="flex justify-between items-center py-2">
              <span>Partner Withdrawals</span>
              <span class="font-semibold text-red-600">-{{ currency }} {{ formatCurrency(metadata.partner_withdrawals) }}</span>
            </div>
            <div v-if="metadata.employee_advances" class="flex justify-between items-center py-2">
              <span>Employee Advances</span>
              <span class="font-semibold text-red-600">-{{ currency }} {{ formatCurrency(metadata.employee_advances) }}</span>
            </div>
            <div v-if="metadata.expenses" class="flex justify-between items-center py-2">
              <span>Expenses</span>
              <span class="font-semibold text-red-600">-{{ currency }} {{ formatCurrency(metadata.expenses) }}</span>
            </div>
          </div>

          <Separator />

          <!-- Closing -->
          <div class="space-y-2">
            <div class="flex justify-between items-center py-2 text-muted-foreground">
              <span>Expected Closing</span>
              <span>{{ currency }} {{ formatCurrency(metadata.expected_closing) }}</span>
            </div>
            <div class="flex justify-between items-center py-2 text-lg font-bold">
              <span>Actual Closing Cash</span>
              <span>{{ currency }} {{ formatCurrency(metadata.closing_cash) }}</span>
            </div>
            <div
              v-if="metadata.variance !== undefined && metadata.variance !== 0"
              :class="[
                'flex justify-between items-center py-2 font-semibold',
                metadata.variance > 0 ? 'text-blue-600' : 'text-red-600'
              ]"
            >
              <span>{{ metadata.variance > 0 ? 'Cash Over' : 'Cash Short' }}</span>
              <span>{{ currency }} {{ formatCurrency(Math.abs(metadata.variance)) }}</span>
            </div>
            <div v-else-if="metadata.variance === 0" class="flex justify-between items-center py-2 text-green-600 font-semibold">
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
