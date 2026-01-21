<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ArrowRight, ArrowLeft, Settings, Info } from 'lucide-vue-next'

interface Account {
  id: string
  code: string
  name: string
}

interface Props {
  company: {
    id: string
    name: string
    slug: string
  }
  arAccounts: Account[]
  apAccounts: Account[]
  revenueAccounts: Account[]
  expenseAccounts: Account[]
  bankAccounts: Account[]
  retainedEarningsAccounts: Account[]
  taxPayableAccounts: Account[]
  taxReceivableAccounts: Account[]
  transitLossAccounts: Account[]
  transitGainAccounts: Account[]
}

const props = defineProps<Props>()

const pickByCode = (accounts: Account[], code: string): string => {
  return accounts.find(a => a.code === code)?.id || accounts[0]?.id || ''
}

const form = useForm({
  ar_account_id: pickByCode(props.arAccounts, '1100'),
  ap_account_id: pickByCode(props.apAccounts, '2100'),
  income_account_id: pickByCode(props.revenueAccounts, '4100'),
  expense_account_id: pickByCode(props.expenseAccounts, '6100'),
  bank_account_id: pickByCode(props.bankAccounts, '1000'),
  retained_earnings_account_id: pickByCode(props.retainedEarningsAccounts, '3100'),
  sales_tax_payable_account_id: props.taxPayableAccounts[0]?.id || '',
  purchase_tax_receivable_account_id: props.taxReceivableAccounts[0]?.id || '',
  transit_loss_account_id: pickByCode(props.transitLossAccounts, '8060'),
  transit_gain_account_id: pickByCode(props.transitGainAccounts, '7050'),
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/default-accounts`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/bank-accounts`)
}
</script>

<template>
  <Head title="Default Accounts Setup" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Settings class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Default Accounts
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Map your key system accounts. These defaults are used when creating invoices, bills, and payments.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index < 3 ? 'bg-green-600 text-white' :
                index === 3 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 3 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="text-green-600">Identity</span>
          <span class="text-green-600">Fiscal Year</span>
          <span class="text-green-600">Bank Accounts</span>
          <span class="font-semibold text-blue-600">Defaults</span>
          <span>Tax</span>
          <span>Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Settings class="w-5 h-5" />
            System Default Accounts
          </CardTitle>
          <CardDescription>
            These accounts will be used automatically by the system for common transactions
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Receivables & Payables -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Receivables & Payables
              </h3>

              <!-- AR Account -->
              <div class="space-y-2">
                <Label for="ar_account" class="font-medium">
                  Accounts Receivable <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.ar_account_id" required>
                  <SelectTrigger id="ar_account">
                    <SelectValue placeholder="Select AR account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in arAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used for tracking customer invoices and amounts owed to you
                </p>
              </div>

              <!-- AP Account -->
              <div class="space-y-2">
                <Label for="ap_account" class="font-medium">
                  Accounts Payable <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.ap_account_id" required>
                  <SelectTrigger id="ap_account">
                    <SelectValue placeholder="Select AP account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in apAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used for tracking vendor bills and amounts you owe
                </p>
              </div>
            </div>

            <!-- Revenue & Expenses -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Revenue & Expenses
              </h3>

              <!-- Income Account -->
              <div class="space-y-2">
                <Label for="income_account" class="font-medium">
                  Default Revenue Account <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.income_account_id" required>
                  <SelectTrigger id="income_account">
                    <SelectValue placeholder="Select revenue account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in revenueAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used as default for invoice line items (you can override per item)
                </p>
              </div>

              <!-- Expense Account -->
              <div class="space-y-2">
                <Label for="expense_account" class="font-medium">
                  Default Expense Account <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.expense_account_id" required>
                  <SelectTrigger id="expense_account">
                    <SelectValue placeholder="Select expense account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in expenseAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used as default for bill line items (you can override per item)
                </p>
              </div>
            </div>

            <!-- Bank & Retained Earnings -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Bank & Equity
              </h3>

              <!-- Bank Account -->
              <div class="space-y-2">
                <Label for="bank_account" class="font-medium">
                  Default Bank Account <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.bank_account_id" required>
                  <SelectTrigger id="bank_account">
                    <SelectValue placeholder="Select bank account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in bankAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Default account for receiving payments
                </p>
              </div>

              <!-- Retained Earnings -->
              <div class="space-y-2">
                <Label for="retained_earnings" class="font-medium">
                  Retained Earnings <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.retained_earnings_account_id" required>
                  <SelectTrigger id="retained_earnings">
                    <SelectValue placeholder="Select retained earnings account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in retainedEarningsAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used during year-end close to transfer profits/losses
                </p>
              </div>
            </div>

            <!-- Receiving Variance -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Receiving Variance
              </h3>

              <div class="space-y-2">
                <Label for="transit_loss" class="font-medium">
                  Transit Loss <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.transit_loss_account_id" required>
                  <SelectTrigger id="transit_loss">
                    <SelectValue placeholder="Select transit loss account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in transitLossAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used when received quantity is lower than expected
                </p>
              </div>

              <div class="space-y-2">
                <Label for="transit_gain" class="font-medium">
                  Transit Gain <span class="text-red-500">*</span>
                </Label>
                <Select v-model="form.transit_gain_account_id" required>
                  <SelectTrigger id="transit_gain">
                    <SelectValue placeholder="Select transit gain account..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in transitGainAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Used when received quantity is higher than expected
                </p>
              </div>
            </div>

            <!-- Tax Accounts (Optional) -->
            <div v-if="taxPayableAccounts.length > 0 || taxReceivableAccounts.length > 0" class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Tax Accounts (Optional)
              </h3>

              <!-- Sales Tax Payable -->
              <div v-if="taxPayableAccounts.length > 0" class="space-y-2">
                <Label for="tax_payable" class="font-medium">
                  Sales Tax Payable
                </Label>
                <Select v-model="form.sales_tax_payable_account_id">
                  <SelectTrigger id="tax_payable">
                    <SelectValue placeholder="Select tax payable account (optional)..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in taxPayableAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  For output VAT/GST collected from customers
                </p>
              </div>

              <!-- Purchase Tax Receivable -->
              <div v-if="taxReceivableAccounts.length > 0" class="space-y-2">
                <Label for="tax_receivable" class="font-medium">
                  Purchase Tax Receivable
                </Label>
                <Select v-model="form.purchase_tax_receivable_account_id">
                  <SelectTrigger id="tax_receivable">
                    <SelectValue placeholder="Select tax receivable account (optional)..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="account in taxReceivableAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ account.code }} - {{ account.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  For input VAT/GST paid to vendors
                </p>
              </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <div class="text-sm">
                  <p class="text-blue-900 dark:text-blue-100 font-medium mb-1">
                    Why set defaults?
                  </p>
                  <p class="text-blue-700 dark:text-blue-300">
                    These defaults save you time when creating invoices and bills. You can always change the account on individual transactions.
                  </p>
                </div>
              </div>
            </div>

            <!-- Validation Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="text-sm text-red-600 dark:text-red-400">
              <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-between pt-6 border-t">
              <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back
              </Button>
              <Button type="submit" :disabled="form.processing">
                Continue to Tax Settings
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
