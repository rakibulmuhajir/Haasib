<script setup>
import { ref, onMounted, computed } from 'vue';
import { http } from '@/lib/http';
import { usePage } from '@inertiajs/vue3';
import InlineEditable from '@/Components/InlineEditable.vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Checkbox from 'primevue/checkbox';

const page = usePage();
const currencies = ref([]);
const availableCurrencies = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const showExchangeRateModal = ref(false);
const showExchangeRateHistoryModal = ref(false);
const fromHistoryModal = ref(false);
const selectedCurrency = ref(null);
const exchangeRateForm = ref({
    exchange_rate: null,
    effective_date: null,
    cease_date: null,
    notes: '',
    is_default: false
});

// Get current company from page props
const currentCompany = computed(() => page.props.auth?.currentCompany);

const fetchCompanyCurrencies = async () => {
    if (!currentCompany.value?.id) return;
    
    try {
        loading.value = true;
        const response = await http.get(`/api/companies/${currentCompany.value.id}/currencies`);
        currencies.value = response.data.data;
    } catch (err) {
        error.value = 'Failed to load currencies';
        console.error('Error loading currencies:', err);
    } finally {
        loading.value = false;
    }
};

const fetchAvailableCurrencies = async () => {
    if (!currentCompany.value?.id) return;
    
    try {
        const response = await http.get(`/api/companies/${currentCompany.value.id}/currencies/available`);
        availableCurrencies.value = response.data.data;
    } catch (err) {
        console.error('Error loading available currencies:', err);
    }
};

const addCurrency = async (currencyId) => {
    if (!currentCompany.value?.id) return;
    
    try {
        saving.value = true;
        await http.post(`/api/companies/${currentCompany.value.id}/currencies`, {
            currency_id: currencyId
        });
        await fetchCompanyCurrencies();
        await fetchAvailableCurrencies();
    } catch (err) {
        error.value = 'Failed to add currency';
        console.error('Error adding currency:', err);
    } finally {
        saving.value = false;
    }
};

const removeCurrency = async (currencyId) => {
    if (!confirm('Are you sure you want to remove this currency?')) return;
    if (!currentCompany.value?.id) return;
    
    try {
        saving.value = true;
        await http.delete(`/api/companies/${currentCompany.value.id}/currencies/${currencyId}`);
        await fetchCompanyCurrencies();
        await fetchAvailableCurrencies();
    } catch (err) {
        error.value = 'Failed to remove currency';
        console.error('Error removing currency:', err);
    } finally {
        saving.value = false;
    }
};


const openExchangeRateModal = (currency) => {
    selectedCurrency.value = currency;
    const existingRate = currency.exchange_rates?.[0];
    exchangeRateForm.value = {
        exchange_rate: existingRate?.exchange_rate || currency.default_rate || null,
        effective_date: existingRate?.effective_date ? new Date(existingRate.effective_date) : new Date(),
        cease_date: existingRate?.cease_date ? new Date(existingRate.cease_date) : null,
        notes: existingRate?.notes || '',
        is_default: existingRate ? false : !!currency.default_rate
    };
    showExchangeRateModal.value = true;
};

const saveExchangeRate = async () => {
    if (!currentCompany.value?.id || !selectedCurrency.value) return;
    
    try {
        saving.value = true;
        
        // If editing an existing rate, use the specific rate endpoint
        if (exchangeRateForm.value.rate_id) {
            await http.patch(`/api/companies/${currentCompany.value.id}/currencies/${selectedCurrency.value.currency.id}/exchange-rates/${exchangeRateForm.value.rate_id}`, {
                exchange_rate: exchangeRateForm.value.exchange_rate,
                effective_date: exchangeRateForm.value.effective_date?.toISOString().split('T')[0],
                cease_date: exchangeRateForm.value.cease_date?.toISOString().split('T')[0],
                notes: exchangeRateForm.value.notes
            });
        } else {
            // Creating a new rate
            await http.patch(`/api/companies/${currentCompany.value.id}/currencies/${selectedCurrency.value.currency.id}/exchange-rate`, {
                exchange_rate: exchangeRateForm.value.exchange_rate,
                effective_date: exchangeRateForm.value.is_default ? null : exchangeRateForm.value.effective_date?.toISOString().split('T')[0],
                cease_date: exchangeRateForm.value.is_default ? null : exchangeRateForm.value.cease_date?.toISOString().split('T')[0],
                notes: exchangeRateForm.value.notes,
                is_default: exchangeRateForm.value.is_default
            });
        }
        
        showExchangeRateModal.value = false;
        if (fromHistoryModal.value) {
            fromHistoryModal.value = false;
            showExchangeRateHistoryModal.value = true;
        }
        await fetchCompanyCurrencies();
    } catch (err) {
        error.value = 'Failed to save exchange rate';
        console.error('Error saving exchange rate:', err);
    } finally {
        saving.value = false;
    }
};

