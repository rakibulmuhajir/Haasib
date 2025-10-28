<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useDynamicPageActions } from '@/composables/useDynamicPageActions'
import { useBulkSelection } from '@/composables/useBulkSelection'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import PageActions from '@/Components/PageActions.vue'
import CompanyCard from '@/Components/CompanyCard.vue'
import CompanyRow from '@/Components/CompanyRow.vue'
import CompanyCardSkeleton from '@/Components/CompanyCardSkeleton.vue'
import CompanyRowSkeleton from '@/Components/CompanyRowSkeleton.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Toast from 'primevue/toast'

// Initialize dynamic page actions
const { initializeActions } = useDynamicPageActions()

// Initialize bulk selection
const {
    selectedItems,
    selectedCount,
    hasSelection,
    clearSelection,
    updateItems: updateBulkSelection,
    toggleItemSelection,
    isItemSelected
} = useBulkSelection([], 'companies')

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Reactive data
const loading = ref(false)
const viewMode = ref(localStorage.getItem('companies-view-mode') || 'table') // 'grid' or 'table'
const companies = ref([])
const totalRecords = ref(0)
const filters = ref({
    search: '',
    industry: null,
    country: null,
    is_active: null,
    currency: null,
})
const sort = ref({
    field: 'name',
    direction: 'asc'
})
const pagination = ref({
    page: 1,
    per_page: 15
})

// Constants
const DEFAULT_PAGE_SIZE = 15
const API_TIMEOUT = 30000 // 30 seconds

// State management
const csrfToken = ref('')
const searchTimeout = ref(null)
const abortController = ref(null)

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompany = computed(() => page.props.currentCompany)
const userCompanies = computed(() => page.props.userCompanies || [])
const canCreateCompany = computed(() => {
    return user.value?.system_role === 'system_owner' ||
           userCompanies.value.some(c => c.userRole === 'owner')
})

// Get user role for a specific company
const getUserRoleForCompany = (company) => {
    // First try to find in userCompanies from props
    const userCompany = userCompanies.value.find(uc => uc.id === company.id)
    if (userCompany?.userRole) {
        return userCompany.userRole
    }

    // Fallback: check if company has user_role property
    if (company.user_role) {
        return company.user_role
    }

    // Fallback: if user is the owner of the current company, assume owner for all companies they can access
    if (user.value?.system_role === 'system_owner') {
        return 'owner'
    }

    // Default to member
    return 'member'
}

// Filter companies based on search
const filteredCompanies = computed(() => {
    if (!filters.value.search) return companies.value

    const search = filters.value.search.toLowerCase()
    return companies.value.filter(company =>
        (company.name || '').toLowerCase().includes(search) ||
        (company.industry || '').toLowerCase().includes(search) ||
        (company.country || '').toLowerCase().includes(search)
    )
})

// Active menu state
const activeMenu = ref(null)

// Filter panel state
const showFilters = ref(false)

// Debounced search function
const debouncedSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value)
    }
    
    searchTimeout.value = setTimeout(() => {
        applyFilters()
    }, 300)
}


// Update bulk selection when companies change
watch(companies, (newCompanies) => {
    if (newCompanies.length > 0) {
        updateBulkSelection(newCompanies)
    }
})

// Options for filters
const industryOptions = [
    { label: 'Hospitality', value: 'hospitality' },
    { label: 'Retail', value: 'retail' },
    { label: 'Professional Services', value: 'professional_services' },
    { label: 'Technology', value: 'technology' },
    { label: 'Healthcare', value: 'healthcare' },
    { label: 'Education', value: 'education' },
    { label: 'Manufacturing', value: 'manufacturing' },
    { label: 'Other', value: 'other' }
]

const statusOptions = [
    { label: 'Active', value: true },
    { label: 'Inactive', value: false }
]

const sortOptions = [
    { label: 'Name (A-Z)', value: { field: 'name', direction: 'asc' } },
    { label: 'Name (Z-A)', value: { field: 'name', direction: 'desc' } },
    { label: 'Created (Newest)', value: { field: 'created_at', direction: 'desc' } },
    { label: 'Created (Oldest)', value: { field: 'created_at', direction: 'asc' } },
    { label: 'Industry', value: { field: 'industry', direction: 'asc' } }
]

