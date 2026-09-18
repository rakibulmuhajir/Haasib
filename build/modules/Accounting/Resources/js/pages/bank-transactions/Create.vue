<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import type { BreadcrumbItem } from '@/types'
import { Landmark } from 'lucide-vue-next'

interface AccountOption { id: string; code: string; name: string }

const props = defineProps<{
  cashAccounts: AccountOption[]
  bankAccounts: AccountOption[]
  expenseAccounts: AccountOption[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Bank Transactions', href: `/${companySlug.value}/banking/transactions` },
  { title: 'New', href: `/${companySlug.value}/banking/transactions/create` },
])

const form = useForm({
  kind: 'deposit' as 'deposit' | 'withdrawal' | 'transfer' | 'charge',
  date: new Date().toISOString().slice(0, 10),
  amount: 0,
  cash_account_id: '',
  bank_account_id: '',
  from_bank_account_id: '',
  to_bank_account_id: '',
  expense_account_id: '',
  reference: '',
  notes: '',
})

const kindLabel: Record<string, string> = {
  deposit: 'Deposit (cash → bank)',
  withdrawal: 'Withdrawal (bank → cash)',
  transfer: 'Transfer (bank → bank)',
  charge: 'Bank charge / fee',
}

const submit = () => form.post(`/${companySlug.value}/banking/transactions`)
</script>

<template>
  <Head title="New Bank Transaction" />
  <PageShell title="New Bank Transaction" description="Record a manual bank movement." :icon="Landmark" :breadcrumbs="breadcrumbs">
    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>Transaction details</CardTitle>
        <CardDescription>Posts a balanced journal immediately.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-5">
        <div class="space-y-2">
          <Label>Kind</Label>
          <RadioGroup v-model="form.kind" class="grid grid-cols-2 gap-2">
            <div v-for="(label, key) in kindLabel" :key="key" class="flex items-center gap-2 rounded-md border p-2">
              <RadioGroupItem :id="`kind-${key}`" :value="key" />
              <Label :for="`kind-${key}`" class="font-normal">{{ label }}</Label>
            </div>
          </RadioGroup>
          <InputError :message="form.errors.kind" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <Label>Date</Label>
            <Input v-model="form.date" type="date" />
            <InputError :message="form.errors.date" />
          </div>
          <div class="space-y-1">
            <Label>Amount</Label>
            <Input v-model.number="form.amount" type="number" min="0.01" step="0.01" />
            <InputError :message="form.errors.amount" />
          </div>
        </div>

        <div v-if="form.kind === 'deposit' || form.kind === 'withdrawal'" class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <Label>Cash account</Label>
            <Select v-model="form.cash_account_id">
              <SelectTrigger><SelectValue placeholder="Select cash account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.cash_account_id" />
          </div>
          <div class="space-y-1">
            <Label>Bank account</Label>
            <Select v-model="form.bank_account_id">
              <SelectTrigger><SelectValue placeholder="Select bank account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.bank_account_id" />
          </div>
        </div>

        <div v-if="form.kind === 'transfer'" class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <Label>From bank account</Label>
            <Select v-model="form.from_bank_account_id">
              <SelectTrigger><SelectValue placeholder="Source account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.from_bank_account_id" />
          </div>
          <div class="space-y-1">
            <Label>To bank account</Label>
            <Select v-model="form.to_bank_account_id">
              <SelectTrigger><SelectValue placeholder="Destination account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.to_bank_account_id" />
          </div>
        </div>

        <div v-if="form.kind === 'charge'" class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <Label>Bank account</Label>
            <Select v-model="form.bank_account_id">
              <SelectTrigger><SelectValue placeholder="Select bank account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.bank_account_id" />
          </div>
          <div class="space-y-1">
            <Label>Bank charges account</Label>
            <Select v-model="form.expense_account_id">
              <SelectTrigger><SelectValue placeholder="Select expense account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.expense_account_id" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <Label>Reference</Label>
            <Input v-model="form.reference" maxlength="100" />
            <InputError :message="form.errors.reference" />
          </div>
          <div class="space-y-1">
            <Label>Notes</Label>
            <Textarea v-model="form.notes" rows="1" />
            <InputError :message="form.errors.notes" />
          </div>
        </div>

        <Button :disabled="form.processing" @click="submit">{{ form.processing ? 'Saving…' : 'Record transaction' }}</Button>
      </CardContent>
    </Card>
  </PageShell>
</template>
