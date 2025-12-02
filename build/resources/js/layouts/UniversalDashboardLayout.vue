<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import AppSidebar from '@/components/AppSidebar.vue'
import DashboardHeader from '@/components/DashboardHeader.vue'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import type { BreadcrumbItemType } from '@/types'
import { usePage } from '@inertiajs/vue3'

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
  fullWidth?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: undefined,
  breadcrumbs: () => [],
  actions: () => [],
  fullWidth: false,
})

const page = usePage()
const isOpen = computed(() => page.props.sidebarOpen)
</script>

<template>
  <SidebarProvider
    :default-open="isOpen"
    :style="{
      '--sidebar-width': 'calc(var(--spacing) * 72)',
      '--header-height': 'calc(var(--spacing) * 12)',
    }"
  >
    <AppSidebar variant="inset" />
    <SidebarInset>
      <DashboardHeader
        :title="title"
        :breadcrumbs="breadcrumbs"
        :actions="actions"
      >
        <template v-if="$slots.actions" #actions>
          <slot name="actions" />
        </template>
      </DashboardHeader>
      <div class="flex flex-1 flex-col">
        <div class="@container/main flex flex-1 flex-col gap-2">
          <div
            :class="[
              'flex flex-col gap-4 py-4 md:gap-6 md:py-6',
              fullWidth ? '' : 'px-4 lg:px-6',
            ]"
          >
            <slot />
          </div>
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
