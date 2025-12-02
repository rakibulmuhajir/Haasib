<script setup lang="ts">
import { computed } from 'vue'

interface Props {
    role: string
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md'
})

const roleConfig = computed(() => {
    const configs = {
        owner: {
            label: 'Owner',
            class: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            icon: 'fa-crown'
        },
        admin: {
            label: 'Admin',
            class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            icon: 'fa-shield-halved'
        },
        accountant: {
            label: 'Accountant',
            class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 border-green-200 dark:border-green-800',
            icon: 'fa-calculator'
        },
        member: {
            label: 'Member',
            class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
            icon: 'fa-user'
        },
        viewer: {
            label: 'Viewer',
            class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
            icon: 'fa-eye'
        },
    }
    return configs[props.role as keyof typeof configs] || configs.member
})

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'text-xs px-2 py-0.5',
        md: 'text-xs px-2.5 py-0.5',
        lg: 'text-sm px-3 py-1',
    }
    return sizes[props.size]
})
</script>

<template>
    <span :class="[
        'inline-flex items-center rounded-full font-medium border',
        roleConfig.class,
        sizeClasses
    ]">
        <i :class="['fas', roleConfig.icon, 'mr-1.5']"></i>
        {{ roleConfig.label }}
    </span>
</template>
