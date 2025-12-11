<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import type { BreadcrumbItem } from '@/types'
import {
  Users,
  BarChart3,
  Settings,
  MapPin,
  Receipt,
  AlertTriangle,
  Wallet,
  CreditCard,
  TrendingUp,
  Calendar,
  Clock,
  FileText,
  Plus,
  DollarSign,
  Building2,
  Mail,
  Phone,
  Hash,
  Globe,
  Percent,
  FileEdit,
  Copy,
  CheckCircle2,
  XCircle,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AddressRef {
  street?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  country?: string | null
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
  billing_address?: AddressRef | null
  shipping_address?: AddressRef | null
  is_active: boolean
}

interface AgingRef {
  current: number
  bucket_1_30: number
  bucket_31_60: number
  bucket_61_90: number
  bucket_90_plus: number
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
  aging: AgingRef
}

interface CurrencyOption {
  currency_code: string
  is_base: boolean
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
  currencies: CurrencyOption[]
  canEdit: boolean
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: props.customer.name },
])

const activeTab = ref('overview')

// Setup inline editing for settings
const settingsEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/customers/${props.customer.id}`,
  successMessage: 'Customer updated successfully',
  errorMessage: 'Failed to update customer',
})

// Register editable fields
const nameField = settingsEdit.registerField('name', props.customer.name)
const emailField = settingsEdit.registerField('email', props.customer.email || '')
const phoneField = settingsEdit.registerField('phone', props.customer.phone || '')
const paymentTermsField = settingsEdit.registerField('payment_terms', props.customer.payment_terms ?? 30)
const creditLimitField = settingsEdit.registerField('credit_limit', props.customer.credit_limit ?? 0)
const currencyField = settingsEdit.registerField('base_currency', props.customer.base_currency || props.company.base_currency)
const taxIdField = settingsEdit.registerField('tax_id', props.customer.tax_id || '')
const statusField = settingsEdit.registerField('is_active', props.customer.is_active)
const notesField = settingsEdit.registerField('notes', props.customer.notes || '')

// Address editing
const addressEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/customers/${props.customer.id}`,
  successMessage: 'Address updated successfully',
  errorMessage: 'Failed to update address',
})

// Billing address fields
const billingStreet = ref(props.customer.billing_address?.street || '')
const billingCity = ref(props.customer.billing_address?.city || '')
const billingState = ref(props.customer.billing_address?.state || '')
const billingZip = ref(props.customer.billing_address?.zip || '')
const billingCountry = ref(props.customer.billing_address?.country || '')
const billingEditing = ref(false)
const billingSaving = ref(false)

// Shipping address fields
const shippingStreet = ref(props.customer.shipping_address?.street || '')
const shippingCity = ref(props.customer.shipping_address?.city || '')
const shippingState = ref(props.customer.shipping_address?.state || '')
const shippingZip = ref(props.customer.shipping_address?.zip || '')
const shippingCountry = ref(props.customer.shipping_address?.country || '')
const shippingEditing = ref(false)
const shippingSaving = ref(false)

// Currency options for select
const currencyOptions = computed(() =>
  props.currencies.map((c) => ({
    value: c.currency_code,
    label: c.is_base ? `${c.currency_code} (Base)` : c.currency_code,
  }))
)

// Status options
const statusOptions = [
  { value: true, label: 'Active' },
  { value: false, label: 'Inactive' },
]

// Payment terms options (common values)
const paymentTermsOptions = [
  { value: 0, label: 'Due on Receipt' },
  { value: 7, label: 'Net 7' },
  { value: 14, label: 'Net 14' },
  { value: 15, label: 'Net 15' },
  { value: 30, label: 'Net 30' },
  { value: 45, label: 'Net 45' },
  { value: 60, label: 'Net 60' },
  { value: 90, label: 'Net 90' },
]

const canManage = computed(() => props.canEdit !== false)

const money = (val: number | null | undefined) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.customer.base_currency || props.company.base_currency,
  }).format(val ?? 0)

