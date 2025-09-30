<script setup>
import { ref, computed, onMounted } from 'vue';
import { debounce } from 'lodash-es';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Badge from 'primevue/badge';
import Chip from 'primevue/chip';
import Checkbox from 'primevue/checkbox';
import Divider from 'primevue/divider';
import Dialog from 'primevue/dialog';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import SimpleExchangeRateManager from './SimpleExchangeRateManager.vue';

const page = usePage();
const toast = useToast();
const confirm = useConfirm();

// Data
const currencies = ref([]);
const availableCurrencies = ref([]);
const exchangeRates = ref([]);
const allSystemCurrencies = ref([]);

// Import data
const importSources = ref([]);
const importUpdateExisting = ref(false);
const searchQuery = ref('');
const searchTags = ref([]);
const searchResults = ref([]);
const selectedCurrencies = ref([]);
const isSearching = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);

// Modals
const showAddCurrencyModal = ref(false);
const showExchangeRateModal = ref(false);
const showSystemCurrenciesModal = ref(false);
const showNewCurrencyModal = ref(false);
const showImportModal = ref(false);

// Forms
const selectedCurrency = ref(null);
const exchangeRateForm = ref({
    id: null,
    from_currency_id: null,
    to_currency_id: null,
    rate: null,
    date: new Date().toISOString().split('T')[0]
});
const newCurrencyForm = ref({
    code: '',
    numeric_code: '',
    name: '',
    symbol: '',
    symbol_position: 'before',
    minor_unit: 2,
    thousands_separator: ',',
    decimal_separator: '.',
    exchange_rate: 1
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

const canCrud = computed(() =>
    page.props.auth.can?.currency?.crud ?? false
);

// Company data
const currentCompany = computed(() => page.props.auth?.currentCompany);
const baseCurrency = computed(() => {
    // The currentCompany.base_currency is a string (e.g., 'PKR')
    // If we need to find the full currency object, we can search the currencies array
    const baseCurrencyCode = currentCompany.value?.base_currency;

    if (baseCurrencyCode && currencies.value.length > 0) {
        // Try to find the full currency object
        const baseCurrencyObj = currencies.value.find(c =>
            (c.code || c.currency_code || c.id) === baseCurrencyCode
        );
        return baseCurrencyObj || { code: baseCurrencyCode };
    }

    return { code: baseCurrencyCode };
});

// Compute currencies with exchange rates
const currenciesWithRates = computed(() => {
    return currencies.value.map(currency => {
        // The currency object has a nested structure: currency.currency.code
        const currencyCode = currency.currency?.code || currency.code || currency.currency_code || currency.id;
        const baseCurrencyCode = baseCurrency.value?.code || baseCurrency.value;

        const exchangeRate = exchangeRates.value.find(
            rate => rate.to_currency === currencyCode &&
                   rate.from_currency === baseCurrencyCode
        );

        return {
            ...currency,
            exchange_rate: exchangeRate?.rate || null
        };
    });
});

// Fetch company currencies
const fetchCompanyCurrencies = async () => {
    if (!currentCompany.value?.id || !canView.value) return;

    try {
        loading.value = true;
        const response = await http.get(`/api/companies/${currentCompany.value.id}/currencies`);

        // The API returns an array of currencies
        const currenciesArray = response.data.data;

        // The company's base currency is available from the company data
        const companyBaseCurrency = currentCompany.value.base_currency;

        // Process the currencies array
        currencies.value = currenciesArray.map(currency => ({
            ...currency,
            // Preserve the UUID if it exists, otherwise use the code
            id: currency.id || currency.currency?.id || currency.code,
            is_base: currency.code === companyBaseCurrency
        }));
    } catch (err) {
        error.value = 'Failed to load currencies';
    } finally {
        loading.value = false;
    }
};

// Fetch available currencies
const fetchAvailableCurrencies = async () => {
    if (!currentCompany.value?.id || !canEditCompany.value) return;

    try {
        const response = await http.get(`/api/companies/${currentCompany.value.id}/currencies/available`);
        availableCurrencies.value = response.data.data;
    } catch (err) {
      }
};

// Fetch exchange rates
const fetchExchangeRates = async () => {
    if (!currentCompany.value?.id || !canView.value) return;

    try {
        const response = await http.get('/api/currencies/latest-exchange-rates');
        const data = response.data.data;

        // Convert the rates object to an array for easier processing
        if (data && data.rates) {
            exchangeRates.value = Object.entries(data.rates).map(([toCurrency, rateData]) => ({
                from_currency: data.base_currency,
                to_currency: toCurrency,
                rate: rateData.rate,
                inverse_rate: rateData.inverse_rate
            }));
        } else {
            exchangeRates.value = [];
        }
    } catch (err) {
        exchangeRates.value = [];
    }
};

// Import currencies methods
const fetchImportSources = async () => {
    try {
        const response = await http.get('/api/currencies/import/sources');
        importSources.value = response.data;
    } catch (err) {
        console.error('Error fetching import sources:', err);
    }
};


// Search currencies from external source
const performSearch = async () => {
    const allSearchTerms = [...searchTags.value];
    if (searchQuery.value.trim()) {
        allSearchTerms.push(searchQuery.value.trim());
    }

    if (allSearchTerms.length === 0) {
        searchResults.value = [];
        return;
    }

    try {
        isSearching.value = true;

        // Search for each term and combine results
        const allResults = [];
        const uniqueCurrencies = new Map();

        for (const term of allSearchTerms) {
            if (term.length < 2) continue;

            const response = await http.get('/api/currencies/import/search', {
                params: {
                    query: term,
                    source: 'ecb'
                }
            });

            const currencies = response.data.data.currencies || [];
            currencies.forEach(currency => {
                if (!uniqueCurrencies.has(currency.code)) {
                    uniqueCurrencies.set(currency.code, currency);
                    allResults.push(currency);
                }
            });
        }

        searchResults.value = allResults;
    } catch (err) {
        console.error('Error searching currencies:', err);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to search currencies',
            life: 3000
        });
    } finally {
        isSearching.value = false;
    }
};

