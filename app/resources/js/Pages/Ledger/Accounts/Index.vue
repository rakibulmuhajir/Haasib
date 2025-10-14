<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, onUnmounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import TreeTable from 'primevue/treetable'
import Column from 'primevue/column'
import { usePageActions } from '@/composables/usePageActions'
import { useToast } from 'primevue/usetoast'
import { FilterMatchMode } from '@primevue/core/api'
import { useFormatting } from '@/composables/useFormatting'
import { useLedgerAccountsFilters } from '@/composables/useLedgerAccountsFilters'

interface Account {
  id: number
  code: string
  name: string
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
  normal_balance: 'debit' | 'credit'
  active: boolean
  system_account: boolean
  description?: string
  level: number
  journal_lines_count: number
  children?: Account[]
}

interface TreeNode {
  key: number
  data: Account
  children?: TreeNode[]
}

const page = usePage()
const { formatMoney } = useFormatting()
const { 
  filters, 
  typeOptions, 
  activeOptions, 
  activeFilters, 
  hasActiveFilters,
  applyFilters,
  clearFilter,
  clearFilters,
  debouncedSearch 
} = useLedgerAccountsFilters(page.props.initialFilters)

const toasty = useToast()
const { setActions, clearActions } = usePageActions()

// Permissions
const canView = computed(() => 
  page.props.auth.permissions?.['ledger.accounts.view'] ?? false
)
const canCreate = computed(() => 
  page.props.auth.permissions?.['ledger.accounts.create'] ?? false
)
const canEdit = computed(() => 
  page.props.auth.permissions?.['ledger.accounts.edit'] ?? false
)

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Ledger', url: '/ledger', icon: 'book' },
  { label: 'Chart of Accounts', url: '/ledger/accounts', icon: 'sitemap' },
])


// Filter and search
const filteredTreeData = computed(() => {
  const accounts = page.props.accounts as Account[] || []
  const filtered = accounts.filter(account => {
    const matchesType = !filters.value.type || account.type === filters.value.type
    const matchesActive = filters.value.active === '' || account.active === filters.value.active
    const matchesSearch = !filters.value.search || 
      account.name.toLowerCase().includes(filters.value.search.toLowerCase()) ||
      account.code.toLowerCase().includes(filters.value.search.toLowerCase())
    
    return matchesType && matchesActive && matchesSearch
  })
  return transformAccountsToTree(filtered)
})


// Get account type badge
const getTypeBadge = (type: string) => {
  const variants = {
    asset: 'info',
    liability: 'warning',
    equity: 'success',
    revenue: 'success',
    expense: 'danger'
  }
  
  return {
    severity: variants[type] || 'secondary',
    value: type.charAt(0).toUpperCase() + type.slice(1)
  }
}

// Get account status indicator
const getStatusIndicator = (active: boolean, systemAccount: boolean) => {
  if (!active) {
    return { class: 'text-gray-400', icon: 'x-circle', label: 'Inactive' }
  }
  if (systemAccount) {
    return { class: 'text-blue-500', icon: 'shield', label: 'System' }
  }
  return { class: 'text-green-500', icon: 'check-circle', label: 'Active' }
}

// Transform accounts for TreeTable
const transformAccountsToTree = (accounts: Account[]): TreeNode[] => {
  return accounts.map(account => ({
    key: account.id,
    data: {
      ...account,
      journal_lines_count: account.journal_lines_count || 0
    },
    children: account.children ? transformAccountsToTree(account.children) : []
  }))
}

// Calculate account balance (simplified for display)
const calculateAccountBalance = (account: Account) => {
  // This would typically come from the backend with actual balance calculations
  const balance = Math.random() * 100000 - 50000 // Mock balance for demo
  return {
    amount: balance,
    type: balance >= 0 ? account.normal_balance : (account.normal_balance === 'debit' ? 'credit' : 'debit')
  }
}

// Page Actions
setActions([
  { key: 'create', label: 'New Account', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('ledger.accounts.create')), disabled: () => !canCreate.value },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => router.visit(route('ledger.accounts.index')) },
])

onUnmounted(() => clearActions())
</script>

