<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Toolbar from 'primevue/toolbar'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'

import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import ThemeSwitcher from '@/Components/ThemeSwitcher.vue'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import UserMenu from '@/Components/UserMenu.vue'
import CommandPalette from '@/Components/CommandPalette.vue'
import AppSidebar from '@/Components/AppSidebar.vue'
import { useSidebar } from '@/composables/useSidebar'

const { expanded, contentPadClass, toggleSidebar } = useSidebar()
const sidebarExpanded = expanded
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <!-- Top Bar (PrimeVue Toolbar) -->
    <Toolbar class="border-b border-gray-200 dark:border-gray-700 !rounded-none !bg-white dark:!bg-gray-800">
      <template #start>
        <div class="flex items-center gap-3">
          <Button text rounded class="md:hidden" @click="toggleSidebar" aria-label="Toggle Menu" icon="pi pi-bars" />
          <Link :href="route('dashboard')" class="flex items-center gap-2">
            <ApplicationLogo class="h-7 w-auto text-gray-800 dark:text-gray-200" />
            <span class="hidden sm:inline text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $page.props.app?.name || 'App' }}</span>
          </Link>
        </div>
      </template>
      <template #end>
        <div class="flex items-center gap-2">
          <CompanySwitcher />
          <ThemeSwitcher />
          <UserMenu />
        </div>
      </template>
    </Toolbar>

    <!-- Left Sidebar (existing implementation) -->
    <AppSidebar :expanded="sidebarExpanded" @toggle="toggleSidebar" />

    <!-- Page Content -->
    <div :class="contentPadClass">
      <!-- Optional page header slot -->
      <header v-if="$slots.header" class="bg-white dark:bg-gray-800 dark:border-b dark:border-gray-700 shadow dark:shadow-none">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <main>
        <slot />
      </main>
    </div>

    <!-- Global Portals / Utilities -->
    <CommandPalette />
    <Toast position="top-right" />
    <ConfirmDialog />
  </div>
  
</template>