const formatExchangeRate = (rate) => {
    if (!rate) return 'N/A';
    return parseFloat(rate).toFixed(6);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
};

const getExchangeRateValue = (currency) => {
    if (!currency.exchange_rates || currency.exchange_rates.length === 0) return null;
    return currency.exchange_rates[0].exchange_rate;
};

// Get the current active exchange rate based on dates
const getCurrentExchangeRate = (currency) => {
    // First try to find a dated rate for today
    if (currency.exchange_rates && currency.exchange_rates.length > 0) {
        const today = new Date().toISOString().split('T')[0];
        
        const currentRate = currency.exchange_rates.find(rate => 
            rate.effective_date <= today && 
            (!rate.cease_date || rate.cease_date >= today)
        );
        
        if (currentRate) {
            return currentRate;
        }
    }
    
    // Fall back to default rate if no dated rate found
    if (currency.default_rate) {
        return {
            ...currency.default_rate,
            is_default: true
        };
    }
    
    return null;
};

// Show exchange rate history modal
const showExchangeRateHistory = (currency) => {
    selectedCurrency.value = currency;
    showExchangeRateHistoryModal.value = true;
};

// Check if a rate is currently active
const isRateActive = (rate) => {
    const today = new Date().toISOString().split('T')[0];
    return rate.effective_date <= today && (!rate.cease_date || rate.cease_date >= today);
};

// Edit a historical rate
const editHistoricalRate = (rate) => {
    exchangeRateForm.value = {
        rate_id: rate.id,
        exchange_rate: rate.exchange_rate,
        effective_date: rate.effective_date ? new Date(rate.effective_date) : new Date(),
        cease_date: rate.cease_date ? new Date(rate.cease_date) : null,
        notes: rate.notes || '',
        is_default: false
    };
    fromHistoryModal.value = true;
    showExchangeRateHistoryModal.value = false;
    showExchangeRateModal.value = true;
};

// Add a new rate from history modal
const addNewRate = () => {
    exchangeRateForm.value = {
        rate_id: null, // Explicitly set to null for new rates
        exchange_rate: selectedCurrency.value?.default_rate || null,
        effective_date: new Date(),
        cease_date: null,
        notes: '',
        is_default: false
    };
    fromHistoryModal.value = true;
    showExchangeRateHistoryModal.value = false;
    showExchangeRateModal.value = true;
};

const handleExchangeRateUpdated = (context) => {
    // For InlineEditable updates, we need to ensure the rate is properly updated
    // The InlineEditable component only sends the exchange_rate value
    // so we need to fetch the updated rates
    fetchCompanyCurrencies();
};

onMounted(() => {
    if (currentCompany.value?.id) {
        fetchCompanyCurrencies();
        fetchAvailableCurrencies();
    }
});
</script>

