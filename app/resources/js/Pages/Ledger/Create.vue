<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import Card from 'primevue/card'
import Divider from 'primevue/divider'

const page = usePage()

// Permissions
const canCreate = computed(() => 
  page.props.auth.permissions?.['ledger.create'] ?? false
)

// Form data
const form = ref({
  description: '',
  reference: '',
  date: format(new Date(), 'yyyy-MM-dd'),
  lines: [
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

// Validation state
const errors = ref<Record<string, string>>({})
const isSubmitting = ref(false)

// Get accounts from props
const accounts = computed(() => page.props.accounts as any[] || [])

// Account options for dropdown
const accountOptions = computed(() => 
  accounts.value.map(account => ({
    label: `${account.code} - ${account.name}`,
    value: account.id
  }))
)

// Totals
const totalDebit = computed(() => 
  form.value.lines.reduce((sum, line) => sum + Number(line.debit_amount), 0)
)

const totalCredit = computed(() => 
  form.value.lines.reduce((sum, line) => sum + Number(line.credit_amount), 0)
)

const isBalanced = computed(() => 
  Math.abs(totalDebit.value - totalCredit.value) < 0.01
)

const getBalanceStatus = () => {
  if (form.value.lines.length < 2) return { class: 'text-gray-500', text: 'Add at least 2 lines' }
  if (isBalanced.value) return { class: 'text-green-600', text: 'Balanced' }
  return { class: 'text-red-600', text: `Unbalanced by ${formatCurrency(Math.abs(totalDebit.value - totalCredit.value))}` }
}

// Line management
const addLine = () => {
  form.value.lines.push({
    account_id: '',
    description: '',
    debit_amount: 0,
    credit_amount: 0
  })
}

const removeLine = (index: number) => {
  if (form.value.lines.length > 2) {
    form.value.lines.splice(index, 1)
  }
}

const getAccountName = (accountId: string) => {
  const account = accounts.value.find(a => a.id === accountId)
  return account ? `${account.code} - ${account.name}` : 'Select account'
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

// Auto-balance helper
const balanceEntry = () => {
  const difference = totalDebit.value - totalCredit.value
  
  if (Math.abs(difference) > 0.01) {
    // Find the last line that has an account
    const lastLineWithAccount = [...form.value.lines].reverse().find(line => line.account_id)
    
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
const submit = async () => {
  if (!canCreate.value) return
  
  isSubmitting.value = true
  errors.value = {}
  
  try {
    await router.post(route('ledger.store'), {
      description: form.value.description,
      reference: form.value.reference || null,
      date: form.value.date,
      lines: form.value.lines.filter(line => line.account_id && (line.debit_amount > 0 || line.credit_amount > 0))
    })
  } catch (error: any) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <LayoutShell>
    <template #sidebar>
      <!-- Sidebar content will be handled by the layout -->
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between">
        <Breadcrumb 
          :items="[
            { label: 'Ledger', url: route('ledger.index') },
            { label: 'Create Journal Entry' }
          ]" 
        />
      </div>
    </template>

    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Create Journal Entry
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
          Create a new double-entry journal entry
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
                <div v-if="errors.date" class="text-red-500 text-sm mt-1">
                  {{ errors.date }}
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
                <div v-if="errors.reference" class="text-red-500 text-sm mt-1">
                  {{ errors.reference }}
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
                <div v-if="errors.description" class="text-red-500 text-sm mt-1">
                  {{ errors.description }}
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
                    <div class="text-lg font-semibold">{{ formatCurrency(totalDebit) }}</div>
                  </div>
                  <div class="text-center">
                    <div class="text-sm text-gray-500">Total Credit</div>
                    <div class="text-lg font-semibold">{{ formatCurrency(totalCredit) }}</div>
                  </div>
                  <div class="text-center">
                    <div class="text-sm text-gray-500">Difference</div>
                    <div class="text-lg font-semibold" :class="getBalanceStatus().class">
                      {{ formatCurrency(Math.abs(totalDebit - totalCredit)) }}
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
                    :disabled="form.value.lines.length < 2 || isBalanced"
                  />
                </div>
              </template>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
              <Link :href="route('ledger.index')">
                <Button
                  label="Cancel"
                  severity="secondary"
                  outlined
                />
              </Link>
              
              <div class="flex items-center gap-3">
                <Button
                  type="submit"
                  label="Create Journal Entry"
                  :loading="isSubmitting"
                  :disabled="!canCreate || form.value.lines.length < 2 || !isBalanced"
                />
              </div>
            </div>
          </form>
        </template>
      </Card>
    </div>
  </LayoutShell>
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