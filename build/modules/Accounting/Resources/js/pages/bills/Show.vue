<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import RelatedActions from '@/components/RelatedActions.vue'
import LedgerDocument from '@/components/LedgerDocument.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { DocumentIssuer, DocumentLine, DocumentTotal } from '@/components/LedgerDocument.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import { FileText, Pencil, Trash2, Package, PackageCheck, Ban } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
  letterhead: DocumentIssuer
}

interface LineItem {
  id: string
  item_id: string | null
  warehouse_id: string | null
  item?: {
    id: string
    name: string
    unit_of_measure: string
    track_inventory: boolean
    delivery_mode: string
  } | null
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
  email?: string | null
  phone?: string | null
  address?: Record<string, unknown> | null
  tax_id?: string | null
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

interface ReceiptLineInput {
  line_id: string
  description: string
  unit_of_measure: string
  remaining: number
  expected_quantity: number
  received_quantity: number
  variance_reason: string | null
  variance_treatment: string | null
  warehouse_id: string | null
  notes: string | null
}

interface ClaimReceiptAccount {
  id: string
  code: string
  name: string
}

interface SupplierClaim {
  id: string
  item_name: string
  warehouse_name: string | null
  expected_quantity: number
  received_quantity: number
  variance_quantity: number
  variance_cost: number
  claim_amount: number
  claim_status: string | null
  claim_received_at: string | null
  claim_received_amount: number | null
  claim_received_account: ClaimReceiptAccount | null
  claim_received_transaction_id: string | null
  claim_received_transaction_number: string | null
}

const props = defineProps<{
  company: CompanyRef
  bill: BillRef
  inventoryEnabled?: boolean
  journalTransactionId?: string | null
  supplierClaims: SupplierClaim[]
  claimReceiptAccounts: ClaimReceiptAccount[]
}>()

const { t } = useLexicon()

// State
const showVoidDialog = ref(false)
const voidReason = ref('')
const isSubmittingVoid = ref(false)
const showReceiptDialog = ref(false)
const showClaimReceiptDialog = ref(false)
const selectedClaim = ref<SupplierClaim | null>(null)

const receiptForm = useForm({
  receipt_date: new Date().toISOString().slice(0, 10),
  notes: '',
  lines: [] as ReceiptLineInput[],
})

const claimReceiptForm = useForm({
  receipt_line_id: '',
  received_date: new Date().toISOString().slice(0, 10),
  received_amount: 0,
  received_account_id: '',
  notes: '',
})

const varianceReasonOptions = [
  { value: 'transit_loss', label: 'Transit loss' },
  { value: 'spillage', label: 'Spillage' },
  { value: 'temperature_adjustment', label: 'Temperature adjustment' },
  { value: 'measurement_error', label: 'Measurement error' },
  { value: 'other', label: 'Other' },
]

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('bills'), href: `/${props.company.slug}/bills` },
  { title: props.bill.bill_number, href: `/${props.company.slug}/bills/${props.bill.id}` },
])

const formatNumber = (val: number, decimals: number = 2) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(val)

