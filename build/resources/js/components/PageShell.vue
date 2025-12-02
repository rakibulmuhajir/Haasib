<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchBar from '@/components/SearchBar.vue'
import type { BreadcrumbItem } from '@/types'

interface Action {
  label: string
  icon?: Component
  onClick?: () => void
  variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive'
  disabled?: boolean
  loading?: boolean
}

interface Props {
  title: string
  description?: string
  icon?: Component
  breadcrumbs?: BreadcrumbItem[]
  badge?: {
    text: string
    variant?: 'default' | 'secondary' | 'outline' | 'destructive'
  }
  actions?: Action[]
  backButton?: {
    label: string
    onClick: () => void
    icon?: Component
  }
  searchable?: boolean
  searchPlaceholder?: string
  searchModelValue?: string
  loading?: boolean
  /** Use compact layout without the content card wrapper */
  compact?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  description: undefined,
  icon: undefined,
  breadcrumbs: () => [],
  badge: undefined,
  actions: () => [],
  backButton: undefined,
  searchable: false,
  searchPlaceholder: 'Search...',
  searchModelValue: '',
  loading: false,
  compact: false,
})

const emit = defineEmits<{
  'update:searchModelValue': [value: string]
  'search': [value: string]
}>()

const handleSearchUpdate = (value: string) => {
  emit('update:searchModelValue', value)
  emit('search', value)
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Main content area with warm background -->
    <div class="min-h-full bg-gradient-to-b from-stone-50 to-zinc-100/50">
      <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <PageHeader
          :title="title"
          :description="description"
          :icon="icon"
          :badge="badge"
          :actions="actions"
          :back-button="backButton"
        >
          <template v-if="$slots.description" #description>
            <slot name="description" />
          </template>
          <template v-if="$slots.actions" #actions>
            <slot name="actions" />
          </template>
        </PageHeader>

        <!-- Toolbar: Search & Filters -->
        <div 
          v-if="searchable || $slots.filters" 
          class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <SearchBar
            v-if="searchable"
            :model-value="searchModelValue"
            :placeholder="searchPlaceholder"
            :loading="loading"
            @update:model-value="handleSearchUpdate"
            class="w-full sm:max-w-sm"
          />
          
          <div v-if="$slots.filters" class="flex items-center gap-2">
            <slot name="filters" />
          </div>
        </div>

        <!-- Main Content -->
        <div v-if="compact">
          <slot />
        </div>
        
        <!-- Card-wrapped content (default) -->
        <div 
          v-else 
          class="rounded-2xl border border-zinc-200/80 bg-white shadow-sm shadow-zinc-900/5"
        >
          <slot />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
