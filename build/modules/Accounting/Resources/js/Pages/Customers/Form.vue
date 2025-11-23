<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useToast } from "@/components/ui/toast/use-toast"
import { useI18n } from 'vue-i18n'
// import Button from 'primevue/button'
// import InputText from 'primevue/inputtext'
// import Textarea from 'primevue/textarea'
// import Dropdown from 'primevue/dropdown'
// import InputNumber from 'primevue/inputnumber'
// import Card from 'primevue/card'
// import Message from 'primevue/message'
// import Toast from 'primevue/toast'

const props = defineProps({
    customer: Object,
    currencies: Array,
    paymentTerms: Array
})

const toast = useToast()
const { t } = useI18n()

const isEditing = computed(() => !!props.customer)

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Blocked', value: 'blocked' }
]

const defaultCurrencyOptions = [
    { label: 'USD - US Dollar', value: 'USD' },
    { label: 'EUR - Euro', value: 'EUR' },
    { label: 'GBP - British Pound', value: 'GBP' },
    { label: 'CAD - Canadian Dollar', value: 'CAD' },
    { label: 'AUD - Australian Dollar', value: 'AUD' }
]

const defaultPaymentTerms = [
    { label: 'Due on Receipt', value: 'due_on_receipt' },
    { label: 'Net 15', value: 'net_15' },
    { label: 'Net 30', value: 'net_30' },
    { label: 'Net 60', value: 'net_60' },
    { label: 'Net 90', value: 'net_90' }
]

const form = useForm({
    name: props.customer?.name || '',
    legal_name: props.customer?.legal_name || '',
    customer_number: props.customer?.customer_number || '',
    email: props.customer?.email || '',
    phone: props.customer?.phone || '',
    default_currency: props.customer?.default_currency || 'USD',
    payment_terms: props.customer?.payment_terms || '',
    credit_limit: props.customer?.credit_limit || null,
    tax_id: props.customer?.tax_id || '',
    website: props.customer?.website || '',
    notes: props.customer?.notes || '',
    status: props.customer?.status || 'active'
})

const submit = () => {
    if (isEditing.value) {
        form.put(route('customers.update', props.customer.id), {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Customer updated successfully',
                    life: 3000
                })
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Validation Error',
                    detail: 'Please check the form for errors',
                    life: 3000
                })
            }
        })
    } else {
        form.post(route('customers.store'), {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Customer created successfully',
                    life: 3000
                })
                form.reset()
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Validation Error',
                    detail: 'Please check the form for errors',
                    life: 3000
                })
            }
        })
    }
}

const cancel = () => {
    router.get(route('customers.index'))
}

const getErrorMessage = (field) => {
    return form.errors[field] || ''
}
</script>

