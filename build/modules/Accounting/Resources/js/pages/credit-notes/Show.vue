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
  Receipt,
  Calendar,
  FileText,
  Send,
} from 'lucide-vue-next'

interface Customer {
  id: string
  name: string
  email?: string
}

interface Invoice {
  id: string
  invoice_number: string
}

interface CreditNote {
  id: string
  credit_note_number: string
  customer: Customer
  invoice?: Invoice
  amount: number
  base_currency: string
  reason: string
  status: string
  credit_date: string
  notes?: string
  terms?: string
  sent_at?: string
  posted_at?: string
  voided_at?: string
  created_at: string
}

interface CompanyRef {
  id: string
  name: string
  slug: string
}

const props = defineProps<{
  company: CompanyRef
  credit_note: CreditNote
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
  { title: props.credit_note.credit_note_number },
])

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case 'draft':
      return 'secondary'
    case 'issued':
      return 'default'
    case 'partial':
      return 'warning'
    case 'applied':
      return 'success'
    case 'void':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
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

const isEditable = computed(() => {
  return ['draft', 'issued'].includes(props.credit_note.status)
})
</script>

<template>
  <Head :title="`Credit Note ${credit_note.credit_note_number}`" />

  <PageShell
    :title="`Credit Note ${credit_note.credit_note_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/credit-notes`)">
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
          <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}/edit`)" :disabled="!isEditable">
            <Edit class="mr-2 h-4 w-4" />
            Edit
          </DropdownMenuItem>
          <DropdownMenuItem @click="router.post(`/${company.slug}/credit-notes/${credit_note.id}/send`)" v-if="credit_note.status === 'draft'">
            <Send class="mr-2 h-4 w-4" />
            Send
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Credit Note Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Credit Note Header -->
        <Card>
          <CardContent class="pt-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h1 class="text-2xl font-bold">Credit Note {{ credit_note.credit_note_number }}</h1>
                <p class="text-muted-foreground">Date: {{ formatDate(credit_note.credit_date) }}</p>
              </div>
              <div class="text-right">
                <Badge :variant="getStatusBadgeVariant(credit_note.status)" class="mb-2">
                  {{ credit_note.status }}
                </Badge>
                <div class="text-2xl font-bold text-green-600">
                  {{ formatCurrency(credit_note.amount, credit_note.base_currency) }}
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
              <div>
                <h3 class="font-semibold mb-2">Customer:</h3>
                <div>
                  <p class="font-medium">{{ credit_note.customer.name }}</p>
                  <p v-if="credit_note.customer.email">{{ credit_note.customer.email }}</p>
                </div>
              </div>
              <div class="text-right">
                <p v-if="credit_note.invoice" class="mb-1">
                  <span class="font-medium">Applied to:</span> {{ credit_note.invoice.invoice_number }}
                </p>
                <p class="mb-1">
                  <span class="font-medium">Currency:</span> {{ credit_note.base_currency }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Reason -->
        <Card>
          <CardHeader>
            <CardTitle>Reason for Credit</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="p-4 bg-muted/50 rounded-lg">
              <p class="font-medium">{{ credit_note.reason }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Terms -->
        <Card v-if="credit_note.terms">
          <CardHeader>
            <CardTitle>Terms and Conditions</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="prose prose-sm max-w-none">
              <p>{{ credit_note.terms }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card v-if="credit_note.notes">
          <CardHeader>
            <CardTitle>Internal Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm">{{ credit_note.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Credit Note Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Credit Note Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between">
              <span>Credit Amount:</span>
              <span class="font-bold text-green-600">{{ formatCurrency(credit_note.amount, credit_note.base_currency) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span>Status:</span>
              <span>{{ credit_note.status }}</span>
            </div>
            <div v-if="credit_note.invoice" class="flex justify-between text-sm">
              <span>Applied to:</span>
              <span>{{ credit_note.invoice.invoice_number }}</span>
            </div>
            <Separator />
            <div class="flex justify-between text-sm text-muted-foreground">
              <span>Credit Date:</span>
              <span>{{ formatDate(credit_note.credit_date) }}</span>
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
              <span>{{ formatDate(credit_note.created_at) }}</span>
            </div>
            <div v-if="credit_note.sent_at" class="flex justify-between text-sm">
              <span>Sent:</span>
              <span>{{ formatDate(credit_note.sent_at) }}</span>
            </div>
            <div v-if="credit_note.posted_at" class="flex justify-between text-sm">
              <span>Posted:</span>
              <span>{{ formatDate(credit_note.posted_at) }}</span>
            </div>
            <div v-if="credit_note.voided_at" class="flex justify-between text-sm">
              <span>Voided:</span>
              <span>{{ formatDate(credit_note.voided_at) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <Button class="w-full" variant="outline" @click="router.get(`/${company.slug}/credit-notes/${credit_note.id}/edit`)" :disabled="!isEditable">
              <Edit class="mr-2 h-4 w-4" />
              Edit Credit Note
            </Button>
            <Button v-if="credit_note.status === 'draft'" class="w-full" variant="outline" @click="router.post(`/${company.slug}/credit-notes/${credit_note.id}/send`)">
              <Send class="mr-2 h-4 w-4" />
              Send to Customer
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>