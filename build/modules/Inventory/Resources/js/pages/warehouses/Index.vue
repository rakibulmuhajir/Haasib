<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Warehouse,
  Plus,
  Eye,
  Pencil,
  Trash2,
  MoreHorizontal,
  Search,
  Star,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface LinkedItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface WarehouseRow {
  id: string
  code: string
  name: string
  warehouse_type: string
  city: string | null
  is_primary: boolean
  is_active: boolean
  is_deleted: boolean
  item_count: number
  total_units: number
  capacity?: number | null
  linked_item?: LinkedItemRef | null
}

interface PaginatedWarehouses {
  data: WarehouseRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  warehouses: PaginatedWarehouses
  filters: {
    search: string
    include_inactive: boolean
    include_deleted: boolean
  }
}>()

const search = ref(props.filters.search)
const includeDeleted = ref(props.filters.include_deleted)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Warehouses', href: `/${props.company.slug}/warehouses` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/warehouses`,
    {
      search: search.value,
      include_deleted: includeDeleted.value ? '1' : '0',
    },
    { preserveState: true }
  )
}

const handleFilterChange = () => {
  router.get(
    `/${props.company.slug}/warehouses`,
    {
      search: search.value,
      include_deleted: includeDeleted.value ? '1' : '0',
    },
    { preserveState: true, replace: true }
  )
}

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'city', label: 'Location' },
  { key: 'item_count', label: 'Items' },
  { key: 'total_units', label: 'Total Units' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.warehouses.data.map((warehouse) => ({
    id: warehouse.id,
    code: warehouse.code,
    name: warehouse.name,
    type: warehouse.warehouse_type === 'tank' ? 'Fuel Tank' : 'Standard',
    city: warehouse.city ?? '-',
    item_count: warehouse.item_count,
    total_units: formatQuantity(warehouse.total_units),
    status: warehouse.is_deleted ? 'Deleted' : warehouse.is_active ? 'Active' : 'Inactive',
    _raw: warehouse,
    _rowClass: warehouse.is_deleted ? 'opacity-50 line-through' : '',
  }))
})

const handleRowClick = (row: any) => {
  // Don't allow clicking into deleted warehouses
  if (row._raw.is_deleted) return
  router.get(`/${props.company.slug}/warehouses/${row.id}`)
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this warehouse?')) {
    router.delete(`/${props.company.slug}/warehouses/${id}`)
  }
}
</script>

<template>
  <Head title="Warehouses" />

  <PageShell
    title="Warehouses"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/warehouses/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Warehouse
      </Button>
    </template>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search warehouses..."
          class="pl-9"
          @keyup.enter="handleSearch"
        />
      </div>
      <div class="flex items-center space-x-2">
        <Checkbox
          id="include-deleted"
          v-model:checked="includeDeleted"
          @update:checked="handleFilterChange"
        />
        <Label for="include-deleted" class="text-sm font-normal cursor-pointer">
          Show deleted
        </Label>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="warehouses.data.length === 0"
      title="No warehouses yet"
      description="Create your first warehouse to start managing stock locations."
      :icon="Warehouse"
    >
      <Button @click="router.get(`/${company.slug}/warehouses/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Warehouse
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: warehouses.current_page,
        lastPage: warehouses.last_page,
        perPage: warehouses.per_page,
        total: warehouses.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span>{{ row.name }}</span>
          <Star v-if="row._raw.is_primary" class="h-4 w-4 text-yellow-500 fill-yellow-500" />
        </div>
      </template>

      <template #cell-status="{ row }">
        <Badge
          :variant="row._raw.is_deleted ? 'destructive' : row._raw.is_active ? 'success' : 'secondary'"
        >
          {{ row.status }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu v-if="!row._raw.is_deleted">
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/warehouses/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/warehouses/${row.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem class="text-destructive" @click="handleDelete(row.id)">
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <span v-else class="text-xs text-muted-foreground italic">Deleted</span>
      </template>
    </DataTable>
  </PageShell>
</template>
