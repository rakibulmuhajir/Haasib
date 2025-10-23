<template>
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-6">Overview</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div 
                v-for="metric in metrics" 
                :key="metric.key"
                class="group bg-gray-50 dark:bg-gray-800 rounded-lg p-4 hover:shadow-md transition-all duration-300 hover:scale-[1.02] cursor-pointer relative overflow-hidden"
            >
                <!-- Subtle animated background gradient -->
                <div class="absolute inset-0 bg-gradient-to-br from-transparent via-white/5 to-transparent dark:from-transparent dark:via-white/2 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <i :class="metric.icon" class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200 group-hover:scale-110"></i>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            {{ metric.label }}
                        </span>
                    </div>
                    
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-gray-900 dark:text-white transition-all duration-300 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                            {{ metric.value }}
                        </span>
                        
                        <div 
                            v-if="metric.trend"
                            :class="[
                                'flex items-center gap-1 text-xs font-medium',
                                metric.trend.type === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'
                            ]"
                        >
                            <i :class="metric.trend.icon" class="w-3 h-3"></i>
                            <span>{{ metric.trend.value }}</span>
                        </div>
                    </div>
                    
                    <p v-if="metric.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ metric.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Trend {
    type: 'up' | 'neutral' | 'down'
    value: string
    icon: string
}

interface Metric {
    key: string
    label: string
    value: string | number
    description?: string
    icon: string
    trend?: Trend
}

interface Company {
    is_active: boolean
    updated_at?: string
}

interface Props {
    company?: Company | null
    users?: any[]
    invitations?: any[]
    fiscalYear?: any
}

const props = defineProps<Props>()

const metrics = computed((): Metric[] => {
    const userCount = props.users?.length || 0
    const activeUsers = props.users?.filter(u => u.is_active).length || 0
    const invitationCount = props.invitations?.length || 0
    
    return [
        {
            key: 'users',
            label: 'Team Members',
            value: activeUsers,
            description: `${userCount} total`,
            icon: 'pi pi-users',
            trend: userCount > 0 ? {
                type: 'up',
                value: 'active',
                icon: 'pi pi-arrow-up'
            } : undefined
        },
        {
            key: 'invitations',
            label: 'Invitations',
            value: invitationCount,
            description: invitationCount === 0 ? 'None pending' : 'Awaiting response',
            icon: 'pi pi-envelope',
            trend: invitationCount > 0 ? {
                type: 'neutral',
                value: 'pending',
                icon: 'pi pi-clock'
            } : undefined
        },
        {
            key: 'status',
            label: 'Status',
            value: props.company?.is_active ? 'Active' : 'Inactive',
            description: props.company?.is_active ? 'Fully operational' : 'Paused',
            icon: props.company?.is_active ? 'pi pi-check-circle' : 'pi pi-pause-circle',
            trend: props.company?.is_active ? {
                type: 'up',
                value: 'running',
                icon: 'pi pi-arrow-up'
            } : undefined
        },
        {
            key: 'updated',
            label: 'Last Updated',
            value: props.company?.updated_at ? getRelativeTime(props.company.updated_at) : 'Never',
            description: props.company?.updated_at ? formatDate(props.company.updated_at) : 'No changes',
            icon: 'pi pi-clock'
        }
    ]
})

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
        
        return formatDate(dateString)
    } catch {
        return dateString
    }
}

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString)
        return new Intl.DateTimeFormat('en-US', { 
            month: 'short', 
            day: 'numeric' 
        }).format(date)
    } catch {
        return dateString
    }
}
</script>