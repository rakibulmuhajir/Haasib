<script setup>
import { computed } from 'vue'
import { useSlots } from 'vue'
import PageActions from './PageActions.vue'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  maxActions: {
    type: Number,
    default: 3
  }
})

const slots = useSlots()

// Check if there are any actions in the left or right slots
const hasLeftActions = computed(() => !!slots.actionsLeft)
const hasRightActions = computed(() => !!slots.actionsRight)
const hasActions = computed(() => hasLeftActions.value || hasRightActions.value)
</script>

<template>
  <div class="page-header">
    <!-- Header Content -->
    <div class="page-header-content">
      <!-- Title Section -->
      <div class="page-header-title">
        <h1 class="page-title">{{ title }}</h1>
        <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
      </div>

      <!-- Actions Section -->
      <div v-if="hasActions" class="page-header-actions">
        <!-- Left Actions -->
        <div v-if="hasLeftActions" class="page-actions-left">
          <slot name="actionsLeft" />
        </div>

        <!-- Spacer -->
        <div v-if="hasLeftActions && hasRightActions" class="page-actions-spacer"></div>

        <!-- Right Actions -->
        <div v-if="hasRightActions" class="page-actions-right">
          <PageActions :maxVisible="maxActions" />
          <slot name="actionsRight" />
        </div>
      </div>
    </div>

    <!-- Breadcrumb Section (Optional) -->
    <div v-if="slots.breadcrumb" class="page-header-breadcrumb">
      <slot name="breadcrumb" />
    </div>

    <!-- Additional Content -->
    <div v-if="slots.default" class="page-header-content-extra">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

:root[data-theme="dark"] .page-header {
  border-bottom-color: var(--p-content-border-color, #374151);
}

.page-header-content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.page-header-title {
  flex: 1;
  min-width: 0;
}

.page-title {
  font-size: 1.875rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--p-text-color, #111827);
  margin: 0;
  word-break: break-word;
}

:root[data-theme="dark"] .page-title {
  color: var(--p-text-color, #f3f4f6);
}

.page-subtitle {
  font-size: 1rem;
  color: var(--p-text-muted-color, #6b7280);
  margin: 0.25rem 0 0 0;
  line-height: 1.5;
}

:root[data-theme="dark"] .page-subtitle {
  color: var(--p-text-muted-color, #9ca3af);
}

.page-header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
}

.page-actions-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-actions-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-actions-spacer {
  flex: 1;
}

.page-header-breadcrumb {
  margin-bottom: 0.5rem;
}

.page-header-content-extra {
  margin-top: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .page-header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .page-header-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .page-actions-left,
  .page-actions-right {
    justify-content: center;
  }
  
  .page-actions-spacer {
    display: none;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 480px) {
  .page-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
  }
  
  .page-title {
    font-size: 1.25rem;
  }
  
  .page-subtitle {
    font-size: 0.875rem;
  }
}
</style>