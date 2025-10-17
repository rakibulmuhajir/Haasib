<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import PrimeDialog from 'primevue/dialog'
import PrimeButton from 'primevue/button'
import PrimeInputText from 'primevue/inputtext'
import PrimeTextarea from 'primevue/textarea'
import PrimeDropdown from 'primevue/dropdown'
import PrimeInputNumber from 'primevue/inputnumber'
import PrimeMessage from 'primevue/message'
import PrimeCard from 'primevue/card'
import PrimeButtonGroup from 'primevue/buttongroup'

interface Account {
  id: string
  code: string
  name: string
  type: string
  is_active: boolean
}

interface AdjustmentLine {
  account_id: string
  description: string
  debit_amount?: number
  credit_amount?: number
}

interface AdjustmentDialogProps {
  visible: boolean
  accounts: Account[]
  loading?: boolean
}

const props = withDefaults(defineProps<AdjustmentDialogProps>(), {
  loading: false
})

const emit = defineEmits<{
  'update:visible': [visible: boolean]
  'save': [adjustmentData: {
    description: string
    reference: string
    entry_date?: string
    lines: AdjustmentLine[]
    notes?: string
  }]
  cancel: []
}>()

// Form data
const description = ref('')
const reference = ref('')
const entryDate = ref('')
const notes = ref('')
const lines = ref<AdjustmentLine[]>([])

// Form validation
const errors = ref<Record<string, string>>({})

// Computed properties
const totalDebits = computed(() => 
  lines.value.reduce((sum, line) => sum + (line.debit_amount || 0), 0)
)

const totalCredits = computed(() => 
  lines.value.reduce((sum, line) => sum + (line.credit_amount || 0), 0)
)

const isBalanced = computed(() => {
  const debit = totalDebits.value
  const credit = totalCredits.value
  return Math.abs(debit - credit) < 0.01 // Allow for floating point precision
})

const canSave = computed(() => {
  return description.value.trim() && 
         reference.value.trim() && 
         lines.value.length > 0 && 
         lines.value.every(line => 
           line.account_id && 
           line.description.trim() && 
           ((line.debit_amount && line.debit_amount > 0) || 
            (line.credit_amount && line.credit_amount > 0))
         ) &&
         isBalanced.value
})

const activeAccountOptions = computed(() => 
  props.accounts.filter(account => account.is_active)
)

// Watch for visibility changes to reset form
watch(() => props.visible, (newVal) => {
  if (!newVal) {
    resetForm()
  }
})

// Methods
function resetForm() {
  description.value = ''
  reference.value = ''
  entryDate.value = ''
  notes.value = ''
  lines.value = []
  errors.value = {}
}

function addLine() {
  lines.value.push({
    account_id: '',
    description: '',
    debit_amount: undefined,
    credit_amount: undefined
  })
}

function removeLine(index: number) {
  lines.value.splice(index, 1)
}

function updateLine(index: number, field: keyof AdjustmentLine, value: any) {
  lines.value[index][field] = value
  
  // Clear credit amount when debit is entered and vice versa
  if (field === 'debit_amount' && value && value > 0) {
    lines.value[index].credit_amount = undefined
  } else if (field === 'credit_amount' && value && value > 0) {
    lines.value[index].debit_amount = undefined
  }
  
  // Clear errors for this line
  delete errors.value[`lines.${index}.account_id`]
  delete errors.value[`lines.${index}.description`]
  delete errors.value[`lines.${index}.amount`]
}

function validateForm(): boolean {
  errors.value = {}
  
  if (!description.value.trim()) {
    errors.value.description = 'Description is required'
  }
  
  if (!reference.value.trim()) {
    errors.value.reference = 'Reference is required'
  }
  
  if (lines.value.length === 0) {
    errors.value.lines = 'At least one line is required'
  } else {
    lines.value.forEach((line, index) => {
      if (!line.account_id) {
        errors.value[`lines.${index}.account_id`] = 'Account is required'
      }
      
      if (!line.description.trim()) {
        errors.value[`lines.${index}.description`] = 'Description is required'
      }
      
      const hasDebit = line.debit_amount && line.debit_amount > 0
      const hasCredit = line.credit_amount && line.credit_amount > 0
      
      if (!hasDebit && !hasCredit) {
        errors.value[`lines.${index}.amount`] = 'Either debit or credit amount is required'
      }
      
      if (hasDebit && hasCredit) {
        errors.value[`lines.${index}.amount`] = 'Cannot have both debit and credit amounts'
      }
    })
  }
  
  if (!isBalanced.value) {
    errors.value.balance = `Debits and credits must balance. Difference: $${Math.abs(totalDebits.value - totalCredits.value).toFixed(2)}`
  }
  
  return Object.keys(errors.value).length === 0
}

function handleSave() {
  if (!validateForm()) {
    return
  }
  
  emit('save', {
    description: description.value.trim(),
    reference: reference.value.trim(),
    entry_date: entryDate.value || undefined,
    lines: lines.value.map(line => ({
      ...line,
      debit_amount: line.debit_amount || undefined,
      credit_amount: line.credit_amount || undefined
    })),
    notes: notes.value.trim() || undefined
  })
}

function handleCancel() {
  emit('cancel')
}

function getAccountLabel(account: Account): string {
  return `${account.code} - ${account.name}`
}
</script>

