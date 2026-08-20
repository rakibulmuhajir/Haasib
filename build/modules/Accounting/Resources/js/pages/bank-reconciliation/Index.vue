<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import MoneyText from '@/components/MoneyText.vue'
import {
  RefreshCcw,
  PlusCircle,
  Eye
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'

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
  last_reconciled_date: string | null
}

interface ReconciliationRow {
  id: string
  statement_date: string
  statement_ending_balance: number
  book_balance: number
  reconciled_balance: number
  difference: number
  status: 'in_progress' | 'completed' | 'cancelled'
  started_at: string
  completed_at: string | null
  bank_account: {
    id: string
    account_name: string
    account_number: string
    currency: string
  }
}

interface Filters {
  bank_account_id: string
  status: string
}

interface PaginatedData {
  data: ReconciliationRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  reconciliations: PaginatedData
  bankAccounts: BankAccountRef[]
  filters: Filters
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Reconciliation', href: `/${props.company.slug}/banking/reconciliation` },
]

const bankAccountFilter = ref(props.filters.bank_account_id || '__all')
const statusFilter = ref(props.filters.status || '__all')

const noneValue = '__all'

// The reconciliation's own lifecycle, not the balance check -- that one gets
// its own colour further down. An in-progress reconciliation is waiting on
// someone, which is what "pending" means everywhere else in this app;
// "completed" is the same fact as "matched" on a bank register, so it reuses
// that state rather than inventing a synonym.
const reconciliationStatus: Record<string, string> = {
  in_progress: 'pending',
  completed: 'reconciled',
  cancelled: 'cancelled',
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '—'
  return formatDateTime(dateStr, { mode: 'date' })
}

const handleFilter = () => {
  router.get(`/${props.company.slug}/banking/reconciliation`, {
    bank_account_id: bankAccountFilter.value === noneValue ? '' : bankAccountFilter.value,
    status: statusFilter.value === noneValue ? '' : statusFilter.value,
  }, { preserveState: true })
}

const handleStartNew = () => {
  router.get(`/${props.company.slug}/banking/reconciliation/start`)
}

const handleView = (id: string) => {
  router.get(`/${props.company.slug}/banking/reconciliation/${id}`)
}

/**
 * A reconciliation run is a register row like any other: which account,
 * against what statement, and whether the two sides came out even. The
 * difference is a pass/fail check rather than an ordinary figure -- it either
 * confirms the books or it doesn't -- so it keeps the success/critical colour
 * the rest of this register's amounts don't get.
 */
const reconciliationColumns = [
  { key: 'bank_account', label: 'Bank Account', kind: 'text' as const },
  { key: 'statement_date', label: 'Statement Date', kind: 'date' as const },
  { key: 'statement_ending_balance', label: 'Statement Balance', kind: 'amount' as const },
  { key: 'difference', label: 'Difference', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]
</script>

<template>
  <Head title="Bank Reconciliation" />

  <PageShell
    title="Bank Reconciliation"
    :breadcrumbs="breadcrumbs"
    :icon="RefreshCcw"
  >
    <template #actions>
      <Button @click="handleStartNew">
        <PlusCircle class="mr-2 h-4 w-4" />
        Start Reconciliation
      </Button>
    </template>

    <!-- Filters -->
    <Card class="mb-6" variant="form">
      <CardContent class="pt-6">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="space-y-2 min-w-[200px]">
            <Label>Bank Account</Label>
            <Select v-model="bankAccountFilter" @update:model-value="handleFilter">
              <SelectTrigger>
                <SelectValue placeholder="All accounts" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem :value="noneValue">All accounts</SelectItem>
                <SelectItem
                  v-for="account in bankAccounts"
                  :key="account.id"
                  :value="account.id"
                >
                  {{ account.account_name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2 min-w-[150px]">
            <Label>Status</Label>
            <Select v-model="statusFilter" @update:model-value="handleFilter">
              <SelectTrigger>
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem :value="noneValue">All statuses</SelectItem>
                <SelectItem value="in_progress">In Progress</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Reconciliations Table -->
    <Card variant="register">
      <CardContent>
        <div v-if="reconciliations.data.length === 0" class="text-center py-12 text-muted-foreground">
          <RefreshCcw class="mx-auto h-12 w-12 mb-4 opacity-50" />
          <p class="text-lg font-medium">No reconciliations found</p>
          <p class="text-sm mb-4">Start a new reconciliation to balance your accounts</p>
          <Button @click="handleStartNew">
            <PlusCircle class="mr-2 h-4 w-4" />
            Start Reconciliation
          </Button>
        </div>

        <LedgerRegister
          v-else
          :data="reconciliations.data"
          :columns="reconciliationColumns"
          clickable
          @row-click="(row) => handleView(row.id)"
        >
          <template #cell-bank_account="{ row }">
            <div>
              <p class="font-medium">{{ row.bank_account.account_name }}</p>
              <p class="text-xs text-muted-foreground">{{ row.bank_account.account_number }}</p>
            </div>
          </template>

          <template #cell-statement_date="{ row }">{{ formatDate(row.statement_date) }}</template>

          <template #cell-statement_ending_balance="{ row }">
            <MoneyText :amount="row.statement_ending_balance" :currency="row.bank_account.currency" />
          </template>

          <template #cell-difference="{ row }">
            <span :class="Math.abs(row.difference) < 0.01 ? 'text-status-success' : 'text-status-critical'">
              <MoneyText :amount="row.difference" :currency="row.bank_account.currency" />
            </span>
          </template>

          <template #cell-status="{ row }">
            <StatusBadge :status="reconciliationStatus[row.status]" />
          </template>

          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2" @click.stop>
              <Button variant="ghost" size="sm" @click="handleView(row.id)">
                <Eye class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>

    <!-- Pagination -->
    <div v-if="reconciliations.last_page > 1" class="mt-6 flex justify-center gap-2">
      <Button
        variant="outline"
        :disabled="reconciliations.current_page === 1"
        @click="router.get(`/${company.slug}/banking/reconciliation`, { page: reconciliations.current_page - 1, ...filters })"
      >
        Previous
      </Button>
      <span class="flex items-center px-4 text-sm text-muted-foreground">
        Page {{ reconciliations.current_page }} of {{ reconciliations.last_page }}
      </span>
      <Button
        variant="outline"
        :disabled="reconciliations.current_page === reconciliations.last_page"
        @click="router.get(`/${company.slug}/banking/reconciliation`, { page: reconciliations.current_page + 1, ...filters })"
      >
        Next
      </Button>
    </div>
  </PageShell>
</template>
