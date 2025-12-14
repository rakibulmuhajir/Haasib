<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import {
  Building2,
  PlusCircle,
  Search,
  CreditCard,
  Wallet,
  Landmark,
  CircleDollarSign,
  MoreHorizontal,
  Eye,
  Pencil,
  Trash2,
  RefreshCcw
} from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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

interface BankAccountRow {
  id: string
  account_name: string
  account_number: string
  account_type: 'checking' | 'savings' | 'credit_card' | 'cash' | 'other'
  currency: string
  current_balance: number
  is_active: boolean
  is_primary: boolean
  last_reconciled_date: string | null
  bank: BankRef | null
  gl_account: GlAccountRef | null
  unreconciled_count: number
}

interface Filters {
  search: string
  include_inactive: boolean
  sort_by: string
  sort_dir: string
}

interface PaginatedData {
  data: BankAccountRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  bankAccounts: PaginatedData
  filters: Filters
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
]

const searchTerm = ref(props.filters.search)
const includeInactive = ref(props.filters.include_inactive)

const accountTypeIcons: Record<string, typeof CreditCard> = {
  checking: Landmark,
  savings: Building2,
  credit_card: CreditCard,
  cash: Wallet,
  other: CircleDollarSign,
}

const accountTypeLabels: Record<string, string> = {
  checking: 'Checking',
  savings: 'Savings',
  credit_card: 'Credit Card',
  cash: 'Petty Cash',
  other: 'Other',
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return 'Never'
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const handleSearch = () => {
  router.get(`/${props.company.slug}/banking/accounts`, {
    search: searchTerm.value,
    include_inactive: includeInactive.value,
  }, { preserveState: true })
}

const handleCreate = () => {
  router.get(`/${props.company.slug}/banking/accounts/create`)
}

const handleView = (id: string) => {
  router.get(`/${props.company.slug}/banking/accounts/${id}`)
}

const handleEdit = (id: string) => {
  router.get(`/${props.company.slug}/banking/accounts/${id}/edit`)
}

const handleReconcile = (id: string) => {
  router.get(`/${props.company.slug}/banking/reconciliation/start`, { bank_account_id: id })
}

const handleDelete = (account: BankAccountRow) => {
  if (account.unreconciled_count > 0) {
    alert('Cannot delete account with unreconciled transactions.')
    return
  }
  if (!confirm(`Delete "${account.account_name}"?`)) return
  router.delete(`/${props.company.slug}/banking/accounts/${account.id}`)
}

const totalBalance = computed(() => {
  return props.bankAccounts.data.reduce((sum, acc) => sum + acc.current_balance, 0)
})
</script>

<template>
  <Head title="Bank Accounts" />

  <PageShell
    title="Bank Accounts"
    :breadcrumbs="breadcrumbs"
    :icon="Landmark"
  >
    <template #actions>
      <Button @click="handleCreate">
        <PlusCircle class="mr-2 h-4 w-4" />
        Add Bank Account
      </Button>
    </template>

    <!-- Summary Card -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted-foreground">Total Balance ({{ company.base_currency }})</p>
            <p class="text-2xl font-bold">{{ formatCurrency(totalBalance, company.base_currency) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-muted-foreground">Active Accounts</p>
            <p class="text-2xl font-bold">{{ bankAccounts.data.filter(a => a.is_active).length }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-4 items-center">
      <div class="relative flex-1 max-w-sm">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input
          v-model="searchTerm"
          placeholder="Search accounts..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <div class="flex items-center gap-2">
        <Checkbox
          id="include-inactive"
          :checked="includeInactive"
          @update:checked="(val) => { includeInactive = val; handleSearch() }"
        />
        <Label for="include-inactive" class="text-sm cursor-pointer">Show inactive</Label>
      </div>
    </div>

    <!-- Accounts List -->
    <div class="space-y-4">
      <div v-if="bankAccounts.data.length === 0" class="text-center py-12 text-muted-foreground bg-muted/20 rounded-lg border border-dashed">
        <Landmark class="mx-auto h-12 w-12 mb-4 opacity-50" />
        <p class="text-lg font-medium">No bank accounts found</p>
        <p class="text-sm mb-4">Add your first bank account to start tracking transactions</p>
        <Button @click="handleCreate">
          <PlusCircle class="mr-2 h-4 w-4" />
          Add Bank Account
        </Button>
      </div>

      <Card
        v-for="account in bankAccounts.data"
        :key="account.id"
        class="hover:shadow-md transition-shadow cursor-pointer"
        @click="handleView(account.id)"
      >
        <CardContent class="pt-6">
          <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
              <div class="p-3 rounded-full bg-primary/10">
                <component :is="accountTypeIcons[account.account_type]" class="h-6 w-6 text-primary" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="font-semibold text-lg">{{ account.account_name }}</h3>
                  <Badge v-if="account.is_primary" variant="secondary">Primary</Badge>
                  <Badge v-if="!account.is_active" variant="outline">Inactive</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                  {{ accountTypeLabels[account.account_type] }} &bull; {{ account.account_number }}
                </p>
                <p v-if="account.bank" class="text-sm text-muted-foreground">
                  {{ account.bank.name }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="font-bold text-lg">{{ formatCurrency(account.current_balance, account.currency) }}</p>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <span v-if="account.unreconciled_count > 0" class="text-amber-600">
                    {{ account.unreconciled_count }} unreconciled
                  </span>
                  <span v-else class="text-green-600">
                    All reconciled
                  </span>
                </div>
                <p class="text-xs text-muted-foreground">
                  Last reconciled: {{ formatDate(account.last_reconciled_date) }}
                </p>
              </div>

              <DropdownMenu>
                <DropdownMenuTrigger as-child @click.stop>
                  <Button variant="ghost" size="icon">
                    <MoreHorizontal class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click.stop="handleView(account.id)">
                    <Eye class="mr-2 h-4 w-4" />
                    View Details
                  </DropdownMenuItem>
                  <DropdownMenuItem @click.stop="handleEdit(account.id)">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuItem @click.stop="handleReconcile(account.id)">
                    <RefreshCcw class="mr-2 h-4 w-4" />
                    Reconcile
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    @click.stop="handleDelete(account)"
                    class="text-destructive"
                  >
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Pagination -->
    <div v-if="bankAccounts.last_page > 1" class="mt-6 flex justify-center gap-2">
      <Button
        variant="outline"
        :disabled="bankAccounts.current_page === 1"
        @click="router.get(`/${company.slug}/banking/accounts`, { page: bankAccounts.current_page - 1, ...filters })"
      >
        Previous
      </Button>
      <span class="flex items-center px-4 text-sm text-muted-foreground">
        Page {{ bankAccounts.current_page }} of {{ bankAccounts.last_page }}
      </span>
      <Button
        variant="outline"
        :disabled="bankAccounts.current_page === bankAccounts.last_page"
        @click="router.get(`/${company.slug}/banking/accounts`, { page: bankAccounts.current_page + 1, ...filters })"
      >
        Next
      </Button>
    </div>
  </PageShell>
</template>
