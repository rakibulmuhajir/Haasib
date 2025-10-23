<template>
    <div class="space-y-4">
        <div v-if="loading" class="flex justify-center py-10">
            <ProgressSpinner />
        </div>
        <div v-else>
            <Message v-if="error" severity="info" :closable="false">
                {{ error }}
            </Message>
            <div v-else-if="entries.length === 0" class="text-center py-10">
                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="pi pi-history text-gray-400 dark:text-gray-500"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    No activity recorded yet
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    Actions in ledger, payments, and configuration will appear here
                </p>
            </div>
            <div v-else class="relative">
                <!-- Timeline line -->
                <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                
                <div class="space-y-4">
                    <div
                        v-for="(entry, index) in entries"
                        :key="entry.id"
                        class="relative flex items-start gap-4"
                    >
                        <!-- Timeline dot -->
                        <div class="relative z-10 flex-shrink-0">
                            <div class="w-3 h-3 bg-white dark:bg-gray-900 border-2 border-blue-500 rounded-full"></div>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0 pb-4">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">
                                            {{ entry.action }}
                                        </h4>
                                        <p v-if="entry.description" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            {{ entry.description }}
                                        </p>
                                        <div class="flex items-center gap-4 mt-2">
                                            <span v-if="entry.actor_name" class="text-xs text-gray-500 dark:text-gray-400">
                                                <i class="pi pi-user mr-1"></i>
                                                {{ entry.actor_name }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" :title="formatDateTime(entry.created_at)">
                                                <i class="pi pi-clock mr-1"></i>
                                                {{ getRelativeTime(entry.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import ProgressSpinner from 'primevue/progressspinner'
import Message from 'primevue/message'

interface AuditEntry {
    id: number
    action: string
    description?: string
    actor_name?: string
    created_at: string
}

const props = defineProps<{
    entries?: AuditEntry[]
    loading?: boolean
    error?: string | null
}>()

const formatDateTime = (value?: string | null) => {
    if (!value) return '—'
    try {
        return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    } catch {
        return value
    }
}

const getRelativeTime = (value?: string | null) => {
    if (!value) return '—'
    try {
        const date = new Date(value)
        const now = new Date()
        const diffMs = now.getTime() - date.getTime()
        const diffMins = Math.floor(diffMs / 60000)
        const diffHours = Math.floor(diffMs / 3600000)
        const diffDays = Math.floor(diffMs / 86400000)

        if (diffMins < 1) return 'just now'
        if (diffMins < 60) return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`
        if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`
        if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
        
        return formatDateTime(value)
    } catch {
        return value
    }
}
</script>