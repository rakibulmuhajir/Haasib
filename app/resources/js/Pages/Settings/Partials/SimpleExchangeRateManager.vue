<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import SelectButton from 'primevue/selectbutton';
import Calendar from 'primevue/calendar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import Badge from 'primevue/badge';
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import Divider from 'primevue/divider';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useFormatting } from '@/composables/useFormatting';

const props = defineProps({
    currencies: {
        type: Array,
        default: () => []
    },
    baseCurrency: {
        type: Object,
        default: null
    },
    exchangeRates: {
        type: Array,
        default: () => []
    },
    canEditExchange: {
        type: Boolean,
        default: false
    },
    canManageSystem: {
        type: Boolean,
        default: false
    },
    canManageCompany: {
        type: Boolean,
        default: false
    },
    currencyPrecision: {
        type: Number,
        default: 2
    }
});

const emit = defineEmits(['refresh']);

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const { formatMoney } = useFormatting();

// Reactive data
const loading = ref(false);
const saving = ref(false);
const exchangeRates = ref([]);
const rateHistory = ref([]);
const selectedCurrency = ref(null);
const showAddRateModal = ref(false);
const showHistoryModal = ref(false);
const editingRate = ref(null);

// Forms
const rateForm = ref({
    from_currency: null,
    to_currency: null,
    rate: null,
    rate_direction: 'forward', // 'forward' or 'reverse'
    effective_date: new Date(),
    cease_date: null,
    notes: '',
    is_default: false // Whether this is a default rate
});

const calculatedInverse = ref(null);


// Computed properties
const dropdownCurrencies = computed(() => {
    if (!props.baseCurrency?.code) return [];
    
    const baseCurrencyCode = props.baseCurrency.code || props.baseCurrency;
    
    return props.currencies
        .filter(currency => {
            // Check if we have a valid UUID for this currency
            let currencyId = null;
            if (currency.currency) {
                currencyId = currency.currency.id;
            } else if (currency.id) {
                // Make sure it's a UUID, not a currency code
                currencyId = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(currency.id) ? currency.id : null;
            }
            
            // If we don't have a valid UUID, skip this currency
            if (!currencyId) return false;
            
            // Also check the currency code
            let currencyCode = null;
            if (currency.currency) {
                currencyCode = currency.currency.code;
            } else if (currency.code) {
                currencyCode = currency.code;
            } else if (currency.currency_code) {
                currencyCode = currency.currency_code;
            }
            
            return currencyCode && currencyCode !== baseCurrencyCode;
        })
        .map(currency => {
            // Extract all possible fields with proper fallbacks
            let code, name, symbol, id;
            
            if (currency.currency) {
                // Nested structure from CurrencySettings
                id = currency.currency.id;
                code = currency.currency.code;
                name = currency.currency.name;
                symbol = currency.currency.symbol;
            } else {
                // Direct structure
                id = currency.id;  // This should be the UUID
                code = currency.code || currency.currency_code;
                name = currency.name;
                symbol = currency.symbol;
            }
            
            return {
                id,
                code,
                name: name || code,
                symbol: symbol || '',
                original: currency
            };
        });
});

const currenciesWithRates = computed(() => {
    const baseCurrencyCode = props.baseCurrency?.code || props.baseCurrency;
    
    return dropdownCurrencies.value.map(currency => {
        // Find active rate for this pair
        const activeRate = getActiveRate(baseCurrencyCode, currency.code);
        const inverseRate = activeRate ? 1 / activeRate.rate : null;
        
        return {
            ...currency.original,
            code: currency.code,
            name: currency.name,
            symbol: currency.symbol,
            current_rate: activeRate?.rate || null,
            inverse_rate: inverseRate,
            last_updated: activeRate?.effective_date || null,
            source: 'manual',
        };
    });
});

// Helper functions
const getActiveRate = (fromCurrency, toCurrency) => {
    const rates = exchangeRates.value.filter(rate => 
        rate.from_currency === fromCurrency && 
        rate.to_currency === toCurrency &&
        new Date(rate.effective_date) <= new Date() &&
        (!rate.cease_date || new Date(rate.cease_date) >= new Date())
    );
    
    // Sort by effective date descending to get the most recent
    rates.sort((a, b) => new Date(b.effective_date) - new Date(a.effective_date));
    return rates[0] || null;
};

