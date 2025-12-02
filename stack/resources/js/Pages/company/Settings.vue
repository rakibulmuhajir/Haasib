<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'

interface Company {
    id: string
    name: string
    slug: string
    industry: string | null
    country: string | null
    base_currency: string
    is_active: boolean
}

interface Props {
    company: Company
    currentUserRole: string
}

const props = defineProps<Props>()
const page = usePage()

const canEdit = computed(() => {
    return ['owner', 'admin'].includes(props.currentUserRole)
})

// Update company form
const companyForm = useForm({
    name: props.company.name,
    industry: props.company.industry || '',
    country: props.company.country || '',
})

const updateCompany = () => {
    companyForm.put(`/${props.company.slug}`, {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled by form
        },
    })
}

const industries = [
    { value: '', label: 'Select Industry' },
    { value: 'hospitality', label: 'Hospitality' },
    { value: 'retail', label: 'Retail' },
    { value: 'professional_services', label: 'Professional Services' },
    { value: 'technology', label: 'Technology' },
    { value: 'healthcare', label: 'Healthcare' },
    { value: 'education', label: 'Education' },
    { value: 'manufacturing', label: 'Manufacturing' },
    { value: 'other', label: 'Other' },
]

const countries = [
    { value: '', label: 'Select Country' },
    { value: 'US', label: 'United States' },
    { value: 'CA', label: 'Canada' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'AU', label: 'Australia' },
    { value: 'DE', label: 'Germany' },
    { value: 'FR', label: 'France' },
    { value: 'JP', label: 'Japan' },
    { value: 'CN', label: 'China' },
    { value: 'IN', label: 'India' },
]
</script>

<template>
    <LayoutShell>
        <UniversalPageHeader
            :title="`${company.name} Settings`"
            description="Manage company information and preferences"
            :show-search="false"
        />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Permission Warning -->
            <div v-if="!canEdit" class="mb-6 rounded-md bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            You don't have permission to edit company settings. Only owners and admins can make changes.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Company Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Company Information
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Basic information about your company
                        </p>
                    </div>

                    <form @submit.prevent="updateCompany" class="px-6 py-5">
                        <div class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Company Name
                                </label>
                                <input
                                    id="name"
                                    v-model="companyForm.name"
                                    type="text"
                                    required
                                    :disabled="!canEdit || companyForm.processing"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                <div v-if="companyForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ companyForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="industry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Industry
                                </label>
                                <select
                                    id="industry"
                                    v-model="companyForm.industry"
                                    :disabled="!canEdit || companyForm.processing"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <option v-for="ind in industries" :key="ind.value" :value="ind.value">
                                        {{ ind.label }}
                                    </option>
                                </select>
                                <div v-if="companyForm.errors.industry" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ companyForm.errors.industry }}
                                </div>
                            </div>

                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Country
                                </label>
                                <select
                                    id="country"
                                    v-model="companyForm.country"
                                    :disabled="!canEdit || companyForm.processing"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <option v-for="country in countries" :key="country.value" :value="country.value">
                                        {{ country.label }}
                                    </option>
                                </select>
                                <div v-if="companyForm.errors.country" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ companyForm.errors.country }}
                                </div>
                            </div>
                        </div>

                        <div v-if="canEdit" class="mt-6 flex justify-end">
                            <PrimaryButton
                                type="submit"
                                :disabled="companyForm.processing"
                            >
                                <i v-if="companyForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                                {{ companyForm.processing ? 'Saving...' : 'Save Changes' }}
                            </PrimaryButton>
                        </div>

                        <!-- Success Message -->
                        <div v-if="companyForm.recentlySuccessful" class="mt-4 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
                            <p class="text-sm text-green-800 dark:text-green-200">
                                <i class="fas fa-check-circle mr-2"></i>
                                Company settings updated successfully!
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Company Details (Read-Only) -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Company Details
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Read-only company information
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ company.id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company Slug</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ company.slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Base Currency</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ company.base_currency }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1">
                                    <span :class="[
                                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                        company.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                    ]">
                                        {{ company.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Your Role</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 capitalize">
                                        {{ currentUserRole }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Danger Zone (Owners Only) -->
                <div v-if="currentUserRole === 'owner'" class="bg-white dark:bg-gray-800 shadow rounded-lg border-2 border-red-200 dark:border-red-900">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium leading-6 text-red-600 dark:text-red-400">
                            Danger Zone
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Irreversible and destructive actions
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Delete this company</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Once deleted, all data will be permanently removed
                                </p>
                            </div>
                            <DangerButton>
                                Delete Company
                            </DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LayoutShell>
</template>
