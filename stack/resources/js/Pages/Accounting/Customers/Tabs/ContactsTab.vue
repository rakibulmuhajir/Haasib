<script setup>
import { ref, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputMask from 'primevue/inputmask'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    customer: Object,
    contacts: Array,
    can: Object
})

const emit = defineEmits(['refresh'])

const toast = useToast()
const { t } = useI18n()

// Dialog states
const contactDialog = ref(false)
const deleteContactDialog = ref(false)
const editingContact = ref(null)
const deletingContact = ref(null)

// Form
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    job_title: '',
    department: '',
    is_primary: false,
    is_active: true,
    notes: ''
})

// Options
const departments = [
    { label: 'Executive', value: 'executive' },
    { label: 'Sales', value: 'sales' },
    { label: 'Marketing', value: 'marketing' },
    { label: 'Finance', value: 'finance' },
    { label: 'Operations', value: 'operations' },
    { label: 'Customer Service', value: 'customer_service' },
    { label: 'IT', value: 'it' },
    { label: 'HR', value: 'hr' },
    { label: 'Other', value: 'other' }
]

// Computed properties
const hasContacts = computed(() => props.contacts && props.contacts.length > 0)

const primaryContact = computed(() => {
    return props.contacts?.find(contact => contact.is_primary) || null
})

// Methods
const openNewContactDialog = () => {
    editingContact.value = null
    form.reset()
    form.clearErrors()
    contactDialog.value = true
}

const openEditContactDialog = (contact) => {
    editingContact.value = contact
    form.defaults({
        first_name: contact.first_name,
        last_name: contact.last_name,
        email: contact.email || '',
        phone: contact.phone || '',
        mobile: contact.mobile || '',
        job_title: contact.job_title || '',
        department: contact.department || '',
        is_primary: contact.is_primary,
        is_active: contact.is_active,
        notes: contact.notes || ''
    })
    form.reset()
    form.clearErrors()
    contactDialog.value = true
}

const confirmDeleteContact = (contact) => {
    deletingContact.value = contact
    deleteContactDialog.value = true
}

const saveContact = () => {
    const url = editingContact.value 
        ? route('customers.contacts.update', [props.customer.id, editingContact.value.id])
        : route('customers.contacts.store', props.customer.id)
    
    const method = editingContact.value ? 'put' : 'post'

    form.submit(method, url, {
        onSuccess: () => {
            contactDialog.value = false
            editingContact.value = null
            form.reset()
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: editingContact.value ? 'Contact updated successfully' : 'Contact created successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors',
                life: 3000
            })
        }
    })
}

const deleteContact = () => {
    if (!deletingContact.value) return

    form.delete(route('customers.contacts.destroy', [props.customer.id, deletingContact.value.id]), {
        onSuccess: () => {
            deleteContactDialog.value = false
            deletingContact.value = null
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Contact deleted successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.error || 'Failed to delete contact',
                life: 3000
            })
        }
    })
}

const setAsPrimary = (contact) => {
    if (contact.is_primary) return

    form.post(route('customers.contacts.set-primary', [props.customer.id, contact.id]), {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Primary contact updated successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.error || 'Failed to update primary contact',
                life: 3000
            })
        }
    })
}

const getSeverity = (isActive) => {
    return isActive ? 'success' : 'warning'
}

const formatPhone = (phone) => {
    if (!phone) return 'N/A'
    // Basic phone formatting - could be enhanced
    return phone.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3')
}

const getContactType = (contact) => {
    if (contact.is_primary) return { label: 'Primary', color: 'bg-blue-100 text-blue-800' }
    if (!contact.is_active) return { label: 'Inactive', color: 'bg-gray-100 text-gray-800' }
    return { label: 'Active', color: 'bg-green-100 text-green-800' }
}
</script>

