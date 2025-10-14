<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
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
    showActions: {
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

// Reactive data
const loading = ref(false)
const users = ref([])
const totalRecords = ref(0)
const filters = ref({
    search: '',
    role: null,
    is_active: null
})
const pagination = ref({
    page: 1,
    per_page: 15
})

// Dialog states
const showRoleDialog = ref(false)
const showRemoveDialog = ref(false)
const selectedUser = ref(null)
const selectedRole = ref('')
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
    { label: 'Active', value: true },
    { label: 'Inactive', value: false }
]

// Computed properties
const currentCompany = computed(() => page.props.currentCompany)
const userRole = computed(() => page.props.currentCompany?.userRole)
const canManageUsers = computed(() => {
    return userRole.value === 'owner' || userRole.value === 'admin'
})

const activeUsersCount = computed(() => {
    return users.value.filter(user => user.pivot?.is_active).length
})

const filteredUsers = computed(() => {
    let filtered = users.value

    if (filters.value.search) {
        const search = filters.value.search.toLowerCase()
        filtered = filtered.filter(user => 
            user.name.toLowerCase().includes(search) ||
            user.email.toLowerCase().includes(search)
        )
    }

    if (filters.value.role) {
        filtered = filtered.filter(user => user.pivot?.role === filters.value.role)
    }

    if (filters.value.is_active !== null) {
        filtered = filtered.filter(user => user.pivot?.is_active === filters.value.is_active)
    }

    return filtered
})