<template>
    <div>
        <Toast />
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEditing ? 'Edit Customer' : 'Create New Customer' }}
                </h1>
                <p class="text-gray-600 mt-1">
                    {{ isEditing ? 'Update customer information' : 'Fill in the details below to create a new customer' }}
                </p>
            </div>
            
            <Link :href="route('customers.index')">
                <Button label="Back to Customers" icon="pi pi-arrow-left" text />
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Information -->
                <div class="lg:col-span-2 space-y-6">
                    <Card>
                        <template #title>
                            Basic Information
                        </template>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Name *
                                    </label>
                                    <InputText
                                        id="name"
                                        v-model="form.name"
                                        :class="{ 'p-invalid': form.errors.name }"
                                        class="w-full"
                                        placeholder="Customer name"
                                        required
                                    />
                                    <Message v-if="form.errors.name" severity="error" :closable="false">
                                        {{ form.errors.name }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="legal_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Legal Name
                                    </label>
                                    <InputText
                                        id="legal_name"
                                        v-model="form.legal_name"
                                        class="w-full"
                                        placeholder="Legal entity name (if different)"
                                    />
                                </div>

                                <div>
                                    <label for="customer_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Customer Number
                                    </label>
                                    <InputText
                                        id="customer_number"
                                        v-model="form.customer_number"
                                        :class="{ 'p-invalid': form.errors.customer_number }"
                                        class="w-full"
                                        placeholder="Will be auto-generated if empty"
                                    />
                                    <Message v-if="form.errors.customer_number" severity="error" :closable="false">
                                        {{ form.errors.customer_number }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                        Status
                                    </label>
                                    <Dropdown
                                        id="status"
                                        v-model="form.status"
                                        :options="statusOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select status"
                                        class="w-full"
                                    />
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email
                                    </label>
                                    <InputText
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        :class="{ 'p-invalid': form.errors.email }"
                                        class="w-full"
                                        placeholder="customer@example.com"
                                    />
                                    <Message v-if="form.errors.email" severity="error" :closable="false">
                                        {{ form.errors.email }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone
                                    </label>
                                    <InputText
                                        id="phone"
                                        v-model="form.phone"
                                        class="w-full"
                                        placeholder="+1 (555) 123-4567"
                                    />
                                </div>

                                <div>
                                    <label for="website" class="block text-sm font-medium text-gray-700 mb-2">
                                        Website
                                    </label>
                                    <InputText
                                        id="website"
                                        v-model="form.website"
                                        :class="{ 'p-invalid': form.errors.website }"
                                        class="w-full"
                                        placeholder="https://example.com"
                                    />
                                    <Message v-if="form.errors.website" severity="error" :closable="false">
                                        {{ form.errors.website }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Tax ID
                                    </label>
                                    <InputText
                                        id="tax_id"
                                        v-model="form.tax_id"
                                        class="w-full"
                                        placeholder="Tax identification number"
                                    />
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes
                                </label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="4"
                                    class="w-full"
                                    placeholder="Internal notes about this customer..."
                                />
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            Financial Information
                        </template>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="default_currency" class="block text-sm font-medium text-gray-700 mb-2">
                                        Default Currency *
                                    </label>
                                    <Dropdown
                                        id="default_currency"
                                        v-model="form.default_currency"
                                        :options="defaultCurrencyOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select currency"
                                        class="w-full"
                                        :class="{ 'p-invalid': form.errors.default_currency }"
                                    />
                                    <Message v-if="form.errors.default_currency" severity="error" :closable="false">
                                        {{ form.errors.default_currency }}
                                    </Message>
                                </div>

                                <div>
                                    <label for="payment_terms" class="block text-sm font-medium text-gray-700 mb-2">
                                        Payment Terms
                                    </label>
                                    <Dropdown
                                        id="payment_terms"
                                        v-model="form.payment_terms"
                                        :options="defaultPaymentTerms"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select payment terms"
                                        class="w-full"
                                    />
                                </div>

                                <div>
                                    <label for="credit_limit" class="block text-sm font-medium text-gray-700 mb-2">
                                        Credit Limit
                                    </label>
                                    <InputNumber
                                        id="credit_limit"
                                        v-model="form.credit_limit"
                                        mode="currency"
                                        currency="USD"
                                        locale="en-US"
                                        :min="0"
                                        class="w-full"
                                        placeholder="No limit"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <template #title>
                            Quick Actions
                        </template>
                        <template #content>
                            <div class="space-y-3">
                                <Button
                                    label="Save Customer"
                                    icon="pi pi-check"
                                    type="submit"
                                    :loading="form.processing"
                                    class="w-full"
                                />
                                
                                <Button
                                    label="Cancel"
                                    icon="pi pi-times"
                                    severity="secondary"
                                    @click="cancel"
                                    class="w-full"
                                />
                            </div>
                        </template>
                    </Card>

                    <Card v-if="isEditing">
                        <template #title>
                            Customer Information
                        </template>
                        <template #content>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-medium">ID:</span>
                                    <span class="ml-2 font-mono text-xs">{{ customer.id }}</span>
                                </div>
                                <div>
                                    <span class="font-medium">Created:</span>
                                    <span class="ml-2">{{ new Date(customer.created_at).toLocaleDateString() }}</span>
                                </div>
                                <div>
                                    <span class="font-medium">Last Updated:</span>
                                    <span class="ml-2">{{ new Date(customer.updated_at).toLocaleDateString() }}</span>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>
        </form>
    </div>
</template>