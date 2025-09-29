import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

interface AccountFilters {
  type: string
  active: string | boolean
  search: string
}

export function useLedgerAccountsFilters(initialFilters?: Partial<AccountFilters>) {
  const filters = ref<AccountFilters>({
    type: initialFilters?.type || '',
    active: initialFilters?.active !== undefined ? initialFilters.active : '',
    search: initialFilters?.search || ''
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
    const params: Record<string, any> = {}
    
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.active !== '') params.active = filters.value.active
    if (filters.value.search) params.search = filters.value.search
    
    router.visit(route('ledger.accounts.index', params), {
      preserveState: true,
      preserveScroll: true
    })
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
    router.visit(route('ledger.accounts.index'), {
      preserveState: true,
      preserveScroll: true
    })
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
      applyFilters()
    }
  )

  return {
    filters,
    typeOptions,
    activeOptions,
    activeFilters,
    hasActiveFilters,
    applyFilters,
    clearFilter,
    clearFilters,
    debouncedSearch
  }
}