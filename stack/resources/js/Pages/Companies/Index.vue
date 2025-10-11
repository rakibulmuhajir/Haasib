<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Dropdown from 'primevue/dropdown'
import ProgressBar from 'primevue/progressbar'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import Tooltip from 'primevue/tooltip'

const { t } = useI18n()
const page = usePage()
const toast = ref()

// Reactive data
const loading = ref(false)
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

        const response = await fetch(`/api/v1/companies?${params}`)
        const data = await response.json()
        
        if (response.ok) {
            companies.value = data.data || []
            totalRecords.value = data.meta?.total || 0
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
        const response = await fetch('/api/v1/company-context/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                company_id: company.id
            })
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: `Switched to ${company.name}`,
                life: 2000
            })
            
            // Reload page to update context
            setTimeout(() => {
                window.location.reload()
            }, 1000)
        } else {
            throw new Error('Failed to switch company')
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
        const response = await fetch(`/api/v1/companies/${company.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
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

const getStatusSeverity = (isActive) => {
    return isActive ? 'success' : 'danger'
}

// Lifecycle
onMounted(() => {
    loadCompanies()
})

// Watch for company context changes
watch(currentCompany, (newCompany) => {
    if (newCompany) {
        loadCompanies()
    }
})
</script>

<template>
    <div class="companies-index">
        <Toast ref="toast" />
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Companies
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Manage your companies and switch between them
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button
                    @click="refreshCompanies"
                    :loading="loading"
                    icon="pi pi-refresh"
                    label="Refresh"
                    severity="secondary"
                    outlined
                />
                <Link
                    v-if="canCreateCompany"
                    href="/companies/create"
                >
                    <Button
                        icon="pi pi-plus"
                        label="Create Company"
                    />
                </Link>
            </div>
        </div>

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
            <template #title>Filters</template>
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="filters.search"
                            placeholder="Search companies..."
                            @keyup.enter="applyFilters"
                        />
                    </IconField>
                    
                    <Dropdown
                        v-model="filters.industry"
                        :options="industryOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select Industry"
                        show-clear
                        @change="applyFilters"
                    />
                    
                    <Dropdown
                        v-model="filters.is_active"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select Status"
                        show-clear
                        @change="applyFilters"
                    />
                    
                    <Dropdown
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
                >
                    <Column field="name" header="Company" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <Avatar
                                    :label="data.name.charAt(0)"
                                    class="bg-primary text-primary-contrast"
                                />
                                <div>
                                    <div class="font-semibold">{{ data.name }}</div>
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
                                    icon="pi pi-sign-in"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    v-tooltip="'Switch to this company'"
                                />
                                
                                <Link :href="`/companies/${data.id}`">
                                    <Button
                                        icon="pi pi-eye"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        v-tooltip="'View details'"
                                    />
                                </Link>
                                
                                <Button
                                    v-if="data.is_active && data.user_role === 'owner'"
                                    @click="deactivateCompany(data)"
                                    icon="pi pi-times"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    v-tooltip="'Deactivate company'"
                                />
                            </div>
                        </template>
                    </Column>
                    
                    <template #empty>
                        <div class="text-center py-8">
                            <i class="pi pi-building text-4xl text-gray-400"></i>
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
    </div>
</template>

<style scoped>
.companies-index {
    @apply p-6;
}
</style>