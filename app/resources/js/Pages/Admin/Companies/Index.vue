<script setup lang="ts">
import { Head, Link, router, onUnmounted, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import DataTablePro from '@/Components/DataTablePro.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { http } from '@/lib/http'

interface Company {
  id: number
  name: string
  slug: string
  base_currency: string
  language: string
  locale: string
  is_active: boolean
  created_at: string
  updated_at: string
}

const props = defineProps({
  companies: {
    type: Object,
    default: () => ({
      data: [],
      current_page: 1,
      per_page: 10,
      total: 0,
      loading: false
    })
  },
  filters: {
    type: Object,
    default: () => ({})
  },
})

const confirm = useConfirm()
const toast = useToast()
const { setActions } = usePageActions()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Companies', url: '/admin/companies', icon: 'companies' },
])

// DataTablePro columns definition
const columns = [
  { field: 'name', header: 'Company Name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'min-width: 250px' },
  { field: 'slug', header: 'Slug', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 150px' },
  { field: 'base_currency', header: 'Currency', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'language', header: 'Language', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'locale', header: 'Locale', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'status', header: 'Status', filter: { type: 'select', options: [{label:'Active', value:'active'},{label:'Inactive', value:'inactive'}] }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 200px; text-align: center' },
]

// Use the useDataTable composable
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'admin.companies.index',
  filterLookups: {
    status: {
      options: [{label:'Active', value:'active'},{label:'Inactive', value:'inactive'}],
      labelField: 'label',
      valueField: 'value'
    }
  }
})

// Search functionality
const searchQuery = ref('')

// Computed properties for selection states
const hasSelected = computed(() => table.selectedRows.value.length > 0)
const hasInactive = computed(() => table.selectedRows.value.some((c: Company) => !c.is_active))
const hasActive = computed(() => table.selectedRows.value.some((c: Company) => c.is_active))

// Bulk operations
async function bulkActivate() {
  confirm.require({
    message: `Are you sure you want to activate ${table.selectedRows.value.length} selected compan${table.selectedRows.value.length > 1 ? 'ies' : 'y'}?`,
    header: 'Confirm Activate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, activate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          table.selectedRows.value.map((company: Company) => 
            http.patch(`/web/companies/${company.slug}/activate`)
          )
        )
        toast.add({ 
          severity: 'success', 
          summary: 'Success', 
          detail: `${table.selectedRows.value.length} compan${table.selectedRows.value.length > 1 ? 'ies have' : 'y has'} been activated`,
          life: 3000 
        })
        table.selectedRows.value = []
        table.fetchData()
      } catch (e) {
        toast.add({ 
          severity: 'error', 
          summary: 'Error', 
          detail: 'Failed to activate companies',
          life: 3000 
        })
      }
    }
  })
}

async function bulkDeactivate() {
  confirm.require({
    message: `Are you sure you want to deactivate ${table.selectedRows.value.length} selected compan${table.selectedRows.value.length > 1 ? 'ies' : 'y'}? Users will not be able to access these companies.`,
    header: 'Confirm Deactivate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, deactivate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          table.selectedRows.value.map((company: Company) => 
            http.patch(`/web/companies/${company.slug}/deactivate`)
          )
        )
        toast.add({ 
          severity: 'success', 
          summary: 'Success', 
          detail: `${table.selectedRows.value.length} compan${table.selectedRows.value.length > 1 ? 'ies have' : 'y has'} been deactivated`,
          life: 3000 
        })
        table.selectedRows.value = []
        table.fetchData()
      } catch (e) {
        toast.add({ 
          severity: 'error', 
          summary: 'Error', 
          detail: 'Failed to deactivate companies',
          life: 3000 
        })
      }
    }
  })
}

// Update actions based on selection
function updateActions() {
  const actions = [
    { key: 'create', label: 'Create Company', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('admin.companies.create')) },
  ]
  
  if (hasSelected.value) {
    if (hasActive.value) {
      actions.push({
        key: 'deactivate', 
        label: `Deactivate Selected (${table.selectedRows.value.length})`, 
        icon: 'pi pi-ban', 
        severity: 'warning', 
        click: bulkDeactivate
      })
    }
    if (hasInactive.value) {
      actions.push({
        key: 'activate', 
        label: `Activate Selected (${table.selectedRows.value.length})`, 
        icon: 'pi pi-check', 
        severity: 'success', 
        click: bulkActivate
      })
    }
  }
  
  setActions(actions)
}

// Watch for selection changes
watch([table.selectedRows, table.items], () => {
  updateActions()
})

// Search handler
const handleSearch = () => {
  table.filterForm.search = searchQuery.value
  table.fetchData()
}

