<template>
    <div>
        <h3 class="text-lg font-semibold mb-3">Pending Invitations</h3>
        <DataTable 
            :value="invitations" 
            :rows="5" 
            size="small"
            responsive-layout="scroll"
            :loading="loading"
        >
            <Column field="email" header="Email" />
            <Column field="role" header="Role">
                <template #body="{ data }">
                    <Badge 
                        :value="data.role" 
                        :severity="data.role === 'owner' ? 'success' : data.role === 'admin' ? 'info' : 'secondary'" 
                    />
                </template>
            </Column>
            <Column field="created_at" header="Invited">
                <template #body="{ data }">
                    {{ formatDate(data.created_at) }}
                </template>
            </Column>
            <Column header="Actions">
                <template #body="{ data }">
                    <Button
                        size="small"
                        icon="pi pi-times"
                        severity="danger"
                        text
                        @click="$emit('cancel-invitation', data)"
                        aria-label="Cancel invitation"
                    />
                </template>
            </Column>
            <template #empty>
                <div class="py-6 text-center">
                    <div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="pi pi-envelope text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        No pending invitations
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

interface CompanyInvitation {
    id: number
    email: string
    role: string
    created_at: string
}

const props = defineProps<{
    invitations?: CompanyInvitation[]
    loading?: boolean
}>()

const emit = defineEmits<{
    'cancel-invitation': [invitation: CompanyInvitation]
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