<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Landmark, Save, X, AlertTriangle } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankOption {
  id: string
  name: string
  swift_code: string | null
  country_code: string | null
}

interface CurrencyOption {
  currency_code: string
  is_base: boolean
}

interface GlAccountOption {
  id: string
  code: string
  name: string
  subtype: string
}

interface AccountTypeOption {
  value: string
  label: string
}

interface BankAccountRef {
  id: string
  account_name: string
  account_number: string
  account_type: string
  currency: string
  bank_id: string | null
  gl_account_id: string | null
  iban: string | null
  swift_code: string | null
  routing_number: string | null
  branch_name: string | null
  branch_address: string | null
  opening_balance: number
  opening_balance_date: string | null
  is_primary: boolean
  is_active: boolean
  notes: string | null
}

const props = defineProps<{
  company: CompanyRef
  bankAccount: BankAccountRef
  banks: BankOption[]
  currencies: CurrencyOption[]
  glAccounts: GlAccountOption[]
  accountTypes: AccountTypeOption[]
  hasTransactions: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: props.bankAccount.account_name, href: `/${props.company.slug}/banking/accounts/${props.bankAccount.id}` },
  { title: 'Edit', href: `/${props.company.slug}/banking/accounts/${props.bankAccount.id}/edit` },
]

const noneValue = '__none'

const form = useForm({
  account_name: props.bankAccount.account_name,
  account_number: props.bankAccount.account_number,
  account_type: props.bankAccount.account_type,
  currency: props.bankAccount.currency,
  bank_id: props.bankAccount.bank_id || noneValue,
  gl_account_id: props.bankAccount.gl_account_id || noneValue,
  iban: props.bankAccount.iban || '',
  swift_code: props.bankAccount.swift_code || '',
  routing_number: props.bankAccount.routing_number || '',
  branch_name: props.bankAccount.branch_name || '',
  branch_address: props.bankAccount.branch_address || '',
  opening_balance: props.bankAccount.opening_balance,
  opening_balance_date: props.bankAccount.opening_balance_date || '',
  is_primary: props.bankAccount.is_primary,
  is_active: props.bankAccount.is_active,
  notes: props.bankAccount.notes || '',
})

const handleSubmit = () => {
  form
    .transform((data) => ({
      ...data,
      bank_id: data.bank_id === noneValue ? null : data.bank_id,
      gl_account_id: data.gl_account_id === noneValue ? null : data.gl_account_id,
    }))
    .put(`/${props.company.slug}/banking/accounts/${props.bankAccount.id}`, {
      preserveScroll: true,
    })
}

const handleCancel = () => {
  router.get(`/${props.company.slug}/banking/accounts/${props.bankAccount.id}`)
}
</script>

