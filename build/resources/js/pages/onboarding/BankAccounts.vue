<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ArrowRight, ArrowLeft, Landmark, Plus, Trash2, Wallet } from 'lucide-vue-next'
import { onMounted } from 'vue'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    base_currency: string
  }
  currencies: Array<{
    code: string
    name: string
    symbol: string
  }>
  existingBankAccounts: Array<{
    id: string
    name: string
    currency: string
    subtype: string
  }>
}

const props = defineProps<Props>()

const form = useForm({
  bank_accounts: [
    {
      id: null,
      account_name: '',
      currency: props.company.base_currency,
      account_type: 'bank' as 'bank' | 'cash',
    },
  ],
})

const addAccount = () => {
  form.bank_accounts.push({
    id: null,
    account_name: '',
    currency: props.company.base_currency,
    account_type: 'bank',
  })
}

const removeAccount = (index: number) => {
  if (form.bank_accounts.length > 1) {
    form.bank_accounts.splice(index, 1)
  }
}

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/bank-accounts`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/fiscal-year`)
}

onMounted(() => {
  if (props.existingBankAccounts.length > 0) {
    form.bank_accounts = props.existingBankAccounts.map(account => ({
      id: account.id,
      account_name: account.name,
      currency: account.currency || props.company.base_currency,
      account_type: account.subtype === 'cash' ? 'cash' : 'bank',
    }))
  }
})
</script>

<template>
  <Head title="Bank Accounts Setup" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Landmark class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Bank & Cash Accounts
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Add your business bank accounts and cash accounts. These are used for receiving payments and reconciliation.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index < 2 ? 'bg-green-600 text-white' :
                index === 2 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 2 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="text-green-600">Identity</span>
          <span class="text-green-600">Fiscal Year</span>
          <span class="font-semibold text-blue-600">Bank Accounts</span>
          <span>Defaults</span>
          <span>Tax</span>
          <span>Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Landmark class="w-5 h-5" />
            Your Bank & Cash Accounts
          </CardTitle>
          <CardDescription>
            Add at least one account where you'll receive payments and manage your business funds
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Bank Accounts List -->
            <div class="space-y-6">
              <div
                v-for="(account, index) in form.bank_accounts"
                :key="index"
                class="border rounded-lg p-6 space-y-4 relative"
                :class="index > 0 ? 'bg-slate-50 dark:bg-slate-800/50' : ''"
              >
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                    Account {{ index + 1 }}
                    <span v-if="index === 0" class="text-blue-600 dark:text-blue-400 ml-2">(Primary)</span>
                  </h3>
                  <Button
                    v-if="index > 0"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="removeAccount(index)"
                    class="text-red-600 hover:text-red-700 hover:bg-red-50"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>

                <!-- Account Name -->
                <div class="space-y-2">
                  <Label :for="`account_name_${index}`">
                    Account Name <span class="text-red-500">*</span>
                  </Label>
                  <Input
                    :id="`account_name_${index}`"
                    v-model="account.account_name"
                    type="text"
                    placeholder="e.g., Meezan Bank Rs, HBL USD Account, Cash Drawer"
                    required
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Give it a descriptive name you'll recognize (e.g., "Meezan Bank Current Account")
                  </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <!-- Currency -->
                  <div class="space-y-2">
                    <Label :for="`currency_${index}`">
                      Currency <span class="text-red-500">*</span>
                    </Label>
                    <Select v-model="account.currency" required>
                      <SelectTrigger :id="`currency_${index}`">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="currency in currencies"
                          :key="currency.code"
                          :value="currency.code"
                        >
                          {{ currency.code }} - {{ currency.name }} ({{ currency.symbol }})
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <!-- Account Type -->
                  <div class="space-y-2">
                    <Label>
                      Account Type <span class="text-red-500">*</span>
                    </Label>
                    <RadioGroup v-model="account.account_type" class="flex gap-4 mt-2">
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem :value="'bank'" :id="`bank_${index}`" />
                        <Label :for="`bank_${index}`" class="font-normal cursor-pointer flex items-center gap-1">
                          <Landmark class="w-4 h-4" />
                          Bank
                        </Label>
                      </div>
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem :value="'cash'" :id="`cash_${index}`" />
                        <Label :for="`cash_${index}`" class="font-normal cursor-pointer flex items-center gap-1">
                          <Wallet class="w-4 h-4" />
                          Cash
                        </Label>
                      </div>
                    </RadioGroup>
                  </div>
                </div>
              </div>
            </div>

            <!-- Add Account Button -->
            <Button
              type="button"
              variant="outline"
              @click="addAccount"
              class="w-full border-dashed"
            >
              <Plus class="w-4 h-4 mr-2" />
              Add Another Account
            </Button>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <p class="text-sm text-blue-900 dark:text-blue-100">
                <strong>💡 Tip:</strong> You can add more accounts later. Common accounts include:
              </p>
              <ul class="mt-2 text-sm text-blue-700 dark:text-blue-300 list-disc list-inside space-y-1">
                <li>Operating bank account (for daily transactions)</li>
                <li>Savings account (for reserves)</li>
                <li>Foreign currency accounts (USD, EUR, etc.)</li>
                <li>Cash drawer or petty cash</li>
              </ul>
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
              <Button type="submit" :disabled="form.processing || form.bank_accounts.some(a => !a.account_name)">
                Continue to Default Accounts
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
