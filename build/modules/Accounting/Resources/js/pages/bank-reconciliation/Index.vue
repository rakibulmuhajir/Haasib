<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  RefreshCcw,
  PlusCircle,
  CheckCircle2,
  Clock,
  XCircle,
  Eye
} from 'lucide-vue-next'
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

const statusLabels: Record<string, string> = {
  in_progress: 'In Progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  in_progress: 'default',
  completed: 'secondary',
  cancelled: 'destructive',
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

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'completed': return CheckCircle2
    case 'cancelled': return XCircle
    default: return Clock
  }
}
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
    <Card class="mb-6">
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
    <Card>
      <CardContent class="pt-6">
        <div v-if="reconciliations.data.length === 0" class="text-center py-12 text-muted-foreground">
          <RefreshCcw class="mx-auto h-12 w-12 mb-4 opacity-50" />
          <p class="text-lg font-medium">No reconciliations found</p>
          <p class="text-sm mb-4">Start a new reconciliation to balance your accounts</p>
          <Button @click="handleStartNew">
            <PlusCircle class="mr-2 h-4 w-4" />
            Start Reconciliation
          </Button>
        </div>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead>Bank Account</TableHead>
              <TableHead>Statement Date</TableHead>
              <TableHead class="text-right">Statement Balance</TableHead>
              <TableHead class="text-right">Difference</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="recon in reconciliations.data"
              :key="recon.id"
              class="cursor-pointer hover:bg-muted/50"
              @click="handleView(recon.id)"
            >
              <TableCell>
                <div>
                  <p class="font-medium">{{ recon.bank_account.account_name }}</p>
                  <p class="text-xs text-muted-foreground">{{ recon.bank_account.account_number }}</p>
                </div>
              </TableCell>
              <TableCell>{{ formatDate(recon.statement_date) }}</TableCell>
              <TableCell class="text-right font-mono">
                {{ formatCurrency(recon.statement_ending_balance, recon.bank_account.currency) }}
              </TableCell>
              <TableCell class="text-right font-mono" :class="Math.abs(recon.difference) < 0.01 ? 'text-green-600' : 'text-red-600'">
                {{ formatCurrency(recon.difference, recon.bank_account.currency) }}
              </TableCell>
              <TableCell>
                <Badge :variant="statusVariants[recon.status]">
                  <component :is="getStatusIcon(recon.status)" class="mr-1 h-3 w-3" />
                  {{ statusLabels[recon.status] }}
                </Badge>
              </TableCell>
              <TableCell class="text-right">
                <Button variant="ghost" size="sm" @click.stop="handleView(recon.id)">
                  <Eye class="h-4 w-4" />
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
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
