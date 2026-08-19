<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MetaChip from '@/components/MetaChip.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import MoneyText from '@/components/MoneyText.vue'
import {
  Landmark,
  Pencil,
  Trash2,
  RefreshCcw,
  ArrowUpRight,
  ArrowDownLeft,
  MoreHorizontal,
  Calendar,
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankRef {
  id: string
  name: string
  swift_code: string | null
}

interface GlAccountRef {
  id: string
  code: string
  name: string
}

interface TransactionRow {
  id: string
  transaction_date: string
  description: string
  transaction_type: string
  amount: number
  is_reconciled: boolean
  payee_name: string | null
  category: string | null
}

interface ReconciliationRef {
  id: string
  statement_date: string
  statement_ending_balance: number
  completed_at: string
}

interface BankAccountRef {
  id: string
  account_name: string
  account_number: string
  account_type: string
  currency: string
  current_balance: number
  opening_balance: number
  opening_balance_date: string | null
  is_active: boolean
  is_primary: boolean
  iban: string | null
  swift_code: string | null
  routing_number: string | null
  branch_name: string | null
  branch_address: string | null
  last_reconciled_date: string | null
  last_reconciled_balance: number | null
  notes: string | null
  bank: BankRef | null
  gl_account: GlAccountRef | null
  unreconciled_count: number
  created_at: string
  updated_at: string
}

const props = defineProps<{
  company: CompanyRef
  bankAccount: BankAccountRef
  recentTransactions: TransactionRow[]
  lastReconciliation: ReconciliationRef | null
  canEdit: boolean
  canDelete: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: props.bankAccount.account_name, href: `/${props.company.slug}/banking/accounts/${props.bankAccount.id}` },
]

const accountTypeLabels: Record<string, string> = {
  checking: 'Checking Account',
  savings: 'Savings Account',
  credit_card: 'Credit Card',
  cash: 'Petty Cash',
  other: 'Other Account',
}

const formatDate = (dateStr: string | null) => {
  return formatSharedDateTime(dateStr, { mode: 'date' })
}

const formatDateTime = (dateStr: string | null) => {
  return formatSharedDateTime(dateStr, { mode: 'datetime' })
}

const handleEdit = () => {
  router.get(`/${props.company.slug}/banking/accounts/${props.bankAccount.id}/edit`)
}

const handleDelete = () => {
  if (props.bankAccount.unreconciled_count > 0) {
    alert('Cannot delete account with unreconciled transactions.')
    return
  }
  if (!confirm(`Delete "${props.bankAccount.account_name}"?`)) return
  router.delete(`/${props.company.slug}/banking/accounts/${props.bankAccount.id}`)
}

const handleReconcile = () => {
  router.get(`/${props.company.slug}/banking/reconciliation/start`, {
    bank_account_id: props.bankAccount.id
  })
}

const handleViewTransaction = (id: string) => {
  // Navigate to bank feed with this transaction highlighted
  router.get(`/${props.company.slug}/banking/feed`, { transaction_id: id })
}

/**
 * This account moves money in both directions, so the register gives each
 * direction its own column instead of a single signed figure. A statement
 * reader should never have to parse a minus sign to know which way money
 * went -- the column heading already says so. Reconciliation is a state, so
 * it goes through StatusBadge and reuses the same "reconciled" vocabulary as
 * bank reconciliation and journals rather than a bespoke icon.
 */
