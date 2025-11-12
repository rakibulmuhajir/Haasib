<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Toolbar from 'primevue/toolbar'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Calendar from 'primevue/calendar'
import Toast from 'primevue/toast'
import { route } from 'ziggy-js'

const props = defineProps({
    journalEntries: Array,
    accounts: Array,
    statistics: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

// Define page actions for main ledger page
const ledgerActions = [
    {
        key: 'add-journal-entry',
        label: 'Create Journal Entry',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'ledger.create'
    },
    {
        key: 'view-accounts',
        label: 'Chart of Accounts',
        icon: 'pi pi-list',
        severity: 'secondary',
        routeName: 'ledger.accounts.index'
    },
    {
        key: 'view-journal',
        label: 'Journal Entries',
        icon: 'pi pi-book',
        severity: 'secondary',
        routeName: 'ledger.journal.index'
    },
    {
        key: 'period-close',
        label: 'Period Close',
        icon: 'pi pi-lock',
        severity: 'secondary',
        routeName: 'ledger.periods.index'
    }
]

// Define quick links for the ledger page
const quickLinks = [
    {
        label: 'Chart of Accounts',
        icon: 'pi pi-list',
        route: route('ledger.accounts.index')
    },
    {
        label: 'Journal Entries',
        icon: 'pi pi-book',
        route: route('ledger.journal.index')
    },
    {
        label: 'Trial Balance',
        icon: 'pi pi-calculator',
        route: route('ledger.reports.trial-balance')
    },
    {
        label: 'Balance Sheet',
        icon: 'pi pi-file',
        route: route('ledger.reports.balance-sheet')
    },
    {
        label: 'Income Statement',
        icon: 'pi pi-chart-line',
        route: route('ledger.reports.income-statement')
    },
    {
        label: 'Period Close',
        icon: 'pi pi-lock',
        route: route('ledger.periods.index')
    }
]

// State management
const searchQuery = ref('')
const selectedStatus = ref(null)
const dateRange = ref(null)
const selectedEntry = ref(null)
const showEntryDialog = ref(false)

// Status options
const statusOptions = [
    { label: 'All Status', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Posted', value: 'posted' },
    { label: 'Voided', value: 'voided' }
]

// Computed properties
const filteredEntries = computed(() => {
    let entries = props.journalEntries?.data || []

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        entries = entries.filter(entry =>
            entry.description?.toLowerCase().includes(query) ||
            entry.reference?.toLowerCase().includes(query) ||
            entry.journal_lines?.some(line =>
                line.account?.name?.toLowerCase().includes(query) ||
                line.account?.code?.toLowerCase().includes(query)
            )
        )
    }

    if (selectedStatus.value) {
        entries = entries.filter(entry => entry.status === selectedStatus.value)
    }

    if (dateRange.value && dateRange.value.length === 2) {
        const [start, end] = dateRange.value
        entries = entries.filter(entry => {
            const entryDate = new Date(entry.date || entry.journal_date || entry.created_at)
            return entryDate >= start && entryDate <= end
        })
    }

    return entries
})

// Methods
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0)
}

const getStatusBadge = (status) => {
    const statuses = {
        draft: { label: 'Draft', severity: 'secondary' },
        posted: { label: 'Posted', severity: 'success' },
        voided: { label: 'Voided', severity: 'danger' }
    }
    return statuses[status] || { label: status, severity: 'secondary' }
}

const viewEntry = (entry) => {
    router.visit(route('ledger.show', entry.id))
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString()
}

const getTotalEntries = () => {
    return props.statistics?.total_entries || props.journalEntries?.total || props.journalEntries?.data?.length || 0
}

const getPostedEntries = () => {
    return props.statistics?.posted_entries || (props.journalEntries?.data?.filter(e => e.status === 'posted').length || 0)
}

const getDraftEntries = () => {
    return props.statistics?.draft_entries || (props.journalEntries?.data?.filter(e => e.status === 'draft').length || 0)
}

const getThisMonthEntries = () => {
    const currentMonth = new Date().getMonth()
    const currentYear = new Date().getFullYear()
    return props.journalEntries?.data?.filter(entry => {
        const entryDate = new Date(entry.date || entry.journal_date || entry.created_at)
        return entryDate.getMonth() === currentMonth && entryDate.getFullYear() === currentYear
    }).length || 0
}
</script>

