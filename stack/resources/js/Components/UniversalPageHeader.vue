<template>
  <div class="page-header">
    <!-- Compact Header Row -->
    <div class="flex items-center justify-between gap-4">
      <!-- Left Section: Title with Tooltip -->
      <div class="flex items-center gap-4 flex-1 min-w-0">
        <div class="group relative">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white cursor-help truncate">
            {{ title }}
          </h1>
          <!-- Tooltip on hover -->
          <div v-if="description" class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
            <div class="font-medium mb-1">{{ description }}</div>
            <div v-if="subDescription" class="text-xs opacity-75">{{ subDescription }}</div>
            <!-- Tooltip arrow -->
            <div class="absolute top-full left-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
          </div>
        </div>
      </div>
      
      <!-- Center Section: Search and Filters -->
      <div v-if="showSearch" class="flex items-center gap-3 flex-shrink-0">
        <!-- Search Bar -->
        <div class="w-64 lg:w-80">
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="searchQuery"
              :placeholder="searchPlaceholder"
              @keyup.enter="handleSearch"
              class="w-full"
            />
          </IconField>
        </div>
        
        <!-- Status Filter (if provided) -->
        <Dropdown
          v-if="statusOptions && statusOptions.length > 0"
          v-model="statusFilter"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Status"
          class="w-32 lg:w-40"
          @change="handleFilter"
        />
        
        <!-- Filter Buttons -->
        <div class="flex gap-2">
          <Button
            icon="pi pi-filter"
            @click="handleSearch"
            :loading="loading"
            class="w-10 h-10 p-0"
            v-tooltip="'Apply filters'"
          />
          
          <Button
            icon="pi pi-filter-slash"
            severity="secondary"
            @click="handleClearFilters"
            :disabled="!hasActiveFilters"
            class="w-10 h-10 p-0"
            v-tooltip="'Clear filters'"
          />
        </div>
      </div>
      
      <!-- Right Section: Page Actions -->
      <div class="flex-shrink-0">
        <PageActions />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePageActions } from '@/composables/usePageActions'
import PageActions from '@/Components/PageActions.vue'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'

const props = defineProps({
  // Basic props
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  subDescription: {
    type: String,
    default: ''
  },
  
  // Search and filter props
  showSearch: {
    type: Boolean,
    default: true
  },
  searchPlaceholder: {
    type: String,
    default: 'Search...'
  },
  statusOptions: {
    type: Array,
    default: () => []
  },
  
  // Actions configuration
  defaultActions: {
    type: Array,
    default: () => []
  },
  bulkActions: {
    type: Array,
    default: () => []
  },
  
  // Selection state (passed from parent)
  selectedItems: {
    type: Array,
    default: () => []
  },
  
  // Loading state
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'search',
  'filter-changed',
  'filters-cleared',
  'bulk-action',
  'selection-cleared'
])

// Local state
const searchQuery = ref('')
const statusFilter = ref('')
const { actions } = usePageActions()

// Computed properties
const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || statusFilter.value !== ''
})

// Initialize page actions based on current global state and props
const initializePageActions = () => {
  // If there are already actions set globally, use those
  if (actions.value.length > 0) {
    return actions.value
  }
  
  // Otherwise, use default actions from props
  return props.defaultActions
}

// Computed actions based on selection and existing actions
const pageActions = computed(() => {
  if (props.selectedItems.length === 0) {
    // No selection - use initialized actions (preserves existing actions)
    return initializePageActions()
  } else {
    // Has selection - show bulk actions
    const bulkActionsWithCount = props.bulkActions.map(action => ({
      ...action,
      label: typeof action.label === 'string' 
        ? action.label.replace('{count}', props.selectedItems.length)
        : action.label
    }))
    
    // Add clear selection action
    return [
      ...bulkActionsWithCount,
      {
        key: 'clear-selection',
        label: 'Clear Selection',
        icon: 'pi pi-times-circle',
        severity: 'secondary',
        action: () => handleClearSelection()
      }
    ]
  }
})

// Update page actions when selection changes, but only if we have bulk actions
watch(pageActions, (newActions) => {
  // Only update global actions if we have selected items (showing bulk actions)
  // or if there are no existing actions
  if (props.selectedItems.length > 0 || actions.value.length === 0) {
    actions.value = newActions
  }
}, { immediate: true, deep: true })

// Methods
const handleSearch = () => {
  emit('search', {
    query: searchQuery.value,
    status: statusFilter.value
  })
}

const handleFilter = () => {
  emit('filter-changed', {
    status: statusFilter.value
  })
  handleSearch() // Trigger search when filter changes
}

const handleClearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  emit('filters-cleared')
}

const handleClearSelection = () => {
  emit('selection-cleared')
}

// Watch for external prop changes
watch(() => props.selectedItems, () => {
  // Actions will be updated by the computed property
}, { deep: true })

// Expose methods for parent component
defineExpose({
  clearSearch: handleClearFilters,
  focusSearch: () => {
    const searchInput = document.querySelector('input[type="text"]')
    if (searchInput) searchInput.focus()
  }
})
</script>

<style scoped>
/* Additional styles if needed */
.page-header {
  @apply mb-6;
}

/* Ensure tooltips don't get cut off */
.group:hover .tooltip {
  opacity: 1;
  visibility: visible;
}
</style>