const calculateInverse = (rate) => {
    return rate ? (1 / rate).toFixed(8) : '';
};

// Helper functions to get currency codes from UUIDs
const getCurrencyCode = (currencyId) => {
    if (!currencyId) return '';
    
    // Check if it's already a code (not UUID)
    if (typeof currencyId === 'string' && !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(currencyId)) {
        return currencyId;
    }
    
    // Check base currency - multiple possible structures
    if (props.baseCurrency) {
        // Direct match
        if (props.baseCurrency.id === currencyId) {
            return props.baseCurrency.code || props.baseCurrency.currency?.code || 'Base';
        }
        // Nested structure
        if (props.baseCurrency.currency?.id === currencyId) {
            return props.baseCurrency.currency.code || 'Base';
        }
        // If baseCurrency itself is a code string
        if (typeof props.baseCurrency === 'string' && props.baseCurrency !== currencyId) {
            return props.baseCurrency;
        }
    }
    
    // Check dropdown currencies
    const currency = dropdownCurrencies.value.find(c => c.id === currencyId);
    if (currency) {
        return currency.code;
    }
    
    // Check all props.currencies as fallback
    if (props.currencies) {
        const fallbackCurrency = props.currencies.find(c => {
            const cId = c.id || c.currency?.id;
            return cId === currencyId;
        });
        if (fallbackCurrency) {
            return fallbackCurrency.currency?.code || fallbackCurrency.code || 'Currency';
        }
    }
    
    // Last resort - return a placeholder or the UUID truncated
    return currencyId.substring(0, 3).toUpperCase() || '???';
};

const getFromCurrencyCode = () => {
    return getCurrencyCode(rateForm.value.from_currency);
};

const getToCurrencyCode = () => {
    return getCurrencyCode(rateForm.value.to_currency);
};

