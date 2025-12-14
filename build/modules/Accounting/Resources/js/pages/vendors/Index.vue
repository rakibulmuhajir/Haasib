<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Building2, Plus, Search, Eye, Pencil, Trash2 } from 'lucide-vue-next'

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
  { key: 'currency', label: 'Currency' },
  { key: 'actions', label: 'Actions' },
]

const tableData = computed(() =>
  props.vendors.data.map((v) => ({
    ...v, // Keep original data for actions
    id: v.id,
    vendor_number: v.vendor_number,
    name: v.name,
    currency: v.base_currency,
    _original: v, // Store original data reference
  }))
)

const viewVendor = (id: string) => {
  router.get(`/${props.company.slug}/vendors/${id}`)
}

const editVendor = (id: string) => {
  router.get(`/${props.company.slug}/vendors/${id}/edit`)
}

const deleteVendor = (id: string) => {
  if (confirm('Are you sure you want to delete this vendor?')) {
    router.delete(`/${props.company.slug}/vendors/${id}`)
  }
}

const handleRowClick = (row: any) => {
  viewVendor(row.id)
}

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
        clickable
        hoverable
        @row-click="handleRowClick"
      >
        <template #vendor_number="{ row }">
          <button
            class="font-medium text-primary hover:text-primary/80 transition-colors"
            @click.stop="viewVendor(row.id)"
          >
            {{ row.vendor_number }}
          </button>
        </template>

        <template #name="{ row }">
          <button
            class="font-medium text-primary hover:text-primary/80 transition-colors"
            @click.stop="viewVendor(row.id)"
          >
            {{ row.name }}
          </button>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8"
              @click.stop="viewVendor(row.id)"
              title="View"
            >
              <Eye class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8"
              @click.stop="editVendor(row.id)"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8 text-destructive hover:text-destructive"
              @click.stop="deleteVendor(row.id)"
              title="Delete"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </template>

        <!-- Mobile Card Template -->
        <template #mobile-card="{ row }">
          <div class="p-4 space-y-3 border-b">
            <div class="flex justify-between items-start">
              <div>
                <button
                  class="font-semibold text-primary hover:text-primary/80 transition-colors"
                  @click.stop="viewVendor(row.id)"
                >
                  {{ row.vendor_number }}
                </button>
                <div class="text-sm text-muted-foreground mt-1">
                  <button
                    class="font-medium text-primary hover:text-primary/80 transition-colors"
                    @click.stop="viewVendor(row.id)"
                  >
                    {{ row.name }}
                  </button>
                </div>
                <div v-if="row.email" class="text-sm text-muted-foreground">{{ row.email }}</div>
                <div v-if="row.phone" class="text-sm text-muted-foreground">{{ row.phone }}</div>
              </div>
              <div class="text-right">
                <div class="text-sm font-medium">{{ row.currency }}</div>
                <div v-if="!row.is_active" class="text-xs text-red-500">Inactive</div>
              </div>
            </div>
            <div class="flex gap-2 pt-2">
              <Button size="sm" @click.stop="viewVendor(row.id)">View</Button>
              <Button size="sm" variant="outline" @click.stop="editVendor(row.id)">Edit</Button>
              <Button size="sm" variant="destructive" @click.stop="deleteVendor(row.id)">Delete</Button>
            </div>
          </div>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
