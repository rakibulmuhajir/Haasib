<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'card',
    validator: (value) => ['card', 'row', 'text', 'avatar'].includes(value)
  },
  lines: {
    type: Number,
    default: 3
  },
  height: {
    type: String,
    default: '1rem'
  },
  width: {
    type: String,
    default: '100%'
  },
  showAvatar: {
    type: Boolean,
    default: false
  },
  avatarSize: {
    type: String,
    default: '3rem'
  }
})

const skeletonClasses = computed(() => {
  const baseClasses = 'animate-pulse bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 dark:from-gray-700 dark:via-gray-600 dark:to-gray-700 bg-[length:200%_100%]'
  const animation = 'animate-shimmer'
  
  return `${baseClasses} ${animation}`
})

const containerClasses = computed(() => {
  switch (props.type) {
    case 'card':
      return 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6'
    case 'row':
      return 'bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-4'
    case 'text':
      return 'space-y-2'
    case 'avatar':
      return 'flex items-center space-x-4'
    default:
      return ''
  }
})
</script>

<template>
  <!-- Card Skeleton -->
  <div v-if="type === 'card'" :class="containerClasses">
    <div class="flex items-center space-x-4 mb-4">
      <div 
        :class="skeletonClasses"
        :style="{ 
          width: avatarSize, 
          height: avatarSize,
          borderRadius: '0.75rem'
        }"
      />
      <div class="flex-1 space-y-2">
        <div 
          :class="skeletonClasses"
          style="height: 1.25rem; width: 60%; border-radius: 0.25rem;"
        />
        <div 
          :class="skeletonClasses"
          style="height: 0.875rem; width: 40%; border-radius: 0.25rem;"
        />
      </div>
    </div>
    
    <div class="space-y-3">
      <div 
        v-for="i in lines"
        :key="i"
        :class="skeletonClasses"
        :style="{ 
          height: '0.875rem', 
          width: i === 1 ? '80%' : i === lines ? '60%' : '90%',
          borderRadius: '0.25rem'
        }"
      />
    </div>
    
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
      <div 
        :class="skeletonClasses"
        style="height: 2.25rem; border-radius: 0.5rem;"
      />
    </div>
  </div>

  <!-- Row Skeleton -->
  <div v-else-if="type === 'row'" :class="containerClasses">
    <div class="flex items-center space-x-4">
      <!-- Checkbox skeleton -->
      <div 
        :class="skeletonClasses"
        style="width: 1rem; height: 1rem; border-radius: 0.25rem;"
      />
      
      <!-- Avatar skeleton -->
      <div 
        :class="skeletonClasses"
        :style="{ 
          width: '2.5rem', 
          height: '2.5rem',
          borderRadius: '0.5rem'
        }"
      />
      
      <!-- Content skeleton -->
      <div class="flex-1 grid grid-cols-5 gap-4">
        <div 
          :class="skeletonClasses"
          style="height: 1rem; width: 70%; border-radius: 0.25rem;"
        />
        <div 
          :class="skeletonClasses"
          style="height: 1rem; width: 50%; border-radius: 0.25rem;"
        />
        <div 
          :class="skeletonClasses"
          style="height: 1rem; width: 60%; border-radius: 0.25rem;"
        />
        <div 
          :class="skeletonClasses"
          style="height: 1rem; width: 40%; border-radius: 0.25rem;"
        />
        <div 
          :class="skeletonClasses"
          style="height: 1rem; width: 30%; border-radius: 0.25rem;"
        />
      </div>
      
      <!-- Menu skeleton -->
      <div 
        :class="skeletonClasses"
        style="width: 2rem; height: 2rem; border-radius: 0.375rem;"
      />
    </div>
  </div>

  <!-- Text Skeleton -->
  <div v-else-if="type === 'text'" :class="containerClasses">
    <div 
      v-for="i in lines" 
      :key="i"
      :class="skeletonClasses"
      :style="{ 
        height, 
        width: i === 1 ? width : i === lines ? '70%' : '85%',
        borderRadius: '0.25rem'
      }"
    />
  </div>

  <!-- Avatar Skeleton -->
  <div v-else-if="type === 'avatar'" :class="containerClasses">
    <div 
      v-if="showAvatar"
      :class="skeletonClasses"
      :style="{ 
        width: avatarSize, 
        height: avatarSize,
        borderRadius: '50%'
      }"
    />
    <div class="flex-1 space-y-2">
      <div 
        :class="skeletonClasses"
        style="height: 1rem; width: 60%; border-radius: 0.25rem;"
      />
      <div 
        v-if="lines > 1"
        :class="skeletonClasses"
        style="height: 0.875rem; width: 40%; border-radius: 0.25rem;"
      />
    </div>
  </div>

  <!-- Custom Skeleton -->
  <div 
    v-else
    :class="skeletonClasses"
    :style="{ 
      height, 
      width,
      borderRadius: '0.375rem'
    }"
  />
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
  animation: shimmer 2s ease-in-out infinite;
}

/* Ensure smooth transitions and prevent layout shift */
.skeleton-enter-active,
.skeleton-leave-active {
  transition: opacity 0.3s ease;
}

.skeleton-enter-from,
.skeleton-leave-to {
  opacity: 0;
}
</style>