<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import Divider from 'primevue/divider'
import ProgressSpinner from 'primevue/progressspinner'
import Toast from 'primevue/toast'
import Tooltip from 'primevue/tooltip'

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Reactive data
const menu = ref()
const loading = ref(false)
const switching = ref(false)
const companies = ref([])
const currentCompany = ref(null)

// Options
const displayOptions = [
    { label: 'View Dashboard', icon: 'pi pi-home', action: 'dashboard' },
    { label: 'View Details', icon: 'pi pi-eye', action: 'details' },
    { label: 'Manage Users', icon: 'pi pi-users', action: 'users' },
    { label: 'Settings', icon: 'pi pi-cog', action: 'settings' }
]

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompanyData = computed(() => page.props.currentCompany)
const userCompanies = computed(() => page.props.userCompanies || [])
const hasCompanies = computed(() => companies.value.length > 0 || userCompanies.value.length > 0)
const currentCompanyName = computed(() => currentCompany.value?.name || currentCompanyData?.name || 'No Company')
const currentUserRole = computed(() => currentCompany.value?.userRole?.role || currentCompanyData?.userRole || '')

// Methods
const loadCompanies = async () => {
    // Use companies from props if available, otherwise fetch
    if (userCompanies.value.length > 0) {
        companies.value = userCompanies.value
        currentCompany.value = userCompanies.value.find(c => c.is_current) || userCompanies.value[0]
        return
    }

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
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load companies',
            life: 3000
        })
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

    switching.value = true
    try {
        const response = await fetch('/api/v1/company-context/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                company_id: company.id
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Switched to ${company.name}`,
                life: 2000
            })
            
            // Update current company locally
            currentCompany.value = company
            
            // Reload page to update context
            setTimeout(() => {
                window.location.reload()
            }, 1000)
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to switch company')
        }
    } catch (error) {
        console.error('Failed to switch company:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to switch company',
            life: 3000
        })
    } finally {
        switching.value = false
    }
}

const handleCompanyAction = (company, action) => {
    // First switch to the company
    switchCompany(company).then(() => {
        // After switching, perform the action
        setTimeout(() => {
            switch (action) {
                case 'dashboard':
                    router.visit('/dashboard')
                    break
                case 'details':
                    router.visit(`/companies/${company.id}`)
                    break
                case 'users':
                    router.visit(`/companies/${company.id}?tab=users`)
                    break
                case 'settings':
                    router.visit(`/companies/${company.id}?tab=settings`)
                    break
            }
        }, 1500)
    })
}

const getCompanyInitials = (name) => {
    if (!name) return '?'
    return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const getRoleSeverity = (role) => {
    switch (role) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        case 'accountant': return 'warning'
        case 'member': return 'secondary'
        case 'viewer': return 'secondary'
        default: return 'secondary'
    }
}

const getAvatarColor = (company) => {
    if (company.id === currentCompany.value?.id) {
        return 'bg-primary text-primary-contrast'
    }
    
    // Generate a consistent color based on company name
    const colors = [
        'bg-blue-500 text-white',
        'bg-green-500 text-white',
        'bg-purple-500 text-white',
        'bg-orange-500 text-white',
        'bg-pink-500 text-white',
        'bg-indigo-500 text-white'
    ]
    
    const index = company.name.charCodeAt(0) % colors.length
    return colors[index]
}

const formatCompanyInfo = (company) => {
    return `${company.industry} • ${company.currency || 'USD'}`
}

// Lifecycle
onMounted(() => {
    loadCompanies()
})
</script>

<template>
    <div class="company-context-switcher">
        <Toast ref="toast" />
        
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center gap-2">
            <ProgressSpinner style="width: 20px; height: 20px" strokeWidth="8" />
            <span class="text-sm text-gray-600 dark:text-gray-400">Loading...</span>
        </div>

        <!-- Company Switcher -->
        <div v-else-if="hasCompanies" class="relative">
            <!-- Current Company Display -->
            <Button
                @click="toggleMenu"
                :loading="switching"
                class="company-switcher-button"
                severity="secondary"
                outlined
            >
                <div class="flex items-center gap-3">
                    <Avatar
                        :label="getCompanyInitials(currentCompanyName)"
                        :class="getAvatarColor(currentCompany || { name: currentCompanyName, id: 'current' })"
                        size="small"
                    />
                    <div class="text-left">
                        <div class="font-medium text-sm">{{ currentCompanyName }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatCompanyInfo(currentCompany || currentCompanyData || {}) }}
                        </div>
                    </div>
                    <i class="pi pi-chevron-down text-xs"></i>
                </div>
            </Button>

            <!-- Company Dropdown Menu -->
            <Menu
                ref="menu"
                :model="companies.map(company => ({
                    label: company.name,
                    command: () => switchCompany(company),
                    template: () => companyItemTemplate(company)
                }))"
                :popup="true"
                class="company-menu"
            >
                <template #item="{ item, props }">
                    <div v-if="props" class="company-menu-item">
                        <div class="flex items-center gap-3 w-full">
                            <Avatar
                                :label="getCompanyInitials(item.label)"
                                :class="getAvatarColor(companies.find(c => c.name === item.label) || item)"
                                size="small"
                            />
                            <div class="flex-1">
                                <div class="font-medium text-sm">{{ item.label }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ formatCompanyInfo(companies.find(c => c.name === item.label) || item) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :value="companies.find(c => c.name === item.label)?.userRole || ''"
                                    :severity="getRoleSeverity(companies.find(c => c.name === item.label)?.userRole)"
                                    size="small"
                                />
                                <i
                                    v-if="currentCompany?.name === item.label || currentCompanyData?.name === item.label"
                                    class="pi pi-check text-green-500"
                                />
                            </div>
                        </div>
                        
                        <!-- Company Actions -->
                        <div v-if="currentCompany?.name !== item.label" class="company-actions mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 gap-1">
                                <Button
                                    v-for="option in displayOptions"
                                    :key="option.action"
                                    @click="handleCompanyAction(companies.find(c => c.name === item.label), option.action)"
                                    :label="option.label"
                                    :icon="option.icon"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    text
                                    class="text-xs"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Menu>
        </div>

        <!-- No Companies State -->
        <div v-else class="flex items-center gap-2">
            <Avatar
                label="?"
                class="bg-gray-400 text-white"
                size="small"
            />
            <div>
                <div class="text-sm font-medium">No Companies</div>
                <div class="text-xs text-gray-500">Create your first company</div>
            </div>
        </div>
    </div>
</template>

<script>
import { h } from 'vue'

const companyItemTemplate = (company) => {
    return h('div', { class: 'company-menu-item' }, [
        h('div', { class: 'flex items-center gap-3 w-full' }, [
            h('Avatar', {
                label: this.getCompanyInitials(company.name),
                class: this.getAvatarColor(company),
                size: 'small'
            }),
            h('div', { class: 'flex-1' }, [
                h('div', { class: 'font-medium text-sm' }, company.name),
                h('div', { class: 'text-xs text-gray-500' }, this.formatCompanyInfo(company))
            ]),
            h('div', { class: 'flex items-center gap-2' }, [
                h('Badge', {
                    value: company.userRole,
                    severity: this.getRoleSeverity(company.userRole),
                    size: 'small'
                }),
                currentCompany.value?.name === company.name ? 
                    h('i', { class: 'pi pi-check text-green-500' }) : null
            ])
        ])
    ])
}
</script>

<style scoped>
.company-context-switcher {
    @apply inline-block;
}

.company-switcher-button {
    @apply min-w-0;
}

.company-menu {
    min-width: 320px;
}

.company-menu-item {
    @apply p-3 w-full;
}

.company-menu-item:hover {
    @apply bg-gray-50 dark:bg-gray-800;
}

.company-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
}

.company-actions button {
    @apply text-xs p-1;
}

/* Dark mode adjustments */
:deep(.company-menu .p-menuitem-content) {
    @apply w-full;
}

:deep(.company-menu .p-menuitem:hover) {
    @apply bg-gray-50 dark:bg-gray-800;
}
</style>