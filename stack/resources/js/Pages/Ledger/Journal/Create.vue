<script setup>
import { ref, computed, reactive } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Calendar from 'primevue/calendar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputNumber from 'primevue/inputnumber'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import { route } from 'ziggy-js'

const props = defineProps({
    accounts: Array,
    batches: Array,
    currencies: Array,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

// Form setup
const form = useForm({
    date: new Date().toISOString().split('T')[0],
    reference: '',
    description: '',
    batch_id: null,
    currency: 'USD',
    journal_lines: [
        {
            account_id: null,
            description: '',
            debit: 0,
            credit: 0
        }
    ]
})

// State management
const expandedRows = ref([])
const accountSearchQuery = ref('')
const filteredAccounts = ref([])

// Computed properties
const totalDebits = computed(() => {
    return form.journal_lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0)
})

const totalCredits = computed(() => {
    return form.journal_lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0)
})

const isBalanced = computed(() => {
    return Math.abs(totalDebits.value - totalCredits.value) < 0.01
})

const canSubmit = computed(() => {
    return form.journal_lines.length >= 2 &&
           isBalanced.value &&
           totalDebits.value > 0 &&
           form.journal_lines.every(line => line.account_id && (line.debit > 0 || line.credit > 0))
})

// Methods
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0)
}

const searchAccounts = (event) => {
    const query = event.query.toLowerCase()
    filteredAccounts.value = props.accounts.filter(account =>
        account.name.toLowerCase().includes(query) ||
        account.code.toLowerCase().includes(query)
    )
}

const addJournalLine = () => {
    form.journal_lines.push({
        account_id: null,
        description: '',
        debit: 0,
        credit: 0
    })
}

const removeJournalLine = (index) => {
    if (form.journal_lines.length > 1) {
        form.journal_lines.splice(index, 1)
    }
}

const handleAccountChange = (index, account) => {
    form.journal_lines[index].account_id = account ? account.id : null
}

const autoBalance = () => {
    const difference = totalDebits.value - totalCredits.value
    if (Math.abs(difference) > 0.01) {
        // Find the last empty line to auto-balance
        const lastLineIndex = form.journal_lines.length - 1
        if (!form.journal_lines[lastLineIndex].account_id) {
            if (difference > 0) {
                form.journal_lines[lastLineIndex].credit = difference
                form.journal_lines[lastLineIndex].debit = 0
            } else {
                form.journal_lines[lastLineIndex].debit = Math.abs(difference)
                form.journal_lines[lastLineIndex].credit = 0
            }
        }
    }
}

const generateReference = () => {
    const date = new Date()
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0')
    form.reference = `JE${year}${month}${day}${random}`
}

const submitForm = () => {
    form.post(route('ledger.journal.store'), {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Journal entry created successfully',
                life: 3000
            })
            router.visit(route('ledger.journal.index'))
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to create journal entry',
                life: 3000
            })
        }
    })
}

const saveAsDraft = () => {
    form.transform(data => ({
        ...data,
        status: 'draft'
    })).post(route('ledger.journal.store'), {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Journal entry saved as draft',
                life: 3000
            })
            router.visit(route('ledger.journal.index'))
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to save journal entry',
                life: 3000
            })
        }
    })
}

const goBack = () => {
    router.visit(route('ledger.journal.index'))
}

// Initialize reference on mount
generateReference()
</script>

