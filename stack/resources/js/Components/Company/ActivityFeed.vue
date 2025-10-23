<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Activity</h2>
            <Button
                v-if="entries.length > 0"
                variant="ghost"
                size="small"
                @click="$emit('refresh')"
                :disabled="loading"
            >
                <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'" class="mr-2"></i>
                Refresh
            </Button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="text-center space-y-4">
                <div class="relative">
                    <i class="pi pi-spin pi-spinner text-blue-500 dark:text-blue-400 text-3xl"></i>
                    <div class="absolute inset-0 animate-ping">
                        <div class="w-12 h-12 bg-blue-500/20 dark:bg-blue-400/20 rounded-full mx-auto"></div>
                    </div>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 animate-pulse">Loading activity...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-12">
            <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-exclamation-triangle text-red-500 dark:text-red-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                Unable to load activity
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">{{ error }}</p>
            <Button @click="$emit('refresh')">
                Try Again
            </Button>
        </div>

        <!-- Empty State -->
        <div v-else-if="entries.length === 0" class="text-center py-12">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-history text-gray-400 dark:text-gray-500 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                No activity yet
            </h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                Actions performed in ledger, payments, and configuration will appear here
            </p>
        </div>

        <!-- Activity Timeline -->
        <div v-else class="relative">
            <!-- Timeline Line -->
            <div class="absolute left-6 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></div>

            <div class="space-y-6">
                <div
                    v-for="(entry, index) in groupedEntries"
                    :key="entry.date"
                    class="relative"
                >
                    <!-- Date Separator -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="relative z-10 flex-shrink-0">
                            <div class="w-12 h-12 bg-white dark:bg-gray-900 border-2 border-gray-200 dark:border-gray-700 rounded-full flex items-center justify-center">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ entry.day }}
                                </span>
                            </div>
                        </div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ entry.date }}
                        </div>
                    </div>

                    <!-- Activities for this date -->
                    <div class="ml-20 space-y-3">
                        <div
                            v-for="(activity, index) in entry.activities"
                            :key="activity.id"
                            :style="{ animationDelay: `${index * 50}ms` }"
                            class="group relative bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-all duration-300 hover:border-gray-300 dark:hover:border-gray-600 animate-fade-in"
                        >
                            <!-- Activity Header -->
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center">
                                        <i :class="getActivityIcon(activity.action)" class="text-gray-600 dark:text-gray-400 text-sm"></i>
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <!-- Action -->
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm mb-1">
                                        {{ activity.action }}
                                    </h4>

                                    <!-- Description -->
                                    <p v-if="activity.description" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        {{ activity.description }}
                                    </p>

                                    <!-- Metadata -->
                                    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                        <span v-if="activity.actor_name" class="flex items-center gap-1">
                                            <i class="pi pi-user"></i>
                                            {{ activity.actor_name }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <i class="pi pi-clock"></i>
                                            {{ getRelativeTime(activity.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div v-if="hasMore" class="text-center pt-6">
            <Button
                variant="ghost"
                @click="$emit('load-more')"
                :disabled="loading"
            >
                <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-arrow-down'" class="mr-2"></i>
                Load More Activity
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'

interface ActivityEntry {
    id: number
    action: string
    description?: string
    actor_name?: string
    created_at: string
}

interface GroupedEntry {
    date: string
    day: string
    activities: ActivityEntry[]
}

const props = defineProps<{
    entries: ActivityEntry[]
    loading?: boolean
    error?: string | null
    hasMore?: boolean
}>()

const emit = defineEmits<{
    refresh: []
    'load-more': []
}>()

const groupedEntries = computed((): GroupedEntry[] => {
    const groups: { [key: string]: ActivityEntry[] } = {}
    
    props.entries.forEach(entry => {
        const date = new Date(entry.created_at)
        const dateKey = date.toLocaleDateString('en-US', { 
            month: 'long', 
            day: 'numeric', 
            year: 'numeric' 
        })
        
        if (!groups[dateKey]) {
            groups[dateKey] = []
        }
        groups[dateKey].push(entry)
    })

    return Object.entries(groups).map(([date, activities]) => {
        const dateObj = new Date(activities[0].created_at)
        return {
            date,
            day: dateObj.toLocaleDateString('en-US', { day: 'numeric' }),
            activities: activities.sort((a, b) => 
                new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
            )
        }
    })
})

const getActivityIcon = (action: string): string => {
    const actionLower = action.toLowerCase()
    
    if (actionLower.includes('create') || actionLower.includes('add')) {
        return 'pi pi-plus'
    }
    if (actionLower.includes('delete') || actionLower.includes('remove')) {
        return 'pi pi-trash'
    }
    if (actionLower.includes('update') || actionLower.includes('edit')) {
        return 'pi pi-pencil'
    }
    if (actionLower.includes('login') || actionLower.includes('auth')) {
        return 'pi pi-key'
    }
    if (actionLower.includes('payment') || actionLower.includes('invoice')) {
        return 'pi pi-dollar'
    }
    if (actionLower.includes('user') || actionLower.includes('member')) {
        return 'pi pi-user'
    }
    if (actionLower.includes('company')) {
        return 'pi pi-building'
    }
    
    return 'pi pi-info-circle'
}

const getRelativeTime = (dateString: string) => {
    try {
        const date = new Date(dateString)
        const now = new Date()
        const diffMs = now.getTime() - date.getTime()
        const diffMins = Math.floor(diffMs / 60000)
        const diffHours = Math.floor(diffMs / 3600000)
        const diffDays = Math.floor(diffMs / 86400000)

        if (diffMins < 1) return 'Just now'
        if (diffMins < 60) return `${diffMins}m ago`
        if (diffHours < 24) return `${diffHours}h ago`
        if (diffDays < 7) return `${diffDays}d ago`
        
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        })
    } catch {
        return dateString
    }
}
</script>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.5s ease-out forwards;
}
</style>