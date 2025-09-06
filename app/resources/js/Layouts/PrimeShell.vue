<script setup>
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <header class="sticky top-0 z-10 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
      <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          <div class="text-sm font-semibold">App</div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Clean start</div>
        </div>
      </div>
    </header>

    <main>
      <slot />
    </main>
  </div>
</template>