// Toggle company status
const toggleCompanyStatus = async (company: Company, activate: boolean) => {
  const action = activate ? 'activate' : 'deactivate'
  const message = activate 
    ? `Activate ${company.name}? Users will be able to access this company.`
    : `Deactivate ${company.name}? Users will not be able to access this company.`
  
  if (confirm(message)) {
    try {
      await http.patch(`/web/companies/${company.slug}/${action}`)
      table.fetchData()
    } catch (error) {
      console.error(`Failed to ${action} company:`, error)
    }
  }
}
</script>

<template>
  <Head title="Companies" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="space-y-6">
      <PageHeader
        title="Companies"
        subtitle="Manage all companies in the system"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="searchQuery"
              placeholder="Search companies by name or slug..."
              class="w-96"
              @keyup.enter="handleSearch"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Companies Table -->
      <Card>
        <template #content>
          <!-- Active Filters Chips -->
          <div v-if="table.activeFilters.value.length" class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-xs text-gray-500">Filters:</span>
            <span
              v-for="f in table.activeFilters.value"
              :key="f.key"
              class="inline-flex items-center text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"
            >
              <span class="mr-1">{{ f.display }}</span>
              <button
                type="button"
                class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                @click="table.clearTableFilterField(table.tableFilters.value, f.field)"
                aria-label="Clear filter"
              >
                ×
              </button>
            </span>
            <Button label="Clear all" size="small" text @click="table.clearFilters()" />
          </div>
          
          <DataTablePro
            :value="companies?.data || []"
            :loading="companies?.loading || false"
            :paginator="true"
            :rows="companies?.per_page || 10"
            :totalRecords="companies?.total || 0"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            :virtualScroll="companies.total > 200"
            scrollHeight="500px"
            responsiveLayout="stack"
            breakpoint="960px"
            v-model:filters="table.tableFilters.value"
            v-model:selection="table.selectedRows.value"
            selectionMode="multiple"
            dataKey="id"
            :showSelectionColumn="true"
            @page="table.onPage"
            @sort="table.onSort"
            @filter="table.onFilter"
          >
            <template #cell-name="{ data }">
              <div class="flex items-center gap-2">
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">{{ data.name }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">{{ data.slug }}</div>
                </div>
                <Tag 
                  v-if="!data.is_active" 
                  severity="danger" 
                  value="Inactive" 
                  size="small"
                />
              </div>
            </template>

            <template #cell-slug="{ data }">
              <span class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ data.slug }}</span>
            </template>

            <template #cell-base_currency="{ data }">
              <span class="font-medium">{{ data.base_currency }}</span>
            </template>

            <template #cell-language="{ data }">
              <span class="text-sm">{{ data.language }}</span>
            </template>

            <template #cell-locale="{ data }">
              <span class="text-sm">{{ data.locale }}</span>
            </template>

            <template #cell-status="{ data }">
              <Tag 
                :severity="data.is_active ? 'success' : 'danger'"
                :value="data.is_active ? 'Active' : 'Inactive'"
                size="small"
              />
            </template>

            <template #cell-actions="{ data }">
              <div class="flex items-center justify-center gap-2">
                <!-- View -->
                <Link :href="route('admin.companies.show', data.slug || data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
                    title="View company details"
                  >
                    <i class="fas fa-eye text-blue-600 dark:text-blue-400"></i>
                  </button>
                </Link>
                
                <!-- Edit -->
                <Link :href="route('admin.companies.edit', data.slug || data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Edit company"
                  >
                    <i class="fas fa-edit text-green-600 dark:text-green-400"></i>
                  </button>
                </Link>
                
                <!-- Users -->
                <Link :href="route('admin.companies.users', data.slug || data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Manage users"
                  >
                    <i class="fas fa-users text-purple-600 dark:text-purple-400"></i>
                  </button>
                </Link>
                
                <!-- Settings -->
                <Link :href="route('admin.companies.settings', data.slug || data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Company settings"
                  >
                    <i class="fas fa-cog text-orange-600 dark:text-orange-400"></i>
                  </button>
                </Link>
                
                <!-- Toggle Status -->
                <button
                  v-if="data.is_active"
                  @click="toggleCompanyStatus(data, false)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Deactivate company"
                >
                  <i class="fas fa-toggle-on text-red-600 dark:text-red-400"></i>
                </button>
                <button
                  v-else
                  @click="toggleCompanyStatus(data, true)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Activate company"
                >
                  <i class="fas fa-toggle-off text-green-600 dark:text-green-400"></i>
                </button>
              </div>
            </template>

            <template #empty>
              <div class="text-center py-8">
                <i class="fas fa-building text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p class="text-gray-500 dark:text-gray-400">No companies found</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your filters or create a new company.</p>
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>
                  Showing {{ companies.from }} to {{ companies.to }} of {{ companies.total }} companies
                </span>
                <span>
                  Selected: {{ table.selectedRows.value.length }}
                </span>
              </div>
            </template>
          </DataTablePro>
        </template>
      </Card>
    </div>

    </LayoutShell>
</template>