<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Badge from 'primevue/badge';
import Divider from 'primevue/divider';
import Dialog from 'primevue/dialog';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { useFormatting } from '@/composables/useFormatting';
import { useDeleteConfirmation } from '@/composables/useDeleteConfirmation';

const page = usePage();
const toast = useToast();
const { formatMoney, formatPercentage } = useFormatting();
const { confirmDelete } = useDeleteConfirmation();

// Data
const currencies = ref([]);
const availableCurrencies = ref([]);
const exchangeRates = ref([]);
const allSystemCurrencies = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);

// Modals
const showAddCurrencyModal = ref(false);
const showExchangeRateModal = ref(false);
const showSystemCurrenciesModal = ref(false);

// Forms
const selectedCurrency = ref(null);
const exchangeRateForm = ref({
    id: null,
    from_currency_id: null,
    to_currency_id: null,
    rate: null,
    date: new Date().toISOString().split('T')[0]
});

// Permissions
const canView = computed(() => 
    page.props.auth.can?.currency?.view ?? false
);

const canEditCompany = computed(() => 
    page.props.auth.can?.currency?.companyEdit ?? false
);

const canManageSystem = computed(() => 
    page.props.auth.can?.currency?.systemManage ?? false
);

const canEditExchange = computed(() => 
    page.props.auth.can?.currency?.exchangeEdit ?? false
);

const canSetDefaults = computed(() => 
    page.props.auth.can?.currency?.defaultSet ?? false
);

const canCrud = computed(() => 
    page.props.auth.can?.currency?.crud ?? false
);

// Company data
const currentCompany = computed(() => page.props.auth?.currentCompany);
const baseCurrency = computed(() => currentCompany.value?.base_currency);

// Fetch company currencies
const fetchCompanyCurrencies = async () => {
    if (!currentCompany.value?.id || !canView.value) return;
    
    try {
        loading.value = true;
        const response = await router.get(`/api/companies/${currentCompany.value.id}/currencies`);
        currencies.value = response.data.data;
    } catch (err) {
        error.value = 'Failed to load currencies';
        console.error('Error loading currencies:', err);
    } finally {
        loading.value = false;
    }
};

// Fetch available currencies
const fetchAvailableCurrencies = async () => {
    if (!currentCompany.value?.id || !canEditCompany.value) return;
    
    try {
        const response = await router.get(`/api/companies/${currentCompany.value.id}/currencies/available`);
        availableCurrencies.value = response.data.data;
    } catch (err) {
        console.error('Error loading available currencies:', err);
    }
};

// Fetch exchange rates
const fetchExchangeRates = async () => {
    if (!currentCompany.value?.id || !canView.value) return;
    
    try {
        const response = await router.get(`/api/companies/${currentCompany.value.id}/currencies/exchange-rates`);
        exchangeRates.value = response.data.data || [];
    } catch (err) {
        console.error('Error loading exchange rates:', err);
    }
};

// Fetch all system currencies (for system admins)
const fetchSystemCurrencies = async () => {
    if (!canManageSystem.value && !canCrud.value) return;
    
    try {
        const response = await router.get('/api/currencies');
        allSystemCurrencies.value = response.data.data;
    } catch (err) {
        console.error('Error loading system currencies:', err);
    }
};

// Add currency to company
const addCurrency = async (currencyId) => {
    if (!currentCompany.value?.id || !canEditCompany.value) return;
    
    try {
        saving.value = true;
        await router.post(`/api/companies/${currentCompany.value.id}/currencies`, {
            currency_id: currencyId
        });
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Currency added successfully',
            life: 3000
        });
        
        await fetchCompanyCurrencies();
        await fetchAvailableCurrencies();
        showAddCurrencyModal.value = false;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to add currency',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Remove currency from company
const removeCurrency = async (currency) => {
    if (!canEditCompany.value) return;
    
    const confirmed = await confirmDelete({
        title: 'Remove Currency',
        message: `Are you sure you want to remove ${currency.name} from your company?`,
        confirmText: 'Remove',
        type: 'warning'
    });
    
    if (!confirmed) return;
    
    try {
        saving.value = true;
        await router.delete(`/api/companies/${currentCompany.value.id}/currencies/${currency.id}`);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Currency removed successfully',
            life: 3000
        });
        
        await fetchCompanyCurrencies();
        await fetchAvailableCurrencies();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to remove currency',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Set as base currency
const setBaseCurrency = async (currency) => {
    if (!canSetDefaults.value) return;
    
    try {
        saving.value = true;
        await router.patch(`/api/companies/${currentCompany.value.id}/base-currency`, {
            currency_id: currency.id
        });
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Base currency updated successfully',
            life: 3000
        });
        
        // Refresh page to get updated company data
        router.reload();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update base currency',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Update exchange rate
