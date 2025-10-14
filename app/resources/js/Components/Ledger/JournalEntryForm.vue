<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import { format } from 'date-fns'
import Link from '@inertiajs/vue3'
import SvgIcon from '@/Components/SvgIcon.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import Card from 'primevue/card'
import Divider from 'primevue/divider'
import { useFormatting } from '@/composables/useFormatting'
import { useToast } from 'primevue/usetoast'

interface JournalLine {
  account_id: string
  description: string
  debit_amount: number
  credit_amount: number
}

interface Account {
  id: string
  code: string
  name: string
}

interface Props {
  initialData?: {
    description?: string
    reference?: string
    date?: string
    lines?: JournalLine[]
  }
  accounts: Account[]
  routeName: string
  submitRoute: string
  method?: 'post' | 'put'
  permissions?: {
    create?: boolean
    edit?: boolean
  }
  title?: string
  subtitle?: string
  showHeader?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  method: 'post',
  permissions: () => ({ create: true, edit: true }),
  title: 'Create Journal Entry',
  subtitle: 'Create a new double-entry journal entry',
  showHeader: true,
  initialData: () => ({})
})

const emit = defineEmits<{
  submit: [data: any]
  cancel: []
  success: []
}>()

const page = usePage()

// Permissions
const canCreate = computed(() => 
  props.permissions?.create ?? true
)

const canEdit = computed(() => 
  props.permissions?.edit ?? true
)

// Form data
const form = useForm({
  description: props.initialData?.description || '',
  reference: props.initialData?.reference || '',
  date: props.initialData?.date || format(new Date(), 'yyyy-MM-dd'),
  lines: props.initialData?.lines || [
    {
      account_id: '',
      description: '',
      debit_amount: 0,
      credit_amount: 0
    },
    {
      account_id: '',
      description: '',
      debit_amount: 0,
      credit_amount: 0
    }
  ]
})

// Get formatting utilities
const { formatMoney } = useFormatting()
const toast = useToast()

// Account options for dropdown
const accountOptions = computed(() => 
  props.accounts.map(account => ({
    label: `${account.code} - ${account.name}`,
    value: account.id
  }))
)

// Totals
const totalDebit = computed(() => 
  form.lines.reduce((sum, line) => sum + Number(line.debit_amount), 0)
)

const totalCredit = computed(() => 
  form.lines.reduce((sum, line) => sum + Number(line.credit_amount), 0)
)

const isBalanced = computed(() => 
  Math.abs(totalDebit.value - totalCredit.value) < 0.01
)

const getBalanceStatus = () => {
  if (form.lines.length < 2) return { class: 'text-gray-500', text: 'Add at least 2 lines' }
  if (isBalanced.value) return { class: 'text-green-600', text: 'Balanced' }
  return { class: 'text-red-600', text: `Unbalanced by ${formatMoney(Math.abs(totalDebit.value - totalCredit.value))}` }
}

// Line management
const addLine = () => {
  form.lines.push({
    account_id: '',
    description: '',
    debit_amount: 0,
    credit_amount: 0
  })
}

const removeLine = (index: number) => {
  if (form.lines.length > 2) {
    form.lines.splice(index, 1)
  }
}

const getAccountName = (accountId: string) => {
  const account = props.accounts.find(a => a.id === accountId)
  return account ? `${account.code} - ${account.name}` : 'Select account'
}

// Auto-balance helper
const balanceEntry = () => {
  const difference = totalDebit.value - totalCredit.value
  
  if (Math.abs(difference) > 0.01) {
    // Find the last line that has an account
    const lastLineWithAccount = [...form.lines].reverse().find(line => line.account_id)
    
    if (lastLineWithAccount) {
      if (difference > 0) {
        lastLineWithAccount.credit_amount = difference
      } else {
        lastLineWithAccount.debit_amount = Math.abs(difference)
      }
    }
  }
}

// Submit
const submit = () => {
  if (!canCreate.value && !canEdit.value) return
  
  form.transform((data) => ({
    ...data,
    reference: data.reference || null,
    lines: data.lines.filter(line => line.account_id && (line.debit_amount > 0 || line.credit_amount > 0))
  }))
  
  const submitMethod = props.method === 'put' ? 'put' : 'post'
  const submitRoute = props.submitRoute
  
  form[submitMethod](submitRoute, {
    onSuccess: () => {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: props.method === 'put' ? 'Journal entry updated successfully' : 'Journal entry created successfully',
        life: 3000
      })
      emit('success')
    }
  })
}

