<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import DataTablePro from '@/Components/DataTablePro.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { http, withIdempotency } from '@/lib/http'
import { usePageConfig, actionPresets } from '@/composables/usePageLayout'

interface User {
  id: number
  name: string
  email: string
  system_role: 'superadmin' | 'user'
  is_active: boolean
  created_at: string
  updated_at: string
}

const props = defineProps({
  users: Object,
  filters: Object,
})

const confirm = useConfirm()
const toast = useToast()
const { setActions, clearActions } = usePageActions()

// Search functionality
const searchQuery = ref('')

// Page configuration using helper
const { breadcrumbs, pageHeader } = usePageConfig({
  module: 'admin',
  entity: 'users',
  action: 'index',
  subtitle: 'Manage system users and their permissions',
  searchPlaceholder: 'Search users by name or email...',
  searchQuery
})

// DataTablePro columns definition
const columns = [
  { field: 'name', header: 'Name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'min-width: 200px' },
  { field: 'email', header: 'Email', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'min-width: 250px' },
  { field: 'system_role', header: 'Role', filter: { type: 'select', options: [{label:'Super Admin', value:'superadmin'},{label:'User', value:'user'}] }, style: 'width: 120px' },
  { field: 'is_active', header: 'Status', filter: { type: 'select', options: [{label:'Active', value:'active'},{label:'Inactive', value:'inactive'}] }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 200px; text-align: center' },
]

// Use the useDataTable composable
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'admin.users.index',
  filterLookups: {
    system_role: {
      options: [{label:'Super Admin', value:'superadmin'},{label:'User', value:'user'}],
      labelField: 'label',
      valueField: 'value'
    },
    is_active: {
      options: [{label:'Active', value:'active'},{label:'Inactive', value:'inactive'}],
      labelField: 'label',
      valueField: 'value'
    }
  }
})

// Computed properties for selection states
const hasSelected = computed(() => table.selectedRows.value.length > 0)
const hasInactive = computed(() => table.selectedRows.value.some((u: User) => !u.is_active))
const hasActive = computed(() => table.selectedRows.value.some((u: User) => u.is_active))

// Format date
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Batch operations
async function activateSelectedUsers() {
  if (!table.selectedRows.value.length) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'No users selected', life: 3000 })
    return
  }
  
  confirm.require({
    message: `Activate ${table.selectedRows.value.length} selected users?`,
    header: 'Confirm Activation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, activate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          table.selectedRows.value.map((user: User) => 
            http.post('/commands', {
              user: user.id
            }, { headers: withIdempotency({ 'X-Action': 'user.activate' }) })
          )
        )
        toast.add({
          severity: 'success',
          summary: 'Success',
          detail: `${table.selectedRows.value.length} users activated successfully`,
          life: 3000
        })
        table.selectedRows.value = []
        table.fetchData()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: e?.response?.data?.message || 'Failed to activate users',
          life: 3000
        })
      }
    }
  })
}

async function deactivateSelectedUsers() {
  if (!table.selectedRows.value.length) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'No users selected', life: 3000 })
    return
  }
  
  confirm.require({
    message: `Deactivate ${table.selectedRows.value.length} selected users?`,
    header: 'Confirm Deactivation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, deactivate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          table.selectedRows.value.map((user: User) => 
            http.post('/commands', {
              user: user.id
            }, { headers: withIdempotency({ 'X-Action': 'user.deactivate' }) })
          )
        )
        toast.add({
          severity: 'success',
          summary: 'Success',
          detail: `${table.selectedRows.value.length} users deactivated successfully`,
          life: 3000
        })
        table.selectedRows.value = []
        table.fetchData()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: e?.response?.data?.message || 'Failed to deactivate users',
          life: 3000
        })
      }
    }
  })
}

