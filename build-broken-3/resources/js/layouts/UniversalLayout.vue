<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    title?: string;
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <header
                class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
            >
                <div class="flex items-center gap-2 flex-1">
                    <SidebarTrigger class="-ml-1" />
                    <template v-if="breadcrumbs && breadcrumbs.length > 0">
                        <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    </template>
                </div>
                <div v-if="$slots['header-actions']" class="flex items-center gap-2">
                    <slot name="header-actions" />
                </div>
            </header>
            
            <div class="flex-1 overflow-auto">
                <div class="p-6">
                    <div v-if="title" class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    </div>
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>
