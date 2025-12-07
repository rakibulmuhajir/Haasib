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
  MoreHorizontal,
  DollarSign,
  CreditCard,
  Building,
  Smartphone,
  FileText,
  Calendar,
} from 'lucide-vue-next'

interface PaymentAllocation {
  id: string
  invoice_id: string
  amount: number
}

interface Customer {
  id: string
  name: string
  email?: string
}

interface Payment {
  id: string
  payment_number: string
  customer: Customer
  amount: number
  currency: string
  payment_method: string
  reference_number?: string
  payment_date: string
  notes?: string
  payment_allocations: PaymentAllocation[]
  created_at: string
}

interface CompanyRef {
  id: string
  name: string
  slug: string
}

const props = defineProps<{
  company: CompanyRef
  payment: Payment
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Payments', href: `/${props.company.slug}/payments` },
  { title: props.payment.payment_number },
])

const getPaymentMethodIcon = (method: string) => {
  switch (method) {
    case 'cash':
      return DollarSign
    case 'bank_transfer':
      return Building
    case 'card':
      return CreditCard
    case 'cheque':
      return FileText
    default:
      return DollarSign
  }
}

const getPaymentMethodLabel = (method: string) => {
  switch (method) {
    case 'cash':
      return 'Cash'
    case 'bank_transfer':
      return 'Bank Transfer'
    case 'card':
      return 'Card'
    case 'cheque':
      return 'Cheque'
    default:
      return 'Other'
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
</script>

<template>
  <Head :title="`Payment ${payment.payment_number}`" />

  <PageShell
    :title="`Payment ${payment.payment_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payments`)">
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
          <DropdownMenuItem @click="router.get(`/${company.slug}/payments/${payment.id}/edit`)">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Payment Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Payment Header -->
        <Card>
          <CardContent class="pt-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h1 class="text-2xl font-bold">Payment {{ payment.payment_number }}</h1>
                <p class="text-muted-foreground">Date: {{ formatDate(payment.payment_date) }}</p>
              </div>
              <div class="text-right">
                <Badge variant="secondary" class="mb-2">
                  {{ getPaymentMethodLabel(payment.payment_method) }}
                </Badge>
                <div class="flex items-center justify-end gap-2">
                  <component :is="getPaymentMethodIcon(payment.payment_method)" class="h-4 w-4" />
                  <span class="font-bold text-lg">{{ formatCurrency(payment.amount, payment.currency) }}</span>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
              <div>
                <h3 class="font-semibold mb-2">Customer:</h3>
                <div>
                  <p class="font-medium">{{ payment.customer.name }}</p>
                  <p v-if="payment.customer.email">{{ payment.customer.email }}</p>
                </div>
              </div>
              <div class="text-right">
                <p v-if="payment.reference_number" class="mb-1">
                  <span class="font-medium">Reference:</span> {{ payment.reference_number }}
                </p>
                <p class="mb-1">
                  <span class="font-medium">Payment Method:</span> {{ getPaymentMethodLabel(payment.payment_method) }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Allocations -->
        <Card v-if="payment.payment_allocations.length > 0">
          <CardHeader>
            <CardTitle>Allocations</CardTitle>
            <CardDescription>How this payment was applied</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div v-for="allocation in payment.payment_allocations" :key="allocation.id" class="flex justify-between items-center p-3 border rounded-lg">
                <div>
                  <p class="font-medium">Invoice #{{ allocation.invoice_id }}</p>
                  <p class="text-sm text-muted-foreground">Applied to invoice</p>
                </div>
                <div class="text-right">
                  <p class="font-medium">{{ formatCurrency(allocation.amount, payment.currency) }}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card v-if="payment.notes">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm">{{ payment.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Payment Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Payment Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between">
              <span>Payment Amount:</span>
              <span class="font-bold">{{ formatCurrency(payment.amount, payment.currency) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Payment Method:</span>
              <span>{{ getPaymentMethodLabel(payment.payment_method) }}</span>
            </div>
            <div v-if="payment.reference_number" class="flex justify-between text-sm">
              <span>Reference:</span>
              <span>{{ payment.reference_number }}</span>
            </div>
            <Separator />
            <div v-if="payment.payment_allocations.length > 0" class="space-y-2">
              <p class="font-medium text-sm">Total Allocated:</p>
              <div class="text-right">
                {{ formatCurrency(payment.payment_allocations.reduce((sum, a) => sum + a.amount, 0), payment.currency) }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Timeline -->
        <Card>
          <CardHeader>
            <CardTitle>Timeline</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between text-sm">
              <span>Created:</span>
              <span>{{ formatDate(payment.created_at) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Payment Date:</span>
              <span>{{ formatDate(payment.payment_date) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <Button class="w-full" variant="outline" @click="router.get(`/${company.slug}/payments/${payment.id}/edit`)">
              <Edit class="mr-2 h-4 w-4" />
              Edit Payment
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>