const updateExchangeRate = async () => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        const url = exchangeRateForm.value.id 
            ? `/api/companies/${currentCompany.value.id}/currencies/exchange-rates/${exchangeRateForm.value.id}`
            : `/api/companies/${currentCompany.value.id}/currencies/exchange-rates`;
        
        await router.post(url, exchangeRateForm.value);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Exchange rate updated successfully',
            life: 3000
        });
        
        await fetchExchangeRates();
        showExchangeRateModal.value = false;
        resetExchangeRateForm();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update exchange rate',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Reset exchange rate form
const resetExchangeRateForm = () => {
    exchangeRateForm.value = {
        from_currency_id: baseCurrency.value?.id,
        to_currency_id: null,
        rate: null,
        date: new Date().toISOString().split('T')[0]
    };
};

// System currency management (Super Admin)
const toggleCurrencyStatus = async (currency) => {
    if (!canCrud.value) return;
    
    try {
        await router.patch(`/api/currencies/${currency.id}/toggle-active`);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Currency ${currency.active ? 'disabled' : 'enabled'} successfully`,
            life: 3000
        });
        
        await fetchSystemCurrencies();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update currency status',
            life: 3000
        });
    }
};

// Sync exchange rates from external source
const syncExchangeRates = async () => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        const response = await router.post(`/api/companies/${currentCompany.value.id}/currencies/exchange-rates/sync`);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: response.data.message || 'Exchange rates synchronized successfully',
            life: 3000
        });
        
        await fetchExchangeRates();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to sync exchange rates',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Initialize
onMounted(async () => {
    if (canView.value) {
        await fetchCompanyCurrencies();
        await fetchExchangeRates();
    }
    
    if (canEditCompany.value) {
        await fetchAvailableCurrencies();
    }
    
    if (canManageSystem.value || canCrud.value) {
        await fetchSystemCurrencies();
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Error Message -->
        <Message v-if="error" severity="error" :closable="false">
            {{ error }}
        </Message>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-8">
            <ProgressSpinner />
        </div>

        <!-- Company Currencies Section -->
        <Card v-if="canView">
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Company Currencies</span>
                    <Button
                        v-if="canEditCompany"
                        label="Add Currency"
                        icon="plus"
                        size="small"
                        @click="showAddCurrencyModal = true"
                    />
                </div>
            </template>
            <template #content>
                <div v-if="currencies.length === 0" class="text-center py-8 text-gray-500">
                    No currencies configured for your company
                </div>
                
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="currency in currencies"
                        :key="currency.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <div class="text-lg font-semibold">{{ currency.name }}</div>
                                <div class="text-sm text-gray-500">{{ currency.code }}</div>
                            </div>
                            <Badge
                                v-if="currency.id === baseCurrency?.id"
                                value="Base"
                                severity="success"
                                size="small"
                            />
                        </div>
                        
                        <div class="text-sm text-gray-600 mb-3">
                            Symbol: {{ currency.symbol }}
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                <span v-if="currency.exchange_rate" class="text-green-600">
                                    1 {{ baseCurrency?.code }} = {{ currency.exchange_rate }} {{ currency.code }}
                                </span>
                                <span v-else class="text-gray-400">
                                    No exchange rate
                                </span>
                            </div>
                            
                            <div class="flex gap-2">
                                <Button
                                    v-if="canSetDefaults && currency.id !== baseCurrency?.id"
                                    icon="check"
                                    size="small"
                                    text
                                    v-tooltip="'Set as base currency'"
                                    @click="setBaseCurrency(currency)"
                                />
                                <Button
                                    v-if="canEditCompany"
                                    icon="trash"
                                    size="small"
                                    text
                                    severity="danger"
                                    v-tooltip="'Remove currency'"
                                    @click="removeCurrency(currency)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Exchange Rates Section -->
        <Card v-if="canView && canEditExchange">
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Exchange Rates</span>
                    <div class="flex gap-2">
                        <Button
                            v-if="canEditExchange"
                            label="Sync Rates"
                            icon="sync"
                            size="small"
                            :loading="saving"
                            @click="syncExchangeRates"
                        />
                        <Button
                            label="Update Rate"
                            icon="refresh"
                            size="small"
                            @click="
                                showExchangeRateModal = true;
                                resetExchangeRateForm();
                            "
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="exchangeRates"
                    :paginator="exchangeRates.length > 10"
                    :rows="10"
                    stripedRows
                    responsiveLayout="scroll"
                >
                    <Column field="from_currency.code" header="From" />
                    <Column field="to_currency.code" header="To" />
                    <Column field="rate" header="Rate">
                        <template #body="{ data }">
                            {{ formatMoney(data.rate, { currency: '' }) }}
                        </template>
                    </Column>
                    <Column field="date" header="Date">
                        <template #body="{ data }">
                            {{ new Date(data.date).toLocaleDateString() }}
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Button
                                icon="edit"
                                size="small"
                                text
                                @click="
                                    showExchangeRateModal = true;
                                    exchangeRateForm = {
                                        id: data.id,
                                        from_currency_id: data.from_currency_id,
                                        to_currency_id: data.to_currency_id,
                                        rate: data.rate,
                                        date: data.date
                                    };
                                "
                            />
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- System Currencies Management (Admin/Super Admin) -->
        <Card v-if="canManageSystem || canCrud">
            <template #title>
                <div class="flex items-center justify-between">
                    <span>System Currencies</span>
                    <Button
                        label="Manage System Currencies"
                        icon="cog"
                        size="small"
                        @click="showSystemCurrenciesModal = true"
                    />
                </div>
            </template>
            <template #subtitle>
                Manage all available currencies in the system
            </template>
            <template #content>
                <div class="text-sm text-gray-600">
                    Total currencies: {{ allSystemCurrencies.length }}
                </div>
            </template>
        </Card>

        <!-- Add Currency Modal -->
        <Dialog
            v-model:visible="showAddCurrencyModal"
            modal
            header="Add Currency"
            :style="{ width: '450px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Select Currency</label>
                    <Dropdown
                        v-model="selectedCurrency"
                        :options="availableCurrencies"
                        optionLabel="name"
                        :filter="true"
                        placeholder="Select a currency"
                        class="w-full"
                    >
                        <template #option="slotProps">
                            <div class="flex items-center justify-between w-full">
                                <span>{{ slotProps.option.name }} ({{ slotProps.option.code }})</span>
                                <span class="text-gray-500">{{ slotProps.option.symbol }}</span>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showAddCurrencyModal = false"
                    />
                    <Button
                        label="Add Currency"
                        :disabled="!selectedCurrency"
                        :loading="saving"
                        @click="addCurrency(selectedCurrency.id)"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Exchange Rate Modal -->
        <Dialog
            v-model:visible="showExchangeRateModal"
            modal
            header="Update Exchange Rate"
            :style="{ width: '450px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">From Currency</label>
                    <Dropdown
                        v-model="exchangeRateForm.from_currency_id"
                        :options="currencies"
                        optionLabel="code"
                        optionValue="id"
                        placeholder="Select currency"
                        class="w-full"
                        :disabled="!!exchangeRateForm.id"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">To Currency</label>
                    <Dropdown
                        v-model="exchangeRateForm.to_currency_id"
                        :options="currencies"
                        optionLabel="code"
                        optionValue="id"
                        placeholder="Select currency"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Exchange Rate</label>
                    <InputNumber
                        v-model="exchangeRateForm.rate"
                        :minFractionDigits="6"
                        :maxFractionDigits="6"
                        placeholder="0.000000"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Date</label>
                    <Calendar
                        v-model="exchangeRateForm.date"
                        dateFormat="yy-mm-dd"
                        class="w-full"
                    />
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showExchangeRateModal = false"
                    />
                    <Button
                        label="Update Rate"
                        :disabled="!exchangeRateForm.rate"
                        :loading="saving"
                        @click="updateExchangeRate"
                    />
                </div>
            </template>
        </Dialog>

        <!-- System Currencies Modal (Super Admin) -->
        <Dialog
            v-model:visible="showSystemCurrenciesModal"
            modal
            header="Manage System Currencies"
            :style="{ width: '800px' }"
        >
            <div class="space-y-4">
                <DataTable
                    :value="allSystemCurrencies"
                    :paginator="allSystemCurrencies.length > 10"
                    :rows="10"
                    stripedRows
                    responsiveLayout="scroll"
                    v-if="canCrud"
                >
                    <Column field="code" header="Code" />
                    <Column field="name" header="Name" />
                    <Column field="symbol" header="Symbol" />
                    <Column field="active" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.active ? 'Active' : 'Inactive'"
                                :severity="data.active ? 'success' : 'danger'"
                                size="small"
                            />
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Button
                                :label="data.active ? 'Disable' : 'Enable'"
                                size="small"
                                :severity="data.active ? 'danger' : 'success'"
                                @click="toggleCurrencyStatus(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
                
                <div v-else class="text-center py-8 text-gray-500">
                    <Message severity="info">
                        You need super admin permissions to manage system currencies.
                    </Message>
                </div>
            </div>
        </Dialog>
    </div>
</template>