// Methods
const loadCompanies = async () => {
    loading.value = true
    
    // Cancel any existing request
    if (abortController.value) {
        abortController.value.abort()
    }
    
    abortController.value = new AbortController()
    
    try {
        const params = new URLSearchParams({
            page: pagination.value.page,
            per_page: pagination.value.per_page,
            sort: sort.value.field,
            direction: sort.value.direction,
            ...Object.fromEntries(
                Object.entries(filters.value).filter(([_, value]) => value !== null && value !== '')
            )
        })

        const response = await fetch(`/api/v1/companies?${params}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value.value,
                'Cache-Control': 'no-cache'
            },
            signal: abortController.value.signal
        })
        
        if (!response.ok) {
            const errorText = await response.text()
            throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }
        
        const data = await response.json()
        
        companies.value = data.data || []
        totalRecords.value = data.meta?.total || 0
        updateBulkSelection(data.data || [])
        
    } catch (error) {
        if (error.name === 'AbortError') {
            return // Request was cancelled, don't show error
        }
        
        console.error('Failed to load companies:', error)
        
        toast.value.add({
            severity: 'error',
            summary: 'Error Loading Companies',
            detail: error.message || 'Unable to load companies. Please try again.',
            life: 5000
        })
    } finally {
        loading.value = false
        abortController.value = null
    }
}

const refreshCompanies = () => {
    loadCompanies()
}

const applyFilters = () => {
    pagination.value.page = 1
    loadCompanies()
}

const clearFilters = () => {
    filters.value = {
        search: '',
        industry: null,
        country: null,
        is_active: null,
        currency: null,
    }
    pagination.value.page = 1
    loadCompanies()
}

const clearSearch = () => {
    filters.value.search = ''
    applyFilters()
}

const toggleFilterPanel = () => {
    showFilters.value = !showFilters.value
}

// Action panel handlers
const handleAction = (action) => {
    switch (action) {
        case 'create-company':
            router.visit('/companies/create')
            break
        case 'import-companies':
            // TODO: Implement import functionality
            toast.value.add({
                severity: 'info',
                summary: 'Coming Soon',
                detail: 'Import functionality will be available soon',
                life: 3000
            })
            break
        case 'export-companies':
            exportCompanies()
            break
        default:
            console.log('Unhandled action:', action)
    }
}

const handleBulkAction = (action) => {
    switch (action) {
        case 'delete':
            bulkDeleteCompanies()
            break
        case 'edit':
            // TODO: Implement bulk edit functionality
            toast.value.add({
                severity: 'info',
                summary: 'Coming Soon',
                detail: 'Bulk edit functionality will be available soon',
                life: 3000
            })
            break
        case 'clear-selection':
            clearSelection()
            break
        default:
            // Silently handle unknown actions
    }
}

const exportCompanies = () => {
    // Create export URL with current filters
    const params = new URLSearchParams({
        ...filters.value,
        sort_field: sort.value.field,
        sort_direction: sort.value.direction,
        per_page: 'all'
    })

    window.open(`/companies/export?${params.toString()}`, '_blank')

    toast.value.add({
        severity: 'success',
        summary: 'Export Started',
        detail: 'Companies export is being prepared',
        life: 3000
    })
}

const bulkDeleteCompanies = async () => {
    if (!confirm(`Are you sure you want to delete ${selectedCount.value} companies? This action cannot be undone.`)) {
        return
    }

    try {
        const response = await fetch('/api/v1/companies/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value.value
            },
            body: JSON.stringify({
                company_ids: selectedItems.value
            })
        })

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Failed to delete companies`)
        }

        toast.value.add({
            severity: 'success',
            summary: 'Success',
            detail: `${selectedCount.value} companies deleted successfully`,
            life: 3000
        })

        clearSelection()
        loadCompanies()
    } catch (error) {
        console.error('Failed to bulk delete companies:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete companies. Please try again.',
            life: 3000
        })
    }
}

const onSort = (event) => {
    sort.value = {
        field: event.sortField,
        direction: event.sortOrder === 1 ? 'desc' : 'asc'
    }
    loadCompanies()
}

const onPage = (event) => {
    pagination.value.page = event.page + 1
    pagination.value.per_page = event.rows
    loadCompanies()
}

