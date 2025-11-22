<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { useToast } from '@/components/ui/toast/use-toast'

const props = defineProps<{
    companyId: string
}>()

interface CompanyUser {
    id: string
    user_id: string
    company_id: string
    role: string
    is_active: boolean
    joined_at: string
    user: {
        id: string
        name: string
        email: string
    }
}

const users = ref<CompanyUser[]>([])
const loading = ref(false)
const error = ref('')
const { toast } = useToast()

// Assignment form
const assignForm = ref({
    email: '',
    role: 'accounting_viewer'
})

const assignLoading = ref(false)
const assignError = ref('')

const roles = [
    { value: 'company_owner', label: 'Company Owner', description: 'Full tenant control: invites, billing, integrations, accounting locking' },
    { value: 'company_admin', label: 'Company Admin', description: 'Operates tenant settings, users, approvals. No destructive backups' },
    { value: 'accounting_admin', label: 'Accounting Admin', description: 'Period close, posting, approvals, audits' },
    { value: 'accounting_operator', label: 'Accounting Operator', description: 'Create/edit invoices/bills/payments, cannot post/void' },
    { value: 'accounting_viewer', label: 'Accounting Viewer', description: 'Read-only access to accounting + reporting' },
    { value: 'portal_customer', label: 'Portal Customer', description: 'External customer portal access with limited permissions' },
    { value: 'portal_vendor', label: 'Portal Vendor', description: 'External vendor portal access with limited permissions' }
]

const loadUsers = async () => {
    loading.value = true
    error.value = ''
    
    try {
        const response = await fetch(`/api/companies/${props.companyId}/users`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        if (!response.ok) {
            throw new Error('Failed to load users')
        }
        const data = await response.json()
        users.value = data.data || []
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to load users'
    } finally {
        loading.value = false
    }
}

const assignUser = async () => {
    if (!assignForm.value.email.trim()) {
        assignError.value = 'Email is required'
        return
    }
    
    assignLoading.value = true
    assignError.value = ''
    
    try {
        const response = await fetch(`/api/companies/${props.companyId}/users`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                email: assignForm.value.email.trim(),
                role: assignForm.value.role
            })
        })
        
        if (!response.ok) {
            const data = await response.json()
            throw new Error(data.message || 'Failed to assign user')
        }
        
        // Reset form and reload users
        assignForm.value.email = ''
        assignForm.value.role = 'accounting_viewer'
        await loadUsers()
        toast({
            title: 'User assigned',
            description: 'User was added to the company.',
        })
    } catch (err) {
        assignError.value = err instanceof Error ? err.message : 'Failed to assign user'
    } finally {
        assignLoading.value = false
    }
}

const unassignUser = async (userId: string) => {
    if (!confirm('Are you sure you want to remove this user from the company?')) {
        return
    }
    
    try {
        const response = await fetch(`/api/companies/${props.companyId}/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        
        if (!response.ok) {
            throw new Error('Failed to remove user')
        }
        
        await loadUsers()
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to remove user'
    }
}

const getRoleBadgeVariant = (role: string) => {
    switch (role) {
        case 'company_owner': return 'default'
        case 'company_admin': return 'secondary'
        case 'accounting_operator': return 'outline'
        case 'accounting_viewer': return 'outline'
        case 'portal_customer': return 'destructive'
        case 'portal_vendor': return 'destructive'
        default: return 'outline'
    }
}

const updateRole = async (userId: string, role: string) => {
    try {
        const response = await fetch(`/api/companies/${props.companyId}/users/${userId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ role })
        })

        if (!response.ok) {
            const data = await response.json()
            throw new Error(data.message || 'Failed to update role')
        }

        await loadUsers()
        toast({
            title: 'Role updated',
            description: 'User role has been changed.',
        })
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to update role'
    }
}

onMounted(() => {
    loadUsers()
})
</script>

<template>
    <div class="space-y-6">
        <!-- Assign User Form -->
        <Card>
            <CardHeader>
                <CardTitle>Assign User to Company</CardTitle>
                <CardDescription>Add an existing user to this company by their email</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <Label for="email">User Email</Label>
                        <Input
                            id="email"
                            v-model="assignForm.email"
                            type="email"
                            placeholder="user@example.com"
                            :disabled="assignLoading"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="role">Role</Label>
                        <Select v-model="assignForm.role" :disabled="assignLoading">
                            <SelectTrigger>
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="role in roles" :key="role.value" :value="role.value">
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end">
                        <Button @click="assignUser" :disabled="assignLoading || !assignForm.email.trim()">
                            {{ assignLoading ? 'Assigning...' : 'Assign User' }}
                        </Button>
                    </div>
                </div>
                <div v-if="assignError" class="mt-3 text-sm text-red-600">
                    {{ assignError }}
                </div>
            </CardContent>
        </Card>

        <!-- Users List -->
        <Card>
            <CardHeader>
                <CardTitle>Company Users</CardTitle>
                <CardDescription>Manage users assigned to this company</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="text-center py-8 text-muted-foreground">
                    Loading users...
                </div>
                <div v-else-if="error" class="text-center py-8 text-red-600">
                    {{ error }}
                </div>
                <div v-else-if="users.length === 0" class="text-center py-8 text-muted-foreground">
                    No users assigned to this company yet.
                </div>
                <div v-else class="space-y-4">
                    <div v-for="companyUser in users" :key="companyUser.id" class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="space-y-1">
                            <div class="font-medium">{{ companyUser.user.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ companyUser.user.email }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <Badge :variant="getRoleBadgeVariant(companyUser.role)">
                                    {{ companyUser.role }}
                                </Badge>
                                <Badge v-if="!companyUser.is_active" variant="destructive">
                                    Inactive
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    Joined {{ new Date(companyUser.joined_at).toLocaleDateString() }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-48">
                                <Select
                                    :model-value="companyUser.role"
                                    :disabled="companyUser.role === 'company_owner'"
                                    @update:model-value="(val) => updateRole(companyUser.user_id, val)"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="role in roles" :key="role.value" :value="role.value">
                                            {{ role.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="companyUser.role === 'company_owner'" class="text-xs text-muted-foreground mt-1">
                                    Owner role is fixed. Contact system admin to transfer.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="companyUser.role === 'company_owner'"
                                @click="unassignUser(companyUser.user_id)"
                            >
                                {{ companyUser.role === 'company_owner' ? 'Owner' : 'Remove' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
