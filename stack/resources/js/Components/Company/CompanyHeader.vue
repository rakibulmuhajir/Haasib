<template>
    <!-- Improved Company Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div class="px-6 py-8">
            <div class="flex items-start justify-between">
                <!-- Company Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-4 mb-4">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ company.name }}
                        </h1>
                        <span :class="[
                            'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium',
                            company.is_active 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
                        ]">
                            {{ company.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                        <span v-if="company.industry" class="flex items-center">
                            <i class="fas fa-industry mr-2"></i>
                            {{ getIndustryLabel(company.industry) }}
                        </span>
                        <span v-if="company.country" class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            {{ getCountryLabel(company.country) }}
                        </span>
                        <span v-if="company.base_currency" class="flex items-center">
                            <i class="fas fa-coins mr-2"></i>
                            {{ company.base_currency }}
                        </span>
                        <span v-if="company.user_role?.role" class="flex items-center">
                            <i :class="getRoleIcon(company.user_role.role) + ' mr-2'"></i>
                            <span class="capitalize">{{ company.user_role.role }}</span>
                        </span>
                    </div>
                </div>
                
                <!-- Primary Actions -->
                <div class="flex items-center gap-3 ml-8">
                    <button 
                        v-if="!isCurrentCompany && company.is_active"
                        @click="$emit('switch-context')"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Switch to Company
                    </button>
                    <button 
                        v-else-if="isCurrentCompany"
                        disabled
                        class="px-4 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed"
                    >
                        <i class="fas fa-check-circle mr-2"></i>
                        Active Company
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Improved Tab Navigation -->
    <div class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
            <button
                v-for="tab in tabOptions"
                :key="tab.key"
                @click="$emit('tab-change', tab.key)"
                :class="[
                    'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                    activeTab === tab.key
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                ]"
            >
                <i :class="tab.icon + ' mr-2'"></i>
                {{ tab.label }}
            </button>
        </nav>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

interface CompanyUser {
    role: string
    joined_at?: string
}

interface Company {
    id: number
    name: string
    is_active: boolean
    created_at: string
    industry?: string | null
    country?: string | null
    base_currency?: string | null
    currency?: string | null
    timezone?: string | null
    language?: string | null
    locale?: string | null
    user_role?: CompanyUser
}

interface Action {
    label: string
    icon: string
    severity?: 'success' | 'danger' | 'secondary' | 'info'
    action: () => void
}

interface TabOption {
    key: string
    label: string
    icon: string
}

const props = defineProps<{
    company: Company
    isCurrentCompany?: boolean
    primaryActions?: Action[]
    secondaryActions?: Action[]
    quickActions?: Action[]
    activeTab?: string
}>()

const emit = defineEmits<{
    'switch-context': []
    'toggle-status': []
    'tab-change': [key: string]
}>()

// Tab options for navigation
const tabOptions: TabOption[] = [
    { key: 'reports', label: 'Reports', icon: 'fas fa-chart-line' },
    { key: 'people', label: 'People', icon: 'fas fa-users' },
    { key: 'activity', label: 'Activity', icon: 'fas fa-history' }
]

const toast = useToast()

const hasActions = computed(() => {
    return (props.primaryActions?.length || 0) > 0 || (props.secondaryActions?.length || 0) > 0
})

const getRoleSeverity = (role: string): 'success' | 'info' | 'secondary' => {
    switch (role.toLowerCase()) {
        case 'owner': return 'success'
        case 'admin': return 'info'
        default: return 'secondary'
    }
}

const getRoleBadgeClasses = (role: string): string => {
    switch (role.toLowerCase()) {
        case 'owner': 
            return 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300'
        case 'admin': 
            return 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
        default: 
            return 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
    }
}

const getRoleBadgeClass = (role: string): string => {
    switch (role.toLowerCase()) {
        case 'owner': return 'role-owner'
        case 'admin': return 'role-admin'
        default: return 'role-member'
    }
}

const getRoleIcon = (role: string): string => {
    switch (role.toLowerCase()) {
        case 'owner': return 'pi pi-crown'
        case 'admin': return 'pi pi-shield'
        default: return 'pi pi-user'
    }
}

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString)
        return new Intl.DateTimeFormat('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        }).format(date)
    } catch {
        return dateString
    }
}

