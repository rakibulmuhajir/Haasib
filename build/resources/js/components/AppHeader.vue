<script setup lang="ts">
/**
 * The horizontal shell.
 *
 * This file used to be the Laravel starter kit's header, untouched: a one-item
 * nav and two links to Laravel's own repository and docs. Nothing in the app
 * rendered it, so nobody noticed. It now carries what the sidebar carried —
 * company switcher, the real navigation, appearance, the user menu — and adds
 * a search control, because a horizontal bar has no room to list forty
 * destinations and the command palette already knows all of them.
 *
 * Rules, not elevation: the bar is separated from the page by a border, and
 * only the dropdowns and the sheet — surfaces that genuinely float — carry a
 * shadow.
 */
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CompanySwitcherItems from '@/components/CompanySwitcherItems.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearanceToggle } from '@/composables/useAppearanceToggle';
import { useCompanySwitcher } from '@/composables/useCompanySwitcher';
import { getInitials } from '@/composables/useInitials';
import { useNavGroups } from '@/composables/useNavGroups';
import { usePaletteVisibility } from '@/composables/usePaletteVisibility';
import { activeNavHref } from '@/navigation/active';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItemType, NavGroup, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChevronDown,
    Laptop2,
    Menu,
    Moon,
    Search,
    SunMedium,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    { breadcrumbs: () => [] },
);

const page = usePage();
const auth = computed(() => page.props.auth as any);

const { navGroups, isFuelStationCompany } = useNavGroups();
const mobileNavOpen = ref(false);
watch(() => page.url, () => { mobileNavOpen.value = false; });
const mobileShortcuts = computed(() => isFuelStationCompany.value
    ? navGroups.value.filter(group => ['Daily Close', 'Stock'].includes(group.label)).map(group => ({ ...group.items[0], title: group.label, group }))
    : []);
const { currentCompany } = useCompanySwitcher();
const { appearance, isDark, appearanceLabel, toggleAppearance, setSystem } =
    useAppearanceToggle();
const { open: openPalette } = usePaletteVisibility();

const activeHref = computed(() => activeNavHref(navGroups.value, page.url));
const isActive = (item: NavItem): boolean =>
    (!!item.href && toUrl(item.href) === activeHref.value) || (item.children ?? []).some(isActive);

const groupIsActive = (group: NavGroup) => group.items.some(isActive);

/**
 * A group holding one destination is a link, not a menu. Making the reader open
 * a dropdown to find its only item is a click spent on nothing.
 */
const isDirectLink = (group: NavGroup) =>
    group.items.length === 1 && !group.items[0].children?.length && !!group.items[0].href;

// The palette's shortcut, spelled the way the reader's keyboard spells it.
const shortcut = computed(() =>
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)
        ? '⌘K'
        : 'Ctrl K',
);

const showSecondRow = computed(() => props.breadcrumbs.length > 0);
</script>

