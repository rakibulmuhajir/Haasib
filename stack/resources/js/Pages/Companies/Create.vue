<script setup>
import { ref, computed } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Divider from 'primevue/divider'

const { t } = useI18n()

// Form setup
const form = useForm({
    name: '',
    industry: '',
    country: '',
    base_currency: 'USD',
    currency: '',
    timezone: '',
    language: 'en',
    locale: 'en_US',
    auto_setup: true,
    create_fiscal_year: true,
    fiscal_year_start: '',
    fiscal_year_end: '',
    settings: {}
})

const toast = ref()
const submitting = ref(false)

// Options
const industryOptions = [
    { label: 'Hospitality', value: 'hospitality' },
    { label: 'Retail', value: 'retail' },
    { label: 'Professional Services', value: 'professional_services' },
    { label: 'Technology', value: 'technology' },
    { label: 'Healthcare', value: 'healthcare' },
    { label: 'Education', value: 'education' },
    { label: 'Manufacturing', value: 'manufacturing' },
    { label: 'Other', value: 'other' }
]

const countryOptions = [
    { label: 'United States', value: 'US' },
    { label: 'Canada', value: 'CA' },
    { label: 'United Kingdom', value: 'GB' },
    { label: 'Australia', value: 'AU' },
    { label: 'Germany', value: 'DE' },
    { label: 'France', value: 'FR' },
    { label: 'Japan', value: 'JP' },
    { label: 'China', value: 'CN' }
]

const currencyOptions = [
    { label: 'USD - US Dollar', value: 'USD' },
    { label: 'EUR - Euro', value: 'EUR' },
    { label: 'GBP - British Pound', value: 'GBP' },
    { label: 'CAD - Canadian Dollar', value: 'CAD' },
    { label: 'AUD - Australian Dollar', value: 'AUD' },
    { label: 'JPY - Japanese Yen', value: 'JPY' },
    { label: 'CNY - Chinese Yuan', value: 'CNY' }
]

const languageOptions = [
    { label: 'English', value: 'en' },
    { label: 'Spanish', value: 'es' },
    { label: 'French', value: 'fr' },
    { label: 'German', value: 'de' },
    { label: 'Japanese', value: 'ja' },
    { label: 'Chinese', value: 'zh' }
]

const localeOptions = [
    { label: 'English (US)', value: 'en_US' },
    { label: 'English (UK)', value: 'en_GB' },
    { label: 'Spanish (ES)', value: 'es_ES' },
    { label: 'French (FR)', value: 'fr_FR' },
    { label: 'German (DE)', value: 'de_DE' }
]

// Computed properties
const suggestedFiscalYearDates = computed(() => {
    if (!form.fiscal_year_start) return null
    
    const start = new Date(form.fiscal_year_start)
    const end = new Date(start)
    end.setFullYear(end.getFullYear() + 1)
    end.setDate(end.getDate() - 1)
    
    return end.toISOString().split('T')[0]
})

const canSubmit = computed(() => {
    return form.name && 
           form.industry && 
           form.country && 
           form.base_currency &&
           !form.processing
})

// Methods
const generateSlug = () => {
    if (form.name) {
        // Slug generation will be handled server-side
        // This is just for preview if needed
        const slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .trim('-')
        return slug
    }
    return ''
}

const setFiscalYearDefaults = () => {
    const today = new Date()
    const currentYear = today.getFullYear()
    
    // Default to calendar year (Jan 1 - Dec 31)
    form.fiscal_year_start = `${currentYear}-01-01`
    form.fiscal_year_end = `${currentYear}-12-31`
}

const handleIndustryChange = () => {
    // Set recommended defaults based on industry
    switch (form.industry) {
        case 'hospitality':
            form.currency = form.base_currency
            break
        case 'retail':
            form.currency = form.base_currency
            break
        case 'professional_services':
            form.currency = form.base_currency
            break
        default:
            form.currency = form.base_currency
    }
}

const handleCountryChange = () => {
    // Set timezone and locale based on country
    switch (form.country) {
        case 'US':
            form.timezone = 'America/New_York'
            form.locale = 'en_US'
            break
        case 'CA':
            form.timezone = 'America/Toronto'
            form.locale = 'en_CA'
            break
        case 'GB':
            form.timezone = 'Europe/London'
            form.locale = 'en_GB'
            break
        case 'AU':
            form.timezone = 'Australia/Sydney'
            form.locale = 'en_AU'
            break
        default:
            form.timezone = 'UTC'
            form.locale = 'en_US'
    }
}

const handleCurrencyChange = () => {
    form.currency = form.base_currency
}

const submitForm = async () => {
    submitting.value = true
    
    try {
        await form.post('/api/v1/companies', {
            onSuccess: (page) => {
                toast.value.add({
                    severity: 'success',
                    summary: 'Success!',
                    detail: 'Company created successfully. Redirecting...',
                    life: 2000
                })
                
                setTimeout(() => {
                    router.visit('/companies')
                }, 1500)
            },
            onError: (errors) => {
                toast.value.add({
                    severity: 'error',
                    summary: 'Validation Error',
                    detail: 'Please check the form for errors',
                    life: 3000
                })
            },
            onFinish: () => {
                submitting.value = false
            }
        })
    } catch (error) {
        console.error('Form submission error:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to create company. Please try again.',
            life: 3000
        })
        submitting.value = false
    }
}

const resetForm = () => {
    form.reset()
    form.clearErrors()
}

// Initialize defaults
setFiscalYearDefaults()
</script>