const switchToCompany = async (company) => {
    try {
        const response = await fetch('/api/v1/companies/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value
            },
            body: JSON.stringify({
                company_id: company.id
            })
        })

        if (response.ok) {
            const responseData = await response.json()
  
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Switched to ${company.name}`,
                life: 2000
            })

            // Redirect to companies page instead of reloading current URL
            setTimeout(() => {
                  window.location.href = window.location.origin + '/companies'
            }, 2000)
        } else {
            const errorText = await response.text()
                throw new Error(`Failed to switch company: ${response.status}`)
        }
    } catch (error) {
        console.error('Failed to switch company:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to switch company',
            life: 3000
        })
    }
}

const deactivateCompany = async (company) => {
    if (!confirm(`Are you sure you want to deactivate ${company.name}?`)) {
        return
    }

    try {
        // Get CSRF token from the hidden input
    const csrfInput = document.querySelector('input[name="_token"]')
    const csrfToken = csrfInput?.value || ''

    const response = await fetch(`/api/v1/companies/${company.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value
            },
            body: JSON.stringify({
                is_active: false
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `${company.name} has been deactivated`,
                life: 3000
            })
            loadCompanies()
        } else {
            throw new Error('Failed to deactivate company')
        }
    } catch (error) {
        console.error('Failed to deactivate company:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to deactivate company',
            life: 3000
        })
    }
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const getIndustryLabel = (industry) => {
    const option = industryOptions.find(opt => opt.value === industry)
    return option?.label || industry
}

const getSortLabel = (sortOption) => {
    if (!sortOption || !sortOption.field) return ''
    const option = sortOptions.find(opt => opt.value.field === sortOption.field && opt.value.direction === sortOption.direction)
    return option?.label || `${sortOption.field} ${sortOption.direction === 'asc' ? 'A-Z' : 'Z-A'}`
}

const getStatusSeverity = (isActive) => {
    return isActive ? 'success' : 'danger'
}

// Row styling for current company highlight
const rowClass = (data) => {
    return data.id === currentCompany.value?.id ? 'current-company-row' : ''
}

// View mode toggle
const setViewMode = (mode) => {
    viewMode.value = mode
}

// Define quick links for the companies page
const quickLinks = [
    {
        label: 'Create Company',
        url: '/companies/create',
        icon: 'pi pi-plus'
    },
    {
        label: 'Import Companies',
        url: '#',
        icon: 'pi pi-upload',
        action: () => handleAction('import-companies')
    },
    {
        label: 'Export Companies',
        url: '#',
        icon: 'pi pi-download',
        action: () => handleAction('export-companies')
    },
    {
        label: 'Company Settings',
        url: currentCompany.value ? `/companies/${currentCompany.value.id}/settings` : '#',
        icon: 'pi pi-cog',
        disabled: !currentCompany.value
    },
    {
        label: 'User Management',
        url: currentCompany.value ? `/companies/${currentCompany.value.id}/users` : '#',
        icon: 'pi pi-users',
        disabled: !currentCompany.value
    }
]

// Grid item selection
const toggleGridSelection = (company) => {
    toggleItemSelection(company)
}

// Menu handling
const setActiveMenu = (companyId) => {
    activeMenu.value = activeMenu.value === companyId ? null : companyId
}

// Toggle all selection
const toggleAllSelection = (event) => {
    if (event.target.checked) {
        // Select all filtered companies
        filteredCompanies.value.forEach(company => {
            if (!isItemSelected(company)) {
                toggleItemSelection(company)
            }
        })
    } else {
        clearSelection()
    }
}

// Role and status color helpers
const getRoleColor = (role) => {
    const colors = {
        'owner': 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800',
        'admin': 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
        'member': 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
    }
    return colors[role] || colors['member']
}

const getStatusColor = (isActive) => {
    return isActive
        ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800'
        : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800'
}


// Keyboard shortcuts
const handleKeyboardShortcuts = (event) => {
    // Cmd/Ctrl + K for search focus
    if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault()
        const searchInput = document.getElementById('search-input') || document.getElementById('search-input-mobile')
        if (searchInput) {
            searchInput.focus()
        }
    }

    // "/" key for search focus (when not in input)
    if (event.key === '/' && !(event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement)) {
        event.preventDefault()
        const searchInput = document.getElementById('search-input') || document.getElementById('search-input-mobile')
        if (searchInput) {
            searchInput.focus()
        }
    }

    // Escape to clear search and blur
    if (event.key === 'Escape') {
        if (filters.value.search) {
            clearSearch()
        } else {
            const searchInput = document.getElementById('search-input') || document.getElementById('search-input-mobile')
            if (searchInput) {
                searchInput.blur()
            }
        }
    }
}

