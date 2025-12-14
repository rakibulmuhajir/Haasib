<script setup lang="ts">
import { computed } from 'vue'
import AppFooter from '@/components/AppFooter.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import Sonner from '@/components/ui/sonner/Sonner.vue'
import type { BreadcrumbItemType } from '@/types'
import { usePage } from '@inertiajs/vue3'

interface Props {
  breadcrumbs?: BreadcrumbItemType[]
  showFooter?: boolean
  fullWidth?: boolean
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
  showFooter: true,
  fullWidth: false,
})

const page = usePage()
const isOpen = computed(() => page.props.sidebarOpen)
</script>

<template>
  <SidebarProvider
    :default-open="isOpen"
    :style="{ '--sidebar-width': 'calc(var(--spacing) * 64)' }"
  >
    <AppSidebar />
    <SidebarInset class="relative flex min-h-screen flex-col bg-surface-2">
      <div
        class="pointer-events-none absolute inset-x-0 top-0 h-56 opacity-80 blur-0"
        :style="{ background: 'var(--shell-hero)' }"
      />

      <AppSidebarHeader :breadcrumbs="breadcrumbs" class="relative z-10" />

      <main class="relative z-10 flex flex-1 flex-col px-4 pb-8 pt-2 lg:px-8">
        <div class="mx-auto flex w-full flex-1 flex-col gap-4" :class="fullWidth ? 'max-w-none' : 'max-w-7xl'">
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
      </main>

      <AppFooter v-if="showFooter" class="relative z-10">
        <template #links>
          <slot name="footer-links" />
        </template>
      </AppFooter>
    </SidebarInset>
    <Sonner />
  </SidebarProvider>
</template>