<template>
    <div class="company-create">
        <Toast ref="toast" />
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Create New Company
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Set up a new company with accounting and management features
                </p>
            </div>
            
            <Link href="/companies">
                <Button
                    icon="pi pi-arrow-left"
                    label="Back to Companies"
                    severity="secondary"
                    outlined
                />
            </Link>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <Card>
                    <template #title>Basic Information</template>
                    <template #content>
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Company Name <span class="text-red-500">*</span>
                                </label>
                                <InputText
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Enter company name"
                                    :class="{ 'p-invalid': form.errors.name }"
                                    class="w-full"
                                />
                                <Message v-if="form.errors.name" severity="error" :closable="false">
                                    {{ form.errors.name }}
                                </Message>
                            </div>

                            <div>
                                <label for="industry" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Industry <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    id="industry"
                                    v-model="form.industry"
                                    :options="industryOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select industry"
                                    :class="{ 'p-invalid': form.errors.industry }"
                                    class="w-full"
                                    @change="handleIndustryChange"
                                />
                                <Message v-if="form.errors.industry" severity="error" :closable="false">
                                    {{ form.errors.industry }}
                                </Message>
                            </div>

                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Country <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    id="country"
                                    v-model="form.country"
                                    :options="countryOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select country"
                                    :class="{ 'p-invalid': form.errors.country }"
                                    class="w-full"
                                    @change="handleCountryChange"
                                />
                                <Message v-if="form.errors.country" severity="error" :closable="false">
                                    {{ form.errors.country }}
                                </Message>
                            </div>

                            <div v-if="generateSlug()" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <i class="pi pi-info-circle mr-2"></i>
                                    URL slug will be: <strong>{{ generateSlug() }}</strong>
                                </p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Financial Settings -->
                <Card>
                    <template #title>Financial Settings</template>
                    <template #content>
                        <div class="space-y-4">
                            <div>
                                <label for="base_currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Base Currency <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    id="base_currency"
                                    v-model="form.base_currency"
                                    :options="currencyOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select base currency"
                                    :class="{ 'p-invalid': form.errors.base_currency }"
                                    class="w-full"
                                    @change="handleCurrencyChange"
                                />
                                <Message v-if="form.errors.base_currency" severity="error" :closable="false">
                                    {{ form.errors.base_currency }}
                                </Message>
                            </div>

                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timezone
                                </label>
                                <InputText
                                    id="timezone"
                                    v-model="form.timezone"
                                    placeholder="e.g., America/New_York"
                                    class="w-full"
                                />
                            </div>

                            <div>
                                <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Language
                                </label>
                                <Dropdown
                                    id="language"
                                    v-model="form.language"
                                    :options="languageOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select language"
                                    class="w-full"
                                />
                            </div>

                            <div>
                                <label for="locale" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Locale
                                </label>
                                <Dropdown
                                    id="locale"
                                    v-model="form.locale"
                                    :options="localeOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select locale"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Fiscal Year Settings -->
                <Card class="lg:col-span-2">
                    <template #title>Fiscal Year Settings</template>
                    <template #content>
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="create_fiscal_year"
                                    v-model="form.create_fiscal_year"
                                    input-id="create_fiscal_year"
                                    binary
                                />
                                <label for="create_fiscal_year" class="font-medium">
                                    Create fiscal year automatically
                                </label>
                            </div>

                            <div v-if="form.create_fiscal_year" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="fiscal_year_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fiscal Year Start Date <span class="text-red-500">*</span>
                                    </label>
                                    <Calendar
                                        id="fiscal_year_start"
                                        v-model="form.fiscal_year_start"
                                        date-format="yy-mm-dd"
                                        placeholder="Select start date"
                                        :class="{ 'p-invalid': form.errors.fiscal_year_start }"
                                        class="w-full"
                                    />
                                    <Message v-if="form.errors.fiscal_year_start" severity="error" :closable="false">
                                        {{ form.errors.fiscal_year_start }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="fiscal_year_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fiscal Year End Date <span class="text-red-500">*</span>
                                    </label>
                                    <Calendar
                                        id="fiscal_year_end"
                                        v-model="form.fiscal_year_end"
                                        date-format="yy-mm-dd"
                                        placeholder="Select end date"
                                        :class="{ 'p-invalid': form.errors.fiscal_year_end }"
                                        class="w-full"
                                    />
                                    <Message v-if="form.errors.fiscal_year_end" severity="error" :closable="false">
                                        {{ form.errors.fiscal_year_end }}
                                    </Message>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    @click="setFiscalYearDefaults"
                                    label="Use Calendar Year"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                />
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Advanced Settings -->
                <Card class="lg:col-span-2">
                    <template #title>Advanced Settings</template>
                    <template #content>
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="auto_setup"
                                    v-model="form.auto_setup"
                                    input-id="auto_setup"
                                    binary
                                />
                                <label for="auto_setup" class="font-medium">
                                    Auto-setup accounting features
                                </label>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                This will automatically create chart of accounts, enable core modules, and set up basic accounting structure.
                            </p>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Form Actions -->
            <Divider class="my-6" />

            <div class="flex justify-between items-center">
                <Button
                    type="button"
                    @click="resetForm"
                    label="Reset Form"
                    severity="secondary"
                    outlined
                    :disabled="form.processing"
                />

                <div class="flex gap-2">
                    <Link href="/companies">
                        <Button
                            type="button"
                            label="Cancel"
                            severity="secondary"
                            outlined
                        />
                    </Link>

                    <Button
                        type="submit"
                        label="Create Company"
                        icon="pi pi-check"
                        :loading="submitting"
                        :disabled="!canSubmit"
                    />
                </div>
            </div>
        </form>

        <!-- Loading Overlay -->
        <div v-if="submitting" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg">
                <ProgressSpinner />
                <p class="mt-4 text-center">Creating company...</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.company-create {
    @apply p-6;
}
</style>