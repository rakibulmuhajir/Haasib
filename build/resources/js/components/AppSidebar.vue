<script setup lang="ts">
import CompanySwitcher from '@/components/CompanySwitcher.vue';
import NavMainCollapsible from '@/components/NavMainCollapsible.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useAppearanceToggle } from '@/composables/useAppearanceToggle';
import { useNavGroups } from '@/composables/useNavGroups';
import { Laptop2, Moon, SunMedium } from 'lucide-vue-next';

interface Props {
    variant?: 'inset' | 'sidebar' | 'floating';
    collapsible?: 'offcanvas' | 'icon' | 'none';
}

withDefaults(defineProps<Props>(), {
    variant: 'inset',
    collapsible: 'icon',
});

const { navGroups } = useNavGroups();
const { appearance, isDark, appearanceLabel, toggleAppearance, setSystem } =
    useAppearanceToggle();
</script>

<template>
    <Sidebar :collapsible="collapsible" :variant="variant">
        <SidebarHeader>
            <CompanySwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMainCollapsible :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/80 bg-sidebar/95">
            <div
                class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/70 px-3 py-2"
            >
                <div class="flex items-center gap-2">
                    <component
                        :is="isDark ? Moon : SunMedium"
                        class="size-4 text-nav-item-text"
                    />
                    <div class="flex flex-col leading-tight">
                        <span
                            class="text-[11px] tracking-wide text-nav-section-text uppercase"
                            >Appearance</span
                        >
                        <span class="text-sm font-medium text-nav-item-text">{{
                            appearanceLabel
                        }}</span>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-1">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="h-8 w-8 rounded-full text-nav-item-text hover:bg-sidebar-border/60 hover:text-nav-item-text-active"
                        @click="toggleAppearance"
                        :aria-pressed="isDark"
                        :aria-label="
                            isDark
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        "
                    >
                        <component
                            :is="isDark ? Moon : SunMedium"
                            class="size-4"
                        />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="h-8 w-8 rounded-full text-nav-item-text hover:bg-sidebar-border/60 hover:text-nav-item-text-active"
                        :class="{
                            'bg-sidebar-border/60 text-nav-item-text-active':
                                appearance === 'system',
                        }"
                        @click="setSystem"
                        aria-label="Use system appearance"
                    >
                        <Laptop2 class="size-4" />
                    </Button>
                </div>
            </div>

            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
