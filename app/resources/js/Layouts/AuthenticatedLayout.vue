<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup>
import { ref } from 'vue'
import { ToastProvider } from 'reka-ui'
import CommandPalette from '@/Components/CommandPalette.vue'
import AppSidebar from '@/Components/AppSidebar.vue'
import AppHeader from '@/Components/AppHeader.vue'
import Toasts from '@/Components/Toasts.vue'
import { useSidebar } from '@/composables/useSidebar'



// header component manages its own responsive toggle

// Collapsible left sidebar behavior via composable
const { expanded, contentPadClass, toggleSidebar } = useSidebar()
const sidebarExpanded = expanded
</script>

<template>
  <ToastProvider>
      <div>
          <div class="min-h-screen bg-gray-100">
              <AppHeader />

              <!-- Left sidebar (desktop) -->
              <AppSidebar :expanded="sidebarExpanded" @toggle="toggleSidebar" />

              <div :class="contentPadClass">
                  <!-- Page Heading -->
                  <header class="bg-white shadow" v-if="$slots.header">
                      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                          <slot name="header" />
                      </div>
                  </header>

                  <!-- Page Content -->
                  <main>
                      <slot />
                  </main>
              </div>
          </div>
          <CommandPalette />
          <Toasts />
      </div>
  </ToastProvider>
</template>
