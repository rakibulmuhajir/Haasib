<script setup lang="ts">
import { computed } from 'vue'
import { toastStore, dismissToast } from './toast'

const toasts = computed(() => toastStore.toasts)
</script>

<template>
  <div class="fixed inset-0 z-[9999] flex flex-col gap-3 p-4 pointer-events-none sm:items-end">
    <transition-group name="toast" tag="div" class="flex flex-col gap-3 w-full sm:w-auto">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto w-full sm:w-[320px] rounded-lg border bg-background/90 backdrop-blur shadow-lg"
        :class="toast.variant === 'destructive' ? 'border-destructive text-destructive-foreground bg-destructive/10' : 'border-border'"
      >
        <div class="flex items-start gap-3 p-4">
          <div class="flex-1">
            <p v-if="toast.title" class="font-semibold text-sm">
              {{ toast.title }}
            </p>
            <p v-if="toast.description" class="text-sm text-muted-foreground mt-1">
              {{ toast.description }}
            </p>
          </div>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground transition"
            @click="dismissToast(toast.id)"
          >
            ✕
          </button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
