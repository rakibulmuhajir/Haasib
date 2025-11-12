<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { usePageActions } from '@/composables/usePageActions'
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
    batches: Array,
    filters: Object,
    statistics: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

// Define page actions for journal entries
const journalActions = [
    {
        key: 'add-journal-entry',
        label: 'Create Journal Entry',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'ledger.journal.create'
    },
    {
        key: 'create-batch',
        label: 'Create Batch',
        icon: 'pi pi-objects-column',
        severity: 'secondary',
        routeName: 'ledger.journal.batches.create'
    },
    {
        key: 'import-entries',
        label: 'Import Entries',
        icon: 'pi pi-upload',
        severity: 'secondary'
    }
]

// Define quick links for the journal entries page
const quickLinks = [
    {
        label: 'Chart of Accounts',
        icon: 'pi pi-list',
        route: route('ledger.accounts.index')
    },
    {
        label: 'Trial Balance',
        icon: 'pi pi-calculator',
        route: route('ledger.reports.trial-balance')
    },
    {
        label: 'General Ledger',
        icon: 'pi pi-book',
        route: route('ledger.index')
    },
    {
        label: 'Batches',
        icon: 'pi pi-objects-column',
        route: route('ledger.journal.batches.index')
    }
]

// State management
const searchQuery = ref('')
const selectedStatus = ref(null)
const selectedBatch = ref(null)
const dateRange = ref(null)
const selectedEntry = ref(null)
const showEntryDialog = ref(false)
const postEntryDialog = ref(false)
const voidEntryDialog = ref(false)
const entryToAction = ref(null)
const expandedRows = ref([])

// Status options
const statusOptions = [
    { label: 'All Status', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Posted', value: 'posted' },
    { label: 'Voided', value: 'voided' }
]

// Computed properties
const filteredEntries = computed(() => {
    let entries = props.journalEntries?.data || props.journalEntries || []

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

    if (selectedBatch.value) {
        entries = entries.filter(entry => entry.batch_id === selectedBatch.value)
    }

    if (dateRange.value && dateRange.value.length === 2) {
        const [start, end] = dateRange.value
        entries = entries.filter(entry => {
            const entryDate = new Date(entry.entry_date)
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
    router.visit(route('ledger.journal.show', entry.id))
}

const editEntry = (entry) => {
    if (entry.status === 'posted') {
        toast.add({
            severity: 'warn',
            summary: 'Cannot Edit',
            detail: 'Posted entries cannot be edited',
            life: 3000
        })
        return
    }
    router.visit(route('ledger.journal.edit', entry.id))
}

const confirmPostEntry = (entry) => {
    if (entry.status !== 'draft') {
        toast.add({
            severity: 'warn',
            summary: 'Cannot Post',
            detail: 'Only draft entries can be posted',
            life: 3000
        })
        return
    }
    entryToAction.value = entry
    postEntryDialog.value = true
}

const postEntry = () => {
    if (entryToAction.value) {
        router.post(route('ledger.journal.post', entryToAction.value.id), {}, {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Journal entry posted successfully',
                    life: 3000
                })
                postEntryDialog.value = false
                entryToAction.value = null
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to post journal entry',
                    life: 3000
                })
            }
        })
    }
}

const confirmVoidEntry = (entry) => {
    if (entry.status !== 'posted') {
        toast.add({
            severity: 'warn',
            summary: 'Cannot Void',
            detail: 'Only posted entries can be voided',
            life: 3000
        })
        return
    }
    entryToAction.value = entry
    voidEntryDialog.value = true
}

const voidEntry = () => {
    if (entryToAction.value) {
        router.post(route('ledger.journal.void', entryToAction.value.id), {}, {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Journal entry voided successfully',
                    life: 3000
                })
                voidEntryDialog.value = false
                entryToAction.value = null
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to void journal entry',
                    life: 3000
                })
            }
        })
    }
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const onRowExpand = (event) => {
    // Row expanded - load journal lines if needed
}

const onRowCollapse = (event) => {
    // Row collapsed
}
</script>