<template>
  <Head title="Chart of Accounts" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Chart of Accounts" />
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
      </div>
    </template>

    <div class="space-y-6">
      <PageHeader
        title="Chart of Accounts"
        subtitle="Browse and manage your company's chart of accounts"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="filters.search"
              placeholder="Search accounts..."
              class="w-64"
              @keyup.enter="debouncedSearch"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Filters Card -->
      <Card>
        <template #title>Filters</template>
        <template #content>
          <div class="flex flex-wrap gap-4">
            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 mb-3 w-full">
              <span class="text-xs text-gray-500 dark:text-gray-400">Active filters:</span>
              <span
                v-for="filter in activeFilters"
                :key="filter.key"
                class="inline-flex items-center text-xs bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded"
              >
                {{ filter.display }}
                <button
                  type="button"
                  class="ml-1 text-blue-500 hover:text-blue-700 dark:hover:text-blue-200"
                  @click="clearFilter(filter.field)"
                  aria-label="Clear filter"
                >
                  ×
                </button>
              </span>
              <Button label="Clear all" size="small" text @click="clearFilters" />
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Account Type
                </label>
                <Select
                  v-model="filters.type"
                  :options="typeOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                  placeholder="Select type"
                  />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Status
                </label>
                <Select
                  v-model="filters.active"
                  :options="activeOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                  placeholder="Select status"
                  />
              </div>
              
              <div class="flex items-end gap-2">
                <Button label="Apply Filters" @click="debouncedSearch" />
                <Button label="Clear" text @click="clearFilters" />
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Accounts Tree Table -->
      <Card>
        <template #content>
          <TreeTable 
            :value="filteredTreeData"
            :paginator="filteredTreeData.length > 20"
            :rows="20"
            :virtualScroll="filteredTreeData.length > 200"
            scrollHeight="500px"
            stripedRows
            responsiveLayout="scroll"
            sortMode="single"
            sortField="code"
            :sortOrder="1"
            :loading="!page.props.accounts"
          >
            <Column field="code" header="Code" expander style="width: 100px">
              <template #body="{ node }">
                <span class="font-mono text-sm font-medium">{{ node.data.code }}</span>
              </template>
            </Column>
            
            <Column field="name" header="Account Name" style="min-width: 300px">
              <template #body="{ node }">
                <div class="flex items-center gap-2">
                  <span 
                    class="text-sm font-medium"
                    :class="node.data.active ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
                  >
                    {{ node.data.name }}
                  </span>
                  <div 
                    v-tooltip.top="getStatusIndicator(node.data.active, node.data.system_account).label"
                    class="flex items-center"
                  >
                    <SvgIcon 
                      :name="getStatusIndicator(node.data.active, node.data.system_account).icon"
                      set="line"
                      class="w-3 h-3"
                      :class="getStatusIndicator(node.data.active, node.data.system_account).class"
                    />
                  </div>
                </div>
                <div v-if="node.data.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {{ node.data.description }}
                </div>
              </template>
            </Column>
            
            <Column field="type" header="Type" style="width: 120px">
              <template #body="{ node }">
                <Badge 
                  :severity="getTypeBadge(node.data.type).severity"
                  :value="getTypeBadge(node.data.type).value"
                  size="small"
                />
              </template>
            </Column>
            
            <Column field="normal_balance" header="Normal Balance" style="width: 120px">
              <template #body="{ node }">
                <span class="text-sm font-medium">
                  {{ node.data.normal_balance.charAt(0).toUpperCase() + node.data.normal_balance.slice(1) }}
                </span>
              </template>
            </Column>
            
            <Column field="journal_lines_count" header="Activity" style="width: 100px">
              <template #body="{ node }">
                <div class="text-center">
                  <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ node.data.journal_lines_count }}
                  </span>
                </div>
              </template>
            </Column>
            
            <Column field="balance" header="Balance" style="width: 150px">
              <template #body="{ node }">
                <div class="text-right">
                  <div 
                    class="text-sm font-medium"
                    :class="calculateAccountBalance(node.data).type === 'debit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                  >
                    {{ formatMoney(Math.abs(calculateAccountBalance(node.data).amount)) }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ calculateAccountBalance(node.data).type }}
                  </div>
                </div>
              </template>
            </Column>
            
            <Column field="actions" header="Actions" style="width: 180px">
              <template #body="{ node }">
                <div class="flex items-center justify-center gap-2">
                  <!-- View -->
                  <Link :href="route('ledger.accounts.show', node.data.id)">
                    <button
                      class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
                      title="View details"
                    >
                      <i class="fas fa-eye text-blue-600 dark:text-blue-400"></i>
                    </button>
                  </Link>
                  
                  <!-- Edit -->
                  <Link v-if="canEdit && !node.data.system_account" :href="route('ledger.accounts.edit', node.data.id)">
                    <button
                      class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                      title="Edit account"
                    >
                      <i class="fas fa-edit text-green-600 dark:text-green-400"></i>
                    </button>
                  </Link>
                  
                  <!-- View Journal -->
                  <Link :href="route('ledger.accounts.journal', node.data.id)">
                    <button
                      class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/20 transition-all duration-200 transform hover:scale-105"
                      title="View journal entries"
                    >
                      <i class="fas fa-book text-purple-600 dark:text-purple-400"></i>
                    </button>
                  </Link>
                  
                  <!-- Balance Report -->
                  <Link :href="route('ledger.accounts.balance', node.data.id)">
                    <button
                      class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/20 transition-all duration-200 transform hover:scale-105"
                      title="View balance report"
                    >
                      <i class="fas fa-chart-line text-orange-600 dark:text-orange-400"></i>
                    </button>
                  </Link>
                </div>
              </template>
            </Column>
            
            <template #empty>
              <div class="text-center py-8">
                <i class="fas fa-sitemap text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p class="text-gray-500 dark:text-gray-400">No accounts found</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your filters.</p>
              </div>
            </template>
            
            <template #loading>
              <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p class="text-gray-500 dark:text-gray-400">Loading accounts...</p>
              </div>
            </template>
          </TreeTable>
        </template>
      </Card>

      <!-- Account Type Summary -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">Assets</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">Liabilities</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">Equity</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">Revenue</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Income Statement</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-red-600 dark:text-red-400">Expenses</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Income Statement</div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    </LayoutShell>
</template>

<style scoped>
:deep(.p-treetable) {
  border-radius: 0.5rem;
  overflow: hidden;
}

:deep(.p-treetable-thead > tr > th) {
  background-color: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
  color: #374151;
}

:deep(.p-treetable-tbody > tr) {
  border-bottom: 1px solid #f3f4f6;
}

:deep(.p-treetable-tbody > tr:hover) {
  background-color: #f8fafc;
}

:deep(.p-card) {
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

:deep(.p-treetable .p-treetable-toggler) {
  margin-right: 0.5rem;
}
</style>