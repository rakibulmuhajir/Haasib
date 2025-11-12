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
import TreeTable from 'primevue/treetable'
import Toast from 'primevue/toast'
import { route } from 'ziggy-js'

const props = defineProps({
    accounts: Array,
    accountGroups: Array,
    filters: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()
const { actions } = usePageActions()

// Define page actions for chart of accounts
const accountActions = [
    {
        key: 'add-account',
        label: 'Add Account',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'ledger.accounts.create'
    },
    {
        key: 'import-accounts',
        label: 'Import Chart of Accounts',
        icon: 'pi pi-upload',
        severity: 'secondary'
    },
    {
        key: 'export-accounts',
        label: 'Export Accounts',
        icon: 'pi pi-download',
        severity: 'secondary'
    }
]

// Define quick links for the chart of accounts page
const quickLinks = [
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
        label: 'Journal Entries',
        icon: 'pi pi-book',
        route: route('ledger.journal.index')
    }
]

// State management
const searchQuery = ref('')
const selectedAccountType = ref(null)
const selectedAccount = ref(null)
const showAccountDialog = ref(false)
const deleteAccountDialog = ref(false)
const accountToDelete = ref(null)

// Account type options
const accountTypes = [
    { label: 'All Types', value: null },
    { label: 'Assets', value: 'asset' },
    { label: 'Liabilities', value: 'liability' },
    { label: 'Equity', value: 'equity' },
    { label: 'Revenue', value: 'revenue' },
    { label: 'Expenses', value: 'expense' }
]

// Computed properties
const filteredAccounts = computed(() => {
    let accounts = props.accounts || []

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        accounts = accounts.filter(account =>
            account.name.toLowerCase().includes(query) ||
            account.code.toLowerCase().includes(query) ||
            account.description?.toLowerCase().includes(query)
        )
    }

    if (selectedAccountType.value) {
        accounts = accounts.filter(account => account.type === selectedAccountType.value)
    }

    return accounts
})

// Methods
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0)
}

const getAccountTypeBadge = (type) => {
    const types = {
        asset: { label: 'Asset', severity: 'success' },
        liability: { label: 'Liability', severity: 'warning' },
        equity: { label: 'Equity', severity: 'info' },
        revenue: { label: 'Revenue', severity: 'success' },
        expense: { label: 'Expense', severity: 'danger' }
    }
    return types[type] || { label: type, severity: 'secondary' }
}

const viewAccount = (account) => {
    router.visit(route('ledger.accounts.show', account.id))
}

const editAccount = (account) => {
    router.visit(route('ledger.accounts.edit', account.id))
}

const confirmDeleteAccount = (account) => {
    accountToDelete.value = account
    deleteAccountDialog.value = true
}

const deleteAccount = () => {
    if (accountToDelete.value) {
        router.delete(route('ledger.accounts.destroy', accountToDelete.value.id), {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Account deleted successfully',
                    life: 3000
                })
                deleteAccountDialog.value = false
                accountToDelete.value = null
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete account',
                    life: 3000
                })
            }
        })
    }
}

