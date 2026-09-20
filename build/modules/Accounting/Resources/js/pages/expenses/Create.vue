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
import type { BreadcrumbItem } from '@/types'
import { ReceiptText } from 'lucide-vue-next'

interface AccountOption { id: string; code: string; name: string }

const props = defineProps<{
  expenseAccounts: AccountOption[]
  paymentAccounts: AccountOption[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Expenses', href: `/${companySlug.value}/expenses` },
  { title: 'New', href: `/${companySlug.value}/expenses/create` },
])

const form = useForm({
  date: new Date().toISOString().slice(0, 10),
  account_id: '',
  amount: 0,
  paid_from_account_id: '',
  description: '',
  reference: '',
  attachment: null as File | null,
})

// forceFormData: an expense with no file still has to post as multipart once the form
// carries a file field, otherwise the null arrives as the string "null".
const submit = () => form.post(`/${companySlug.value}/expenses`, { forceFormData: true })

const onFile = (event: Event) => {
  const input = event.target as HTMLInputElement
  form.attachment = input.files?.[0] ?? null
}
</script>

<template>
  <Head title="New Expense" />
  <PageShell title="New Expense" description="Record an expense paid from cash or bank." :icon="ReceiptText" :breadcrumbs="breadcrumbs">
    <Card class="max-w-xl">
      <CardHeader>
        <CardTitle>Expense details</CardTitle>
        <CardDescription>Posts Dr Expense, Cr the account paid from.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-5">
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

        <div class="space-y-1">
          <Label>Expense account</Label>
          <Select v-model="form.account_id">
            <SelectTrigger><SelectValue placeholder="Select expense account" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.account_id" />
        </div>

        <div class="space-y-1">
          <Label>Paid from</Label>
          <Select v-model="form.paid_from_account_id">
            <SelectTrigger><SelectValue placeholder="Cash or bank account" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="a in paymentAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.paid_from_account_id" />
        </div>

        <div class="space-y-1">
          <Label>Payee / description</Label>
          <Input v-model="form.description" maxlength="255" />
          <InputError :message="form.errors.description" />
        </div>

        <div class="space-y-1">
          <Label>Reference</Label>
          <Input v-model="form.reference" maxlength="100" />
          <InputError :message="form.errors.reference" />
        </div>

        <div class="space-y-1">
          <Label for="expense-attachment">Bill or receipt</Label>
          <Input id="expense-attachment" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp,.heic" @change="onFile" />
          <p class="text-text-metadata">Optional. A PDF or a photo, up to 10 MB. Kept private to this company.</p>
          <InputError :message="form.errors.attachment" />
        </div>

        <Button :disabled="form.processing" @click="submit">{{ form.processing ? 'Saving…' : 'Record expense' }}</Button>
      </CardContent>
    </Card>
  </PageShell>
</template>
