<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Component } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { AlertTriangle, Info, CheckCircle } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  description?: string
  confirmText?: string
  cancelText?: string
  variant?: 'default' | 'destructive' | 'success'
  icon?: Component
  loading?: boolean
  /** Hide cancel button for acknowledgment dialogs */
  hideCancel?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Are you sure?',
  description: 'This action cannot be undone.',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  variant: 'default',
  icon: undefined,
  loading: false,
  hideCancel: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  'confirm': []
  'cancel': []
}>()

const variantConfig = {
  default: {
    icon: Info,
    iconBg: 'bg-zinc-100',
    iconColor: 'text-zinc-600',
    buttonClass: '',
  },
  destructive: {
    icon: AlertTriangle,
    iconBg: 'bg-red-50',
    iconColor: 'text-red-600',
    buttonClass: 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-600',
  },
  success: {
    icon: CheckCircle,
    iconBg: 'bg-emerald-50',
    iconColor: 'text-emerald-600',
    buttonClass: 'bg-emerald-600 hover:bg-emerald-700 focus-visible:ring-emerald-600',
  },
}

const config = variantConfig[props.variant]
const DisplayIcon = props.icon || config.icon

const handleConfirm = () => {
  emit('confirm')
}

const handleCancel = () => {
  emit('update:open', false)
  emit('cancel')
}

// Only emit cancel when dialog is closed via backdrop/escape, not via cancel button
const handleOpenChange = (value: boolean) => {
  if (!value && props.open) {
    // Dialog is being closed externally (backdrop click, escape key)
    emit('update:open', false)
    emit('cancel')
  } else {
    emit('update:open', value)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent 
      class="sm:max-w-md border-zinc-200 bg-white shadow-xl"
    >
      <DialogHeader>
        <div class="flex gap-4">
          <!-- Icon -->
          <div
            :class="[
              'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
              variantConfig[variant].iconBg
            ]"
          >
            <component
              :is="DisplayIcon"
              :class="['h-5 w-5', variantConfig[variant].iconColor]"
            />
          </div>
          
          <div class="flex-1 pt-0.5">
            <DialogTitle class="text-lg font-semibold text-zinc-900">
              {{ title }}
            </DialogTitle>
            <DialogDescription class="mt-1.5 text-sm leading-relaxed text-zinc-500">
              <slot name="description">
                {{ description }}
              </slot>
            </DialogDescription>
          </div>
        </div>
      </DialogHeader>

      <!-- Custom Content Slot -->
      <div v-if="$slots.default" class="py-2">
        <slot />
      </div>

      <DialogFooter class="gap-2 sm:gap-2">
        <Button
          v-if="!hideCancel"
          variant="outline"
          @click="handleCancel"
          :disabled="loading"
          class="border-zinc-200 hover:bg-zinc-50"
        >
          {{ cancelText }}
        </Button>
        <Button
          @click="handleConfirm"
          :disabled="loading"
          :class="variantConfig[variant].buttonClass"
        >
          <span
            v-if="loading"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
          />
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
