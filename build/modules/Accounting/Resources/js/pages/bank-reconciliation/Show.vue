<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Progress } from '@/components/ui/progress'
import {
  RefreshCcw,
  CheckCircle2,
  XCircle,
  ArrowDownLeft,
  ArrowUpRight,
  Clock,
  AlertTriangle
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankAccountRef {
  id: string
  account_name: string
  account_number: string
  currency: string
  current_balance: number
  opening_balance: number
}

interface ReconciliationRef {
  id: string
  statement_date: string
  statement_ending_balance: number
  book_balance: number
  reconciled_balance: number
  difference: number
  status: 'in_progress' | 'completed' | 'cancelled'
  started_at: string
  completed_at: string | null
  bank_account: BankAccountRef
}

interface TransactionRow {
  id: string
  transaction_date: string
  description: string
  transaction_type: string
  amount: number
  is_reconciled: boolean
  reconciliation_id: string | null
  payee_name: string | null
  reference_number: string | null
}

interface SummaryRef {
  starting_balance: number
  statement_ending_balance: number
  reconciled_balance: number
  book_balance: number
  difference: number
  cleared_deposits: number
  cleared_withdrawals: number
  uncleared_count: number
}

const props = defineProps<{
  company: CompanyRef
  reconciliation: ReconciliationRef
  transactions: TransactionRow[]
  summary: SummaryRef
  canComplete: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Reconciliation', href: `/${props.company.slug}/banking/reconciliation` },
  { title: formatDate(props.reconciliation.statement_date), href: '#' },
]

// Local state for optimistic updates
const localTransactions = ref([...props.transactions])
const localSummary = ref({ ...props.summary })
const localCanComplete = ref(props.canComplete)
const isProcessing = ref(false)

const isInProgress = computed(() => props.reconciliation.status === 'in_progress')
const isCompleted = computed(() => props.reconciliation.status === 'completed')
const isCancelled = computed(() => props.reconciliation.status === 'cancelled')

function formatCurrency(amount: number, currency: string) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function isCleared(transaction: TransactionRow) {
  return transaction.reconciliation_id === props.reconciliation.id
}

async function toggleTransaction(transaction: TransactionRow) {
  if (!isInProgress.value || isProcessing.value) return

  isProcessing.value = true

  // Optimistic update
  const idx = localTransactions.value.findIndex(t => t.id === transaction.id)
  if (idx !== -1) {
    const wasCleared = isCleared(localTransactions.value[idx])
    localTransactions.value[idx] = {
      ...localTransactions.value[idx],
      reconciliation_id: wasCleared ? null : props.reconciliation.id,
      is_reconciled: !wasCleared,
    }

    // Update summary
    const amount = transaction.amount
    if (wasCleared) {
      // Unclearing
      if (amount > 0) {
        localSummary.value.cleared_deposits -= amount
      } else {
        localSummary.value.cleared_withdrawals -= amount
      }
      localSummary.value.reconciled_balance -= amount
      localSummary.value.uncleared_count++
    } else {
      // Clearing
      if (amount > 0) {
        localSummary.value.cleared_deposits += amount
      } else {
        localSummary.value.cleared_withdrawals += amount
      }
      localSummary.value.reconciled_balance += amount
      localSummary.value.uncleared_count--
    }
    localSummary.value.difference = localSummary.value.statement_ending_balance - localSummary.value.reconciled_balance
    localCanComplete.value = Math.abs(localSummary.value.difference) < 0.01
  }

  try {
    const response = await fetch(`/${props.company.slug}/banking/reconciliation/${props.reconciliation.id}/toggle`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ transaction_id: transaction.id }),
    })

    const data = await response.json()

    if (!data.success) {
      throw new Error('Failed to toggle transaction')
    }

    // Update with server values
    localSummary.value.reconciled_balance = data.reconciled_balance
    localSummary.value.difference = data.difference
    localCanComplete.value = data.can_complete
  } catch (error) {
    // Revert optimistic update
    localTransactions.value = [...props.transactions]
    localSummary.value = { ...props.summary }
    localCanComplete.value = props.canComplete
    toast.error('Failed to update transaction')
  } finally {
    isProcessing.value = false
  }
}

