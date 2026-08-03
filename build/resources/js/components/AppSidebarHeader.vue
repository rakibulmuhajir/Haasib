<script setup lang="ts">
import type { Component } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { SidebarTrigger } from '@/components/ui/sidebar'
import type { BreadcrumbItemType } from '@/types'

interface Action {
  label: string
  icon?: Component
  onClick?: () => void
  variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive'
  disabled?: boolean
}

interface Props {
  title?: string
  subtitle?: string
  breadcrumbs?: BreadcrumbItemType[]
  actions?: Action[]
}

const props = withDefaults(defineProps<Props>(), {
  title: undefined,
  subtitle: undefined,
  breadcrumbs: () => [],
  actions: () => [],
})

</script>

<template>
  <header
    class="relative flex h-16 shrink-0 items-center border-b border-border bg-background/95 px-6 backdrop-blur transition-[width,height] ease-linear supports-[backdrop-filter]:bg-background/60 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[var(--shell-hero-ring)] to-transparent" />

    <div class="flex w-full items-center justify-between gap-3">
      <div class="flex min-w-0 flex-1 items-start gap-3">
        <SidebarTrigger class="-ml-1 text-text-tertiary transition-colors hover:text-text-primary" />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <div v-if="breadcrumbs && breadcrumbs.length > 0" class="flex items-center gap-2 text-xs text-text-tertiary">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div v-if="actions.length > 0 || $slots.actions" class="flex items-center gap-2">
          <slot name="actions">
            <Button
              v-for="(action, index) in actions"
              :key="index"
              :variant="action.variant || 'ghost'"
              :disabled="action.disabled"
              @click="action.onClick"
              size="sm"
            >
              <component :is="action.icon" v-if="action.icon" class="mr-2 h-4 w-4" />
              {{ action.label }}
            </Button>
          </slot>
        </div>
      </div>
    </div>
  </header>
</template>