<template>
    <header class="sticky top-0 z-40 bg-surface-raised">
        <div class="border-b border-rule-default">
            <div class="mx-auto flex h-14 w-full items-center gap-2 px-4 lg:px-8">
                <!-- Mobile: the whole navigation, flat. NavMainCollapsible is
                     bound to sidebar primitives and cannot come along. -->
                <Sheet v-model:open="mobileNavOpen">
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="xl:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[300px] overflow-y-auto p-0">
                        <SheetHeader class="border-b border-rule-subtle px-4 py-3">
                            <SheetTitle class="flex items-center gap-2 text-left">
                                <AppLogoIcon class="size-5 fill-current" />
                                {{ currentCompany?.name || 'Haasib' }}
                            </SheetTitle>
                        </SheetHeader>

                        <nav aria-label="Main navigation" class="px-2 py-3">
                            <div v-for="group in navGroups" :key="group.label" class="mb-4">
                                <p
                                    class="px-2 pb-1 text-[11px] tracking-wide text-text-metadata uppercase"
                                >
                                    {{ group.label }}
                                </p>
                                <template v-for="item in group.items" :key="item.title">
                                    <Link
                                        v-if="item.href"
                                        :href="item.href"
                                        class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-sunken"
                                        :class="
                                            isActive(item)
                                                ? 'bg-surface-sunken font-medium text-text-primary'
                                                : 'text-text-secondary'
                                        "
                                    >
                                        <component :is="item.icon" v-if="item.icon" class="size-4" />
                                        {{ item.title }}
                                    </Link>
                                    <p
                                        v-else
                                        class="px-2 pt-2 pb-1 text-xs font-medium text-text-secondary"
                                    >
                                        {{ item.title }}
                                    </p>
                                    <Link
                                        v-for="child in item.children ?? []"
                                        :key="child.title"
                                        :href="child.href!"
                                        class="ml-4 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-sunken"
                                        :class="
                                            isActive(child)
                                                ? 'bg-surface-sunken font-medium text-text-primary'
                                                : 'text-text-secondary'
                                        "
                                    >
                                        {{ child.title }}
                                    </Link>
                                </template>
                            </div>
                        </nav>
                    </SheetContent>
                </Sheet>

                <Link :href="dashboard()" class="flex shrink-0 items-center" aria-label="Haasib">
                    <AppLogo />
                </Link>

                <!-- Company switcher, header-shaped: the name only, since the
                     slug is already the first segment of the address bar. -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="hidden max-w-36 gap-2 px-2 sm:inline-flex"
                        >
                            <Building2 class="size-4 shrink-0" />
                            <span class="truncate">{{
                                currentCompany?.name || 'Select company'
                            }}</span>
                            <ChevronDown class="size-4 shrink-0 opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="min-w-64">
                        <CompanySwitcherItems />
                    </DropdownMenuContent>
                </DropdownMenu>

                <nav class="hidden min-w-0 flex-1 items-center gap-0.5 xl:flex">
                    <template v-for="group in navGroups" :key="group.label">
                        <Link
                            v-if="isDirectLink(group)"
                            :href="group.items[0].href!"
                            class="shrink-0 whitespace-nowrap rounded-none border-b-2 px-2 py-1.5 text-sm hover:bg-surface-sunken"
                            :class="
                                groupIsActive(group)
                                    ? 'border-status-critical font-medium text-text-primary'
                                    : 'border-transparent text-text-secondary'
                            "
                        >
                            {{ group.items[0].title }}
                        </Link>

                        <DropdownMenu v-else>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    type="button"
                                    class="flex shrink-0 items-center gap-1 whitespace-nowrap rounded-none border-b-2 px-2 py-1.5 text-sm hover:bg-surface-sunken data-[state=open]:bg-surface-sunken"
                                    :class="
                                        groupIsActive(group)
                                            ? 'border-status-critical font-medium text-text-primary'
                                            : 'border-transparent text-text-secondary'
                                    "
                                >
                                    {{ group.label }}
                                    <ChevronDown class="size-3.5 opacity-60" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="min-w-56">
                                <template v-for="(item, index) in group.items" :key="item.title">
                                    <!-- A sub-list is fenced off from what came
                                         before it, not from what follows — a
                                         separator after the last one is a rule
                                         drawn under nothing. -->
                                    <DropdownMenuSeparator
                                        v-if="item.children?.length && index > 0"
                                    />
                                    <DropdownMenuItem
                                        v-if="item.href"
                                        as-child
                                        :class="isActive(item) ? 'font-medium' : ''"
                                    >
                                        <Link :href="item.href" class="flex items-center gap-2">
                                            <component
                                                :is="item.icon"
                                                v-if="item.icon"
                                                class="size-4"
                                            />
                                            <span class="flex-1">{{ item.title }}</span>
                                            <span
                                                v-if="item.badge"
                                                class="text-xs text-text-metadata"
                                                >{{ item.badge }}</span
                                            >
                                        </Link>
                                    </DropdownMenuItem>

                                    <template v-if="item.children?.length">
                                        <DropdownMenuLabel
                                            v-if="!item.href"
                                            class="text-xs text-text-metadata"
                                            >{{ item.title }}</DropdownMenuLabel
                                        >
                                        <DropdownMenuItem
                                            v-for="child in item.children"
                                            :key="child.title"
                                            as-child
                                            :class="isActive(child) ? 'font-medium' : ''"
                                        >
                                            <Link :href="child.href!">{{ child.title }}</Link>
                                        </DropdownMenuItem>
                                    </template>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <!-- Search opens the command palette rather than a second
                         search of its own; the shortcut is printed so the
                         keyboard route is discoverable from the mouse route. -->
                    <Button
                        variant="ghost"
                        class="gap-2 px-2 text-text-secondary"
                        aria-label="Search"
                        @click="openPalette"
                    >
                        <Search class="size-4" />
                        <span class="hidden text-sm 2xl:inline">Search</span>
                        <kbd
                            class="hidden rounded-sm border border-rule-subtle px-1.5 font-mono text-[11px] text-text-metadata 2xl:inline"
                            >{{ shortcut }}</kbd
                        >
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="appearanceLabel"
                            >
                                <component :is="isDark ? Moon : SunMedium" class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-44">
                            <DropdownMenuLabel class="text-xs text-text-metadata">{{
                                appearanceLabel
                            }}</DropdownMenuLabel>
                            <DropdownMenuItem @click="toggleAppearance">
                                <component :is="isDark ? SunMedium : Moon" class="size-4" />
                                {{ isDark ? 'Light mode' : 'Dark mode' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                :class="appearance === 'system' ? 'font-medium' : ''"
                                @click="setSystem"
                            >
                                <Laptop2 class="size-4" />
                                Match system
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="rounded-full"
                                aria-label="Account"
                            >
                                <Avatar class="size-8">
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-surface-sunken font-semibold text-text-primary"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <nav v-if="isFuelStationCompany" aria-label="Station shortcuts" class="flex items-center gap-2 border-b border-rule-subtle px-4 py-2 xl:hidden">
            <Button v-for="item in mobileShortcuts" :key="item.title" variant="ghost" as-child>
                <Link :href="item.href!" :class="groupIsActive(item.group) ? 'bg-surface-sunken font-medium' : ''">{{ item.title }}</Link>
            </Button>
            <Button variant="ghost" @click="mobileNavOpen = true" aria-label="More navigation">More <ChevronDown class="ml-1 size-4" /></Button>
        </nav>

        <div v-if="showSecondRow" class="border-b border-rule-subtle bg-surface-canvas">
            <div class="mx-auto flex h-11 w-full items-center gap-4 px-4 lg:px-8">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
                <div class="ml-auto flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </header>
</template>