// Lifecycle
onMounted(() => {
    // Initialize CSRF token once
    const csrfInput = document.querySelector('input[name="_token"]')
    csrfToken.value = csrfInput?.value || ''
    
    // Initialize dynamic page actions based on current route
    initializeActions()

    // Initialize companies from page props
    if (page.props.companies) {
        companies.value = page.props.companies
        totalRecords.value = page.props.companies.length
        updateBulkSelection(page.props.companies)
    } else {
        loadCompanies()
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts)
})

// Cleanup
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyboardShortcuts)
    
    // Clear search timeout
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value)
    }
    
    // Cancel any ongoing requests
    if (abortController.value) {
        abortController.value.abort()
    }
})

// Watch for company context changes
watch(currentCompany, (newCompany) => {
    if (newCompany) {
        loadCompanies()
    }
})

// Watch for view mode changes and save to localStorage
watch(viewMode, (newMode) => {
    localStorage.setItem('companies-view-mode', newMode)
})
</script>


<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
          title="Companies"
          description="Manage your business entities and organizations"
          subDescription="Create and configure company settings and preferences"
          :show-search="true"
          search-placeholder="Search companies..."
        />

        <PageActions />

        <!-- Main Content Grid -->
        <div class="content-grid-5-6">
            <!-- Left Column - Main Content -->
            <div class="main-content">
                <!-- Controls Bar -->
                <div class="card flex flex-col sm:flex-row gap-4 mb-6 p-4">
                    <!-- Search Bar -->
                    <div class="search-container">
                        <IconField>
                            <InputIcon class="fas fa-search" />
                            <InputText
                                v-model="filters.search"
                                placeholder="Search companies..."
                                type="search"
                                class="w-full"
                                @input="debouncedSearch"
                            />
                            <InputIcon v-if="filters.search" class="fas fa-times cursor-pointer" @click="clearSearch" />
                        </IconField>
                    </div>

                    <!-- View and Actions -->
                    <div class="view-controls">
                        <!-- View toggle as radiogroup -->
                        <div
                            role="radiogroup"
                            aria-label="View mode"
                            class="view-toggle"
                        >
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="viewMode === 'grid'"
                                aria-label="Grid view"
                                @click="setViewMode('grid')"
                                :class="[
                                    'view-toggle-button',
                                    viewMode === 'grid' ? 'active' : ''
                                ]"
                            >
                                <i class="fas fa-th-large" aria-hidden="true" />
                                <span class="sr-only">Grid view</span>
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="viewMode === 'table'"
                                aria-label="Table view"
                                @click="setViewMode('table')"
                                :class="[
                                    'view-toggle-button',
                                    viewMode === 'table' ? 'active' : ''
                                ]"
                            >
                                <i class="fas fa-list" aria-hidden="true" />
                                <span class="sr-only">Table view</span>
                            </button>
                        </div>

                        <Button
                            @click="handleAction('create-company')"
                            aria-label="Create new company"
                            class="create-button"
                        >
                            <i class="fas fa-plus" aria-hidden="true" />
                            <span class="hidden sm:inline">New Company</span>
                        </Button>
                    </div>
                </div>

                <!-- Grid/Table Content -->
                <div class="content-area">
                    <!-- Grid View -->
                    <section v-if="viewMode === 'grid'" aria-label="Companies grid view">
                        <ul role="list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Loading skeletons -->
                        <template v-if="loading">
                            <CompanyCardSkeleton v-for="i in 6" :key="`skeleton-${i}`" />
                        </template>

                        <!-- Actual company cards -->
                        <li v-for="company in filteredCompanies" :key="company.id">
                            <CompanyCard
                                :company="company"
                                :is-selected="selectedItems.some(item => item.id === company.id)"
                                :is-current="company.id === currentCompany?.id"
                                :user-role="getUserRoleForCompany(company)"
                                @toggle-selection="toggleGridSelection(company)"
                                @switch-company="switchToCompany(company)"
                                @deactivate-company="deactivateCompany(company)"
                            />
                        </li>
                        </ul>
                    </section>

                    <!-- Table View -->
                    <section v-else aria-label="Companies table view">
                        <div class="bw-table-container">
                            <table class="bw-table" role="table">
                                <thead>
                                    <tr>
                                        <th class="w-12 text-left">
                                            <input
                                                type="checkbox"
                                                class="w-4 h-4 rounded border border-gray-300 dark:border-gray-600"
                                                @change="toggleAllSelection"
                                                :checked="selectedItems.length === filteredCompanies.length && filteredCompanies.length > 0"
                                            />
                                        </th>
                                        <th class="text-left">
                                            Company
                                        </th>
                                        <th class="text-left">
                                            Industry
                                        </th>
                                        <th class="text-left">
                                            Country • Currency
                                        </th>
                                        <th class="text-left">
                                            Role
                                        </th>
                                        <th class="text-left">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loading skeletons -->
                                    <template v-if="loading">
                                        <CompanyRowSkeleton v-for="i in 5" :key="`skeleton-${i}`" />
                                    </template>

                                    <!-- Actual company rows -->
                                    <CompanyRow
                                        v-for="company in filteredCompanies"
                                        :key="company.id"
                                        :company="company"
                                        :is-selected="selectedItems.some(item => item.id === company.id)"
                                        :is-current="company.id === currentCompany?.id"
                                        :user-role="getUserRoleForCompany(company)"
                                        @toggle-selection="toggleGridSelection(company)"
                                        @switch-company="switchToCompany(company)"
                                        @deactivate-company="deactivateCompany(company)"
                                    />
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Empty State -->
                    <section v-if="filteredCompanies.length === 0 && !loading" aria-labelledby="empty-title" class="empty-state">
                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-building text-2xl text-gray-400 dark:text-gray-500" aria-hidden="true" />
                        </div>
                        <h2 id="empty-title" class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            {{ filters.search ? 'No companies found' : 'No companies yet' }}
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-sm">
                            {{ filters.search
                                ? 'Try adjusting your search terms'
                                : 'Get started by creating your first company' }}
                        </p>
                        <div v-if="!filters.search && canCreateCompany">
                            <Link href="/companies/create">
                                <Button class="create-button">
                                    <i class="fas fa-plus" aria-hidden="true" />
                                    Create Company
                                </Button>
                            </Link>
                        </div>
                    </section>

                    <!-- Initial Loading State -->
                    <div v-if="loading && filteredCompanies.length === 0" class="loading-state">
                        <div class="w-12 h-12 border-4 border-gray-200 dark:border-gray-700 border-t-gray-900 dark:border-t-white rounded-full animate-spin mb-4"></div>
                        <p class="text-gray-500 dark:text-gray-400">Loading companies...</p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Quick Links -->
            <div class="sidebar-content">
                <QuickLinks 
                    :links="quickLinks" 
                    title="Company Actions"
                />
            </div>
        </div>

        <!-- Floating Selection Bar -->
        <Transition
            name="bulk-actions-float"
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-in"
            enter-from-class="opacity-0 transform translate-y-4"
            enter-to-class="opacity-100 transform translate-y-0"
            leave-from-class="opacity-100 transform translate-y-0"
            leave-to-class="opacity-0 transform translate-y-4"
        >
            <div v-if="hasSelection" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
                <div class="bw-floating-toolbar">
                    <span class="bw-floating-toolbar__count">
                        {{ selectedCount }} selected
                    </span>
                    <div class="bw-floating-toolbar__divider" />
                    <button
                        @click="handleBulkAction('export')"
                        class="bw-floating-toolbar__button"
                        title="Export selected"
                    >
                        <i class="fas fa-download text-sm" />
                    </button>
                    <button
                        @click="handleBulkAction('delete')"
                        class="bw-floating-toolbar__button bw-floating-toolbar__button--danger"
                        title="Delete selected"
                    >
                        <i class="fas fa-trash text-sm" />
                    </button>
                    <button
                        @click="clearSelection"
                        class="bw-floating-toolbar__button"
                        title="Clear selection"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </Transition>
    </LayoutShell>
</template>
