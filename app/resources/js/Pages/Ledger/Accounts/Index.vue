<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import TreeTable from 'primevue/treetable'
import Column from 'primevue/column'

const page = usePage()
const filters = ref({
  type: '',
  active: ''
})

// Permissions
const canView = computed(() => 
  page.props.auth.permissions?.['ledger.accounts.view'] ?? false
)

// Filter options
const typeOptions = [
  { label: 'All Types', value: '' },
  { label: 'Assets', value: 'asset' },
  { label: 'Liabilities', value: 'liability' },
  { label: 'Equity', value: 'equity' },
  { label: 'Revenue', value: 'revenue' },
  { label: 'Expenses', value: 'expense' }
]

const activeOptions = [
  { label: 'All', value: '' },
  { label: 'Active Only', value: true },
  { label: 'Inactive Only', value: false }
]

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

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
const transformAccountsToTree = (accounts: any[]) => {
  return accounts.map(account => ({
    key: account.id,
    data: {
      id: account.id,
      code: account.code,
      name: account.name,
      type: account.type,
      normal_balance: account.normal_balance,
      active: account.active,
      system_account: account.system_account,
      description: account.description,
      level: account.level,
      journal_lines_count: account.journal_lines_count || 0,
      children: account.children || []
    },
    children: account.children ? transformAccountsToTree(account.children) : []
  }))
}

const treeData = computed(() => {
  const accounts = page.props.accounts as any[] || []
  return transformAccountsToTree(accounts)
})

// Calculate account balance (simplified for display)
const calculateAccountBalance = (account: any) => {
  // This would typically come from the backend with actual balance calculations
  const balance = Math.random() * 100000 - 50000 // Mock balance for demo
  return {
    amount: balance,
    type: balance >= 0 ? account.normal_balance : (account.normal_balance === 'debit' ? 'credit' : 'debit')
  }
}
</script>

<template>
  <LayoutShell>
    <template #sidebar>
      <!-- Sidebar content will be handled by the layout -->
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between">
        <Breadcrumb :items="[{ label: 'Chart of Accounts' }]" />
      </div>
    </template>

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Chart of Accounts
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Browse and manage your company's chart of accounts
          </p>
        </div>
      </div>

      <!-- Filters -->
      <Card>
        <template #title>Filters</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
            
            <div class="flex items-end">
              <Link :href="route('ledger.accounts.index', filters)" preserve-state>
                <Button label="Apply Filters" class="w-full" />
              </Link>
            </div>
          </div>
        </template>
      </Card>

      <!-- Accounts Tree Table -->
      <Card>
        <template #content>
          <TreeTable 
            :value="treeData"
            :paginator="true"
            :rows="20"
            stripedRows
            responsiveLayout="scroll"
            sortMode="single"
            sortField="code"
            :sortOrder="1"
          >
            <Column field="code" header="Code" expander style="width: 100px">
              <template #body="{ node }">
                <span class="font-mono text-sm">{{ node.data.code }}</span>
              </template>
            </Column>
            
            <Column field="name" header="Account Name" style="min-width: 300px">
              <template #body="{ node }">
                <div class="flex items-center gap-2">
                  <span 
                    class="text-sm font-medium"
                    :class="node.data.active ? 'text-gray-900 dark:text-white' : 'text-gray-400'"
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
                <div v-if="node.data.description" class="text-xs text-gray-500 mt-1">
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
                    :class="calculateAccountBalance(node.data).type === 'debit' ? 'text-green-600' : 'text-red-600'"
                  >
                    {{ formatCurrency(Math.abs(calculateAccountBalance(node.data).amount)) }}
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ calculateAccountBalance(node.data).type }}
                  </div>
                </div>
              </template>
            </Column>
            
            <Column field="actions" header="Actions" style="width: 100px">
              <template #body="{ node }">
                <div class="flex items-center justify-center gap-2">
                  <Link :href="route('ledger.accounts.show', node.data.id)">
                    <Button
                      text
                      icon="eye"
                      size="small"
                      v-tooltip.top="'View details'"
                    />
                  </Link>
                </div>
              </template>
            </Column>
          </TreeTable>
        </template>
      </Card>

      <!-- Account Type Summary -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">Assets</div>
              <div class="text-sm text-gray-500">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-orange-600">Liabilities</div>
              <div class="text-sm text-gray-500">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">Equity</div>
              <div class="text-sm text-gray-500">Balance Sheet</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">Revenue</div>
              <div class="text-sm text-gray-500">Income Statement</div>
            </div>
          </template>
        </Card>
        
        <Card>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-red-600">Expenses</div>
              <div class="text-sm text-gray-500">Income Statement</div>
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
</style>