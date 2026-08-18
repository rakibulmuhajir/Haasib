<script setup lang="ts">
import CompanySwitcherItems from '@/components/CompanySwitcherItems.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCompanySwitcher } from '@/composables/useCompanySwitcher';
import { Building2, ChevronsUpDown } from 'lucide-vue-next';

const { currentCompany } = useCompanySwitcher();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
                        >
                            <Building2 class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">
                                {{ currentCompany?.name || 'No Company' }}
                            </span>
                            <span class="truncate text-xs text-muted-foreground">
                                {{ currentCompany?.slug || 'Select company' }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56"
                    align="start"
                    side="bottom"
                    :side-offset="4"
                >
                    <CompanySwitcherItems />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
