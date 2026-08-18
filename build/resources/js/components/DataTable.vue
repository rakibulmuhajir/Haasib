<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'
import { formatDateTimeForDisplay } from '@/lib/datetime'
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
} from 'lucide-vue-next'

interface Column<T> {
  key: keyof T | string
  label: string
  sortable?: boolean
  class?: string
  headerClass?: string
  render?: (row: T) => any
  /**
   * Figures belong on the right so the decimal points line up down the column
   * and the eye can compare magnitudes without reading a single digit. Setting
   * this also switches the cell to tabular figures and stops it wrapping — a
   * currency amount broken across two lines is not a currency amount.
   */
  numeric?: boolean
}

interface Props {
  data: T[]
  columns: Column<T>[]
  title?: string
  description?: string
  keyField?: keyof T
  loading?: boolean
  /**
   * Accepts either the camelCase shape this component was written for or a
   * Laravel paginator straight off the wire. Half the call sites pass the
   * paginator — `:pagination="invoices"` — and under the old strict shape
   * `currentPage` read undefined, the page count came out NaN, and the pager
   * silently never rendered. Page two was unreachable and nothing said so.
   */
  pagination?: {
    currentPage?: number
    perPage?: number
    total: number
    current_page?: number
    per_page?: number
    last_page?: number
  }
  /**
   * Which density contract this register works at. Reconciliation and journals
   * are compact because reconciliation is dense work, not because the person
   * doing it prefers small text.
   */
  density?: 'comfortable' | 'compact' | 'print'
  /** Enable row hover effect */
  hoverable?: boolean
  /** Enable row click handling */
  clickable?: boolean
  /** Stripe alternate rows */
  striped?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: undefined,
  description: undefined,
  keyField: 'id' as keyof T,
  loading: false,
  pagination: undefined,
  density: undefined,
  hoverable: true,
  clickable: false,
  striped: false,
})

const emit = defineEmits<{
  'sort': [column: keyof T | string, direction: 'asc' | 'desc' | null]
  'page-change': [page: number]
  'row-click': [row: T]
}>()

const resolvedKeyField = computed<keyof T>(() => (props.keyField ?? ('id' as keyof T)))

interface SortState {
  column: string | null
  direction: 'asc' | 'desc' | null
}

const sortState = ref<SortState>({
  column: null,
  direction: null,
})

const handleSort = (column: Column<T>) => {
  if (!column.sortable) return

  const columnKey = String(column.key)

  if (sortState.value.column === columnKey) {
    if (sortState.value.direction === 'asc') {
      sortState.value.direction = 'desc'
    } else if (sortState.value.direction === 'desc') {
      sortState.value.column = null
      sortState.value.direction = null
    }
  } else {
    sortState.value.column = columnKey
    sortState.value.direction = 'asc'
  }

  emit('sort', columnKey as keyof T | string, sortState.value.direction)
}

const getSortIcon = (column: Column<T>): Component => {
  if (sortState.value.column !== String(column.key)) return ArrowUpDown
  return sortState.value.direction === 'asc' ? ArrowUp : ArrowDown
}

/** One shape for the rest of the component to reason about. */
const page = computed(() => {
  const p = props.pagination
  if (!p) return null

  const currentPage = p.currentPage ?? p.current_page ?? 1
  const perPage = p.perPage ?? p.per_page ?? 0
  const total = p.total ?? 0

  return { currentPage, perPage, total }
})

const totalPages = computed(() => {
  const p = page.value
  if (!p) return 1
  // A paginator that reports its own page count is more trustworthy than one
  // derived from a per-page figure that may have been filtered.
  if (props.pagination?.last_page) return props.pagination.last_page
  if (!p.perPage) return 1
  return Math.ceil(p.total / p.perPage)
})

const pageRange = computed(() => {
  if (!page.value) return []
  const current = page.value.currentPage
  const total = totalPages.value
  const delta = 1
  const range: (number | 'ellipsis')[] = []
  
  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
      range.push(i)
    } else if (range[range.length - 1] !== 'ellipsis') {
      range.push('ellipsis')
    }
  }
  
  return range
})

const goToPage = (target: number) => {
  if (!page.value) return
  if (target < 1 || target > totalPages.value) return
  emit('page-change', target)
}

const getCellValue = (row: T, column: Column<T>) => {
  if (column.render) {
    return column.render(row)
  }
  return row[column.key as keyof T]
}

const getDisplayCellValue = (row: T, column: Column<T>) => {
  return formatDateTimeForDisplay(getCellValue(row, column), String(column.key))
}

const handleRowClick = (row: T) => {
  if (props.clickable) {
    emit('row-click', row)
  }
}
</script>