// Methods
const fetchExchangeRates = async () => {
    if (!props.baseCurrency?.code) return;
    
    try {
        loading.value = true;
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates`, {
            params: {
                base_currency: props.baseCurrency.code
            }
        });
        exchangeRates.value = response.data.data;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load exchange rates',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const resetRateForm = () => {
    // Get the base currency UUID with multiple fallback strategies
    let baseCurrencyId = null;
    
    // Log for debugging (remove in production)
    console.log('resetRateForm baseCurrency:', props.baseCurrency);
    
    // Strategy 1: Direct ID property
    if (props.baseCurrency?.id) {
        baseCurrencyId = props.baseCurrency.id;
    }
    // Strategy 2: Nested currency object
    else if (props.baseCurrency?.currency?.id) {
        baseCurrencyId = props.baseCurrency.currency.id;
    }
    // Strategy 3: If baseCurrency is just a code string, use dropdown currencies to find UUID
    else if (typeof props.baseCurrency === 'string' || props.baseCurrency?.code) {
        const baseCode = typeof props.baseCurrency === 'string' ? props.baseCurrency : props.baseCurrency.code;
        // Look for this code in dropdown currencies
        const foundCurrency = dropdownCurrencies.value.find(c => c.code === baseCode);
        if (foundCurrency) {
            baseCurrencyId = foundCurrency.id;
        } else {
            // If not found in dropdown, check props.currencies
            const foundInProps = props.currencies?.find(c => {
                const cCode = c.currency?.code || c.code;
                return cCode === baseCode;
            });
            if (foundInProps) {
                baseCurrencyId = foundInProps.id || foundInProps.currency?.id;
            }
        }
    }
    
    console.log('Final baseCurrencyId:', baseCurrencyId);
    
    rateForm.value = {
        from_currency: baseCurrencyId,
        to_currency: null,
        rate: null,
        rate_direction: 'forward',
        effective_date: new Date(),
        cease_date: null,
        notes: '',
        is_default: false
    };
    editingRate.value = null;
    calculatedInverse.value = null;
};

// Calculate inverse rate
const calculateInverseRate = () => {
    if (rateForm.value.rate && rateForm.value.rate > 0) {
        calculatedInverse.value = (1 / rateForm.value.rate).toFixed(8);
    } else {
        calculatedInverse.value = null;
    }
};

const openAddRateModal = (currency = null) => {
    resetRateForm();
    if (currency) {
        // Use the UUID, not the code
        rateForm.value.to_currency = currency.id || currency.currency?.id;
        selectedCurrency.value = currency;
    }
    showAddRateModal.value = true;
};

const saveRate = async () => {
    if (!props.canEditExchange) return;
    
    // Validation
    if (!rateForm.value.from_currency || !rateForm.value.to_currency) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please select both currencies',
            life: 3000
        });
        return;
    }
    
    if (!rateForm.value.rate || rateForm.value.rate <= 0) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please enter a positive rate',
            life: 3000
        });
        return;
    }
    
    if (!rateForm.value.effective_date) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Effective date is required',
            life: 3000
        });
        return;
    }
    
    // Check for overlapping windows
    const overlaps = checkForOverlappingRates();
    if (overlaps.length > 0) {
        toast.add({
            severity: 'warn',
            summary: 'Date Conflict',
            detail: 'This rate overlaps with an existing rate for this period',
            life: 5000
        });
        return;
    }
    
    try {
        saving.value = true;
        
        // Convert rate to always be stored as from_currency -> to_currency
        let finalRate = rateForm.value.rate;
        if (rateForm.value.rate_direction === 'reverse') {
            // Convert reverse rate to forward rate
            finalRate = rateForm.value.rate ? (1 / rateForm.value.rate) : null;
        }
        
        const payload = {
            from_currency_id: rateForm.value.from_currency,
            to_currency_id: rateForm.value.to_currency,
            rate: finalRate,
            date: rateForm.value.effective_date.toISOString().split('T')[0],
            cease_date: rateForm.value.cease_date ? rateForm.value.cease_date.toISOString().split('T')[0] : null,
            notes: rateForm.value.notes,
            is_default: rateForm.value.is_default || false,
            company_id: page.props.auth.currentCompany.id
        };
        
        if (editingRate.value) {
            await http.put(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/${editingRate.value.id}`, payload);
            toast.add({
                severity: 'success',
                summary: 'Rate Updated',
                detail: 'Exchange rate updated successfully',
                life: 3000
            });
        } else {
            await http.post(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates`, payload);
            toast.add({
                severity: 'success',
                summary: 'Rate Added',
                detail: 'Exchange rate added successfully',
                life: 3000
            });
        }
        
        await fetchExchangeRates();
        emit('refresh');
        showAddRateModal.value = false;
        resetRateForm();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: err.response?.data?.message || 'Failed to save exchange rate',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const checkForOverlappingRates = () => {
    const newEffectiveDate = new Date(rateForm.value.effective_date);
    const newCeaseDate = rateForm.value.cease_date ? new Date(rateForm.value.cease_date) : null;
    
    return exchangeRates.value.filter(rate => {
        if (rate.id === editingRate.value?.id) return false;
        if (rate.from_currency !== rateForm.value.from_currency || rate.to_currency !== rateForm.value.to_currency) return false;
        
        const existingEffectiveDate = new Date(rate.effective_date);
        const existingCeaseDate = rate.cease_date ? new Date(rate.cease_date) : null;
        
        // Check if dates overlap
        if (newCeaseDate && existingCeaseDate) {
            return newEffectiveDate <= existingCeaseDate && newCeaseDate >= existingEffectiveDate;
        } else if (newCeaseDate) {
            return newEffectiveDate <= existingCeaseDate;
        } else if (existingCeaseDate) {
            return newCeaseDate >= existingEffectiveDate;
        } else {
            return true; // Both are permanent rates
        }
    });
};

const editRate = (rate) => {
    editingRate.value = rate;
    rateForm.value = {
        from_currency: rate.from_currency,
        to_currency: rate.to_currency,
        rate: rate.rate,
        rate_direction: 'forward',
        effective_date: new Date(rate.effective_date),
        cease_date: rate.cease_date ? new Date(rate.cease_date) : null,
        notes: rate.notes || '',
        is_default: rate.is_default || false
    };
    calculatedInverse.value = rate.rate ? (1 / rate.rate).toFixed(8) : null;
    showAddRateModal.value = true;
};

const confirmDeleteRate = (rate) => {
    if (!props.canEditExchange) return;
    
    confirm.require({
        message: `Are you sure you want to delete this exchange rate?`,
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptLabel: 'Delete',
        acceptClass: 'p-button-danger',
        accept: () => deleteRate(rate)
    });
};

const deleteRate = async (rate) => {
    try {
        await http.delete(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/${rate.id}`);
        
        toast.add({
            severity: 'success',
            summary: 'Rate Deleted',
            detail: 'Exchange rate deleted successfully',
            life: 3000
        });
        
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete exchange rate',
            life: 3000
        });
    }
};