<template>
  <Head :title="`Edit ${bankAccount.account_name}`" />
  <PageShell
    :title="`Edit ${bankAccount.account_name}`"
    :breadcrumbs="breadcrumbs"
    :icon="Landmark"
  >
    <form class="space-y-6 max-w-3xl" @submit.prevent="handleSubmit">
      <!-- Currency Lock Warning -->
      <Alert v-if="hasTransactions" variant="warning">
        <AlertTriangle class="h-4 w-4" />
        <AlertDescription>
          This account has transactions. Currency and opening balance cannot be changed.
        </AlertDescription>
      </Alert>

      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Account Details</CardTitle>
          <CardDescription>Update the basic information for this bank account</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="account_name">Account Name *</Label>
              <Input
                id="account_name"
                v-model="form.account_name"
                placeholder="e.g., Main Business Checking"
                required
              />
              <p v-if="form.errors.account_name" class="text-sm text-destructive">{{ form.errors.account_name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="account_number">Account Number *</Label>
              <Input
                id="account_number"
                v-model="form.account_number"
                placeholder="e.g., 1234567890"
                required
              />
              <p v-if="form.errors.account_number" class="text-sm text-destructive">{{ form.errors.account_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="account_type">Account Type *</Label>
              <Select v-model="form.account_type">
                <SelectTrigger id="account_type">
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="type in accountTypes"
                    :key="type.value"
                    :value="type.value"
                  >
                    {{ type.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.account_type" class="text-sm text-destructive">{{ form.errors.account_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="currency">Currency *</Label>
              <Select v-model="form.currency" :disabled="hasTransactions">
                <SelectTrigger id="currency">
                  <SelectValue placeholder="Select currency" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="curr in currencies"
                    :key="curr.currency_code"
                    :value="curr.currency_code"
                  >
                    {{ curr.currency_code }}
                    <span v-if="curr.is_base" class="text-muted-foreground"> (Base)</span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="hasTransactions" class="text-xs text-muted-foreground">Currency cannot be changed after transactions exist</p>
              <p v-if="form.errors.currency" class="text-sm text-destructive">{{ form.errors.currency }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bank_id">Bank (optional)</Label>
              <Select v-model="form.bank_id">
                <SelectTrigger id="bank_id">
                  <SelectValue placeholder="Select bank" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue">No bank selected</SelectItem>
                  <SelectItem
                    v-for="bank in banks"
                    :key="bank.id"
                    :value="bank.id"
                  >
                    {{ bank.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="gl_account_id">GL Account (optional)</Label>
              <Select v-model="form.gl_account_id">
                <SelectTrigger id="gl_account_id">
                  <SelectValue placeholder="Link to GL account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue">No GL account</SelectItem>
                  <SelectItem
                    v-for="gl in glAccounts"
                    :key="gl.id"
                    :value="gl.id"
                  >
                    {{ gl.code }} — {{ gl.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Banking Details -->
      <Card>
        <CardHeader>
          <CardTitle>Banking Details</CardTitle>
          <CardDescription>Optional banking identifiers</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="iban">IBAN</Label>
              <Input
                id="iban"
                v-model="form.iban"
                placeholder="e.g., SA0380000000608010167519"
                maxlength="34"
              />
              <p v-if="form.errors.iban" class="text-sm text-destructive">{{ form.errors.iban }}</p>
            </div>

            <div class="space-y-2">
              <Label for="swift_code">SWIFT Code</Label>
              <Input
                id="swift_code"
                v-model="form.swift_code"
                placeholder="e.g., RJHISARI"
                maxlength="11"
              />
            </div>

            <div class="space-y-2">
              <Label for="routing_number">Routing Number</Label>
              <Input
                id="routing_number"
                v-model="form.routing_number"
                placeholder="e.g., 021000021"
              />
            </div>

            <div class="space-y-2">
              <Label for="branch_name">Branch Name</Label>
              <Input
                id="branch_name"
                v-model="form.branch_name"
                placeholder="e.g., Main Branch"
              />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="branch_address">Branch Address</Label>
            <Textarea
              id="branch_address"
              v-model="form.branch_address"
              placeholder="Full branch address"
              rows="2"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Opening Balance (only if no transactions) -->
      <Card v-if="!hasTransactions">
        <CardHeader>
          <CardTitle>Opening Balance</CardTitle>
          <CardDescription>Adjust the starting balance for this account</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="opening_balance">Opening Balance</Label>
              <Input
                id="opening_balance"
                v-model.number="form.opening_balance"
                type="number"
                step="0.01"
                placeholder="0.00"
              />
            </div>

            <div class="space-y-2">
              <Label for="opening_balance_date">As of Date</Label>
              <Input
                id="opening_balance_date"
                v-model="form.opening_balance_date"
                type="date"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Settings</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center gap-3">
            <Checkbox
              id="is_primary"
              :checked="form.is_primary"
              @update:checked="(val) => form.is_primary = val"
            />
            <Label for="is_primary" class="cursor-pointer">
              Set as primary account
            </Label>
          </div>

          <div class="flex items-center gap-3">
            <Checkbox
              id="is_active"
              :checked="form.is_active"
              @update:checked="(val) => form.is_active = val"
            />
            <Label for="is_active" class="cursor-pointer">
              Account is active
            </Label>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Any additional notes about this account"
              rows="3"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="handleCancel">
          <X class="mr-2 h-4 w-4" />
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
