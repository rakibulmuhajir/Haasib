<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Menu from 'primevue/menu'
import Divider from 'primevue/divider'
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'
import Toast from 'primevue/toast'
import ProgressSpinner from 'primevue/progressspinner'
import CompanyContextSwitcher from '../Components/Company/ContextSwitcher.vue'
import { useCompanyContext } from '../composables/useCompanyContext'

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Use company context composable
const {
    currentCompany,
    userCompanies,
    user,
    permissions,
    canCreateCompany,
    hasPermission,
    canPerformAction,
    getCompanyAvatarData,
    formatCompanyDisplay
} = useCompanyContext()

// Reactive data
const collapsed = ref(false)
const activeMenuItems = ref(new Set())
const userMenu = ref()
const companyMenu = ref()

// Computed properties
const hasCompanies = computed(() => userCompanies.value.length > 0)
const userInitials = computed(() => {
    if (!user.value?.name) return '?'
    return user.value.name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
})

// Navigation items configuration
const mainNavigation = computed(() => {
    const items = [
        {
            label: 'Dashboard',
            icon: 'pi pi-home',
            route: '/dashboard',
            permission: null // Always visible
        },
        {
            label: 'Companies',
            icon: 'pi pi-building',
            route: '/companies',
            permission: null // Always visible
        },
        {
            label: 'Users',
            icon: 'pi pi-users',
            route: '#',
            permission: 'canViewUsers',
            subItems: [
                {
                    label: 'All Users',
                    route: '/companies/users',
                    permission: 'canViewUsers'
                },
                {
                    label: 'Invitations',
                    route: '/companies/invitations',
                    permission: 'canInvite'
                }
            ]
        }
    ]

    // Add company-specific items if a company is selected
    if (currentCompany.value) {
        items.push(
            {
                label: 'Invoicing',
                icon: 'pi pi-file',
                route: '/invoices',
                permission: 'canAccessInvoicing'
            },
            {
                label: 'Accounting',
                icon: 'pi pi-calculator',
                route: '/accounting',
                permission: 'canAccessAccounting'
            },
            {
                label: 'Reports',
                icon: 'pi pi-chart-bar',
                route: '/reports',
                permission: 'canViewReports'
            },
            {
                label: 'Settings',
                icon: 'pi pi-cog',
                route: '/settings',
                permission: 'canViewSettings'
            }
        )
    }

    return items.filter(item => 
        !item.permission || hasPermission(item.permission)
    )
})

const companySettings = computed(() => {
    if (!currentCompany.value) return []

    return [
        {
            label: 'Company Details',
            icon: 'pi pi-info-circle',
            route: `/companies/${currentCompany.value.id}`,
            permission: null
        },
        {
            label: 'Fiscal Year',
            icon: 'pi pi-calendar',
            route: `/companies/${currentCompany.value.id}/fiscal-year`,
            permission: 'canManage'
        },
        {
            label: 'Modules',
            icon: 'pi pi-th-large',
            route: `/companies/${currentCompany.value.id}/modules`,
            permission: 'canManage'
        },
        {
            label: 'Audit Log',
            icon: 'pi pi-history',
            route: `/companies/${currentCompany.value.id}/audit`,
            permission: 'canManage'
        }
    ].filter(item => 
        !item.permission || hasPermission(item.permission)
    )
})

// Methods
const toggleCollapse = () => {
    collapsed.value = !collapsed.value
}

const toggleMenuItem = (item) => {
    if (activeMenuItems.value.has(item.label)) {
        activeMenuItems.value.delete(item.label)
    } else {
        activeMenuItems.value.add(item.label)
    }
}

const handleLogout = async () => {
    try {
        await fetch('/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        window.location.href = '/'
    } catch (error) {
        console.error('Logout failed:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to logout',
            life: 3000
        })
    }
}

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event)
}

const toggleCompanyMenu = (event) => {
    companyMenu.value.toggle(event)
}

const isMenuItemActive = (item) => {
    const currentPath = window.location.pathname
    if (item.route === currentPath) return true
    if (item.route && currentPath.startsWith(item.route)) return true
    if (item.subItems) {
        return item.subItems.some(subItem => 
            subItem.route === currentPath || currentPath.startsWith(subItem.route)
        )
    }
    return false
}

const isSubMenuItemActive = (item) => {
    const currentPath = window.location.pathname
    return item.route === currentPath || currentPath.startsWith(item.route)
}

const getMenuItemClasses = (item) => {
    const isActive = isMenuItemActive(item)
    const isExpanded = activeMenuItems.value.has(item.label)
    
    return [
        'flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors',
        collapsed.value ? 'justify-center' : '',
        isActive 
            ? 'bg-primary text-primary-contrast' 
            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
    ]
}

const getSubMenuItemClasses = (item) => {
    const isActive = isSubMenuItemActive(item)
    return [
        'flex items-center gap-3 px-8 py-2 rounded-md text-sm transition-colors',
        collapsed.value ? 'justify-center px-4' : '',
        isActive
            ? 'bg-primary/20 text-primary dark:bg-primary/10'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800'
    ]
}
</script>

