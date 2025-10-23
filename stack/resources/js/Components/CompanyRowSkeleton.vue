<script setup>
</script>

<template>
  <!-- Loading skeleton that matches CompanyRow structure -->
  <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors animate-pulse">
    <td class="px-4 py-4">
      <!-- Checkbox skeleton -->
      <div class="w-4 h-4 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" />
    </td>
    
    <td class="px-4 py-4">
      <div class="flex items-center gap-3">
        <!-- Avatar skeleton -->
        <div class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-gray-700 animate-shimmer" />
        
        <!-- Company name and slug skeleton -->
        <div class="min-w-0">
          <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded mb-2 animate-shimmer" style="width: 120px;" />
          <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 80px;" />
        </div>
      </div>
    </td>
    
    <td class="px-4 py-4">
      <!-- Industry skeleton -->
      <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 100px;" />
    </td>
    
    <td class="px-4 py-4">
      <!-- Location skeleton -->
      <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-shimmer" style="width: 140px;" />
    </td>
    
    <td class="px-4 py-4">
      <!-- Role skeleton -->
      <div class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800 rounded animate-shimmer" style="width: 60px; height: 24px;" />
    </td>
    
    <td class="px-4 py-4 text-right">
      <!-- Menu skeleton -->
      <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-lg animate-shimmer ml-auto" style="width: 32px; height: 32px;" />
    </td>
  </tr>
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