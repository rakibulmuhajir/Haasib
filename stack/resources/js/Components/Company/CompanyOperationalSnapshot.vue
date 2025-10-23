<template>
    <Card>
        <template #title>
            <h2 id="operational-title">Operational Snapshot</h2>
        </template>
        <template #content>
            <!-- Loading skeleton for operational summary -->
            <div v-if="!company" class="space-y-4">
                <div v-for="i in 4" :key="i" class="animate-pulse">
                    <div class="flex items-start gap-3 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                        <div class="w-5 h-5 bg-gray-200 dark:bg-gray-700 rounded mt-1"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
                            <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded w-32"></div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-48"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div v-else class="space-y-4" role="list" aria-labelledby="operational-title">
                <div
                    v-for="item in operationsSummary"
                    :key="item.label"
                    class="flex items-start gap-3 border border-gray-200 dark:border-gray-700 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 rounded-lg"
                    role="listitem"
                    tabindex="0"
                    :aria-label="`${item.label}: ${item.value} - ${item.description}`"
                >
                    <span :class="['pi', item.icon, 'text-primary', 'text-lg', 'mt-1']"></span>
                    <div>
                        <div class="text-sm font-medium text-gray-500">
                            {{ item.label }}
                        </div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ item.value }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ item.description }}
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Card from 'primevue/card'

interface CompanyPayload {
    id?: number
    name?: string
    is_active?: boolean
    users?: any[]
    invitations?: any[]
}

interface OperationalItem {
    label: string
    value: string | number
    description: string
    icon: string
}

const props = defineProps<{
    company?: CompanyPayload | null
}>()

const operationsSummary = computed((): OperationalItem[] => {
    const userCount = props.company?.users?.length ?? 0
    const invitationCount = props.company?.invitations?.length ?? 0

    return [
        {
            label: 'Active Users',
            value: userCount,
            description: 'Team members with access',
            icon: 'pi-users'
        },
        {
            label: 'Pending Invitations',
            value: invitationCount,
            description: 'Invitations awaiting acceptance',
            icon: 'pi-envelope'
        },
        {
            label: 'Company Status',
            value: props.company?.is_active ? 'Active' : 'Inactive',
            description: props.company?.is_active ? 'Company is operational' : 'Company is paused',
            icon: props.company?.is_active ? 'pi-check-circle' : 'pi-times-circle'
        },
        {
            label: 'Company ID',
            value: props.company?.id ?? 'N/A',
            description: 'Unique system identifier',
            icon: 'pi-hashtag'
        }
    ]
})
</script>