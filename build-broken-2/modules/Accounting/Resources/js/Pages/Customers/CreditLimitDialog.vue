<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
// Reka UI components
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Toaster } from '@/components/ui/sonner'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    customer: Object,
    creditData: Object,
    visible: Boolean
})

const emit = defineEmits(['update:visible', 'saved', 'refresh'])

const { t } = useI18n()

// Form state
const form = useForm({
    amount: null,
    effective_at: new Date(),
    expires_at: null,
    reason: '',
    approval_reference: '',
    status: 'approved',
    auto_expire_conflicts: false
})

const loading = ref(false)

// Computed properties
const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
})

const currentLimit = computed(() => props.creditData?.credit_limit || 0)
const currentExposure = computed(() => props.creditData?.current_exposure || 0)
const availableCredit = computed(() => {
    return currentLimit.value ? Math.max(0, currentLimit.value - currentExposure.value) : null
})

const utilizationPercentage = computed(() => {
    if (!currentLimit.value || currentLimit.value === 0) return 0
    return Math.round((currentExposure.value / currentLimit.value) * 100)
})

const getUtilizationSeverity = (percentage) => {
    if (percentage >= 90) return 'danger'
    if (percentage >= 75) return 'warning'
    if (percentage >= 50) return 'info'
    return 'success'
}

const getUtilizationLabel = (percentage) => {
    if (percentage >= 90) return 'Critical'
    if (percentage >= 75) return 'High'
    if (percentage >= 50) return 'Moderate'
    return 'Healthy'
}

const getSeverity = (status) => {
    switch (status) {
        case 'active': return 'success'
        case 'inactive': return 'warning'
        case 'blocked': return 'danger'
        default: return 'info'
    }
}

// Status options
const statusOptions = [
    { label: 'Approved', value: 'approved' },
    { label: 'Pending Approval', value: 'pending' },
    { label: 'Revoked', value: 'revoked' }
]

// Methods
const openDialog = () => {
    form.reset()
    form.clearErrors()
    
    // Set default values
    form.amount = currentLimit.value
    form.effective_at = new Date()
    form.status = 'approved'
    
    emit('update:visible', true)
}

const saveCreditLimit = async () => {
    if (loading.value) return
    
    loading.value = true
    
    const url = route('customers.credit-limit.adjust', props.customer.id)
    
    try {
        await form.post(url, {
            onSuccess: () => {
                dialogVisible.value = false
                emit('saved')
                emit('refresh')
                
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Credit limit adjusted successfully',
                    life: 3000
                })
                
                form.reset()
                form.clearErrors()
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to adjust credit limit',
                    life: 3000
                })
            }
        })
    } finally {
        loading.value = false
    }
}

const calculateNewUtilization = () => {
    if (!form.amount || form.amount <= 0) return 0
    
    const newLimit = form.amount
    const currentUtil = currentExposure.value
    
    if (newLimit <= 0) return 0
    
    return Math.round((currentUtil / newLimit) * 100)
}

const newUtilizationPercentage = computed(() => {
    return calculateNewUtilization()
})

const newUtilizationSeverity = computed(() => {
    return getUtilizationSeverity(newUtilizationPercentage.value)
})

const newUtilizationLabel = computed(() => {
    return getUtilizationLabel(newUtilizationPercentage.value)
})

const getChangeAmount = () => {
    if (!form.amount || form.amount <= 0) return 0
    return form.amount - currentLimit.value
}

const getChangePercentage = () => {
    const change = getChangeAmount()
    if (currentLimit.value === 0) return change > 0 ? 100 : 0
    return Math.round((change / currentLimit.value) * 100)
}

const getChangeLabel = () => {
    const change = getChangeAmount()
    if (change > 0) return 'Increase'
    if (change < 0) return 'Decrease'
    return 'No Change'
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount || 0)
}

const formatPercentage = (value) => {
    return `${value}%`
}

// Watch for visibility changes to set default values
watch(() => props.visible, (newValue) => {
    if (newValue) {
        openDialog()
    }
})
</script>

