<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { RefreshCcw, ArrowRight, Calendar, Landmark, Info } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankAccountOption {
  id: string
  account_name: string
  account_number: string
  currency: string
  current_balance: number
  last_reconciled_date: string | null
  last_reconciled_balance: number | null
}

const props = defineProps<{
  company: CompanyRef
  bankAccounts: BankAccountOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Reconciliation', href: `/${props.company.slug}/banking/reconciliation` },
  { title: 'Start', href: `/${props.company.slug}/banking/reconciliation/start` },
]

const noneValue = '__none'

const form = useForm({
  bank_account_id: noneValue,
  statement_date: new Date().toISOString().split('T')[0],
  statement_ending_balance: 0,
})

const selectedAccount = computed(() => {
  if (form.bank_account_id === noneValue) return null
  return props.bankAccounts.find(a => a.id === form.bank_account_id) || null
})

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency,
  }).format(amount)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return 'Never'
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

// Pre-populate statement ending balance with current balance when account is selected
watch(() => form.bank_account_id, (newId) => {
  if (newId !== noneValue) {
    const account = props.bankAccounts.find(a => a.id === newId)
    if (account) {
      form.statement_ending_balance = account.current_balance
    }
  }
})

const handleSubmit = () => {
  if (form.bank_account_id === noneValue) {
    return
  }
  form.post(`/${props.company.slug}/banking/reconciliation`, {
    preserveScroll: true,
  })
}

const handleCancel = () => {
  router.get(`/${props.company.slug}/banking/reconciliation`)
}
</script>

<template>
  <Head title="Start Reconciliation" />
  <PageShell
    title="Start Bank Reconciliation"
    :breadcrumbs="breadcrumbs"
    :icon="RefreshCcw"
  >
    <div class="max-w-2xl">
      <!-- Info Alert -->
      <Alert class="mb-6">
        <Info class="h-4 w-4" />
        <AlertTitle>What is Bank Reconciliation?</AlertTitle>
        <AlertDescription>
          Bank reconciliation compares your recorded transactions with your bank statement
          to ensure your books match your actual bank balance. You'll mark transactions
          as cleared until the difference is zero.
        </AlertDescription>
      </Alert>

      <form @submit.prevent="handleSubmit">
        <Card>
          <CardHeader>
            <CardTitle>Reconciliation Details</CardTitle>
            <CardDescription>
              Select the bank account and enter your statement information
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Bank Account Selection -->
            <div class="space-y-2">
              <Label for="bank_account_id">Bank Account *</Label>
              <Select v-model="form.bank_account_id">
                <SelectTrigger id="bank_account_id">
                  <SelectValue placeholder="Select a bank account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue" disabled>Select a bank account</SelectItem>
                  <SelectItem
                    v-for="account in bankAccounts"
                    :key="account.id"
                    :value="account.id"
                  >
                    <div class="flex items-center gap-2">
                      <Landmark class="h-4 w-4" />
                      <span>{{ account.account_name }}</span>
                      <span class="text-muted-foreground">({{ account.account_number }})</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.bank_account_id" class="text-sm text-destructive">
                {{ form.errors.bank_account_id }}
              </p>
            </div>

            <!-- Account Summary (when selected) -->
            <div v-if="selectedAccount" class="rounded-lg border p-4 bg-muted/50">
              <h4 class="font-medium mb-3">Account Summary</h4>
              <div class="grid gap-3 sm:grid-cols-2 text-sm">
                <div>
                  <p class="text-muted-foreground">Current Book Balance</p>
                  <p class="font-medium text-lg">
                    {{ formatCurrency(selectedAccount.current_balance, selectedAccount.currency) }}
                  </p>
                </div>
                <div>
                  <p class="text-muted-foreground">Last Reconciled</p>
                  <p class="font-medium">
                    {{ formatDate(selectedAccount.last_reconciled_date) }}
                  </p>
                  <p v-if="selectedAccount.last_reconciled_balance !== null" class="text-xs text-muted-foreground">
                    Balance: {{ formatCurrency(selectedAccount.last_reconciled_balance, selectedAccount.currency) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Statement Date -->
            <div class="space-y-2">
              <Label for="statement_date">Statement Date *</Label>
              <div class="relative">
                <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  id="statement_date"
                  v-model="form.statement_date"
                  type="date"
                  class="pl-10"
                  required
                />
              </div>
              <p class="text-xs text-muted-foreground">
                The ending date shown on your bank statement
              </p>
              <p v-if="form.errors.statement_date" class="text-sm text-destructive">
                {{ form.errors.statement_date }}
              </p>
            </div>

            <!-- Statement Ending Balance -->
            <div class="space-y-2">
              <Label for="statement_ending_balance">Statement Ending Balance *</Label>
              <Input
                id="statement_ending_balance"
                v-model.number="form.statement_ending_balance"
                type="number"
                step="0.01"
                placeholder="0.00"
                required
              />
              <p class="text-xs text-muted-foreground">
                The ending balance shown on your bank statement for the date above
              </p>
              <p v-if="form.errors.statement_ending_balance" class="text-sm text-destructive">
                {{ form.errors.statement_ending_balance }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t">
              <Button type="button" variant="outline" @click="handleCancel">
                Cancel
              </Button>
              <Button
                type="submit"
                :disabled="form.processing || form.bank_account_id === noneValue"
              >
                Start Reconciliation
                <ArrowRight class="ml-2 h-4 w-4" />
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  </PageShell>
</template>