async function toggleUserStatus(user: User) {
  const action = user.is_active ? 'user.deactivate' : 'user.activate'
  const actionText = user.is_active ? 'deactivate' : 'activate'
  
  confirm.require({
    message: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} user ${user.name}?`,
    header: 'Confirm Action',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: `Yes, ${actionText}`,
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await http.post('/commands', {
          user: user.id
        }, { headers: withIdempotency({ 'X-Action': action }) })
        
        toast.add({
          severity: 'success',
          summary: 'Success',
          detail: `User ${actionText}d successfully`,
          life: 3000
        })
        table.fetchData()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: e?.response?.data?.message || `Failed to ${actionText} user`,
          life: 3000
        })
      }
    }
  })
}

// Update actions based on selection
function updateActions() {
  const actions = [
    actionPresets.create('Create User', 'admin.users.create', { icon: 'pi pi-user-plus' }),
  ]
  
  if (hasSelected.value) {
    if (hasActive.value) {
      actions.push({
        key: 'deactivate', 
        label: `Deactivate Selected (${table.selectedRows.value.length})`, 
        icon: 'pi pi-user-minus', 
        severity: 'warning', 
        click: deactivateSelectedUsers
      })
    }
    if (hasInactive.value) {
      actions.push({
        key: 'activate', 
        label: `Activate Selected (${table.selectedRows.value.length})`, 
        icon: 'pi pi-user-plus', 
        severity: 'success', 
        click: activateSelectedUsers
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

// Initialize actions on mount
updateActions()

// Clean up actions on unmount
onUnmounted(() => clearActions())
</script>

<template>
  <Head title="Users" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbs" />
        <!-- Page actions will be handled by PageActions component -->
      </div>
    </template>

    <div class="space-y-4">
      <PageHeader v-bind="pageHeader">
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="searchQuery"
              placeholder="Search users by name or email..."
              class="w-96"
              @keyup.enter="handleSearch"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Users Table -->
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
            :value="users.data"
            :loading="users.loading"
            :paginator="true"
            :rows="users.per_page"
            :totalRecords="users.total"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            :virtualScroll="users.total > 200"
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
              <div class="font-medium text-gray-900 dark:text-white">{{ data.name }}</div>
            </template>

            <template #cell-email="{ data }">
              <div class="text-sm">{{ data.email }}</div>
            </template>

            <template #cell-system_role="{ data }">
              <Badge
                v-if="data.system_role === 'superadmin'"
                severity="danger"
                value="Super Admin"
                size="small"
              />
              <Badge
                v-else
                severity="info"
                value="User"
                size="small"
              />
            </template>

            <template #cell-is_active="{ data }">
              <Badge
                :value="data.is_active ? 'Active' : 'Inactive'"
                :severity="data.is_active ? 'success' : 'danger'"
                size="small"
              />
            </template>

            <template #cell-actions="{ data }">
              <div class="flex items-center justify-center gap-2">
                <!-- View -->
                <Link :href="route('admin.users.show', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
                    title="View user details"
                  >
                    <i class="fas fa-eye text-blue-600 dark:text-blue-400"></i>
                  </button>
                </Link>
                
                <!-- Edit -->
                <Link :href="route('admin.users.edit', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Edit user"
                  >
                    <i class="fas fa-edit text-green-600 dark:text-green-400"></i>
                  </button>
                </Link>
                
                <!-- Permissions -->
                <Link :href="route('admin.users.permissions', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Manage permissions"
                  >
                    <i class="fas fa-key text-purple-600 dark:text-purple-400"></i>
                  </button>
                </Link>
                
                <!-- Activity Log -->
                <Link :href="route('admin.users.activity', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/20 transition-all duration-200 transform hover:scale-105"
                    title="View activity log"
                  >
                    <i class="fas fa-history text-orange-600 dark:text-orange-400"></i>
                  </button>
                </Link>
                
                <!-- Toggle Status -->
                <button
                  v-if="data.is_active"
                  @click="toggleUserStatus(data)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Deactivate user"
                >
                  <i class="fas fa-user-slash text-red-600 dark:text-red-400"></i>
                </button>
                <button
                  v-else
                  @click="toggleUserStatus(data)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Activate user"
                >
                  <i class="fas fa-user-check text-green-600 dark:text-green-400"></i>
                </button>
              </div>
            </template>

            <template #empty>
              <div class="text-center py-8">
                <i class="fas fa-users text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p class="text-gray-500 dark:text-gray-400">No users found</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your filters or create a new user.</p>
              </div>
            </template>

            <template #loading>
              <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p class="text-gray-500 dark:text-gray-400">Loading users...</p>
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>
                  Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users
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