function handleComplete() {
  router.post(`/${props.company.slug}/banking/reconciliation/${props.reconciliation.id}/complete`, {}, {
    onSuccess: () => {
      toast.success('Reconciliation completed successfully')
    },
    onError: () => {
      toast.error('Failed to complete reconciliation')
    },
  })
}

function confirmCancel() {
  if (!confirm('Cancel this reconciliation? This will unmark all cleared transactions.')) return
  handleCancel()
}

function handleCancel() {
  router.post(`/${props.company.slug}/banking/reconciliation/${props.reconciliation.id}/cancel`, {}, {
    onSuccess: () => {
      toast.success('Reconciliation cancelled')
    },
    onError: () => {
      toast.error('Failed to cancel reconciliation')
    },
  })
}

const deposits = computed(() => localTransactions.value.filter(t => t.amount > 0))
const withdrawals = computed(() => localTransactions.value.filter(t => t.amount < 0))

const progressPercent = computed(() => {
  const total = localTransactions.value.length
  if (total === 0) return 100
  const cleared = localTransactions.value.filter(t => isCleared(t)).length
  return Math.round((cleared / total) * 100)
})
</script>

<template>
  <Head :title="`Reconciliation - ${formatDate(reconciliation.statement_date)}`" />

  <PageShell
    :title="`Reconcile ${reconciliation.bank_account.account_name}`"
    :breadcrumbs="breadcrumbs"
    :icon="RefreshCcw"
  >
    <template #actions>
      <div class="flex gap-2">
        <template v-if="isInProgress">
          <Button variant="outline" @click="confirmCancel">
            <XCircle class="mr-2 h-4 w-4" />
            Cancel
          </Button>

          <Button
            :disabled="!localCanComplete"
            @click="handleComplete"
          >
            <CheckCircle2 class="mr-2 h-4 w-4" />
            Finish Reconciliation
          </Button>
        </template>

        <Badge v-if="isCompleted" variant="secondary" class="px-3 py-1">
          <CheckCircle2 class="mr-1 h-4 w-4" />
          Completed
        </Badge>

        <Badge v-if="isCancelled" variant="destructive" class="px-3 py-1">
          <XCircle class="mr-1 h-4 w-4" />
          Cancelled
        </Badge>
      </div>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Progress -->
        <Card v-if="isInProgress">
          <CardContent class="pt-6">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-muted-foreground">Progress</span>
              <span class="text-sm font-medium">{{ progressPercent }}%</span>
            </div>
            <Progress :model-value="progressPercent" class="h-2" />
            <p class="mt-2 text-xs text-muted-foreground">
              {{ localTransactions.filter(t => isCleared(t)).length }} of {{ localTransactions.length }} transactions cleared
            </p>
          </CardContent>
        </Card>

        <!-- Deposits Section -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <ArrowDownLeft class="h-5 w-5 text-green-600" />
              Deposits & Credits
              <Badge variant="outline" class="ml-auto">{{ deposits.length }}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="deposits.length === 0" class="text-center py-6 text-muted-foreground">
              No deposits in this period
            </div>
            <div v-else class="divide-y">
              <div
                v-for="tx in deposits"
                :key="tx.id"
                class="flex items-center gap-4 py-3"
                :class="{ 'opacity-50': isCancelled }"
              >
                <Checkbox
                  v-if="isInProgress"
                  :checked="isCleared(tx)"
                  :disabled="isProcessing"
                  @update:checked="toggleTransaction(tx)"
                />
                <CheckCircle2
                  v-else-if="isCleared(tx)"
                  class="h-5 w-5 text-green-600"
                />
                <Clock
                  v-else
                  class="h-5 w-5 text-muted-foreground"
                />

                <div class="flex-1 min-w-0">
                  <p class="font-medium truncate">{{ tx.description }}</p>
                  <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{{ formatDate(tx.transaction_date) }}</span>
                    <span v-if="tx.payee_name">&bull; {{ tx.payee_name }}</span>
                    <span v-if="tx.reference_number">&bull; #{{ tx.reference_number }}</span>
                  </div>
                </div>

                <span class="font-mono text-green-600 font-medium">
                  +{{ formatCurrency(tx.amount, reconciliation.bank_account.currency) }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Withdrawals Section -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <ArrowUpRight class="h-5 w-5 text-red-600" />
              Payments & Withdrawals
              <Badge variant="outline" class="ml-auto">{{ withdrawals.length }}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="withdrawals.length === 0" class="text-center py-6 text-muted-foreground">
              No withdrawals in this period
            </div>
            <div v-else class="divide-y">
              <div
                v-for="tx in withdrawals"
                :key="tx.id"
                class="flex items-center gap-4 py-3"
                :class="{ 'opacity-50': isCancelled }"
              >
                <Checkbox
                  v-if="isInProgress"
                  :checked="isCleared(tx)"
                  :disabled="isProcessing"
                  @update:checked="toggleTransaction(tx)"
                />
                <CheckCircle2
                  v-else-if="isCleared(tx)"
                  class="h-5 w-5 text-green-600"
                />
                <Clock
                  v-else
                  class="h-5 w-5 text-muted-foreground"
                />

                <div class="flex-1 min-w-0">
                  <p class="font-medium truncate">{{ tx.description }}</p>
                  <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{{ formatDate(tx.transaction_date) }}</span>
                    <span v-if="tx.payee_name">&bull; {{ tx.payee_name }}</span>
                    <span v-if="tx.reference_number">&bull; #{{ tx.reference_number }}</span>
                  </div>
                </div>

                <span class="font-mono text-red-600 font-medium">
                  {{ formatCurrency(tx.amount, reconciliation.bank_account.currency) }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar - Summary -->
      <div class="space-y-6">
        <!-- Reconciliation Summary -->
        <Card class="sticky top-6">
          <CardHeader>
            <CardTitle>Reconciliation Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <!-- Statement Info -->
            <div class="pb-4 border-b">
              <p class="text-sm text-muted-foreground">Statement Date</p>
              <p class="font-medium">{{ formatDate(reconciliation.statement_date) }}</p>
            </div>

            <!-- Balances -->
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-sm text-muted-foreground">Starting Balance</span>
                <span class="font-mono">
                  {{ formatCurrency(localSummary.starting_balance, reconciliation.bank_account.currency) }}
                </span>
              </div>

              <div class="flex justify-between text-green-600">
                <span class="text-sm">+ Cleared Deposits</span>
                <span class="font-mono">
                  {{ formatCurrency(localSummary.cleared_deposits, reconciliation.bank_account.currency) }}
                </span>
              </div>

              <div class="flex justify-between text-red-600">
                <span class="text-sm">- Cleared Payments</span>
                <span class="font-mono">
                  {{ formatCurrency(Math.abs(localSummary.cleared_withdrawals), reconciliation.bank_account.currency) }}
                </span>
              </div>

              <div class="flex justify-between pt-2 border-t">
                <span class="text-sm font-medium">Reconciled Balance</span>
                <span class="font-mono font-medium">
                  {{ formatCurrency(localSummary.reconciled_balance, reconciliation.bank_account.currency) }}
                </span>
              </div>

              <div class="flex justify-between">
                <span class="text-sm text-muted-foreground">Statement Balance</span>
                <span class="font-mono">
                  {{ formatCurrency(localSummary.statement_ending_balance, reconciliation.bank_account.currency) }}
                </span>
              </div>
            </div>

            <!-- Difference -->
            <div
              class="p-4 rounded-lg"
              :class="Math.abs(localSummary.difference) < 0.01 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium">Difference</span>
                <span
                  class="font-mono text-lg font-bold"
                  :class="Math.abs(localSummary.difference) < 0.01 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ formatCurrency(localSummary.difference, reconciliation.bank_account.currency) }}
                </span>
              </div>

              <p
                v-if="Math.abs(localSummary.difference) < 0.01"
                class="text-sm text-green-600 mt-2 flex items-center gap-1"
              >
                <CheckCircle2 class="h-4 w-4" />
                Ready to finish!
              </p>
              <p
                v-else
                class="text-sm text-red-600 mt-2 flex items-center gap-1"
              >
                <AlertTriangle class="h-4 w-4" />
                Clear more transactions to balance
              </p>
            </div>

            <!-- Uncleared Count -->
            <div class="text-center text-sm text-muted-foreground">
              {{ localSummary.uncleared_count }} transaction{{ localSummary.uncleared_count !== 1 ? 's' : '' }} still uncleared
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
