<script setup>
import Skeleton from './Skeleton.vue'
</script>

<template>
  <!-- Loading skeleton that matches CompanyCard structure -->
  <div class="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 animate-pulse">
    <div class="p-6">
      <!-- Company Avatar Skeleton -->
      <div class="w-12 h-12 mb-4 rounded-xl bg-gray-200 dark:bg-gray-700 animate-shimmer" />

      <!-- Company Info Skeleton -->
      <div class="mb-4">
        <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded mb-2 animate-shimmer" style="width: 60%;" />
        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 40%;" />
      </div>

      <!-- Metadata Skeleton -->
      <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-4">
        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 50px;" />
        <span>•</span>
        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 40px;" />
        <span>•</span>
        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 60px;" />
      </div>

      <!-- Action Button Skeleton -->
      <div class="h-9 bg-gray-100 dark:bg-gray-700 rounded-lg animate-shimmer" />
    </div>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

.animate-shimmer {
  background: linear-gradient(90deg, 
    rgba(229, 231, 235, 1) 0%, 
    rgba(209, 213, 219, 1) 50%, 
    rgba(229, 231, 235, 1) 100%
  );
  background-size: 200% 100%;
  animation: shimmer 2s ease-in-out infinite;
}

.dark .animate-shimmer {
  background: linear-gradient(90deg, 
    rgba(75, 85, 99, 1) 0%, 
    rgba(55, 65, 81, 1) 50%, 
    rgba(75, 85, 99, 1) 100%
  );
  background-size: 200% 100%;
}
</style>