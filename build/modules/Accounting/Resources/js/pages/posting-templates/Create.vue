<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'

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
  sales_tax_payable_account_id: string | null
  purchase_tax_receivable_account_id: string | null
  discount_received_account_id: string | null
}

const props = defineProps<{
  company: CompanyRef
  accounts: AccountRef[]
  defaults: Defaults
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Company', href: `/${props.company.slug}` },
  { title: 'Posting Templates', href: `/${props.company.slug}/posting-templates` },
  { title: 'Create', href: `/${props.company.slug}/posting-templates/create` },
]

const docTypes = [
  { value: 'AR_INVOICE', label: 'AR Invoice' },
  { value: 'AR_PAYMENT', label: 'AR Payment' },
  { value: 'AR_CREDIT_NOTE', label: 'AR Credit Note' },
  { value: 'AP_BILL', label: 'AP Bill' },
  { value: 'AP_PAYMENT', label: 'AP Payment' },
  { value: 'AP_VENDOR_CREDIT', label: 'AP Vendor Credit' },
]

const rolesByDocType: Record<string, { role: string; label: string }[]> = {
  AR_INVOICE: [
    { role: 'AR', label: 'Accounts Receivable' },
    { role: 'REVENUE', label: 'Revenue' },
    { role: 'TAX_PAYABLE', label: 'Tax Payable' },
    { role: 'DISCOUNT_GIVEN', label: 'Discount Given' },
  ],
  AR_PAYMENT: [
    { role: 'AR', label: 'Accounts Receivable' },
    { role: 'BANK', label: 'Bank' },
    { role: 'CASH', label: 'Cash' },
  ],
  AR_CREDIT_NOTE: [
    { role: 'AR', label: 'Accounts Receivable' },
    { role: 'REVENUE', label: 'Revenue' },
    { role: 'TAX_PAYABLE', label: 'Tax Payable' },
  ],
  AP_BILL: [
    { role: 'AP', label: 'Accounts Payable' },
    { role: 'EXPENSE', label: 'Expense' },
    { role: 'TAX_RECEIVABLE', label: 'Tax Receivable' },
    { role: 'DISCOUNT_RECEIVED', label: 'Discount Received' },
  ],
  AP_PAYMENT: [
    { role: 'AP', label: 'Accounts Payable' },
    { role: 'BANK', label: 'Bank' },
    { role: 'CASH', label: 'Cash' },
  ],
  AP_VENDOR_CREDIT: [
    { role: 'AP', label: 'Accounts Payable' },
    { role: 'EXPENSE', label: 'Expense' },
    { role: 'TAX_RECEIVABLE', label: 'Tax Receivable' },
  ],
}

	const form = useForm({
	  doc_type: 'AR_INVOICE',
	  name: '',
	  description: '',
	  is_active: true,
	  is_default: false,
	  effective_from: new Date().toISOString().slice(0, 10),
	  effective_to: '',
	  lines: [] as { role: string; account_id: string }[],
	})

const roles = computed(() => rolesByDocType[form.doc_type] ?? [])

const defaultRoleAccounts = computed<Record<string, string>>(() => ({
  AR: props.defaults.ar_account_id ?? '',
  AP: props.defaults.ap_account_id ?? '',
  REVENUE: props.defaults.income_account_id ?? '',
  EXPENSE: props.defaults.expense_account_id ?? '',
  BANK: props.defaults.bank_account_id ?? '',
  CASH: props.defaults.bank_account_id ?? '',
  TAX_PAYABLE: props.defaults.sales_tax_payable_account_id ?? '',
  TAX_RECEIVABLE: props.defaults.purchase_tax_receivable_account_id ?? '',
  DISCOUNT_GIVEN: props.defaults.expense_account_id ?? '',
  DISCOUNT_RECEIVED: props.defaults.discount_received_account_id ?? props.defaults.income_account_id ?? '',
}))

