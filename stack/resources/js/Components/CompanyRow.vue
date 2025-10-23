<script setup>
import { Link } from '@inertiajs/vue3'

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
</script>

<template>
    <tr :class="[
        isCurrent ? 'bw-table-row-current' : ''
    ]">
        <td class="px-4 py-4">
            <input
                type="checkbox"
                :checked="isSelected"
                @change="$emit('toggleSelection')"
                class="w-4 h-4 rounded border border-gray-300 dark:border-gray-600"
                :aria-label="`Select ${company.name}`"
            />
        </td>
        <td class="px-4 py-4 bw-interactive-cell">
            <div class="flex items-center gap-3 bw-interactive-cell__content">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 flex items-center justify-center text-white dark:text-gray-900 font-semibold text-sm flex-shrink-0">
                    {{ company.name.charAt(0) }}
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Link :href="`/companies/${company.id}`" class="font-medium text-gray-900 dark:text-white truncate hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            {{ company.name }}
                        </Link>
                        <div v-if="isCurrent" class="flex-shrink-0 w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    </div>
                    <span class="text-sm text-gray-500 dark:text-gray-400 truncate block">
                        {{ company.slug }}
                    </span>
                </div>
            </div>
        </td>
        <td class="px-4 py-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ company.industry || 'N/A' }}</span>
        </td>
        <td class="px-4 py-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">
                {{ (company.country || 'N/A') }} • {{ (company.currency || 'N/A') }}
            </span>
        </td>
        <td class="px-4 py-4">
            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded capitalize">
                {{ userRole }}
            </span>
        </td>
        <td class="px-4 py-4 text-right">
            <div class="flex items-center justify-end gap-1">
                <Link :href="`/companies/${company.id}`">
                    <button 
                        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
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
                    v-if="company.is_active && userRole === 'owner'"
                    @click="$emit('deactivateCompany')"
                    class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                    :title="`Deactivate ${company.name}`"
                >
                    <i class="fas fa-ban text-sm" />
                </button>
            </div>
        </td>
    </tr>
</template>