<template>
  <div :data-density="density">
    <!-- Header -->
    <div
      v-if="title || description || $slots.header"
      class="flex items-center justify-between border-b border-border/80 bg-surface-1 px-6 py-4"
    >
      <div>
        <h3 v-if="title" class="font-semibold text-text-primary">{{ title }}</h3>
        <p v-if="description" class="mt-0.5 text-sm text-text-secondary">
          {{ description }}
        </p>
      </div>
      <div v-if="$slots.header">
        <slot name="header" />
      </div>
    </div>

    <!-- Desktop Table -->
    <div class="hidden overflow-x-auto lg:block">
      <table class="min-w-full">
        <thead>
          <tr class="border-b border-border/80">
            <th
              v-for="column in columns"
              :key="String(column.key)"
              :scope="'col'"
              :class="[
                'dt-head',
                column.numeric ? 'text-right' : 'text-left',
                column.headerClass,
                { 'cursor-pointer select-none transition-colors hover:text-text-primary': column.sortable },
              ]"
              @click="handleSort(column)"
            >
              <div class="flex items-center gap-2" :class="column.numeric && 'justify-end'">
                <span>{{ column.label }}</span>
                <component
                  :is="getSortIcon(column)"
                  v-if="column.sortable"
                  class="h-3.5 w-3.5 transition-colors"
                  :class="[
                    sortState.column === String(column.key) ? 'text-primary' : 'text-text-quaternary'
                  ]"
                />
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border/70">
          <!-- Loading State -->
          <tr v-if="loading">
            <td :colspan="columns.length" class="dt-cell dt-cell--wide text-center">
              <div class="flex items-center justify-center gap-3">
                <div class="h-5 w-5 animate-spin rounded-full border-2 border-border border-t-primary" />
                <span class="text-sm text-text-secondary">Loading...</span>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="data.length === 0">
            <td :colspan="columns.length" class="dt-cell dt-cell--wide">
              <slot name="empty">
                <div class="text-center text-sm text-text-secondary">No data available</div>
              </slot>
            </td>
          </tr>
          
          <!-- Data Rows -->
          <tr
            v-else
            v-for="(row, index) in data"
            :key="String(row[resolvedKeyField])"
            :class="[
              'transition-colors',
              hoverable && 'hover:bg-muted/60',
              clickable && 'cursor-pointer',
              striped && index % 2 === 1 && 'bg-muted/50',
            ]"
            @click="handleRowClick(row)"
          >
            <td
              v-for="column in columns"
              :key="String(column.key)"
              :class="['dt-cell', column.numeric && 'dt-cell--numeric', column.class]"
            >
              <slot :name="`cell-${String(column.key)}`" :row="row" :value="getCellValue(row, column)">
                {{ getDisplayCellValue(row, column) }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards -->
    <div class="space-y-3 p-4 lg:hidden">
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="flex items-center gap-3">
          <div class="h-5 w-5 animate-spin rounded-full border-2 border-border border-t-primary" />
          <span class="text-sm text-text-secondary">Loading...</span>
        </div>
      </div>
      
      <div v-else-if="data.length === 0" class="py-12 text-center text-sm text-text-secondary">
        <slot name="empty">No data available</slot>
      </div>
      
      <div v-else>
        <slot 
          name="mobile-card" 
          v-for="row in data" 
          :key="String(row[resolvedKeyField])" 
          :row="row"
        >
          <div 
            class="rounded-xl border border-border/80 bg-surface-1 p-4"
            :class="{ 'cursor-pointer hover:border-border': clickable }"
            @click="handleRowClick(row)"
          >
            <div class="space-y-2.5 text-sm">
              <div
                v-for="column in columns"
                :key="String(column.key)"
                class="flex items-center justify-between gap-4"
              >
                <span class="text-text-tertiary">{{ column.label }}</span>
                <span class="text-right font-medium text-text-primary">
                  {{ getDisplayCellValue(row, column) }}
                </span>
              </div>
            </div>
          </div>
        </slot>
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="pagination && totalPages > 1"
      class="flex flex-col gap-4 border-t border-border/80 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <p v-if="page" class="text-sm text-text-secondary">
        Showing
        <span class="dt-count">{{ (page.currentPage - 1) * page.perPage + 1 }}</span>
        to
        <span class="dt-count">{{ Math.min(page.currentPage * page.perPage, page.total) }}</span>
        of
        <span class="dt-count">{{ page.total }}</span>
      </p>
      
      <nav class="flex items-center gap-1">
        <Button
          variant="outline"
          size="sm"
          :disabled="page?.currentPage === 1"
          @click="goToPage((page?.currentPage ?? 1) - 1)"
          class="h-8 w-8 p-0"
        >
          <ChevronLeft class="h-4 w-4" />
          <span class="sr-only">Previous page</span>
        </Button>
        
        <template v-for="(p, index) in pageRange" :key="index">
          <span
            v-if="p === 'ellipsis'"
            class="px-2 text-text-tertiary"
          >
            …
          </span>
          <Button
            v-else
            :variant="p === page?.currentPage ? 'default' : 'outline'"
            size="sm"
            :aria-current="p === page?.currentPage ? 'page' : undefined"
            @click="goToPage(p)"
            class="h-8 w-8 p-0"
          >
            {{ p }}
          </Button>
        </template>
        
        <Button
          variant="outline"
          size="sm"
          :disabled="page?.currentPage === totalPages"
          @click="goToPage((page?.currentPage ?? 1) + 1)"
          class="h-8 w-8 p-0"
        >
          <ChevronRight class="h-4 w-4" />
          <span class="sr-only">Next page</span>
        </Button>
      </nav>
    </div>
  </div>
</template>

<style scoped>
/* The register. Cell padding comes from the density contract rather than from
   fixed utilities, so a compact reconciliation view and a comfortable customer
   list are the same table honouring different work. */
.dt-cell {
  padding: var(--cell-py) var(--cell-px, 1.5rem);
  font-size: 0.875rem;
  color: var(--text-secondary, inherit);
}

.dt-cell--wide {
  padding-block: calc(var(--cell-py) * 3);
}

/* Figures: right-aligned, tabular, and never wrapped. Tabular figures matter
   even when the face is not monospace — proportional digits make a column of
   amounts ragged in a way that defeats the point of a column. */
.dt-cell--numeric {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.dt-head {
  padding: var(--cell-py) var(--cell-px, 1.5rem);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-metadata);
}

/* Counts in the pager are figures too, and they change as you page. Tabular
   stops the surrounding words shuffling sideways when 9 becomes 10. */
.dt-count {
  font-variant-numeric: tabular-nums;
  color: var(--text-primary, inherit);
  font-weight: 500;
}
</style>
