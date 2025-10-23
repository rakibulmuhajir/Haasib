<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'
import { usePageActions } from '@/composables/usePageActions'

const props = defineProps({
  // Current data context
  data: {
    type: Array,
    default: () => []
  },
  // Data type (companies, invoices, etc.)
  dataType: {
    type: String,
    required: true
  },
  // Selected items for bulk actions
  selectedItems: {
    type: Array,
    default: () => []
  },
  // Whether to show bulk action controls
  showBulkActions: {
    type: Boolean,
    default: true
  },
  // Max visible actions before showing "More" button
  maxVisible: {
    type: Number,
    default: 4
  },
  // Available actions configuration
  availableActions: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['action', 'bulkAction'])
const page = usePage()
const { actions } = usePageActions()

// Reactive state
const moreMenu = ref()
const visibleCount = ref(props.maxVisible)

// Computed properties
const hasSelection = computed(() => props.selectedItems.length > 0)
const selectedCount = computed(() => props.selectedItems.length)
const totalRecords = computed(() => props.data.length)

// Dynamic actions based on data type and selection
const dynamicActions = computed(() => {
  const actionList = []

  // Add data-type specific actions
  switch (props.dataType) {
    case 'companies':
      actionList.push(
        {
          key: 'create-company',
          label: 'New Company',
          icon: 'pi pi-plus',
          severity: 'primary',
          click: () => emit('action', 'create-company'),
          disabled: false,
          show: true
        },
        {
          key: 'import-companies',
          label: 'Import',
          icon: 'pi pi-upload',
          severity: 'secondary',
          click: () => emit('action', 'import-companies'),
          disabled: false,
          show: true
        },
        {
          key: 'export-companies',
          label: 'Export',
          icon: 'pi pi-download',
          severity: 'secondary',
          click: () => emit('action', 'export-companies'),
          disabled: totalRecords.value === 0,
          show: true
        }
      )
      break

    case 'invoices':
      actionList.push(
        {
          key: 'create-invoice',
          label: 'New Invoice',
          icon: 'pi pi-plus',
          severity: 'primary',
          click: () => emit('action', 'create-invoice'),
          disabled: false,
          show: true
        },
        {
          key: 'import-invoices',
          label: 'Import',
          icon: 'pi pi-upload',
          severity: 'secondary',
          click: () => emit('action', 'import-invoices'),
          disabled: false,
          show: true
        },
        {
          key: 'export-invoices',
          label: 'Export',
          icon: 'pi pi-download',
          severity: 'secondary',
          click: () => emit('action', 'export-invoices'),
          disabled: totalRecords.value === 0,
          show: true
        },
        {
          key: 'batch-pay',
          label: 'Batch Pay',
          icon: 'pi pi-wallet',
          severity: 'success',
          click: () => emit('action', 'batch-pay'),
          disabled: !hasSelection.value,
          show: true
        }
      )
      break

    case 'reports':
      actionList.push(
        {
          key: 'generate-report',
          label: 'Generate Report',
          icon: 'pi pi-chart-bar',
          severity: 'primary',
          click: () => emit('action', 'generate-report'),
          disabled: false,
          show: true
        },
        {
          key: 'schedule-report',
          label: 'Schedule',
          icon: 'pi pi-clock',
          severity: 'secondary',
          click: () => emit('action', 'schedule-report'),
          disabled: false,
          show: true
        },
        {
          key: 'export-report',
          label: 'Export',
          icon: 'pi pi-download',
          severity: 'secondary',
          click: () => emit('action', 'export-report'),
          disabled: totalRecords.value === 0,
          show: true
        }
      )
      break

    default:
      // Generic actions
      actionList.push(
        {
          key: 'create',
          label: 'New Item',
          icon: 'pi pi-plus',
          severity: 'primary',
          click: () => emit('action', 'create'),
          disabled: false,
          show: true
        },
        {
          key: 'export',
          label: 'Export',
          icon: 'pi pi-download',
          severity: 'secondary',
          click: () => emit('action', 'export'),
          disabled: totalRecords.value === 0,
          show: true
        }
      )
  }

  // Add bulk actions if selection exists
  if (hasSelection.value && props.showBulkActions) {
    actionList.unshift(
      {
        key: 'bulk-delete',
        label: `Delete (${selectedCount.value})`,
        icon: 'pi pi-trash',
        severity: 'danger',
        click: () => emit('bulkAction', 'delete'),
        disabled: false,
        show: true
      },
      {
        key: 'bulk-edit',
        label: `Edit (${selectedCount.value})`,
        icon: 'pi pi-pencil',
        severity: 'secondary',
        click: () => emit('bulkAction', 'edit'),
        disabled: false,
        show: true
      }
    )
  }

  // Merge with custom actions
  const customActions = props.availableActions.map(action => ({
    ...action,
    key: action.key || action.label.toLowerCase().replace(/\s+/g, '-')
  }))

  return [...actionList, ...customActions].filter(action => action.show)
})

// Visible and overflow actions
const visibleActions = computed(() => {
  return dynamicActions.value.slice(0, visibleCount.value)
})

const overflowActions = computed(() => {
  return dynamicActions.value.slice(visibleCount.value)
})

const moreMenuItems = computed(() => {
  return overflowActions.value.map(action => ({
    label: action.label,
    icon: action.icon,
    disabled: action.disabled,
    command: () => action.click()
  }))
})

// Methods
function handleActionClick(action) {
  if (action.click && !action.disabled) {
    action.click()
  }
}

function toggleMore(event) {
  moreMenu.value.toggle(event)
}

function clearSelection() {
  emit('bulkAction', 'clear-selection')
}

// Compute visible count based on screen size
function computeVisibleCount() {
  const w = window.innerWidth || document.documentElement.clientWidth || 1024
  let maxByBreakpoint = props.maxVisible
  
  if (w >= 1536) maxByBreakpoint = 6
  else if (w >= 1280) maxByBreakpoint = 5
  else if (w >= 1024) maxByBreakpoint = 4
  else if (w >= 768) maxByBreakpoint = 3
  else if (w >= 640) maxByBreakpoint = 2
  else maxByBreakpoint = 1

  visibleCount.value = Math.min(dynamicActions.value.length, maxByBreakpoint)
}

// Lifecycle
onMounted(() => {
  computeVisibleCount()
  window.addEventListener('resize', computeVisibleCount)
})

// Watch for data changes
watch(() => props.dataType, computeVisibleCount)
watch(() => props.selectedItems.length, computeVisibleCount)
</script>

<template>
  <div class="action-panel">
    <!-- Selection Info -->
    <div v-if="hasSelection && showBulkActions" class="selection-info">
      <div class="selection-info-left">
        <Badge :value="selectedCount" severity="primary" />
        <span class="selection-text">
          {{ selectedCount }} {{ selectedCount === 1 ? 'item' : 'items' }} selected
        </span>
      </div>
      <div class="selection-info-right">
        <Button
          label="Clear"
          size="small"
          text
          severity="secondary"
          @click="clearSelection"
        />
      </div>
    </div>

    <!-- Actions -->
    <div v-if="dynamicActions.length" class="actions-container">
      <div class="actions-left">
        <template v-for="action in visibleActions" :key="action.key">
          <component 
            :is="action.href ? Link : 'div'" 
            :href="action.href"
            class="action-wrapper"
          >
            <Button
              :label="action.label"
              :icon="action.icon"
              :severity="action.severity || 'secondary'"
              :outlined="action.outlined"
              :text="action.text"
              :disabled="action.disabled"
              size="small"
              class="action-btn"
              @click="handleActionClick(action)"
              v-tooltip="action.tooltip || ''"
            />
          </component>
        </template>

        <!-- More button -->
        <Button
          v-if="overflowActions.length"
          label="More"
          icon="pi pi-ellipsis-h"
          size="small"
          outlined
          class="action-btn more-btn"
          @click="toggleMore"
          aria-haspopup="true"
          aria-controls="more_menu"
        />
        <Menu
          ref="moreMenu"
          id="more_menu"
          :model="moreMenuItems"
          :popup="true"
        />
      </div>

      <div class="actions-right">
        <slot name="actions-right" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="actions-empty">
      <slot name="empty">
        <p class="text-muted text-sm">No actions available</p>
      </slot>
    </div>
  </div>
</template>

<style scoped>
.action-panel {
  margin-bottom: 1.5rem;
}

.selection-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: var(--p-primary-50, #eff6ff);
  border: 1px solid var(--p-primary-200, #dbeafe);
  border-radius: var(--p-border-radius-md, 6px);
  margin-bottom: 1rem;
}

:root[data-theme="dark"] .selection-info {
  background: var(--p-primary-950, #172554);
  border-color: var(--p-primary-800, #1e40af);
}

.selection-info-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.selection-text {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--p-primary-700, #1d4ed8);
}

:root[data-theme="dark"] .selection-text {
  color: var(--p-primary-300, #93c5fd);
}

.selection-info-right {
  flex-shrink: 0;
}

.actions-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.actions-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.actions-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.action-wrapper {
  display: inline-flex;
}

.action-btn {
  white-space: nowrap;
}

.more-btn {
  min-width: 80px;
}

.actions-empty {
  padding: 1rem;
  text-align: center;
  color: var(--p-text-muted-color, #6b7280);
}

/* Responsive Design */
@media (max-width: 768px) {
  .selection-info {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    text-align: center;
  }

  .selection-info-left {
    justify-content: center;
  }

  .selection-info-right {
    align-self: center;
  }

  .actions-container {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .actions-left {
    justify-content: center;
    flex-wrap: wrap;
  }

  .actions-right {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .selection-info {
    padding: 0.625rem 0.875rem;
  }

  .actions-left {
    gap: 0.375rem;
  }

  .action-btn {
    font-size: 0.875rem;
  }
}
</style>