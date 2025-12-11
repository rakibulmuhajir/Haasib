<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
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

const heading = computed(() => {
  if (props.title) return props.title
  const lastCrumb = props.breadcrumbs[props.breadcrumbs.length - 1]
  return lastCrumb?.title || 'Dashboard'
})
</script>

<template>
  <header
    class="relative flex h-(--header-height) shrink-0 items-center border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/70 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)"
  >
    <div class="absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[var(--shell-hero-ring)] to-transparent" />

    <div class="flex w-full items-center justify-between gap-3 px-4 py-2 lg:gap-4 lg:px-6">
      <div class="flex min-w-0 flex-1 items-start gap-3">
        <SidebarTrigger class="-ml-1 mt-1 text-text-tertiary hover:text-text-primary transition-colors" />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <div v-if="breadcrumbs && breadcrumbs.length > 0" class="flex items-center gap-2 text-xs text-text-tertiary">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <h1 class="truncate text-lg font-semibold text-text-primary">{{ heading }}</h1>
            <slot name="meta">
              <span v-if="subtitle" class="rounded-full bg-accent px-2.5 py-1 text-[11px] font-medium text-text-secondary">
                {{ subtitle }}
              </span>
            </slot>
          </div>

          <div v-if="$slots.description" class="text-sm text-text-secondary">
            <slot name="description" />
          </div>
        </div>
      </div>

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
  </header>
</template>
