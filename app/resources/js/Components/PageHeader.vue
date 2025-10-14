<script setup lang="ts">
import PageActions from '@/Components/PageActions.vue'

const props = withDefaults(defineProps<{
  title: string
  subtitle?: string
  maxActions?: number
}>(), {
  subtitle: '',
  maxActions: 4,
})
</script>

<template>
  <header class="page-header">
    <div class="page-header-left">
      <div class="page-header-title">
        <slot name="icon" />
        <h1 class="page-header-h1">{{ title }}</h1>
      </div>
      <p v-if="subtitle" class="page-header-sub">{{ subtitle }}</p>
      <slot name="below-title" />
    </div>

    <div class="page-header-right">
      <slot name="actions-left" />
      <PageActions :maxVisible="maxActions" />
      <slot name="actions-right" />
    </div>
  </header>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  min-width: 0;
}

.page-header-left {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}

.page-header-title { display: flex; align-items: center; gap: .5rem; min-width: 0; }
.page-header-h1 { font-size: 1.5rem; line-height: 2rem; font-weight: 700; color: var(--p-text-color, var(--text-color)); margin: 0; }
.page-header-sub { color: var(--p-text-muted-color, #6b7280); margin: 0; font-size: .95rem; }

.page-header-right {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-left: auto;
  min-width: 0;
}

@media (max-width: 767px) {
  .page-header { align-items: flex-start; }
  .page-header-right { width: 100%; justify-content: flex-start; flex-wrap: nowrap; overflow-x: auto; padding-bottom: .25rem; }
}
</style>

