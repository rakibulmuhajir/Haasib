<script setup lang="ts">
import { computed } from 'vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import type { BreadcrumbItemType } from '@/types'
import { usePage } from '@inertiajs/vue3'

interface Props {
  breadcrumbs?: BreadcrumbItemType[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})

const page = usePage()
const isOpen = computed(() => page.props.sidebarOpen)
</script>

<template>
  <SidebarProvider :default-open="isOpen">
    <AppSidebar />
    <SidebarInset>
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