<template>
    <div class="sidebar-container">
        <Toast ref="toast" />
        
        <!-- Sidebar -->
        <aside 
            :class="[
                'h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col transition-all duration-300',
                collapsed ? 'w-20' : 'w-64'
            ]"
        >
            <!-- Logo/Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                        <i class="pi pi-building text-white text-lg"></i>
                    </div>
                    <div v-if="!collapsed" class="font-bold text-lg text-gray-900 dark:text-white">
                        Haasib
                    </div>
                </div>
                <Button
                    @click="toggleCollapse"
                    icon="pi pi-bars"
                    severity="secondary"
                    outlined
                    size="small"
                />
            </div>

            <!-- Company Context Switcher -->
            <div v-if="hasCompanies" class="p-4 border-b border-gray-200 dark:border-gray-700">
                <CompanyContextSwitcher />
            </div>

            <!-- No Company State -->
            <div v-else class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="text-center">
                    <Avatar
                        label="?"
                        class="bg-gray-400 text-white mx-auto mb-2"
                    />
                    <p v-if="!collapsed" class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        No companies yet
                    </p>
                    <Link v-if="canCreateCompany" href="/companies/create">
                        <Button
                            v-if="!collapsed"
                            icon="pi pi-plus"
                            label="Create Company"
                            size="small"
                            class="w-full"
                        />
                        <Button
                            v-else
                            icon="pi pi-plus"
                            size="small"
                            v-tooltip="'Create Company'"
                        />
                    </Link>
                </div>
            </div>

            <!-- Main Navigation -->
            <div class="flex-1 overflow-y-auto p-4">
                <Accordion :activeIndex="activeMenuItems" @tab-open="toggleMenuItem">
                    <!-- Basic Navigation (no accordion) -->
                    <nav class="space-y-2">
                        <Link
                            v-for="item in mainNavigation"
                            :key="item.label"
                            :href="item.route"
                            :class="getMenuItemClasses(item)"
                        >
                            <i :class="item.icon" />
                            <span v-if="!collapsed" class="flex-1">{{ item.label }}</span>
                            <i
                                v-if="!collapsed && item.subItems"
                                :class="[
                                    'pi transition-transform',
                                    activeMenuItems.has(item.label) ? 'pi-chevron-down' : 'pi-chevron-right'
                                ]"
                            />
                        </Link>
                    </nav>

                    <!-- Sub Items for expanded menu items -->
                    <nav
                        v-for="item in mainNavigation.filter(item => item.subItems && activeMenuItems.has(item.label))"
                        :key="item.label + '-sub'"
                        class="mt-1 space-y-1"
                    >
                        <Link
                            v-for="subItem in item.subItems"
                            :key="subItem.label"
                            :href="subItem.route"
                            :class="getSubMenuItemClasses(subItem)"
                        >
                            <i :class="subItem.icon" />
                            <span v-if="!collapsed">{{ subItem.label }}</span>
                        </Link>
                    </nav>

                    <!-- Company Settings (only when company is selected) -->
                    <div v-if="currentCompany && companySettings.length > 0" class="mt-6">
                        <h3 v-if="!collapsed" class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                            Company Settings
                        </h3>
                        <nav class="space-y-2">
                            <Link
                                v-for="item in companySettings"
                                :key="item.label"
                                :href="item.route"
                                :class="getMenuItemClasses(item)"
                            >
                                <i :class="item.icon" />
                                <span v-if="!collapsed" class="flex-1">{{ item.label }}</span>
                            </Link>
                        </nav>
                    </div>
                </Accordion>
            </div>

            <!-- User Menu -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <Menu ref="userMenu" :model="userMenuItems" :popup="true">
                    <template #start>
                        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <Avatar
                                    :label="userInitials"
                                    class="bg-gray-600 text-white"
                                />
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ user?.name || 'User' }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ user?.email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </Menu>

                <div class="flex items-center gap-3 cursor-pointer" @click="toggleUserMenu">
                    <Avatar
                        :label="userInitials"
                        class="bg-gray-600 text-white"
                    />
                    <div v-if="!collapsed" class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ user?.name || 'User' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ user?.system_role || 'employee' }}
                        </p>
                    </div>
                    <i v-if="!collapsed" class="pi pi-chevron-down text-gray-400"></i>
                </div>
            </div>
        </aside>

        <!-- Mobile Overlay -->
        <div 
            v-if="!collapsed && false" 
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
            @click="toggleCollapse"
        />
    </div>
</template>

<script>
const userMenuItems = [
    {
        label: 'Profile',
        icon: 'pi pi-user',
        command: () => {
            // Navigate to profile
            window.location.href = '/profile'
        }
    },
    {
        label: 'Settings',
        icon: 'pi pi-cog',
        command: () => {
            // Navigate to settings
            window.location.href = '/settings'
        }
    },
    {
        separator: true
    },
    {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        command: () => {
            // Handle logout
            this.handleLogout()
        }
    }
]
</script>

<style scoped>
.sidebar-container {
    @apply relative;
}

/* Custom scrollbar for sidebar */
.sidebar-container aside::-webkit-scrollbar {
    width: 6px;
}

.sidebar-container aside::-webkit-scrollbar-track {
    @apply bg-gray-100 dark:bg-gray-800;
}

.sidebar-container aside::-webkit-scrollbar-thumb {
    @apply bg-gray-300 dark:bg-gray-600 rounded-full;
}

.sidebar-container aside::-webkit-scrollbar-thumb:hover {
    @apply bg-gray-400 dark:bg-gray-500;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .sidebar-container aside {
        @apply fixed left-0 top-0 z-50 transform transition-transform;
    }
    
    .sidebar-container aside.collapsed {
        @apply -translate-x-full;
    }
}
</style>