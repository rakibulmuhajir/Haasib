<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useDynamicPageActions } from '@/composables/useDynamicPageActions'
import { useBulkSelection } from '@/composables/useBulkSelection'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ColumnGroup from 'primevue/columngroup'
import Row from 'primevue/row'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ProgressBar from 'primevue/progressbar'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import Tooltip from 'primevue/tooltip'
import Checkbox from 'primevue/checkbox'

// Initialize dynamic page actions
const { initializeActions } = useDynamicPageActions()

// Initialize bulk selection
const {
    selectedItems,
    selectedCount,
    hasSelection,
    isIndeterminate,
    selectAll,
    toggleItemSelection,
    isItemSelected,
    toggleSelectAll,
    clearSelection,
    updateItems: updateBulkSelection
} = useBulkSelection([], 'companies')

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Reactive data
const loading = ref(false)
const filtersVisible = ref(true)
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

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompany = computed(() => page.props.currentCompany)
const userCompanies = computed(() => page.props.userCompanies || [])
const canCreateCompany = computed(() => {
    return user.value?.system_role === 'system_owner' || 
           userCompanies.value.some(c => c.userRole === 'owner')
})

// Count active filters
const activeFiltersCount = computed(() => {
    let count = 0
    if (filters.value.search) count++
    if (filters.value.industry) count++
    if (filters.value.country) count++
    if (filters.value.is_active !== null) count++
    if (filters.value.currency) count++
    return count
})

// Update bulk selection when companies change
watch(companies, (newCompanies) => {
    if (newCompanies.length > 0) {
        updateBulkSelection(newCompanies)
    }
}, { deep: true })

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

        // Get CSRF token from the hidden input
    const csrfInput = document.querySelector('input[name="_token"]')
    const csrfToken = csrfInput?.value || ''
    
    const response = await fetch(`/api/v1/companies?${params}`, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        const data = await response.json()
        
        if (response.ok) {
            companies.value = data.data || []
            totalRecords.value = data.meta?.total || 0
            updateBulkSelection(data.data || [])
        } else {
            throw new Error(data.message || 'Failed to load companies')
        }
    } catch (error) {
        console.error('Failed to load companies:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message,
            life: 3000
        })
    } finally {
        loading.value = false
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

const toggleFilters = () => {
    filtersVisible.value = !filtersVisible.value
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
        // Get CSRF token from the hidden input (this is what @csrf generates)
        const csrfInput = document.querySelector('input[name="_token"]')
        const csrfToken = csrfInput?.value || ''
        
        const response = await fetch('/api/v1/companies/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                company_id: company.id
            })
        })

        if (response.ok) {
            const responseData = await response.json()
            console.log('Switch response:', responseData)
            console.log('Current company before reload:', window.location.href)
            console.log('Window location origin:', window.location.origin)
            
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Switched to ${company.name}`,
                life: 2000
            })
            
            // Redirect to companies page instead of reloading current URL
            setTimeout(() => {
                console.log('About to redirect to companies page...')
                window.location.href = window.location.origin + '/companies'
            }, 2000)
        } else {
            const errorText = await response.text()
            console.error('Switch failed response:', errorText)
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
                'X-CSRF-TOKEN': csrfToken
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

// Bulk action methods
const performBulkDelete = async () => {
    if (selectedCount.value === 0) return
    
    if (!confirm(`Are you sure you want to delete ${selectedCount.value} selected ${selectedCount.value === 1 ? 'company' : 'companies'}? This action cannot be undone.`)) {
        return
    }

    try {
        const companyIds = selectedItems.value.map(company => company.id)
        
        const response = await fetch('/companies/bulk', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete',
                company_ids: companyIds
            })
        })

        const data = await response.json()
        
        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Successfully deleted ${selectedCount.value} ${selectedCount.value === 1 ? 'company' : 'companies'}`,
                life: 3000
            })
            
            clearSelection()
            loadCompanies()
        } else {
            throw new Error(data.message || 'Failed to delete companies')
        }
    } catch (error) {
        console.error('Failed to delete companies:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to delete companies',
            life: 3000
        })
    }
}

const performBulkExport = () => {
    if (selectedCount.value === 0) return
    
    const companyIds = selectedItems.value.map(company => company.id).join(',')
    const exportUrl = `/api/companies/export?ids=${companyIds}`
    
    // Create a temporary link to trigger download
    const link = document.createElement('a')
    link.href = exportUrl
    link.target = '_blank'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    
    toast.value.add({
        severity: 'info',
        summary: 'Export Started',
        detail: `Exporting ${selectedCount.value} ${selectedCount.value === 1 ? 'company' : 'companies'}...`,
        life: 3000
    })
}

