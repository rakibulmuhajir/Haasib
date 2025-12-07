<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Layers, Plus, Search } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRow {
  id: string
  code: string
  name: string
  type: string
  subtype: string
  currency: string | null
  is_active: boolean
}

interface PaginatedAccounts {
  data: AccountRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  accounts: PaginatedAccounts
  filters: {
    type?: string
    search?: string
  }
}>()

const allTypesValue = '__all'
const search = ref(props.filters.search ?? '')
const typeFilter = ref(props.filters.type ?? allTypesValue)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Accounts', href: `/${props.company.slug}/accounts` },
]

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'subtype', label: 'Subtype' },
  { key: 'currency', label: 'Currency' },
  { key: 'status', label: 'Status' },
]

const tableData = computed(() =>
  props.accounts.data.map((acc) => ({
    id: acc.id,
    code: acc.code,
    name: acc.name,
    type: acc.type,
    subtype: acc.subtype,
    currency: acc.currency ?? props.company.base_currency,
    status: acc.is_active ? 'Active' : 'Inactive',
  }))
)

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/accounts`,
    {
      search: search.value,
      type: typeFilter.value === allTypesValue ? '' : typeFilter.value,
    },
    { preserveState: true }
  )
}

const typeOptions = [
  'asset',
  'liability',
  'equity',
  'revenue',
  'expense',
  'cogs',
  'other_income',
  'other_expense',
]
</script>

<template>
  <Head title="Accounts" />

  <PageShell
    :title="`Accounts`"
    :breadcrumbs="breadcrumbs"
    :icon="Layers"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/accounts/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Account
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-3">
      <div class="relative md:col-span-2">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search code or name..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="typeFilter" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All Types" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="allTypesValue">All Types</SelectItem>
          <SelectItem v-for="t in typeOptions" :key="t" :value="t">{{ t }}</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="!accounts.data.length">
      <EmptyState
        title="No accounts yet"
        description="Create your first account to start posting."
        cta-text="Create Account"
        @click="router.get(`/${company.slug}/accounts/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="accounts"
      >
        <template #status="{ value }">
          <Badge :variant="value === 'Active' ? 'success' : 'secondary'">{{ value }}</Badge>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
