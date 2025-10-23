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
            'group relative bg-white dark:bg-gray-800 rounded-xl border transition-all',
            isCurrent
                ? 'border-gray-900 dark:border-white shadow-sm'
                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600',
            isSelected ? 'ring-2 ring-gray-900 dark:ring-white ring-offset-2 dark:ring-offset-gray-950' : ''
        ]"
    >
        <!-- Selection Checkbox -->
        <button
            @click="$emit('toggleSelection')"
            class="absolute top-3 left-3 w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10 focus:opacity-100"
            :aria-label="`Select ${company.name}`"
        >
            <i v-if="isSelected" class="fas fa-check text-gray-900 dark:text-white text-sm" />
        </button>

        <!-- Direct Action Buttons -->
        <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
            <Link :href="`/companies/${company.id}`">
                <button 
                    class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                    :title="`View ${company.name} details`"
                >
                    <i class="fas fa-external-link-alt text-sm" />
                </button>
            </Link>
            
            <button 
                v-if="!isCurrent && company.is_active"
                @click="$emit('switchCompany')"
                class="p-1.5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors"
                :title="`Switch to ${company.name}`"
            >
                <i class="fas fa-sign-in-alt text-sm" />
            </button>
            
            <button 
                v-if="company.is_active && company.user_role === 'owner'"
                @click="$emit('deactivateCompany')"
                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                :title="`Deactivate ${company.name}`"
            >
                <i class="fas fa-ban text-sm" />
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