// Keyboard shortcuts
const handleKeyboardShortcuts = (event) => {
    // Ctrl/Cmd + K to focus search
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault()
        if (!filtersVisible.value) {
            toggleFilters()
        }
        // Focus on search input after a short delay to allow it to render
        setTimeout(() => {
            const searchInput = document.querySelector('input[placeholder="Search companies..."]')
            if (searchInput) {
                searchInput.focus()
            }
        }, 100)
    }
    
    // Escape to collapse filters
    if (event.key === 'Escape' && filtersVisible.value) {
        filtersVisible.value = false
    }
}

// Lifecycle
onMounted(() => {
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
})

// Watch for company context changes
watch(currentCompany, (newCompany) => {
    if (newCompany) {
        loadCompanies()
    }
})
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />
        
        <!-- Page Header -->
        <PageHeader 
            title="Companies" 
            subtitle="Manage your companies and switch between them"
        />

        <!-- Current Company Info -->
        <Card v-if="currentCompany" class="mb-6">
            <template #content>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Avatar
                            :label="currentCompany.name.charAt(0)"
                            class="bg-primary text-primary-contrast"
                            size="large"
                        />
                        <div>
                            <h3 class="font-semibold text-lg">{{ currentCompany.name }}</h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ currentCompany.industry }} • {{ currentCompany.currency }}
                            </p>
                        </div>
                        <Badge
                            :value="currentCompany.userRole"
                            :severity="getStatusSeverity(currentCompany.isActive)"
                        />
                    </div>
                    <div class="text-sm text-gray-500">
                        Currently active company
                    </div>
                </div>
            </template>
        </Card>

        <!-- Filters -->
        <Card class="mb-6">
            <template #title>
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <span>Filters</span>
                        <Badge 
                            v-if="activeFiltersCount > 0" 
                            :value="activeFiltersCount" 
                            severity="info"
                            class="text-xs"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="activeFiltersCount > 0 && !filtersVisible"
                            @click="clearFilters"
                            label="Clear"
                            severity="secondary"
                            text
                            size="small"
                            class="p-0 text-xs"
                        />
                        <Button
                            @click="toggleFilters"
                            :icon="filtersVisible ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                            severity="secondary"
                            text
                            rounded
                            size="small"
                            class="p-0"
                            v-tooltip="filtersVisible ? 'Collapse filters' : 'Expand filters'"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <!-- Filter summary when collapsed -->
                <div v-if="!filtersVisible && activeFiltersCount > 0" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span class="font-medium">{{ activeFiltersCount }} active {{ activeFiltersCount === 1 ? 'filter' : 'filters' }}</span>
                    <span class="ml-2">
                        <span v-if="filters.value.search" class="inline-block bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs mr-1">Search: "{{ filters.value.search }}"</span>
                        <span v-if="filters.value.industry" class="inline-block bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs mr-1">Industry: {{ getIndustryLabel(filters.value.industry) }}</span>
                        <span v-if="filters.value.is_active !== null" class="inline-block bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs mr-1">Status: {{ filters.value.is_active ? 'Active' : 'Inactive' }}</span>
                        <span v-if="filters.value.sort" class="inline-block bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs mr-1">Sort: {{ getSortLabel(filters.value.sort) }}</span>
                    </span>
                </div>
                
                <div v-show="filtersVisible" class="transition-all duration-300 ease-in-out">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <IconField>
                        <InputIcon class="fas fa-search" />
                        <InputText
                            v-model="filters.search"
                            placeholder="Search companies..."
                            @keyup.enter="applyFilters"
                        />
                    </IconField>
                    
                    <Select
                        v-model="filters.industry"
                        :options="industryOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select Industry"
                        show-clear
                        @change="applyFilters"
                    />
                    
                    <Select
                        v-model="filters.is_active"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select Status"
                        show-clear
                        @change="applyFilters"
                    />
                    
                    <Select
                        v-model="sort"
                        :options="sortOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Sort By"
                        @change="applyFilters"
                    />
                </div>
                
                <div class="flex gap-2 mt-4">
                    <Button
                        @click="applyFilters"
                        label="Apply Filters"
                        icon="pi pi-filter"
                        size="small"
                    />
                    <Button
                        @click="clearFilters"
                        label="Clear"
                        severity="secondary"
                        outlined
                        size="small"
                    />
                </div>
                </div>
            </template>
        </Card>

        <!-- Bulk Actions Bar -->
        <Card v-if="hasSelection" class="mb-6 border-l-4 border-blue-500">
            <template #content>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Checkbox
                            :model-value="selectAll"
                            @update:model-value="toggleSelectAll"
                            :binary="true"
                        />
                        <span class="font-medium">
                            {{ selectedCount }} {{ selectedCount === 1 ? 'company' : 'companies' }} selected
                        </span>
                        <Badge :value="selectedCount" severity="info" />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            @click="clearSelection"
                            label="Clear Selection"
                            severity="secondary"
                            outlined
                            size="small"
                            icon="fas fa-times"
                        />
                        <Button
                            @click="performBulkDelete"
                            label="Delete Selected"
                            severity="danger"
                            size="small"
                            icon="fas fa-trash"
                            :disabled="selectedCount === 0"
                        />
                        <Button
                            @click="performBulkExport"
                            label="Export Selected"
                            severity="secondary"
                            outlined
                            size="small"
                            icon="fas fa-download"
                            :disabled="selectedCount === 0"
                        />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Companies Table -->
        <Card>
            <template #content>
                <DataTable
                    :value="companies"
                    :loading="loading"
                    :total-records="totalRecords"
                    :paginator="true"
                    :rows="pagination.per_page"
                    :first="(pagination.page - 1) * pagination.per_page"
                    lazy
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    current-page-report-template="Showing {first} to {last} of {totalRecords} companies"
                    @sort="onSort"
                    @page="onPage"
                    sort-field="name"
                    :sort-order="1"
                    scrollable
                    scroll-height="flex"
                    v-model:selection="selectedItems"
                    data-key="id"
                    :selectAll="selectAll"
                    @select-all-change="toggleSelectAll"
                >
                    <!-- Selection Column -->
                    <Column selection-mode="multiple" header-style="width: 3rem; text-align: center" body-style="width: 3rem; text-align: center"></Column>
                    
                    <Column field="name" header="Company" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <Avatar
                                    :label="data.name.charAt(0)"
                                    class="bg-primary text-primary-contrast"
                                />
                                <div>
                                    <Link :href="`/companies/${data.id}`" class="font-semibold text-primary hover:text-primary-600 transition-colors">
                                        {{ data.name }}
                                    </Link>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ data.slug }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="industry" header="Industry" sortable>
                        <template #body="{ data }">
                            {{ getIndustryLabel(data.industry) }}
                        </template>
                    </Column>
                    
                    <Column field="country" header="Country" sortable />
                    
                    <Column field="currency" header="Currency" sortable />
                    
                    <Column field="user_role" header="Your Role">
                        <template #body="{ data }">
                            <Badge
                                :value="data.user_role"
                                :severity="data.user_role === 'owner' ? 'success' : 'info'"
                            />
                        </template>
                    </Column>
                    
                    <Column field="is_active" header="Status" sortable>
                        <template #body="{ data }">
                            <Badge
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                :severity="getStatusSeverity(data.is_active)"
                            />
                        </template>
                    </Column>
                    
                    <Column field="created_at" header="Created" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    
                    <Column header="Actions">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    v-if="data.id !== currentCompany?.id"
                                    @click="switchToCompany(data)"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    v-tooltip="'Switch to this company'"
                                >
                                    <i class="fas fa-sign-in-alt"></i>
                                </Button>
                                
                                <Link :href="`/companies/${data.id}`">
                                    <Button
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        v-tooltip="'View details'"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </Button>
                                </Link>
                                
                                <Button
                                    v-if="data.is_active && data.user_role === 'owner'"
                                    @click="deactivateCompany(data)"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    v-tooltip="'Deactivate company'"
                                >
                                    <i class="fas fa-ban"></i>
                                </Button>
                            </div>
                        </template>
                    </Column>
                    
                    <template #empty>
                        <div class="text-center py-8">
                            <i class="fas fa-building text-4xl text-gray-400"></i>
                            <p class="text-gray-500 dark:text-gray-400 mt-4">
                                No companies found
                            </p>
                            <Link v-if="canCreateCompany" href="/companies/create">
                                <Button
                                    label="Create your first company"
                                    icon="pi pi-plus"
                                    class="mt-4"
                                />
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </LayoutShell>
</template>

<style scoped>
/* No extra padding needed - LayoutShell handles layout spacing */
</style>