const formatDate = (dateString: string) => {
  return formatSharedDateTime(dateString, { mode: 'date' })
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

const receivableLineItems = computed(() => {
  return props.bill.line_items.filter((item) => {
    const linkedItem = item.item
    if (!linkedItem) return false
    if (!linkedItem.track_inventory) return false
    if (linkedItem.delivery_mode !== 'requires_receiving') return false
    return item.quantity_received < item.quantity
  })
})

const buildReceiptLines = (): ReceiptLineInput[] => {
  return receivableLineItems.value.map((item) => {
    const remaining = Math.max(0, Number(item.quantity) - Number(item.quantity_received))
    return {
      line_id: item.id,
      description: item.item?.name || item.description,
      unit_of_measure: item.item?.unit_of_measure || '',
      remaining,
      expected_quantity: remaining,
      received_quantity: remaining,
      variance_reason: null,
      variance_treatment: null,
      warehouse_id: item.warehouse_id,
      notes: null,
    }
  })
}

const openReceiptDialog = () => {
  receiptForm.clearErrors()
  receiptForm.reset()
  receiptForm.receipt_date = new Date().toISOString().slice(0, 10)
  receiptForm.notes = ''
  receiptForm.lines = buildReceiptLines()
  showReceiptDialog.value = true
}

const varianceQuantity = (line: ReceiptLineInput) => {
  const expected = Number(line.expected_quantity || 0)
  const received = Number(line.received_quantity || 0)
  return received - expected
}

const varianceLabelClass = (line: ReceiptLineInput) => {
  const variance = varianceQuantity(line)
  if (variance > 0) return 'text-status-success'
  if (variance < 0) return 'text-status-attention'
  return 'text-muted-foreground'
}

const hasMissingReasons = computed(() => {
  return receiptForm.lines.some((line) => {
    const variance = varianceQuantity(line)
    return Math.abs(variance) > 0.0001 && !line.variance_reason
  })
})

const hasMissingTreatments = computed(() => {
  return receiptForm.lines.some((line) => varianceQuantity(line) < -0.0001 && !line.variance_treatment)
})

const submitReceipt = () => {
  const lines = receiptForm.lines
    .filter((line) => Number(line.received_quantity || 0) > 0)
    .map((line) => {
      const variance = varianceQuantity(line)
      return {
        line_id: line.line_id,
        expected_quantity: Number(line.expected_quantity),
        received_quantity: Number(line.received_quantity),
        variance_reason: Math.abs(variance) > 0.0001 ? line.variance_reason : null,
        variance_treatment: variance < -0.0001 ? line.variance_treatment : null,
        warehouse_id: line.warehouse_id,
        notes: line.notes,
      }
    })

  receiptForm
    .transform(() => ({
      receipt_date: receiptForm.receipt_date,
      notes: receiptForm.notes,
      lines,
    }))
    .post(`/${props.company.slug}/bills/${props.bill.id}/receive-goods`, {
      preserveScroll: true,
      onSuccess: () => {
        showReceiptDialog.value = false
      },
    })
}

const pendingSupplierClaims = computed(() =>
  props.supplierClaims.filter((claim) => claim.claim_status === 'pending')
)

const openClaimReceiptDialog = (claim: SupplierClaim) => {
  selectedClaim.value = claim
  claimReceiptForm.clearErrors()
  claimReceiptForm.reset()
  claimReceiptForm.receipt_line_id = claim.id
  claimReceiptForm.received_date = new Date().toISOString().slice(0, 10)
  claimReceiptForm.received_amount = claim.claim_amount
  claimReceiptForm.received_account_id = props.claimReceiptAccounts[0]?.id ?? ''
  claimReceiptForm.notes = ''
  showClaimReceiptDialog.value = true
}

const submitClaimReceipt = () => {
  claimReceiptForm.post(`/${props.company.slug}/bills/${props.bill.id}/supplier-claims/receive`, {
    preserveScroll: true,
    onSuccess: () => {
      showClaimReceiptDialog.value = false
      selectedClaim.value = null
    },
  })
}

// Determine which actions to show based on bill status
const canEdit = computed(() => !['paid', 'void', 'cancelled'].includes(props.bill.status))
const canVoid = computed(() => ['received', 'partial', 'paid'].includes(props.bill.status))
const canDelete = computed(() => props.bill.status === 'draft')

// Check if any line items link to an item
const hasLinkedItems = computed(() => {
  return props.bill.line_items.some(item => item.item_id !== null)
})

// Check if any line items require receiving confirmation
const hasReceivableItems = computed(() => receivableLineItems.value.length > 0)

// Check if goods can be received (has inventory items, not voided, not fully received)
const canReceiveGoods = computed(() => {
  if (!props.inventoryEnabled) return false
  if (!hasReceivableItems.value) return false
  if (props.bill.status !== 'paid') return false
  if (props.bill.goods_received_at) return false
  return true
})

// Check if goods are fully received
const goodsFullyReceived = computed(() => {
  if (!hasReceivableItems.value) return false
  return props.bill.goods_received_at !== null
})

const stockStatusLabel = computed(() => {
  if (!props.inventoryEnabled || !hasLinkedItems.value) return t('stockNotTracked')
  if (!hasReceivableItems.value) return t('stockReceived')
  if (goodsFullyReceived.value) return t('stockReceived')
  if (props.bill.status !== 'paid') return t('stockAwaitingPayment')
  return t('stockPending')
})

const stockStatusVariant = computed((): 'default' | 'secondary' | 'destructive' | 'outline' => {
  if (!props.inventoryEnabled || !hasLinkedItems.value) return 'secondary'
  if (!hasReceivableItems.value) return 'default'
  if (goodsFullyReceived.value) return 'default'
  if (props.bill.status !== 'paid') return 'outline'
  return 'destructive'
})

const handleReceiveGoods = () => {
  openReceiptDialog()
}

/**
 * The bill as a document rather than a screen.
 *
 * A bill is an invoice somebody sent us, so the vendor issues it and the
 * company receives it -- which is why the vendor block is the letterhead here
 * and the customer block is the letterhead on an invoice. Same sheet, parties
 * swapped. The page used to render line items as bordered cards, one card per
 * line, which is the only place in the app that shape appeared; going through
 * LedgerDocument puts bills into the same register grammar as everything else.
 */
const ADDRESS_PARTS = ['line1', 'line2', 'street', 'city', 'state', 'postal_code', 'country']

const addressLines = (address?: Record<string, unknown> | null): string[] => {
  if (!address) return []
  return ADDRESS_PARTS.map((part) => address[part])
    .filter((value): value is string => typeof value === 'string' && value.trim().length > 0)
}

const issuer = computed(() => ({
  name: props.bill.vendor?.name ?? t('vendor'),
  logoUrl: props.bill.vendor?.logo_url,
  lines: addressLines(props.bill.vendor?.address),
  email: props.bill.vendor?.email ?? undefined,
  phone: props.bill.vendor?.phone ?? undefined,
  taxId: props.bill.vendor?.tax_id ?? undefined,
  taxIdLabel: 'NTN',
}))

/* Same identity as the issuer block elsewhere; on a bill it is the party
   receiving rather than the party sending. */
const billedTo = computed(() => props.company.letterhead)

const documentDates = computed(() =>
  [
    { label: t('billDate'), value: formatDate(props.bill.bill_date) },
    { label: t('dueDate'), value: formatDate(props.bill.due_date) },
  ].filter((date): date is { label: string; value: string } => Boolean(date.value)),
)

const documentLines = computed<DocumentLine[]>(() =>
  props.bill.line_items.map((item) => ({
    description: item.description,
    detail: item.item?.name && item.item.name !== item.description ? item.item.name : undefined,
    quantity: item.quantity,
    unit: item.item?.unit_of_measure,
    unitPrice: item.unit_price,
    amount: item.total,
  })),
)

/* Zero rows are left out: a discount of nothing is not a fact about this bill. */
const documentTotals = computed<DocumentTotal[]>(() => {
  const totals: DocumentTotal[] = [{ label: t('subtotal'), amount: props.bill.subtotal }]
  if (props.bill.discount_amount > 0) {
    totals.push({ label: t('discount'), amount: props.bill.discount_amount, sign: '−' })
  }
  if (props.bill.tax_amount > 0) {
    totals.push({ label: t('tax'), amount: props.bill.tax_amount, sign: '+' })
  }
  if (props.bill.paid_amount > 0) {
    totals.push({ label: t('paid'), amount: props.bill.paid_amount, sign: '−', muted: true })
  }
  return totals
})

/* Stamped across the sheet when the bill's standing is in question. */
const overprint = computed(() => {
  if (['void', 'cancelled', 'reversed'].includes(props.bill.status)) return 'Void'
  if (props.bill.status === 'draft') return 'Draft'
  if (props.bill.status === 'paid' || Number(props.bill.balance) === 0) return 'Paid'
  return null
})

const navigateToVendor = () => {
  if (props.bill.vendor_id) {
    router.get(`/${props.company.slug}/vendors/${props.bill.vendor_id}`)
  }
}
</script>

<template>
  <Head :title="`${t('bill')} ${bill.bill_number}`" />
  <PageShell
    :title="`${t('bill')} ${bill.bill_number}`"
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
        <LedgerDocument
          :doc-type="t('bill')"
          :doc-number="bill.bill_number"
          :issuer="issuer"
          :bill-to="billedTo"
          :bill-to-label="t('billedTo')"
          :dates="documentDates"
          :lines="documentLines"
          :totals="documentTotals"
          :grand-total-label="t('total')"
          :grand-total-amount="bill.total_amount"
          :amount-due-label="t('balanceDue')"
          :amount-due-amount="bill.balance"
          :currency="bill.currency"
          locale="en-PK"
          :overprint="overprint"
        >
          <template v-if="bill.notes" #terms>
            <p dir="auto">{{ bill.notes }}</p>
          </template>
        </LedgerDocument>

        <Card v-if="supplierClaims.length > 0" variant="detail">
          <CardHeader>
            <CardTitle>Supplier Claims</CardTitle>
            <CardDescription>Short deliveries claimed from the supplier stay here until compensation is received.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-for="claim in supplierClaims"
              :key="claim.id"
              class="rounded-lg border p-4"
            >
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-medium">{{ claim.item_name }}</p>
                    <StatusBadge :status="claim.claim_status" fallback="Pending" />
                  </div>
                  <p class="text-sm text-muted-foreground">
                    Short {{ formatNumber(Math.abs(claim.variance_quantity), 3) }}
                    <span v-if="claim.warehouse_name"> · {{ claim.warehouse_name }}</span>
                  </p>
                  <p v-if="claim.claim_received_at" class="text-xs text-muted-foreground">
                    Received {{ formatDate(claim.claim_received_at) }}
                    <span v-if="claim.claim_received_account"> · {{ claim.claim_received_account.code }} — {{ claim.claim_received_account.name }}</span>
                  </p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                  <span class="font-semibold"><MoneyText :amount="claim.claim_amount" :currency="bill.currency" /></span>
                  <Button
                    v-if="claim.claim_status === 'pending'"
                    size="sm"
                    variant="outline"
                    @click="openClaimReceiptDialog(claim)"
                  >
                    Mark Received
                  </Button>
                  <Button
                    v-else-if="claim.claim_received_transaction_id"
                    size="sm"
                    variant="ghost"
                    @click="router.get(`/${company.slug}/journals/${claim.claim_received_transaction_id}`)"
                  >
                    View Journal
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Payment Summary -->
        <Card variant="detail">
          <CardHeader>
            <CardTitle>{{ t('paymentSummary') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('billAmount') }}</span>
                <span class="font-medium"><MoneyText :amount="bill.total_amount" :currency="bill.currency" /></span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">{{ t('amountPaid') }}</span>
                <span class="font-medium"><MoneyText :amount="bill.paid_amount" :currency="bill.currency" /></span>
              </div>
              <Separator />
              <div class="flex justify-between text-base font-semibold">
                <span>{{ t('balanceDue') }}</span>
                <span :class="bill.balance > 0 ? 'text-destructive' : 'text-status-success'">
                  <MoneyText :amount="bill.balance" :currency="bill.currency" />
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
                {{ t('receiveStock') }}
              </Button>

              <!-- Goods Received Status -->
              <div
                v-if="goodsFullyReceived"
                class="flex items-center justify-center gap-2 p-2 rounded-md bg-status-success/10 text-status-success text-sm"
              >
                <PackageCheck class="h-4 w-4" />
                <span>{{ t('stockReceived') }}</span>
              </div>

              <!-- No Inventory Items Warning -->
              <div
                v-if="inventoryEnabled && !hasLinkedItems && !['void', 'cancelled', 'draft'].includes(bill.status)"
                class="flex items-start gap-2 p-3 rounded-md bg-status-attention/10 border border-status-attention/30 text-status-attention text-xs"
              >
                <Package class="h-4 w-4 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium mb-1">No inventory items</p>
                  <p class="text-status-attention">
                    This bill has no linked inventory items. To track goods receipt, edit the bill and select inventory items for each line.
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Bill Details -->
        <Card variant="detail">
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
              <StatusBadge :status="bill.status" explain />
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">{{ t('stockStatus') }}</span>
              <Badge :variant="stockStatusVariant">{{ stockStatusLabel }}</Badge>
            </div>
          </CardContent>
        </Card>

        <!-- Internal Notes -->
        <Card v-if="bill.internal_notes" variant="detail">
          <CardHeader>
            <CardTitle>{{ t('internalNotes') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">{{ bill.internal_notes }}</p>
          </CardContent>
        </Card>
      </div>
    </div>

    <Dialog v-model:open="showReceiptDialog">
      <DialogContent class="max-w-4xl">
        <DialogHeader>
          <DialogTitle>Receive Goods</DialogTitle>
          <DialogDescription>
            Record expected vs received quantities for this delivery. Variances post to Transit Loss/Gain.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="receipt_date">Receipt date</Label>
              <Input id="receipt_date" v-model="receiptForm.receipt_date" type="date" />
              <InputError :message="receiptForm.errors.receipt_date" />
            </div>
            <div class="space-y-2">
              <Label for="receipt_notes">Notes</Label>
              <Textarea id="receipt_notes" v-model="receiptForm.notes" rows="2" />
              <InputError :message="receiptForm.errors.notes" />
            </div>
          </div>

          <div class="rounded-lg border">
            <div class="grid grid-cols-12 gap-3 border-b bg-muted/40 px-4 py-2 text-xs font-medium text-muted-foreground">
              <div class="col-span-4">Item</div>
              <div class="col-span-2 text-right">Remaining</div>
              <div class="col-span-2 text-right">Expected</div>
              <div class="col-span-2 text-right">Received</div>
              <div class="col-span-2 text-right">Variance</div>
            </div>

            <div
              v-for="line in receiptForm.lines"
              :key="line.line_id"
              class="border-b px-4 py-3 last:border-b-0"
            >
              <div class="grid grid-cols-12 items-center gap-3">
                <div class="col-span-4">
                  <p class="text-sm font-medium text-foreground">{{ line.description }}</p>
                  <p v-if="line.unit_of_measure" class="text-xs text-muted-foreground">
                    Unit: {{ line.unit_of_measure }}
                  </p>
                </div>
                <div class="col-span-2 text-right text-sm text-muted-foreground">
                  {{ formatNumber(line.remaining, 3) }}
                </div>
                <div class="col-span-2">
                  <Input
                    v-model.number="line.expected_quantity"
                    type="number"
                    min="0.01"
                    :max="line.remaining"
                    step="0.001"
                    class="h-9 text-right"
                  />
                </div>
                <div class="col-span-2">
                  <Input
                    v-model.number="line.received_quantity"
                    type="number"
                    min="0.01"
                    :max="line.remaining"
                    step="0.001"
                    class="h-9 text-right"
                  />
                </div>
                <div class="col-span-2 text-right text-sm font-medium" :class="varianceLabelClass(line)">
                  {{ formatNumber(varianceQuantity(line), 3) }}
                </div>
              </div>

              <div v-if="Math.abs(varianceQuantity(line)) > 0.0001" class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Variance reason</Label>
                  <Select v-model="line.variance_reason">
                    <SelectTrigger>
                      <SelectValue placeholder="Select reason" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in varianceReasonOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div v-if="varianceQuantity(line) < -0.0001" class="space-y-2">
                  <Label>Shortage treatment</Label>
                  <Select v-model="line.variance_treatment">
                    <SelectTrigger>
                      <SelectValue placeholder="Choose treatment" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="supplier_claim">Claim from supplier</SelectItem>
                      <SelectItem value="final_loss">Final loss</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Line notes</Label>
                  <Input v-model="line.notes" placeholder="Optional notes" />
                </div>
              </div>
            </div>
          </div>

          <div v-if="Object.keys(receiptForm.errors).length" class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
            {{ receiptForm.errors.lines ?? receiptForm.errors.receipt_date ?? receiptForm.errors.notes ?? 'Please review the receipt details.' }}
          </div>
          <p v-if="hasMissingReasons || hasMissingTreatments" class="text-xs text-status-attention">
            Select a variance reason for every variance and a treatment for every shortage.
          </p>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="showReceiptDialog = false">Cancel</Button>
          <Button type="button" :disabled="receiptForm.processing || hasMissingReasons || hasMissingTreatments" @click="submitReceipt">
            Confirm receipt
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="showClaimReceiptDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Receive Supplier Claim</DialogTitle>
          <DialogDescription>
            Record compensation for the short delivery claim.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-2">
          <div v-if="selectedClaim" class="rounded-md border bg-muted/30 p-3 text-sm">
            <div class="flex justify-between">
              <span>{{ selectedClaim.item_name }}</span>
              <span class="font-medium"><MoneyText :amount="selectedClaim.claim_amount" :currency="bill.currency" /></span>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="claim_received_date">Received date</Label>
              <Input id="claim_received_date" v-model="claimReceiptForm.received_date" type="date" />
              <InputError :message="claimReceiptForm.errors.received_date" />
            </div>
            <div class="space-y-2">
              <Label for="claim_received_amount">Amount</Label>
              <Input id="claim_received_amount" v-model.number="claimReceiptForm.received_amount" type="number" min="0.01" step="0.01" />
              <InputError :message="claimReceiptForm.errors.received_amount" />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Received in</Label>
            <Select v-model="claimReceiptForm.received_account_id">
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="account in claimReceiptAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} — {{ account.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="claimReceiptForm.errors.received_account_id" />
          </div>

          <div class="space-y-2">
            <Label for="claim_notes">Notes</Label>
            <Input id="claim_notes" v-model="claimReceiptForm.notes" placeholder="Optional" />
            <InputError :message="claimReceiptForm.errors.notes" />
          </div>

          <div v-if="Object.keys(claimReceiptForm.errors).length" class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
            {{ claimReceiptForm.errors.received_amount ?? claimReceiptForm.errors.received_account_id ?? claimReceiptForm.errors.received_date ?? 'Please review the claim receipt.' }}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" :disabled="claimReceiptForm.processing" @click="showClaimReceiptDialog = false">
            Cancel
          </Button>
          <Button :disabled="claimReceiptForm.processing || !claimReceiptForm.received_account_id" @click="submitClaimReceipt">
            Mark Received
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

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

    <RelatedActions screen="bill.show" :slug="company.slug" :subject="bill" />
  </PageShell>
</template>
