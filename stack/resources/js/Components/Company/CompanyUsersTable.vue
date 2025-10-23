<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Team Members</h3>
            <Button
                v-if="canInvite"
                size="small"
                icon="pi pi-user-plus"
                label="Invite User"
                severity="secondary"
                outlined
                @click="$emit('invite')"
            />
        </div>

        <DataTable 
            :value="users" 
            :paginator="users.length > 8" 
            :rows="8" 
            size="small"
            responsive-layout="scroll"
            :loading="loading"
        >
            <Column header="User">
                <template #body="{ data }">
                    <div class="flex items-center gap-3">
                        <Avatar :label="data.name?.charAt(0)" class="bg-primary text-primary-contrast" />
                        <div>
                            <div class="font-medium">{{ data.name }}</div>
                            <div class="text-sm text-gray-500">{{ data.email }}</div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column header="Role">
                <template #body="{ data }">
                    <Badge 
                        :value="data.role" 
                        :severity="data.role === 'owner' ? 'success' : data.role === 'admin' ? 'info' : 'secondary'" 
                    />
                </template>
            </Column>
            <Column header="Joined">
                <template #body="{ data }">
                    {{ formatDate(data.joined_at) }}
                </template>
            </Column>
            <template #empty>
                <div class="py-10 text-center">
                    <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="pi pi-users text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        No team members yet
                    </p>
                    <Button
                        v-if="canInvite"
                        size="small"
                        icon="pi pi-user-plus"
                        label="Invite First Team Member"
                        severity="secondary"
                        outlined
                        @click="$emit('invite')"
                    />
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

interface CompanyUser {
    id: number
    name: string
    email: string
    role: string
    joined_at: string
}

const props = defineProps<{
    users?: CompanyUser[]
    loading?: boolean
    canInvite?: boolean
}>()

const emit = defineEmits<{
    invite: []
}>()

const formatDate = (value?: string | null) => {
    if (!value) return '—'
    try {
        return new Intl.DateTimeFormat('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        }).format(new Date(value))
    } catch {
        return value
    }
}
</script>