const creditUsedPercent = computed(() => {
  if (!props.customer.credit_limit || props.customer.credit_limit === 0) return null
  const used = props.summary.open_balance
  return Math.min(100, Math.round((used / props.customer.credit_limit) * 100))
})

const getInitials = (name: string) => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const formatAddress = (addr: AddressRef | null | undefined) => {
  if (!addr) return null
  const parts = [addr.street, addr.city, addr.state, addr.zip, addr.country].filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : null
}

const invoiceColumns = [
  { key: 'invoice_number', label: 'Invoice #' },
  { key: 'invoice_date', label: 'Date' },
  { key: 'due_date', label: 'Due' },
  { key: 'total_amount', label: 'Total' },
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

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default',
    sent: 'secondary',
    draft: 'outline',
    overdue: 'destructive',
    void: 'secondary',
    cancelled: 'secondary',
  }
  return variants[status.toLowerCase()] || 'outline'
}

// Address save handlers
const saveBillingAddress = async () => {
  billingSaving.value = true
  router.patch(
    `/${props.company.slug}/customers/${props.customer.id}`,
    {
      billing_address: {
        street: billingStreet.value || '',
        city: billingCity.value || '',
        state: billingState.value || '',
        zip: billingZip.value || '',
        country: billingCountry.value || '',
      },
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        billingEditing.value = false
        billingSaving.value = false
        toast.success('Billing address updated')
      },
      onError: () => {
        billingSaving.value = false
        toast.error('Failed to update billing address')
      },
    }
  )
}

const saveShippingAddress = async () => {
  shippingSaving.value = true
  router.patch(
    `/${props.company.slug}/customers/${props.customer.id}`,
    {
      shipping_address: {
        street: shippingStreet.value || '',
        city: shippingCity.value || '',
        state: shippingState.value || '',
        zip: shippingZip.value || '',
        country: shippingCountry.value || '',
      },
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        shippingEditing.value = false
        shippingSaving.value = false
        toast.success('Shipping address updated')
      },
      onError: () => {
        shippingSaving.value = false
        toast.error('Failed to update shipping address')
      },
    }
  )
}

const copyBillingToShipping = () => {
  shippingStreet.value = billingStreet.value
  shippingCity.value = billingCity.value
  shippingState.value = billingState.value
  shippingZip.value = billingZip.value
  shippingCountry.value = billingCountry.value
  toast.success('Billing address copied to shipping')
}

const cancelBillingEdit = () => {
  billingStreet.value = props.customer.billing_address?.street || ''
  billingCity.value = props.customer.billing_address?.city || ''
  billingState.value = props.customer.billing_address?.state || ''
  billingZip.value = props.customer.billing_address?.zip || ''
  billingCountry.value = props.customer.billing_address?.country || ''
  billingEditing.value = false
}

const cancelShippingEdit = () => {
  shippingStreet.value = props.customer.shipping_address?.street || ''
  shippingCity.value = props.customer.shipping_address?.city || ''
  shippingState.value = props.customer.shipping_address?.state || ''
  shippingZip.value = props.customer.shipping_address?.zip || ''
  shippingCountry.value = props.customer.shipping_address?.country || ''
  shippingEditing.value = false
}
</script>

