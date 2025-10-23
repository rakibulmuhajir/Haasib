<template>
    <div class="space-y-4">
        <!-- Header with Stats -->
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Company Invitations
                <Badge 
                    v-if="pendingCount > 0" 
                    :value="pendingCount" 
                    severity="warning"
                    class="ml-2"
                />
            </h3>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ pendingCount }} pending</span>
                <span>•</span>
                <span>{{ totalCount }} total</span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl text-gray-400"></i>
            <p class="text-gray-500 mt-2">Loading invitations...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="invitations.length === 0" class="text-center py-12">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-envelope text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                No invitations
            </h3>
            <p class="text-gray-500 dark:text-gray-400">
                You don't have any company invitations.
            </p>
        </div>

        <!-- Invitations List -->
        <div v-else class="space-y-3">
            <div 
                v-for="invitation in invitations" 
                :key="invitation.id"
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-sm transition-shadow"
                :class="{
                    'opacity-75': invitation.status === 'rejected',
                    'border-yellow-300 dark:border-yellow-600': invitation.status === 'pending' && !isExpired(invitation.expires_at),
                    'border-red-300 dark:border-red-600': invitation.status === 'pending' && isExpired(invitation.expires_at)
                }"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h4 class="font-medium text-gray-900 dark:text-white">
                                {{ invitation.company_name }}
                            </h4>
                            <Badge 
                                :value="invitation.role" 
                                :severity="getRoleSeverity(invitation.role)"
                                size="small"
                            />
                            <Badge 
                                :value="getStatusLabel(invitation.status)" 
                                :severity="getStatusSeverity(invitation.status)"
                                size="small"
                            />
                            <Badge 
                                v-if="invitation.status === 'pending' && isExpired(invitation.expires_at)"
                                value="Expired" 
                                severity="danger"
                                size="small"
                            />
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            Invited by {{ invitation.invited_by }} • {{ formatDate(invitation.created_at) }}
                        </p>
                        
                        <p v-if="invitation.message" class="text-sm text-gray-500 dark:text-gray-500 mb-3 italic">
                            "{{ invitation.message }}"
                        </p>
                        
                        <p v-if="invitation.expires_at" class="text-xs text-gray-500 dark:text-gray-500">
                            Expires: {{ formatDate(invitation.expires_at) }}
                        </p>
                    </div>
                    
                    <div class="flex gap-2 ml-4">
                        <Button
                            v-if="invitation.status === 'pending' && !isExpired(invitation.expires_at)"
                            size="small"
                            @click="$emit('accept', invitation)"
                        >
                            <i class="pi pi-check mr-1"></i>
                            Accept
                        </Button>
                        <Button
                            v-if="invitation.status === 'pending'"
                            variant="danger"
                            size="small"
                            @click="$emit('reject', invitation)"
                        >
                            <i class="pi pi-times mr-1"></i>
                            Reject
                        </Button>
                        <Button
                            v-if="invitation.status === 'accepted'"
                            variant="outline"
                            size="small"
                            @click="$emit('view-company', invitation)"
                        >
                            <i class="pi pi-external-link mr-1"></i>
                            View
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Badge from 'primevue/badge'
import Button from 'primevue/button'

interface CompanyInvitation {
    id: number
    company_id: number
    company_name: string
    role: string
    email: string
    status: 'pending' | 'accepted' | 'rejected'
    invited_by: string
    message?: string | null
    created_at: string
    expires_at?: string | null
}

const props = defineProps<{
    invitations: CompanyInvitation[]
    loading?: boolean
}>()

const emit = defineEmits<{
    accept: [invitation: CompanyInvitation]
    reject: [invitation: CompanyInvitation]
    'view-company': [invitation: CompanyInvitation]
}>()

const pendingCount = computed(() => 
    props.invitations.filter(inv => inv.status === 'pending' && !isExpired(inv.expires_at)).length
)

const totalCount = computed(() => props.invitations.length)

const getRoleSeverity = (role: string): 'success' | 'info' | 'secondary' => {
    switch (role.toLowerCase()) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        default: return 'secondary'
    }
}

const getStatusSeverity = (status: string): 'success' | 'warning' | 'danger' | 'secondary' => {
    switch (status.toLowerCase()) {
        case 'accepted': return 'success'
        case 'pending': return 'warning'
        case 'rejected': return 'danger'
        default: return 'secondary'
    }
}

const getStatusLabel = (status: string): string => {
    switch (status.toLowerCase()) {
        case 'accepted': return 'Accepted'
        case 'pending': return 'Pending'
        case 'rejected': return 'Rejected'
        default: return status
    }
}

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString)
        return new Intl.DateTimeFormat('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date)
    } catch {
        return dateString
    }
}

const isExpired = (expiresAt?: string | null) => {
    if (!expiresAt) return false
    return new Date(expiresAt) < new Date()
}
</script>