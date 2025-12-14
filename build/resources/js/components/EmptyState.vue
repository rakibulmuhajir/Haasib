<script setup lang="ts">
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'

interface Action {
  label: string
  icon?: Component
  onClick: () => void
  variant?: 'default' | 'secondary' | 'outline' | 'ghost'
}

interface Props {
  icon?: Component
  title: string
  description?: string
  actions?: Action[]
  /** Visual size variant */
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  icon: undefined,
  description: undefined,
  actions: () => [],
  size: 'md',
})

const sizeClasses = {
  sm: {
    wrapper: 'p-8',
    icon: 'h-10 w-10',
    iconWrapper: 'h-16 w-16',
    title: 'text-base',
    description: 'text-sm max-w-xs',
  },
  md: {
    wrapper: 'p-12',
    icon: 'h-12 w-12',
    iconWrapper: 'h-20 w-20',
    title: 'text-lg',
    description: 'text-sm max-w-sm',
  },
  lg: {
    wrapper: 'p-16',
    icon: 'h-14 w-14',
    iconWrapper: 'h-24 w-24',
    title: 'text-xl',
    description: 'text-base max-w-md',
  },
}
</script>

<template>
  <div
    :class="[
      'flex flex-col items-center justify-center text-center',
      sizeClasses[size].wrapper
    ]"
  >
    <!-- Icon -->
    <div 
      v-if="icon || $slots.icon"
      :class="[
        'mb-4 flex items-center justify-center rounded-2xl',
        'bg-gradient-to-br from-zinc-100 to-zinc-50',
        'ring-1 ring-zinc-200/50',
        sizeClasses[size].iconWrapper
      ]"
    >
      <slot name="icon">
        <component
          :is="icon"
          :class="['text-zinc-400', sizeClasses[size].icon]"
        />
      </slot>
    </div>

    <!-- Title -->
    <h3 
      :class="[
        'font-semibold text-zinc-900',
        sizeClasses[size].title
      ]"
    >
      {{ title }}
    </h3>

    <!-- Description -->
    <p 
      v-if="description" 
      :class="[
        'mt-2 text-zinc-500 leading-relaxed',
        sizeClasses[size].description
      ]"
    >
      {{ description }}
    </p>

    <!-- Custom Description Slot -->
    <div 
      v-if="$slots.description" 
      :class="[
        'mt-2 text-zinc-500',
        sizeClasses[size].description
      ]"
    >
      <slot name="description" />
    </div>

    <!-- Actions -->
    <div 
      v-if="actions.length > 0 || $slots.actions" 
      class="mt-6 flex items-center gap-3"
    >
      <slot name="actions">
        <Button
          v-for="(action, index) in actions"
          :key="index"
          :variant="action.variant || 'default'"
          @click="action.onClick"
          size="sm"
          :class="[
            action.variant === 'default' || !action.variant 
              ? 'bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 shadow-md shadow-teal-600/20' 
              : ''
          ]"
        >
          <component
            :is="action.icon"
            v-if="action.icon"
            class="mr-2 h-4 w-4"
          />
          {{ action.label }}
        </Button>
      </slot>
    </div>
  </div>
</template>
