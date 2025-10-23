<template>
    <div class="space-y-6">
        <!-- Header with Invite Action -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                Team Members
                <span v-if="members.length > 0" class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">
                    ({{ activeMembers.length }} active)
                </span>
            </h2>
            
            <div class="flex gap-2">
                <Button
                    v-if="canManage"
                    variant="outline"
                    size="small"
                    @click="showRoleManagement = true"
                >
                    <i class="pi pi-user-edit mr-2"></i>
                    Manage Roles
                </Button>
                <Button
                    v-if="canInvite"
                    size="small"
                    @click="showInviteModal = true"
                >
                    <i class="pi pi-user-plus mr-2"></i>
                    Invite Member
                </Button>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="members.length === 0" class="text-center py-12">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-users text-gray-400 dark:text-gray-500 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                No team members yet
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
                Invite team members to collaborate on this company
            </p>
            <Button
                v-if="canInvite"
                @click="$emit('invite')"
            >
                <i class="pi pi-user-plus mr-2"></i>
                Invite First Member
            </Button>
        </div>

        <!-- Team Members Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="member in members"
                :key="member.id"
                class="group relative bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm transition-shadow"
            >
                <!-- Member Info -->
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                {{ member.name?.charAt(0) || '?' }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-medium text-gray-900 dark:text-white truncate">
                                {{ member.name }}
                            </h3>
                            <div v-if="!member.is_active" class="w-2 h-2 bg-gray-400 rounded-full"></div>
                            <div v-else class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                        </div>
                        
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate mb-2">
                            {{ member.email }}
                        </p>
                        
                        <div class="flex items-center gap-2">
                            <Badge
                                :value="member.role"
                                :severity="getRoleSeverity(member.role)"
                                size="small"
                            />
                            <span v-if="!member.is_active" class="text-xs text-gray-500 dark:text-gray-400">
                                Inactive
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Join Date -->
                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Joined {{ formatDate(member.joined_at) }}</span>
                        <div v-if="canManage" class="opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button
                                variant="ghost"
                                size="small"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                @click="$emit('manage-member', member)"
                            >
                                <i class="pi pi-ellipsis-h"></i>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div v-if="members.length > 0" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ members.length }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Members</div>
                </div>
                <div>
                    <div class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ activeMembers.length }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Active</div>
                </div>
                <div>
                    <div class="text-2xl font-semibold text-gray-600 dark:text-gray-400">
                        {{ inactiveMembers.length }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Inactive</div>
                </div>
                <div>
                    <div class="text-2xl font-semibold text-blue-600 dark:text-blue-400">
                        {{ ownersCount }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Owners</div>
                </div>
            </div>
        </div>

        <!-- Invite Modal -->
        <Dialog 
            v-model:visible="showInviteModal" 
            modal 
            header="Invite Team Member" 
            :style="{ width: '450px' }"
        >
            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email Address
                    </label>
                    <InputText 
                        id="email"
                        v-model="inviteForm.email" 
                        type="email" 
                        class="w-full" 
                        placeholder="colleague@example.com"
                        :class="{ 'p-invalid': inviteErrors.email }"
                    />
                    <small v-if="inviteErrors.email" class="text-red-500">{{ inviteErrors.email }}</small>
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Role
                    </label>
                    <Dropdown 
                        id="role"
                        v-model="inviteForm.role" 
                        :options="roleOptions" 
                        optionLabel="label" 
                        optionValue="value"
                        class="w-full"
                        placeholder="Select a role"
                        :class="{ 'p-invalid': inviteErrors.role }"
                    />
                    <small v-if="inviteErrors.role" class="text-red-500">{{ inviteErrors.role }}</small>
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Personal Message (optional)
                    </label>
                    <Textarea 
                        id="message"
                        v-model="inviteForm.message" 
                        rows="3" 
                        class="w-full"
                        placeholder="Join our team to help us build amazing things..."
                    />
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Cancel" 
                    variant="secondary" 
                    @click="closeInviteModal"
                    :disabled="inviteLoading"
                />
                <Button 
                    :label="inviteLoading ? 'Sending...' : 'Send Invitation'" 
                    @click="sendInvitation"
                    :loading="inviteLoading"
                    :disabled="!inviteForm.email || !inviteForm.role"
                />
            </template>
        </Dialog>

        <!-- Role Management Modal -->
        <Dialog 
            v-model:visible="showRoleManagement" 
            modal 
            header="Manage Team Roles" 
            :style="{ width: '600px' }"
            maximizable
        >
            <div class="space-y-4">
                <div v-if="members.length === 0" class="text-center py-8">
                    <i class="pi pi-users text-gray-400 text-4xl mb-3"></i>
                    <p class="text-gray-500 dark:text-gray-400">No team members to manage</p>
                </div>
                
                <div v-else class="space-y-3">
                    <div 
                        v-for="member in members" 
                        :key="member.id"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {{ member.name?.charAt(0) || '?' }}
                                </span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ member.name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ member.email }}</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <Dropdown 
                                v-model="member.role" 
                                :options="roleOptions" 
                                optionLabel="label" 
                                optionValue="value"
                                class="w-32"
                                @change="updateMemberRole(member)"
                                :disabled="member.role.toLowerCase() === 'owner' && members.filter(m => m.role.toLowerCase() === 'owner').length === 1"
                            />
                            <div class="flex items-center gap-2">
                                <div :class="[
                                    'w-2 h-2 rounded-full',
                                    member.is_active ? 'bg-emerald-500' : 'bg-gray-400'
                                ]"></div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ member.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Done" 
                    @click="showRoleManagement = false"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'

