<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useForm } from '@inertiajs/vue3'

const page = usePage()
const toast = ref()

// Props from controller
const props = defineProps({
    vendor: {
        type: Object,
        required: true
    }
})

// Form data using Inertia's useForm
const form = useForm({
    legal_name: props.vendor.legal_name,
    display_name: props.vendor.display_name || '',
    vendor_code: props.vendor.vendor_code,
    tax_id: props.vendor.tax_id || '',
    vendor_type: props.vendor.vendor_type,
    status: props.vendor.status,
    website: props.vendor.website || '',
    notes: props.vendor.notes || '',
    contacts: props.vendor.contacts.map(contact => ({
        id: contact.id,
        first_name: contact.first_name,
        last_name: contact.last_name,
        email: contact.email || '',
        phone: contact.phone || '',
        mobile: contact.mobile || '',
        contact_type: contact.contact_type
    })) || [{
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        mobile: '',
        contact_type: 'primary'
    }]
})

// Options for dropdowns
const vendorTypeOptions = [
    { label: 'Company', value: 'company' },
    { label: 'Individual', value: 'individual' },
    { label: 'Other', value: 'other' }
]

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Suspended', value: 'suspended' }
]

const contactTypeOptions = [
    { label: 'Primary', value: 'primary' },
    { label: 'Billing', value: 'billing' },
    { label: 'Technical', value: 'technical' },
    { label: 'Other', value: 'other' }
]

// Contact management methods
const addContact = () => {
    form.contacts.push({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        mobile: '',
        contact_type: 'other'
    })
}

const removeContact = (index) => {
    if (form.contacts.length > 1) {
        form.contacts.splice(index, 1)
    } else {
        toast.value.add({
            severity: 'warn',
            summary: 'Cannot Remove',
            detail: 'At least one contact is required',
            life: 3000
        })
    }
}

// Form submission
const submit = () => {
    form.put(`/vendors/${props.vendor.id}`, {
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Vendor updated successfully',
                life: 3000
            })
        },
        onError: (errors) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors',
                life: 5000
            })
        }
    })
}

const cancel = () => {
    router.visit(`/vendors/${props.vendor.id}`)
}

