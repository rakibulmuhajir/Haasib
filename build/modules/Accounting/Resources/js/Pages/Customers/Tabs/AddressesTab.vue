<script setup>
import { ref, computed } from 'vue'
import { useToast } from "@/components/ui/toast/use-toast"
import { useI18n } from 'vue-i18n'
// import DataTable from 'primevue/datatable'
// import Column from 'primevue/column'
// import Button from 'primevue/button'
// import Dialog from 'primevue/dialog'
// import InputText from 'primevue/inputtext'
// import Textarea from 'primevue/textarea'
// import Dropdown from 'primevue/dropdown'
// import Checkbox from 'primevue/checkbox'
// import Tag from 'primevue/tag'
// import Toast from 'primevue/toast'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    customer: Object,
    addresses: Array,
    can: Object
})

const emit = defineEmits(['refresh'])

const toast = useToast()
const { t } = useI18n()

// Dialog states
const addressDialog = ref(false)
const deleteAddressDialog = ref(false)
const editingAddress = ref(null)
const deletingAddress = ref(null)

// Form
const form = useForm({
    address_line_1: '',
    address_line_2: '',
    city: '',
    state_province: '',
    postal_code: '',
    country: '',
    address_type: 'billing',
    is_default: false,
    is_active: true,
    notes: ''
})

// Options
const addressTypes = [
    { label: 'Billing', value: 'billing' },
    { label: 'Shipping', value: 'shipping' },
    { label: 'Both', value: 'both' },
    { label: 'Other', value: 'other' }
]

const countries = [
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
    { label: 'Mexico', value: 'MX' }
]

// Computed properties
const hasAddresses = computed(() => props.addresses && props.addresses.length > 0)

const defaultBillingAddress = computed(() => {
    return props.addresses?.find(addr => 
        addr.is_default && (addr.address_type === 'billing' || addr.address_type === 'both')
    ) || null
})

const defaultShippingAddress = computed(() => {
    return props.addresses?.find(addr => 
        addr.is_default && (addr.address_type === 'shipping' || addr.address_type === 'both')
    ) || null
})

const groupedAddresses = computed(() => {
    const groups = {
        billing: [],
        shipping: [],
        both: [],
        other: []
    }
    
    props.addresses?.forEach(address => {
        groups[address.address_type]?.push(address)
    })
    
    return groups
})

// Methods
const openNewAddressDialog = () => {
    editingAddress.value = null
    form.reset()
    form.clearErrors()
    addressDialog.value = true
}

const openEditAddressDialog = (address) => {
    editingAddress.value = address
    form.defaults({
        address_line_1: address.address_line_1,
        address_line_2: address.address_line_2 || '',
        city: address.city,
        state_province: address.state_province,
        postal_code: address.postal_code,
        country: address.country,
        address_type: address.address_type,
        is_default: address.is_default,
        is_active: address.is_active,
        notes: address.notes || ''
    })
    form.reset()
    form.clearErrors()
    addressDialog.value = true
}

const confirmDeleteAddress = (address) => {
    deletingAddress.value = address
    deleteAddressDialog.value = true
}

const saveAddress = () => {
    const url = editingAddress.value 
        ? route('customers.addresses.update', [props.customer.id, editingAddress.value.id])
        : route('customers.addresses.store', props.customer.id)
    
    const method = editingAddress.value ? 'put' : 'post'

    form.submit(method, url, {
        onSuccess: () => {
            addressDialog.value = false
            editingAddress.value = null
            form.reset()
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: editingAddress.value ? 'Address updated successfully' : 'Address created successfully',
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

const deleteAddress = () => {
    if (!deletingAddress.value) return

    form.delete(route('customers.addresses.destroy', [props.customer.id, deletingAddress.value.id]), {
        onSuccess: () => {
            deleteAddressDialog.value = false
            deletingAddress.value = null
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Address deleted successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.error || 'Failed to delete address',
                life: 3000
            })
        }
    })
}

const setAsDefault = (address) => {
    if (address.is_default) return

    form.post(route('customers.addresses.set-default', [props.customer.id, address.id]), {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Default address updated successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.error || 'Failed to update default address',
                life: 3000
            })
        }
    })
}

