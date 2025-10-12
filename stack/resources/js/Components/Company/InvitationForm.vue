<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Calendar from 'primevue/calendar'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tooltip from 'primevue/tooltip'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    companyId: {
        type: String,
        required: true
    },
    showForm: {
        type: Boolean,
        default: true
    },
    showList: {
        type: Boolean,
        default: true
    },
    readonly: {
        type: Boolean,
        default: false
    }
})

const { t } = useI18n()
const page = usePage()
const confirm = useConfirm()
const toast = ref()

// Form setup
const invitationForm = useForm({
    email: '',
    role: 'employee',
    message: '',
    expires_in_days: 7
})

// Reactive data
const loading = ref(false)
const invitations = ref([])
const totalRecords = ref(0)
const filters = ref({
    search: '',
    role: null,
    status: null
})
const pagination = ref({
    page: 1,
    per_page: 15
})

// Dialog states
const showInviteDialog = ref(false)
const showResendDialog = ref(false)
const selectedInvitation = ref(null)
const loadingAction = ref(false)

// Options
const roleOptions = [
    { label: 'Owner', value: 'owner' },
    { label: 'Admin', value: 'admin' },
    { label: 'Accountant', value: 'accountant' },
    { label: 'Member', value: 'member' },
    { label: 'Viewer', value: 'viewer' }
]

const statusOptions = [
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Expired', value: 'expired' }
]

const expiresInOptions = [
    { label: '3 days', value: 3 },
    { label: '7 days', value: 7 },
    { label: '14 days', value: 14 },
    { label: '30 days', value: 30 }
]

// Computed properties
const currentCompany = computed(() => page.props.currentCompany)
const userRole = computed(() => page.props.currentCompany?.userRole)
const canInvite = computed(() => {
    return !props.readonly && (userRole.value === 'owner' || userRole.value === 'admin')
})

const pendingInvitationsCount = computed(() => {
    return invitations.value.filter(inv => inv.status === 'pending').length
})

const filteredInvitations = computed(() => {
    let filtered = invitations.value

    if (filters.value.search) {
        const search = filters.value.search.toLowerCase()
        filtered = filtered.filter(inv => 
            inv.email.toLowerCase().includes(search)
        )
    }

    if (filters.value.role) {
        filtered = filtered.filter(inv => inv.role === filters.value.role)
    }

    if (filters.value.status) {
        filtered = filtered.filter(inv => inv.status === filters.value.status)
    }

    return filtered
})

