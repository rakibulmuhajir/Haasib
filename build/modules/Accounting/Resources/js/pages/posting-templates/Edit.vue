<script setup lang="ts">
	import { computed, ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
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

interface PostingTemplateLine {
  id: string
  role: string
  account_id: string
}

interface PostingTemplate {
  id: string
  doc_type: string
  name: string
  description?: string | null
  is_active: boolean
  is_default: boolean
  version: number
  effective_from: string
  effective_to?: string | null
  lines: PostingTemplateLine[]
}

interface PreviewEntry {
  account_id: string
  type: 'debit' | 'credit'
  amount: number
  description?: string
  account?: AccountRef | null
}

const props = defineProps<{
  company: CompanyRef
  template: PostingTemplate
  accounts: AccountRef[]
  preview?: {
    transaction: {
      type: string
      number: string
      date: string
      currency: string
      total: number
      tax: number
      discount: number
    }
    entries: PreviewEntry[]
  } | null
  defaults: {
    ar_account_id: string | null
    ap_account_id: string | null
    income_account_id: string | null
    expense_account_id: string | null
    bank_account_id: string | null
    sales_tax_payable_account_id: string | null
    purchase_tax_receivable_account_id: string | null
    discount_received_account_id: string | null
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Company', href: `/${props.company.slug}` },
  { title: 'Posting Templates', href: `/${props.company.slug}/posting-templates` },
  { title: 'Edit', href: `/${props.company.slug}/posting-templates/${props.template.id}/edit` },
]

const rolesByDocType: Record<string, { role: string; label: string; required: boolean }[]> = {
  AR_INVOICE: [
    { role: 'AR', label: 'Accounts Receivable', required: true },
    { role: 'REVENUE', label: 'Revenue', required: true },
    { role: 'TAX_PAYABLE', label: 'Tax Payable', required: false },
    { role: 'DISCOUNT_GIVEN', label: 'Discount Given', required: false },
  ],
  AR_PAYMENT: [
    { role: 'AR', label: 'Accounts Receivable', required: true },
    { role: 'BANK', label: 'Bank', required: false },
    { role: 'CASH', label: 'Cash', required: false },
  ],
  AR_CREDIT_NOTE: [
    { role: 'AR', label: 'Accounts Receivable', required: true },
    { role: 'REVENUE', label: 'Revenue', required: true },
    { role: 'TAX_PAYABLE', label: 'Tax Payable', required: false },
  ],
  AP_BILL: [
    { role: 'AP', label: 'Accounts Payable', required: true },
    { role: 'EXPENSE', label: 'Expense', required: true },
    { role: 'TAX_RECEIVABLE', label: 'Tax Receivable', required: false },
    { role: 'DISCOUNT_RECEIVED', label: 'Discount Received', required: false },
  ],
  AP_PAYMENT: [
    { role: 'AP', label: 'Accounts Payable', required: true },
    { role: 'BANK', label: 'Bank', required: false },
    { role: 'CASH', label: 'Cash', required: false },
  ],
  AP_VENDOR_CREDIT: [
    { role: 'AP', label: 'Accounts Payable', required: true },
    { role: 'EXPENSE', label: 'Expense', required: true },
    { role: 'TAX_RECEIVABLE', label: 'Tax Receivable', required: false },
  ],
}

const roles = computed(() => rolesByDocType[props.template.doc_type] ?? [])

	const form = useForm({
	  name: props.template.name,
	  description: props.template.description ?? '',
	  is_active: props.template.is_active,
	  is_default: props.template.is_default,
	  effective_from: props.template.effective_from,
	  effective_to: props.template.effective_to ?? '',
	  lines: [] as { role: string; account_id: string }[],
	})

	const ensureLines = () => {
	  const existing = new Map(props.template.lines.map((l) => [l.role, l.account_id]))
	  const current = new Map(form.lines.map((l) => [l.role, l.account_id]))

	  form.lines = roles.value.map(({ role }) => ({
	    role,
	    account_id: current.get(role) ?? existing.get(role) ?? '',
	  }))
	}

	watch(
	  () => props.template.id,
	  () => ensureLines(),
	  { immediate: true },
	)

	const roleMetaByRole = computed(() => {
	  const entries = roles.value.map((r) => [r.role, r] as const)
	  return Object.fromEntries(entries) as Record<string, { role: string; label: string; required: boolean }>
	})

const accountLabel = (acc: AccountRef) => `${acc.code} — ${acc.name}`

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
  form.put(`/${props.company.slug}/posting-templates/${props.template.id}`, {
    preserveScroll: true,
  })
}

const previewId = ref('')
const runPreview = () => {
  const query = previewId.value ? `?preview_id=${encodeURIComponent(previewId.value)}` : ''
  router.get(`/${props.company.slug}/posting-templates/${props.template.id}/edit${query}`, {}, { preserveScroll: true })
}

const totalDebits = computed(() => (props.preview?.entries ?? []).filter((e) => e.type === 'debit').reduce((s, e) => s + Number(e.amount), 0))
const totalCredits = computed(() => (props.preview?.entries ?? []).filter((e) => e.type === 'credit').reduce((s, e) => s + Number(e.amount), 0))
</script>

<template>
  <Head title="Edit Posting Template" />

  <PageShell :title="`Edit Posting Template`" :breadcrumbs="breadcrumbs">
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          {{ template.name }}
          <Badge variant="outline">{{ template.doc_type }}</Badge>
          <Badge v-if="template.is_default" variant="default">Default</Badge>
        </CardTitle>
        <CardDescription>
          Update account mappings for this document type.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="rounded-md border border-muted bg-muted/40 p-3 text-xs text-muted-foreground">
          These mappings tell the system which accounts to debit or credit when a document is posted.
          For bills, use inventory accounts on the bill lines for fuel purchases; EXPENSE is only a fallback.
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Name</Label>
            <Input v-model="form.name" />
            <div v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</div>
          </div>
          <div class="space-y-2">
            <Label>Description</Label>
            <Input v-model="form.description" />
          </div>
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
	              <div class="flex items-center gap-2">
	                <div class="text-sm font-medium">
	                  {{ roleMetaByRole[line.role]?.label ?? line.role }}
	                  <span class="text-xs text-muted-foreground">({{ line.role }})</span>
	                </div>
	                <Badge v-if="roleMetaByRole[line.role]?.required" variant="secondary">Required</Badge>
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
	          <div v-if="(form.errors as any).posting" class="text-sm text-destructive">{{ (form.errors as any).posting }}</div>
	        </div>

        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" type="button" @click="router.get(`/${company.slug}/posting-templates`)">
            Back
          </Button>
          <Button type="button" :disabled="form.processing" @click="save">
            Save
          </Button>
        </div>
      </CardContent>
    </Card>

    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Preview</CardTitle>
        <CardDescription>
          Enter a document UUID to preview the journal entry for this template (AR Invoice or AP Bill templates only).
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="flex flex-col md:flex-row gap-2 md:items-end">
          <div class="flex-1 space-y-2">
            <Label>Document ID</Label>
            <Input v-model="previewId" placeholder="UUID (invoice_id or bill_id)" />
          </div>
          <Button variant="outline" type="button" @click="runPreview">
            Preview
          </Button>
        </div>

        <div v-if="preview" class="space-y-3">
          <div class="text-sm text-muted-foreground">
            {{ preview.transaction.type }} • {{ preview.transaction.number }} • {{ preview.transaction.currency }}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>Total: {{ preview.transaction.total }}</div>
            <div>Tax: {{ preview.transaction.tax }}</div>
            <div>Discount: {{ preview.transaction.discount }}</div>
          </div>

          <div class="rounded-md border">
            <div class="grid grid-cols-12 gap-2 p-3 text-sm font-medium bg-muted/50">
              <div class="col-span-6">Account</div>
              <div class="col-span-3 text-right">Debit</div>
              <div class="col-span-3 text-right">Credit</div>
            </div>
            <div v-for="(e, idx) in preview.entries" :key="idx" class="grid grid-cols-12 gap-2 p-3 text-sm border-t">
              <div class="col-span-6">
                <div class="font-medium">
                  {{ e.account?.code ?? e.account_id }} — {{ e.account?.name ?? '' }}
                </div>
                <div class="text-muted-foreground">{{ e.description }}</div>
              </div>
              <div class="col-span-3 text-right">
                {{ e.type === 'debit' ? e.amount : '' }}
              </div>
              <div class="col-span-3 text-right">
                {{ e.type === 'credit' ? e.amount : '' }}
              </div>
            </div>
            <div class="grid grid-cols-12 gap-2 p-3 text-sm border-t font-medium">
              <div class="col-span-6 text-right">Totals</div>
              <div class="col-span-3 text-right">{{ totalDebits.toFixed(2) }}</div>
              <div class="col-span-3 text-right">{{ totalCredits.toFixed(2) }}</div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
