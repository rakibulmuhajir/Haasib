<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'
import {
  User,
  ArrowLeft,
  Pencil,
  Trash2,
  Mail,
  Phone,
  MapPin,
  CreditCard,
  Calendar,
  Hash,
  Receipt,
  Wallet,
  PiggyBank,
  ScrollText,
  CreditCard as CreditCardIcon,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Address {
  street?: string
  city?: string
  state?: string
  zip?: string
  country?: string
}

interface Customer {
  id: string
  customer_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string
  payment_terms: number
  tax_id: string | null
  credit_limit: number | null
  notes: string | null
  billing_address: Address | null
  shipping_address: Address | null
  is_active: boolean
  created_at: string
  updated_at: string
}

interface InvoiceRow {
  id: string
  invoice_number: string
  invoice_date: string
  due_date: string
  total_amount: number
  paid_amount: number
  balance: number
  status: string
}

interface PaymentRow {
  id: string
  payment_number: string
  payment_date: string
  amount: number
  currency: string
  payment_method: string
  reference_number: string | null
  notes: string | null
  payment_allocations?: Array<{ invoice?: { invoice_number: string } }>
}

const props = defineProps<{
  company: CompanyRef
  customer: Customer
  summary: {
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
  invoices: InvoiceRow[]
  payments: PaymentRow[]
}>()

const deleteDialogOpen = ref(false)
const activeTab = ref('invoices')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: props.customer.name },
])

const formatAddress = (address: Address | null): string => {
  if (!address) return '—'
  const parts = [
    address.street,
    address.city,
    address.state,
    address.zip,
    address.country,
  ].filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : '—'
}

