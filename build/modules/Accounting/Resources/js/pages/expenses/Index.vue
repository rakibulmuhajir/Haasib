<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Plus, Search } from 'lucide-vue-next'

interface Expense {
  id: string
  date: string
  transaction_number: string
  description: string | null
  amount: number
  expense_account: string | null
  paid_from: string | null
}

interface PaginatedExpenses {
  data: Expense[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  expenses: PaginatedExpenses
  filterAccounts: Array<{ id: string; code: string; name: string }>
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
  { title: 'Expenses', href: `/${companySlug.value}/expenses` },
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
  router.get(`/${companySlug.value}/expenses`, query(page), {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => (fetching.value = false),
  })
}

const handleFilter = () => go()
const handlePage = (page: number) => go(page)

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'expense_account', label: 'Account', kind: 'text' as const },
  { key: 'paid_from', label: 'Paid From', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
]

const tableData = computed(() => props.expenses.data.map((e) => ({ ...e, id: e.id })))

const goToJournal = (row: any) => router.get(`/${companySlug.value}/journals/${row.id}`)
</script>

<template>
  <Head title="Expenses" />
  <PageShell title="Expenses" description="Standalone expense entries, outside the Daily Close." :icon="ReceiptText" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button as-child>
        <Link :href="`/${companySlug}/expenses/create`"><Plus class="mr-2 h-4 w-4" />New Expense</Link>
      </Button>
    </template>

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
          <SelectItem v-for="account in filterAccounts" :key="account.id" :value="account.id">
            {{ account.code }} — {{ account.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <Input v-model="dateFrom" type="date" class="w-[160px]" @change="handleFilter" />
      <Input v-model="dateTo" type="date" class="w-[160px]" @change="handleFilter" />
    </div>

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Expenses</CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <LedgerRegister
          :data="tableData"
          :columns="columns"
          :pagination="expenses"
          :loading="fetching"
          clickable
          @row-click="goToJournal"
          @page-change="handlePage"
        >
          <template #empty>
            <EmptyState title="No expenses yet" description="Record an expense paid from cash or bank." />
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