<template>
    <div class="space-y-6">
        <Message v-if="error" severity="error" :closable="false">
            {{ error }}
        </Message>

        <Message v-if="!currentCompany" severity="info" :closable="false">
            Please select a company to manage currency settings.
        </Message>

        <div v-if="currentCompany" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Managing currencies for <strong>{{ currentCompany.name }}</strong>. The base currency is {{ currentCompany.base_currency }}.
            </p>
        </div>

        <!-- Available Currencies -->
        <div v-if="currentCompany" class="bg-white p-6 shadow rounded-lg dark:bg-gray-800">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-coins mr-2"></i> Add Currency
            </h3>
            <div class="flex flex-wrap gap-2">
                <Dropdown
                    v-model="selectedCurrency"
                    :options="availableCurrencies"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select a currency"
                    class="w-64"
                    :disabled="loading || saving"
                />
                <Button
                    label="Add"
                    @click="addCurrency(selectedCurrency)"
                    :disabled="!selectedCurrency || loading || saving"
                    :loading="saving"
                >
                    <i class="fas fa-plus mr-1"></i> Add
                </Button>
            </div>
        </div>

        <!-- Company Currencies -->
        <div v-if="currentCompany" class="bg-white p-6 shadow rounded-lg dark:bg-gray-800">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-list mr-2"></i> Company Currencies
            </h3>
            
            <div v-if="loading" class="flex justify-center py-8">
                <ProgressSpinner />
            </div>

            <div v-else-if="currencies.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                No currencies added yet. Add your first currency above.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="currency in currencies"
                    :key="currency.id"
                    class="flex items-center justify-between p-4 border border-gray-200 rounded-lg dark:border-gray-700"
                >
                    <div class="flex items-center space-x-4">
                        <span class="text-lg font-medium" :class="currency.is_base_currency ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white'">
                            {{ currency.currency.code }}
                        </span>
                        <span class="text-gray-600 dark:text-gray-400">
                            {{ currency.currency.name }}
                        </span>
                        <span v-if="currency.is_base_currency" class="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                            Base Currency
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ currency.currency.symbol }}
                        </span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <div v-if="!currency.is_base_currency" class="text-sm text-gray-600 dark:text-gray-400">
                            <div class="space-y-2">
                                <!-- Current/Active Rate -->
                                <div v-if="getCurrentExchangeRate(currency)" class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Current Rate:</span>
                                    <InlineEditable
                                        :model-value="getCurrentExchangeRate(currency)?.exchange_rate"
                                        type="number"
                                        :step="'0.000001'"
                                        :min="0"
                                        :api-url="getCurrentExchangeRate(currency)?.id ? `/api/companies/${currentCompany.id}/currencies/${currency.currency.id}/exchange-rates/${getCurrentExchangeRate(currency)?.id}` : `/api/companies/${currentCompany.id}/currencies/${currency.currency.id}/exchange-rate`"
                                        action-type="update_exchange_rate"
                                        :context="{
                                            companyId: currentCompany.id,
                                            currencyId: currency.currency.id,
                                            rateId: getCurrentExchangeRate(currency)?.id
                                        }"
                                        :display-value="formatExchangeRate(getCurrentExchangeRate(currency)?.exchange_rate)"
                                        @updated="handleExchangeRateUpdated"
                                    />
                                    <Button
                                        size="small"
                                        text
                                        @click="showExchangeRateHistory(currency)"
                                        v-tooltip="'View Rate History'"
                                        class="text-xs"
                                    >
                                        <i class="fas fa-history"></i>
                                    </Button>
                                </div>
                                
                                <!-- No Rate Set -->
                                <div v-else>
                                    <Button
                                        size="small"
                                        text
                                        @click="openExchangeRateModal(currency)"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <i class="fas fa-dollar-sign mr-1"></i> Set Exchange Rate
                                    </Button>
                                </div>

                                <!-- Expired Rate Notice -->
                                <div v-if="getCurrentExchangeRate(currency)?.cease_date" class="text-xs text-orange-600 dark:text-orange-400">
                                    Rate expires {{ formatDate(getCurrentExchangeRate(currency).cease_date) }}
                                </div>
                            </div>
                        </div>

    
                        <Button
                            size="small"
                            text
                            @click="removeCurrency(currency.currency.id)"
                            :disabled="currency.is_base_currency"
                            v-tooltip="currency.is_base_currency ? 'Cannot remove base currency' : 'Remove Currency'"
                            :class="currency.is_base_currency ? 'text-gray-400 cursor-not-allowed' : 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300'"
                        >
                            <i class="fas fa-trash"></i>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exchange Rate Modal -->
        <Dialog
            v-model:visible="showExchangeRateModal"
            modal
            :header="exchangeRateForm.rate_id ? 'Edit Exchange Rate' : 'Set Exchange Rate'"
            :style="{ width: '450px' }"
        >
            <div v-if="selectedCurrency" class="space-y-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Setting exchange rate for {{ selectedCurrency.currency.code }} 
                    relative to your base currency ({{ currentCompany.base_currency }})
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Exchange Rate
                    </label>
                    <InputNumber
                        v-model="exchangeRateForm.exchange_rate"
                        :minFractionDigits="6"
                        :maxFractionDigits="6"
                        :min="0"
                        placeholder="1.000000"
                        class="w-full"
                    />
                </div>

                <div class="space-y-2">
                    <div class="flex items-center">
                        <Checkbox 
                            v-model="exchangeRateForm.is_default" 
                            inputId="is_default"
                            :binary="true"
                        />
                        <label for="is_default" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Set as default rate (used when no specific rate exists for a date)
                        </label>
                    </div>
                </div>

                <div v-if="!exchangeRateForm.is_default" class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Effective Date
                        </label>
                        <Calendar
                            v-model="exchangeRateForm.effective_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Cease Date (Optional)
                        </label>
                        <Calendar
                            v-model="exchangeRateForm.cease_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Notes (Optional)
                    </label>
                    <InputText
                        v-model="exchangeRateForm.notes"
                        class="w-full"
                    />
                </div>
            </div>

            <template #footer>
                <Button
                    @click="showExchangeRateModal = false"
                    text
                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                >
                    <i class="fas fa-times mr-1"></i> Cancel
                </Button>
                <Button
                    @click="saveExchangeRate"
                    :loading="saving"
                    :disabled="!exchangeRateForm.exchange_rate"
                    class="bg-blue-600 hover:bg-blue-700 text-white"
                >
                    <i class="fas fa-check mr-1"></i> Save
                </Button>
            </template>
        </Dialog>

        <!-- Exchange Rate History Modal -->
        <Dialog
            v-model:visible="showExchangeRateHistoryModal"
            modal
            header="Exchange Rate History"
            :style="{ width: '600px' }"
        >
            <div v-if="selectedCurrency" class="space-y-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Exchange rate history for {{ selectedCurrency.currency.code }} 
                    relative to your base currency ({{ currentCompany.base_currency }})
                </div>

                <div class="space-y-2">
                    <div v-if="selectedCurrency.exchange_rates && selectedCurrency.exchange_rates.length > 0" class="space-y-2">
                        <div 
                            v-for="(rate, index) in [...selectedCurrency.exchange_rates].sort((a, b) => new Date(b.effective_date) - new Date(a.effective_date))"
                            :key="index"
                            class="flex items-center justify-between p-3 border rounded-lg"
                            :class="isRateActive(rate) ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700'"
                        >
                            <div class="flex-1">
                                <div class="font-medium">
                                    {{ formatExchangeRate(rate.exchange_rate) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Effective: {{ formatDate(rate.effective_date) }}
                                    <span v-if="rate.cease_date"> - Ceased: {{ formatDate(rate.cease_date) }}</span>
                                </div>
                                <div v-if="rate.notes" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    {{ rate.notes }}
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span 
                                    v-if="isRateActive(rate)"
                                    class="text-xs px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded"
                                >
                                    Active
                                </span>
                                <Button
                                    size="small"
                                    text
                                    @click="editHistoricalRate(rate)"
                                    v-tooltip="'Edit Rate'"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    <i class="fas fa-pencil-alt"></i>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No exchange rates found for this currency
                    </div>
                </div>

                <div class="pt-4 border-t">
                    <Button
                        size="small"
                        @click="addNewRate"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        <i class="fas fa-plus mr-1"></i> Add New Rate
                    </Button>
                </div>
            </div>

            <template #footer>
                <Button
                    @click="showExchangeRateHistoryModal = false"
                    text
                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                >
                    <i class="fas fa-times mr-1"></i> Close
                </Button>
            </template>
        </Dialog>
    </div>
</template>