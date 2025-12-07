<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  ArrowLeft,
  Edit,
  Send,
  Download,
  FileText,
  MoreHorizontal,
  Trash2,
  Copy,
  DollarSign,
} from 'lucide-vue-next'

interface LineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  tax_rate?: number
  discount_amount?: number
  total: number
}

interface Customer {
  id: string
  name: string
  email?: string
  phone?: string
  billing_address?: Record<string, any>
}

interface Invoice {
  id: string
  invoice_number: string
  customer: Customer
  status: string
  currency: string
  base_currency: string
  exchange_rate: number
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  paid_amount: number
  balance: number
  invoice_date: string
  due_date: string
  description?: string
  reference?: string
  notes?: string
  line_items: LineItem[]
  sent_at?: string
  viewed_at?: string
  paid_at?: string
  created_at: string
}

interface CompanyRef {
  id: string
  name: string
  slug: string
}

const props = defineProps<{
  company: CompanyRef
  invoice: Invoice
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
  { title: props.invoice.invoice_number },
])

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case 'draft':
      return 'secondary'
    case 'sent':
      return 'default'
    case 'paid':
      return 'success'
    case 'overdue':
      return 'destructive'
    case 'cancelled':
      return 'outline'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const isOverdue = computed(() => {
  return props.invoice.status !== 'paid' &&
         props.invoice.status !== 'cancelled' &&
         new Date(props.invoice.due_date) < new Date()
})

const sendInvoice = () => {
  router.post(`/${props.company.slug}/invoices/${props.invoice.id}/send`)
}

const duplicateInvoice = () => {
  router.post(`/${props.company.slug}/invoices/${props.invoice.id}/duplicate`)
}

const voidInvoice = () => {
  if (confirm('Are you sure you want to void this invoice?')) {
    router.post(`/${props.company.slug}/invoices/${props.invoice.id}/void`)
  }
}
</script>

<template>
  <Head :title="`Invoice ${invoice.invoice_number}`" />

  <PageShell
    :title="`Invoice ${invoice.invoice_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/invoices`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline">
            <MoreHorizontal class="mr-2 h-4 w-4" />
            More
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${invoice.id}/edit`)">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
          <DropdownMenuItem @click="duplicateInvoice">
            <Copy class="mr-2 h-4 w-4" />
            Duplicate
          </DropdownMenuItem>
          <DropdownMenuItem @click="voidInvoice">
            <Trash2 class="mr-2 h-4 w-4" />
            Void
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Button @click="sendInvoice" :disabled="invoice.status === 'paid' || invoice.status === 'cancelled'">
        <Send class="mr-2 h-4 w-4" />
        Send Invoice
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Invoice Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Invoice Header -->
        <Card>
          <CardContent class="pt-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h1 class="text-2xl font-bold">Invoice {{ invoice.invoice_number }}</h1>
                <p class="text-muted-foreground">Date: {{ formatDate(invoice.invoice_date) }}</p>
                <p class="text-muted-foreground">Due: {{ formatDate(invoice.due_date) }}</p>
                <p v-if="isOverdue" class="text-destructive font-medium">Overdue</p>
              </div>
              <Badge :variant="getStatusBadgeVariant(invoice.status)" class="text-sm">
                {{ invoice.status }}
              </Badge>
            </div>

            <div class="grid grid-cols-2 gap-6">
              <div>
                <h3 class="font-semibold mb-2">Bill To:</h3>
                <div>
                  <p class="font-medium">{{ invoice.customer.name }}</p>
                  <p v-if="invoice.customer.email">{{ invoice.customer.email }}</p>
                  <p v-if="invoice.customer.phone">{{ invoice.customer.phone }}</p>
                </div>
              </div>
              <div class="text-right">
                <p v-if="invoice.reference" class="mb-1">
                  <span class="font-medium">Reference:</span> {{ invoice.reference }}
                </p>
                <p v-if="invoice.payment_terms" class="mb-1">
                  <span class="font-medium">Terms:</span> {{ invoice.payment_terms }} days
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Line Items -->
        <Card>
          <CardHeader>
            <CardTitle>Items</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div class="grid grid-cols-12 gap-2 text-sm font-medium text-muted-foreground pb-2 border-b">
                <div class="col-span-6">Description</div>
                <div class="col-span-2 text-right">Quantity</div>
                <div class="col-span-2 text-right">Price</div>
                <div class="col-span-2 text-right">Total</div>
              </div>

              <div v-for="item in invoice.line_items" :key="item.id" class="grid grid-cols-12 gap-2 py-2">
                <div class="col-span-6">{{ item.description }}</div>
                <div class="col-span-2 text-right">{{ item.quantity }}</div>
                <div class="col-span-2 text-right">{{ formatCurrency(item.unit_price, invoice.currency) }}</div>
                <div class="col-span-2 text-right">{{ formatCurrency(item.total, invoice.currency) }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card v-if="invoice.description || invoice.notes">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-if="invoice.description">
              <h4 class="font-medium">Description</h4>
              <p class="text-sm">{{ invoice.description }}</p>
            </div>
            <div v-if="invoice.notes">
              <h4 class="font-medium">Customer Notes</h4>
              <p class="text-sm">{{ invoice.notes }}</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Amount Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Amount Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between text-sm">
              <span>Subtotal:</span>
              <span>{{ formatCurrency(invoice.subtotal, invoice.currency) }}</span>
            </div>
            <div v-if="invoice.discount_amount > 0" class="flex justify-between text-sm">
              <span>Discount:</span>
              <span>-{{ formatCurrency(invoice.discount_amount, invoice.currency) }}</span>
            </div>
            <div v-if="invoice.tax_amount > 0" class="flex justify-between text-sm">
              <span>Tax:</span>
              <span>{{ formatCurrency(invoice.tax_amount, invoice.currency) }}</span>
            </div>
            <Separator />
            <div class="flex justify-between font-bold">
              <span>Total:</span>
              <span>{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Paid:</span>
              <span>{{ formatCurrency(invoice.paid_amount, invoice.currency) }}</span>
            </div>
            <Separator />
            <div class="flex justify-between font-bold text-lg">
              <span>Balance Due:</span>
              <span>{{ formatCurrency(invoice.balance, invoice.currency) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Status Timeline -->
        <Card>
          <CardHeader>
            <CardTitle>Status Timeline</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between text-sm">
              <span>Created:</span>
              <span>{{ formatDate(invoice.created_at) }}</span>
            </div>
            <div v-if="invoice.sent_at" class="flex justify-between text-sm">
              <span>Sent:</span>
              <span>{{ formatDate(invoice.sent_at) }}</span>
            </div>
            <div v-if="invoice.viewed_at" class="flex justify-between text-sm">
              <span>Viewed:</span>
              <span>{{ formatDate(invoice.viewed_at) }}</span>
            </div>
            <div v-if="invoice.paid_at" class="flex justify-between text-sm">
              <span>Paid:</span>
              <span>{{ formatDate(invoice.paid_at) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <Button class="w-full" variant="outline" @click="sendInvoice" :disabled="invoice.status === 'paid' || invoice.status === 'cancelled'">
              <Send class="mr-2 h-4 w-4" />
              Send to Customer
            </Button>
            <Button class="w-full" variant="outline">
              <Download class="mr-2 h-4 w-4" />
              Download PDF
            </Button>
            <Button class="w-full" variant="outline" @click="router.get(`/${company.slug}/invoices/${invoice.id}/edit`)" :disabled="invoice.status === 'paid'">
              <Edit class="mr-2 h-4 w-4" />
              Edit Invoice
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>