// Debounced search to avoid too many API calls
const searchCurrencies = debounce(performSearch, 500);

// Import selected currencies
const importSelectedCurrencies = async () => {
    if (selectedCurrencies.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Selection',
            detail: 'Please select at least one currency to import',
            life: 3000
        });
        return;
    }

    // Check for duplicates
    const existingCurrencyCodes = new Set(currencies.value.map(c => c.code));
    const duplicateCurrencies = selectedCurrencies.value.filter(currency =>
        existingCurrencyCodes.has(currency.code)
    );

    if (duplicateCurrencies.length > 0) {
        const duplicateNames = duplicateCurrencies.map(c => `${c.name} (${c.code})`).join(', ');
        toast.add({
            severity: 'warn',
            summary: 'Duplicate Currencies',
            detail: `The following currencies already exist in the system: ${duplicateNames}`,
            life: 5000
        });
        return;
    }

    try {
        saving.value = true;
        const response = await http.post('/api/currencies/import/specific', {
            source: 'ecb',
            currency_codes: selectedCurrencies.value.map(c => c.code),
            update_existing: importUpdateExisting.value
        });

        toast.add({
            severity: 'success',
            summary: 'Import Successful',
            detail: `Created ${response.data.data.created}, updated ${response.data.data.updated}, skipped ${response.data.data.skipped} currencies`,
            life: 5000
        });

        // Reset search and selection
        searchQuery.value = '';
        searchTags.value = [];
        searchResults.value = [];
        selectedCurrencies.value = [];
        await fetchSystemCurrencies();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to import currencies',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Handle search input key events
const handleSearchKeydown = (event) => {
    if (event.key === ',' && searchQuery.value.trim()) {
        event.preventDefault();
        const tag = searchQuery.value.trim();
        if (!searchTags.value.includes(tag)) {
            searchTags.value.push(tag);
            searchQuery.value = '';
            performSearch();
        }
    } else if (event.key === 'Backspace' && !searchQuery.value && searchTags.value.length > 0) {
        searchTags.value.pop();
        performSearch();
    }
};

// Remove a search tag
const removeSearchTag = (index) => {
    searchTags.value.splice(index, 1);
    performSearch();
};

// Fetch all system currencies (for system admins)
const fetchSystemCurrencies = async () => {
    if (!canManageSystem.value && !canCrud.value) return;

    try {
        const response = await http.get('/api/currencies');
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
        await http.post(`/api/companies/${currentCompany.value.id}/currencies`, {
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

    // Get the correct currency ID - it could be in different locations
    const currencyId = currency.id || currency.currency?.id;
    const currencyName = currency.currency?.name || currency.name;

    if (!currencyId) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Currency ID not found',
            life: 3000
        });
        return;
    }

    confirm.require({
        message: `Are you sure you want to remove ${currencyName} from your company?`,
        header: 'Remove Currency',
        icon: 'fa-solid fa-triangle-exclamation',
        acceptLabel: 'Remove',
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                saving.value = true;
                await http.delete(`/api/companies/${currentCompany.value.id}/currencies/${currencyId}`);

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
        }
    });
};

// Base currency cannot be changed until migration wizard is implemented
// TODO: Implement migration wizard for base currency changes

