import { FilterMatchMode, FilterOperator } from '@primevue/core/api'

export type FilterRule = {
  field: string
  operator: string
  value: any
}

export type FilterDSL = {
  logic: 'and' | 'or'
  rules: FilterRule[]
}

export type ColumnFilterType = 'text' | 'number' | 'date' | 'select' | 'multiselect'

export interface ColumnDefLite {
  field: string
  header?: string
  filterable?: boolean
  filterField?: string
  filter?: {
    type?: ColumnFilterType
    matchMode?: string
  }
}

// Build default PrimeVue filters model from columns
export function buildDefaultTableFiltersFromColumns(columns: ColumnDefLite[]) {
  const f: Record<string, any> = {
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  }
  for (const col of columns) {
    if (col.filterable === false) continue
    const field = col.filterField || col.field
    const type = col.filter?.type
    let mode = col.filter?.matchMode as any
    if (!mode) {
      if (type === 'number') mode = FilterMatchMode.GREATER_THAN_OR_EQUAL_TO
      else if (type === 'date') mode = FilterMatchMode.DATE_AFTER
      else if (type === 'select') mode = FilterMatchMode.EQUALS
      else if (type === 'multiselect') mode = FilterMatchMode.IN
      else mode = FilterMatchMode.CONTAINS
    }
    f[field] = { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: mode }] }
  }
  return f
}

// Map DataTable filters model to normalized DSL
export function buildDslFromTableFilters(filtersModel: any): FilterDSL {
  const f: any = filtersModel || {}
  const rules: FilterRule[] = []

  const addRule = (field: string, operator: string, value: any) => {
    if (value === null || value === '' || (Array.isArray(value) && !value.filter((v) => v !== null && v !== '').length)) return
    rules.push({ field, operator, value })
  }

  const toLocalDate = (d: any) => {
    if (!d) return ''
    const dt = typeof d === 'string' ? new Date(d) : d
    if (!dt || isNaN(dt.getTime())) return ''
    const y = dt.getFullYear()
    const m = String(dt.getMonth() + 1).padStart(2, '0')
    const day = String(dt.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  const isFiniteNumber = (v: any) => {
    if (typeof v === 'number') return Number.isFinite(v)
    if (typeof v === 'string' && v.trim() !== '') {
      const n = Number(v)
      return Number.isFinite(n)
    }
    return false
  }

  for (const key of Object.keys(f)) {
    if (key === 'global') continue
    const cs = (f[key]?.constraints || []) as Array<{ value: any; matchMode: string }>
    if (!cs.length) continue

    for (const c of cs) {
      const mm = String(c.matchMode || '').toLowerCase()

      // Between: try date first, fallback to numeric
      if (mm === 'between' && Array.isArray(c.value)) {
        const a = c.value[0]
        const b = c.value[1]
        const aDate = toLocalDate(a)
        const bDate = toLocalDate(b)
        if (aDate || bDate) {
          addRule(key, 'between', [aDate || null, bDate || null])
        } else {
          const an = Number(a)
          const bn = Number(b)
          if (Number.isFinite(an) || Number.isFinite(bn)) addRule(key, 'between', [Number.isFinite(an) ? an : null, Number.isFinite(bn) ? bn : null])
        }
        continue
      }

      // Date modes
      if (mm.includes('after')) {
        const d = toLocalDate(c.value)
        if (d) addRule(key, 'after', d)
        continue
      }
      if (mm.includes('before')) {
        const d = toLocalDate(c.value)
        if (d) addRule(key, 'before', d)
        continue
      }
      if (mm === 'dateis') {
        const d = toLocalDate(c.value)
        if (d) { addRule(key, 'on', d); continue }
      }

      // Set-like
      if (mm === 'in') {
        if (Array.isArray(c.value) && c.value.length) addRule(key, 'in', c.value)
        continue
      }

      // Numeric
      if (mm.includes('greater')) {
        if (isFiniteNumber(c.value)) addRule(key, 'gte', Number(c.value))
        continue
      }
      if (mm.includes('less')) {
        if (isFiniteNumber(c.value)) addRule(key, 'lte', Number(c.value))
        continue
      }
      if (mm === 'equals' || mm === 'eq') {
        if (isFiniteNumber(c.value)) addRule(key, 'eq', Number(c.value))
        else if (c.value !== null && c.value !== '') addRule(key, 'equals', c.value)
        continue
      }

      // Text
      if (mm.includes('starts')) {
        if (c.value) addRule(key, 'starts_with', c.value)
        continue
      }
      if (mm.includes('contains')) {
        if (c.value) addRule(key, 'contains', c.value)
        continue
      }
    }
  }

  return { logic: 'and', rules }
}

// Clear a specific field in the PrimeVue table filters model while preserving matchMode
export function clearTableFilterField(tableFilters: any, field: string) {
  if (!tableFilters[field]) return
  const mm = tableFilters[field].constraints?.[0]?.matchMode
  tableFilters[field] = { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: mm }] }
}

export function mapTextMode(mm: string) {
  const m = (mm || '').toString().toLowerCase()
  if (m.includes('starts')) return 'starts_with'
  if (m.includes('equal')) return 'equals'
  return 'contains'
}

export function encodeFilters(dsl: FilterDSL) {
  return JSON.stringify(dsl)
}
