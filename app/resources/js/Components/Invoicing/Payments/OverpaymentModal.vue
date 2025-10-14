<template>
  <Dialog
    v-model:visible="localVisible"
    :header="'Overpayment Detected'"
    :style="{ width: '600px' }"
    modal
    @hide="onHide"
  >
    <div class="space-y-4">
      <!-- Overpayment Info -->
      <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
        <div class="flex items-start">
          <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3"></i>
          <div>
            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
              Overpayment Detected
            </h4>
            <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
              You've entered {{ formatMoney(form.amount || 0, previewCurrency) }} but {{ selectedInvoice?.invoice_number }} only has 
              {{ formatMoney(selectedInvoice?.balance_due || 0, selectedInvoice?.currency || { code: companyBaseCurrency }) }} due.
            </p>
            <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
              Overpayment amount: {{ formatMoney(overpaymentAmount, previewCurrency) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Options -->
      <div class="space-y-3">
        <h5 class="text-sm font-medium text-gray-900 dark:text-white">How would you like to handle the overpayment?</h5>

        <div class="space-y-2">
          <div class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
               :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': localOption === 'apply' }"
               @click="selectOption('apply')">
            <RadioButton
              v-model="localOption"
              value="apply"
              inputId="apply"
              name="overpaymentOption"
            />
            <label for="apply" class="ml-3 flex-1 cursor-pointer">
              <div class="font-medium text-gray-900 dark:text-white">Apply Full Amount</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Apply the entire payment. The excess will be available as a credit for future invoices.
              </div>
            </label>
          </div>

          <div class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
               :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': localOption === 'distribute' }"
               @click="selectOption('distribute')">
            <RadioButton
              v-model="localOption"
              value="distribute"
              inputId="distribute"
              name="overpaymentOption"
            />
            <label for="distribute" class="ml-3 flex-1 cursor-pointer">
              <div class="font-medium text-gray-900 dark:text-white">Distribute to Other Invoices</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Automatically distribute remaining {{ formatMoney(remainingForOtherInvoices, previewCurrency) }} across other outstanding invoices.
              </div>
            </label>
          </div>

          <div class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
               :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': localOption === 'credit' }"
               @click="selectOption('credit')">
            <RadioButton
              v-model="localOption"
              value="credit"
              inputId="credit"
              name="overpaymentOption"
            />
            <label for="credit" class="ml-3 flex-1 cursor-pointer">
              <div class="font-medium text-gray-900 dark:text-white">Create Customer Credit</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Apply only the due amount and create a credit for the remaining {{ formatMoney(remainingAfterAllocation, previewCurrency) }}.
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="localOption" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h6 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Summary</h6>
        <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
          <li>• {{ formatMoney(selectedInvoice?.balance_due || 0, selectedInvoice?.currency?.code || companyBaseCurrency) }} applied to {{ selectedInvoice?.invoice_number }}</li>
          <li v-if="localOption === 'distribute'">• {{ formatMoney(remainingForOtherInvoices, previewCurrency) }} distributed to other invoices</li>
          <li v-if="localOption === 'credit'">• {{ formatMoney(remainingAfterAllocation, previewCurrency) }} created as Customer Credit</li>
          <li v-if="localOption === 'apply'">• {{ formatMoney(form.amount || 0, previewCurrency) }} applied to {{ selectedInvoice?.invoice_number }}</li>
          <li v-if="localOption === 'apply'">• {{ formatMoney(overpaymentAmount, previewCurrency) }} created as Customer Credit</li>
        </ul>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          severity="secondary"
          outlined
          @click="onCancel"
        />
        <Button
          label="Apply Option"
          severity="primary"
          :disabled="!localOption"
          @click="onApply"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'
import { formatMoney } from '@/Utils/formatting'

interface Props {
  visible: boolean
  form: {
    amount?: number
  }
  selectedInvoice?: {
    invoice_number: string
    balance_due: number
    currency?: any
  }
  previewCurrency: any
  overpaymentAmount: number
  remainingForOtherInvoices: number
  remainingAfterAllocation: number
  companyBaseCurrency: string
  modelValue?: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
  (e: 'update:visible', value: boolean): void
  (e: 'apply-overpayment', option: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const localVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const localOption = computed({
  get: () => props.modelValue || 'credit',
  set: (value) => emit('update:modelValue', value)
})

const selectOption = (option: string) => {
  localOption.value = option
}

const onApply = () => {
  emit('apply-overpayment', localOption.value)
}

const onCancel = () => {
  localVisible.value = false
}

const onHide = () => {
  // Reset option when hiding
  if (!localVisible.value) {
    emit('update:modelValue', 'credit')
  }
}
</script>