<script setup>
import { useToasts } from '@/composables/useToasts.js'

const { toasts, removeToast } = useToasts()

const toastClasses = {
  info: 'bg-blue-500 border-blue-600',
  success: 'bg-green-500 border-green-600',
  warning: 'bg-yellow-500 border-yellow-600',
  danger: 'bg-red-500 border-red-600',
}
</script>

<template>
  <div class="fixed top-5 right-5 z-50 w-full max-w-sm">
    <TransitionGroup
      tag="div"
      class="relative space-y-3"
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-x-10"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition-all duration-300 ease-in absolute w-full"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-10"
      move-class="transition-transform duration-300 ease-in-out"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[toastClasses[toast.type] || toastClasses.info, 'text-white text-sm font-semibold px-4 py-3 rounded-lg shadow-lg flex justify-between items-center border-b-4']"
      >
        <span>{{ toast.message }}</span>
        <button @click="removeToast(toast.id)" class="ml-4 font-bold text-xl leading-none -mt-1 text-white opacity-70 hover:opacity-100">&times;</button>
      </div>
    </TransitionGroup>
  </div>
</template>
