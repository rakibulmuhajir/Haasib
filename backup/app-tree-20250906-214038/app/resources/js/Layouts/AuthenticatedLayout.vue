<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup>
import { ref } from 'vue'
import CommandPalette from '@/Components/CommandPalette.vue'
import AppSidebar from '@/Components/AppSidebar.vue'
import AppHeader from '@/Components/AppHeader.vue'
import { useSidebar } from '@/composables/useSidebar'

// PrimeVue roots (kept alongside Reka during migration)
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'



// header component manages its own responsive toggle

// Collapsible left sidebar behavior via composable
const { expanded, contentPadClass, toggleSidebar } = useSidebar()
const sidebarExpanded = expanded
</script>

<template>
  <div>
      <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
          <AppHeader />

          <!-- Left sidebar (desktop) -->
          <AppSidebar :expanded="sidebarExpanded" @toggle="toggleSidebar" />

          <div :class="contentPadClass">
              <!-- Page Heading -->
              <header class="bg-white dark:bg-gray-800 dark:border-b dark:border-gray-700 shadow dark:shadow-none" v-if="$slots.header">
                  <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                      <slot name="header" />
                  </div>
              </header>

              <!-- Page Content -->
              <main class="text-gray-900 dark:text-gray-100">
                  <slot />
              </main>
          </div>
      </div>
      <CommandPalette />
      <!-- PrimeVue global portals -->
      <Toast position="top-right" />
      <ConfirmDialog />
  </div>
</template>
