<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Package,
  Plus,
  Eye,
  Pencil,
  Trash2,
  MoreHorizontal,
  Search,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Category {
  id: string
  name: string
  code: string
}

interface ItemRow {
  id: string
  sku: string
  name: string
  item_type: string
  category: Category | null
  selling_price: number
  cost_price: number
  currency: string
  is_active: boolean
  total_quantity: number
  total_available: number
}

interface PaginatedItems {
  data: ItemRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  items: PaginatedItems
  categories: Category[]
  filters: {
    search: string
    category_id: string
    item_type: string
    include_inactive: boolean
    sort_by: string
    sort_dir: string
  }
}>()

const search = ref(props.filters.search)
const categoryId = ref(props.filters.category_id || 'all')
const itemType = ref(props.filters.item_type || 'all')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Items', href: `/${props.company.slug}/items` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/items`,
    {
      search: search.value,
      category_id: categoryId.value === 'all' ? '' : categoryId.value,
      item_type: itemType.value === 'all' ? '' : itemType.value,
    },
    { preserveState: true }
  )
}

const getTypeBadgeVariant = (type: string) => {
  switch (type) {
    case 'product':
      return 'default'
    case 'service':
      return 'secondary'
    case 'non_inventory':
      return 'outline'
    case 'bundle':
      return 'default'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency || 'USD',
  }).format(amount)
}

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const columns = [
  { key: 'sku', label: 'SKU' },
  { key: 'name', label: 'Name' },
  { key: 'item_type', label: 'Type' },
  { key: 'category', label: 'Category' },
  { key: 'selling_price', label: 'Price' },
  { key: 'total_quantity', label: 'On Hand' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.items.data.map((item) => ({
    id: item.id,
    sku: item.sku,
    name: item.name,
    item_type: item.item_type,
    category: item.category?.name ?? '-',
    selling_price: formatCurrency(item.selling_price, item.currency),
    total_quantity: formatQuantity(item.total_quantity),
    _actions: item.id,
    _raw: item,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/items/${row.id}`)
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this item?')) {
    router.delete(`/${props.company.slug}/items/${id}`)
  }
}
</script>

<template>
  <Head title="Items" />

  <PageShell
    title="Items"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/items/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Item
      </Button>
    </template>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search items..."
          class="pl-9"
          @keyup.enter="handleSearch"
        />
      </div>

      <Select v-model="categoryId" @update:model-value="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Categories" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Categories</SelectItem>
          <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="itemType" @update:model-value="handleSearch">
        <SelectTrigger class="w-[150px]">
          <SelectValue placeholder="All Types" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Types</SelectItem>
          <SelectItem value="product">Product</SelectItem>
          <SelectItem value="service">Service</SelectItem>
          <SelectItem value="non_inventory">Non-Inventory</SelectItem>
          <SelectItem value="bundle">Bundle</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="items.data.length === 0"
      title="No items yet"
      description="Create your first item to start tracking inventory."
      :icon="Package"
    >
      <Button @click="router.get(`/${company.slug}/items/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Item
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: items.current_page,
        lastPage: items.last_page,
        perPage: items.per_page,
        total: items.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-item_type="{ row }">
        <Badge :variant="getTypeBadgeVariant(row._raw.item_type)">
          {{ row._raw.item_type }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/items/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/items/${row.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem class="text-destructive" @click="handleDelete(row.id)">
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
    </DataTable>
  </PageShell>
</template>
