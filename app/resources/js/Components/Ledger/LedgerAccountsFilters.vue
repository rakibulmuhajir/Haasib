<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Badge from 'primevue/badge'

interface AccountFilters {
  type: string
  active: string | boolean
  search: string
}

interface Props {
  initialFilters?: Partial<AccountFilters>
  routeName?: string
  autoApply?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  routeName: 'ledger.accounts.index',
  autoApply: true
})

const emit = defineEmits<{
  filtersChange: [filters: AccountFilters]
  apply: [filters: AccountFilters]
  clear: []
}>()

const filters = ref<AccountFilters>({
  type: props.initialFilters?.type || '',
  active: props.initialFilters?.active !== undefined ? props.initialFilters.active : '',
  search: props.initialFilters?.search || ''
})

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

// Active filters for display
const activeFilters = computed(() => {
  const active: Array<{ key: string; display: string; field: string; value: any }> = []
  
  if (filters.value.type) {
    active.push({
      key: 'type',
      display: `Type: ${typeOptions.find(t => t.value === filters.value.type)?.label}`,
      field: 'type',
      value: filters.value.type
    })
  }
  
  if (filters.value.active !== '') {
    active.push({
      key: 'active',
      display: `Status: ${activeOptions.find(a => a.value === filters.value.active)?.label}`,
      field: 'active',
      value: filters.value.active
    })
  }
  
  if (filters.value.search) {
    active.push({
      key: 'search',
      display: `Search: "${filters.value.search}"`,
      field: 'search',
      value: filters.value.search
    })
  }
  
  return active
})

// Check if any filters are active
const hasActiveFilters = computed(() => activeFilters.value.length > 0)

// Apply filters and update URL
const applyFilters = () => {
  emit('apply', filters.value)
  emit('filtersChange', filters.value)
  
  if (props.autoApply) {
    const params: Record<string, any> = {}
    
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.active !== '') params.active = filters.value.active
    if (filters.value.search) params.search = filters.value.search
    
    router.visit(route(props.routeName, params), {
      preserveState: true,
      preserveScroll: true
    })
  }
}

// Clear a specific filter
const clearFilter = (field: keyof AccountFilters) => {
  filters.value[field] = field === 'active' ? '' : ''
  applyFilters()
}

// Clear all filters
const clearFilters = () => {
  filters.value = {
    type: '',
    active: '',
    search: ''
  }
  emit('clear')
  
  if (props.autoApply) {
    router.visit(route(props.routeName), {
      preserveState: true,
      preserveScroll: true
    })
  }
}

// Auto-apply on search enter (debounced)
let searchTimeout: NodeJS.Timeout
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 300)
}

// Watch for changes to auto-apply filters (except search which is debounced)
watch(
  () => [filters.value.type, filters.value.active],
  () => {
    if (props.autoApply) {
      applyFilters()
    }
  }
)

// Expose methods for external access
defineExpose({
  filters,
  applyFilters,
  clearFilters,
  clearFilter,
  debouncedSearch
})
</script>

<template>
  <div class="space-y-4">
    <!-- Filters Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Search -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Search
        </label>
        <InputText
          v-model="filters.search"
          placeholder="Search accounts..."
          class="w-full"
          @keyup.enter="applyFilters"
          @input="debouncedSearch"
        />
      </div>
      
      <!-- Type Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Type
        </label>
        <Select
          v-model="filters.type"
          :options="typeOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Select type"
          @change="applyFilters"
        />
      </div>
      
      <!-- Status Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Status
        </label>
        <Select
          v-model="filters.active"
          :options="activeOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Select status"
          @change="applyFilters"
        />
      </div>
      
      <!-- Clear Button -->
      <div class="flex items-end">
        <Button
          label="Clear All"
          icon="times"
          severity="secondary"
          outlined
          size="small"
          :disabled="!hasActiveFilters"
          @click="clearFilters"
        />
      </div>
    </div>
    
    <!-- Active Filters Chips -->
    <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
      <span class="text-xs text-gray-500">Active filters:</span>
      <Badge
        v-for="filter in activeFilters"
        :key="filter.key"
        :value="filter.display"
        severity="info"
        size="small"
        class="cursor-pointer"
        @click="clearFilter(filter.field as keyof AccountFilters)"
      />
    </div>
  </div>
</template>

<style scoped>
:deep(.p-badge) {
  border-radius: 9999px;
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

:deep(.p-badge):hover {
  opacity: 0.8;
}
</style>