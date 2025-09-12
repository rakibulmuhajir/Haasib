<script setup>
import { Link } from '@inertiajs/vue3'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-surface-50 dark:bg-surface-900">
    <div class="max-w-md w-full space-y-8 p-8">
      <div class="text-center">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 dark:bg-red-900">
          <svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h2 class="mt-6 text-3xl font-extrabold text-surface-900 dark:text-white">
          No Company Selected
        </h2>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
          You need to select a company to access this feature.
        </p>
      </div>
      
      <div class="mt-8 space-y-6">
        <div class="bg-white dark:bg-surface-800 shadow-sm rounded-lg p-6">
          <h3 class="text-lg font-medium text-surface-900 dark:text-white mb-4">
            Select a Company
          </h3>
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-4">
            Choose a company from the dropdown below to continue:
          </p>
          <CompanySwitcher />
        </div>
        
        <div class="text-center">
          <Link 
            :href="route('dashboard')" 
            class="text-primary-600 hover:text-primary-500 text-sm font-medium"
          >
            ← Back to Dashboard
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>