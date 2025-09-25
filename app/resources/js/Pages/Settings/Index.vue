<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import LayoutShell from '@/Components/Layout/LayoutShell.vue';
import Sidebar from '@/Components/Sidebar/Sidebar.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CurrencySettings from './Partials/CurrencySettings.vue';
import { usePageActions } from '@/composables/usePageActions.js';
import TabMenu from 'primevue/tabmenu';

const page = usePage();
const activeTab = ref(0);
const { setActions } = usePageActions();

// Define tabs
const tabs = ref([
    { 
        label: 'General', 
        icon: 'fas fa-cog',
        route: route('settings.index', { group: 'general' }),
        key: 'general'
    },
    { 
        label: 'Currency', 
        icon: 'fas fa-dollar-sign',
        route: route('settings.index', { group: 'currency' }),
        key: 'currency'
    },
    { 
        label: 'Notifications', 
        icon: 'fas fa-bell',
        route: route('settings.index', { group: 'notifications' }),
        key: 'notifications'
    },
    { 
        label: 'Appearance', 
        icon: 'fas fa-palette',
        route: route('settings.index', { group: 'appearance' }),
        key: 'appearance'
    },
    { 
        label: 'Security', 
        icon: 'fas fa-lock',
        route: route('settings.index', { group: 'security' }),
        key: 'security'
    }
]);

// Breadcrumb items
const breadcrumbItems = ref([
    { label: 'Home', url: '/dashboard', icon: 'home' },
    { label: 'Settings', url: '/settings', icon: 'settings' }
]);

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
    <Head title="Settings" />

    <LayoutShell>
        <template #sidebar>
            <Sidebar title="Settings" />
        </template>

        <template #topbar>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="space-y-4">
            <PageHeader title="Settings" subtitle="Manage your account preferences and application settings" />

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
                            <h3 class="text-lg font-medium">General Settings</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage your general account preferences.
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Coming Soon</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>General settings will include:</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li>Account profile information</li>
                                                <li>Default company selection</li>
                                                <li>Time zone and regional settings</li>
                                                <li>Language preferences</li>
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
                            <h3 class="text-lg font-medium">Currency Settings</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage your currency preferences and exchange rates.
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <CurrencySettings :user="$page.props.auth.user" />
                        </div>
                    </div>

                    <!-- Notifications Settings -->
                    <div v-show="tabs[activeTab]?.key === 'notifications'" class="space-y-6">
                        <div class="text-gray-900 dark:text-white">
                            <h3 class="text-lg font-medium">Notification Settings</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Configure how you receive notifications.
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Coming Soon</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>Notification settings will include:</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li>Email notifications for invoices</li>
                                                <li>Payment reminders</li>
                                                <li>System alerts and updates</li>
                                                <li>Push notification preferences</li>
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
                            <h3 class="text-lg font-medium">Appearance Settings</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Customize the look and feel of the application.
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Coming Soon</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>Appearance settings will include:</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li>Dark/light theme toggle</li>
                                                <li>Custom color schemes</li>
                                                <li>Compact vs spacious layout</li>
                                                <li>Font size preferences</li>
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
                            <h3 class="text-lg font-medium">Security Settings</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage your account security preferences.
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Coming Soon</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>Security settings will include:</p>
                                            <ul class="list-disc list-inside mt-2 space-y-1">
                                                <li>Two-factor authentication</li>
                                                <li>Password change requirements</li>
                                                <li>Session management</li>
                                                <li>Login history</li>
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