<script setup>
import { ref, onMounted } from 'vue';
import { http } from '@/lib/http';
import InlineEditable from '@/Components/InlineEditable.vue';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Card from 'primevue/card';

const props = defineProps({
  company: { type: String, required: true }
});

const currencies = ref([]);
const availableCurrencies = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const showExchangeRateModal = ref(false);
const selectedCurrency = ref(null);
const exchangeRateForm = ref({
    exchange_rate: null,
    effective_date: null,
    cease_date: null,
    notes: ''
});

const fetchCompanyCurrencies = async () => {
    try {
        loading.value = true;
        const response = await http.get(`/api/companies/${props.company}/currencies`);
        currencies.value = response.data.data;
    } catch (err) {
        error.value = 'Failed to load currencies';
        console.error('Error loading currencies:', err);
    } finally {
        loading.value = false;
    }
};

const fetchAvailableCurrencies = async () => {
    try {
        const response = await http.get(`/api/companies/${props.company}/currencies/available`);
        availableCurrencies.value = response.data.data;
    } catch (err) {
        console.error('Error loading available currencies:', err);
    }
};

const addCurrency = async (currencyId) => {
    try {
        saving.value = true;
        await http.post(`/api/companies/${props.company}/currencies`, {
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
    
    try {
        saving.value = true;
        await http.delete(`/api/companies/${props.company}/currencies/${currencyId}`);
        await fetchCompanyCurrencies();
        await fetchAvailableCurrencies();
    } catch (err) {
        error.value = 'Failed to remove currency';
        console.error('Error removing currency:', err);
    } finally {
        saving.value = false;
    }
};

const setAsBase = async (currencyId) => {
    try {
        saving.value = true;
        await http.patch(`/api/companies/${props.company}/currencies/${currencyId}/set-base`);
        await fetchCompanyCurrencies();
    } catch (err) {
        error.value = 'Failed to set base currency';
        console.error('Error setting base currency:', err);
    } finally {
        saving.value = false;
    }
};

const openExchangeRateModal = (currency) => {
    selectedCurrency.value = currency;
    const existingRate = currency.exchange_rates?.[0];
    exchangeRateForm.value = {
        exchange_rate: existingRate?.exchange_rate || null,
        effective_date: existingRate?.effective_date ? new Date(existingRate.effective_date) : new Date(),
        cease_date: existingRate?.cease_date ? new Date(existingRate.cease_date) : null,
        notes: existingRate?.notes || ''
    };
    showExchangeRateModal.value = true;
};

const saveExchangeRate = async () => {
    if (!selectedCurrency.value) return;
    
    try {
        saving.value = true;
        await http.patch(`/api/companies/${props.company}/currencies/${selectedCurrency.value.currency.id}/exchange-rate`, {
            exchange_rate: exchangeRateForm.value.exchange_rate,
            effective_date: exchangeRateForm.value.effective_date?.toISOString().split('T')[0],
            cease_date: exchangeRateForm.value.cease_date?.toISOString().split('T')[0],
            notes: exchangeRateForm.value.notes
        });
        showExchangeRateModal.value = false;
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

const handleExchangeRateUpdated = () => {
    fetchCompanyCurrencies();
};

onMounted(() => {
    fetchCompanyCurrencies();
    fetchAvailableCurrencies();
});
</script>

<template>
    <div class="space-y-6">
        <Message v-if="error" severity="error" :closable="false">
            {{ error }}
        </Message>

        <!-- Available Currencies -->
        <Card>
            <template #title>
                <i class="fas fa-coins mr-2"></i> Add Currency
            </template>
            <template #content>
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
            </template>
        </Card>

        <!-- Company Currencies -->
        <Card>
            <template #title>
                <i class="fas fa-list mr-2"></i> Company Currencies
            </template>
            <template #content>
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
                                <InlineEditable
                                    :model-value="getExchangeRateValue(currency)"
                                    type="number"
                                    :step="'0.000001'"
                                    :min="0"
                                    :api-url="`/api/companies/${props.company}/currencies/${currency.currency.id}/exchange-rate`"
                                    action-type="update_exchange_rate"
                                    :context="{
                                        companyId: props.company,
                                        currencyId: currency.currency.id
                                    }"
                                    :display-value="formatExchangeRate(getExchangeRateValue(currency))"
                                    :empty-value="'Click to set rate'"
                                    :show-modal="true"
                                    @updated="handleExchangeRateUpdated"
                                />
                                <div v-if="currency.exchange_rates[0]?.cease_date" class="text-xs text-red-600 dark:text-red-400 mt-1">
                                    Expires {{ formatDate(currency.exchange_rates[0].cease_date) }}
                                </div>
                            </div>

                            <Button
                                v-if="!currency.is_base_currency && !getExchangeRateValue(currency)"
                                size="small"
                                text
                                @click="openExchangeRateModal(currency)"
                                v-tooltip="'Set Exchange Rate'"
                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                <i class="fas fa-dollar-sign"></i>
                            </Button>

                            <Button
                                v-if="!currency.is_base_currency"
                                size="small"
                                text
                                @click="setAsBase(currency.currency.id)"
                                v-tooltip="'Set as Base Currency'"
                                class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                            >
                                <i class="fas fa-star"></i>
                            </Button>

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
            </template>
        </Card>

        <!-- Exchange Rate Modal -->
        <div v-if="showExchangeRateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Set Exchange Rate</h3>
                
                <div v-if="selectedCurrency" class="space-y-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Setting exchange rate for {{ selectedCurrency.currency.code }} 
                        relative to your base currency
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

                    <div class="grid grid-cols-2 gap-4">
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

                <div class="flex justify-end gap-2 mt-6">
                    <Button
                        label="Cancel"
                        @click="showExchangeRateModal = false"
                        text
                    />
                    <Button
                        label="Save"
                        @click="saveExchangeRate"
                        :loading="saving"
                        :disabled="!exchangeRateForm.exchange_rate"
                    />
                </div>
            </div>
        </div>
    </div>
</template>