<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  FolderTree,
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
}

interface Parent {
  id: string
  name: string
  code: string
}

interface CategoryRow {
  id: string
  code: string
  name: string
  description: string | null
  parent: Parent | null
  is_active: boolean
  sort_order: number
  items_count: number
}

interface PaginatedCategories {
  data: CategoryRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  categories: PaginatedCategories
  filters: {
    search: string
    include_inactive: boolean
  }
}>()

const search = ref(props.filters.search)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Item Categories', href: `/${props.company.slug}/item-categories` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/item-categories`,
    { search: search.value },
    { preserveState: true }
  )
}

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'parent', label: 'Parent' },
  { key: 'items_count', label: 'Items' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.categories.data.map((category) => ({
    id: category.id,
    code: category.code,
    name: category.name,
    parent: category.parent?.name ?? '-',
    items_count: category.items_count,
    status: category.is_active ? 'Active' : 'Inactive',
    _raw: category,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/item-categories/${row.id}`)
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this category?')) {
    router.delete(`/${props.company.slug}/item-categories/${id}`)
  }
}
</script>

<template>
  <Head title="Item Categories" />

  <PageShell
    title="Item Categories"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/item-categories/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Category
      </Button>
    </template>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search categories..."
          class="pl-9"
          @keyup.enter="handleSearch"
        />
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="categories.data.length === 0"
      title="No categories yet"
      description="Create categories to organize your items."
      :icon="FolderTree"
    >
      <Button @click="router.get(`/${company.slug}/item-categories/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Category
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: categories.current_page,
        lastPage: categories.last_page,
        perPage: categories.per_page,
        total: categories.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-status="{ row }">
        <Badge :variant="row._raw.is_active ? 'success' : 'secondary'">
          {{ row.status }}
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
            <DropdownMenuItem @click="router.get(`/${company.slug}/item-categories/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/item-categories/${row.id}/edit`)">
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