const ensureLines = () => {
  const existing = new Map(form.lines.map((l) => [l.role, l]))
  form.lines = roles.value.map(({ role }) => {
    const current = existing.get(role)
    if (current?.account_id) return current
    return { role, account_id: defaultRoleAccounts.value[role] ?? '' }
  })
}

ensureLines()

const accountLabel = (acc: AccountRef) => `${acc.code} — ${acc.name}`

const roleMetaByRole = computed(() => {
  const entries = roles.value.map((r) => [r.role, r] as const)
  return Object.fromEntries(entries) as Record<string, { role: string; label: string }>
})

const roleHelp: Record<string, string> = {
  AR: 'Customer balances owed to you.',
  AP: 'Vendor balances you still need to pay.',
  REVENUE: 'Used when a line has no specific income account.',
  EXPENSE: 'Used when a line has no specific expense or inventory account.',
  BANK: 'Where money is deposited for payments and receipts.',
  CASH: 'Use if payments are handled as physical cash.',
  TAX_PAYABLE: 'Sales tax you owe (only needed if tax is used).',
  TAX_RECEIVABLE: 'Purchase tax you can claim (only needed if tax is used).',
  DISCOUNT_GIVEN: 'Discounts you give on invoices.',
  DISCOUNT_RECEIVED: 'Discounts you receive on bills.',
}

const save = () => {
  ensureLines()
  form.post(`/${props.company.slug}/posting-templates`, {
    onSuccess: () => {
      // redirected by server
    },
  })
}
</script>

<template>
  <Head title="Create Posting Template" />

  <PageShell title="Create Posting Template" :breadcrumbs="breadcrumbs">
    <Card>
      <CardHeader>
        <CardTitle>Template</CardTitle>
        <CardDescription>Define account mappings for a document type.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="rounded-md border border-muted bg-muted/40 p-3 text-xs text-muted-foreground">
          These mappings tell the system which accounts to debit or credit when a document is posted.
          For bills, use inventory accounts on the bill lines for fuel purchases; EXPENSE is only a fallback.
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Doc Type</Label>
            <Select v-model="form.doc_type" @update:modelValue="ensureLines">
              <SelectTrigger>
                <SelectValue placeholder="Select doc type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="d in docTypes" :key="d.value" :value="d.value">
                  {{ d.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>Name</Label>
            <Input v-model="form.name" placeholder="e.g., Default AR Invoice" />
            <div v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</div>
          </div>
        </div>

        <div class="space-y-2">
          <Label>Description</Label>
          <Input v-model="form.description" placeholder="Optional" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Effective From</Label>
            <Input v-model="form.effective_from" type="date" />
          </div>
          <div class="space-y-2">
            <Label>Effective To</Label>
            <Input v-model="form.effective_to" type="date" />
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Switch id="is-active" v-model:checked="form.is_active" />
            <Label for="is-active">Active</Label>
          </div>
          <div class="flex items-center gap-3">
            <Switch id="is-default" v-model:checked="form.is_default" />
            <Label for="is-default">Default for this doc type</Label>
          </div>
        </div>

        <div class="space-y-3">
          <div class="font-medium">Role Mappings</div>
          <div v-for="line in form.lines" :key="line.role" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
            <div>
              <div class="text-sm font-medium">
                {{ roleMetaByRole[line.role]?.label ?? line.role }}
                <span class="text-xs text-muted-foreground">({{ line.role }})</span>
              </div>
              <div class="text-xs text-muted-foreground">{{ roleHelp[line.role] ?? '' }}</div>
            </div>
            <div class="md:col-span-2">
              <Select v-model="line.account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">— None —</SelectItem>
                  <SelectItem v-for="acc in accounts" :key="acc.id" :value="acc.id">
                    {{ accountLabel(acc) }}
                  </SelectItem>
                </SelectContent>
              </Select>
	            </div>
	          </div>
          <div v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</div>
        </div>

        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" type="button" @click="router.get(`/${company.slug}/posting-templates`)">
            Cancel
          </Button>
          <Button type="button" :disabled="form.processing" @click="save">
            Create
          </Button>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
