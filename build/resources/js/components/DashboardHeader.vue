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
  breadcrumbs?: BreadcrumbItemType[]
  actions?: Action[]
}

const props = withDefaults(defineProps<Props>(), {
  title: undefined,
  breadcrumbs: () => [],
  actions: () => [],
})
</script>

<template>
  <header
    class="flex h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)"
  >
    <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
      <SidebarTrigger class="-ml-1" />

      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Separator orientation="vertical" class="mx-2 data-[orientation=vertical]:h-4" />
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>

      <template v-if="title">
        <Separator v-if="!breadcrumbs || breadcrumbs.length === 0" orientation="vertical" class="mx-2 data-[orientation=vertical]:h-4" />
        <h1 class="text-base font-medium">{{ title }}</h1>
      </template>

      <div v-if="actions.length > 0 || $slots.actions" class="ml-auto flex items-center gap-2">
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
