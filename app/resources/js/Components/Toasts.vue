<script setup lang="ts">
import { useToasts } from '@/composables/useToasts.js'
import { ToastRoot, ToastTitle, ToastDescription, ToastClose, ToastViewport } from 'reka-ui'

const { toasts, removeToast } = useToasts()

const toastClasses = {
  info: 'bg-blue-50 border-blue-200 text-blue-800',
  success: 'bg-green-50 border-green-200 text-green-800',
  warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
  danger: 'bg-red-50 border-red-200 text-red-800',
}

function handleOpenChange(id: number, open: boolean) {
  if (!open) {
    // Allow animation to finish before removing
    setTimeout(() => removeToast(id), 200)
  }
}
</script>

<template>
  <template v-for="toast in toasts" :key="toast.id">
    <ToastRoot
      :open="toast.open"
      @update:open="handleOpenChange(toast.id, $event)"
      :duration="toast.duration"
      :class="[
        toastClasses[toast.type] || toastClasses.info,
        'border rounded-lg shadow-lg p-4 grid [grid-template-areas:\'title_close\'_\'description_close\'] grid-cols-[auto_max-content] gap-x-4 items-center',
        'data-[state=open]:animate-slideIn data-[state=closed]:animate-hide data-[swipe=move]:translate-x-[var(--reka-toast-swipe-move-x)] data-[swipe=cancel]:translate-x-0 data-[swipe=cancel]:transition-[transform_200ms_ease-out] data-[swipe=end]:animate-swipeOut'
      ]"
    >
      <ToastTitle class="[grid-area:_title] font-semibold text-sm">
        {{ toast.type.charAt(0).toUpperCase() + toast.type.slice(1) }}
      </ToastTitle>
      <ToastDescription class="[grid-area:_description] text-sm">
        {{ toast.message }}
      </ToastDescription>
      <ToastClose class="[grid-area:_close] text-lg font-bold opacity-70 hover:opacity-100">&times;</ToastClose>
    </ToastRoot>
  </template>

  <ToastViewport class="fixed top-0 right-0 flex flex-col p-6 gap-3 w-[390px] max-w-[100vw] m-0 list-none z-[2147483647] outline-none" />
</template>
