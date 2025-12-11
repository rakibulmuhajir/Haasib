<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { FileText, Pencil, Trash2, Building } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  logo_url?: string
}

interface LineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
  discount_rate: number
  line_total: number
  tax_amount: number
  total: number
}

interface VendorRef {
  id: string
  name: string
  logo_url?: string
}

interface BillRef {
  id: string
  bill_number: string
  vendor_id: string
  vendor: VendorRef | null
  bill_date: string
  due_date: string
  status: string
  currency: string
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  paid_amount: number
  balance: number
  notes: string | null
  internal_notes: string | null
  line_items: LineItem[]
}

const props = defineProps<{
  company: CompanyRef
  bill: BillRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bills', href: `/${props.company.slug}/bills` },
  { title: props.bill.bill_number, href: `/${props.company.slug}/bills/${props.bill.id}` },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const formatNumber = (val: number, decimals: number = 2) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(val)

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const statusVariant = (s: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
  if (s === 'draft') return 'secondary'
  if (s === 'received') return 'default'
  if (s === 'partial') return 'outline'
  if (s === 'paid') return 'default'
  if (s === 'overdue') return 'destructive'
  return 'secondary'
}

const handleDelete = () => {
  if (!confirm('Are you sure you want to delete this bill?')) return
  router.delete(`/${props.company.slug}/bills/${props.bill.id}`)
}

const navigateToVendor = () => {
  if (props.bill.vendor_id) {
    router.get(`/${props.company.slug}/vendors/${props.bill.vendor_id}`)
  }
}
</script>

<template>
  <Head :title="`Bill ${bill.bill_number}`" />
  <PageShell
    :title="`Bill ${bill.bill_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button variant="outline" @click="router.get(`/${company.slug}/bills/${bill.id}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button variant="destructive" @click="handleDelete">
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Bill Details Card -->
        <Card>
          <CardHeader>
            <!-- Vendor Logo Section -->
            <div class="mb-4 pb-4 border-b">
              <div class="flex items-center gap-4">
                <div v-if="bill.vendor?.logo_url" class="flex-shrink-0">
                  <img
                    :src="bill.vendor.logo_url"
                    :alt="`${bill.vendor.name} logo`"
                    class="h-16 w-auto object-contain"
                  />
                </div>
                <div v-else class="flex-shrink-0">
                  <div class="h-16 w-16 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Building class="h-8 w-8 text-primary" />
                  </div>
                </div>
                <div>
                  <h2 class="text-xl font-semibold">{{ bill.vendor?.name ?? 'Unknown Vendor' }}</h2>
                  <p class="text-sm text-muted-foreground">Vendor</p>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <CardTitle>{{ bill.bill_number }}</CardTitle>
                <CardDescription>{{ formatDate(bill.bill_date) }}</CardDescription>
              </div>
              <Badge :variant="statusVariant(bill.status)" class="text-base px-4 py-1">
                {{ bill.status }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Due Date -->
            <div>
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Due Date</h3>
              <p class="text-lg font-semibold">{{ formatDate(bill.due_date) }}</p>
            </div>

            <Separator />

            <!-- Line Items -->
            <div>
              <h3 class="text-lg font-semibold mb-4">Line Items</h3>
              <div class="space-y-3">
                <div
                  v-for="item in bill.line_items"
                  :key="item.id"
                  class="p-4 border rounded-lg"
                >
                  <div class="flex justify-between items-start mb-2">
                    <h4 class="font-medium">{{ item.description }}</h4>
                    <span class="font-semibold">{{ formatMoney(item.total, bill.currency) }}</span>
                  </div>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-muted-foreground">
                    <div>
                      <span class="font-medium">Qty:</span> {{ formatNumber(item.quantity) }}
                    </div>
                    <div>
                      <span class="font-medium">Price:</span> {{ formatMoney(item.unit_price, bill.currency) }}
                    </div>
                    <div v-if="item.tax_rate > 0">
                      <span class="font-medium">Tax:</span> {{ formatNumber(item.tax_rate) }}%
                    </div>
                    <div v-if="item.discount_rate > 0">
                      <span class="font-medium">Discount:</span> {{ formatNumber(item.discount_rate) }}%
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <Separator />

            <!-- Totals -->
            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Subtotal</span>
                <span class="font-medium">{{ formatMoney(bill.subtotal, bill.currency) }}</span>
              </div>
              <div v-if="bill.tax_amount > 0" class="flex justify-between text-sm">
                <span class="text-muted-foreground">Tax</span>
                <span class="font-medium">{{ formatMoney(bill.tax_amount, bill.currency) }}</span>
              </div>
              <div v-if="bill.discount_amount > 0" class="flex justify-between text-sm">
                <span class="text-muted-foreground">Discount</span>
                <span class="font-medium text-destructive">-{{ formatMoney(bill.discount_amount, bill.currency) }}</span>
              </div>
              <Separator />
              <div class="flex justify-between text-lg font-bold">
                <span>Total</span>
                <span>{{ formatMoney(bill.total_amount, bill.currency) }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="bill.notes" class="pt-4 border-t">
              <h4 class="text-sm font-medium text-muted-foreground mb-2">Notes</h4>
              <p class="text-sm">{{ bill.notes }}</p>
            </div>
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
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Bill Amount</span>
                <span class="font-medium">{{ formatMoney(bill.total_amount, bill.currency) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Amount Paid</span>
                <span class="font-medium">{{ formatMoney(bill.paid_amount, bill.currency) }}</span>
              </div>
              <Separator />
              <div class="flex justify-between text-base font-semibold">
                <span>Balance Due</span>
                <span :class="bill.balance > 0 ? 'text-destructive' : 'text-green-600'">
                  {{ formatMoney(bill.balance, bill.currency) }}
                </span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2">
              <Button
                v-if="bill.status === 'draft'"
                class="w-full"
                @click="router.post(`/${company.slug}/bills/${bill.id}/receive`)"
              >
                Mark as Received
              </Button>

              <Button
                v-if="bill.balance > 0 && bill.status === 'received'"
                class="w-full"
                @click="router.get(`/${company.slug}/bill-payments/create?bill_id=${bill.id}`)"
              >
                Record Payment
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Bill Details -->
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-muted-foreground">Bill Number</span>
              <span class="font-medium">{{ bill.bill_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Bill Date</span>
              <span class="font-medium">{{ formatDate(bill.bill_date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Due Date</span>
              <span class="font-medium">{{ formatDate(bill.due_date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Currency</span>
              <span class="font-medium">{{ bill.currency }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Status</span>
              <Badge :variant="statusVariant(bill.status)">{{ bill.status }}</Badge>
            </div>
          </CardContent>
        </Card>

        <!-- Internal Notes -->
        <Card v-if="bill.internal_notes">
          <CardHeader>
            <CardTitle class="text-sm">Internal Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">{{ bill.internal_notes }}</p>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
