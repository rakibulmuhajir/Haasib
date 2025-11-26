<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AccountingSidebar from '@/components/AccountingSidebar.vue'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Toaster } from '@/components/ui/sonner'
// import CompanyContextDebugger from '@/components/CompanyContextDebugger.vue'

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
    size?: 'sm' | 'lg' | 'icon'
    action?: () => void
    href?: string
  }>
}

withDefaults(defineProps<Props>(), {
  title: 'Dashboard',
  subtitle: 'Overview',
  breadcrumbs: () => [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Overview', active: true }
  ],
  headerActions: () => []
})
</script>

<template>
  <div class="flex h-screen">
    <!-- Fixed Sidebar -->
    <AccountingSidebar />
    
    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header -->
      <header class="flex h-16 shrink-0 items-center gap-2 border-b bg-background">
        <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
          <Breadcrumb>
            <BreadcrumbList>
              <template v-for="(crumb, index) in breadcrumbs" :key="index">
                <BreadcrumbItem v-if="!crumb.active" class="hidden md:block">
                  <BreadcrumbLink :href="crumb.href || '#'">
                    {{ crumb.label }}
                  </BreadcrumbLink>
                </BreadcrumbItem>
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
                @click="action.href ? router.visit(action.href) : action.action?.()"
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
      <div class="flex-1 overflow-auto">
        <div class="container mx-auto px-4 py-6">
          <!-- Page Content Slot -->
          <slot />
        </div>
      </div>
    </div>
  </div>
  
  <Toaster />
  
  <!-- Company Context Debugger -->
  <!-- <CompanyContextDebugger :show-debugger="showDebugger" /> -->
</template>
