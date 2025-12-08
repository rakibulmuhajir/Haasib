<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { FileText, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface JournalEntry {
  id: string
  line_number: number
  description: string | null
  debit_amount: number
  credit_amount: number
  account: {
    code: string
    name: string
  }
}

interface JournalRef {
  id: string
  transaction_number: string
  transaction_date: string
  posting_date: string
  description: string | null
  status: string
  total_debit: number
  total_credit: number
  journal_entries: JournalEntry[]
}

const props = defineProps<{
  company: CompanyRef
  journal: JournalRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Journals', href: `/${props.company.slug}/journals` },
  { title: props.journal.transaction_number, href: `/${props.company.slug}/journals/${props.journal.id}` },
]

const money = (val: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: props.company.base_currency || 'USD' }).format(val ?? 0)

const badgeVariant = (val: string) => {
  if (val === 'draft') return 'secondary'
  if (val === 'posted') return 'success'
  if (val === 'void') return 'outline'
  if (val === 'reversed') return 'secondary'
  return 'secondary'
}

const columns = [
  { key: 'line_number', label: '#' },
  { key: 'account', label: 'Account' },
  { key: 'description', label: 'Description' },
  { key: 'debit', label: 'Debit' },
  { key: 'credit', label: 'Credit' },
]

const tableData = computed(() =>
  props.journal.journal_entries.map((e) => ({
    line_number: e.line_number,
    account: `${e.account.code} — ${e.account.name}`,
    description: e.description ?? '—',
    debit: money(Number(e.debit_amount)),
    credit: money(Number(e.credit_amount)),
  }))
)
</script>

<template>
  <Head :title="`Journal ${journal.transaction_number}`" />
  <PageShell
    :title="`Journal ${journal.transaction_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <Button variant="outline" @click="() => window.history.back()">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card>
        <CardHeader>
          <CardTitle>Dates</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span>Transaction</span>
            <span>{{ new Date(journal.transaction_date).toLocaleDateString() }}</span>
          </div>
          <div class="flex justify-between">
            <span>Posting</span>
            <span>{{ new Date(journal.posting_date).toLocaleDateString() }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Status</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <Badge :variant="badgeVariant(journal.status)">{{ journal.status }}</Badge>
          <div v-if="journal.description" class="text-muted-foreground">
            {{ journal.description }}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Totals</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span>Debit</span>
            <span>{{ money(Number(journal.total_debit)) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Credit</span>
            <span>{{ money(Number(journal.total_credit)) }}</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="mt-6">
      <DataTable :columns="columns" :data="tableData" />
    </div>
  </PageShell>
</template>
