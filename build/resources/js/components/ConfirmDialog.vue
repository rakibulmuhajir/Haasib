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

/* Variants carry the icon and the class hooks; the colours themselves live in
   the stylesheet below so a theme change reaches them. The destructive confirm
   uses the button component's own destructive variant rather than a literal
   red, which is why there is no buttonClass for it any more. */
const variantConfig = {
  default: { icon: Info, button: 'default' },
  destructive: { icon: AlertTriangle, button: 'destructive' },
  success: { icon: CheckCircle, button: 'default' },
} as const

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
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <div class="flex gap-4">
          <!-- Icon -->
          <div class="confirm-icon" :data-variant="variant">
            <component :is="DisplayIcon" class="h-5 w-5" />
          </div>
          
          <div class="flex-1 pt-0.5">
            <DialogTitle class="text-lg font-semibold text-foreground">
              {{ title }}
            </DialogTitle>
            <DialogDescription class="mt-1.5 text-sm leading-relaxed text-text-secondary">
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
        >
          {{ cancelText }}
        </Button>
        <Button
          :variant="variantConfig[variant].button"
          @click="handleConfirm"
          :disabled="loading"
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

<style scoped>
/* A tinted disc, not a filled sticker: the icon reads as an aside to the
   question, and the question is the thing being asked. */
.confirm-icon {
  display: flex;
  height: 44px;
  width: 44px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: var(--surface-sunken);
  color: var(--text-secondary);
}

.confirm-icon[data-variant='destructive'] {
  background: color-mix(in oklab, var(--status-critical) 12%, transparent);
  color: var(--status-critical);
}

.confirm-icon[data-variant='success'] {
  background: color-mix(in oklab, var(--status-success) 14%, transparent);
  color: var(--status-success);
}
</style>
