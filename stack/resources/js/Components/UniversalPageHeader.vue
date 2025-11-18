<template>
  <!-- STRICT LAYOUT STANDARD: Single-row header design (NO DEVIATION ALLOWED) -->
  <div class="page-header">
    <div class="flex items-center justify-between gap-3 py-3">
      <!-- LEFT: Title (Minimal space) -->
      <div class="flex items-center gap-2 min-w-0 flex-shrink">
        <div class="group relative min-w-0">
          <h1 class="text-xl font-semibold text-gray-900 dark:text-white cursor-help truncate leading-tight">
            {{ title }}
          </h1>
          <!-- Compact tooltip -->
          <div v-if="description" class="absolute bottom-full left-0 mb-1 px-2 py-1 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50 max-w-xs">
            <div class="font-medium">{{ description }}</div>
            <div v-if="subDescription" class="text-xs opacity-75 mt-0.5">{{ subDescription }}</div>
          </div>
        </div>
      </div>
      
      <!-- CENTER: Inline search and filters (Space efficient) -->
      <div v-if="showSearch" class="flex items-center gap-2 flex-1 justify-center max-w-md">
        <!-- Compact search -->
        <div class="w-48 lg:w-56">
          <IconField class="relative">
            <InputIcon>
              <i class="pi pi-search text-sm" />
            </InputIcon>
            <InputText
              v-model="searchQuery"
              :placeholder="searchPlaceholder"
              @keyup.enter="handleSearch"
              class="w-full text-sm h-9"
              size="small"
            />
          </IconField>
        </div>
        
        <!-- Compact status filter -->
        <Dropdown
          v-if="statusOptions && statusOptions.length > 0"
          v-model="statusFilter"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Filter"
          class="w-24 h-9"
          @change="handleFilter"
          size="small"
        />
        
        <!-- Minimal filter controls -->
        <Button
          v-if="hasActiveFilters"
          icon="pi pi-times"
          @click="handleClearFilters"
          severity="secondary"
          text
          class="w-8 h-8 p-0"
          v-tooltip="'Clear filters'"
        />
      </div>
      
      <!-- RIGHT: Page actions (Inline, minimal) -->
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
/* STRICT LAYOUT STANDARD: Space-efficient header styles */
.page-header {
  @apply mb-4 border-b border-gray-200 dark:border-gray-700;
  /* Reduced margin and added subtle border for visual separation */
}

/* Compact form elements */
.page-header :deep(.p-inputtext) {
  @apply text-sm;
}

.page-header :deep(.p-dropdown) {
  @apply text-sm;
}

.page-header :deep(.p-button) {
  @apply text-sm;
}

/* Ensure tooltips don't interfere with layout */
.group:hover .tooltip {
  opacity: 1;
  visibility: visible;
}

/* Responsive adjustments for mobile */
@media (max-width: 768px) {
  .page-header {
    @apply mb-3;
  }
  
  /* Stack elements on mobile if needed */
  .page-header .flex {
    @apply flex-wrap gap-2;
  }
}
</style>