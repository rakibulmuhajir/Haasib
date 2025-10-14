<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import Divider from 'primevue/divider'
import Badge from 'primevue/badge'
import ProgressSpinner from 'primevue/progressspinner'
import Message from 'primevue/message'

const { t } = useI18n()
const page = usePage()

const emit = defineEmits(['moduleToggled'])

const modulesDialog = ref(false)
const loading = ref(false)
const error = ref('')
const modules = ref([])
const availableModules = ref([
    {
        id: 'invoicing',
        name: 'Invoicing',
        description: 'Create and manage invoices, track payments, and generate reports',
        icon: 'fas fa-file-invoice',
        category: 'billing',
        required_permissions: ['invoices.create', 'invoices.view'],
        dependencies: [],
        features: ['Invoice creation', 'Payment tracking', 'Auto-numbering', 'PDF generation']
    },
    {
        id: 'payments',
        name: 'Payment Processing',
        description: 'Process payments, manage payment methods, and reconcile transactions',
        icon: 'fas fa-credit-card',
        category: 'billing',
        required_permissions: ['payments.create', 'payments.view'],
        dependencies: ['invoicing'],
        features: ['Payment recording', 'Multiple payment methods', 'Reconciliation', 'Refunds']
    },
    {
        id: 'reporting',
        name: 'Reporting & Analytics',
        description: 'Generate financial reports, analytics, and business insights',
        icon: 'fas fa-chart-bar',
        category: 'analytics',
        required_permissions: ['reports.view', 'reports.export'],
        dependencies: [],
        features: ['Financial reports', 'Custom dashboards', 'Data export', 'Scheduled reports']
    },
    {
        id: 'inventory',
        name: 'Inventory Management',
        description: 'Track inventory, manage stock levels, and handle product catalog',
        icon: 'fas fa-boxes',
        category: 'operations',
        required_permissions: ['inventory.view', 'inventory.manage'],
        dependencies: [],
        features: ['Stock tracking', 'Product catalog', 'Low stock alerts', 'Barcoding']
    },
    {
        id: 'time_tracking',
        name: 'Time Tracking',
        description: 'Track employee time, manage projects, and generate timesheets',
        icon: 'fas fa-clock',
        category: 'operations',
        required_permissions: ['time.view', 'time.manage'],
        dependencies: [],
        features: ['Time entry', 'Project tracking', 'Timesheet approval', 'Billing integration']
    },
    {
        id: 'hr',
        name: 'Human Resources',
        description: 'Manage employees, payroll, and HR operations',
        icon: 'fas fa-users',
        category: 'management',
        required_permissions: ['hr.view', 'hr.manage'],
        dependencies: [],
        features: ['Employee management', 'Leave tracking', 'Performance reviews', 'Document storage']
    }
])

// Computed properties
const currentCompany = computed(() => page.props.current_company)
const user = computed(() => page.props.auth?.user)
const enabledModulesCount = computed(() => modules.value.filter(m => m.enabled).length)
const totalModulesCount = computed(() => availableModules.value.length)

// Methods
const loadModules = async () => {
    loading.value = true
    error.value = ''
    
    try {
        const response = await fetch('/api/v1/modules')
        const data = await response.json()
        
        if (response.ok) {
            modules.value = data.data || []
            // Merge available modules with enabled status
            modules.value = availableModules.map(available => {
                const installed = modules.value.find(m => m.id === available.id)
                return {
                    ...available,
                    enabled: installed?.enabled || false,
                    status: installed?.status || 'available',
                    last_enabled_at: installed?.last_enabled_at,
                    disabled_reason: installed?.disabled_reason
                }
            })
        } else {
            error.value = data.message || 'Failed to load modules'
        }
    } catch (error) {
        console.error('Failed to load modules:', error)
        error.value = 'Network error. Please try again.'
    } finally {
        loading.value = false
    }
}