const cancel = () => {
  emit('cancel')
}

// Expose form instance for external access
defineExpose({
  form,
  submit,
  cancel,
  addLine,
  removeLine,
  balanceEntry
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div v-if="showHeader">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ title }}
      </h1>
      <p class="text-gray-600 dark:text-gray-400 mt-1">
        {{ subtitle }}
      </p>
    </div>

    <!-- Form Card -->
    <Card>
      <template #content>
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Header Information -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Date <span class="text-red-500">*</span>
              </label>
              <Calendar
                v-model="form.date"
                dateFormat="yy-mm-dd"
                class="w-full"
                required
              />
              <div v-if="form.errors.date" class="text-red-500 text-sm mt-1">
                {{ form.errors.date }}
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Reference
              </label>
              <InputText
                v-model="form.reference"
                class="w-full"
                placeholder="e.g., JE-001"
              />
              <div v-if="form.errors.reference" class="text-red-500 text-sm mt-1">
                {{ form.errors.reference }}
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description <span class="text-red-500">*</span>
              </label>
              <InputText
                v-model="form.description"
                class="w-full"
                placeholder="Enter description"
                required
              />
              <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
                {{ form.errors.description }}
              </div>
            </div>
          </div>

          <!-- Journal Lines -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Journal Lines
              </h3>
              <Button
                type="button"
                label="Add Line"
                icon="plus"
                size="small"
                @click="addLine"
              />
            </div>

            <div class="space-y-3">
              <div 
                v-for="(line, index) in form.lines" 
                :key="index"
                class="grid grid-cols-1 md:grid-cols-12 gap-3 p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
              >
                <!-- Account -->
                <div class="md:col-span-4">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Account
                  </label>
                  <Select
                    v-model="line.account_id"
                    :options="accountOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                    placeholder="Select account"
                  />
                </div>
                
                <!-- Description -->
                <div class="md:col-span-3">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Description
                  </label>
                  <InputText
                    v-model="line.description"
                    class="w-full"
                    placeholder="Optional"
                  />
                </div>
                
                <!-- Debit -->
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Debit
                  </label>
                  <InputText
                    v-model.number="line.debit_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full"
                    placeholder="0.00"
                  />
                </div>
                
                <!-- Credit -->
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Credit
                  </label>
                  <InputText
                    v-model.number="line.credit_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full"
                    placeholder="0.00"
                  />
                </div>
                
                <!-- Actions -->
                <div class="md:col-span-1 flex items-end">
                  <Button
                    type="button"
                    icon="trash"
                    severity="danger"
                    size="small"
                    text
                    @click="removeLine(index)"
                    :disabled="form.lines.length <= 2"
                    v-tooltip.top="'Remove line'"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Balance Summary -->
          <Card>
            <template #title>Balance Summary</template>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                  <div class="text-sm text-gray-500">Total Debit</div>
                  <div class="text-lg font-semibold">{{ formatMoney(totalDebit) }}</div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-gray-500">Total Credit</div>
                  <div class="text-lg font-semibold">{{ formatMoney(totalCredit) }}</div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-gray-500">Difference</div>
                  <div class="text-lg font-semibold" :class="getBalanceStatus().class">
                    {{ formatMoney(Math.abs(totalDebit - totalCredit)) }}
                  </div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-gray-500">Status</div>
                  <div class="text-lg font-semibold" :class="getBalanceStatus().class">
                    {{ getBalanceStatus().text }}
                  </div>
                </div>
              </div>
              
              <div class="mt-4 text-center">
                <Button
                  type="button"
                  label="Auto Balance"
                  size="small"
                  @click="balanceEntry"
                  :disabled="form.lines.length < 2 || isBalanced"
                />
              </div>
            </template>
          </Card>

          <!-- Actions -->
          <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
            <Button
              label="Cancel"
              severity="secondary"
              outlined
              @click="cancel"
            />
            
            <div class="flex items-center gap-3">
              <Button
                type="submit"
                :label="method === 'put' ? 'Update Journal Entry' : 'Create Journal Entry'"
                :loading="form.processing"
                :disabled="(!canCreate && !canEdit) || form.lines.length < 2 || !isBalanced"
              />
            </div>
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>

<style scoped>
:deep(.p-card) {
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

:deep(.p-select), :deep(.p-inputtext), :deep(.p-calendar) {
  border-radius: 0.5rem;
}
</style>