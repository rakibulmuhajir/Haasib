<script setup lang="ts">
import { computed } from 'vue'
import AppSidebar from "@/components/dashboard/sidebar-07/AppSidebar.vue"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import Toaster from '@/components/ui/toast/Toaster.vue'
import CompanyContextDebugger from '@/components/CompanyContextDebugger.vue'

interface Props {
  title?: string
  subtitle?: string
  breadcrumbs?: Array<{
    label: string
    href?: string
    active?: boolean
  }>
  headerActions?: Array<{
    label: string
    variant?: 'default' | 'outline' | 'ghost' | 'destructive'
    size?: 'sm' | 'md' | 'lg'
    action?: () => void
    href?: string
  }>
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Dashboard',
  subtitle: 'Overview',
  breadcrumbs: () => [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Overview', active: true }
  ],
  headerActions: () => []
})

// Show debugger in development or when debug parameter is present
const showDebugger = computed(() => {
  return import.meta.env.DEV || 
         window.location.search.includes('debug=company') ||
         localStorage.getItem('company_debug') === 'true'
})
</script>

<template>
  <SidebarProvider
    :style="{
      '--sidebar-width': 'calc(var(--spacing) * 72)',
      '--header-height': 'calc(var(--spacing) * 12)',
    }"
  >
    <AppSidebar variant="inset" />
    <SidebarInset>
      <!-- Header -->
      <header class="flex h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
        <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
          <SidebarTrigger class="-ml-1" />
          <Separator
            orientation="vertical"
            class="mx-2 data-[orientation=vertical]:h-4"
          />
          
          <!-- Breadcrumbs -->
          <Breadcrumb>
            <BreadcrumbList>
              <template v-for="(crumb, index) in breadcrumbs" :key="index">
                <BreadcrumbItem v-if="!crumb.active" class="hidden md:block">
                  <BreadcrumbLink :href="crumb.href || '#'">
                    {{ crumb.label }}
                  </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="!crumb.active && index < breadcrumbs.length - 1" class="hidden md:block" />
                <BreadcrumbItem v-if="crumb.active">
                  <BreadcrumbPage>{{ crumb.label }}</BreadcrumbPage>
                </BreadcrumbItem>
              </template>
            </BreadcrumbList>
          </Breadcrumb>
          
          <!-- Header Actions -->
          <div v-if="headerActions.length > 0" class="ml-auto flex items-center gap-2">
            <template v-for="action in headerActions" :key="action.label">
              <Button
                v-if="action.href"
                :variant="action.variant || 'outline'"
                :size="action.size || 'sm'"
                :class="action.variant === 'default' ? '' : 'hidden sm:flex'"
                @click="action.href ? $router?.push(action.href) : action.action?.()"
              >
                {{ action.label }}
              </Button>
              <Button
                v-else
                :variant="action.variant || 'outline'"
                :size="action.size || 'sm'"
                :class="action.variant === 'default' ? '' : 'hidden sm:flex'"
                @click="action.action"
              >
                {{ action.label }}
              </Button>
            </template>
          </div>
        </div>
      </header>
      
      <!-- Main Content -->
      <div class="flex flex-1 flex-col">
        <div class="@container/main flex flex-1 flex-col gap-2">
          <div class="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
            <!-- Page Content Slot -->
            <slot />
          </div>
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
  
  <!-- Company Context Debugger -->
  <CompanyContextDebugger :show-debugger="showDebugger" />
  
  <Toaster />
</template>
