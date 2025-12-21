<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { FileText, Pencil, Trash2, Building, Package, PackageCheck, Ban } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  logo_url?: string
}

interface LineItem {
  id: string
  item_id: string | null
  warehouse_id: string | null
  description: string
  quantity: number
  quantity_received: number
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
  received_at: string | null
  goods_received_at: string | null
  line_items: LineItem[]
}

const props = defineProps<{
  company: CompanyRef
  bill: BillRef
  inventoryEnabled?: boolean
  journalTransactionId?: string | null
}>()

const { t } = useLexicon()

// State
const showVoidDialog = ref(false)
const voidReason = ref('')
const isSubmittingVoid = ref(false)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('bills'), href: `/${props.company.slug}/bills` },
  { title: props.bill.bill_number, href: `/${props.company.slug}/bills/${props.bill.id}` },
])

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
  if (!confirm(t('confirmDeleteBill'))) return
  router.delete(`/${props.company.slug}/bills/${props.bill.id}`)
}

const openVoidDialog = () => {
  voidReason.value = ''
  showVoidDialog.value = true
}

const handleVoid = () => {
  if (!voidReason.value.trim()) return

  isSubmittingVoid.value = true
  router.post(`/${props.company.slug}/bills/${props.bill.id}/void`, {
    void_reason: voidReason.value
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSubmittingVoid.value = false
      showVoidDialog.value = false
    }
  })
}

// Determine which actions to show based on bill status
const canEdit = computed(() => !['paid', 'void', 'cancelled'].includes(props.bill.status))
const canVoid = computed(() => ['received', 'partial', 'paid'].includes(props.bill.status))
const canDelete = computed(() => props.bill.status === 'draft')

// Check if any line items have inventory items
const hasInventoryItems = computed(() => {
  return props.bill.line_items.some(item => item.item_id !== null)
})

// Check if goods can be received (has inventory items, not voided, not fully received)
const canReceiveGoods = computed(() => {
  if (!props.inventoryEnabled) return false
  if (!hasInventoryItems.value) return false
  if (['void', 'cancelled', 'draft'].includes(props.bill.status)) return false
  // Check if any line still has quantity to receive
  return props.bill.line_items.some(item =>
    item.item_id && item.quantity_received < item.quantity
  )
})

// Check if goods are fully received
const goodsFullyReceived = computed(() => {
  if (!hasInventoryItems.value) return false
  return props.bill.goods_received_at !== null
})

const handleReceiveGoods = () => {
  if (!confirm('Confirm that all goods have been physically received?')) return
  router.post(`/${props.company.slug}/bills/${props.bill.id}/receive-goods`)
}

const navigateToVendor = () => {
  if (props.bill.vendor_id) {
    router.get(`/${props.company.slug}/vendors/${props.bill.vendor_id}`)
  }
}
</script>

