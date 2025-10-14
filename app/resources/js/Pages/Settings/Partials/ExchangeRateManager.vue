<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import SelectButton from 'primevue/selectbutton';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Badge from 'primevue/badge';
import Chip from 'primevue/chip';
import Checkbox from 'primevue/checkbox';
import Divider from 'primevue/divider';
import Dialog from 'primevue/dialog';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import ToggleButton from 'primevue/togglebutton';
import FileUpload from 'primevue/fileupload';
import { useToast } from 'primevue/usetoast';
import { useFormatting } from '@/composables/useFormatting';
import { useConfirm } from 'primevue/useconfirm';

const page = usePage();
const toast = useToast();
const { formatMoney, formatDate, formatDateTime } = useFormatting();
const confirm = useConfirm();

// Props
const props = defineProps({
    currencies: {
        type: Array,
        default: () => []
    },
    baseCurrency: {
        type: Object,
        default: () => ({})
    },
    exchangeRates: {
        type: Array,
        default: () => []
    }
});

// Emits
const emit = defineEmits(['refresh']);

// Permissions
const canView = computed(() => 
    page.props.auth.can?.currency?.view ?? false
);

const canEditExchange = computed(() => 
    page.props.auth.can?.currency?.exchangeEdit ?? false
);

const canManageSystem = computed(() => 
    page.props.auth.can?.currency?.systemManage ?? false
);

// Data
const loading = ref(false);
const saving = ref(false);
const exchangeRates = ref(props.exchangeRates || []);
const rateHistory = ref([]);
const automationSettings = ref({
    auto_sync_enabled: false,
    sync_frequency: 'daily',
    primary_source: 'ecb',
    secondary_source: null,
    notify_on_changes: true,
    require_approval_for_changes: true,
    max_variance_percentage: 5,
    fallback_behavior: 'last_successful',
    custom_sources: []
});

// Forms
const rateForm = ref({
    id: null,
    from_currency: null,
    to_currency: null,
    rate: null,
    inverse_rate: null,
    effective_date: new Date().toISOString().split('T')[0],
    cease_date: null,
    source: 'manual',
    notes: '',
    is_approved: false,
    is_override: false,
    override_type: null, // 'fixed' or 'adjustment'
    adjustment_value: null,
    adjustment_type: null // 'percentage' or 'fixed'
});

const overrideForm = ref({
    id: null,
    from_currency: null,
    to_currency: null,
    override_type: 'fixed',
    fixed_rate: null,
    adjustment_type: 'percentage',
    adjustment_value: null,
    effective_date: new Date().toISOString().split('T')[0],
    cease_date: null,
    notes: ''
});

const fallbackForm = ref({
    currency_pair: null,
    fallback_type: 'last_successful', // 'last_successful' or 'static'
    static_rate: null,
    max_age_hours: 72 // 3 days default
});

const conversionHelper = ref({
    amount: 100,
    from_currency: null,
    to_currency: null,
    result: null
});

// Modals
const showAddRateModal = ref(false);
const showOverrideModal = ref(false);
const showFallbackModal = ref(false);
const showHistoryModal = ref(false);
const showImportModal = ref(false);
const showAutomationModal = ref(false);
const showConversionHelper = ref(false);
const showBulkUpdateModal = ref(false);
const showSourceConfigModal = ref(false);
const showErrorLogModal = ref(false);

// Selected data
const selectedCurrencyPair = ref(null);
const selectedRates = ref([]);
const historyFilter = ref({
    from_currency: null,
    to_currency: null,
    date_range: null
});

// Import data
const importFile = ref(null);
const importPreview = ref([]);
const importSummary = ref({
    total: 0,
    new: 0,
    updated: 0,
    skipped: 0,
    errors: 0
});

// New data structures
const rateOverrides = ref([]);
const fallbackRates = ref([]);
const customSources = ref([]);
const syncStatus = ref({
    last_sync: null,
    last_success: null,
    last_error: null,
    is_using_fallback: false,
    fallback_currency: null,
    fallback_reason: null
});
const errorLogs = ref([]);
const sourceConfig = ref({
    name: '',
    endpoint: '',
    api_key: '',
    rate_path: 'rates',
    from_field: 'from',
    to_field: 'to',
    rate_field: 'rate',
    date_field: 'date',
    headers: '{}'
});

// Computed properties
const currenciesWithRates = computed(() => {
    return props.currencies.map(currency => {
        const currencyCode = currency.currency?.code || currency.code || currency.currency_code || currency.id;
        const baseCurrencyCode = props.baseCurrency?.code || props.baseCurrency;
        
        // Check for active overrides first
        const activeOverride = rateOverrides.value.find(override => 
            override.to_currency === currencyCode && 
            override.from_currency === baseCurrencyCode &&
            new Date(override.effective_date) <= new Date() &&
            (!override.cease_date || new Date(override.cease_date) >= new Date())
        );
        
        const currentRate = exchangeRates.value.find(
            rate => rate.to_currency === currencyCode && 
                   rate.from_currency === baseCurrencyCode &&
                   rate.effective_date <= new Date().toISOString().split('T')[0]
        );
        
        // Sort by effective date to get the most recent
        const allRates = exchangeRates.value
            .filter(rate => 
                rate.to_currency === currencyCode && 
                rate.from_currency === baseCurrencyCode
            )
            .sort((a, b) => new Date(b.effective_date) - new Date(a.effective_date));
        
        const latestRate = allRates[0];
        const fallback = fallbackRates.value.find(f => 
            f.from_currency === baseCurrencyCode && 
            f.to_currency === currencyCode
        );
        
        // Apply override if active
        let displayRate = latestRate?.rate;
        let displayInverse = latestRate?.inverse_rate;
        let rateSource = latestRate?.source || 'none';
        let isOverride = false;
        let overrideInfo = null;
        
        if (activeOverride) {
            isOverride = true;
            if (activeOverride.override_type === 'fixed') {
                displayRate = activeOverride.fixed_rate;
                displayInverse = 1 / activeOverride.fixed_rate;
            } else if (activeOverride.override_type === 'adjustment' && latestRate) {
                if (activeOverride.adjustment_type === 'percentage') {
                    displayRate = latestRate.rate * (1 + activeOverride.adjustment_value / 100);
                } else {
                    displayRate = latestRate.rate + activeOverride.adjustment_value;
                }
                displayInverse = 1 / displayRate;
            }
            rateSource = 'override';
            overrideInfo = {
                type: activeOverride.override_type,
                value: activeOverride.override_type === 'fixed' 
                    ? activeOverride.fixed_rate 
                    : `${activeOverride.adjustment_value > 0 ? '+' : ''}${activeOverride.adjustment_value}${activeOverride.adjustment_type === 'percentage' ? '%' : ''}`,
                cease_date: activeOverride.cease_date
            };
        }
        
        const isStale = !isOverride && latestRate && 
            new Date(latestRate.effective_date) < new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        
        const isUsingFallback = syncStatus.value.is_using_fallback && 
            currencyCode === syncStatus.value.fallback_currency;
        
        return {
            ...currency,
            code: currencyCode,
            current_rate: displayRate,
            inverse_rate: displayInverse,
            last_updated: latestRate?.created_at || latestRate?.updated_at,
            source: rateSource,
            is_stale: isStale,
            is_pending: latestRate?.source === 'import' && !latestRate?.is_approved,
            is_override: isOverride,
            override_info: overrideInfo,
            history_count: allRates.length,
            has_fallback: !!fallback,
            fallback_type: fallback?.fallback_type,
            is_using_fallback: isUsingFallback
        };
    });
});

