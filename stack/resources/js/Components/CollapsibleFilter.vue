<script setup>
import { ref, computed, watch } from 'vue'
// Using PrimeVue icon classes instead of external icons
import Button from 'primevue/button'
import Card from 'primevue/card'
import Badge from 'primevue/badge'

const props = defineProps({
  title: {
    type: String,
    default: 'Filters'
  },
  defaultCollapsed: {
    type: Boolean,
    default: false
  },
  persistent: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['toggle', 'clear'])

const isCollapsed = ref(props.defaultCollapsed)
const activeFiltersCount = ref(0)

// Watch for active filters count changes
watch(activeFiltersCount, (newCount) => {
  // Auto-expand if filters are applied
  if (newCount > 0 && isCollapsed.value) {
    isCollapsed.value = false
  }
})

// Toggle collapse state
function toggleFilters() {
  isCollapsed.value = !isCollapsed.value
  emit('toggle', isCollapsed.value)
}

// Clear all filters
function clearFilters() {
  emit('clear')
}

// Keyboard accessibility
function handleKeyDown(event) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    toggleFilters()
  }
}

// Expose methods for parent components
defineExpose({
  collapse: () => { isCollapsed.value = true },
  expand: () => { isCollapsed.value = false },
  setActiveFiltersCount: (count) => { activeFiltersCount.value = count }
})
</script>

<template>
  <div class="collapsible-filter">
    <!-- Filter Header -->
    <div 
      class="filter-header"
      @click="toggleFilters"
      @keydown="handleKeyDown"
      role="button"
      :aria-expanded="!isCollapsed"
      aria-controls="filter-content"
      tabindex="0"
    >
      <div class="filter-header-left">
        <div class="filter-header-title">
          <i class="pi pi-filter filter-icon" />
          <h3 class="filter-title">{{ title }}</h3>
        </div>
        <Badge 
          v-if="activeFiltersCount > 0" 
          :value="activeFiltersCount" 
          severity="primary"
          class="filter-badge"
        />
      </div>
      
      <div class="filter-header-right">
        <Button
          v-if="activeFiltersCount > 0"
          label="Clear"
          size="small"
          text
          severity="secondary"
          @click.stop="clearFilters"
          class="clear-btn"
        />
        <Button
          :icon="isCollapsed ? 'pi pi-chevron-down' : 'pi pi-chevron-up'"
          size="small"
          text
          severity="secondary"
          class="toggle-btn"
          :aria-label="isCollapsed ? 'Expand filters' : 'Collapse filters'"
        />
      </div>
    </div>

    <!-- Filter Content -->
    <Transition
      name="filter-collapse"
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-300 ease-in"
      enter-from-class="max-h-0 overflow-hidden"
      enter-to-class="max-h-screen overflow-visible"
      leave-from-class="max-h-screen overflow-visible"
      leave-to-class="max-h-0 overflow-hidden"
    >
      <div 
        v-show="!isCollapsed"
        id="filter-content"
        class="filter-content"
      >
        <div class="filter-content-inner">
          <slot 
            :setActiveFiltersCount="count => activeFiltersCount = count"
            :isCollapsed="isCollapsed"
          />
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.collapsible-filter {
  margin-bottom: 1.5rem;
  background: var(--p-surface-0, #ffffff);
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: var(--p-border-radius-lg, 8px);
  overflow: hidden;
}

:root[data-theme="dark"] .collapsible-filter {
  background: var(--p-surface-0, rgba(255, 255, 255, 0.08));
  border-color: var(--p-content-border-color, rgba(255, 255, 255, 0.12));
}

.filter-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  cursor: pointer;
  user-select: none;
  transition: background-color var(--p-transition-duration, 0.2s);
  border-bottom: 1px solid transparent;
}

.filter-header:hover {
  background-color: var(--p-content-hover-background, #f9fafb);
}

:root[data-theme="dark"] .filter-header:hover {
  background-color: var(--p-content-hover-background, rgba(255, 255, 255, 0.08));
}

.filter-header:focus {
  outline: 2px solid var(--p-primary-color, #3b82f6);
  outline-offset: -2px;
}

.filter-header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.filter-header-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
}

.filter-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: var(--p-text-muted-color, #6b7280);
  flex-shrink: 0;
}

.filter-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-text-color, #111827);
  margin: 0;
}

:root[data-theme="dark"] .filter-title {
  color: var(--p-text-color, #f3f4f6);
}

.filter-badge {
  flex-shrink: 0;
}

.filter-header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.clear-btn {
  margin-right: 0.25rem;
}

.toggle-btn {
  width: 2rem;
  height: 2rem;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.filter-content {
  border-top: 1px solid var(--p-content-border-color, #e5e7eb);
}

:root[data-theme="dark"] .filter-content {
  border-top-color: var(--p-content-border-color, rgba(255, 255, 255, 0.12));
}

.filter-content-inner {
  padding: 1.25rem;
}

/* Transition animations */
.filter-collapse-enter-active,
.filter-collapse-leave-active {
  transition: max-height 0.3s ease-in-out, opacity 0.2s ease-in-out;
}

.filter-collapse-enter-from {
  max-height: 0;
  opacity: 0;
}

.filter-collapse-leave-to {
  max-height: 0;
  opacity: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .filter-header {
    padding: 0.875rem 1rem;
  }
  
  .filter-header-left {
    gap: 0.5rem;
  }
  
  .filter-header-title {
    gap: 0.375rem;
  }
  
  .filter-icon {
    width: 1.125rem;
    height: 1.125rem;
  }
  
  .filter-title {
    font-size: 0.9rem;
  }
  
  .filter-content-inner {
    padding: 1rem;
  }
}

@media (max-width: 480px) {
  .filter-header {
    padding: 0.75rem 0.875rem;
  }
  
  .filter-header-right {
    gap: 0.375rem;
  }
  
  .clear-btn {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
  }
  
  .filter-content-inner {
    padding: 0.875rem;
  }
}
</style>