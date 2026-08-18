<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ChevronLeft } from 'lucide-vue-next'

interface Action {
  label: string
  icon?: Component
  onClick?: () => void
  variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive'
  disabled?: boolean
  loading?: boolean
}

interface Props {
  title: string
  description?: string
  icon?: Component
  badge?: {
    text: string
    variant?: 'default' | 'secondary' | 'outline' | 'destructive'
  }
  actions?: Action[]
  backButton?: {
    label: string
    onClick: () => void
    icon?: Component
  }
}

const props = withDefaults(defineProps<Props>(), {
  description: undefined,
  icon: undefined,
  badge: undefined,
  actions: () => [],
  backButton: undefined,
})

const BackIcon = computed(() => props.backButton?.icon || ChevronLeft)
</script>

<template>
  <header class="pb-6">
    <!-- Back Button -->
    <div v-if="backButton" class="mb-4">
      <Button
        @click="backButton.onClick"
        variant="ghost"
        size="sm"
        class="group -ml-2 inline-flex items-center gap-1.5 px-2 text-sm font-medium text-text-secondary transition-colors hover:text-foreground"
      >
        <component 
          :is="BackIcon" 
          class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" 
        />
        {{ backButton.label }}
      </Button>
    </div>

    <!-- Header Content -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 flex-1">
        <!-- Title Row -->
        <div class="flex items-center gap-3">
          <!-- Icon with accent background -->
          <!-- Was a teal-to-emerald gradient chip with a coloured drop shadow.
               Shadow is reserved for surfaces that genuinely float — dialog,
               popover, dropdown, toast — and a page-title icon does not float.
               A rule and a quiet ground say the same thing without the glow. -->
          <div
            v-if="icon"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-surface-2"
          >
            <component :is="icon" class="h-5 w-5 text-text-secondary" />
          </div>

          <!-- Title -->
          <!-- Page titles are the display role. Off-skin --display-family is
               the sans stack, so this changes nothing until the skin is on. -->
          <h1 class="font-display text-2xl font-semibold tracking-tight text-foreground">
            {{ title }}
          </h1>

          <!-- Badge -->
          <Badge
            v-if="badge"
            :variant="badge.variant || 'secondary'"
            class="shrink-0 rounded-full px-2.5 font-medium"
          >
            {{ badge.text }}
          </Badge>
        </div>

        <!-- Description -->
        <p 
          v-if="description" 
          class="mt-2 text-[15px] leading-relaxed text-text-secondary"
        >
          {{ description }}
        </p>

        <!-- Custom description slot -->
        <div v-if="$slots.description" class="mt-2 text-[15px] text-text-secondary">
          <slot name="description" />
        </div>
      </div>

      <!-- Actions -->
      <div 
        v-if="actions.length > 0 || $slots.actions" 
        class="flex items-center gap-2 shrink-0"
      >
        <slot name="actions">
          <Button
            v-for="(action, index) in actions"
            :key="index"
            :variant="action.variant || 'default'"
            :disabled="action.disabled || action.loading"
            @click="action.onClick"
            size="sm"
          >
            <component
              :is="action.icon"
              v-if="action.icon && !action.loading"
              class="mr-2 h-4 w-4"
            />
            <span
              v-if="action.loading"
              class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            />
            {{ action.label }}
          </Button>
        </slot>
      </div>
    </div>
  </header>
</template>