// Methods
const loadUsers = async () => {
    loading.value = true
    try {
        const params = new URLSearchParams({
            page: pagination.value.page,
            per_page: pagination.value.per_page
        })

        const response = await fetch(`/api/v1/companies/${props.companyId}/users?${params}`)
        const data = await response.json()
        
        if (response.ok) {
            users.value = data.data || []
            totalRecords.value = data.meta?.total || 0
        } else {
            throw new Error(data.message || 'Failed to load users')
        }
    } catch (error) {
        console.error('Failed to load users:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load company users',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const applyFilters = () => {
    pagination.value.page = 1
}

const clearFilters = () => {
    filters.value = {
        search: '',
        role: null,
        is_active: null
    }
    pagination.value.page = 1
}

const onPage = (event) => {
    pagination.value.page = event.page + 1
    pagination.value.per_page = event.rows
    loadUsers()
}

const openRoleDialog = (user) => {
    if (props.readonly) return
    
    selectedUser.value = user
    selectedRole.value = user.pivot?.role || ''
    showRoleDialog.value = true
}

const confirmRoleChange = async () => {
    if (!selectedUser.value || !selectedRole.value) return

    loadingAction.value = true
    try {
        const response = await fetch(`/api/v1/companies/${props.companyId}/users/${selectedUser.value.id}/role`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                role: selectedRole.value
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Role updated for ${selectedUser.value.name}`,
                life: 3000
            })
            
            showRoleDialog.value = false
            loadUsers()
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to update role')
        }
    } catch (error) {
        console.error('Failed to update user role:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to update user role',
            life: 3000
        })
    } finally {
        loadingAction.value = false
    }
}

const confirmRemoveUser = (user) => {
    if (props.readonly) return

    selectedUser.value = user
    
    confirm.require({
        message: `Are you sure you want to remove ${user.name} from this company?`,
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptLabel: 'Remove',
        acceptClass: 'p-button-danger',
        accept: () => removeUser()
    })
}

const removeUser = async () => {
    if (!selectedUser.value) return

    loadingAction.value = true
    try {
        const response = await fetch(`/api/v1/companies/${props.companyId}/users/${selectedUser.value.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `${selectedUser.value.name} removed from company`,
                life: 3000
            })
            
            loadUsers()
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to remove user')
        }
    } catch (error) {
        console.error('Failed to remove user:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to remove user',
            life: 3000
        })
    } finally {
        loadingAction.value = false
        selectedUser.value = null
    }
}

const toggleUserStatus = async (user) => {
    if (props.readonly) return

    const newStatus = !user.pivot?.is_active
    const action = newStatus ? 'activate' : 'deactivate'
    
    loadingAction.value = true
    try {
        const response = await fetch(`/api/v1/companies/${props.companyId}/users/${user.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                is_active: newStatus
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `User ${action}d successfully`,
                life: 3000
            })
            
            loadUsers()
        } else {
            const data = await response.json()
            throw new Error(data.message || 'Failed to update user status')
        }
    } catch (error) {
        console.error('Failed to update user status:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to update user status',
            life: 3000
        })
    } finally {
        loadingAction.value = false
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

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

// Watch for company changes
watch(() => props.companyId, () => {
    loadUsers()
})

// Lifecycle
onMounted(() => {
    loadUsers()
})
</script>

<template>
    <div class="company-member-list">
        <Toast ref="toast" />
        <ConfirmDialog />
        
        <!-- Header -->
        <Card class="mb-6">
            <template #title>Company Members</template>
            <template #content>
                <div class="flex justify-between items-center">
                    <div class="flex gap-4">
                        <div class="text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Total:</span>
                            <span class="font-semibold">{{ filteredUsers.length }}</span>
                        </div>
                        <div class="text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Active:</span>
                            <span class="font-semibold text-green-600">{{ activeUsersCount }}</span>
                        </div>
                    </div>
                    
                    <div v-if="!readonly && canManageUsers" class="flex gap-2">
                        <Button
                            icon="pi pi-refresh"
                            @click="loadUsers"
                            :loading="loading"
                            severity="secondary"
                            outlined
                            size="small"
                        />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Filters -->
        <Card class="mb-6">
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <InputText
                        v-model="filters.search"
                        placeholder="Search users..."
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
                        v-model="filters.is_active"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Filter by status"
                        show-clear
                        class="w-full"
                        @change="applyFilters"
                    />
                </div>
                
                <div class="flex gap-2 mt-4">
                    <Button
                        @click="applyFilters"
                        label="Apply Filters"
                        icon="pi pi-filter"
                        size="small"
                    />
                    <Button
                        @click="clearFilters"
                        label="Clear"
                        severity="secondary"
                        outlined
                        size="small"
                    />
                </div>
            </template>
        </Card>

        <!-- Users Table -->
        <Card>
            <template #content>
                <DataTable
                    :value="filteredUsers"
                    :loading="loading"
                    :paginator="true"
                    :rows="pagination.per_page"
                    :first="(pagination.page - 1) * pagination.per_page"
                    lazy
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    current-page-report-template="Showing {first} to {last} of {totalRecords} users"
                    @page="onPage"
                    scrollable
                    scroll-height="400px"
                >
                    <Column field="name" header="User" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <Avatar
                                    :label="data.name.charAt(0)"
                                    class="bg-primary text-primary-contrast"
                                />
                                <div>
                                    <div class="font-semibold">{{ data.name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ data.email }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="pivot.role" header="Role" sortable>
                        <template #body="{ data }">
                            <Badge
                                :value="data.pivot?.role"
                                :severity="getRoleSeverity(data.pivot?.role)"
                            />
                        </template>
                    </Column>
                    
                    <Column field="pivot.is_active" header="Status" sortable>
                        <template #body="{ data }">
                            <Badge
                                :value="data.pivot?.is_active ? 'Active' : 'Inactive'"
                                :severity="data.pivot?.is_active ? 'success' : 'danger'"
                            />
                        </template>
                    </Column>
                    
                    <Column field="pivot.created_at" header="Joined" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.pivot?.created_at) }}
                        </template>
                    </Column>
                    
                    <Column v-if="showActions && !readonly" header="Actions">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    v-if="canManageUsers"
                                    @click="openRoleDialog(data)"
                                    icon="pi pi-pencil"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    v-tooltip="'Change role'"
                                />
                                
                                <Button
                                    v-if="canManageUsers"
                                    @click="toggleUserStatus(data)"
                                    :icon="data.pivot?.is_active ? 'pi pi-ban' : 'pi pi-check'"
                                    size="small"
                                    :severity="data.pivot?.is_active ? 'warning' : 'success'"
                                    outlined
                                    v-tooltip="data.pivot?.is_active ? 'Deactivate' : 'Activate'"
                                />
                                
                                <Button
                                    v-if="canManageUsers && data.pivot?.role !== 'owner'"
                                    @click="confirmRemoveUser(data)"
                                    icon="pi pi-times"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    v-tooltip="'Remove from company'"
                                />
                            </div>
                        </template>
                    </Column>
                    
                    <template #empty>
                        <div class="text-center py-8">
                            <i class="pi pi-users text-4xl text-gray-400"></i>
                            <p class="text-gray-500 dark:text-gray-400 mt-4">
                                No users found
                            </p>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Role Change Dialog -->
        <Dialog
            v-model:visible="showRoleDialog"
            :header="`Change Role for ${selectedUser?.name}`"
            :modal="true"
            :style="{ width: '450px' }"
        >
            <div class="space-y-4">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        New Role
                    </label>
                    <Dropdown
                        id="role"
                        v-model="selectedRole"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select new role"
                        class="w-full"
                    />
                </div>
                
                <Message severity="warn" :closable="false">
                    Changing a user's role will affect their permissions within this company.
                </Message>
            </div>
            
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    outlined
                    @click="showRoleDialog = false"
                />
                <Button
                    label="Update Role"
                    :loading="loadingAction"
                    @click="confirmRoleChange"
                />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.company-member-list {
    @apply space-y-4;
}
</style>