interface TeamMember {
    id: number
    name: string
    email: string
    role: string
    is_active: boolean
    joined_at: string
}

interface InviteForm {
    email: string
    role: string
    message: string
}

const props = defineProps<{
    members: TeamMember[]
    canInvite?: boolean
    canManage?: boolean
}>()

const emit = defineEmits<{
    invite: []
    'manage-member': [member: TeamMember]
    'invite-member': [data: InviteForm]
    'update-role': [member: TeamMember, newRole: string]
}>()

const activeMembers = computed(() => props.members.filter(m => m.is_active))
const inactiveMembers = computed(() => props.members.filter(m => !m.is_active))
const ownersCount = computed(() => props.members.filter(m => m.role.toLowerCase() === 'owner').length)

const getRoleSeverity = (role: string): 'success' | 'info' | 'secondary' | 'danger' => {
    switch (role.toLowerCase()) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        case 'member': return 'secondary'
        default: return 'secondary'
    }
}

// Invite Modal State
const showInviteModal = ref(false)
const showRoleManagement = ref(false)
const inviteLoading = ref(false)
const inviteErrors = ref<Record<string, string>>({})

const inviteForm = ref<InviteForm>({
    email: '',
    role: '',
    message: ''
})

const roleOptions = [
    { label: 'Member', value: 'member' },
    { label: 'Admin', value: 'admin' },
    { label: 'Owner', value: 'owner' },
    { label: 'Accountant', value: 'accountant' }
]

const resetInviteForm = () => {
    inviteForm.value = {
        email: '',
        role: '',
        message: ''
    }
    inviteErrors.value = {}
}

const closeInviteModal = () => {
    showInviteModal.value = false
    resetInviteForm()
}

const validateInviteForm = (): boolean => {
    const errors: Record<string, string> = {}
    
    if (!inviteForm.value.email) {
        errors.email = 'Email is required'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inviteForm.value.email)) {
        errors.email = 'Please enter a valid email address'
    }
    
    if (!inviteForm.value.role) {
        errors.role = 'Role is required'
    }
    
    inviteErrors.value = errors
    return Object.keys(errors).length === 0
}

const sendInvitation = async () => {
    if (!validateInviteForm()) return
    
    inviteLoading.value = true
    try {
        const emitPromise = emit('invite-member', { ...inviteForm.value })
        await emitPromise
        showInviteModal.value = false
        resetInviteForm()
    } catch (error) {
        console.error('Failed to send invitation:', error)
    } finally {
        inviteLoading.value = false
    }
}

const updateMemberRole = async (member: TeamMember) => {
    try {
        const emitPromise = emit('update-role', member, member.role)
        await emitPromise
    } catch (error) {
        console.error('Failed to update member role:', error)
        // Revert the role change on error
        const originalMember = props.members.find(m => m.id === member.id)
        if (originalMember) {
            member.role = originalMember.role
        }
    }
}

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString)
        const now = new Date()
        const diffMs = now.getTime() - date.getTime()
        const diffDays = Math.floor(diffMs / 86400000)

        if (diffDays < 1) return 'Today'
        if (diffDays < 7) return `${diffDays}d ago`
        if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`
        if (diffDays < 365) return `${Math.floor(diffDays / 30)}mo ago`
        
        return new Intl.DateTimeFormat('en-US', { 
            month: 'short', 
            year: 'numeric' 
        }).format(date)
    } catch {
        return dateString
    }
}
</script>