const viewRateHistory = (currency) => {
    selectedCurrency.value = currency;
    const rates = exchangeRates.value.filter(rate => 
        (rate.from_currency === props.baseCurrency.code && rate.to_currency === currency.code) ||
        (rate.to_currency === props.baseCurrency.code && rate.from_currency === currency.code)
    );
    
    // Sort by effective date descending
    rates.sort((a, b) => new Date(b.effective_date) - new Date(a.effective_date));
    rateHistory.value = rates;
    showHistoryModal.value = true;
};


// Initialize
onMounted(() => {
    // Don't use await in onMounted
    if (props.exchangeRates.length === 0) {
        fetchExchangeRates();
    }
});

// Watch for prop changes
watch(() => props.exchangeRates, (newRates) => {
    if (newRates.length > 0) {
        exchangeRates.value = newRates;
    }
}, { immediate: true });

// Watch for base currency changes and update form
watch(() => props.baseCurrency, (newBaseCurrency) => {
    if (newBaseCurrency && !rateForm.value.from_currency) {
        const baseCurrencyId = newBaseCurrency?.id || newBaseCurrency?.currency?.id;
        rateForm.value.from_currency = baseCurrencyId;
    }
}, { immediate: true });

// Watch for rate changes to calculate inverse
watch(() => rateForm.value.rate, () => {
    calculateInverseRate();
});

// Watch for direction change to recalculate
watch(() => rateForm.value.rate_direction, () => {
    calculateInverseRate();
});
</script>