const formattedRates = computed(() => {
    return exchangeRates.value.map(rate => ({
        ...rate,
        formatted_rate: formatMoney(rate.rate, { currency: '' }),
        formatted_inverse: formatMoney(rate.inverse_rate, { currency: '' }),
        status: getStatusBadge(rate)
    }));
});

// Methods
const getStatusBadge = (rate) => {
    if (rate.source === 'import' && !rate.is_approved) {
        return { label: 'Pending', severity: 'warning' };
    }
    if (new Date(rate.effective_date) > new Date()) {
        return { label: 'Future', severity: 'info' };
    }
    if (new Date(rate.created_at) < new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)) {
        return { label: 'Stale', severity: 'danger' };
    }
    return { label: 'Active', severity: 'success' };
};

// Exchange rate operations
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

const saveExchangeRate = async () => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        
        // Validate
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
                detail: 'Please enter a valid exchange rate',
                life: 3000
            });
            return;
        }
        
        // Auto-calculate inverse rate if not provided
        if (!rateForm.value.inverse_rate && rateForm.value.rate) {
            rateForm.value.inverse_rate = 1 / rateForm.value.rate;
        }
        
        const url = rateForm.value.id 
            ? `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/${rateForm.value.id}`
            : `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates`;
        
        await http.post(url, rateForm.value);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Exchange rate ${rateForm.value.id ? 'updated' : 'created'} successfully`,
            life: 3000
        });
        
        showAddRateModal.value = false;
        resetRateForm();
        await fetchExchangeRates();
        emit('refresh');
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

const resetRateForm = () => {
    rateForm.value = {
        id: null,
        from_currency: props.baseCurrency?.code || null,
        to_currency: null,
        rate: null,
        inverse_rate: null,
        effective_date: new Date().toISOString().split('T')[0],
        cease_date: null,
        source: 'manual',
        notes: '',
        is_approved: true,
        is_override: false,
        override_type: null,
        adjustment_value: null,
        adjustment_type: null
    };
};

// Override management
const saveRateOverride = async () => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        
        // Validate
        if (!overrideForm.value.from_currency || !overrideForm.value.to_currency) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please select both currencies',
                life: 3000
            });
            return;
        }
        
        if (overrideForm.value.override_type === 'fixed' && !overrideForm.value.fixed_rate) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please enter a fixed rate',
                life: 3000
            });
            return;
        }
        
        if (overrideForm.value.override_type === 'adjustment' && !overrideForm.value.adjustment_value) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please enter an adjustment value',
                life: 3000
            });
            return;
        }
        
        const url = overrideForm.value.id 
            ? `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/overrides/${overrideForm.value.id}`
            : `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/overrides`;
        
        await http.post(url, overrideForm.value);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Rate override ${overrideForm.value.id ? 'updated' : 'created'} successfully`,
            life: 3000
        });
        
        showOverrideModal.value = false;
        resetOverrideForm();
        await fetchRateOverrides();
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: err.response?.data?.message || 'Failed to save rate override',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const resetOverrideForm = () => {
    overrideForm.value = {
        id: null,
        from_currency: props.baseCurrency?.code || null,
        to_currency: null,
        override_type: 'fixed',
        fixed_rate: null,
        adjustment_type: 'percentage',
        adjustment_value: null,
        effective_date: new Date().toISOString().split('T')[0],
        cease_date: null,
        notes: ''
    };
};

const fetchRateOverrides = async () => {
    if (!props.baseCurrency?.code) return;
    
    try {
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/overrides`, {
            params: {
                base_currency: props.baseCurrency.code
            }
        });
        rateOverrides.value = response.data.data;
    } catch (err) {
        console.error('Failed to fetch rate overrides:', err);
    }
};

const deleteRateOverride = async (override) => {
    confirm.require({
        message: 'Are you sure you want to delete this rate override?',
        header: 'Confirm Deletion',
        icon: 'fa-solid fa-triangle-exclamation',
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                await http.delete(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/overrides/${override.id}`);
                
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Rate override deleted successfully',
                    life: 3000
                });
                
                await fetchRateOverrides();
                await fetchExchangeRates();
                emit('refresh');
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete rate override',
                    life: 3000
                });
            }
        }
    });
};

// Fallback management
const saveFallbackRate = async () => {
    if (!canManageSystem.value) return;
    
    try {
        saving.value = true;
        
        if (!fallbackForm.value.currency_pair) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please select a currency pair',
                life: 3000
            });
            return;
        }
        
        if (fallbackForm.value.fallback_type === 'static' && !fallbackForm.value.static_rate) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please enter a static fallback rate',
                life: 3000
            });
            return;
        }
        
        const [fromCurrency, toCurrency] = fallbackForm.value.currency_pair.split('/');
        
        await http.post(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/fallbacks`, {
            from_currency: fromCurrency,
            to_currency: toCurrency,
            fallback_type: fallbackForm.value.fallback_type,
            static_rate: fallbackForm.value.static_rate,
            max_age_hours: fallbackForm.value.max_age_hours
        });
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Fallback rate configured successfully',
            life: 3000
        });
        
        showFallbackModal.value = false;
        resetFallbackForm();
        await fetchFallbackRates();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: err.response?.data?.message || 'Failed to save fallback rate',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const resetFallbackForm = () => {
    fallbackForm.value = {
        currency_pair: null,
        fallback_type: 'last_successful',
        static_rate: null,
        max_age_hours: 72
    };
};

const fetchFallbackRates = async () => {
    try {
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/fallbacks`);
        fallbackRates.value = response.data.data;
    } catch (err) {
        console.error('Failed to fetch fallback rates:', err);
    }
};

// Enhanced sync with fallback
const syncRatesWithFallback = async (source = null) => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        const syncSource = source || automationSettings.value.primary_source;
        
        const response = await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/sync`,
            { 
                source: syncSource,
                use_fallback: true,
                fallback_behavior: automationSettings.value.fallback_behavior
            }
        );
        
        // Update sync status
        syncStatus.value = {
            last_sync: new Date(),
            last_success: response.data.success ? new Date() : syncStatus.value.last_success,
            last_error: response.data.error || null,
            is_using_fallback: response.data.used_fallback || false,
            fallback_currency: response.data.fallback_currency || null,
            fallback_reason: response.data.fallback_reason || null
        };
        
        if (response.data.used_fallback) {
            toast.add({
                severity: 'warn',
                summary: 'Using Fallback Rates',
                detail: response.data.fallback_reason || 'Primary source failed, using fallback rates',
                life: 5000
            });
        } else if (response.data.error) {
            toast.add({
                severity: 'error',
                summary: 'Sync Partially Failed',
                detail: response.data.error,
                life: 5000
            });
        } else {
            toast.add({
                severity: 'success',
                summary: 'Sync Complete',
                detail: response.data.message || 'Exchange rates synchronized successfully',
                life: 3000
            });
        }
        
        // Log the sync attempt
        await logSyncAttempt({
            source: syncSource,
            success: response.data.success,
            error: response.data.error,
            used_fallback: response.data.used_fallback,
            fallback_reason: response.data.fallback_reason
        });
        
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Sync Error',
            detail: err.response?.data?.message || 'Failed to sync exchange rates',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const logSyncAttempt = async (logData) => {
    try {
        await http.post(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/sync-logs`, logData);
    } catch (err) {
        console.error('Failed to log sync attempt:', err);
    }
};