const toggleAccountStatus = (account) => {
    router.patch(route('ledger.accounts.toggle-status', account.id), {
        active: !account.active
    }, {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Account ${account.active ? 'deactivated' : 'activated'} successfully`,
                life: 3000
            })
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to update account status',
                life: 3000
            })
        }
    })
}
</script>

<template>
    <LayoutShell>
        <Head title="Chart of Accounts" />

        <UniversalPageHeader
            title="Chart of Accounts"
            subtitle="Manage your company's chart of accounts"
            :actions="actions(accountActions)"
        />

        <Toast />

        <!-- Quick Links -->
        <QuickLinks :links="quickLinks" />

        <!-- Filters Toolbar -->
        <Toolbar class="mb-6">
            <template #start>
                <div class="flex gap-4 items-center">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search accounts..."
                            class="w-64"
                        />
                    </IconField>
                    <Dropdown
                        v-model="selectedAccountType"
                        :options="accountTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Filter by type"
                        class="w-48"
                    />
                </div>
            </template>
            <template #end>
                <div class="text-sm text-surface-600">
                    Showing {{ filteredAccounts.length }} of {{ accounts.length }} accounts
                </div>
            </template>
        </Toolbar>

        <!-- Accounts Data Table -->
        <div class="card">
            <DataTable
                :value="filteredAccounts"
                :paginator="true"
                :rows="25"
                :loading="false"
                :globalFilterFields="['name', 'code', 'description']"
                responsiveLayout="scroll"
                :rowHover="true"
                v-model:selection="selectedAccount"
                selectionMode="single"
                @row-select="viewAccount"
                dataKey="id"
            >
                <Column field="code" header="Code" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="font-mono">{{ data.code }}</span>
                    </template>
                </Column>

                <Column field="name" header="Account Name" style="min-width: 300px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ data.name }}</span>
                            <Tag
                                v-if="!data.active"
                                value="Inactive"
                                severity="secondary"
                                size="small"
                            />
                        </div>
                        <div v-if="data.description" class="text-sm text-surface-600 mt-1">
                            {{ data.description }}
                        </div>
                    </template>
                </Column>

                <Column field="type" header="Type" style="min-width: 120px">
                    <template #body="{ data }">
                        <Tag
                            :value="getAccountTypeBadge(data.type).label"
                            :severity="getAccountTypeBadge(data.type).severity"
                        />
                    </template>
                </Column>

                <Column field="normal_balance" header="Normal Balance" style="min-width: 120px">
                    <template #body="{ data }">
                        <Tag
                            :value="data.normal_balance === 'debit' ? 'Debit' : 'Credit'"
                            :severity="data.normal_balance === 'debit' ? 'info' : 'warning'"
                        />
                    </template>
                </Column>

                <Column field="current_balance" header="Current Balance" style="min-width: 140px">
                    <template #body="{ data }">
                        <span :class="{
                            'text-green-600': data.current_balance > 0 && data.normal_balance === 'debit',
                            'text-red-600': data.current_balance < 0 && data.normal_balance === 'debit',
                            'text-red-600': data.current_balance > 0 && data.normal_balance === 'credit',
                            'text-green-600': data.current_balance < 0 && data.normal_balance === 'credit'
                        }">
                            {{ formatCurrency(data.current_balance) }}
                        </span>
                    </template>
                </Column>

                <Column field="allow_manual_entries" header="Manual Entries" style="min-width: 120px">
                    <template #body="{ data }">
                        <Tag
                            :value="data.allow_manual_entries ? 'Allowed' : 'Not Allowed'"
                            :severity="data.allow_manual_entries ? 'success' : 'danger'"
                            size="small"
                        />
                    </template>
                </Column>

                <Column header="Actions" style="min-width: 160px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                icon="pi pi-eye"
                                size="small"
                                text
                                rounded
                                @click="viewAccount(data)"
                                v-tooltip="'View Account'"
                            />
                            <Button
                                icon="pi pi-pencil"
                                size="small"
                                text
                                rounded
                                severity="secondary"
                                @click="editAccount(data)"
                                v-tooltip="'Edit Account'"
                                v-if="can.update"
                            />
                            <Button
                                :icon="data.active ? 'pi pi-ban' : 'pi pi-check'"
                                size="small"
                                text
                                rounded
                                :severity="data.active ? 'danger' : 'success'"
                                @click="toggleAccountStatus(data)"
                                v-tooltip="data.active ? 'Deactivate' : 'Activate'"
                                v-if="can.update"
                            />
                            <Button
                                icon="pi pi-trash"
                                size="small"
                                text
                                rounded
                                severity="danger"
                                @click="confirmDeleteAccount(data)"
                                v-tooltip="'Delete Account'"
                                v-if="can.delete"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Delete Account Confirmation Dialog -->
        <Dialog
            v-model:visible="deleteAccountDialog"
            :style="{ width: '450px' }"
            header="Confirm Delete"
            :modal="true"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle text-3xl text-orange-500" />
                <div v-if="accountToDelete">
                    <span v-if="accountToDelete" class="font-semibold">{{ accountToDelete.name }}</span>
                    will be permanently deleted. This action cannot be undone.
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="deleteAccountDialog = false" />
                <Button
                    label="Delete"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteAccount"
                />
            </template>
        </Dialog>
    </LayoutShell>
</template>