const getIndustryLabel = (industryValue: string) => {
    const industryMap: Record<string, string> = {
        'technology': 'Technology',
        'healthcare': 'Healthcare',
        'finance': 'Finance',
        'manufacturing': 'Manufacturing',
        'retail': 'Retail',
        'other': 'Other'
    }
    return industryMap[industryValue] || industryValue
}

const getCountryLabel = (countryValue: string) => {
    const countryMap: Record<string, string> = {
        'US': 'United States',
        'CA': 'Canada',
        'UK': 'United Kingdom',
        'AU': 'Australia',
        'DE': 'Germany',
        'FR': 'France'
    }
    return countryMap[countryValue] || countryValue
}

const copyCompanyId = async () => {
    try {
        await navigator.clipboard.writeText(String(props.company.id))
        toast.add({
            severity: 'success',
            summary: 'Copied',
            detail: 'Company ID copied.',
            life: 2000
        })
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Copy failed',
            detail: 'Unable to copy company ID.',
            life: 2500
        })
    }
}


</script>

<style scoped>
/* Minimal Header Styling */
.executive-header {
    background: transparent;
    color: #1f2937;
    position: relative;
}

/* Company Hero Section */
.company-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    position: relative;
    z-index: 10;
    max-width: 100%;
}

.company-identity {
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    flex: 1;
}

.back-navigation {
    margin-bottom: 1rem;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.back-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.nav-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}

.nav-button {
    background: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease !important;
}

.nav-button:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.company-logo-wrapper {
    perspective: 1000px;
}

.company-logo {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.2s ease;
}

.company-logo:hover {
    border-color: #9ca3af;
}

.logo-text {
    font-size: 1rem;
    font-weight: 600;
    color: #4b5563;
}

.status-dot {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.status-active {
    background: #10b981;
}

.status-inactive {
    background: #6b7280;
}

.status-icon {
    font-size: 8px;
    color: white;
}

.company-info {
    flex: 1;
}

.company-name-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.company-name {
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

.copy-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.75rem;
}

.copy-icon-btn:hover {
    background: #e5e7eb;
    color: #374151;
}



.company-badges {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.info-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
}

.industry-badge {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #dbeafe;
}

.country-badge {
    background: rgba(34, 197, 94, 0.2);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #dcfce7;
}

.info-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}


.current-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(59, 130, 246, 0.3);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(59, 130, 246, 0.5);
}

.role-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.role-owner {
    background: rgba(34, 197, 94, 0.3);
    border: 1px solid rgba(34, 197, 94, 0.5);
}

.role-admin {
    background: rgba(59, 130, 246, 0.3);
    border: 1px solid rgba(59, 130, 246, 0.5);
}

.role-member {
    background: rgba(156, 163, 175, 0.3);
    border: 1px solid rgba(156, 163, 175, 0.5);
}


/* Quick Actions */
.quick-actions {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    width: 100%;
}

.quick-actions-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
    width: 100%;
}

.quick-action-btn {
    background: rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.9rem !important;
    font-weight: 500 !important;
    border-radius: 8px !important;
    justify-content: flex-start !important;
    min-height: 44px;
}

.quick-action-btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.quick-action-btn i {
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

.quick-action-btn:hover i {
    opacity: 1;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .company-hero {
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .company-name {
        font-size: 2.5rem;
    }
    
    .nav-actions {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .company-hero {
        flex-direction: column;
        gap: 1rem;
    }
    
    .company-identity {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .nav-actions {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
    
    .company-badges {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .company-name {
        font-size: 2rem;
    }
    
    .quick-actions {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .quick-action-btn {
        padding: 0.625rem 0.75rem !important;
        font-size: 0.85rem !important;
        min-height: 40px;
    }
}
</style>