const fetchErrorLogs = async () => {
    try {
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/error-logs`);
        errorLogs.value = response.data.data;
        showErrorLogModal.value = true;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load error logs',
            life: 3000
        });
    }
};

// Custom source management
const saveCustomSource = async () => {
    if (!canManageSystem.value) return;
    
    try {
        saving.value = true;
        
        // Validate
        if (!sourceConfig.value.name || !sourceConfig.value.endpoint) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please fill in all required fields',
                life: 3000
            });
            return;
        }
        
        // Test the source configuration
        const testResponse = await http.post(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/test-source`, sourceConfig.value);
        
        if (testResponse.data.success) {
            // Save the configuration
            await http.post(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/custom-sources`, sourceConfig.value);
            
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Custom source configured successfully',
                life: 3000
            });
            
            showSourceConfigModal.value = false;
            resetSourceConfig();
            await fetchCustomSources();
        } else {
            toast.add({
                severity: 'error',
                summary: 'Configuration Error',
                detail: testResponse.data.error || 'Failed to connect to the source',
                life: 5000
            });
        }
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: err.response?.data?.message || 'Failed to save custom source',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const resetSourceConfig = () => {
    sourceConfig.value = {
        name: '',
        endpoint: '',
        api_key: '',
        rate_path: 'rates',
        from_field: 'from',
        to_field: 'to',
        rate_field: 'rate',
        date_field: 'date',
        headers: '{}'
    };
};

const fetchCustomSources = async () => {
    try {
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/custom-sources`);
        customSources.value = response.data.data;
    } catch (err) {
        console.error('Failed to fetch custom sources:', err);
    }
};

const deleteCustomSource = async (source) => {
    confirm.require({
        message: `Are you sure you want to delete the "${source.name}" source?`,
        header: 'Confirm Deletion',
        icon: 'fa-solid fa-triangle-exclamation',
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                await http.delete(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/custom-sources/${source.id}`);
                
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Custom source deleted successfully',
                    life: 3000
                });
                
                await fetchCustomSources();
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete custom source',
                    life: 3000
                });
            }
        }
    });
};

const deleteFallbackRate = async (fallback) => {
    confirm.require({
        message: 'Are you sure you want to delete this fallback configuration?',
        header: 'Confirm Deletion',
        icon: 'fa-solid fa-triangle-exclamation',
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                await http.delete(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/fallbacks/${fallback.id}`);
                
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Fallback configuration deleted',
                    life: 3000
                });
                
                await fetchFallbackRates();
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete fallback',
                    life: 3000
                });
            }
        }
    });
};

const testCustomSource = async () => {
    if (!sourceConfig.value.endpoint) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please enter an endpoint URL',
            life: 3000
        });
        return;
    }
    
    try {
        saving.value = true;
        const response = await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/test-source`,
            sourceConfig.value
        );
        
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Connection Successful',
                detail: `Successfully connected and fetched ${response.data.rates_count} rates`,
                life: 3000
            });
        } else {
            toast.add({
                severity: 'error',
                summary: 'Connection Failed',
                detail: response.data.error || 'Failed to connect to the source',
                life: 5000
            });
        }
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Test Failed',
            detail: err.response?.data?.message || 'Failed to test source',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const clearErrorLogs = async () => {
    try {
        await http.delete(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/error-logs`);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Error logs cleared',
            life: 3000
        });
        
        errorLogs.value = [];
        showErrorLogModal.value = false;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to clear error logs',
            life: 3000
        });
    }
};

// History management
const fetchRateHistory = async (currencyPair) => {
    if (!currencyPair) return;
    
    try {
        loading.value = true;
        const response = await http.get(`/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/history`, {
            params: {
                from_currency: currencyPair.from,
                to_currency: currencyPair.to
            }
        });
        rateHistory.value = response.data.data;
        showHistoryModal.value = true;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load rate history',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Import operations
const handleFileUpload = async (event) => {
    const file = event.files[0];
    if (!file) return;
    
    try {
        const formData = new FormData();
        formData.append('file', file);
        
        loading.value = true;
        const response = await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/import/preview`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );
        
        importPreview.value = response.data.preview;
        importSummary.value = response.data.summary;
        
        if (importSummary.value.errors > 0) {
            toast.add({
                severity: 'warn',
                summary: 'Import Warnings',
                detail: `${importSummary.value.errors} rows have errors`,
                life: 5000
            });
        }
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Upload Error',
            detail: 'Failed to process file',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const confirmImport = async () => {
    if (!importFile.value) return;
    
    try {
        saving.value = true;
        const formData = new FormData();
        formData.append('file', importFile.value);
        
        const response = await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/import`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );
        
        toast.add({
            severity: 'success',
            summary: 'Import Complete',
            detail: `Created ${response.data.created}, updated ${response.data.updated}, skipped ${response.data.skipped}`,
            life: 5000
        });
        
        showImportModal.value = false;
        importFile.value = null;
        importPreview.value = [];
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Import Error',
            detail: 'Failed to import exchange rates',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Automation settings
const saveAutomationSettings = async () => {
    if (!canManageSystem.value) return;
    
    try {
        saving.value = true;
        await http.put(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/automation`,
            automationSettings.value
        );
        
        toast.add({
            severity: 'success',
            summary: 'Settings Saved',
            detail: 'Automation settings updated successfully',
            life: 3000
        });
        
        showAutomationModal.value = false;
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save automation settings',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Conversion helper
const calculateConversion = () => {
    if (!conversionHelper.value.amount || 
        !conversionHelper.value.from_currency || 
        !conversionHelper.value.to_currency) {
        conversionHelper.value.result = null;
        return;
    }
    
    const rate = exchangeRates.value.find(r => 
        r.from_currency === conversionHelper.value.from_currency &&
        r.to_currency === conversionHelper.value.to_currency &&
        r.effective_date <= new Date().toISOString().split('T')[0]
    );
    
    if (rate) {
        conversionHelper.value.result = conversionHelper.value.amount * rate.rate;
    } else {
        // Try inverse
        const inverseRate = exchangeRates.value.find(r => 
            r.from_currency === conversionHelper.value.to_currency &&
            r.to_currency === conversionHelper.value.from_currency &&
            r.effective_date <= new Date().toISOString().split('T')[0]
        );
        
        if (inverseRate) {
            conversionHelper.value.result = conversionHelper.value.amount / inverseRate.rate;
        } else {
            conversionHelper.value.result = null;
        }
    }
};

// Bulk operations
const approveSelectedRates = async () => {
    if (selectedRates.value.length === 0) return;
    
    try {
        saving.value = true;
        await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/bulk-approve`,
            { rate_ids: selectedRates.value.map(r => r.id) }
        );
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `${selectedRates.value.length} rates approved`,
            life: 3000
        });
        
        selectedRates.value = [];
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to approve rates',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

const deleteSelectedRates = async () => {
    if (selectedRates.value.length === 0) return;
    
    confirm.require({
        message: `Are you sure you want to delete ${selectedRates.value.length} exchange rates?`,
        header: 'Confirm Deletion',
        icon: 'fa-solid fa-triangle-exclamation',
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                saving.value = true;
                await http.delete(
                    `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/bulk-delete`,
                    { data: { rate_ids: selectedRates.value.map(r => r.id) } }
                );
                
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `${selectedRates.value.length} rates deleted`,
                    life: 3000
                });
                
                selectedRates.value = [];
                await fetchExchangeRates();
                emit('refresh');
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete rates',
                    life: 3000
                });
            } finally {
                saving.value = false;
            }
        }
    });
};

