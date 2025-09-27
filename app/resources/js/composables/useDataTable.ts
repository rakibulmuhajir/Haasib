import { ref, watch, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { buildDefaultTableFiltersFromColumns, buildDslFromTableFilters, clearTableFilterField } from '@/Utils/filters'

interface UseDataTableOptions {
  columns: any[]
  initialFilters: Record<string, any>
  routeName: string
  filterLookups?: Record<string, { options: any[], labelField?: string, valueField?: string }>
}

export function useDataTable(options: UseDataTableOptions) {
  const { columns, initialFilters, routeName } = options

  // Internal state for the data table
  const tableFilters = ref<Record<string, any>>(buildDefaultTableFiltersFromColumns(columns))
  const selectedRows = ref<any[]>([])

  // The form that holds simple filter values and sorting/pagination state
  const filterForm = useForm({
    ...initialFilters,
    sort_by: initialFilters.sort_by || 'created_at',
    sort_direction: initialFilters.sort_direction || 'desc',
  })

  // --- Internal Logic ---

  // Builds the final query object to send to the server
  const buildQuery = (extraParams: Record<string, any> = {}) => {
    const baseQuery = filterForm.data()
    const dsl = buildDslFromTableFilters(tableFilters.value)
    if (dsl.rules.length) {
      baseQuery.filters = JSON.stringify(dsl)
    }
    return { ...baseQuery, ...extraParams }
  }

  // The main function to fetch data from the server
  const fetchData = (extraParams: Record<string, any> = {}) => {
    const query = buildQuery(extraParams)
    router.get(route(routeName), query, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  // --- Event Handlers ---

  const onPage = (e: any) => {
    fetchData({ page: (e.page || 0) + 1 })
  }

  const onSort = (e: any) => {
    if (!e.sortField) return
    filterForm.sort_by = e.sortField
    filterForm.sort_direction = e.sortOrder === 1 ? 'asc' : 'desc'
    fetchData()
  }

  const onFilter = (e: any) => {
    if (e && e.filters) {
      tableFilters.value = e.filters
    }
    fetchData()
  }

  // --- Watchers ---

  // Watch for changes in simple filters (like a search input) and refetch
  watch(
    () => filterForm.data(),
    () => {
      // This check prevents an infinite loop after a form submission
      if (filterForm.recentlySuccessful) return
      fetchData()
    },
    { deep: true }
  )

  // --- Computed Properties ---

  const activeFilters = computed(() => {
    const chips: Array<{ key: string; display: string; field: string }> = []
    const filters = tableFilters.value || {}

    for (const key in filters) {
      if (key === 'global' || !filters[key]?.constraints) continue

      const column = columns.find(c => (c.filterField || c.field) === key)
      if (!column) continue

      const header = column.header
      const filterType = column.filter?.type || 'text'

      for (const constraint of filters[key].constraints) {
        const { value, matchMode } = constraint
        if (value === null || value === '' || (Array.isArray(value) && !value.length)) continue

        let displayValue = ''

        switch (filterType) {
          case 'select': {
            const lookup = options.filterLookups?.[key]
            if (lookup) {
              const option = lookup.options.find(o => String(o[lookup.valueField || 'value']) === String(value))
              displayValue = `${header}: ${option ? option[lookup.labelField || 'label'] : value}`
            } else {
              displayValue = `${header}: ${value}`
            }
            break
          }
          case 'date': {
            const toLocal = (d: any) => {
              if (!d) return ''
              const dt = new Date(d)
              return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`
            }
            if (matchMode === 'between' && Array.isArray(value)) {
              const [start, end] = value.map(toLocal)
              if (start || end) displayValue = `${header}: ${start || '…'} → ${end || '…'}`
            } else if (matchMode.includes('after')) {
              displayValue = `${header} ≥ ${toLocal(value)}`
            } else if (matchMode.includes('before')) {
              displayValue = `${header} ≤ ${toLocal(value)}`
            } else {
              displayValue = `${header} = ${toLocal(value)}`
            }
            break
          }
          case 'number': {
            if (matchMode === 'between' && Array.isArray(value)) {
              const [min, max] = value
              if (min != null || max != null) displayValue = `${header}: ${min ?? '…'} – ${max ?? '…'}`
            } else if (matchMode.includes('greater')) {
              displayValue = `${header} ≥ ${value}`
            } else if (matchMode.includes('less')) {
              displayValue = `${header} ≤ ${value}`
            } else {
              displayValue = `${header} = ${value}`
            }
            break
          }
          default: // text
            displayValue = `${header}: ${value}`
        }

        if (displayValue) chips.push({ key, field: key, display: displayValue })
      }
    }
    return chips
  })

  // --- Exposed API ---

  return {
    // State
    tableFilters,
    selectedRows,
    filterForm,
    activeFilters,

    // Methods
    fetchData,
    onPage,
    onSort,
    onFilter,

    // Helpers
    clearTableFilterField,
    
    // Additional methods
    clearFilters: () => {
      tableFilters.value = buildDefaultTableFiltersFromColumns(columns)
      fetchData()
    }
  }
}
