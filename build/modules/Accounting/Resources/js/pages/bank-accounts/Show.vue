<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Landmark,
  Pencil,
  Trash2,
  RefreshCcw,
  ArrowUpRight,
  ArrowDownLeft,
  MoreHorizontal,
  Calendar,
  CheckCircle2,
  Clock
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

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

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency,
  }).format(amount)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const formatDateTime = (dateStr: string | null) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
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
                <p class="text-3xl font-bold">{{ formatCurrency(bankAccount.current_balance, bankAccount.currency) }}</p>
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
          <CardContent>
            <div v-if="recentTransactions.length === 0" class="text-center py-8 text-muted-foreground">
              No transactions yet
            </div>
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                  <TableHead class="text-center">Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="tx in recentTransactions"
                  :key="tx.id"
                  class="cursor-pointer hover:bg-muted/50"
                  @click="handleViewTransaction(tx.id)"
                >
                  <TableCell class="whitespace-nowrap">
                    {{ formatDate(tx.transaction_date) }}
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <component
                        :is="tx.amount > 0 ? ArrowDownLeft : ArrowUpRight"
                        :class="tx.amount > 0 ? 'text-green-600' : 'text-red-600'"
                        class="h-4 w-4"
                      />
                      <div>
                        <p class="font-medium">{{ tx.description }}</p>
                        <p v-if="tx.payee_name" class="text-xs text-muted-foreground">{{ tx.payee_name }}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge v-if="tx.category" variant="outline">{{ tx.category }}</Badge>
                    <span v-else class="text-muted-foreground text-sm">Uncategorized</span>
                  </TableCell>
                  <TableCell class="text-right font-mono" :class="tx.amount > 0 ? 'text-green-600' : 'text-red-600'">
                    {{ formatCurrency(Math.abs(tx.amount), bankAccount.currency) }}
                  </TableCell>
                  <TableCell class="text-center">
                    <CheckCircle2 v-if="tx.is_reconciled" class="h-4 w-4 text-green-600 mx-auto" />
                    <Clock v-else class="h-4 w-4 text-amber-500 mx-auto" />
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
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
                <p class="text-2xl font-bold" :class="bankAccount.unreconciled_count > 0 ? 'text-amber-600' : 'text-green-600'">
                  {{ bankAccount.unreconciled_count }}
                </p>
              </div>

              <div v-if="lastReconciliation">
                <p class="text-sm text-muted-foreground">Last Reconciled</p>
                <p class="font-medium">{{ formatDate(lastReconciliation.statement_date) }}</p>
                <p class="text-sm text-muted-foreground">
                  Balance: {{ formatCurrency(lastReconciliation.statement_ending_balance, bankAccount.currency) }}
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
                <p class="font-medium">{{ formatCurrency(bankAccount.opening_balance, bankAccount.currency) }}</p>
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