const formatCurrency = (amount: number | null, currency: string): string => {
  if (amount === null) return 'No limit'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

const formatMoney = (amount: number): string => {
  return formatCurrency(amount, props.summary.base_currency)
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const handleEdit = () => {
  router.visit(`/${props.company.slug}/customers/${props.customer.id}/edit`)
}

const handleDelete = () => {
  router.delete(`/${props.company.slug}/customers/${props.customer.id}`, {
    onSuccess: () => {
      deleteDialogOpen.value = false
    },
  })
}

const handleBack = () => {
  router.visit(`/${props.company.slug}/customers`)
}
</script>

<template>
  <Head :title="`${customer.name} - ${company.name}`" />
  <PageShell
    :title="customer.name"
    :icon="User"
    :breadcrumbs="breadcrumbs"
    :back-button="{
      label: 'Back to Customers',
      onClick: handleBack,
      icon: ArrowLeft,
    }"
  >
    <template #description>
      <div class="flex items-center gap-2">
        <span class="font-mono text-sm text-slate-400">{{ customer.customer_number }}</span>
        <Badge :variant="customer.is_active ? 'default' : 'secondary'">
          {{ customer.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </div>
    </template>

    <template #actions>
      <Button variant="outline" size="sm" @click="handleEdit">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button variant="destructive" size="sm" @click="deleteDialogOpen = true">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </Button>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <Card>
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Outstanding Balance</CardTitle>
            <Receipt class="h-4 w-4 text-slate-400" />
          </div>
          <CardDescription>Open invoices for this customer</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.open_balance) }}</div>
          <p class="text-xs text-slate-500 mt-1">{{ summary.invoice_count }} invoice{{ summary.invoice_count === 1 ? '' : 's' }} open</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Available Credit</CardTitle>
            <PiggyBank class="h-4 w-4 text-slate-400" />
          </div>
          <CardDescription>Remaining credit notes not yet applied</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.available_credit) }}</div>
          <p class="text-xs text-slate-500 mt-1">{{ summary.credit_note_count }} credit note{{ summary.credit_note_count === 1 ? '' : 's' }}</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Payments Received</CardTitle>
            <Wallet class="h-4 w-4 text-slate-400" />
          </div>
          <CardDescription>Lifetime payments recorded</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.payments_received) }}</div>
        </CardContent>
      </Card>
    </div>

    <div class="space-y-6">
      <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Outstanding</CardTitle>
              <Receipt class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Open invoice balance</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.open_balance) }}</div>
            <p class="text-xs text-slate-500 mt-1">{{ summary.invoice_count }} open invoice{{ summary.invoice_count === 1 ? '' : 's' }}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Overdue</CardTitle>
              <Hash class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Past due portion</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.overdue_balance) }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Available Credit</CardTitle>
              <PiggyBank class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Unapplied credit notes</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.available_credit) }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Paid YTD</CardTitle>
              <Wallet class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Payments this year</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.paid_ytd) }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Invoiced YTD</CardTitle>
              <ScrollText class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Issued this year</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ formatMoney(summary.invoiced_ytd) }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm text-slate-400">Avg Days to Pay</CardTitle>
              <Calendar class="h-4 w-4 text-slate-400" />
            </div>
            <CardDescription>Paid invoices only</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-semibold text-slate-50">{{ summary.avg_days_to_pay ?? '—' }}</div>
          </CardContent>
        </Card>
      </div>

      <Tabs v-model="activeTab" class="space-y-4">
        <TabsList>
          <TabsTrigger value="invoices">Invoices</TabsTrigger>
          <TabsTrigger value="payments">Payments</TabsTrigger>
          <TabsTrigger value="details">Details</TabsTrigger>
        </TabsList>

        <TabsContent value="invoices">
          <Card>
            <CardHeader>
              <CardTitle>Invoices</CardTitle>
              <CardDescription>Newest first</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-slate-500">
                  <tr>
                    <th class="text-left py-2 pr-4">Invoice</th>
                    <th class="text-left py-2 pr-4">Issue</th>
                    <th class="text-left py-2 pr-4">Due</th>
                    <th class="text-left py-2 pr-4">Total</th>
                    <th class="text-left py-2 pr-4">Paid</th>
                    <th class="text-left py-2 pr-4">Balance</th>
                    <th class="text-left py-2 pr-4">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inv in invoices" :key="inv.id" class="border-t border-slate-800/40">
                    <td class="py-2 pr-4 font-medium text-slate-100">{{ inv.invoice_number }}</td>
                    <td class="py-2 pr-4 text-slate-300">{{ formatDate(inv.invoice_date) }}</td>
                    <td class="py-2 pr-4 text-slate-300">{{ formatDate(inv.due_date) }}</td>
                    <td class="py-2 pr-4 text-slate-100">{{ formatMoney(inv.total_amount) }}</td>
                    <td class="py-2 pr-4 text-slate-100">{{ formatMoney(inv.paid_amount) }}</td>
                    <td class="py-2 pr-4 text-slate-100">{{ formatMoney(inv.balance) }}</td>
                    <td class="py-2 pr-4">
                      <Badge :variant="inv.status === 'paid' ? 'default' : inv.status === 'overdue' ? 'destructive' : 'secondary'">
                        {{ inv.status }}
                      </Badge>
                    </td>
                  </tr>
                  <tr v-if="invoices.length === 0">
                    <td colspan="7" class="py-3 text-center text-slate-500">No invoices yet.</td>
                  </tr>
                </tbody>
              </table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="payments">
          <Card>
            <CardHeader>
              <CardTitle>Payments</CardTitle>
              <CardDescription>Newest first</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-slate-500">
                  <tr>
                    <th class="text-left py-2 pr-4">Date</th>
                    <th class="text-left py-2 pr-4">Payment #</th>
                    <th class="text-left py-2 pr-4">Invoice</th>
                    <th class="text-left py-2 pr-4">Amount</th>
                    <th class="text-left py-2 pr-4">Method</th>
                    <th class="text-left py-2 pr-4">Reference</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="pay in payments" :key="pay.id" class="border-t border-slate-800/40">
                    <td class="py-2 pr-4 text-slate-300">{{ formatDate(pay.payment_date) }}</td>
                    <td class="py-2 pr-4 font-medium text-slate-100">{{ pay.payment_number }}</td>
                    <td class="py-2 pr-4 text-slate-200">
                      {{ pay.payment_allocations?.[0]?.invoice?.invoice_number || '—' }}
                    </td>
                    <td class="py-2 pr-4 text-slate-100">{{ formatMoney(pay.amount) }}</td>
                    <td class="py-2 pr-4 capitalize text-slate-200">{{ pay.payment_method || '—' }}</td>
                    <td class="py-2 pr-4 text-slate-300">{{ pay.reference_number || '—' }}</td>
                  </tr>
                  <tr v-if="payments.length === 0">
                    <td colspan="6" class="py-3 text-center text-slate-500">No payments yet.</td>
                  </tr>
                </tbody>
              </table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="details">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card>
              <CardHeader>
                <CardTitle>Contact Information</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex items-center gap-3">
                  <Mail class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Email</p>
                    <p class="text-slate-200">{{ customer.email || '—' }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <Phone class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Phone</p>
                    <p class="text-slate-200">{{ customer.phone || '—' }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <Hash class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Tax ID</p>
                    <p class="text-slate-200">{{ customer.tax_id || '—' }}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Financial Settings</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex items-center gap-3">
                  <CreditCard class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Currency</p>
                    <p class="text-slate-200">{{ customer.base_currency }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <Calendar class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Payment Terms</p>
                    <p class="text-slate-200">{{ customer.payment_terms }} days</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <CreditCard class="h-4 w-4 text-slate-400" />
                  <div>
                    <p class="text-xs text-slate-500">Credit Limit</p>
                    <p class="text-slate-200">{{ formatCurrency(customer.credit_limit, customer.base_currency) }}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Billing Address</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="flex items-start gap-3">
                  <MapPin class="h-4 w-4 text-slate-400 mt-1" />
                  <p class="text-slate-200">{{ formatAddress(customer.billing_address) }}</p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Shipping Address</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="flex items-start gap-3">
                  <MapPin class="h-4 w-4 text-slate-400 mt-1" />
                  <p class="text-slate-200">{{ formatAddress(customer.shipping_address) }}</p>
                </div>
              </CardContent>
            </Card>

            <Card v-if="customer.notes" class="lg:col-span-2">
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-slate-300 whitespace-pre-wrap">{{ customer.notes }}</p>
              </CardContent>
            </Card>

            <Card class="lg:col-span-2">
              <CardHeader>
                <CardTitle>Details</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <p class="text-slate-500">Customer Number</p>
                    <p class="text-slate-200 font-mono">{{ customer.customer_number }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500">ID</p>
                    <p class="text-slate-200 font-mono text-xs">{{ customer.id }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500">Created</p>
                    <p class="text-slate-200">{{ formatDate(customer.created_at) }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500">Last Updated</p>
                    <p class="text-slate-200">{{ formatDate(customer.updated_at) }}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-slate-100">Delete Customer</DialogTitle>
          <DialogDescription class="text-slate-400">
            Are you sure you want to delete <strong>{{ customer.name }}</strong>? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteDialogOpen = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="handleDelete">
            <Trash2 class="mr-2 h-4 w-4" />
            Delete Customer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