<template>
    <LayoutShell>
        <Head title="Create Journal Entry" />

        <UniversalPageHeader
            title="Create Journal Entry"
            subtitle="Create a new double-entry journal entry"
            :show-actions="false"
        />

        <Toast />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2">
                <Card>
                    <template #content>
                        <form @submit.prevent="submitForm" class="space-y-6">
                            <!-- Entry Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="date" class="block text-sm font-medium mb-2">Date *</label>
                                    <Calendar
                                        id="date"
                                        v-model="form.date"
                                        dateFormat="yy-mm-dd"
                                        :showIcon="true"
                                        class="w-full"
                                        required
                                    />
                                    <Message v-if="form.errors.date" severity="error" :closable="false">
                                        {{ form.errors.date }}
                                    </Message>
                                </div>
                                <div>
                                    <label for="reference" class="block text-sm font-medium mb-2">Reference</label>
                                    <div class="flex gap-2">
                                        <InputText
                                            id="reference"
                                            v-model="form.reference"
                                            placeholder="Auto-generated"
                                            class="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            icon="pi pi-refresh"
                                            @click="generateReference"
                                            v-tooltip="'Generate Reference'"
                                        />
                                    </div>
                                    <Message v-if="form.errors.reference" severity="error" :closable="false">
                                        {{ form.errors.reference }}
                                    </Message>
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium mb-2">Description *</label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Enter journal entry description"
                                    class="w-full"
                                    required
                                />
                                <Message v-if="form.errors.description" severity="error" :closable="false">
                                    {{ form.errors.description }}
                                </Message>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="batch" class="block text-sm font-medium mb-2">Batch</label>
                                    <Dropdown
                                        id="batch"
                                        v-model="form.batch_id"
                                        :options="batches"
                                        optionLabel="name"
                                        optionValue="id"
                                        placeholder="Select batch (optional)"
                                        class="w-full"
                                        showClear
                                    />
                                    <Message v-if="form.errors.batch_id" severity="error" :closable="false">
                                        {{ form.errors.batch_id }}
                                    </Message>
                                </div>
                                <div>
                                    <label for="currency" class="block text-sm font-medium mb-2">Currency</label>
                                    <Dropdown
                                        id="currency"
                                        v-model="form.currency"
                                        :options="currencies"
                                        optionLabel="name"
                                        optionValue="code"
                                        placeholder="Select currency"
                                        class="w-full"
                                    />
                                    <Message v-if="form.errors.currency" severity="error" :closable="false">
                                        {{ form.errors.currency }}
                                    </Message>
                                </div>
                            </div>

                            <!-- Journal Lines -->
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">Journal Lines</h3>
                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            icon="pi pi-plus"
                                            label="Add Line"
                                            size="small"
                                            @click="addJournalLine"
                                        />
                                        <Button
                                            type="button"
                                            icon="pi pi-calculator"
                                            label="Auto Balance"
                                            size="small"
                                            severity="secondary"
                                            @click="autoBalance"
                                            v-tooltip="`Balance: ${formatCurrency(totalDebits - totalCredits)}`"
                                        />
                                    </div>
                                </div>

                                <DataTable
                                    :value="form.journal_lines"
                                    responsiveLayout="scroll"
                                    :row-hover="true"
                                    dataKey="index"
                                    :showHeaders="true"
                                >
                                    <Column header="Account" style="min-width: 300px">
                                        <template #body="{ data, index }">
                                            <Dropdown
                                                v-model="data.account_id"
                                                :options="accounts"
                                                optionLabel="name"
                                                optionValue="id"
                                                placeholder="Select account"
                                                class="w-full"
                                                :filter="true"
                                                :virtualScrollerOptions="{ itemSize: 38 }"
                                            >
                                                <template #option="slotProps">
                                                    <div>
                                                        <span class="font-mono text-sm">{{ slotProps.option.code }}</span>
                                                        <span class="ml-2">{{ slotProps.option.name }}</span>
                                                    </div>
                                                </template>
                                            </Dropdown>
                                        </template>
                                    </Column>

                                    <Column header="Description" style="min-width: 200px">
                                        <template #body="{ data, index }">
                                            <InputText
                                                v-model="data.description"
                                                placeholder="Line description"
                                                class="w-full"
                                            />
                                        </template>
                                    </Column>

                                    <Column header="Debit" style="min-width: 120px">
                                        <template #body="{ data, index }">
                                            <InputNumber
                                                v-model="data.debit"
                                                mode="currency"
                                                currency="USD"
                                                locale="en-US"
                                                :min="0"
                                                :step="0.01"
                                                class="w-full"
                                                @input="data.credit = 0"
                                            />
                                        </template>
                                    </Column>

                                    <Column header="Credit" style="min-width: 120px">
                                        <template #body="{ data, index }">
                                            <InputNumber
                                                v-model="data.credit"
                                                mode="currency"
                                                currency="USD"
                                                locale="en-US"
                                                :min="0"
                                                :step="0.01"
                                                class="w-full"
                                                @input="data.debit = 0"
                                            />
                                        </template>
                                    </Column>

                                    <Column header="Actions" style="min-width: 80px">
                                        <template #body="{ data, index }">
                                            <Button
                                                icon="pi pi-trash"
                                                size="small"
                                                text
                                                rounded
                                                severity="danger"
                                                @click="removeJournalLine(index)"
                                                :disabled="form.journal_lines.length === 1"
                                                v-tooltip="'Remove Line'"
                                            />
                                        </template>
                                    </Column>
                                </DataTable>

                                <Message v-if="form.errors.journal_lines" severity="error" :closable="false">
                                    {{ form.errors.journal_lines }}
                                </Message>
                            </div>

                            <!-- Balance Check -->
                            <div class="flex justify-between items-center p-4 border rounded-lg">
                                <div>
                                    <div class="text-sm text-surface-600">Total Debits</div>
                                    <div class="text-xl font-bold text-green-600">{{ formatCurrency(totalDebits) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm text-surface-600 mb-2">Balance Status</div>
                                    <Tag
                                        :value="isBalanced ? 'Balanced' : 'Not Balanced'"
                                        :severity="isBalanced ? 'success' : 'danger'"
                                        size="large"
                                    />
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-surface-600">Total Credits</div>
                                    <div class="text-xl font-bold text-red-600">{{ formatCurrency(totalCredits) }}</div>
                                </div>
                            </div>

                            <Message
                                v-if="!isBalanced"
                                severity="warn"
                                :closable="false"
                            >
                                Journal entries must balance. Difference: {{ formatCurrency(totalDebits - totalCredits) }}
                            </Message>

                            <!-- Action Buttons -->
                            <div class="flex justify-between">
                                <div>
                                    <Button
                                        type="button"
                                        label="Cancel"
                                        icon="pi pi-times"
                                        severity="secondary"
                                        @click="goBack"
                                    />
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        label="Save as Draft"
                                        icon="pi pi-save"
                                        severity="secondary"
                                        @click="saveAsDraft"
                                        :disabled="form.processing"
                                    />
                                    <Button
                                        type="submit"
                                        label="Create Entry"
                                        icon="pi pi-check"
                                        :disabled="!canSubmit || form.processing"
                                        :loading="form.processing"
                                    />
                                </div>
                            </div>
                        </form>
                    </template>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Quick Tips -->
                <Card class="mb-4">
                    <template #title>
                        <i class="pi pi-info-circle mr-2"></i>
                        Quick Tips
                    </template>
                    <template #content>
                        <div class="space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <i class="pi pi-check text-green-500 mt-1"></i>
                                <span>Every journal entry must have at least 2 lines</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="pi pi-check text-green-500 mt-1"></i>
                                <span>Total debits must equal total credits</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="pi pi-check text-green-500 mt-1"></i>
                                <span>Each line must have either a debit OR credit amount</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="pi pi-check text-green-500 mt-1"></i>
                                <span>Use the Auto Balance button to balance the entry</span>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Account Types Reference -->
                <Card>
                    <template #title>
                        <i class="pi pi-list mr-2"></i>
                        Account Types
                    </template>
                    <template #content>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-green-600 font-semibold">Assets</span>
                                <span>Normal Balance: Debit</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-red-600 font-semibold">Liabilities</span>
                                <span>Normal Balance: Credit</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-600 font-semibold">Equity</span>
                                <span>Normal Balance: Credit</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-green-600 font-semibold">Revenue</span>
                                <span>Normal Balance: Credit</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-red-600 font-semibold">Expenses</span>
                                <span>Normal Balance: Debit</span>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </LayoutShell>
</template>