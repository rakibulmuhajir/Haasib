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
import { Building2, Plus, Search } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRow {
  id: string
  vendor_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string
  is_active: boolean
}

interface PaginatedVendors {
  data: VendorRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  vendors: PaginatedVendors
  filters: {
    search?: string
    include_inactive?: boolean
  }
}>()

const search = ref(props.filters.search ?? '')
const includeInactive = ref(!!props.filters.include_inactive)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
]

const columns = [
  { key: 'vendor_number', label: 'Vendor #' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'currency', label: 'Currency' },
  { key: 'status', label: 'Status' },
]

const tableData = computed(() =>
  props.vendors.data.map((v) => ({
    id: v.id,
    vendor_number: v.vendor_number,
    name: v.name,
    email: v.email ?? '—',
    phone: v.phone ?? '—',
    currency: v.base_currency,
    status: v.is_active ? 'Active' : 'Inactive',
  }))
)

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/vendors`,
    {
      search: search.value,
      include_inactive: includeInactive.value ? 1 : '',
    },
    { preserveState: true }
  )
}
</script>

<template>
  <Head title="Vendors" />
  <PageShell
    title="Vendors"
    :breadcrumbs="breadcrumbs"
    :icon="Building2"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/vendors/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Vendor
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-3">
      <div class="relative md:col-span-2">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search vendors..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="includeInactive" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="Active only" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="false">Active only</SelectItem>
          <SelectItem :value="true">Include inactive</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="!vendors.data.length">
      <EmptyState
        title="No vendors yet"
        description="Create your first vendor to start tracking bills."
        cta-text="Create Vendor"
        @click="router.get(`/${company.slug}/vendors/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="vendors"
      >
        <template #status="{ value }">
          <Badge :variant="value === 'Active' ? 'success' : 'secondary'">{{ value }}</Badge>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