// Methods
const loadInvitations = async () => {
    loading.value = true
    try {
        const params = new URLSearchParams({
            page: pagination.value.page,
            per_page: pagination.value.per_page
        })

        const response = await fetch(`/api/v1/companies/${props.companyId}/invitations?${params}`)
        const data = await response.json()
        
        if (response.ok) {
            invitations.value = data.data || []
            totalRecords.value = data.meta?.total || 0
        } else {
            throw new Error(data.message || 'Failed to load invitations')
        }
    } catch (error) {
        console.error('Failed to load invitations:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load company invitations',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const openInviteDialog = () => {
    invitationForm.reset()
    invitationForm.clearErrors()
    showInviteDialog.value = true
}

const sendInvitation = async () => {
    loadingAction.value = true
    
    try {
        await invitationForm.post(`/api/v1/companies/${props.companyId}/invitations`, {
            onSuccess: () => {
                toast.value.add({
                    severity: 'success',
                    summary: 'Success!',
                    detail: 'Invitation sent successfully',
                    life: 3000
                })
                
                showInviteDialog.value = false
                loadInvitations()
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
                loadingAction.value = false
            }
        })
    } catch (error) {
        console.error('Invitation sending error:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to send invitation. Please try again.',
            life: 3000
        })
        loadingAction.value = false
    }
}

const applyFilters = () => {
    pagination.value.page = 1
}

const clearFilters = () => {
    filters.value = {
        search: '',
        role: null,
        status: null
    }
    pagination.value.page = 1
}

const onPage = (event) => {
    pagination.value.page = event.page + 1
    pagination.value.per_page = event.rows
    loadInvitations()
}

const openResendDialog = (invitation) => {
    if (props.readonly || invitation.status !== 'pending') return
    
    selectedInvitation.value = invitation
    showResendDialog.value = true
}

const resendInvitation = async () => {
    if (!selectedInvitation.value) return

    loadingAction.value = true
    try {
        const response = await fetch(`/api/v1/companies/${props.companyId}/invitations/${selectedInvitation.value.id}/resend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invitation resent successfully',
                life: 3000
            })
            
            showResendDialog.value = false
            loadInvitations()
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to resend invitation')
        }
    } catch (error) {
        console.error('Failed to resend invitation:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to resend invitation',
            life: 3000
        })
    } finally {
        loadingAction.value = false
        selectedInvitation.value = null
    }
}

const confirmRevokeInvitation = (invitation) => {
    if (props.readonly || !invitation.can_be_revoked) return

    selectedInvitation.value = invitation
    
    confirm.require({
        message: `Are you sure you want to revoke the invitation to ${invitation.email}?`,
        header: 'Confirm Revocation',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptLabel: 'Revoke',
        acceptClass: 'p-button-warning',
        accept: () => revokeInvitation()
    })
}

const revokeInvitation = async () => {
    if (!selectedInvitation.value) return

    loadingAction.value = true
    try {
        const response = await fetch(`/api/v1/invitations/${selectedInvitation.value.id}/revoke`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invitation revoked successfully',
                life: 3000
            })
            
            loadInvitations()
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to revoke invitation')
        }
    } catch (error) {
        console.error('Failed to revoke invitation:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to revoke invitation',
            life: 3000
        })
    } finally {
        loadingAction.value = false
        selectedInvitation.value = null
    }
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

const getStatusSeverity = (status) => {
    switch (status) {
        case 'pending': return 'warning'
        case 'accepted': return 'success'
        case 'rejected': return 'danger'
        case 'expired': return 'secondary'
        case 'revoked': return 'danger'
        default: return 'secondary'
    }
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const getDaysUntilExpiry = (expiresAt) => {
    const now = new Date()
    const expiry = new Date(expiresAt)
    const diffTime = expiry - now
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays
}

// Watch for company changes
watch(() => props.companyId, () => {
    loadInvitations()
})

// Lifecycle
onMounted(() => {
    loadInvitations()
})
</script>

<template>
    <div class="company-invitation-form">
        <Toast ref="toast" />
        <ConfirmDialog />
        
        <!-- Invitation Form -->
        <Card v-if="showForm" class="mb-6">
            <template #title>Send New Invitation</template>
            <template #content>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <InputText
                                id="email"
                                v-model="invitationForm.email"
                                placeholder="Enter email address"
                                :class="{ 'p-invalid': invitationForm.errors.email }"
                                class="w-full"
                            />
                            <Message v-if="invitationForm.errors.email" severity="error" :closable="false">
                                {{ invitationForm.errors.email }}
                            </Message>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Role <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                id="role"
                                v-model="invitationForm.role"
                                :options="roleOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select role"
                                :class="{ 'p-invalid': invitationForm.errors.role }"
                                class="w-full"
                            />
                            <Message v-if="invitationForm.errors.role" severity="error" :closable="false">
                                {{ invitationForm.errors.role }}
                            </Message>
                        </div>

                        <div>
                            <label for="expires_in_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Expires In
                            </label>
                            <Dropdown
                                id="expires_in_days"
                                v-model="invitationForm.expires_in_days"
                                :options="expiresInOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select expiry period"
                                class="w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Personal Message (Optional)
                        </label>
                        <Textarea
                            id="message"
                            v-model="invitationForm.message"
                            placeholder="Add a personal message for the invitee..."
                            rows="3"
                            class="w-full"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button
                            @click="sendInvitation"
                            :loading="loadingAction"
                            icon="pi pi-send"
                            label="Send Invitation"
                            :disabled="!canInvite"
                        />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Invitations List -->
        <Card v-if="showList">
            <template #title>
                <div class="flex justify-between items-center">
                    <span>Company Invitations</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Pending: {{ pendingInvitationsCount }}
                        </span>
                        <Button
                            v-if="canInvite"
                            @click="openInviteDialog"
                            icon="pi pi-plus"
                            label="New Invitation"
                            size="small"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <!-- Filters -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <InputText
                        v-model="filters.search"
                        placeholder="Search invitations..."
                        class="w-full"
                        @keyup.enter="applyFilters"
                    />
                    
                    <Dropdown
                        v-model="filters.role"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Filter by role"
                        show-clear
                        class="w-full"
                        @change="applyFilters"
                    />
                    
                    <Dropdown
                        v-model="filters.status"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Filter by status"
                        show-clear
                        class="w-full"
                        @change="applyFilters"
                    />
                </div>

                <DataTable
                    :value="filteredInvitations"
                    :loading="loading"
                    :paginator="true"
                    :rows="pagination.per_page"
                    :first="(pagination.page - 1) * pagination.per_page"
                    lazy
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    current-page-report-template="Showing {first} to {last} of {totalRecords} invitations"
                    @page="onPage"
                    scrollable
                    scroll-height="400px"
                >
                    <Column field="email" header="Email" sortable />
                    
                    <Column field="role" header="Role" sortable>
                        <template #body="{ data }">
                            <Badge
                                :value="data.role"
                                :severity="getRoleSeverity(data.role)"
                            />
                        </template>
                    </Column>
                    
                    <Column field="status" header="Status" sortable>
                        <template #body="{ data }">
                            <Badge
                                :value="data.status"
                                :severity="getStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    
                    <Column field="expires_at" header="Expires" sortable>
                        <template #body="{ data }">
                            <div>
                                <div>{{ formatDate(data.expires_at) }}</div>
                                <div
                                    v-if="data.status === 'pending'"
                                    class="text-xs"
                                    :class="getDaysUntilExpiry(data.expires_at) <= 3 ? 'text-red-500' : 'text-gray-500'"
                                >
                                    {{ getDaysUntilExpiry(data.expires_at) }} days left
                                </div>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="created_at" header="Sent" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    
                    <Column v-if="!readonly" header="Actions">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    v-if="data.status === 'pending' && canInvite"
                                    @click="openResendDialog(data)"
                                    icon="pi pi-refresh"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    v-tooltip="'Resend invitation'"
                                />
                                
                                <Button
                                    v-if="data.can_be_revoked && canInvite"
                                    @click="confirmRevokeInvitation(data)"
                                    icon="pi pi-ban"
                                    size="small"
                                    severity="warning"
                                    outlined
                                    v-tooltip="'Revoke invitation'"
                                />
                                
                                <Button
                                    v-if="data.accept_url"
                                    @click="navigator.clipboard.writeText(data.accept_url)"
                                    icon="pi pi-copy"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    v-tooltip="'Copy invitation link'"
                                />
                            </div>
                        </template>
                    </Column>
                    
                    <template #empty>
                        <div class="text-center py-8">
                            <i class="pi pi-envelope text-4xl text-gray-400"></i>
                            <p class="text-gray-500 dark:text-gray-400 mt-4">
                                No invitations found
                            </p>
                            <Button
                                v-if="canInvite"
                                @click="openInviteDialog"
                                label="Send your first invitation"
                                icon="pi pi-plus"
                                class="mt-4"
                            />
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Resend Dialog -->
        <Dialog
            v-model:visible="showResendDialog"
            header="Resend Invitation"
            :modal="true"
            :style="{ width: '400px' }"
        >
            <div class="space-y-4">
                <p>
                    Are you sure you want to resend the invitation to
                    <strong>{{ selectedInvitation?.email }}</strong>?
                </p>
                
                <Message severity="info" :closable="false">
                    This will extend the expiry date and send a new email to the recipient.
                </Message>
            </div>
            
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    outlined
                    @click="showResendDialog = false"
                />
                <Button
                    label="Resend"
                    :loading="loadingAction"
                    @click="resendInvitation"
                />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.company-invitation-form {
    @apply space-y-4;
}
</style>