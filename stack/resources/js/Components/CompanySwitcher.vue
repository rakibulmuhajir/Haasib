<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Divider from 'primevue/divider'
import ProgressSpinner from 'primevue/progressspinner'

const { t } = useI18n()
const page = usePage()

const menu = ref()
const loading = ref(false)
const companies = ref([])
const currentCompany = ref(null)

// Computed properties
const user = computed(() => page.props.auth?.user)
const hasCompanies = computed(() => companies.value.length > 0)
const currentCompanyName = computed(() => currentCompany.value?.name || t('companies.no_companies'))

// Methods
const loadCompanies = async () => {
    loading.value = true
    try {
        const response = await fetch('/api/v1/companies')
        const data = await response.json()
        
        if (response.ok) {
            companies.value = data.data || []
            currentCompany.value = companies.value.find(c => c.is_current) || null
        }
    } catch (error) {
        console.error('Failed to load companies:', error)
    } finally {
        loading.value = false
    }
}

const toggleMenu = (event) => {
    menu.value.toggle(event)
}

const switchCompany = async (company) => {
    if (company.id === currentCompany.value?.id) {
        return
    }

    loading.value = true
    try {
        const response = await fetch(`/api/v1/companies/${company.id}/switch`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        const data = await response.json()

        if (response.ok) {
            // Reload the page to reflect the company change
            window.location.reload()
        } else {
            console.error('Failed to switch company:', data.message)
        }
    } catch (error) {
        console.error('Error switching company:', error)
    } finally {
        loading.value = false
    }
}

const createCompany = () => {
    router.visit('/companies/create')
}

const companyItems = computed(() => {
    const items = []

    if (hasCompanies.value) {
        // Add company list
        items.push(...companies.value.map(company => ({
            label: company.name,
            icon: company.is_current ? 'fas fa-check-circle' : 'fas fa-building',
            command: () => switchCompany(company),
            class: company.is_current ? 'bg-blue-50 dark:bg-blue-900/20' : '',
            badge: company.is_current ? t('companies.current') : null
        })))

        items.push({
            separator: true
        })
    }

    // Add create company option
    items.push({
        label: t('companies.create_company'),
        icon: 'fas fa-plus',
        command: createCompany
    })

    return items
})

const getCompanyInitials = (companyName) => {
    return companyName
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

// Lifecycle
onMounted(() => {
    loadCompanies()
})
</script>

<template>
    <div class="relative">
        <!-- Company Switcher Button -->
        <Button 
            @click="toggleMenu"
            :loading="loading"
            text
            size="small"
            class="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
            <div v-if="currentCompany" class="flex items-center space-x-2">
                <Avatar 
                    :label="getCompanyInitials(currentCompany.name)"
                    size="small"
                    :style="{ backgroundColor: '#3B82F6', color: 'white' }"
                />
                <div class="text-left">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ currentCompany.name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ currentCompany.role }}
                    </div>
                </div>
            </div>
            <div v-else class="flex items-center space-x-2">
                <i class="fas fa-building text-gray-500"></i>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ t('companies.no_companies') }}
                </span>
            </div>
            <i class="fas fa-chevron-down text-xs text-gray-500 ml-1"></i>
        </Button>

        <!-- Loading State -->
        <div v-if="loading && !companies.length" class="flex items-center space-x-2 px-3 py-2">
            <ProgressSpinner style="width: 16px; height: 16px;" strokeWidth="4" />
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('common.loading') }}</span>
        </div>

        <!-- Company Menu -->
        <Menu 
            ref="menu" 
            :model="companyItems" 
            popup
            :style="{ minWidth: '250px' }"
        >
            <template #itemicon="{ item }">
                <i v-if="item.icon" :class="item.icon" class="mr-2"></i>
            </template>
            <template #item="{ item, props }">
                <a v-ripple class="flex items-center" v-bind="props.action">
                    <i v-if="item.icon" :class="item.icon" class="mr-2 text-gray-600 dark:text-gray-400"></i>
                    <span class="flex-1">{{ item.label }}</span>
                    <Badge v-if="item.badge" :value="item.badge" severity="success" size="small" />
                </a>
            </template>
        </Menu>

        <!-- No Companies State (when menu is empty) -->
        <div v-if="!loading && !hasCompanies" class="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4 z-50">
            <div class="text-center">
                <i class="fas fa-building text-3xl text-gray-400 mb-3"></i>
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                    {{ t('companies.no_companies') }}
                </h4>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">
                    Create your first company to get started
                </p>
                <Button 
                    @click="createCompany"
                    icon="fas fa-plus"
                    :label="t('companies.create_company')"
                    size="small"
                    class="w-full"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for menu item hover states */
:deep(.p-menuitem-link) {
    border-radius: 0.375rem;
    margin: 0.125rem;
}

:deep(.p-menuitem-link:hover) {
    background-color: rgb(243 244 246);
}

.dark :deep(.p-menuitem-link:hover) {
    background-color: rgb(55 65 81);
}

:deep(.p-menuitem-link.active) {
    background-color: rgb(239 246 255);
}

.dark :deep(.p-menuitem-link.active) {
    background-color: rgb(30 58 138 / 0.2);
}
</style>