// Sync from external source
const syncRates = async (source = 'ecb') => {
    if (!canEditExchange.value) return;
    
    try {
        saving.value = true;
        const response = await http.post(
            `/api/companies/${page.props.auth.currentCompany.id}/currencies/exchange-rates/sync`,
            { source }
        );
        
        toast.add({
            severity: 'success',
            summary: 'Sync Complete',
            detail: response.data.message || 'Exchange rates synchronized successfully',
            life: 3000
        });
        
        await fetchExchangeRates();
        emit('refresh');
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Sync Error',
            detail: err.response?.data?.message || 'Failed to sync exchange rates',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Initialize
onMounted(async () => {
    if (props.exchangeRates.length === 0) {
        await fetchExchangeRates();
    }
    
    // Fetch additional data
    await fetchRateOverrides();
    await fetchFallbackRates();
    await fetchCustomSources();
    
    // Initialize conversion helper defaults
    conversionHelper.value.from_currency = props.baseCurrency?.code || null;
});

// Watch for prop changes
watch(() => props.exchangeRates, (newRates) => {
    if (newRates.length > 0) {
        exchangeRates.value = newRates;
    }
}, { immediate: true });
</script>

<template>
    <div class="space-y-6">
        
        <!-- Header with actions -->
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Exchange Rate Management</span>
                    <div class="flex gap-2">
                        <Button
                            v-if="canEditExchange"
                            label="Sync Now"
                            size="small"
                            :loading="saving"
                            @click="syncRates"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="sync" />
                            </template>
                        </Button>
                        <Button
                            v-if="canEditExchange"
                            label="Add Rate"
                            size="small"
                            @click="
                                showAddRateModal = true;
                                resetRateForm();
                            "
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="plus" />
                            </template>
                        </Button>
                        <Button
                            v-if="canManageSystem"
                            label="Automation"
                            size="small"
                            @click="showAutomationModal = true"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="cog" />
                            </template>
                        </Button>
                        <Button
                            label="Import"
                            size="small"
                            @click="showImportModal = true"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="file-import" />
                            </template>
                        </Button>
                        <Button
                            v-if="canEditExchange"
                            label="Override"
                            size="small"
                            @click="
                                showOverrideModal = true;
                                resetOverrideForm();
                            "
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="override" />
                            </template>
                        </Button>
                        <Button
                            v-if="canManageSystem"
                            label="Fallback"
                            size="small"
                            @click="showFallbackModal = true"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="shield-alt" />
                            </template>
                        </Button>
                        <Button
                            label="Convert"
                            size="small"
                            @click="
                                showConversionHelper = true;
                                conversionHelper.from_currency = baseCurrency?.code;
                            "
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="calculator" />
                            </template>
                        </Button>
                        <Button
                            v-if="syncStatus.last_error"
                            label="View Errors"
                            size="small"
                            severity="danger"
                            @click="fetchErrorLogs"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="exclamation-triangle" />
                            </template>
                        </Button>
                    </div>
                </div>
            </template>
            <template #subtitle>
                Manage and monitor exchange rates for multi-currency transactions
            </template>
        </Card>

        <!-- Error Status Banner -->
        <Message v-if="syncStatus.last_error || syncStatus.is_using_fallback" :severity="syncStatus.is_using_fallback ? 'warn' : 'error'" :closable="false">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium">
                        {{ syncStatus.is_using_fallback ? 'Using Fallback Rates' : 'Sync Error' }}
                    </div>
                    <div class="text-sm mt-1">
                        {{ syncStatus.fallback_reason || syncStatus.last_error }}
                        <span v-if="syncStatus.last_sync" class="ml-2">
                            (Last attempt: {{ formatDateTime(syncStatus.last_sync) }})
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        label="Retry Now"
                        size="small"
                        @click="syncRates"
                    />
                    <Button
                        label="View Details"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="fetchErrorLogs"
                    />
                </div>
            </div>
        </Message>

        <!-- Current Rates Overview -->
        <Card>
            <template #title>Current Exchange Rates</template>
            <template #content>
                <div v-if="loading" class="flex justify-center py-8">
                    <ProgressSpinner />
                </div>
                
                <div v-else-if="currenciesWithRates.length === 0" class="text-center py-12">
                    <div class="mb-4">
                        <FontAwesomeIcon 
                            icon="chart-line" 
                            class="text-6xl text-gray-300"
                        />
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">
                        No exchange rates configured
                    </h3>
                    <p class="text-gray-500 mb-6">
                        Add exchange rates to enable multi-currency transactions
                    </p>
                    <Button
                        v-if="canEditExchange"
                        label="Add Your First Rate"
                        icon="pi pi-plus"
                        @click="showAddRateModal = true"
                    />
                </div>
                
                <div v-else>
                    <!-- Status summary -->
                    <div class="mb-4 flex flex-wrap gap-4">
                        <div class="flex items-center gap-2">
                            <Badge value="Active" severity="success" />
                            <span class="text-sm text-gray-600">Up to date rates</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge value="Stale" severity="danger" />
                            <span class="text-sm text-gray-600">Older than 7 days</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge value="Pending" severity="warning" />
                            <span class="text-sm text-gray-600">Awaiting approval</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge value="Override" severity="info" />
                            <span class="text-sm text-gray-600">Custom rate applied</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge value="Fallback" severity="warning" />
                            <span class="text-sm text-gray-600">Using fallback rate</span>
                        </div>
                    </div>
                    
                    <!-- Rates grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="currency in currenciesWithRates"
                            :key="currency.code"
                            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                            :class="{
                                'border-yellow-300 dark:border-yellow-700': currency.is_pending,
                                'border-red-300 dark:border-red-700': currency.is_stale
                            }"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="text-lg font-semibold">
                                        {{ currency.currency?.name || currency.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ currency.code }}
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Badge
                                        v-if="currency.is_override"
                                        :value="currency.override_info?.type === 'fixed' ? `Fixed @ ${currency.override_info.value}` : `Adj ${currency.override_info.value}`"
                                        severity="info"
                                        size="small"
                                    />
                                    <Badge
                                        v-else-if="currency.is_using_fallback"
                                        value="Fallback"
                                        severity="warning"
                                        size="small"
                                    />
                                    <Badge
                                        v-else-if="currency.is_stale"
                                        value="Stale"
                                        severity="danger"
                                        size="small"
                                    />
                                    <Badge
                                        v-else-if="currency.is_pending"
                                        value="Pending"
                                        severity="warning"
                                        size="small"
                                    />
                                    <Badge
                                        v-else-if="currency.current_rate"
                                        value="Active"
                                        severity="success"
                                        size="small"
                                    />
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Rate:</span>
                                    <span v-if="currency.current_rate" class="font-medium">
                                        1 {{ baseCurrency?.code }} = {{ formatMoney(currency.current_rate, { currency: '' }) }} {{ currency.code }}
                                    </span>
                                    <span v-else class="text-gray-400">Not set</span>
                                </div>
                                
                                <div v-if="currency.current_rate" class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Inverse:</span>
                                    <span class="font-medium">
                                        1 {{ currency.code }} = {{ formatMoney(currency.inverse_rate, { currency: '' }) }} {{ baseCurrency?.code }}
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Source:</span>
                                    <div class="flex items-center gap-2">
                                        <Chip
                                            :label="currency.source === 'override' ? 'Override' : currency.source"
                                            size="small"
                                            :class="{
                                                'bg-blue-100 text-blue-800': currency.source === 'ecb',
                                                'bg-green-100 text-green-800': currency.source === 'manual',
                                                'bg-yellow-100 text-yellow-800': currency.source === 'import',
                                                'bg-purple-100 text-purple-800': currency.source === 'override',
                                                'bg-orange-100 text-orange-800': currency.is_using_fallback
                                            }"
                                        />
                                        <span v-if="currency.override_info?.cease_date" class="text-xs text-gray-500">
                                            until {{ formatDate(currency.override_info.cease_date) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-if="currency.last_updated" class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Updated:</span>
                                    <span class="text-xs text-gray-500">
                                        {{ formatDateTime(currency.last_updated) }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <Button
                                        size="small"
                                        text
                                        severity="secondary"
                                        @click="fetchRateHistory({ 
                                            from: baseCurrency?.code, 
                                            to: currency.code 
                                        })"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon icon="history" />
                                        </template>
                                        <span class="ml-1">History ({{ currency.history_count }})</span>
                                    </Button>
                                    
                                    <Button
                                        v-if="canEditExchange"
                                        size="small"
                                        text
                                        severity="info"
                                        @click="
                                            showOverrideModal = true;
                                            overrideForm = {
                                                from_currency: baseCurrency?.code,
                                                to_currency: currency.code,
                                                override_type: 'fixed',
                                                effective_date: new Date().toISOString().split('T')[0]
                                            };
                                        "
                                        v-tooltip="currency.is_override ? 'Edit Override' : 'Add Override'"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon :icon="currency.is_override ? 'edit' : 'plus'" />
                                        </template>
                                    </Button>
                                    
                                    <Button
                                        v-if="canEditExchange"
                                        size="small"
                                        text
                                        @click="
                                            showAddRateModal = true;
                                            rateForm = {
                                                from_currency: baseCurrency?.code,
                                                to_currency: currency.code,
                                                rate: currency.current_rate,
                                                effective_date: new Date().toISOString().split('T')[0],
                                                source: 'manual'
                                            };
                                        "
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon icon="edit" />
                                        </template>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Detailed Rates Table -->
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>All Exchange Rates</span>
                    <div v-if="canEditExchange && selectedRates.length > 0" class="flex gap-2">
                        <Button
                            label="Approve Selected"
                            size="small"
                            severity="success"
                            @click="approveSelectedRates"
                        />
                        <Button
                            label="Delete Selected"
                            size="small"
                            severity="danger"
                            @click="deleteSelectedRates"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="formattedRates"
                    :paginator="formattedRates.length > 10"
                    :rows="10"
                    stripedRows
                    responsiveLayout="scroll"
                    v-model:selection="selectedRates"
                    selectionMode="multiple"
                    dataKey="id"
                >
                    <Column selectionMode="multiple" style="width: 50px" />
                    <Column field="from_currency" header="From" style="width: 100px" />
                    <Column field="to_currency" header="To" style="width: 100px" />
                    <Column field="rate" header="Rate">
                        <template #body="{ data }">
                            {{ formatMoney(data.rate, { currency: '' }) }}
                        </template>
                    </Column>
                    <Column field="inverse_rate" header="Inverse">
                        <template #body="{ data }">
                            {{ formatMoney(data.inverse_rate, { currency: '' }) }}
                        </template>
                    </Column>
                    <Column field="effective_date" header="Effective Date">
                        <template #body="{ data }">
                            {{ formatDate(data.effective_date) }}
                        </template>
                    </Column>
                    <Column field="source" header="Source" />
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.status.label"
                                :severity="data.status.severity"
                                size="small"
                            />
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 120px">
                        <template #body="{ data }">
                            <Button
                                size="small"
                                text
                                @click="
                                    showAddRateModal = true;
                                    rateForm = { ...data };
                                "
                                v-if="canEditExchange"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="edit" />
                                </template>
                            </Button>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Add/Edit Rate Modal -->
        <Dialog
            v-model:visible="showAddRateModal"
            modal
            :header="rateForm.id ? 'Edit Exchange Rate' : 'Add Exchange Rate'"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">From Currency</label>
                        <Dropdown
                            v-model="rateForm.from_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                            :disabled="!!rateForm.id"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">To Currency</label>
                        <Dropdown
                            v-model="rateForm.to_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                        />
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Exchange Rate</label>
                        <InputNumber
                            v-model="rateForm.rate"
                            :min="0"
                            :minFractionDigits="6"
                            :maxFractionDigits="6"
                            placeholder="0.000000"
                            class="w-full"
                            @update:modelValue="() => {
                                if (rateForm.rate) {
                                    rateForm.inverse_rate = 1 / rateForm.rate;
                                }
                            }"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Inverse Rate</label>
                        <InputNumber
                            v-model="rateForm.inverse_rate"
                            :min="0"
                            :minFractionDigits="6"
                            :maxFractionDigits="6"
                            placeholder="0.000000"
                            class="w-full"
                            @update:modelValue="() => {
                                if (rateForm.inverse_rate) {
                                    rateForm.rate = 1 / rateForm.inverse_rate;
                                }
                            }"
                        />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Effective Date</label>
                    <Calendar
                        v-model="rateForm.effective_date"
                        dateFormat="yy-mm-dd"
                        class="w-full"
                        :minDate="new Date()"
                    />
                    <small class="text-gray-500">Future dates allowed for scheduled rates</small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Source</label>
                    <Dropdown
                        v-model="rateForm.source"
                        :options="[
                            { label: 'Manual Entry', value: 'manual' },
                            { label: 'ECB Import', value: 'ecb' },
                            { label: 'File Import', value: 'import' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="rateForm.source === 'import' && canManageSystem">
                    <label class="flex items-center gap-2">
                        <Checkbox
                            v-model="rateForm.is_approved"
                            binary
                        />
                        <span class="text-sm">Approve this rate</span>
                    </label>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="rateForm.notes"
                        rows="3"
                        class="w-full"
                        placeholder="Optional notes about this rate..."
                    />
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showAddRateModal = false"
                    />
                    <Button
                        :label="rateForm.id ? 'Update Rate' : 'Add Rate'"
                        :disabled="!rateForm.rate"
                        :loading="saving"
                        @click="saveExchangeRate"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Rate History Modal -->
        <Dialog
            v-model:visible="showHistoryModal"
            modal
            header="Exchange Rate History"
            :style="{ width: '800px' }"
        >
            <div class="space-y-4">
                <div v-if="selectedCurrencyPair" class="text-sm text-gray-600">
                    Showing history for {{ selectedCurrencyPair.from }} → {{ selectedCurrencyPair.to }}
                </div>
                
                <DataTable
                    :value="rateHistory"
                    :paginator="rateHistory.length > 10"
                    :rows="10"
                    stripedRows
                    responsiveLayout="scroll"
                >
                    <Column field="rate" header="Rate">
                        <template #body="{ data }">
                            {{ formatMoney(data.rate, { currency: '' }) }}
                        </template>
                    </Column>
                    <Column field="effective_date" header="Effective Date">
                        <template #body="{ data }">
                            {{ formatDate(data.effective_date) }}
                        </template>
                    </Column>
                    <Column field="created_at" header="Created">
                        <template #body="{ data }">
                            {{ formatDateTime(data.created_at) }}
                        </template>
                    </Column>
                    <Column field="source" header="Source" />
                    <Column field="created_by" header="Created By" />
                    <Column field="notes" header="Notes" />
                </DataTable>
            </div>
        </Dialog>

        <!-- Import Modal -->
        <Dialog
            v-model:visible="showImportModal"
            modal
            header="Import Exchange Rates"
            :style="{ width: '900px' }"
            @hide="importFile = null; importPreview = []"
        >
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">CSV Format Requirements</h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <li>• Required columns: from_currency, to_currency, rate, effective_date (YYYY-MM-DD)</li>
                        <li>• Optional columns: source, notes</li>
                        <li>• Date format: YYYY-MM-DD</li>
                        <li>• Rate must be a positive number with up to 6 decimal places</li>
                    </ul>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Upload CSV File</label>
                    <FileUpload
                        mode="basic"
                        accept=".csv"
                        :auto="false"
                        chooseLabel="Choose CSV"
                        @select="handleFileUpload"
                        :disabled="loading"
                    />
                </div>
                
                <div v-if="importPreview.length > 0">
                    <Divider />
                    <h4 class="font-medium mb-3">Import Preview</h4>
                    
                    <div class="mb-4 flex gap-4 text-sm">
                        <div class="text-green-600">
                            <FontAwesomeIcon icon="check-circle" />
                            New: {{ importSummary.new }}
                        </div>
                        <div class="text-blue-600">
                            <FontAwesomeIcon icon="edit" />
                            Updated: {{ importSummary.updated }}
                        </div>
                        <div class="text-gray-600">
                            <FontAwesomeIcon icon="minus-circle" />
                            Skipped: {{ importSummary.skipped }}
                        </div>
                        <div v-if="importSummary.errors > 0" class="text-red-600">
                            <FontAwesomeIcon icon="exclamation-triangle" />
                            Errors: {{ importSummary.errors }}
                        </div>
                    </div>
                    
                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <DataTable
                            :value="importPreview"
                            stripedRows
                            responsiveLayout="scroll"
                        >
                            <Column field="from_currency" header="From" />
                            <Column field="to_currency" header="To" />
                            <Column field="rate" header="Rate" />
                            <Column field="effective_date" header="Date" />
                            <Column field="status" header="Status">
                                <template #body="{ data }">
                                    <Badge
                                        :value="data.status"
                                        :severity="data.status === 'error' ? 'danger' : 'success'"
                                        size="small"
                                    />
                                </template>
                            </Column>
                            <Column v-if="importSummary.errors > 0" field="error" header="Error" />
                        </DataTable>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showImportModal = false"
                    />
                    <Button
                        label="Import Rates"
                        severity="success"
                        :disabled="!importPreview.length || importSummary.errors > 0"
                        :loading="saving"
                        @click="confirmImport"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="file-import" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>

        <!-- Automation Settings Modal -->
        <Dialog
            v-model:visible="showAutomationModal"
            modal
            header="Automation Settings"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">Automatic Sync</span>
                        <ToggleButton
                            v-model="automationSettings.auto_sync_enabled"
                            onLabel="Enabled"
                            offLabel="Disabled"
                        />
                    </label>
                    <p class="text-xs text-gray-500">
                        Automatically sync exchange rates from external sources
                    </p>
                </div>
                
                <div v-if="automationSettings.auto_sync_enabled">
                    <label class="block text-sm font-medium mb-2">Sync Frequency</label>
                    <Dropdown
                        v-model="automationSettings.sync_frequency"
                        :options="[
                            { label: 'Hourly', value: 'hourly' },
                            { label: 'Daily', value: 'daily' },
                            { label: 'Weekly', value: 'weekly' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="automationSettings.auto_sync_enabled">
                    <label class="block text-sm font-medium mb-2">Data Source</label>
                    <Dropdown
                        v-model="automationSettings.sync_source"
                        :options="[
                            { label: 'European Central Bank', value: 'ecb' },
                            { label: 'XE.com', value: 'xe' },
                            { label: 'Open Exchange Rates', value: 'oer' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="flex items-center gap-2 mb-2">
                        <Checkbox
                            v-model="automationSettings.notify_on_changes"
                            binary
                        />
                        <span class="text-sm font-medium">Notify on rate changes</span>
                    </label>
                    <p class="text-xs text-gray-500">
                        Send notifications when exchange rates are updated
                    </p>
                </div>
                
                <div>
                    <label class="flex items-center gap-2 mb-2">
                        <Checkbox
                            v-model="automationSettings.require_approval_for_changes"
                            binary
                        />
                        <span class="text-sm font-medium">Require approval for imported rates</span>
                    </label>
                    <p class="text-xs text-gray-500">
                        Imported rates require manual approval before becoming active
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Maximum Variance Alert (%)</label>
                    <InputNumber
                        v-model="automationSettings.max_variance_percentage"
                        :min="1"
                        :max="100"
                        suffix="%"
                        class="w-full"
                    />
                    <p class="text-xs text-gray-500">
                        Alert when rates change by more than this percentage
                    </p>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showAutomationModal = false"
                    />
                    <Button
                        label="Save Settings"
                        :loading="saving"
                        @click="saveAutomationSettings"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Conversion Helper Modal -->
        <Dialog
            v-model:visible="showConversionHelper"
            modal
            header="Currency Converter"
            :style="{ width: '450px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Amount</label>
                    <InputNumber
                        v-model="conversionHelper.amount"
                        :min="0"
                        :maxFractionDigits="2"
                        class="w-full"
                        @update:modelValue="calculateConversion"
                    />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">From</label>
                        <Dropdown
                            v-model="conversionHelper.from_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                            @update:modelValue="calculateConversion"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">To</label>
                        <Dropdown
                            v-model="conversionHelper.to_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                            @update:modelValue="calculateConversion"
                        />
                    </div>
                </div>
                
                <div v-if="conversionHelper.result !== null" class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                        {{ formatMoney(conversionHelper.result, { currency: conversionHelper.to_currency }) }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        {{ formatMoney(conversionHelper.amount, { currency: conversionHelper.from_currency }) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-2">
                        Rate: 1 {{ conversionHelper.from_currency }} = {{ 
                            formatMoney(
                                conversionHelper.result / conversionHelper.amount, 
                                { currency: '' }
                            ) 
                        }} {{ conversionHelper.to_currency }}
                    </div>
                </div>
                
                <div v-else class="text-center py-8 text-gray-500">
                    <FontAwesomeIcon icon="calculator" class="text-4xl mb-2" />
                    <p>Enter amount and select currencies to convert</p>
                </div>
            </div>
        </Dialog>

        <!-- Override Modal -->
        <Dialog
            v-model:visible="showOverrideModal"
            modal
            :header="overrideForm.id ? 'Edit Rate Override' : 'Add Rate Override'"
            :style="{ width: '550px' }"
        >
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Override:</strong> Custom rates take precedence over live rates. 
                        Set effective/cease dates to control when the override applies.
                    </p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">From Currency</label>
                        <Dropdown
                            v-model="overrideForm.from_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                            :disabled="!!overrideForm.id"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">To Currency</label>
                        <Dropdown
                            v-model="overrideForm.to_currency"
                            :options="currenciesWithRates"
                            optionLabel="code"
                            optionValue="code"
                            placeholder="Select currency"
                            class="w-full"
                            :disabled="!!overrideForm.id"
                        />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Override Type</label>
                    <SelectButton
                        v-model="overrideForm.override_type"
                        :options="[
                            { label: 'Fixed Rate', value: 'fixed' },
                            { label: 'Adjustment', value: 'adjustment' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="overrideForm.override_type === 'fixed'">
                    <label class="block text-sm font-medium mb-2">Fixed Rate</label>
                    <InputNumber
                        v-model="overrideForm.fixed_rate"
                        :min="0"
                        :minFractionDigits="6"
                        :maxFractionDigits="6"
                        placeholder="0.000000"
                        class="w-full"
                    />
                    <small class="text-gray-500">
                        This exact rate will be used regardless of live rates
                    </small>
                </div>
                
                <div v-else-if="overrideForm.override_type === 'adjustment'">
                    <div>
                        <label class="block text-sm font-medium mb-2">Adjustment Type</label>
                        <SelectButton
                            v-model="overrideForm.adjustment_type"
                            :options="[
                                { label: 'Percentage (%)', value: 'percentage' },
                                { label: 'Fixed Amount', value: 'fixed' }
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                    
                    <div class="mt-3">
                        <label class="block text-sm font-medium mb-2">
                            Adjustment Value
                            <span class="text-gray-500 font-normal">
                                ({{ overrideForm.adjustment_type === 'percentage' ? '+' : '±' }})
                            </span>
                        </label>
                        <InputNumber
                            v-model="overrideForm.adjustment_value"
                            :minFractionDigits="overrideForm.adjustment_type === 'percentage' ? 2 : 6"
                            :maxFractionDigits="overrideForm.adjustment_type === 'percentage' ? 2 : 6"
                            :prefix="overrideForm.adjustment_type === 'percentage' ? '' : baseCurrency?.code + ' '"
                            :suffix="overrideForm.adjustment_type === 'percentage' ? '%' : ''"
                            placeholder="0.00"
                            class="w-full"
                        />
                        <small class="text-gray-500">
                            {{ overrideForm.adjustment_type === 'percentage' 
                                ? 'Positive values increase the rate, negative values decrease it' 
                                : 'Fixed amount to add/subtract from the live rate'
                            }}
                        </small>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Effective Date</label>
                        <Calendar
                            v-model="overrideForm.effective_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                            :minDate="new Date()"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Cease Date (Optional)</label>
                        <Calendar
                            v-model="overrideForm.cease_date"
                            dateFormat="yy-mm-dd"
                            class="w-full"
                            :minDate="overrideForm.effective_date"
                        />
                        <small class="text-gray-500">Leave blank for indefinite override</small>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="overrideForm.notes"
                        rows="3"
                        class="w-full"
                        placeholder="Reason for override..."
                    />
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showOverrideModal = false"
                    />
                    <Button
                        :label="overrideForm.id ? 'Update Override' : 'Create Override'"
                        :disabled="!overrideForm.from_currency || !overrideForm.to_currency || 
                            (overrideForm.override_type === 'fixed' && !overrideForm.fixed_rate) ||
                            (overrideForm.override_type === 'adjustment' && !overrideForm.adjustment_value)"
                        :loading="saving"
                        @click="saveRateOverride"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Fallback Modal -->
        <Dialog
            v-model:visible="showFallbackModal"
            modal
            header="Configure Fallback Rates"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
                    <p class="text-sm text-orange-800 dark:text-orange-200">
                        <strong>Fallback Rates:</strong> Used when primary sources fail. 
                        Configure per-currency pair fallback strategies.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Currency Pair</label>
                    <Dropdown
                        v-model="fallbackForm.currency_pair"
                        :options="currenciesWithRates.map(c => `${baseCurrency?.code}/${c.code}`)"
                        placeholder="Select currency pair"
                        class="w-full"
                        filter
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Fallback Type</label>
                    <SelectButton
                        v-model="fallbackForm.fallback_type"
                        :options="[
                            { label: 'Last Successful Rate', value: 'last_successful' },
                            { label: 'Static Rate', value: 'static' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="fallbackForm.fallback_type === 'static'">
                    <label class="block text-sm font-medium mb-2">Static Fallback Rate</label>
                    <InputNumber
                        v-model="fallbackForm.static_rate"
                        :min="0"
                        :minFractionDigits="6"
                        :maxFractionDigits="6"
                        placeholder="0.000000"
                        class="w-full"
                    />
                    <small class="text-gray-500">
                        This rate will be used when no other rates are available
                    </small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">
                        Maximum Age (hours)
                        <span class="text-gray-500 font-normal">- For "Last Successful" option</span>
                    </label>
                    <InputNumber
                        v-model="fallbackForm.max_age_hours"
                        :min="1"
                        :max="168"
                        class="w-full"
                    />
                    <small class="text-gray-500">
                        Don't use rates older than this many hours
                    </small>
                </div>
                
                <!-- Existing fallbacks -->
                <div v-if="fallbackRates.length > 0">
                    <Divider />
                    <h4 class="font-medium mb-3">Configured Fallbacks</h4>
                    <div class="space-y-2">
                        <div
                            v-for="fallback in fallbackRates"
                            :key="`${fallback.from_currency}/${fallback.to_currency}`"
                            class="flex items-center justify-between p-3 border rounded-lg"
                        >
                            <div>
                                <div class="font-medium">
                                    {{ fallback.from_currency }} → {{ fallback.to_currency }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ fallback.fallback_type === 'static' 
                                        ? `Static: ${fallback.static_rate}` 
                                        : `Last successful (< ${fallback.max_age_hours}h)`
                                    }}
                                </div>
                            </div>
                            <Button
                                size="small"
                                text
                                severity="danger"
                                @click="deleteFallbackRate(fallback)"
                                v-if="canManageSystem"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="trash" />
                                </template>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showFallbackModal = false"
                    />
                    <Button
                        label="Save Fallback"
                        :disabled="!fallbackForm.currency_pair || 
                            (fallbackForm.fallback_type === 'static' && !fallbackForm.static_rate)"
                        :loading="saving"
                        @click="saveFallbackRate"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Automation Settings Modal -->
        <Dialog
            v-model:visible="showAutomationModal"
            modal
            header="Automation & Source Settings"
            :style="{ width: '600px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">Automatic Sync</span>
                        <ToggleButton
                            v-model="automationSettings.auto_sync_enabled"
                            onLabel="Enabled"
                            offLabel="Disabled"
                        />
                    </label>
                    <p class="text-xs text-gray-500">
                        Automatically sync exchange rates from external sources
                    </p>
                </div>
                
                <div v-if="automationSettings.auto_sync_enabled">
                    <label class="block text-sm font-medium mb-2">Sync Frequency</label>
                    <Dropdown
                        v-model="automationSettings.sync_frequency"
                        :options="[
                            { label: 'Hourly', value: 'hourly' },
                            { label: 'Daily', value: 'daily' },
                            { label: 'Weekly', value: 'weekly' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="automationSettings.auto_sync_enabled">
                    <label class="block text-sm font-medium mb-2">Primary Source</label>
                    <Dropdown
                        v-model="automationSettings.primary_source"
                        :options="[
                            { label: 'European Central Bank', value: 'ecb' },
                            { label: 'XE.com', value: 'xe' },
                            { label: 'Open Exchange Rates', value: 'oer' },
                            ...customSources.map(s => ({ label: s.name, value: `custom_${s.id}` }))
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div v-if="automationSettings.auto_sync_enabled">
                    <label class="block text-sm font-medium mb-2">Secondary Source (Optional)</label>
                    <Dropdown
                        v-model="automationSettings.secondary_source"
                        :options="[
                            { label: 'None', value: null },
                            { label: 'European Central Bank', value: 'ecb' },
                            { label: 'XE.com', value: 'xe' },
                            { label: 'Open Exchange Rates', value: 'oer' },
                            ...customSources.map(s => ({ label: s.name, value: `custom_${s.id}` }))
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                    <p class="text-xs text-gray-500">Used if primary source fails</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Fallback Behavior</label>
                    <Dropdown
                        v-model="automationSettings.fallback_behavior"
                        :options="[
                            { label: 'Use last successful rate within age limit', value: 'last_successful' },
                            { label: 'Use static fallback rates', value: 'static' },
                            { label: 'Fail with error', value: 'fail' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="flex items-center gap-2 mb-2">
                        <Checkbox
                            v-model="automationSettings.notify_on_changes"
                            binary
                        />
                        <span class="text-sm font-medium">Notify on rate changes</span>
                    </label>
                    <p class="text-xs text-gray-500">
                        Send notifications when exchange rates are updated
                    </p>
                </div>
                
                <div>
                    <label class="flex items-center gap-2 mb-2">
                        <Checkbox
                            v-model="automationSettings.require_approval_for_changes"
                            binary
                        />
                        <span class="text-sm font-medium">Require approval for imported rates</span>
                    </label>
                    <p class="text-xs text-gray-500">
                        Imported rates require manual approval before becoming active
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Maximum Variance Alert (%)</label>
                    <InputNumber
                        v-model="automationSettings.max_variance_percentage"
                        :min="1"
                        :max="100"
                        suffix="%"
                        class="w-full"
                    />
                    <p class="text-xs text-gray-500">
                        Alert when rates change by more than this percentage
                    </p>
                </div>
                
                <!-- Custom Sources Section -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium">Custom Sources</h4>
                        <Button
                            label="Add Source"
                            size="small"
                            @click="
                                showSourceConfigModal = true;
                                resetSourceConfig();
                            "
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="plus" />
                            </template>
                        </Button>
                    </div>
                    
                    <div v-if="customSources.length === 0" class="text-center py-4 text-gray-500">
                        <p>No custom sources configured</p>
                    </div>
                    
                    <div v-else class="space-y-2">
                        <div
                            v-for="source in customSources"
                            :key="source.id"
                            class="flex items-center justify-between p-3 border rounded-lg"
                        >
                            <div>
                                <div class="font-medium">{{ source.name }}</div>
                                <div class="text-sm text-gray-600">{{ source.endpoint }}</div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    size="small"
                                    text
                                    @click="
                                        showSourceConfigModal = true;
                                        sourceConfig = { ...source };
                                    "
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="edit" />
                                    </template>
                                </Button>
                                <Button
                                    size="small"
                                    text
                                    severity="danger"
                                    @click="deleteCustomSource(source)"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="trash" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showAutomationModal = false"
                    />
                    <Button
                        label="Save Settings"
                        :loading="saving"
                        @click="saveAutomationSettings"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Source Configuration Modal -->
        <Dialog
            v-model:visible="showSourceConfigModal"
            modal
            header="Configure Custom Source"
            :style="{ width: '600px' }"
        >
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Source Name *</label>
                        <InputText
                            v-model="sourceConfig.name"
                            placeholder="My Custom API"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">API Key (Optional)</label>
                        <InputText
                            v-model="sourceConfig.api_key"
                            placeholder="your-api-key"
                            class="w-full"
                        />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Endpoint URL *</label>
                    <InputText
                        v-model="sourceConfig.endpoint"
                        placeholder="https://api.example.com/rates"
                        class="w-full"
                    />
                </div>
                
                <Divider align="left">
                    <div class="inline-flex items-center">
                        <span class="font-medium">Response Mapping</span>
                    </div>
                </Divider>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Rates Path</label>
                        <InputText
                            v-model="sourceConfig.rate_path"
                            placeholder="data.rates"
                            class="w-full"
                        />
                        <small class="text-gray-500">JSON path to rates array (e.g., data.rates)</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Date Field</label>
                        <InputText
                            v-model="sourceConfig.date_field"
                            placeholder="date"
                            class="w-full"
                        />
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">From Currency Field</label>
                        <InputText
                            v-model="sourceConfig.from_field"
                            placeholder="from"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">To Currency Field</label>
                        <InputText
                            v-model="sourceConfig.to_field"
                            placeholder="to"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Rate Field</label>
                        <InputText
                            v-model="sourceConfig.rate_field"
                            placeholder="rate"
                            class="w-full"
                        />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Additional Headers (JSON)</label>
                    <Textarea
                        v-model="sourceConfig.headers"
                        rows="3"
                        class="w-full"
                        placeholder='{"Authorization": "Bearer token"}'
                    />
                    <small class="text-gray-500">JSON format for additional request headers</small>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <strong>Note:</strong> The API should return rates in the format specified by your mapping. 
                        Test the configuration before saving.
                    </p>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-between items-center">
                    <Button
                        label="Test Connection"
                        severity="secondary"
                        outlined
                        :loading="saving"
                        @click="testCustomSource"
                    />
                    <div class="flex gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            outlined
                            @click="showSourceConfigModal = false"
                        />
                        <Button
                            label="Save Source"
                            :disabled="!sourceConfig.name || !sourceConfig.endpoint"
                            :loading="saving"
                            @click="saveCustomSource"
                        />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- Error Logs Modal -->
        <Dialog
            v-model:visible="showErrorLogModal"
            modal
            header="Sync Error Logs"
            :style="{ width: '800px' }"
        >
            <div class="space-y-4">
                <div v-if="errorLogs.length === 0" class="text-center py-8 text-gray-500">
                    <FontAwesomeIcon icon="check-circle" class="text-4xl mb-2 text-green-500" />
                    <p>No errors found</p>
                </div>
                
                <div v-else>
                    <div class="mb-4 text-sm text-gray-600">
                        Showing {{ errorLogs.length }} recent error(s)
                    </div>
                    
                    <div class="max-h-96 overflow-y-auto">
                        <div
                            v-for="log in errorLogs"
                            :key="log.id"
                            class="border-b border-gray-200 dark:border-gray-700 py-3"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-red-600">
                                        {{ log.error_type || 'Sync Error' }}
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        {{ log.message }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2">
                                        Source: {{ log.source }} | 
                                        Attempted: {{ formatDateTime(log.created_at) }}
                                    </div>
                                    <div v-if="log.fallback_used" class="text-xs text-orange-600 mt-1">
                                        Fallback used: {{ log.fallback_reason }}
                                    </div>
                                </div>
                                <Badge
                                    :value="log.resolved ? 'Resolved' : 'Open'"
                                    :severity="log.resolved ? 'success' : 'warning'"
                                    size="small"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Clear Logs"
                        severity="danger"
                        outlined
                        @click="clearErrorLogs"
                    />
                    <Button
                        label="Close"
                        @click="showErrorLogModal = false"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>