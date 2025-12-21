<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Settings2, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRef {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

interface Defaults {
  ar_account_id: string | null
  ap_account_id: string | null
  income_account_id: string | null
  expense_account_id: string | null
  bank_account_id: string | null
  retained_earnings_account_id: string | null
  sales_tax_payable_account_id: string | null
  purchase_tax_receivable_account_id: string | null
}

const props = defineProps<{
  company: CompanyRef
  defaults: Defaults
  accounts: AccountRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Accounts', href: `/${props.company.slug}/accounts` },
  { title: 'Default Accounts', href: `/${props.company.slug}/accounting/default-accounts` },
]

const bySubtype = (subtypes: string[]) =>
  props.accounts.filter((a) => subtypes.includes(a.subtype))

const byType = (types: string[]) =>
  props.accounts.filter((a) => types.includes(a.type))

const arAccounts = computed(() => bySubtype(['accounts_receivable']))
const apAccounts = computed(() => bySubtype(['accounts_payable']))
const bankAccounts = computed(() => bySubtype(['bank', 'cash']))
const retainedEarnings = computed(() => bySubtype(['retained_earnings']))
const incomeAccounts = computed(() => byType(['revenue']))
const expenseAccounts = computed(() => byType(['expense', 'cogs', 'asset']))

const form = useForm({
  ar_account_id: props.defaults.ar_account_id ?? '',
  ap_account_id: props.defaults.ap_account_id ?? '',
  income_account_id: props.defaults.income_account_id ?? '',
  expense_account_id: props.defaults.expense_account_id ?? '',
  bank_account_id: props.defaults.bank_account_id ?? '',
  retained_earnings_account_id: props.defaults.retained_earnings_account_id ?? '',
  sales_tax_payable_account_id: props.defaults.sales_tax_payable_account_id ?? '',
  purchase_tax_receivable_account_id: props.defaults.purchase_tax_receivable_account_id ?? '',
})

const submit = () => {
  form
    .transform((d) => ({
      ...d,
      sales_tax_payable_account_id: d.sales_tax_payable_account_id || null,
      purchase_tax_receivable_account_id: d.purchase_tax_receivable_account_id || null,
    }))
    .patch(`/${props.company.slug}/accounting/default-accounts`, { preserveScroll: true })
}

const optionLabel = (a: AccountRef) => `${a.code} — ${a.name}`
</script>

<template>
  <Head title="Default Accounts" />
  <PageShell
    title="Default Accounts"
    :breadcrumbs="breadcrumbs"
    :icon="Settings2"
  >
    <Card>
      <CardHeader>
        <CardTitle>Used by posting templates</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Accounts Receivable (AR)</Label>
              <Select v-model="form.ar_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select AR account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in arAccounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.ar_account_id" class="text-sm text-red-600">{{ form.errors.ar_account_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Accounts Payable (AP)</Label>
              <Select v-model="form.ap_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select AP account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in apAccounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.ap_account_id" class="text-sm text-red-600">{{ form.errors.ap_account_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Default income (Revenue)</Label>
              <Select v-model="form.income_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select revenue account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in incomeAccounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.income_account_id" class="text-sm text-red-600">{{ form.errors.income_account_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Default expense</Label>
              <Select v-model="form.expense_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select expense account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.expense_account_id" class="text-sm text-red-600">{{ form.errors.expense_account_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Default bank/cash account</Label>
              <Select v-model="form.bank_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select bank/cash account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.bank_account_id" class="text-sm text-red-600">{{ form.errors.bank_account_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Retained earnings</Label>
              <Select v-model="form.retained_earnings_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select retained earnings account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="a in retainedEarnings" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.retained_earnings_account_id" class="text-sm text-red-600">{{ form.errors.retained_earnings_account_id }}</p>
            </div>
          </div>

          <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              Save defaults
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>

