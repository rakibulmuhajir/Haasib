<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Plus } from 'lucide-vue-next'

interface Expense {
  id: string
  date: string
  transaction_number: string
  description: string | null
  amount: number
  expense_account: string | null
  paid_from: string | null
}

const props = defineProps<{ expenses: Expense[]; currency: string }>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Expenses', href: `/${companySlug.value}/expenses` },
])

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'expense_account', label: 'Account', kind: 'text' as const },
  { key: 'paid_from', label: 'Paid From', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
]

const tableData = computed(() => props.expenses.map((e) => ({ ...e, id: e.id })))

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

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Recent Expenses</CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <LedgerRegister :data="tableData" :columns="columns" clickable @row-click="goToJournal">
          <template #empty>
            <EmptyState title="No expenses yet" description="Record an expense paid from cash or bank." />
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>
  </PageShell>
</template>
