<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = page.props.auth?.user

// State
const activeTab = ref('user')
const loading = ref(false)
const saving = ref(false)
const success = ref(false)
const error = ref('')

// Settings data
const settings = ref({
    user: {},
    company: null,
    tax: null,
    system: {}
})

const activeCompany = ref(null)

// Computed properties
const availableTabs = computed(() => {
    const tabs = [
        { key: 'user', label: 'User Settings', icon: 'fas fa-user' },
        { key: 'company', label: 'Company Settings', icon: 'fas fa-building', disabled: !activeCompany.value },
        { key: 'tax', label: 'Tax Settings', icon: 'fas fa-calculator', disabled: !activeCompany.value }
    ]
    
    return tabs
})

const currentTabData = computed(() => {
    switch (activeTab.value) {
        case 'user':
            return settings.value.user
        case 'company':
            return settings.value.company
        case 'tax':
            return settings.value.tax
        default:
            return {}
    }
})

// Load settings
async function loadSettings() {
    loading.value = true
    error.value = ''
    
    try {
        const response = await fetch('/api/settings', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        
        if (!response.ok) {
            throw new Error('Failed to load settings')
        }
        
        const data = await response.json()
        settings.value = data.settings
        activeCompany.value = data.active_company
        
    } catch (err) {
        error.value = err.message
    } finally {
        loading.value = false
    }
}

// Save settings
async function saveSettings() {
    saving.value = true
    error.value = ''
    success.value = false
    
    try {
        const response = await fetch('/api/settings', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                settings: Object.entries(currentTabData.value).map(([key, value]) => ({
                    group: activeTab.value,
                    key,
                    value
                }))
            })
        })
        
        if (!response.ok) {
            throw new Error('Failed to save settings')
        }
        
        const data = await response.json()
        settings.value = data.settings
        success.value = true
        
        setTimeout(() => {
            success.value = false
        }, 3000)
        
    } catch (err) {
        error.value = err.message
    } finally {
        saving.value = false
    }
}

// Save single setting
async function saveSetting(key, value) {
    try {
        const response = await fetch(`/api/settings/${activeTab.value}/${key}`, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({ value })
        })
        
        if (!response.ok) {
            throw new Error('Failed to save setting')
        }
        
        // Update local state
        if (currentTabData.value) {
            currentTabData.value[key] = value
        }
        
        success.value = true
        setTimeout(() => {
            success.value = false
        }, 2000)
        
    } catch (err) {
        error.value = err.message
    }
}

// Format setting value for display
function formatSettingValue(key, value) {
    if (settings.value.system?.available_locales?.[value]) {
        return settings.value.system.available_locales[value]
    }
    
    if (settings.value.system?.available_themes?.[value]) {
        return settings.value.system.available_themes[value]
    }
    
    if (settings.value.system?.available_currencies?.[value]) {
        return settings.value.system.available_currencies[value] + ` (${value})`
    }
    
    if (settings.value.system?.available_date_formats?.[value]) {
        return settings.value.system.available_date_formats[value]
    }
    
    if (settings.value.system?.available_time_formats?.[value]) {
        return settings.value.system.available_time_formats[value]
    }
    
    if (settings.value.system?.available_number_formats?.[value]) {
        return settings.value.system.available_number_formats[value]
    }
    
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No'
    }
    
    return value
}

// Get setting options for dropdown
function getSettingOptions(key) {
    const system = settings.value.system
    
    switch (key) {
        case 'locale':
            return system?.available_locales || {}
        case 'timezone':
            return system?.available_timezones || {}
        case 'currency_code':
            return system?.available_currencies || {}
        case 'theme':
            return system?.available_themes || {}
        case 'date_format':
            return system?.available_date_formats || {}
        case 'time_format':
            return system?.available_time_formats || {}
        case 'number_format':
            return system?.available_number_formats || {}
        case 'currency_format':
            return {
                'symbol' => 'Symbol ($)',
                'code' => 'Code (USD)',
                'none' => 'None'
            }
        case 'default_reporting_frequency':
            return {
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually'
            }
        default:
            return {}
    }
}

// Render input based on setting type
function getSettingInputType(key, value) {
    if (typeof value === 'boolean') {
        return 'checkbox'
    }
    
    const options = getSettingOptions(key)
    if (Object.keys(options).length > 0) {
        return 'select'
    }
    
    if (key.includes('email')) {
        return 'email'
    }
    
    if (key.includes('phone') || key.includes('fax')) {
        return 'tel'
    }
    
    if (key.includes('website') || key.includes('url')) {
        return 'url'
    }
    
    return 'text'
}

// Handle tab change
function changeTab(tabKey) {
    activeTab.value = tabKey
}

// Go to company selection
function selectCompany() {
    window.location.href = '/companies'
}

onMounted(() => {
    loadSettings()
})
</script>