// Generate vendor code
const generateVendorCode = () => {
    const timestamp = Date.now().toString(36).toUpperCase()
    const random = Math.random().toString(36).substring(2, 5).toUpperCase()
    form.vendor_code = `V${timestamp}-${random}`
}
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
            :title="`Edit ${vendor.display_name || vendor.legal_name}`"
            description="Update vendor information and contacts"
            :sub-description="`Vendor Code: ${vendor.vendor_code}`"
        />

        <!-- Form Container -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <!-- Form Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Vendor Information</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Update basic information about the vendor</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Company Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Legal Name -->
                        <div>
                            <label for="legal_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Legal Name <span class="text-red-500">*</span>
                            </label>
                            <InputText
                                id="legal_name"
                                v-model="form.legal_name"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.legal_name }"
                                placeholder="Official business name"
                            />
                            <small v-if="form.errors.legal_name" class="text-red-500">{{ form.errors.legal_name }}</small>
                        </div>

                        <!-- Display Name -->
                        <div>
                            <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Display Name
                            </label>
                            <InputText
                                id="display_name"
                                v-model="form.display_name"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.display_name }"
                                placeholder="How the name appears in lists"
                            />
                            <small v-if="form.errors.display_name" class="text-red-500">{{ form.errors.display_name }}</small>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Optional: If different from legal name
                            </p>
                        </div>

                        <!-- Vendor Code -->
                        <div>
                            <label for="vendor_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Vendor Code <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <InputText
                                    id="vendor_code"
                                    v-model="form.vendor_code"
                                    class="flex-1"
                                    :class="{ 'p-invalid': form.errors.vendor_code }"
                                    placeholder="V-0001"
                                />
                                <Button
                                    type="button"
                                    @click="generateVendorCode"
                                    label="Generate"
                                    severity="secondary"
                                    size="small"
                                />
                            </div>
                            <small v-if="form.errors.vendor_code" class="text-red-500">{{ form.errors.vendor_code }}</small>
                        </div>

                        <!-- Tax ID -->
                        <div>
                            <label for="tax_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tax ID / EIN
                            </label>
                            <InputText
                                id="tax_id"
                                v-model="form.tax_id"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.tax_id }"
                                placeholder="12-3456789"
                            />
                            <small v-if="form.errors.tax_id" class="text-red-500">{{ form.errors.tax_id }}</small>
                        </div>

                        <!-- Vendor Type -->
                        <div>
                            <label for="vendor_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Vendor Type <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                id="vendor_type"
                                v-model="form.vendor_type"
                                :options="vendorTypeOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.vendor_type }"
                            />
                            <small v-if="form.errors.vendor_type" class="text-red-500">{{ form.errors.vendor_type }}</small>
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                id="status"
                                v-model="form.status"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.status }"
                            />
                            <small v-if="form.errors.status" class="text-red-500">{{ form.errors.status }}</small>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Website -->
                        <div>
                            <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Website
                            </label>
                            <InputText
                                id="website"
                                v-model="form.website"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.website }"
                                placeholder="https://example.com"
                            />
                            <small v-if="form.errors.website" class="text-red-500">{{ form.errors.website }}</small>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notes
                            </label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.notes }"
                                rows="3"
                                placeholder="Additional notes about this vendor..."
                            />
                            <small v-if="form.errors.notes" class="text-red-500">{{ form.errors.notes }}</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Contact Information</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Manage contact persons for this vendor</p>
                            </div>
                            <Button
                                type="button"
                                @click="addContact"
                                icon="pi pi-plus"
                                label="Add Contact"
                                severity="secondary"
                                size="small"
                            />
                        </div>

                        <div v-if="form.errors.contacts" class="mb-4">
                            <small class="text-red-500">{{ form.errors.contacts }}</small>
                        </div>

                        <!-- Contact Cards -->
                        <div class="space-y-4">
                            <div
                                v-for="(contact, index) in form.contacts"
                                :key="index"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        Contact {{ index + 1 }} {{ index === 0 ? '(Primary)' : '' }}
                                        <span v-if="contact.id" class="text-xs text-gray-500 ml-2">
                                            ID: {{ contact.id.substring(0, 8) }}...
                                        </span>
                                    </h4>
                                    <Button
                                        v-if="form.contacts.length > 1"
                                        type="button"
                                        @click="removeContact(index)"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        size="small"
                                        text
                                    />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- First Name -->
                                    <div>
                                        <label :for="`first_name_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            First Name <span class="text-red-500">*</span>
                                        </label>
                                        <InputText
                                            :id="`first_name_${index}`"
                                            v-model="contact.first_name"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.first_name`] }"
                                            placeholder="John"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.first_name`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.first_name`] }}
                                        </small>
                                    </div>

                                    <!-- Last Name -->
                                    <div>
                                        <label :for="`last_name_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Last Name <span class="text-red-500">*</span>
                                        </label>
                                        <InputText
                                            :id="`last_name_${index}`"
                                            v-model="contact.last_name"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.last_name`] }"
                                            placeholder="Doe"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.last_name`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.last_name`] }}
                                        </small>
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <label :for="`email_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Email
                                        </label>
                                        <InputText
                                            :id="`email_${index}`"
                                            v-model="contact.email"
                                            type="email"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.email`] }"
                                            placeholder="john.doe@example.com"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.email`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.email`] }}
                                        </small>
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <label :for="`phone_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Phone
                                        </label>
                                        <InputText
                                            :id="`phone_${index}`"
                                            v-model="contact.phone"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.phone`] }"
                                            placeholder="+1 (555) 123-4567"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.phone`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.phone`] }}
                                        </small>
                                    </div>

                                    <!-- Mobile -->
                                    <div>
                                        <label :for="`mobile_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Mobile
                                        </label>
                                        <InputText
                                            :id="`mobile_${index}`"
                                            v-model="contact.mobile"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.mobile`] }"
                                            placeholder="+1 (555) 987-6543"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.mobile`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.mobile`] }}
                                        </small>
                                    </div>

                                    <!-- Contact Type -->
                                    <div>
                                        <label :for="`contact_type_${index}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Contact Type <span class="text-red-500">*</span>
                                        </label>
                                        <Dropdown
                                            :id="`contact_type_${index}`"
                                            v-model="contact.contact_type"
                                            :options="contactTypeOptions"
                                            option-label="label"
                                            option-value="value"
                                            class="w-full"
                                            :class="{ 'p-invalid': form.errors[`contacts.${index}.contact_type`] }"
                                        />
                                        <small v-if="form.errors[`contacts.${index}.contact_type`]" class="text-red-500">
                                            {{ form.errors[`contacts.${index}.contact_type`] }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center justify-between">
                            <!-- Left side info -->
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <p>Vendor ID: {{ vendor.id }}</p>
                                <p>Created: {{ new Date(vendor.created_at).toLocaleDateString() }}</p>
                            </div>

                            <!-- Right side actions -->
                            <div class="flex items-center space-x-4">
                                <Link
                                    :href="`/vendors/${vendor.id}`"
                                    class="inline-flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200"
                                >
                                    Cancel
                                </Link>
                                <Button
                                    type="submit"
                                    label="Update Vendor"
                                    :loading="form.processing"
                                    icon="pi pi-save"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </LayoutShell>
</template>