<template>
  <Head :title="`Customer: ${customer.name}`" />
  <PageShell
    :title="customer.name"
    :icon="Users"
    :breadcrumbs="breadcrumbs"
    :badge="{ text: customer.is_active ? 'Active' : 'Inactive', variant: customer.is_active ? 'default' : 'secondary' }"
    compact
  >
    <template #description>
      <div class="flex items-center gap-3">
        <Avatar class="h-8 w-8">
          <AvatarImage v-if="customer.logo_url" :src="customer.logo_url" :alt="customer.name" />
          <AvatarFallback class="bg-zinc-100 text-zinc-600 text-xs">{{ getInitials(customer.name) }}</AvatarFallback>
        </Avatar>
        <span class="font-mono text-zinc-400">{{ customer.customer_number }}</span>
        <span v-if="customer.email" class="text-zinc-300">•</span>
        <span v-if="customer.email" class="text-zinc-500">{{ customer.email }}</span>
      </div>
    </template>

    <template #actions>
      <Button size="sm" @click="router.visit(`/${company.slug}/invoices/create?customer=${customer.id}`)">
        <Plus class="mr-2 h-4 w-4" />
        New Invoice
      </Button>
    </template>

    <Tabs v-model="activeTab" class="w-full">
      <TabsList class="mb-6 bg-zinc-100">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Overview
        </TabsTrigger>
        <TabsTrigger value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger value="addresses" class="gap-2">
          <MapPin class="h-4 w-4" />
          Addresses
        </TabsTrigger>
      </TabsList>

      <!-- Overview Tab -->
      <TabsContent value="overview" class="space-y-6">
        <!-- Key Financial Stats -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Open Balance</CardTitle>
              <Receipt class="h-4 w-4 text-zinc-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ money(summary.open_balance) }}</div>
              <p class="text-xs text-zinc-500 mt-1">{{ summary.invoice_count }} open invoice{{ summary.invoice_count === 1 ? '' : 's' }}</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Overdue</CardTitle>
              <AlertTriangle class="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div :class="['text-2xl font-semibold', summary.overdue_balance > 0 ? 'text-amber-600' : 'text-zinc-900']">
                {{ money(summary.overdue_balance) }}
              </div>
              <p class="text-xs text-zinc-500 mt-1">Past due date</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Available Credit</CardTitle>
              <CreditCard class="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ money(summary.available_credit) }}</div>
              <p class="text-xs text-zinc-500 mt-1">{{ summary.credit_note_count }} credit note{{ summary.credit_note_count === 1 ? '' : 's' }}</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Paid YTD</CardTitle>
              <Wallet class="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ money(summary.paid_ytd) }}</div>
              <p class="text-xs text-zinc-500 mt-1">This year</p>
            </CardContent>
          </Card>
        </div>

        <!-- AR Aging & Quick Stats -->
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-sm font-medium text-zinc-500">AR Aging</CardTitle>
              <CardDescription>Outstanding balance by age</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-zinc-700">
              <div class="flex justify-between">
                <span>Current</span>
                <span class="font-medium text-zinc-900">{{ money(summary.aging?.current ?? 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span>1-30 days</span>
                <span class="font-medium text-zinc-900">{{ money(summary.aging?.bucket_1_30 ?? 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span>31-60 days</span>
                <span class="font-medium text-zinc-900">{{ money(summary.aging?.bucket_31_60 ?? 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span>61-90 days</span>
                <span class="font-medium text-zinc-900">{{ money(summary.aging?.bucket_61_90 ?? 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span>90+ days</span>
                <span :class="['font-medium', (summary.aging?.bucket_90_plus ?? 0) > 0 ? 'text-red-600' : 'text-zinc-900']">
                  {{ money(summary.aging?.bucket_90_plus ?? 0) }}
                </span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-sm font-medium text-zinc-500">Quick Stats</CardTitle>
              <CardDescription>Customer performance metrics</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-zinc-700">
              <div class="flex justify-between">
                <span class="flex items-center gap-2">
                  <FileText class="h-4 w-4 text-zinc-400" />
                  Total Billed
                </span>
                <span class="font-medium text-zinc-900">{{ money(summary.total_billed) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="flex items-center gap-2">
                  <TrendingUp class="h-4 w-4 text-zinc-400" />
                  Invoiced YTD
                </span>
                <span class="font-medium text-zinc-900">{{ money(summary.invoiced_ytd) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="flex items-center gap-2">
                  <Clock class="h-4 w-4 text-zinc-400" />
                  Avg Days to Pay
                </span>
                <span class="font-medium text-zinc-900">{{ summary.avg_days_to_pay ? `${summary.avg_days_to_pay} days` : '—' }}</span>
              </div>
              <div v-if="creditUsedPercent !== null" class="flex justify-between">
                <span class="flex items-center gap-2">
                  <Percent class="h-4 w-4 text-zinc-400" />
                  Credit Used
                </span>
                <span :class="['font-medium', creditUsedPercent > 80 ? 'text-amber-600' : 'text-zinc-900']">
                  {{ creditUsedPercent }}%
                </span>
              </div>
              <div class="pt-2 flex flex-wrap gap-2">
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/invoices/create?customer=${customer.id}`)">
                  <Plus class="mr-1 h-3 w-3" />
                  Invoice
                </Button>
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/payments/create?customer=${customer.id}`)">
                  <DollarSign class="mr-1 h-3 w-3" />
                  Payment
                </Button>
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/credit-notes/create?customer=${customer.id}`)">
                  <FileEdit class="mr-1 h-3 w-3" />
                  Credit Note
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Invoices & Payments Tables -->
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-sm font-medium text-zinc-500">Recent Invoices</CardTitle>
                <CardDescription>Last 25 invoices</CardDescription>
              </div>
              <Button size="sm" variant="ghost" @click="router.visit(`/${company.slug}/invoices?customer=${customer.id}`)">
                View All
              </Button>
            </CardHeader>
            <CardContent>
              <DataTable :columns="invoiceColumns" :data="invoiceRows" key-field="id" compact>
                <template #cell-status="{ row }">
                  <Badge :variant="getStatusVariant(row.status)" class="capitalize">
                    {{ row.status }}
                  </Badge>
                </template>
              </DataTable>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-sm font-medium text-zinc-500">Recent Payments</CardTitle>
                <CardDescription>Last 25 payments</CardDescription>
              </div>
              <Button size="sm" variant="ghost" @click="router.visit(`/${company.slug}/payments?customer=${customer.id}`)">
                View All
              </Button>
            </CardHeader>
            <CardContent>
              <DataTable :columns="paymentColumns" :data="paymentRows" key-field="id" compact />
            </CardContent>
          </Card>
        </div>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent value="settings" class="space-y-6">
        <!-- Contact Information -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Contact Information</CardTitle>
            <CardDescription class="text-zinc-500">
              {{ canManage ? 'Click the pencil icon to edit' : 'Contact an admin to make changes' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <InlineEditable
                v-model="nameField.value.value"
                label="Customer Name"
                :editing="nameField.isEditing.value"
                :saving="nameField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Building2"
                @start-edit="nameField.startEditing()"
                @save="nameField.save()"
                @cancel="nameField.cancelEditing()"
              />

              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Customer Number</Label>
                <div class="flex items-center gap-2 font-mono text-base text-zinc-900">
                  <Hash class="h-4 w-4 text-zinc-400" />
                  {{ customer.customer_number }}
                </div>
                <p class="text-xs text-zinc-400">Auto-generated, cannot be changed</p>
              </div>

              <InlineEditable
                v-model="emailField.value.value"
                label="Email"
                :editing="emailField.isEditing.value"
                :saving="emailField.isSaving.value"
                :can-edit="canManage"
                type="email"
                :icon="Mail"
                placeholder="billing@example.com"
                @start-edit="emailField.startEditing()"
                @save="emailField.save()"
                @cancel="emailField.cancelEditing()"
              />

              <InlineEditable
                v-model="phoneField.value.value"
                label="Phone"
                :editing="phoneField.isEditing.value"
                :saving="phoneField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Phone"
                placeholder="+1 (555) 123-4567"
                @start-edit="phoneField.startEditing()"
                @save="phoneField.save()"
                @cancel="phoneField.cancelEditing()"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Billing Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900 flex items-center gap-2">
              <CreditCard class="h-4 w-4" />
              Billing Settings
            </CardTitle>
            <CardDescription class="text-zinc-500">
              Payment terms, credit limits, and tax information
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <InlineEditable
                v-model="paymentTermsField.value.value"
                label="Payment Terms"
                :editing="paymentTermsField.isEditing.value"
                :saving="paymentTermsField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="paymentTermsOptions"
                :icon="Calendar"
                helper-text="Default terms for new invoices"
                @start-edit="paymentTermsField.startEditing()"
                @save="paymentTermsField.save()"
                @cancel="paymentTermsField.cancelEditing()"
              />

              <InlineEditable
                v-model="creditLimitField.value.value"
                label="Credit Limit"
                :editing="creditLimitField.isEditing.value"
                :saving="creditLimitField.isSaving.value"
                :can-edit="canManage"
                type="number"
                :icon="DollarSign"
                :display-value="money(creditLimitField.value.value as number)"
                helper-text="Maximum outstanding balance allowed"
                @start-edit="creditLimitField.startEditing()"
                @save="creditLimitField.save()"
                @cancel="creditLimitField.cancelEditing()"
              />

              <InlineEditable
                v-model="currencyField.value.value"
                label="Currency"
                :editing="currencyField.isEditing.value"
                :saving="currencyField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="currencyOptions"
                :icon="Globe"
                helper-text="Default currency for invoices"
                @start-edit="currencyField.startEditing()"
                @save="currencyField.save()"
                @cancel="currencyField.cancelEditing()"
              />

              <InlineEditable
                v-model="taxIdField.value.value"
                label="Tax ID"
                :editing="taxIdField.isEditing.value"
                :saving="taxIdField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="FileText"
                placeholder="Enter tax ID"
                helper-text="VAT, GST, or tax registration number"
                @start-edit="taxIdField.startEditing()"
                @save="taxIdField.save()"
                @cancel="taxIdField.cancelEditing()"
              />

              <InlineEditable
                v-model="statusField.value.value"
                label="Status"
                :editing="statusField.isEditing.value"
                :saving="statusField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="statusOptions"
                helper-text="Inactive customers won't appear in dropdowns"
                @start-edit="statusField.startEditing()"
                @save="statusField.save()"
                @cancel="statusField.cancelEditing()"
              >
                <template #display>
                  <Badge :variant="statusField.value.value ? 'default' : 'secondary'">
                    <component :is="statusField.value.value ? CheckCircle2 : XCircle" class="mr-1 h-3 w-3" />
                    {{ statusField.value.value ? 'Active' : 'Inactive' }}
                  </Badge>
                </template>
              </InlineEditable>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Internal Notes</CardTitle>
            <CardDescription class="text-zinc-500">
              Private notes about this customer (not visible on invoices)
            </CardDescription>
          </CardHeader>
          <CardContent>
            <InlineEditable
              v-model="notesField.value.value"
              label="Notes"
              :editing="notesField.isEditing.value"
              :saving="notesField.isSaving.value"
              :can-edit="canManage"
              type="textarea"
              placeholder="Add notes about this customer..."
              @start-edit="notesField.startEditing()"
              @save="notesField.save()"
              @cancel="notesField.cancelEditing()"
            />
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Addresses Tab -->
      <TabsContent value="addresses" class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
          <!-- Billing Address -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-zinc-900">Billing Address</CardTitle>
                <CardDescription class="text-zinc-500">Used on invoices and statements</CardDescription>
              </div>
              <Button
                v-if="canManage && !billingEditing"
                variant="ghost"
                size="sm"
                @click="billingEditing = true"
              >
                Edit
              </Button>
            </CardHeader>
            <CardContent>
              <div v-if="!billingEditing" class="space-y-2 text-sm text-zinc-700">
                <div v-if="formatAddress(customer.billing_address)">
                  <p v-if="customer.billing_address?.street">{{ customer.billing_address.street }}</p>
                  <p v-if="customer.billing_address?.city || customer.billing_address?.state || customer.billing_address?.zip">
                    {{ [customer.billing_address?.city, customer.billing_address?.state, customer.billing_address?.zip].filter(Boolean).join(', ') }}
                  </p>
                  <p v-if="customer.billing_address?.country">{{ customer.billing_address.country }}</p>
                </div>
                <p v-else class="text-zinc-400">No billing address set</p>
              </div>

              <form v-else class="space-y-3" @submit.prevent="saveBillingAddress">
                <div class="space-y-2">
                  <Label for="billing_street">Street</Label>
                  <Input id="billing_street" v-model="billingStreet" placeholder="123 Main St" />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="billing_city">City</Label>
                    <Input id="billing_city" v-model="billingCity" placeholder="New York" />
                  </div>
                  <div class="space-y-2">
                    <Label for="billing_state">State</Label>
                    <Input id="billing_state" v-model="billingState" placeholder="NY" />
                  </div>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="billing_zip">ZIP Code</Label>
                    <Input id="billing_zip" v-model="billingZip" placeholder="10001" />
                  </div>
                  <div class="space-y-2">
                    <Label for="billing_country">Country</Label>
                    <Input id="billing_country" v-model="billingCountry" placeholder="US" maxlength="2" />
                  </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                  <Button type="button" variant="outline" size="sm" @click="cancelBillingEdit" :disabled="billingSaving">
                    Cancel
                  </Button>
                  <Button type="submit" size="sm" :disabled="billingSaving">
                    <span v-if="billingSaving" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                    Save
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <!-- Shipping Address -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-zinc-900">Shipping Address</CardTitle>
                <CardDescription class="text-zinc-500">Delivery address for goods</CardDescription>
              </div>
              <div class="flex gap-2">
                <Button
                  v-if="canManage && !shippingEditing && formatAddress(customer.billing_address)"
                  variant="ghost"
                  size="sm"
                  @click="copyBillingToShipping"
                >
                  <Copy class="mr-1 h-3 w-3" />
                  Copy Billing
                </Button>
                <Button
                  v-if="canManage && !shippingEditing"
                  variant="ghost"
                  size="sm"
                  @click="shippingEditing = true"
                >
                  Edit
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="!shippingEditing" class="space-y-2 text-sm text-zinc-700">
                <div v-if="formatAddress(customer.shipping_address)">
                  <p v-if="customer.shipping_address?.street">{{ customer.shipping_address.street }}</p>
                  <p v-if="customer.shipping_address?.city || customer.shipping_address?.state || customer.shipping_address?.zip">
                    {{ [customer.shipping_address?.city, customer.shipping_address?.state, customer.shipping_address?.zip].filter(Boolean).join(', ') }}
                  </p>
                  <p v-if="customer.shipping_address?.country">{{ customer.shipping_address.country }}</p>
                </div>
                <p v-else class="text-zinc-400">No shipping address set</p>
              </div>

              <form v-else class="space-y-3" @submit.prevent="saveShippingAddress">
                <div class="space-y-2">
                  <Label for="shipping_street">Street</Label>
                  <Input id="shipping_street" v-model="shippingStreet" placeholder="123 Main St" />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="shipping_city">City</Label>
                    <Input id="shipping_city" v-model="shippingCity" placeholder="New York" />
                  </div>
                  <div class="space-y-2">
                    <Label for="shipping_state">State</Label>
                    <Input id="shipping_state" v-model="shippingState" placeholder="NY" />
                  </div>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="shipping_zip">ZIP Code</Label>
                    <Input id="shipping_zip" v-model="shippingZip" placeholder="10001" />
                  </div>
                  <div class="space-y-2">
                    <Label for="shipping_country">Country</Label>
                    <Input id="shipping_country" v-model="shippingCountry" placeholder="US" maxlength="2" />
                  </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                  <Button type="button" variant="outline" size="sm" @click="cancelShippingEdit" :disabled="shippingSaving">
                    Cancel
                  </Button>
                  <Button type="submit" size="sm" :disabled="shippingSaving">
                    <span v-if="shippingSaving" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                    Save
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