<template>
  <Head :title="`${t('bills')} ${bill.bill_number}`" />
  <PageShell
    :title="`${t('bills')} ${bill.bill_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button
          v-if="journalTransactionId"
          variant="outline"
          @click="router.get(`/${company.slug}/journals/${journalTransactionId}`)"
        >
          <FileText class="mr-2 h-4 w-4" />
          View Journal
        </Button>
        <Button v-if="canEdit" variant="outline" @click="router.get(`/${company.slug}/bills/${bill.id}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          {{ t('edit') }}
        </Button>
        <Button v-if="canVoid" variant="outline" @click="openVoidDialog">
          <Ban class="mr-2 h-4 w-4" />
          {{ t('void') }}
        </Button>
        <Button v-if="canDelete" variant="destructive" @click="handleDelete">
          <Trash2 class="mr-2 h-4 w-4" />
          {{ t('delete') }}
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
                  <h2 class="text-xl font-semibold">{{ bill.vendor?.name ?? t('vendor') }}</h2>
                  <p class="text-sm text-muted-foreground">{{ t('vendor') }}</p>
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
              <h3 class="text-sm font-medium text-muted-foreground mb-2">{{ t('dueDate') }}</h3>
              <p class="text-lg font-semibold">{{ formatDate(bill.due_date) }}</p>
            </div>

            <Separator />

            <!-- Line Items -->
            <div>
              <h3 class="text-lg font-semibold mb-4">{{ t('lineItems') }}</h3>
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
                      <span class="font-medium">{{ t('quantity') }}:</span> {{ formatNumber(item.quantity) }}
                    </div>
                    <div>
                      <span class="font-medium">{{ t('price') }}:</span> {{ formatMoney(item.unit_price, bill.currency) }}
                    </div>
                    <div v-if="item.tax_rate > 0">
                      <span class="font-medium">{{ t('tax') }}:</span> {{ formatNumber(item.tax_rate) }}%
                    </div>
                    <div v-if="item.discount_rate > 0">
                      <span class="font-medium">{{ t('discount') }}:</span> {{ formatNumber(item.discount_rate) }}%
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <Separator />

            <!-- Totals -->
            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('subtotal') }}</span>
                <span class="font-medium">{{ formatMoney(bill.subtotal, bill.currency) }}</span>
              </div>
              <div v-if="bill.tax_amount > 0" class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('tax') }}</span>
                <span class="font-medium">{{ formatMoney(bill.tax_amount, bill.currency) }}</span>
              </div>
              <div v-if="bill.discount_amount > 0" class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('discount') }}</span>
                <span class="font-medium text-destructive">-{{ formatMoney(bill.discount_amount, bill.currency) }}</span>
              </div>
              <Separator />
              <div class="flex justify-between text-lg font-bold">
                <span>{{ t('total') }}</span>
                <span>{{ formatMoney(bill.total_amount, bill.currency) }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="bill.notes" class="pt-4 border-t">
              <h4 class="text-sm font-medium text-muted-foreground mb-2">{{ t('notes') }}</h4>
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
            <CardTitle>{{ t('paymentSummary') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('billAmount') }}</span>
                <span class="font-medium">{{ formatMoney(bill.total_amount, bill.currency) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('amountPaid') }}</span>
                <span class="font-medium">{{ formatMoney(bill.paid_amount, bill.currency) }}</span>
              </div>
              <Separator />
              <div class="flex justify-between text-base font-semibold">
                <span>{{ t('balanceDue') }}</span>
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
                {{ t('markAsReceived') }}
              </Button>

              <Button
                v-if="bill.balance > 0 && bill.status !== 'draft' && bill.status !== 'void'"
                class="w-full"
                @click="router.get(`/${company.slug}/bill-payments/create?bill_id=${bill.id}`)"
              >
                {{ t('recordPayment') }}
              </Button>

              <!-- Goods Receipt Button -->
              <Button
                v-if="canReceiveGoods"
                class="w-full"
                variant="outline"
                @click="handleReceiveGoods"
              >
                <Package class="mr-2 h-4 w-4" />
                Confirm Goods Received
              </Button>

              <!-- Goods Received Status -->
              <div
                v-if="goodsFullyReceived"
                class="flex items-center justify-center gap-2 p-2 rounded-md bg-green-50 text-green-700 text-sm"
              >
                <PackageCheck class="h-4 w-4" />
                <span>Goods Received</span>
              </div>

              <!-- No Inventory Items Warning -->
              <div
                v-if="inventoryEnabled && !hasInventoryItems && !['void', 'cancelled', 'draft'].includes(bill.status)"
                class="flex items-start gap-2 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-xs"
              >
                <Package class="h-4 w-4 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium mb-1">No inventory items</p>
                  <p class="text-amber-700">
                    This bill has no linked inventory items. To track goods receipt, edit the bill and select inventory items for each line.
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Bill Details -->
        <Card>
          <CardHeader>
            <CardTitle>{{ t('details') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('billNumber') }}</span>
              <span class="font-medium">{{ bill.bill_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('billDate') }}</span>
              <span class="font-medium">{{ formatDate(bill.bill_date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('dueDate') }}</span>
              <span class="font-medium">{{ formatDate(bill.due_date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('currency') }}</span>
              <span class="font-medium">{{ bill.currency }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('status') }}</span>
              <Badge :variant="statusVariant(bill.status)">{{ bill.status }}</Badge>
            </div>
          </CardContent>
        </Card>

        <!-- Internal Notes -->
        <Card v-if="bill.internal_notes">
          <CardHeader>
            <CardTitle class="text-sm">{{ t('internalNotes') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">{{ bill.internal_notes }}</p>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Void Dialog -->
    <Dialog v-model:open="showVoidDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Void Bill</DialogTitle>
          <DialogDescription>
            This action will reverse all financial entries and mark this bill as void. This cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="void_reason">Reason *</Label>
            <Textarea
              id="void_reason"
              v-model="voidReason"
              placeholder="Enter reason for voiding this bill..."
              rows="4"
              class="resize-none"
            />
            <p class="text-xs text-muted-foreground">
              Please provide a reason for voiding this bill for audit purposes.
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            @click="showVoidDialog = false"
            :disabled="isSubmittingVoid"
          >
            Cancel
          </Button>
          <Button
            variant="destructive"
            @click="handleVoid"
            :disabled="!voidReason.trim() || isSubmittingVoid"
          >
            <Ban class="mr-2 h-4 w-4" />
            {{ isSubmittingVoid ? 'Voiding...' : 'Void Bill' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