<template>
    <div>
        <Toast />
        
        <Dialog
            v-model:visible="dialogVisible"
            :style="{ width: '600px' }"
            :header="`Adjust Credit Limit - ${customer.name}`"
            :modal="true"
            :draggable="false"
        >
            <form @submit.prevent="saveCreditLimit">
                <div class="space-y-6">
                    <!-- Current Credit Summary -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Current Credit Summary</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500">Current Limit</div>
                                <div class="text-lg font-semibold">
                                    {{ formatCurrency(currentLimit) }}
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-xs text-gray-500">Current Exposure</div>
                                <div class="text-lg font-semibold">
                                    {{ formatCurrency(currentExposure) }}
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-xs text-gray-500">Available Credit</div>
                                <div class="text-lg font-semibold">
                                    {{ availableCredit !== null ? formatCurrency(availableCredit) : 'Unlimited' }}
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-xs text-gray-500">Utilization</div>
                                <div class="flex items-center gap-2">
                                    <ProgressBar
                                        :value="utilizationPercentage"
                                        :class="['w-20', getUtilizationSeverity(utilizationPercentage)]"
                                    />
                                    <span class="text-sm font-medium">
                                        {{ formatPercentage(utilizationPercentage) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <Tag 
                                :value="getUtilizationLabel(utilizationPercentage)" 
                                :severity="getUtilizationSeverity(utilizationPercentage)"
                            />
                        </div>
                    </div>

                    <!-- New Credit Limit Form -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            New Credit Limit *
                        </label>
                        <InputNumber
                            v-model="form.amount"
                            :min="0"
                            :step="100"
                            mode="currency"
                            currency="USD"
                            locale="en-US"
                            :class="{ 'p-invalid': form.errors.amount }"
                            class="w-full"
                            placeholder="Enter new credit limit"
                        />
                        <small v-if="form.errors.amount" class="text-red-500">
                            {{ form.errors.amount }}
                        </small>
                    </div>

                    <!-- Change Preview -->
                    <div v-if="form.amount && form.amount !== currentLimit" class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-700 mb-2">Change Preview</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-blue-600">Change Amount</div>
                                <div class="text-base font-semibold" :class="form.amount > currentLimit ? 'text-green-600' : 'text-red-600'">
                                    {{ getChangeLabel() }}: {{ formatCurrency(Math.abs(getChangeAmount())) }}
                                </div>
                                <div class="text-xs text-blue-600">
                                    {{ formatPercentage(getChangePercentage()) }}
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-xs text-blue-600">New Utilization</div>
                                <div class="flex items-center gap-2">
                                    <ProgressBar
                                        :value="newUtilizationPercentage"
                                        :class="['w-20', newUtilizationSeverity]"
                                    />
                                    <span class="text-sm font-medium">
                                        {{ formatPercentage(newUtilizationPercentage) }}
                                    </span>
                                </div>
                                <Tag 
                                    :value="newUtilizationLabel" 
                                    :severity="newUtilizationSeverity"
                                    size="small"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Effective Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Effective Date *
                        </label>
                        <Calendar
                            v-model="form.effective_at"
                            :showTime="true"
                            :class="{ 'p-invalid': form.errors.effective_at }"
                            class="w-full"
                            placeholder="Select effective date and time"
                        />
                        <small v-if="form.errors.effective_at" class="text-red-500">
                            {{ form.errors.effective_at }}
                        </small>
                    </div>

                    <!-- Expiry Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Expiry Date
                        </label>
                        <Calendar
                            v-model="form.expires_at"
                            :showTime="true"
                            :minDate="form.effective_at"
                            class="w-full"
                            placeholder="Select expiry date and time (optional)"
                        />
                        <small class="text-gray-500">
                            Leave empty for no expiry date
                        </small>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Status
                        </label>
                        <Dropdown
                            v-model="form.status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            placeholder="Select status"
                        />
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Reason
                        </label>
                        <Textarea
                            v-model="form.reason"
                            :class="{ 'p-invalid': form.errors.reason }"
                            class="w-full"
                            rows="3"
                            placeholder="Reason for credit limit adjustment"
                        />
                        <small v-if="form.errors.reason" class="text-red-500">
                            {{ form.errors.reason }}
                        </small>
                    </div>

                    <!-- Approval Reference -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Approval Reference
                        </label>
                        <InputText
                            v-model="form.approval_reference"
                            :class="{ 'p-invalid': form.errors.approval_reference }"
                            class="w-full"
                            placeholder="Approval reference (optional)"
                        />
                        <small v-if="form.errors.approval_reference" class="text-red-500">
                            {{ form.errors.approval_reference }}
                        </small>
                    </div>

                    <!-- Auto Expire Conflicts -->
                    <div class="flex items-center">
                        <input
                            v-model="form.auto_expire_conflicts"
                            type="checkbox"
                            id="auto_expire_conflicts"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="auto_expire_conflicts" class="ml-2 text-sm text-gray-700">
                            Auto-expire conflicting limits
                        </label>
                        <small class="text-gray-500 ml-auto">
                            Automatically expire existing limits that conflict with this new limit
                        </small>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        text
                        @click="dialogVisible = false"
                        :disabled="loading"
                    />
                    <Button
                        label="Save Credit Limit"
                        icon="pi pi-check"
                        type="submit"
                        :loading="loading"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<style scoped>
.confirmation-content {
    align-items: center;
}
</style>