<template>
    <div>
        <Toast />
        
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Customer Contacts</h3>
                <p class="text-sm text-gray-600">Manage contact information for this customer</p>
            </div>
            
            <Button
                v-if="can.create"
                label="Add Contact"
                icon="pi pi-plus"
                @click="openNewContactDialog"
            />
        </div>

        <!-- Primary Contact Summary -->
        <div v-if="primaryContact" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Tag value="Primary Contact" severity="info" />
                        <span class="font-medium">
                            {{ primaryContact.first_name }} {{ primaryContact.last_name }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span v-if="primaryContact.email">{{ primaryContact.email }}</span>
                        <span v-if="primaryContact.email && primaryContact.phone"> • </span>
                        <span v-if="primaryContact.phone">{{ formatPhone(primaryContact.phone) }}</span>
                    </div>
                </div>
                <Button
                    v-if="can.update"
                    icon="pi pi-pencil"
                    size="small"
                    text
                    @click="openEditContactDialog(primaryContact)"
                />
            </div>
        </div>

        <!-- Contacts Table -->
        <DataTable
            v-if="hasContacts"
            :value="contacts"
            :paginator="contacts.length > 10"
            :rows="10"
            responsiveLayout="scroll"
            class="p-datatable-sm"
        >
            <Column field="name" header="Name" style="min-width: 12rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <div>
                            <div class="font-medium">
                                {{ data.first_name }} {{ data.last_name }}
                            </div>
                            <div v-if="data.job_title" class="text-sm text-gray-600">
                                {{ data.job_title }}
                            </div>
                        </div>
                        <Tag 
                            :value="getContactType(data).label" 
                            :class="getContactType(data).color"
                            size="small"
                        />
                    </div>
                </template>
            </Column>

            <Column field="email" header="Email" style="min-width: 12rem">
                <template #body="{ data }">
                    <span v-if="data.email">{{ data.email }}</span>
                    <span v-else class="text-gray-400 italic">No email</span>
                </template>
            </Column>

            <Column field="phone" header="Phone" style="min-width: 10rem">
                <template #body="{ data }">
                    <div class="space-y-1">
                        <div v-if="data.phone" class="text-sm">
                            <span class="text-gray-600">P: </span>{{ formatPhone(data.phone) }}
                        </div>
                        <div v-if="data.mobile" class="text-sm">
                            <span class="text-gray-600">M: </span>{{ formatPhone(data.mobile) }}
                        </div>
                        <div v-if="!data.phone && !data.mobile" class="text-gray-400 italic">
                            No phone
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="department" header="Department" style="min-width: 8rem">
                <template #body="{ data }">
                    <span v-if="data.department" class="capitalize">{{ data.department.replace('_', ' ') }}</span>
                    <span v-else class="text-gray-400 italic">N/A</span>
                </template>
            </Column>

            <Column field="is_primary" header="Primary" style="min-width: 6rem">
                <template #body="{ data }">
                    <Tag 
                        v-if="data.is_primary" 
                        value="Yes" 
                        severity="success" 
                        size="small"
                    />
                    <Button
                        v-else-if="can.update"
                        label="Set as Primary"
                        size="small"
                        text
                        @click="setAsPrimary(data)"
                    />
                    <span v-else class="text-gray-400 italic">No</span>
                </template>
            </Column>

            <Column header="Actions" style="min-width: 8rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button
                            v-if="can.update"
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="openEditContactDialog(data)"
                        />
                        <Button
                            v-if="can.delete"
                            icon="pi pi-trash"
                            size="small"
                            text
                            severity="danger"
                            @click="confirmDeleteContact(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Empty State -->
        <div v-else class="text-center py-12 text-gray-500">
            <i class="pi pi-users text-4xl mb-4 text-gray-300"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No contacts found</h3>
            <p class="text-sm text-gray-600 mb-4">
                Get started by adding the first contact for this customer.
            </p>
            <Button
                v-if="can.create"
                label="Add First Contact"
                icon="pi pi-plus"
                @click="openNewContactDialog"
            />
        </div>

        <!-- Contact Form Dialog -->
        <Dialog
            v-model:visible="contactDialog"
            :style="{ width: '600px' }"
            :header="editingContact ? 'Edit Contact' : 'Add Contact'"
            :modal="true"
        >
            <form @submit.prevent="saveContact">
                <div class="space-y-4">
                    <!-- Name Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                First Name *
                            </label>
                            <InputText
                                v-model="form.first_name"
                                :class="{ 'p-invalid': form.errors.first_name }"
                                class="w-full"
                                required
                            />
                            <small v-if="form.errors.first_name" class="text-red-500">
                                {{ form.errors.first_name }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Last Name *
                            </label>
                            <InputText
                                v-model="form.last_name"
                                :class="{ 'p-invalid': form.errors.last_name }"
                                class="w-full"
                                required
                            />
                            <small v-if="form.errors.last_name" class="text-red-500">
                                {{ form.errors.last_name }}
                            </small>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <InputText
                            v-model="form.email"
                            type="email"
                            :class="{ 'p-invalid': form.errors.email }"
                            class="w-full"
                        />
                        <small v-if="form.errors.email" class="text-red-500">
                            {{ form.errors.email }}
                        </small>
                    </div>

                    <!-- Phone Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Phone
                            </label>
                            <InputMask
                                v-model="form.phone"
                                mask="(999) 999-9999"
                                :class="{ 'p-invalid': form.errors.phone }"
                                class="w-full"
                            />
                            <small v-if="form.errors.phone" class="text-red-500">
                                {{ form.errors.phone }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Mobile
                            </label>
                            <InputMask
                                v-model="form.mobile"
                                mask="(999) 999-9999"
                                :class="{ 'p-invalid': form.errors.mobile }"
                                class="w-full"
                            />
                            <small v-if="form.errors.mobile" class="text-red-500">
                                {{ form.errors.mobile }}
                            </small>
                        </div>
                    </div>

                    <!-- Job Title & Department -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Job Title
                            </label>
                            <InputText
                                v-model="form.job_title"
                                :class="{ 'p-invalid': form.errors.job_title }"
                                class="w-full"
                            />
                            <small v-if="form.errors.job_title" class="text-red-500">
                                {{ form.errors.job_title }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Department
                            </label>
                            <Dropdown
                                v-model="form.department"
                                :options="departments"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': form.errors.department }"
                                class="w-full"
                                placeholder="Select department"
                            />
                            <small v-if="form.errors.department" class="text-red-500">
                                {{ form.errors.department }}
                            </small>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Notes
                        </label>
                        <InputText
                            v-model="form.notes"
                            :class="{ 'p-invalid': form.errors.notes }"
                            class="w-full"
                        />
                        <small v-if="form.errors.notes" class="text-red-500">
                            {{ form.errors.notes }}
                        </small>
                    </div>

                    <!-- Toggles -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.is_primary"
                                type="checkbox"
                                id="is_primary"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label for="is_primary" class="text-sm text-gray-700">
                                Set as Primary Contact
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                id="is_active"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label for="is_active" class="text-sm text-gray-700">
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        text
                        @click="contactDialog = false"
                        :disabled="form.processing"
                    />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        type="submit"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-model:visible="deleteContactDialog"
            :style="{ width: '450px' }"
            header="Confirm Delete"
            :modal="true"
        >
            <div class="confirmation-content flex items-center">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem"></i>
                <span v-if="deletingContact">
                    Are you sure you want to delete 
                    <strong>{{ deletingContact.first_name }} {{ deletingContact.last_name }}</strong>?
                    This action cannot be undone.
                </span>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    @click="deleteContactDialog = false"
                />
                <Button
                    label="Delete"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteContact"
                />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.confirmation-content {
    align-items: center;
}
</style>