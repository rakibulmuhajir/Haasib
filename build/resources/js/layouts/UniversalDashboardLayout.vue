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
    <SidebarInset class="relative flex min-h-screen flex-col bg-surface-2">
      <div
        class="pointer-events-none absolute inset-x-0 top-0 h-56 opacity-80"
        :style="{ background: 'var(--shell-hero)' }"
      />

      <DashboardHeader
        :title="title"
        :breadcrumbs="breadcrumbs"
        :actions="actions"
        class="relative z-10"
      >
        <template v-if="$slots.actions" #actions>
          <slot name="actions" />
        </template>
      </DashboardHeader>

      <div class="relative z-10 flex flex-1 flex-col px-4 pb-8 pt-2 lg:px-8">
        <div
          :class="[
            'flex w-full flex-1 flex-col gap-4',
            fullWidth ? 'max-w-none' : 'max-w-7xl',
          ]"
          class="mx-auto"
        >
          <section
            v-if="$slots.hero"
            class="rounded-2xl border border-border/80 bg-surface-1/90 p-4 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-surface-1/70"
          >
            <slot name="hero" />
          </section>

          <section class="flex flex-1 flex-col rounded-2xl border border-border/80 bg-surface-1 p-4 shadow-sm">
            <slot />
          </section>
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