const transactionColumns = [
  { key: 'transaction_date', label: 'Date', kind: 'date' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'category', label: 'Category', kind: 'text' as const },
  { key: 'deposited', label: 'Deposited', kind: 'in' as const },
  { key: 'withdrawn', label: 'Withdrawn', kind: 'out' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
]
</script>

<template>
  <Head :title="bankAccount.account_name" />
  <PageShell
    :title="bankAccount.account_name"
    :breadcrumbs="breadcrumbs"
    :icon="Landmark"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button variant="outline" @click="handleReconcile">
          <RefreshCcw class="mr-2 h-4 w-4" />
          Reconcile
        </Button>
        <Button v-if="canEdit" variant="outline" @click="handleEdit">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button
          v-if="canDelete && bankAccount.unreconciled_count === 0"
          variant="destructive"
          @click="handleDelete"
        >
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Main Info -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Balance Card -->
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-muted-foreground">Current Balance</p>
                <p class="text-3xl font-bold"><MoneyText :amount="bankAccount.current_balance" :currency="bankAccount.currency" /></p>
              </div>
              <div class="flex items-center gap-2">
                <Badge :variant="bankAccount.is_active ? 'default' : 'secondary'">
                  {{ bankAccount.is_active ? 'Active' : 'Inactive' }}
                </Badge>
                <Badge v-if="bankAccount.is_primary" variant="outline">Primary</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Account Details -->
        <Card>
          <CardHeader>
            <CardTitle>Account Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-sm text-muted-foreground">Account Type</p>
                <p class="font-medium">{{ accountTypeLabels[bankAccount.account_type] || bankAccount.account_type }}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">Account Number</p>
                <p class="font-medium font-mono">{{ bankAccount.account_number }}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">Currency</p>
                <p class="font-medium">{{ bankAccount.currency }}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">Bank</p>
                <p class="font-medium">{{ bankAccount.bank?.name || '—' }}</p>
              </div>
              <div v-if="bankAccount.iban">
                <p class="text-sm text-muted-foreground">IBAN</p>
                <p class="font-medium font-mono">{{ bankAccount.iban }}</p>
              </div>
              <div v-if="bankAccount.swift_code">
                <p class="text-sm text-muted-foreground">SWIFT Code</p>
                <p class="font-medium font-mono">{{ bankAccount.swift_code }}</p>
              </div>
              <div v-if="bankAccount.routing_number">
                <p class="text-sm text-muted-foreground">Routing Number</p>
                <p class="font-medium font-mono">{{ bankAccount.routing_number }}</p>
              </div>
              <div v-if="bankAccount.gl_account">
                <p class="text-sm text-muted-foreground">GL Account</p>
                <p class="font-medium">{{ bankAccount.gl_account.code }} — {{ bankAccount.gl_account.name }}</p>
              </div>
            </div>

            <div v-if="bankAccount.branch_name || bankAccount.branch_address" class="mt-4 pt-4 border-t">
              <p class="text-sm text-muted-foreground mb-1">Branch</p>
              <p v-if="bankAccount.branch_name" class="font-medium">{{ bankAccount.branch_name }}</p>
              <p v-if="bankAccount.branch_address" class="text-sm text-muted-foreground">{{ bankAccount.branch_address }}</p>
            </div>

            <div v-if="bankAccount.notes" class="mt-4 pt-4 border-t">
              <p class="text-sm text-muted-foreground mb-1">Notes</p>
              <p class="text-sm">{{ bankAccount.notes }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Recent Transactions -->
        <Card>
          <CardHeader>
            <CardTitle>Recent Transactions</CardTitle>
            <CardDescription>Last 25 transactions</CardDescription>
          </CardHeader>
          <CardContent class="p-0">
            <LedgerRegister
              :data="recentTransactions"
              :columns="transactionColumns"
              clickable
              @row-click="(row) => handleViewTransaction(row.id)"
            >
              <template #empty>No transactions yet</template>

              <template #cell-description="{ row }">
                <div class="flex items-center gap-2">
                  <component
                    :is="row.amount > 0 ? ArrowDownLeft : ArrowUpRight"
                    :class="row.amount > 0 ? 'text-amount-inflow' : 'text-amount-outflow'"
                    class="h-4 w-4"
                  />
                  <div>
                    <p class="font-medium">{{ row.description }}</p>
                    <p v-if="row.payee_name" class="text-xs text-muted-foreground">{{ row.payee_name }}</p>
                  </div>
                </div>
              </template>

              <template #cell-category="{ row }">
                <MetaChip v-if="row.category" tone="neutral" bare>{{ row.category }}</MetaChip>
                <span v-else class="text-muted-foreground text-sm">Uncategorized</span>
              </template>

              <template #cell-deposited="{ row }">
                <MoneyText v-if="row.amount > 0" :amount="row.amount" :currency="bankAccount.currency" />
                <template v-else>—</template>
              </template>

              <template #cell-withdrawn="{ row }">
                <MoneyText v-if="row.amount < 0" :amount="Math.abs(row.amount)" :currency="bankAccount.currency" />
                <template v-else>—</template>
              </template>

              <template #cell-status="{ row }">
                <StatusBadge :status="row.is_reconciled ? 'reconciled' : 'pending'" />
              </template>
            </LedgerRegister>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Reconciliation Status -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <RefreshCcw class="h-4 w-4" />
              Reconciliation
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div>
                <p class="text-sm text-muted-foreground">Unreconciled Transactions</p>
                <p class="text-2xl font-bold" :class="bankAccount.unreconciled_count > 0 ? 'text-status-attention' : 'text-status-success'">
                  {{ bankAccount.unreconciled_count }}
                </p>
              </div>

              <div v-if="lastReconciliation">
                <p class="text-sm text-muted-foreground">Last Reconciled</p>
                <p class="font-medium">{{ formatDate(lastReconciliation.statement_date) }}</p>
                <p class="text-sm text-muted-foreground">
                  Balance: <MoneyText :amount="lastReconciliation.statement_ending_balance" :currency="bankAccount.currency" />
                </p>
              </div>

              <div v-if="bankAccount.last_reconciled_date">
                <p class="text-sm text-muted-foreground">Last Reconciled Date</p>
                <p class="font-medium">{{ formatDate(bankAccount.last_reconciled_date) }}</p>
              </div>

              <Button class="w-full" @click="handleReconcile">
                <RefreshCcw class="mr-2 h-4 w-4" />
                Start Reconciliation
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Opening Balance -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Calendar class="h-4 w-4" />
              Opening Balance
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div>
                <p class="text-sm text-muted-foreground">Amount</p>
                <p class="font-medium"><MoneyText :amount="bankAccount.opening_balance" :currency="bankAccount.currency" /></p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">As of</p>
                <p class="font-medium">{{ formatDate(bankAccount.opening_balance_date) }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Timestamps -->
        <Card>
          <CardContent class="pt-6">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Created</span>
                <span>{{ formatDateTime(bankAccount.created_at) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Updated</span>
                <span>{{ formatDateTime(bankAccount.updated_at) }}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
