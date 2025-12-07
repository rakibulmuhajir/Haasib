<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Users } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface CustomerRef {
  id: string
  customer_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string | null
  payment_terms: number | null
  tax_id: string | null
  credit_limit: number | null
  notes: string | null
  logo_url: string | null
  billing_address?: Record<string, string | null> | null
  shipping_address?: Record<string, string | null> | null
  is_active: boolean
}

interface SummaryRef {
  open_balance: number
  invoice_count: number
  total_billed: number
  available_credit: number
  credit_note_count: number
  payments_received: number
  base_currency: string
  overdue_balance: number
  paid_ytd: number
  invoiced_ytd: number
  avg_days_to_pay: number | null
}

interface InvoiceRef {
  id: string
  invoice_number: string
  invoice_date: string
  due_date: string
  total_amount: number
  paid_amount: number
  balance: number
  status: string
}

interface PaymentRef {
  id: string
  payment_number: string
  payment_date: string
  amount: number
  currency: string
  payment_method: string
  reference_number: string | null
}

const props = defineProps<{
  company: CompanyRef
  customer: CustomerRef
  summary: SummaryRef
  invoices: InvoiceRef[]
  payments: PaymentRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: props.customer.name, href: `/${props.company.slug}/customers/${props.customer.id}` },
]

const money = (val: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.customer.base_currency || props.company.base_currency,
  }).format(val ?? 0)

const invoiceColumns = [
  { key: 'invoice_number', label: 'Invoice #' },
  { key: 'invoice_date', label: 'Date' },
  { key: 'due_date', label: 'Due' },
  { key: 'total_amount', label: 'Total' },
  { key: 'paid_amount', label: 'Paid' },
  { key: 'balance', label: 'Balance' },
  { key: 'status', label: 'Status' },
]

const paymentColumns = [
  { key: 'payment_number', label: 'Payment #' },
  { key: 'payment_date', label: 'Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'reference_number', label: 'Reference' },
]

const invoiceRows = computed(() =>
  props.invoices.map((inv) => ({
    ...inv,
    total_amount: money(inv.total_amount),
    paid_amount: money(inv.paid_amount),
    balance: money(inv.balance),
  }))
)

const paymentRows = computed(() =>
  props.payments.map((p) => ({
    ...p,
    amount: money(p.amount),
    reference_number: p.reference_number ?? '—',
  }))
)
</script>

<template>
  <Head :title="`Customer ${customer.customer_number}`" />
  <PageShell
    :title="customer.name"
    :breadcrumbs="breadcrumbs"
    :icon="Users"
  >
    <div class="grid gap-4 md:grid-cols-3">
      <Card>
        <CardHeader>
          <CardTitle>Contact</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="font-semibold">{{ customer.name }}</div>
          <div class="text-muted-foreground">{{ customer.email || '—' }}</div>
          <div class="text-muted-foreground">{{ customer.phone || '—' }}</div>
          <Badge :variant="customer.is_active ? 'success' : 'secondary'">
            {{ customer.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Financials</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span>Open Balance</span>
            <span>{{ money(summary.open_balance) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Overdue</span>
            <span>{{ money(summary.overdue_balance) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Available Credit</span>
            <span>{{ money(summary.available_credit) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Payments Received</span>
            <span>{{ money(summary.payments_received) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Invoices</span>
            <span>{{ summary.invoice_count }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Terms</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span>Payment Terms</span>
            <span>{{ customer.payment_terms ?? '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span>Credit Limit</span>
            <span>{{ customer.credit_limit != null ? money(customer.credit_limit) : '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span>Tax ID</span>
            <span>{{ customer.tax_id || '—' }}</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-4 md:grid-cols-2 mt-6">
      <Card>
        <CardHeader>
          <CardTitle>Invoices</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable :columns="invoiceColumns" :data="invoiceRows" />
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Payments</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable :columns="paymentColumns" :data="paymentRows" />
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