// Update exchange rate
const updateExchangeRate = async () => {
    if (!canEditExchange.value) return;

    try {
        saving.value = true;
        const url = exchangeRateForm.value.id
            ? `/api/companies/${currentCompany.value.id}/currencies/exchange-rates/${exchangeRateForm.value.id}`
            : `/api/companies/${currentCompany.value.id}/currencies/exchange-rates`;

        await http.post(url, exchangeRateForm.value);

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
        await http.patch(`/api/currencies/${currency.id}/toggle-active`);

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


// Create new currency
const createNewCurrency = async () => {
    if (!canCrud.value) return;

    try {
        saving.value = true;
        await http.post('/api/currencies', newCurrencyForm.value);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Currency created successfully',
            life: 3000
        });

        await fetchSystemCurrencies();
        showNewCurrencyModal.value = false;
        resetNewCurrencyForm();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to create currency',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Reset new currency form
const resetNewCurrencyForm = () => {
    newCurrencyForm.value = {
        code: '',
        numeric_code: '',
        name: '',
        symbol: '',
        symbol_position: 'before',
        minor_unit: 2,
        thousands_separator: ',',
        decimal_separator: '.',
        exchange_rate: 1
    };
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
        await fetchImportSources();
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
                        size="small"
                        @click="showAddCurrencyModal = true"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="plus" />
                        </template>
                    </Button>
                </div>
            </template>
            <template #content>
                <div v-if="currenciesWithRates.length === 0" class="text-center py-12">
                    <div class="mb-4">
                        <FontAwesomeIcon
                            icon="coins"
                            class="text-6xl text-gray-300"
                        />
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">
                        No currencies configured for your company
                    </h3>
                    <p class="text-gray-500 mb-6">
                        Add currencies to manage transactions and view exchange rates
                    </p>
                    <Button
                        v-if="canEditCompany"
                        label="Add Your First Currency"
                        icon="pi pi-plus"
                        @click="showAddCurrencyModal = true"
                    />
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="currency in currenciesWithRates"
                        :key="currency.id || currency.currency?.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <div class="text-lg font-semibold">{{ currency.currency?.name || currency.name }}</div>
                                <div class="text-sm text-gray-500">{{ currency.currency?.code || currency.code }}</div>
                            </div>
                            <Badge
                                v-if="(currency.currency?.code || currency.code) === baseCurrency?.code"
                                value="Base"
                                severity="success"
                                size="small"
                            />
                        </div>

                        <div class="text-sm text-gray-600 mb-3">
                            Symbol: {{ currency.currency?.symbol || currency.symbol }}
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                <span v-if="currency.exchange_rate" class="text-green-600">
                                    1 {{ baseCurrency?.code }} = {{ currency.exchange_rate }} {{ currency.currency?.code || currency.code }}
                                </span>
                                <span v-else class="text-gray-400">
                                    No exchange rate
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    v-if="canEditCompany"
                                    size="small"
                                    text
                                    severity="danger"
                                    v-tooltip="'Remove currency'"
                                    @click="removeCurrency(currency)"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="trash" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Exchange Rates Section -->
        <SimpleExchangeRateManager
            v-if="canView"
            :currencies="currencies"
            :base-currency="baseCurrency"
            :exchange-rates="exchangeRates"
            :can-edit-exchange="canEditExchange"
            :can-manage-system="canManageSystem"
            :can-manage-company="canManageCompany"
            :currency-precision="currencyPrecision"
            @refresh="fetchExchangeRates"
        />

        <!-- System Currencies Management (Admin/Super Admin) -->
        <Card v-if="canManageSystem || canCrud">
            <template #title>
                <div class="flex items-center justify-between">
                    <span>System Currencies</span>
                    <div class="flex gap-2">
                        <Button
                            label="Import Currencies"
                            size="small"
                            @click="showImportModal = true"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="download" />
                            </template>
                        </Button>
                        <Button
                            label="Manage System Currencies"
                            size="small"
                            @click="showSystemCurrenciesModal = true"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="cog" />
                            </template>
                        </Button>
                    </div>
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
                        :options="allSystemCurrencies"
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
                        :options="allSystemCurrencies"
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
                <div class="flex justify-end" v-if="canCrud">
                    <Button
                        label="Add New Currency"
                        size="small"
                        @click="showNewCurrencyModal = true"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="plus" />
                        </template>
                    </Button>
                </div>
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

        <!-- New Currency Modal -->
        <Dialog
            v-model:visible="showNewCurrencyModal"
            modal
            header="Add New Currency"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Currency Code *</label>
                        <InputText
                            v-model="newCurrencyForm.code"
                            placeholder="USD"
                            class="w-full"
                            maxlength="3"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Numeric Code *</label>
                        <InputNumber
                            v-model="newCurrencyForm.numeric_code"
                            placeholder="840"
                            class="w-full"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Currency Name *</label>
                    <InputText
                        v-model="newCurrencyForm.name"
                        placeholder="US Dollar"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Symbol *</label>
                    <InputText
                        v-model="newCurrencyForm.symbol"
                        placeholder="$"
                        class="w-full"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Symbol Position</label>
                        <Dropdown
                            v-model="newCurrencyForm.symbol_position"
                            :options="[
                                { label: 'Before (e.g. $100)', value: 'before' },
                                { label: 'After (e.g. 100$)', value: 'after' }
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Minor Units</label>
                        <InputNumber
                            v-model="newCurrencyForm.minor_unit"
                            :min="0"
                            :max="4"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Thousands Separator</label>
                        <InputText
                            v-model="newCurrencyForm.thousands_separator"
                            placeholder=","
                            class="w-full"
                            maxlength="5"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Decimal Separator</label>
                        <InputText
                            v-model="newCurrencyForm.decimal_separator"
                            placeholder="."
                            class="w-full"
                            maxlength="5"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Exchange Rate (to USD) *</label>
                    <InputNumber
                        v-model="newCurrencyForm.exchange_rate"
                        :min="0"
                        :minFractionDigits="6"
                        class="w-full"
                    />
                    <small class="text-gray-500">Rate relative to USD (base currency)</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showNewCurrencyModal = false"
                    />
                    <Button
                        label="Create Currency"
                        :disabled="!newCurrencyForm.code || !newCurrencyForm.name || !newCurrencyForm.symbol"
                        :loading="saving"
                        @click="createNewCurrency"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Import Currencies Modal -->
        <Dialog
            v-model:visible="showImportModal"
            modal
            header="Import Currencies from European Central Bank"
            :style="{ width: '900px' }"
            @hide="searchQuery = ''; searchTags = []; searchResults.value = []; selectedCurrencies.value = []"
        >
            <div class="space-y-4">
                <!-- Source Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Source:</strong> European Central Bank via exchangerate.host
                    </p>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        Free service providing exchange rates for major world currencies
                    </p>
                </div>

                <!-- Search Section -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium">Search Currencies</label>

                    <!-- Search Tags -->
                    <div v-if="searchTags.length > 0" class="flex flex-wrap gap-2">
                        <Chip
                            v-for="(tag, index) in searchTags"
                            :key="index"
                            :label="tag"
                            removable
                            @remove="removeSearchTag(index)"
                            class="bg-blue-100 text-blue-800"
                        />
                    </div>

                    <div class="relative">
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search by currency name, code, or symbol... (Press comma to add as tag)"
                            class="w-full pr-10"
                            @input="searchCurrencies"
                            @keydown="handleSearchKeydown"
                        />
                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                            <ProgressSpinner
                                v-if="isSearching"
                                style="width: 20px; height: 20px"
                                stroke-width="3"
                            />
                            <FontAwesomeIcon
                                v-else
                                icon="search"
                                class="text-gray-400"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        Type at least 2 characters to search. Press comma to create multiple search tags.
                    </p>
                </div>

                <!-- Search Results -->
                <div v-if="searchResults.length > 0" class="space-y-3">
                    <Divider />
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Search Results</h3>
                        <span class="text-sm text-gray-500">
                            {{ selectedCurrencies.length }} of {{ searchResults.length }} selected
                        </span>
                    </div>

                    <!-- Currency List -->
                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <DataTable
                            :value="searchResults"
                            stripedRows
                            responsiveLayout="scroll"
                            selectionMode="multiple"
                            v-model:selection="selectedCurrencies"
                        >
                            <Column selectionMode="multiple" style="width: 50px" />
                            <Column field="code" header="Code" style="width: 80px" />
                            <Column field="numeric_code" header="Numeric Code" style="width: 100px" />
                            <Column field="name" header="Name" />
                            <Column field="symbol" header="Symbol" style="width: 80px" />
                            <Column field="exchange_rate" header="Exchange Rate" style="width: 120px">
                                <template #body="{ data }">
                                    {{ data.exchange_rate?.toFixed(6) || 'N/A' }}
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Import Options -->
                <div v-if="selectedCurrencies.length > 0" class="space-y-3">
                    <Divider />
                    <div>
                        <label class="flex items-center gap-2">
                            <Checkbox
                                v-model="importUpdateExisting"
                                binary
                            />
                            <span class="text-sm">Update existing currencies</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500">
                            When enabled, existing currencies will be updated with new exchange rates and information
                        </p>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between items-center">
                    <div v-if="selectedCurrencies.length > 0" class="text-sm text-gray-600">
                        {{ selectedCurrencies.length }} currency{{ selectedCurrencies.length > 1 ? 'ies' : 'y' }} selected
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            outlined
                            @click="showImportModal = false"
                        />
                        <Button
                            label="Import Selected"
                            severity="success"
                            :disabled="selectedCurrencies.length === 0"
                            :loading="saving"
                            @click="importSelectedCurrencies"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="download" />
                            </template>
                        </Button>
                    </div>
                </div>
            </template>
        </Dialog>
    </div>
</template>
