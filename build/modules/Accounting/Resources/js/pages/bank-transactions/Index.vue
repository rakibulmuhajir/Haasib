<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Landmark, Plus, Search } from 'lucide-vue-next'

interface AccountBalance { id: string; code: string; name: string; subtype: string; balance: number }
interface TxnLine { account_name: string | null; debit: number; credit: number }
interface BankTransaction {
  id: string
  date: string
  transaction_number: string
  transaction_type: string
  description: string | null
  amount: number
  lines: TxnLine[]
  // As-of-this-row balance across the whole ledger for the account currently
  // filtered on -- null when no single account is selected (see
  // BankTransactionController::index for why mixing accounts has no one balance).
  running_balance: number | null
}

interface PaginatedTransactions {
  data: BankTransaction[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  transactions: PaginatedTransactions
  accounts: AccountBalance[]
  currency: string
  filters: {
    account_id: string
    date_from: string
    date_to: string
    search: string
  }
}>()

const { companySlug } = useCompanyRoute()

const search = ref(props.filters.search)
const accountId = ref(props.filters.account_id || 'all')
const dateFrom = ref(props.filters.date_from)
const dateTo = ref(props.filters.date_to)
const fetching = ref(false)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Bank Transactions', href: `/${companySlug.value}/banking/transactions` },
])

const query = (page?: number) => ({
  search: search.value,
  account_id: accountId.value === 'all' ? '' : accountId.value,
  date_from: dateFrom.value,
  date_to: dateTo.value,
  page,
})

const go = (page?: number) => {
  fetching.value = true
  router.get(`/${companySlug.value}/banking/transactions`, query(page), {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => (fetching.value = false),
  })
}

const handleFilter = () => go()
const handlePage = (page: number) => go(page)

const showRunningBalance = computed(() => accountId.value !== 'all')

const columns = computed(() => [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'transaction_number', label: 'Reference', kind: 'text' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
  ...(showRunningBalance.value ? [{ key: 'running_balance', label: 'Balance', kind: 'amount' as const }] : []),
])

const tableData = computed(() => props.transactions.data.map((t) => ({
  id: t.id,
  date: t.date,
  transaction_number: t.transaction_number,
  description: t.description ?? '—',
  amount: t.amount,
  running_balance: t.running_balance,
  _raw: t,
})))

const goToJournal = (row: any) => router.get(`/${companySlug.value}/journals/${row.id}`)
</script>

<template>
  <Head title="Bank Transactions" />
  <PageShell title="Bank Transactions" description="Manual deposits, withdrawals, transfers and bank charges." :icon="Landmark" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button as-child>
        <Link :href="`/${companySlug}/banking/transactions/create`"><Plus class="mr-2 h-4 w-4" />New Transaction</Link>
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card v-for="account in accounts" :key="account.id" class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>{{ account.code }} {{ account.name }}</CardDescription>
          <CardTitle class="text-xl"><MoneyText :amount="account.balance" :currency="currency" /></CardTitle>
        </CardHeader>
      </Card>
    </div>

    <div class="flex flex-col gap-4 md:flex-row">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" placeholder="Search reference or description..." class="pl-10" @keyup.enter="handleFilter" />
      </div>
      <Select v-model="accountId" @update:modelValue="handleFilter">
        <SelectTrigger class="w-[220px]">
          <SelectValue placeholder="All Accounts" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Accounts</SelectItem>
          <SelectItem v-for="account in accounts" :key="account.id" :value="account.id">
            {{ account.code }} — {{ account.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <Input v-model="dateFrom" type="date" class="w-[160px]" @change="handleFilter" />
      <Input v-model="dateTo" type="date" class="w-[160px]" @change="handleFilter" />
    </div>

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Transactions</CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <LedgerRegister
          :data="tableData"
          :columns="columns"
          :pagination="transactions"
          :loading="fetching"
          clickable
          @row-click="goToJournal"
          @page-change="handlePage"
        >
          <template #empty>
            <EmptyState title="No bank transactions yet" description="Record a deposit, withdrawal, transfer or bank charge." />
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
