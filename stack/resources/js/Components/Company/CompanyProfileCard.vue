<template>
    <Card>
        <template #title>
            Company Profile
        </template>
        <template #content>
            <div class="space-y-6">
                <!-- Company Name -->
                <InlineEditable
                    :model-value="companyData.name"
                    v-model:editing="isEditingName"
                    label="Company Name"
                    type="text"
                    :saving="isSaving('name')"
                    @update:model-value="updateCompanyData('name', $event)"
                    @save="$emit('save-field', 'name', $event)"
                    @cancel="$emit('cancel-edit')"
                    :validate="(value: string) => (!value ? 'Company name is required' : null)"
                >
                    <template #display>
                        <div class="flex items-center gap-3 cursor-pointer">
                            <Avatar
                                :label="companyData.name?.charAt(0) ?? '?'"
                                class="bg-primary text-primary-contrast"
                                shape="circle"
                                size="large"
                            />
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                    {{ companyData.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Slug: {{ companyData.slug }}
                                </p>
                            </div>
                        </div>
                    </template>
                </InlineEditable>

                <Divider />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <InlineEditable
                        :model-value="companyData.industry"
                        v-model:editing="isEditingIndustry"
                        label="Industry"
                        type="select"
                        :options="industryOptions"
                        :saving="isSaving('industry')"
                        @update:model-value="updateCompanyData('industry', $event)"
                        @save="$emit('save-field', 'industry', $event)"
                        @cancel="$emit('cancel-edit')"
                    />

                    <InlineEditable
                        :model-value="companyData.country"
                        v-model:editing="isEditingCountry"
                        label="Country"
                        type="select"
                        :options="countryOptions"
                        :saving="isSaving('country')"
                        @update:model-value="updateCompanyData('country', $event)"
                        @save="$emit('save-field', 'country', $event)"
                        @cancel="$emit('cancel-edit')"
                    />

                    <InlineEditable
                        :model-value="companyData.timezone"
                        v-model:editing="isEditingTimezone"
                        label="Timezone"
                        type="text"
                        :saving="isSaving('timezone')"
                        @update:model-value="updateCompanyData('timezone', $event)"
                        @save="$emit('save-field', 'timezone', $event)"
                        @cancel="$emit('cancel-edit')"
                        :validate="(value: string) => (!value ? 'Timezone is required' : null)"
                        placeholder="e.g. America/New_York"
                    />

                    <InlineEditable
                        :model-value="companyData.base_currency"
                        v-model:editing="isEditingCurrency"
                        label="Base Currency"
                        type="select"
                        :options="currencyOptions"
                        :saving="isSaving('base_currency')"
                        @update:model-value="updateCompanyData('base_currency', $event)"
                        @save="$emit('save-field', 'base_currency', $event)"
                        @cancel="$emit('cancel-edit')"
                    />

                    <InlineEditable
                        :model-value="companyData.language"
                        v-model:editing="isEditingLanguage"
                        label="Language"
                        type="select"
                        :options="languageOptions"
                        :saving="isSaving('language')"
                        @update:model-value="updateCompanyData('language', $event)"
                        @save="$emit('save-field', 'language', $event)"
                        @cancel="$emit('cancel-edit')"
                    />

                    <InlineEditable
                        :model-value="companyData.locale"
                        v-model:editing="isEditingLocale"
                        label="Locale"
                        type="select"
                        :options="localeOptions"
                        :saving="isSaving('locale')"
                        @update:model-value="updateCompanyData('locale', $event)"
                        @save="$emit('save-field', 'locale', $event)"
                        @cancel="$emit('cancel-edit')"
                        :validate="(value: string) => {
                            if (!value) return null
                            return /^[a-z]{2}_[A-Z]{2}$/.test(value) ? null : 'Locale must look like en_US'
                        }"
                    />
                </div>
            </div>
        </template>
    </Card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Avatar from 'primevue/avatar'
import Card from 'primevue/card'
import Divider from 'primevue/divider'
import InlineEditable from '@/Components/InlineEditable.vue'

interface CompanyPayload {
    id?: number
    name?: string
    slug?: string
    industry?: string
    country?: string
    timezone?: string
    base_currency?: string
    language?: string
    locale?: string
}

const props = defineProps<{
    companyData: CompanyPayload
    isSaving: (field: string) => boolean
}>()

const emit = defineEmits<{
    'update-company-data': [field: string, value: any]
    'save-field': [field: string, value: any]
    'cancel-edit': []
}>()

// Editing states
const isEditingName = ref(false)
const isEditingIndustry = ref(false)
const isEditingCountry = ref(false)
const isEditingTimezone = ref(false)
const isEditingCurrency = ref(false)
const isEditingLanguage = ref(false)
const isEditingLocale = ref(false)

const updateCompanyData = (field: string, value: any) => {
    emit('update-company-data', field, value)
}

// Options
const industryOptions = [
    { label: 'Select Industry', value: '' },
    { label: 'Technology', value: 'technology' },
    { label: 'Healthcare', value: 'healthcare' },
    { label: 'Finance', value: 'finance' },
    { label: 'Manufacturing', value: 'manufacturing' },
    { label: 'Retail', value: 'retail' },
    { label: 'Consulting', value: 'consulting' },
    { label: 'Education', value: 'education' },
    { label: 'Real Estate', value: 'real-estate' },
    { label: 'Hospitality', value: 'hospitality' },
    { label: 'Other', value: 'other' },
]

const countryOptions = [
    { label: 'Select Country', value: '' },
    { label: 'United States', value: 'US' },
    { label: 'Canada', value: 'CA' },
    { label: 'United Kingdom', value: 'UK' },
    { label: 'Australia', value: 'AU' },
    { label: 'Germany', value: 'DE' },
    { label: 'France', value: 'FR' },
    { label: 'Japan', value: 'JP' },
    { label: 'China', value: 'CN' },
    { label: 'India', value: 'IN' },
    { label: 'Brazil', value: 'BR' },
]

const currencyOptions = [
    { label: 'Select Currency', value: '' },
    { label: 'USD - US Dollar', value: 'USD' },
    { label: 'EUR - Euro', value: 'EUR' },
    { label: 'GBP - British Pound', value: 'GBP' },
    { label: 'JPY - Japanese Yen', value: 'JPY' },
    { label: 'CNY - Chinese Yuan', value: 'CNY' },
    { label: 'INR - Indian Rupee', value: 'INR' },
    { label: 'CAD - Canadian Dollar', value: 'CAD' },
    { label: 'AUD - Australian Dollar', value: 'AUD' },
]

const languageOptions = [
    { label: 'English', value: 'en' },
    { label: 'Spanish', value: 'es' },
    { label: 'French', value: 'fr' },
    { label: 'German', value: 'de' },
    { label: 'Japanese', value: 'ja' },
    { label: 'Chinese', value: 'zh' },
]

const localeOptions = [
    { label: 'English (US)', value: 'en_US' },
    { label: 'English (UK)', value: 'en_GB' },
    { label: 'Spanish (ES)', value: 'es_ES' },
    { label: 'French (FR)', value: 'fr_FR' },
    { label: 'German (DE)', value: 'de_DE' },
]
</script>