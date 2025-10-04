<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LayoutShell from '@/Components/Layout/LayoutShell.vue';
import Sidebar from '@/Components/Sidebar/Sidebar.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CurrencySettings from './Partials/CurrencySettings.vue';
import { usePageActions } from '@/composables/usePageActions.js';
import { usePermissions } from '@/composables/usePermissions';
import TabMenu from 'primevue/tabmenu';

const page = usePage();
const activeTab = ref(0);
const { setActions } = usePageActions();
const { can, hasRole, currentCompanyId } = usePermissions();
const { t, tm } = useI18n();

const baseTabConfig = [
    {
        icon: 'fas fa-cog',
        route: route('settings.index', { group: 'general' }),
        key: 'general'
    },
    {
        icon: 'fas fa-dollar-sign',
        route: route('settings.index', { group: 'currency' }),
        key: 'currency'
    },
    {
        icon: 'fas fa-bell',
        route: route('settings.index', { group: 'notifications' }),
        key: 'notifications'
    },
    {
        icon: 'fas fa-palette',
        route: route('settings.index', { group: 'appearance' }),
        key: 'appearance'
    },
    {
        icon: 'fas fa-lock',
        route: route('settings.index', { group: 'security' }),
        key: 'security'
    },
];

const tabs = computed(() => {
    const localized = baseTabConfig.map(tab => ({
        ...tab,
        label: t(`settings.tabs.${tab.key}`)
    }));

    if (can.assignRoles() && currentCompanyId.value) {
        localized.push({
            icon: 'fas fa-user-shield',
            route: route('companies.roles.index', currentCompanyId.value),
            key: 'roles',
            label: t('settings.tabs.roles')
        });
    }

    return localized;
});

const breadcrumbItems = computed(() => [
    { label: t('settings.breadcrumb.home'), url: '/dashboard', icon: 'home' },
    { label: t('settings.breadcrumb.settings'), url: '/settings', icon: 'settings' }
]);

const listFrom = (key) => {
    const value = tm(key);
    return Array.isArray(value) ? value : [];
};

const generalComingItems = computed(() => listFrom('settings.cards.general.comingSoon.items'));
const notificationComingItems = computed(() => listFrom('settings.cards.notifications.comingSoon.items'));
const appearanceComingItems = computed(() => listFrom('settings.cards.appearance.comingSoon.items'));
const securityComingItems = computed(() => listFrom('settings.cards.security.comingSoon.items'));

// Set active tab based on URL
onMounted(() => {
    // Parse URL search parameters
    const urlParams = new URLSearchParams(window.location.search);
    const group = urlParams.get('group') || 'general';
    const tabIndex = tabs.value.findIndex(tab => tab.key === group);
    activeTab.value = tabIndex >= 0 ? tabIndex : 0;
    
    // Set page actions if needed
    setActions([
        // Add any page-specific actions here
    ]);
});

// Handle tab change
function onTabChange(event) {
    const tab = tabs.value[event.index];
    // Update URL with search parameter
    const url = new URL(window.location);
    if (tab.key === 'general') {
        url.searchParams.delete('group');
    } else {
        url.searchParams.set('group', tab.key);
    }
    router.visit(url.toString());
}
</script>

<template>
    <Head :title="t('settings.pageTitle')" />

    <LayoutShell>
        <template #sidebar>
            <Sidebar :title="t('settings.header.title')" />
        </template>

        <template #topbar>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="space-y-4">
            <PageHeader
                :title="t('settings.header.title')"
                :subtitle="t('settings.header.subtitle')"
            />

            <!-- Tab Navigation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <TabMenu 
                    :model="tabs" 
                    :activeIndex="activeTab"
                    @tab-change="onTabChange"
                />
                
                <!-- Tab Content -->
                <div class="p-6">
                    <!-- General Settings -->
                    <div v-show="tabs[activeTab]?.key === 'general'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">{{ t('settings.cards.general.title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('settings.cards.general.description') }}
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">{{ t('settings.cards.general.comingSoon.title') }}</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>{{ t('settings.cards.general.comingSoon.intro') }}</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li v-for="item in generalComingItems" :key="item">{{ item }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Currency Settings -->
                    <div v-show="tabs[activeTab]?.key === 'currency'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">{{ t('settings.cards.currency.title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('settings.cards.currency.description') }}
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <CurrencySettings :user="$page.props.auth.user" />
                        </div>
                    </div>

                    <!-- Notifications Settings -->
                    <div v-show="tabs[activeTab]?.key === 'notifications'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">{{ t('settings.cards.notifications.title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('settings.cards.notifications.description') }}
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">{{ t('settings.cards.notifications.comingSoon.title') }}</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>{{ t('settings.cards.notifications.comingSoon.intro') }}</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li v-for="item in notificationComingItems" :key="item">{{ item }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appearance Settings -->
                    <div v-show="tabs[activeTab]?.key === 'appearance'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">{{ t('settings.cards.appearance.title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('settings.cards.appearance.description') }}
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">{{ t('settings.cards.appearance.comingSoon.title') }}</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>{{ t('settings.cards.appearance.comingSoon.intro') }}</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li v-for="item in appearanceComingItems" :key="item">{{ item }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div v-show="tabs[activeTab]?.key === 'security'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">{{ t('settings.cards.security.title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('settings.cards.security.description') }}
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">{{ t('settings.cards.security.comingSoon.title') }}</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>{{ t('settings.cards.security.comingSoon.intro') }}</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li v-for="item in securityComingItems" :key="item">{{ item }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