<template>
    <LayoutShell>
        <template #default>
            <!-- Page Header -->
            <UniversalPageHeader
                title="Settings"
                description="Manage your account and application preferences"
                :default-actions="[
                    { key: 'save', label: 'Save Changes', icon: 'fas fa-save', action: saveSettings, severity: 'primary', disabled: saving || !activeTab || activeTab === 'system' }
                ]"
            />

            <!-- Success/Error Messages -->
            <div v-if="success" class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700 dark:text-green-200">
                            Settings saved successfully!
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700 dark:text-red-200">
                            {{ error }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="max-w-4xl mx-auto">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-8">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            v-for="tab in availableTabs"
                            :key="tab.key"
                            @click="changeTab(tab.key)"
                            :disabled="tab.disabled"
                            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors"
                            :class="[
                                activeTab === tab.key
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : tab.disabled
                                        ? 'border-transparent text-gray-400 cursor-not-allowed'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            <i :class="tab.icon + ' mr-2'"></i>
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">Loading settings...</p>
                </div>

                <!-- Settings Content -->
                <div v-else-if="currentTabData" class="space-y-6">
                    <!-- User Settings -->
                    <div v-if="activeTab === 'user'" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">User Preferences</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Configure your personal account settings and preferences
                            </p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Locale Settings -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Language
                                    </label>
                                    <select
                                        v-model="settings.user.locale"
                                        @change="saveSetting('locale', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('locale')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Timezone
                                    </label>
                                    <select
                                        v-model="settings.user.timezone"
                                        @change="saveSetting('timezone', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('timezone')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Display Settings -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Theme
                                    </label>
                                    <select
                                        v-model="settings.user.theme"
                                        @change="saveSetting('theme', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('theme')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Date Format
                                    </label>
                                    <select
                                        v-model="settings.user.date_format"
                                        @change="saveSetting('date_format', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('date_format')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Time Format
                                    </label>
                                    <select
                                        v-model="settings.user.time_format"
                                        @change="saveSetting('time_format', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('time_format')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Number Format
                                    </label>
                                    <select
                                        v-model="settings.user.number_format"
                                        @change="saveSetting('number_format', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('number_format')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Notification Settings -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Notifications</h4>
                                <div class="space-y-4">
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.user.email_notifications"
                                            @change="saveSetting('email_notifications', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Email notifications
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.user.push_notifications"
                                            @change="saveSetting('push_notifications', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Push notifications (if available)
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Company Settings -->
                    <div v-else-if="activeTab === 'company' && settings.company" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Company Settings</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Configure your company information and preferences
                            </p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Company Name
                                    </label>
                                    <input
                                        v-model="settings.company.name"
                                        @blur="saveSetting('name', $event.target.value)"
                                        type="text"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Currency
                                    </label>
                                    <select
                                        v-model="settings.company.currency_code"
                                        @change="saveSetting('currency_code', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('currency_code')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Timezone
                                    </label>
                                    <select
                                        v-model="settings.company.timezone"
                                        @change="saveSetting('timezone', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('timezone')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Email
                                    </label>
                                    <input
                                        v-model="settings.company.email"
                                        @blur="saveSetting('email', $event.target.value)"
                                        type="email"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone
                                    </label>
                                    <input
                                        v-model="settings.company.phone"
                                        @blur="saveSetting('phone', $event.target.value)"
                                        type="tel"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Website
                                    </label>
                                    <input
                                        v-model="settings.company.website"
                                        @blur="saveSetting('website', $event.target.value)"
                                        type="url"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address
                                    </label>
                                    <textarea
                                        v-model="settings.company.address"
                                        @blur="saveSetting('address', $event.target.value)"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax ID
                                    </label>
                                    <input
                                        v-model="settings.company.tax_id"
                                        @blur="saveSetting('tax_id', $event.target.value)"
                                        type="text"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Registration Number
                                    </label>
                                    <input
                                        v-model="settings.company.registration_number"
                                        @blur="saveSetting('registration_number', $event.target.value)"
                                        type="text"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tax Settings -->
                    <div v-else-if="activeTab === 'tax' && settings.tax" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tax Settings</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Configure tax calculation and reporting settings
                            </p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax Country
                                    </label>
                                    <input
                                        v-model="settings.tax.tax_country_code"
                                        @blur="saveSetting('tax_country_code', $event.target.value)"
                                        type="text"
                                        maxlength="2"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reporting Frequency
                                    </label>
                                    <select
                                        v-model="settings.tax.default_reporting_frequency"
                                        @change="saveSetting('default_reporting_frequency', $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    >
                                        <option v-for="(label, value) in getSettingOptions('default_reporting_frequency')" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-2 space-y-4">
                                    <h4 class="text-md font-medium text-gray-900 dark:text-white">Tax Calculation</h4>
                                    
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tax.tax_inclusive_pricing"
                                            @change="saveSetting('tax_inclusive_pricing', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Tax inclusive pricing
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tax.calculate_sales_tax"
                                            @change="saveSetting('calculate_sales_tax', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Calculate sales tax
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tax.charge_tax_on_shipping"
                                            @change="saveSetting('charge_tax_on_shipping', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Charge tax on shipping
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tax.auto_calculate_tax"
                                            @change="saveSetting('auto_calculate_tax', $event.target.checked)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Auto calculate tax
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Company Selected -->
                    <div v-else-if="(activeTab === 'company' || activeTab === 'tax') && !activeCompany" 
                         class="text-center py-12 bg-white dark:bg-gray-800 shadow rounded-lg">
                        <i class="fas fa-building text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Company Selected</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            You need to select an active company to manage these settings.
                        </p>
                        <button
                            @click="selectCompany"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <i class="fas fa-building mr-2"></i>
                            Select Company
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </LayoutShell>
</template>