<template>
    <LayoutShell>
        <Head title="General Ledger" />

        <UniversalPageHeader
            title="General Ledger"
            subtitle="Manage your company's financial records"
        />

        <Toast />

        <!-- Quick Links -->
        <QuickLinks :links="quickLinks" />

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="card">
                <div class="text-2xl font-bold text-blue-600">{{ getTotalEntries() }}</div>
                <div class="text-surface-600">Total Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-green-600">{{ getPostedEntries() }}</div>
                <div class="text-surface-600">Posted Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-orange-600">{{ getDraftEntries() }}</div>
                <div class="text-surface-600">Draft Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-purple-600">{{ getThisMonthEntries() }}</div>
                <div class="text-surface-600">This Month</div>
            </div>
        </div>

        <!-- Filters Toolbar -->
        <Toolbar class="mb-6">
            <template #start>
                <div class="flex gap-4 items-center flex-wrap">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search entries..."
                            class="w-64"
                        />
                    </IconField>
                    <Dropdown
                        v-model="selectedStatus"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Filter by status"
                        class="w-40"
                    />
                    <Calendar
                        v-model="dateRange"
                        selectionMode="range"
                        :manualInput="false"
                        placeholder="Date range"
                        class="w-48"
                    />
                </div>
            </template>
            <template #end>
                <div class="text-sm text-surface-600">
                    Showing {{ filteredEntries.length }} of {{ journalEntries?.data?.length || 0 }} entries
                </div>
            </template>
        </Toolbar>

        <!-- Journal Entries Data Table -->
        <div class="card">
            <DataTable
                :value="filteredEntries"
                :paginator="true"
                :rows="25"
                :loading="false"
                :globalFilterFields="['description', 'reference']"
                responsiveLayout="scroll"
                :rowHover="true"
                v-model:selection="selectedEntry"
                selectionMode="single"
                @row-select="viewEntry"
                dataKey="id"
            >
                <Column field="date" header="Date" style="min-width: 120px">
                    <template #body="{ data }">
                        {{ formatDate(data.date || data.journal_date || data.created_at) }}
                    </template>
                </Column>

                <Column field="reference" header="Reference" style="min-width: 140px">
                    <template #body="{ data }">
                        <span class="font-mono">{{ data.reference || data.journal_number }}</span>
                    </template>
                </Column>

                <Column field="description" header="Description" style="min-width: 300px">
                    <template #body="{ data }">
                        <div>
                            <span class="font-semibold">{{ data.description }}</span>
                            <div v-if="data.created_by" class="text-sm text-surface-600 mt-1">
                                by {{ data.created_by.name || 'System' }}
                            </div>
                        </div>
                    </template>
                </Column>

                <Column field="total_debits" header="Debits" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="text-green-600 font-semibold">
                            {{ formatCurrency(data.total_debits) }}
                        </span>
                    </template>
                </Column>

                <Column field="total_credits" header="Credits" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="text-red-600 font-semibold">
                            {{ formatCurrency(data.total_credits) }}
                        </span>
                    </template>
                </Column>

                <Column field="status" header="Status" style="min-width: 120px">
                    <template #body="{ data }">
                        <Tag
                            :value="getStatusBadge(data.status).label"
                            :severity="getStatusBadge(data.status).severity"
                        />
                    </template>
                </Column>

                <Column header="Actions" style="min-width: 120px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                icon="pi pi-eye"
                                size="small"
                                text
                                rounded
                                @click="viewEntry(data)"
                                v-tooltip="'View Entry'"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Quick Actions Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="card text-center p-6">
                <i class="pi pi-plus-circle text-4xl text-blue-600 mb-3"></i>
                <h3 class="text-lg font-semibold mb-2">Create Journal Entry</h3>
                <p class="text-surface-600 mb-4">Create a new double-entry journal entry</p>
                <Button
                    label="Create Entry"
                    icon="pi pi-plus"
                    @click="router.visit(route('ledger.create'))"
                    severity="primary"
                />
            </div>

            <div class="card text-center p-6">
                <i class="pi pi-list text-4xl text-green-600 mb-3"></i>
                <h3 class="text-lg font-semibold mb-2">Chart of Accounts</h3>
                <p class="text-surface-600 mb-4">Manage your company's chart of accounts</p>
                <Button
                    label="View Accounts"
                    icon="pi pi-list"
                    @click="router.visit(route('ledger.accounts.index'))"
                    severity="success"
                />
            </div>

            <div class="card text-center p-6">
                <i class="pi pi-chart-line text-4xl text-purple-600 mb-3"></i>
                <h3 class="text-lg font-semibold mb-2">Financial Reports</h3>
                <p class="text-surface-600 mb-4">View trial balance, balance sheet, and income statement</p>
                <Button
                    label="View Reports"
                    icon="pi pi-chart-line"
                    @click="router.visit(route('ledger.reports.trial-balance'))"
                    severity="secondary"
                />
            </div>
        </div>
    </LayoutShell>
</template>