<template>
    <LayoutShell>
        <Head title="Journal Entries" />

        <UniversalPageHeader
            title="Journal Entries"
            subtitle="Create and manage journal entries"
            :actions="journalActions"
        />

        <Toast />

        <!-- Quick Links -->
        <QuickLinks :links="quickLinks" />

        <!-- Statistics Cards -->
        <div v-if="statistics" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="card">
                <div class="text-2xl font-bold text-blue-600">{{ statistics.total_entries || 0 }}</div>
                <div class="text-surface-600">Total Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-green-600">{{ statistics.posted_entries || 0 }}</div>
                <div class="text-surface-600">Posted Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-orange-600">{{ statistics.draft_entries || 0 }}</div>
                <div class="text-surface-600">Draft Entries</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-purple-600">{{ statistics.this_month || 0 }}</div>
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
                    <Dropdown
                        v-model="selectedBatch"
                        :options="batches"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Filter by batch"
                        class="w-48"
                        showClear
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
                    Showing {{ filteredEntries.length }} of {{ journalEntries?.data?.length || journalEntries?.length || 0 }} entries
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
                :expandedRows="expandedRows"
                @row-expand="onRowExpand"
                @row-collapse="onRowCollapse"
            >
                <Column :expander="true" headerStyle="width: 3rem" />

                <Column field="entry_date" header="Date" style="min-width: 120px">
                    <template #body="{ data }">
                        {{ formatDate(data.entry_date) }}
                    </template>
                </Column>

                <Column field="entry_number" header="Reference" style="min-width: 140px">
                    <template #body="{ data }">
                        <span class="font-mono">{{ data.entry_number }}</span>
                    </template>
                </Column>

                <Column field="description" header="Description" style="min-width: 300px">
                    <template #body="{ data }">
                        <div>
                            <span class="font-semibold">{{ data.description }}</span>
                            <div v-if="data.batch" class="text-sm text-surface-600 mt-1">
                                Batch: {{ data.batch.name }}
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

                <Column field="created_by" header="Created By" style="min-width: 140px">
                    <template #body="{ data }">
                        {{ data.created_by?.name || 'System' }}
                    </template>
                </Column>

                <Column header="Actions" style="min-width: 200px">
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
                            <Button
                                icon="pi pi-pencil"
                                size="small"
                                text
                                rounded
                                severity="secondary"
                                @click="editEntry(data)"
                                v-tooltip="'Edit Entry'"
                                v-if="can.update && data.status === 'draft'"
                            />
                            <Button
                                icon="pi pi-check"
                                size="small"
                                text
                                rounded
                                severity="success"
                                @click="confirmPostEntry(data)"
                                v-tooltip="'Post Entry'"
                                v-if="can.post && data.status === 'draft'"
                            />
                            <Button
                                icon="pi pi-ban"
                                size="small"
                                text
                                rounded
                                severity="danger"
                                @click="confirmVoidEntry(data)"
                                v-tooltip="'Void Entry'"
                                v-if="can.void && data.status === 'posted'"
                            />
                        </div>
                    </template>
                </Column>

                <!-- Row Expansion Template -->
                <template #expansion="{ data }">
                    <div class="p-4 bg-surface-50">
                        <h5 class="font-semibold mb-3">Journal Lines</h5>
                        <DataTable :value="data.journal_lines || []" responsiveLayout="scroll">
                            <Column field="account.account_number" header="Account" style="min-width: 200px">
                                <template #body="{ data: line }">
                                    <div>
                                        <span class="font-mono">{{ line.account?.account_number }}</span>
                                        <div class="text-sm text-surface-600">{{ line.account?.account_name }}</div>
                                    </div>
                                </template>
                            </Column>
                            <Column field="description" header="Description" style="min-width: 250px" />
                            <Column field="debit_amount" header="Debit" style="min-width: 120px">
                                <template #body="{ data: line }">
                                    <span v-if="line.debit_amount" class="text-green-600 font-semibold">
                                        {{ formatCurrency(line.debit_amount) }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="credit_amount" header="Credit" style="min-width: 120px">
                                <template #body="{ data: line }">
                                    <span v-if="line.credit_amount" class="text-red-600 font-semibold">
                                        {{ formatCurrency(line.credit_amount) }}
                                    </span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Post Entry Confirmation Dialog -->
        <Dialog
            v-model:visible="postEntryDialog"
            :style="{ width: '450px' }"
            header="Confirm Post"
            :modal="true"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-check-circle text-3xl text-green-500" />
                <div v-if="entryToAction">
                    Are you sure you want to post journal entry
                    <span class="font-semibold">{{ entryToAction.entry_number }}</span>?
                    <br>
                    This action cannot be undone.
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="postEntryDialog = false" />
                <Button
                    label="Post Entry"
                    icon="pi pi-check"
                    severity="success"
                    @click="postEntry"
                />
            </template>
        </Dialog>

        <!-- Void Entry Confirmation Dialog -->
        <Dialog
            v-model:visible="voidEntryDialog"
            :style="{ width: '450px' }"
            header="Confirm Void"
            :modal="true"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-ban text-3xl text-red-500" />
                <div v-if="entryToAction">
                    Are you sure you want to void journal entry
                    <span class="font-semibold">{{ entryToAction.entry_number }}</span>?
                    <br>
                    This action cannot be undone.
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="voidEntryDialog = false" />
                <Button
                    label="Void Entry"
                    icon="pi pi-ban"
                    severity="danger"
                    @click="voidEntry"
                />
            </template>
        </Dialog>
    </LayoutShell>
</template>