const getAddressTypeLabel = (type) => {
    const typeObj = addressTypes.find(t => t.value === type)
    return typeObj ? typeObj.label : type
}

const getAddressTypeColor = (type) => {
    switch (type) {
        case 'billing': return 'bg-blue-100 text-blue-800'
        case 'shipping': return 'bg-green-100 text-green-800'
        case 'both': return 'bg-purple-100 text-purple-800'
        default: return 'bg-gray-100 text-gray-800'
    }
}

const formatFullAddress = (address) => {
    const parts = [
        address.address_line_1,
        address.address_line_2,
        `${address.city}, ${address.state_province} ${address.postal_code}`,
        address.country
    ].filter(Boolean)
    
    return parts.join(', ')
}
</script>

<template>
    <div>
        <Toast />
        
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Customer Addresses</h3>
                <p class="text-sm text-gray-600">Manage billing and shipping addresses for this customer</p>
            </div>
            
            <Button
                v-if="can.create"
                label="Add Address"
                icon="pi pi-plus"
                @click="openNewAddressDialog"
            />
        </div>

        <!-- Default Addresses Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Default Billing Address -->
            <div v-if="defaultBillingAddress" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <Tag value="Default Billing" severity="info" />
                            <Tag 
                                :value="getAddressTypeLabel(defaultBillingAddress.address_type)" 
                                :class="getAddressTypeColor(defaultBillingAddress.address_type)"
                                size="small"
                            />
                        </div>
                        <div class="text-sm text-gray-700">
                            {{ formatFullAddress(defaultBillingAddress) }}
                        </div>
                    </div>
                    <Button
                        v-if="can.update"
                        icon="pi pi-pencil"
                        size="small"
                        text
                        @click="openEditAddressDialog(defaultBillingAddress)"
                    />
                </div>
            </div>
            
            <!-- Default Shipping Address -->
            <div v-if="defaultShippingAddress" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <Tag value="Default Shipping" severity="success" />
                            <Tag 
                                :value="getAddressTypeLabel(defaultShippingAddress.address_type)" 
                                :class="getAddressTypeColor(defaultShippingAddress.address_type)"
                                size="small"
                            />
                        </div>
                        <div class="text-sm text-gray-700">
                            {{ formatFullAddress(defaultShippingAddress) }}
                        </div>
                    </div>
                    <Button
                        v-if="can.update"
                        icon="pi pi-pencil"
                        size="small"
                        text
                        @click="openEditAddressDialog(defaultShippingAddress)"
                    />
                </div>
            </div>
        </div>

        <!-- Address Type Groups -->
        <div v-if="hasAddresses" class="space-y-6">
            <!-- Billing Addresses -->
            <div v-if="groupedAddresses.billing.length > 0">
                <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center gap-2">
                    <Tag value="Billing" severity="info" />
                    <span class="text-sm text-gray-600">({{ groupedAddresses.billing.length }})</span>
                </h4>
                <DataTable
                    :value="groupedAddresses.billing"
                    responsiveLayout="scroll"
                    class="p-datatable-sm mb-6"
                >
                    <Column field="address" header="Address" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.address_line_1 }}</div>
                                <div v-if="data.address_line_2" class="text-sm text-gray-600">
                                    {{ data.address_line_2 }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ data.city }}, {{ data.state_province }} {{ data.postal_code }}
                                </div>
                                <div class="text-sm text-gray-600">{{ data.country }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="is_default" header="Default" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                v-if="data.is_default" 
                                value="Yes" 
                                severity="success" 
                                size="small"
                            />
                            <Button
                                v-else-if="can.update"
                                label="Set as Default"
                                size="small"
                                text
                                @click="setAsDefault(data)"
                            />
                            <span v-else class="text-gray-400 italic">No</span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Status" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                :value="data.is_active ? 'Active' : 'Inactive'" 
                                :severity="data.is_active ? 'success' : 'warning'"
                                size="small"
                            />
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
                                    @click="openEditAddressDialog(data)"
                                />
                                <Button
                                    v-if="can.delete"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    severity="danger"
                                    @click="confirmDeleteAddress(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Shipping Addresses -->
            <div v-if="groupedAddresses.shipping.length > 0">
                <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center gap-2">
                    <Tag value="Shipping" severity="success" />
                    <span class="text-sm text-gray-600">({{ groupedAddresses.shipping.length }})</span>
                </h4>
                <DataTable
                    :value="groupedAddresses.shipping"
                    responsiveLayout="scroll"
                    class="p-datatable-sm mb-6"
                >
                    <Column field="address" header="Address" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.address_line_1 }}</div>
                                <div v-if="data.address_line_2" class="text-sm text-gray-600">
                                    {{ data.address_line_2 }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ data.city }}, {{ data.state_province }} {{ data.postal_code }}
                                </div>
                                <div class="text-sm text-gray-600">{{ data.country }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="is_default" header="Default" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                v-if="data.is_default" 
                                value="Yes" 
                                severity="success" 
                                size="small"
                            />
                            <Button
                                v-else-if="can.update"
                                label="Set as Default"
                                size="small"
                                text
                                @click="setAsDefault(data)"
                            />
                            <span v-else class="text-gray-400 italic">No</span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Status" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                :value="data.is_active ? 'Active' : 'Inactive'" 
                                :severity="data.is_active ? 'success' : 'warning'"
                                size="small"
                            />
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
                                    @click="openEditAddressDialog(data)"
                                />
                                <Button
                                    v-if="can.delete"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    severity="danger"
                                    @click="confirmDeleteAddress(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Both/Other Type Addresses -->
            <div v-if="groupedAddresses.both.length > 0 || groupedAddresses.other.length > 0">
                <h4 class="text-md font-medium text-gray-900 mb-3">Other Addresses</h4>
                <DataTable
                    :value="[...groupedAddresses.both, ...groupedAddresses.other]"
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                >
                    <Column field="address" header="Address" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.address_line_1 }}</div>
                                <div v-if="data.address_line_2" class="text-sm text-gray-600">
                                    {{ data.address_line_2 }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ data.city }}, {{ data.state_province }} {{ data.postal_code }}
                                </div>
                                <div class="text-sm text-gray-600">{{ data.country }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="address_type" header="Type" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag 
                                :value="getAddressTypeLabel(data.address_type)" 
                                :class="getAddressTypeColor(data.address_type)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <Column field="is_default" header="Default" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                v-if="data.is_default" 
                                value="Yes" 
                                severity="success" 
                                size="small"
                            />
                            <Button
                                v-else-if="can.update"
                                label="Set as Default"
                                size="small"
                                text
                                @click="setAsDefault(data)"
                            />
                            <span v-else class="text-gray-400 italic">No</span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Status" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag 
                                :value="data.is_active ? 'Active' : 'Inactive'" 
                                :severity="data.is_active ? 'success' : 'warning'"
                                size="small"
                            />
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
                                    @click="openEditAddressDialog(data)"
                                />
                                <Button
                                    v-if="can.delete"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    severity="danger"
                                    @click="confirmDeleteAddress(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12 text-gray-500">
            <i class="pi pi-map-marker text-4xl mb-4 text-gray-300"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No addresses found</h3>
            <p class="text-sm text-gray-600 mb-4">
                Get started by adding the first address for this customer.
            </p>
            <Button
                v-if="can.create"
                label="Add First Address"
                icon="pi pi-plus"
                @click="openNewAddressDialog"
            />
        </div>

        <!-- Address Form Dialog -->
        <Dialog
            v-model:visible="addressDialog"
            :style="{ width: '650px' }"
            :header="editingAddress ? 'Edit Address' : 'Add Address'"
            :modal="true"
        >
            <form @submit.prevent="saveAddress">
                <div class="space-y-4">
                    <!-- Address Lines -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Address Line 1 *
                        </label>
                        <InputText
                            v-model="form.address_line_1"
                            :class="{ 'p-invalid': form.errors.address_line_1 }"
                            class="w-full"
                            required
                        />
                        <small v-if="form.errors.address_line_1" class="text-red-500">
                            {{ form.errors.address_line_1 }}
                        </small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Address Line 2
                        </label>
                        <InputText
                            v-model="form.address_line_2"
                            :class="{ 'p-invalid': form.errors.address_line_2 }"
                            class="w-full"
                            placeholder="Apartment, suite, unit, building, floor, etc."
                        />
                        <small v-if="form.errors.address_line_2" class="text-red-500">
                            {{ form.errors.address_line_2 }}
                        </small>
                    </div>

                    <!-- City, State, Postal Code -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                City *
                            </label>
                            <InputText
                                v-model="form.city"
                                :class="{ 'p-invalid': form.errors.city }"
                                class="w-full"
                                required
                            />
                            <small v-if="form.errors.city" class="text-red-500">
                                {{ form.errors.city }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                State/Province *
                            </label>
                            <InputText
                                v-model="form.state_province"
                                :class="{ 'p-invalid': form.errors.state_province }"
                                class="w-full"
                                required
                            />
                            <small v-if="form.errors.state_province" class="text-red-500">
                                {{ form.errors.state_province }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Postal Code *
                            </label>
                            <InputText
                                v-model="form.postal_code"
                                :class="{ 'p-invalid': form.errors.postal_code }"
                                class="w-full"
                                required
                            />
                            <small v-if="form.errors.postal_code" class="text-red-500">
                                {{ form.errors.postal_code }}
                            </small>
                        </div>
                    </div>

                    <!-- Country & Address Type -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Country *
                            </label>
                            <Dropdown
                                v-model="form.country"
                                :options="countries"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': form.errors.country }"
                                class="w-full"
                                placeholder="Select country"
                                required
                            />
                            <small v-if="form.errors.country" class="text-red-500">
                                {{ form.errors.country }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Address Type *
                            </label>
                            <Dropdown
                                v-model="form.address_type"
                                :options="addressTypes"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': form.errors.address_type }"
                                class="w-full"
                                placeholder="Select type"
                                required
                            />
                            <small v-if="form.errors.address_type" class="text-red-500">
                                {{ form.errors.address_type }}
                            </small>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Notes
                        </label>
                        <Textarea
                            v-model="form.notes"
                            :class="{ 'p-invalid': form.errors.notes }"
                            class="w-full"
                            rows="3"
                            placeholder="Any additional notes about this address"
                        />
                        <small v-if="form.errors.notes" class="text-red-500">
                            {{ form.errors.notes }}
                        </small>
                    </div>

                    <!-- Toggles -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="form.is_default"
                                inputId="is_default"
                                binary
                            />
                            <label for="is_default" class="text-sm text-gray-700">
                                Set as Default Address
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="form.is_active"
                                inputId="is_active"
                                binary
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
                        @click="addressDialog = false"
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
            v-model:visible="deleteAddressDialog"
            :style="{ width: '450px' }"
            header="Confirm Delete"
            :modal="true"
        >
            <div class="confirmation-content flex items-center">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem"></i>
                <span v-if="deletingAddress">
                    Are you sure you want to delete this address?
                    This action cannot be undone.
                </span>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    @click="deleteAddressDialog = false"
                />
                <Button
                    label="Delete"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteAddress"
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