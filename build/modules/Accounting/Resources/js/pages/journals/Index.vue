<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { FileText, Plus, Search } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface JournalRow {
  id: string
  transaction_number: string
  transaction_date: string
  posting_date: string
  description: string | null
  status: string
  total_debit: number
  total_credit: number
  journal_entries_count: number
}

interface PaginatedJournals {
  data: JournalRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  journals: PaginatedJournals
  filters: {
    search?: string
    status?: string
  }
}>()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'all')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Journals', href: `/${props.company.slug}/journals` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/journals`,
    {
      search: search.value,
      status: status.value === 'all' ? '' : status.value,
    },
    { preserveState: true }
  )
}

const formatCurrency = (amount: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.company.base_currency || 'USD',
  }).format(amount)

const badgeVariant = (val: string) => {
  if (val === 'draft') return 'secondary'
  if (val === 'posted') return 'success'
  if (val === 'void') return 'outline'
  if (val === 'reversed') return 'secondary'
  return 'secondary'
}

const columns = [
  { key: 'transaction_number', label: 'Journal #' },
  { key: 'transaction_date', label: 'Date' },
  { key: 'status', label: 'Status' },
  { key: 'total_debit', label: 'Debit' },
  { key: 'total_credit', label: 'Credit' },
  { key: 'journal_entries_count', label: 'Lines' },
]

const tableData = computed(() =>
  props.journals.data.map((j) => ({
    id: j.id,
    transaction_number: j.transaction_number,
    transaction_date: new Date(j.transaction_date).toLocaleDateString(),
    status: j.status,
    total_debit: formatCurrency(j.total_debit),
    total_credit: formatCurrency(j.total_credit),
    journal_entries_count: j.journal_entries_count,
  }))
)
</script>

<template>
  <Head title="Journals" />
  <PageShell
    title="Journals"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/journals/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Journal
      </Button>
    </template>

    <div class="flex flex-col gap-4 md:flex-row mb-6">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search journal # or description..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="posted">Posted</SelectItem>
          <SelectItem value="void">Void</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <DataTable
      :columns="columns"
      :data="tableData"
      :pagination="journals"
      @row-click="(row: any) => router.get(`/${company.slug}/journals/${row.id}`)"
    >
      <template #status="{ value }">
        <Badge :variant="badgeVariant(value)">{{ value }}</Badge>
      </template>
    </DataTable>

    <EmptyState
      v-if="!journals.data.length"
      icon="FileText"
      title="No journals yet"
      description="Create your first manual journal entry."
      :action-label="'New Journal'"
      @action="router.get(`/${company.slug}/journals/create`)"
    />
  </PageShell>
</template>