const toggleModule = async (moduleId) => {
    const module = modules.value.find(m => m.id === moduleId)
    if (!module) return

    const newState = !module.enabled
    const action = newState ? 'enable' : 'disable'

    // Check dependencies
    if (newState && module.dependencies.length > 0) {
        const missingDeps = module.dependencies.filter(dep => 
            !modules.value.find(m => m.id === dep)?.enabled
        )
        if (missingDeps.length > 0) {
            error.value = `This module requires: ${missingDeps.join(', ')}`
            return
        }
    }

    // Check permissions
    if (newState && module.required_permissions.length > 0) {
        const userPermissions = user.value?.permissions || []
        const missingPerms = module.required_permissions.filter(perm => 
            !userPermissions.includes(perm)
        )
        if (missingPerms.length > 0) {
            error.value = `You need these permissions: ${missingPerms.join(', ')}`
            return
        }
    }

    loading.value = true
    error.value = ''

    try {
        const response = await fetch(`/api/v1/modules/${moduleId}/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        const data = await response.json()

        if (response.ok) {
            // Update module status
            module.enabled = newState
            module.status = newState ? 'enabled' : 'disabled'
            module.last_enabled_at = newState ? new Date().toISOString() : null
            
            emit('moduleToggled', {
                moduleId,
                enabled: newState,
                module
            })

            // Show success message briefly
            setTimeout(() => {
                error.value = ''
            }, 3000)
        } else {
            error.value = data.message || `Failed to ${action} module`
        }
    } catch (error) {
        console.error(`Failed to ${action} module:`, error)
        error.value = `Network error. Could not ${action} module.`
    } finally {
        loading.value = false
    }
}

const getModuleIcon = (module) => {
    return module.icon || 'fas fa-cube'
}

const getModuleStatus = (module) => {
    if (module.enabled) {
        return { label: 'Enabled', severity: 'success' }
    } else if (module.status === 'error') {
        return { label: 'Error', severity: 'danger' }
    } else if (module.status === 'maintenance') {
        return { label: 'Maintenance', severity: 'warning' }
    } else {
        return { label: 'Disabled', severity: 'secondary' }
    }
}

const canToggleModule = (module) => {
    // User can disable their own enabled modules
    if (module.enabled) {
        return true
    }
    
    // Check if user has required permissions to enable
    if (module.required_permissions.length > 0) {
        const userPermissions = user.value?.permissions || []
        return module.required_permissions.every(perm => userPermissions.includes(perm))
    }
    
    return true
}

const openModulesDialog = () => {
    modulesDialog.value = true
    loadModules()
}

// Lifecycle
onMounted(() => {
    loadModules()
})
</script>

<template>
    <div class="relative">
        <!-- Module Toggle Button -->
        <Button 
            @click="openModulesDialog"
            :loading="loading"
            text
            size="small"
            class="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
            <div class="flex items-center space-x-2">
                <i class="fas fa-puzzle-piece text-gray-600 dark:text-gray-400"></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ enabledModulesCount }}/{{ totalModulesCount }}
                </span>
            </div>
            <i class="fas fa-chevron-down text-xs text-gray-500 ml-1"></i>
        </Button>

        <!-- Modules Dialog -->
        <Dialog 
            v-model:visible="modulesDialog" 
            modal 
            :header="t('modules.title')"
            :style="{ width: '800px', maxHeight: '80vh' }"
        >
            <template #header>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-puzzle-piece text-blue-600 dark:text-blue-400"></i>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ t('modules.title') }}
                        </span>
                        <Badge :value="`${enabledModulesCount}/${totalModulesCount}`" />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Error Message -->
                <Message v-if="error" severity="error" :closable="false" class="mb-4">
                    {{ error }}
                </Message>

                <!-- Loading State -->
                <div v-if="loading && modules.length === 0" class="flex justify-center py-12">
                    <ProgressSpinner />
                </div>

                <!-- Modules Grid -->
                <div v-else class="space-y-4">
                    <div v-for="module in modules" :key="module.id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <!-- Module Info -->
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <i :class="getModuleIcon(module)" class="text-lg text-blue-600 dark:text-blue-400"></i>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ module.name }}
                                    </h3>
                                    <Badge :value="getModuleStatus(module).label" :severity="getModuleStatus(module).severity" />
                                </div>
                                
                                <p class="text-gray-600 dark:text-gray-400 mb-3">
                                    {{ module.description }}
                                </p>

                                <!-- Features -->
                                <div v-if="module.features.length > 0" class="mb-3">
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="feature in module.features" :key="feature" 
                                              class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">
                                            {{ feature }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Dependencies -->
                                <div v-if="module.dependencies.length > 0" class="text-xs text-amber-600 dark:text-amber-400 mb-2">
                                    <i class="fas fa-link mr-1"></i>
                                    Requires: {{ module.dependencies.join(', ') }}
                                </div>

                                <!-- Required Permissions -->
                                <div v-if="module.required_permissions.length > 0 && !canToggleModule(module)" 
                                     class="text-xs text-red-600 dark:text-red-400">
                                    <i class="fas fa-lock mr-1"></i>
                                    Missing permissions: {{ module.required_permissions.join(', ') }}
                                </div>
                            </div>

                            <!-- Toggle Switch -->
                            <div class="ml-4">
                                <ToggleSwitch 
                                    :model-value="module.enabled"
                                    @update:model-value="toggleModule(module.id)"
                                    :disabled="loading || !canToggleModule(module)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Modules State -->
                <div v-if="modules.length === 0 && !loading" class="text-center py-12">
                    <i class="fas fa-puzzle-piece text-3xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">
                        No modules available
                    </p>
                </div>
            </template>

            <template #footer>
                <Button @click="modulesDialog = false" :label="$t('common.close')" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.p-toggleswitch) {
    transform: scale(0.9);
}

:deep(.p-toggleswitch:not(.p-disabled):hover .p-toggleswitch-slider) {
    background: rgb(59 130 246);
}

:deep(.p-badge) {
    font-size: 0.75rem;
}

.module-card {
    transition: all 0.2s ease-in-out;
}

.module-card:hover {
    transform: translateY(-2px);
}
</style>