<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Landmark, Plus } from 'lucide-vue-next'

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
}

const props = defineProps<{
  transactions: BankTransaction[]
  accounts: AccountBalance[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Bank Transactions', href: `/${companySlug.value}/banking/transactions` },
])

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'transaction_number', label: 'Reference', kind: 'text' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
]

const tableData = computed(() => props.transactions.map((t) => ({
  id: t.id, date: t.date, transaction_number: t.transaction_number, description: t.description ?? '—', amount: t.amount, _raw: t,
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

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Recent Transactions</CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <LedgerRegister :data="tableData" :columns="columns" clickable @row-click="goToJournal">
          <template #empty>
            <EmptyState title="No bank transactions yet" description="Record a deposit, withdrawal, transfer or bank charge." />
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
