<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'
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
}

interface Props {
  data: T[]
  columns: Column<T>[]
  title?: string
  description?: string
  keyField?: keyof T
  loading?: boolean
  pagination?: {
    currentPage: number
    perPage: number
    total: number
  }
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
  hoverable: true,
  clickable: false,
  striped: false,
})

const emit = defineEmits<{
  'sort': [column: keyof T | string, direction: 'asc' | 'desc' | null]
  'page-change': [page: number]
  'row-click': [row: T]
}>()

interface SortState {
  column: keyof T | string | null
  direction: 'asc' | 'desc' | null
}

const sortState = ref<SortState>({
  column: null,
  direction: null,
})

const handleSort = (column: Column<T>) => {
  if (!column.sortable) return

  if (sortState.value.column === column.key) {
    if (sortState.value.direction === 'asc') {
      sortState.value.direction = 'desc'
    } else if (sortState.value.direction === 'desc') {
      sortState.value.column = null
      sortState.value.direction = null
    }
  } else {
    sortState.value.column = column.key
    sortState.value.direction = 'asc'
  }

  emit('sort', sortState.value.column ?? column.key, sortState.value.direction)
}

const getSortIcon = (column: Column<T>): Component => {
  if (sortState.value.column !== column.key) return ArrowUpDown
  return sortState.value.direction === 'asc' ? ArrowUp : ArrowDown
}

const totalPages = computed(() => {
  if (!props.pagination) return 1
  return Math.ceil(props.pagination.total / props.pagination.perPage)
})

const pageRange = computed(() => {
  if (!props.pagination) return []
  const current = props.pagination.currentPage
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

const goToPage = (page: number) => {
  if (!props.pagination) return
  if (page < 1 || page > totalPages.value) return
  emit('page-change', page)
}

const getCellValue = (row: T, column: Column<T>) => {
  if (column.render) {
    return column.render(row)
  }
  return row[column.key as keyof T]
}

const handleRowClick = (row: T) => {
  if (props.clickable) {
    emit('row-click', row)
  }
}
</script>

<template>
  <div>
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
              :class="[
                'px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-text-tertiary',
                column.headerClass,
                { 'cursor-pointer select-none transition-colors hover:text-text-primary': column.sortable },
              ]"
              @click="handleSort(column)"
            >
              <div class="flex items-center gap-2">
                <span>{{ column.label }}</span>
                <component
                  :is="getSortIcon(column)"
                  v-if="column.sortable"
                  class="h-3.5 w-3.5 transition-colors"
                  :class="[
                    sortState.column === column.key ? 'text-primary' : 'text-text-quaternary'
                  ]"
                />
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border/70">
          <!-- Loading State -->
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-6 py-12 text-center">
              <div class="flex items-center justify-center gap-3">
                <div class="h-5 w-5 animate-spin rounded-full border-2 border-border border-t-primary" />
                <span class="text-sm text-text-secondary">Loading...</span>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="data.length === 0">
            <td :colspan="columns.length" class="px-6 py-12">
              <slot name="empty">
                <div class="text-center text-sm text-text-secondary">No data available</div>
              </slot>
            </td>
          </tr>
          
          <!-- Data Rows -->
          <tr
            v-else
            v-for="(row, index) in data"
            :key="String(row[keyField])"
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
              :class="['px-6 py-4 text-sm text-text-secondary', column.class]"
            >
              <slot :name="`cell-${String(column.key)}`" :row="row" :value="getCellValue(row, column)">
                {{ getCellValue(row, column) }}
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
          :key="String(row[keyField])" 
          :row="row"
        >
          <div 
            class="rounded-xl border border-border/80 bg-surface-1 p-4 shadow-sm"
            :class="{ 'cursor-pointer hover:border-border hover:shadow-sm': clickable }"
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
                  {{ getCellValue(row, column) }}
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
      <p class="text-sm text-text-secondary">
        Showing
        <span class="font-medium text-text-primary">
          {{ (pagination.currentPage - 1) * pagination.perPage + 1 }}
        </span>
        to
        <span class="font-medium text-text-primary">
          {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }}
        </span>
        of
        <span class="font-medium text-text-primary">{{ pagination.total }}</span>
        results
      </p>
      
      <nav class="flex items-center gap-1">
        <Button
          variant="outline"
          size="sm"
          :disabled="pagination.currentPage === 1"
          @click="goToPage(pagination.currentPage - 1)"
          class="h-8 w-8 p-0"
        >
          <ChevronLeft class="h-4 w-4" />
          <span class="sr-only">Previous page</span>
        </Button>
        
        <template v-for="(page, index) in pageRange" :key="index">
          <span 
            v-if="page === 'ellipsis'" 
            class="px-2 text-zinc-400"
          >
            ...
          </span>
          <Button
            v-else
            :variant="page === pagination.currentPage ? 'default' : 'outline'"
            size="sm"
            @click="goToPage(page)"
            class="h-8 w-8 p-0"
            :class="[
              page === pagination.currentPage 
                ? 'bg-primary text-primary-foreground hover:bg-primary/90' 
                : ''
            ]"
          >
            {{ page }}
          </Button>
        </template>
        
        <Button
          variant="outline"
          size="sm"
          :disabled="pagination.currentPage === totalPages"
          @click="goToPage(pagination.currentPage + 1)"
          class="h-8 w-8 p-0"
        >
          <ChevronRight class="h-4 w-4" />
          <span class="sr-only">Next page</span>
        </Button>
      </nav>
    </div>
  </div>
</template>
