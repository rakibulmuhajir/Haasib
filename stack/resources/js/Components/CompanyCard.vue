<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    company: {
        type: Object,
        required: true
    },
    isSelected: {
        type: Boolean,
        default: false
    },
    isCurrent: {
        type: Boolean,
        default: false
    },
    userRole: {
        type: String,
        default: 'member'
    }
})

const emit = defineEmits(['toggleSelection', 'switchCompany', 'deactivateCompany'])

// Get the user's role for this company from userCompanies data
const userRole = computed(() => {
    // This would need to be passed as a prop from the parent component
    // For now, assume the user has owner role if they can deactivate
    return company.user_role || 'member'
})
</script>

<template>
    <div
        :class="[
            'group relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all duration-200 p-6',
            isCurrent ? 'border-blue-500 dark:border-blue-400' : '',
            isSelected ? 'ring-2 ring-blue-500 dark:ring-blue-400 ring-offset-2 dark:ring-offset-gray-950' : ''
        ]"
    >
        <!-- Selection Checkbox -->
        <button
            @click="$emit('toggleSelection')"
            class="absolute top-4 left-4 w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10 focus:opacity-100"
            :aria-label="`Select ${company.name}`"
        >
            <i v-if="isSelected" class="fas fa-check text-blue-600 dark:text-blue-400 text-sm" />
        </button>

        <!-- Action Menu (replaces direct action buttons) -->
        <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            <button 
                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200"
                :title="`Actions for ${company.name}`"
            >
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <div class="p-6">
            <!-- Company Avatar -->
            <div class="w-12 h-12 mb-4 rounded-xl bg-gradient-to-br from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 flex items-center justify-center text-white dark:text-gray-900 text-lg font-bold">
                {{ company.name.charAt(0) }}
            </div>

            <!-- Company Info -->
            <div class="mb-4">
                <Link :href="`/companies/${company.id}`" class="text-base font-semibold text-gray-900 dark:text-white mb-1 block hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                    {{ company.name }}
                </Link>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ company.industry || 'N/A' }}</p>
            </div>

            <!-- Metadata -->
            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-4">
                <span>{{ company.country || 'N/A' }}</span>
                <span>•</span>
                <span>{{ company.currency || 'N/A' }}</span>
                <span>•</span>
                <span class="capitalize">{{ company.user_role || 'member' }}</span>
            </div>

            <!-- Status Indicator -->
            <div v-if="!company.is_active" class="mb-4">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded">
                    Inactive
                </span>
            </div>

            <!-- Action -->
            <button
                v-if="!isCurrent"
                @click="$emit('switchCompany')"
                class="w-full py-2 text-sm font-medium text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
            >
                Switch to this company
            </button>
            <button
                v-else
                disabled
                class="w-full py-2 text-sm font-medium text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg cursor-not-allowed"
            >
                Active Company
            </button>
        </div>
    </div>
</template>
