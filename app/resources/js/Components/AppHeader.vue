<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import UserMenu from '@/Components/UserMenu.vue'
import AppMobileNav from '@/Components/AppMobileNav.vue'
import AppNavList from '@/Components/AppNavList.vue'
import { navLinks } from '@/nav/links'

const showingNavigationDropdown = ref(false)
</script>

<template>
  <nav class="border-b border-gray-100 bg-white">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 justify-between">
        <div class="flex">
          <!-- Logo -->
          <div class="flex shrink-0 items-center">
            <Link :href="route('dashboard')">
              <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
            </Link>
          </div>

          <!-- Navigation Links -->
          <AppNavList :items="navLinks.filter(l => l.includeInHeader)" />
        </div>

        <!-- Right side (desktop) -->
        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
          <!-- Company Switcher (global tenant context) -->
          <CompanySwitcher />
          <!-- User Menu -->
          <UserMenu />
        </div>

        <!-- Hamburger -->
        <div class="-me-2 flex items-center sm:hidden">
          <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
              <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <AppMobileNav :open="showingNavigationDropdown" />
  </nav>
</template>