<template>
    <div class="space-y-6">
        <Toast />
        
        <!-- Header with actions -->
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Exchange Rate Management</span>
                    <div class="flex gap-2">
                        <Button
                            v-if="canEditExchange"
                            label="Add Rate"
                            size="small"
                            @click="openAddRateModal"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="plus" />
                            </template>
                        </Button>
                    </div>
                </div>
            </template>
            
            <template #content>
                <Message
                    v-if="!baseCurrency"
                    severity="warn"
                    text="No base currency configured. Please set a base currency first."
                />
                
                <div v-else class="space-y-4">
                    <div class="flex items-center gap-4 text-sm text-gray-600">
                        <span>Base Currency: <strong>{{ baseCurrency?.code || baseCurrency }} ({{ baseCurrency?.symbol || '' }})</strong></span>
                        <span>•</span>
                        <span>All rates are relative to base currency</span>
                    </div>
                    
                    <!-- Exchange Rates Table -->
                    <DataTable
                        :value="currenciesWithRates"
                        :loading="loading"
                        dataKey="code"
                        stripedRows
                        showGridlines
                        :paginator="currenciesWithRates.length > 10"
                        :rows="10"
                        responsiveLayout="scroll"
                    >
                        <Column field="code" header="Currency" style="min-width: 120px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ data.code }}</span>
                                    <span class="text-gray-500">{{ data.symbol }}</span>
                                </div>
                            </template>
                        </Column>
                        
                        <Column field="name" header="Name" style="min-width: 150px">
                            <template #body="{ data }">
                                {{ data.name }}
                            </template>
                        </Column>
                        
                        <Column field="current_rate" header="Rate (Base → Currency)" style="min-width: 150px">
                            <template #body="{ data }">
                                <div v-if="data.current_rate" class="text-right">
                                    <span class="font-mono">{{ formatMoney(data.current_rate, { currency: '', precision: 6 }) }}</span>
                                </div>
                                <div v-else class="text-gray-400 text-center">—</div>
                            </template>
                        </Column>
                        
                        <Column field="inverse_rate" header="Rate (Currency → Base)" style="min-width: 150px">
                            <template #body="{ data }">
                                <div v-if="data.inverse_rate" class="text-right">
                                    <span class="font-mono">{{ formatMoney(data.inverse_rate, { currency: '', precision: 6 }) }}</span>
                                </div>
                                <div v-else class="text-gray-400 text-center">—</div>
                            </template>
                        </Column>
                        
                        <Column field="last_updated" header="Last Updated" style="min-width: 120px">
                            <template #body="{ data }">
                                <div v-if="data.last_updated">
                                    {{ new Date(data.last_updated).toLocaleDateString() }}
                                </div>
                                <div v-else class="text-gray-400">—</div>
                            </template>
                        </Column>
                        
                        <Column header="Actions" style="min-width: 150px">
                            <template #body="{ data }">
                                <div class="flex gap-1">
                                    <Button
                                        size="small"
                                        text
                                        @click="openAddRateModal(data)"
                                        :disabled="!canEditExchange"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon icon="plus" />
                                        </template>
                                        <template #tooltip>
                                            Add Rate
                                        </template>
                                    </Button>
                                    <Button
                                        size="small"
                                        text
                                        @click="viewRateHistory(data)"
                                        :disabled="!currenciesWithRates.find(c => c.code === data.code)?.current_rate"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon icon="history" />
                                        </template>
                                        <template #tooltip>
                                            View History
                                        </template>
                                    </Button>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>
        
        <!-- Add/Edit Rate Modal -->
        <Dialog
            v-model:visible="showAddRateModal"
            :header="editingRate ? 'Edit Exchange Rate' : 'Add Exchange Rate'"
            :modal="true"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">From Currency</label>
                        <Dropdown
                            v-model="rateForm.from_currency"
                            :options="[{ id: baseCurrency?.id || baseCurrency?.currency?.id, code: baseCurrency?.code || baseCurrency, symbol: baseCurrency?.symbol || '' }]"
                            optionLabel="code"
                            optionValue="id"
                            placeholder="Select currency"
                            class="w-full"
                            disabled
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">To Currency</label>
                        <Dropdown
                            v-model="rateForm.to_currency"
                            :options="dropdownCurrencies"
                            optionLabel="code"
                            optionValue="id"
                            placeholder="Select currency"
                            class="w-full"
                            :disabled="!!editingRate"
                        >
                            <template #option="slotProps">
                                <div class="flex items-center justify-between w-full">
                                    <span>{{ slotProps.option.code }}</span>
                                    <span class="text-gray-500">{{ slotProps.option.name }} ({{ slotProps.option.symbol }})</span>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Exchange Rate Direction</label>
                    <SelectButton
                        v-model="rateForm.rate_direction"
                        :options="[
                            { label: `${getFromCurrencyCode()} → ${getToCurrencyCode()}`, value: 'forward' },
                            { label: `${getToCurrencyCode()} → ${getFromCurrencyCode()}`, value: 'reverse' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">
                        Exchange Rate
                        <span class="text-gray-500 font-normal">
                            (1 {{ rateForm.rate_direction === 'forward' ? getFromCurrencyCode() : getToCurrencyCode() }} = ? {{ rateForm.rate_direction === 'forward' ? getToCurrencyCode() : getFromCurrencyCode() }})
                        </span>
                    </label>
                    <InputNumber
                        v-model="rateForm.rate"
                        :min="0"
                        :maxFractionDigits="8"
                        mode="decimal"
                        class="w-full"
                        placeholder="Enter rate"
                        @input="calculateInverseRate"
                    />
                    <div class="mt-2 space-y-1">
                        <small class="text-gray-600">
                            <span v-if="rateForm.rate_direction === 'forward'">
                                1 {{ getFromCurrencyCode() }} = {{ rateForm.rate || 0 }} {{ getToCurrencyCode() }}
                            </span>
                            <span v-else>
                                1 {{ getToCurrencyCode() }} = {{ rateForm.rate || 0 }} {{ getFromCurrencyCode() }}
                            </span>
                        </small>
                        <br>
                        <small class="text-blue-600" v-if="calculatedInverse">
                            Inverse: 1 {{ rateForm.rate_direction === 'forward' ? getToCurrencyCode() : getFromCurrencyCode() }} = {{ calculatedInverse }} {{ rateForm.rate_direction === 'forward' ? getFromCurrencyCode() : getToCurrencyCode() }}
                        </small>
                    </div>
                </div>
                
                <div>
                    <label class="flex items-center gap-2 mb-2">
                        <Checkbox
                            v-model="rateForm.is_default"
                            binary
                        />
                        <span class="text-sm font-medium">Set as Default Rate</span>
                    </label>
                    <small class="text-gray-500">
                        This rate will be used as the default when no other rates are effective
                    </small>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Effective Date *</label>
                        <Calendar
                            v-model="rateForm.effective_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                            :showIcon="true"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Cease Date (optional)</label>
                        <Calendar
                            v-model="rateForm.cease_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                            :showIcon="true"
                            placeholder="Leave empty for permanent rate"
                        />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="rateForm.notes"
                        rows="3"
                        class="w-full"
                        placeholder="Optional notes about this rate"
                    />
                </div>
                
                <Message severity="info" :closable="false">
                    <div class="text-sm">
                        <p><strong>How rates work:</strong></p>
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li>Enter rates in either direction (e.g., PKR→SAR or SAR→PKR) - the system handles conversion</li>
                            <li>Default rates are used when no other rates are effective for the current date</li>
                            <li>Overriding rates with newer effective dates will replace older ones</li>
                            <li>Cease dates automatically retire rates - leave empty for permanent rates</li>
                            <li>Rates can be pre-scheduled by setting future effective dates</li>
                            <li>Use effective and cease dates to create rates for specific periods (e.g., quarterly rates)</li>
                        </ul>
                    </div>
                </Message>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        text
                        @click="showAddRateModal = false"
                    />
                    <Button
                        :label="editingRate ? 'Update Rate' : 'Save Rate'"
                        :loading="saving"
                        @click="saveRate"
                    />
                </div>
            </template>
        </Dialog>
        
        <!-- Rate History Modal -->
        <Dialog
            v-model:visible="showHistoryModal"
            :header="`Rate History: ${selectedCurrency?.code}`"
            :modal="true"
            :style="{ width: '700px' }"
        >
            <DataTable
                :value="rateHistory"
                dataKey="id"
                stripedRows
                showGridlines
                :paginator="rateHistory.length > 10"
                :rows="10"
                responsiveLayout="scroll"
            >
                <Column field="effective_date" header="Effective Date" style="min-width: 120px">
                    <template #body="{ data }">
                        {{ new Date(data.effective_date).toLocaleDateString() }}
                    </template>
                </Column>
                
                <Column field="cease_date" header="Cease Date" style="min-width: 120px">
                    <template #body="{ data }">
                        {{ data.cease_date ? new Date(data.cease_date).toLocaleDateString() : '—' }}
                    </template>
                </Column>
                
                <Column field="rate" header="Rate" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="font-mono">{{ formatMoney(data.rate, { currency: '', precision: 6 }) }}</span>
                    </template>
                </Column>
                
                <Column field="notes" header="Notes" style="min-width: 200px">
                    <template #body="{ data }">
                        {{ data.notes || '—' }}
                    </template>
                </Column>
                
                <Column field="created_at" header="Created" style="min-width: 120px">
                    <template #body="{ data }">
                        {{ new Date(data.created_at).toLocaleDateString() }}
                    </template>
                </Column>
                
                <Column header="Actions" style="min-width: 80px">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <Button
                                size="small"
                                text
                                @click="editRate(data)"
                                :disabled="!canEditExchange"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="edit" />
                                </template>
                            </Button>
                            <Button
                                size="small"
                                text
                                severity="danger"
                                @click="confirmDeleteRate(data)"
                                :disabled="!canEditExchange"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="trash" />
                                </template>
                            </Button>
                        </div>
                    </template>
                </Column>
            </DataTable>
            
            <template #footer>
                <div class="flex justify-end">
                    <Button
                        label="Close"
                        @click="showHistoryModal = false"
                    />
                </div>
            </template>
        </Dialog>
        
    </div>
</template>