<template>
  <PrimeDialog 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Create Period Close Adjustment"
    :style="{ width: '90vw', maxWidth: '800px' }"
    :loading="loading"
  >
    <div class="space-y-6">
      <!-- Header Info -->
      <PrimeMessage severity="info" :closable="false">
        Create adjusting journal entries for the period close. All adjustments must balance (debits = credits) and will be audited.
      </PrimeMessage>

      <!-- Basic Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Description *
          </label>
          <PrimeInputText 
            id="description"
            v-model="description"
            placeholder="Adjustment description"
            class="w-full"
            :class="{ 'p-invalid': errors.description }"
          />
          <small v-if="errors.description" class="text-red-500">{{ errors.description }}</small>
        </div>

        <div>
          <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Reference *
          </label>
          <PrimeInputText 
            id="reference"
            v-model="reference"
            placeholder="ADJ-001"
            class="w-full"
            :class="{ 'p-invalid': errors.reference }"
          />
          <small v-if="errors.reference" class="text-red-500">{{ errors.reference }}</small>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="entryDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Entry Date
          </label>
          <PrimeInputText 
            id="entryDate"
            v-model="entryDate"
            type="date"
            placeholder="Leave empty for today"
            class="w-full"
          />
        </div>

        <div>
          <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Notes
          </label>
          <PrimeTextarea 
            id="notes"
            v-model="notes"
            rows="2"
            placeholder="Additional notes..."
            class="w-full"
          />
        </div>
      </div>

      <!-- Journal Entry Lines -->
      <PrimeCard>
        <template #header>
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Journal Entry Lines</h3>
            <PrimeButton 
              label="Add Line" 
              icon="pi pi-plus" 
              @click="addLine"
              size="small"
            />
          </div>
        </template>

        <template #content>
          <div v-if="lines.length === 0" class="text-center py-8 text-gray-500">
            <i class="pi pi-plus-circle text-3xl mb-2"></i>
            <p>No journal entry lines added yet. Click "Add Line" to begin.</p>
          </div>

          <div v-else class="space-y-4">
            <div 
              v-for="(line, index) in lines" 
              :key="index"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-4"
            >
              <div class="flex justify-between items-start">
                <h4 class="font-medium text-gray-900 dark:text-gray-100">Line {{ index + 1 }}</h4>
                <PrimeButton 
                  icon="pi pi-trash" 
                  @click="removeLine(index)"
                  severity="danger"
                  text
                  size="small"
                />
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Account *
                  </label>
                  <PrimeDropdown 
                    :options="activeAccountOptions"
                    optionLabel="name"
                    optionValue="id"
                    :filter="true"
                    placeholder="Select account"
                    class="w-full"
                    :class="{ 'p-invalid': errors[`lines.${index}.account_id`] }"
                    @update:modelValue="value => updateLine(index, 'account_id', value)"
                  >
                    <template #option="{ option }">
                      <span class="font-mono text-sm">{{ option.code }}</span> - {{ option.name }}
                    </template>
                    <template #value="{ value }">
                      <span v-if="value">
                        {{ getAccountLabel(activeAccountOptions.find(acc => acc.id === value)!) }}
                      </span>
                      <span v-else>Select account</span>
                    </template>
                  </PrimeDropdown>
                  <small v-if="errors[`lines.${index}.account_id`]" class="text-red-500">
                    {{ errors[`lines.${index}.account_id`] }}
                  </small>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description *
                  </label>
                  <PrimeInputText 
                    placeholder="Line description"
                    class="w-full"
                    :class="{ 'p-invalid': errors[`lines.${index}.description`] }"
                    @update:modelValue="value => updateLine(index, 'description', value)"
                  />
                  <small v-if="errors[`lines.${index}.description`]" class="text-red-500">
                    {{ errors[`lines.${index}.description`] }}
                  </small>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Debit Amount
                  </label>
                  <PrimeInputNumber 
                    placeholder="0.00"
                    :min="0"
                    :step="0.01"
                    mode="currency"
                    currency="USD"
                    class="w-full"
                    :class="{ 'p-invalid': errors[`lines.${index}.amount`] }"
                    @update:modelValue="value => updateLine(index, 'debit_amount', value)"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Credit Amount
                  </label>
                  <PrimeInputNumber 
                    placeholder="0.00"
                    :min="0"
                    :step="0.01"
                    mode="currency"
                    currency="USD"
                    class="w-full"
                    :class="{ 'p-invalid': errors[`lines.${index}.amount`] }"
                    @update:modelValue="value => updateLine(index, 'credit_amount', value)"
                  />
                </div>
              </div>
            </div>
          </div>
        </template>
      </PrimeCard>

      <!-- Balance Summary -->
      <div v-if="lines.length > 0" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Debits</div>
            <div class="text-lg font-semibold text-green-600">
              ${{ totalDebits.toFixed(2) }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Credits</div>
            <div class="text-lg font-semibold text-red-600">
              ${{ totalCredits.toFixed(2) }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Difference</div>
            <div class="text-lg font-semibold" :class="isBalanced ? 'text-blue-600' : 'text-orange-600'">
              ${{ Math.abs(totalDebits - totalCredits).toFixed(2) }}
            </div>
          </div>
        </div>
        
        <PrimeMessage 
          v-if="!isBalanced" 
          severity="error" 
          :closable="false"
          class="mt-3"
        >
          Journal entry must balance (debits = credits)
        </PrimeMessage>
        
        <PrimeMessage 
          v-else 
          severity="success" 
          :closable="false"
          class="mt-3"
        >
          Journal entry is balanced
        </PrimeMessage>
      </div>

      <!-- Form Errors -->
      <PrimeMessage 
        v-if="errors.lines || errors.balance" 
        severity="error" 
        :closable="false"
      >
        {{ errors.lines || errors.balance }}
      </PrimeMessage>
    </div>

    <template #footer>
      <div class="flex justify-between">
        <PrimeButton 
          label="Cancel" 
          @click="handleCancel"
          severity="secondary"
        />
        <PrimeButton 
          label="Create Adjustment" 
          @click="handleSave"
          :loading="loading"
          :disabled="!canSave"
          severity="primary"
        />
      </div>
    </template>
  </PrimeDialog>
</template>