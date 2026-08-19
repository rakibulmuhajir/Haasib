<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { Droplets, ArrowLeft, Truck, Calendar, FileText } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'

interface ReceiptLine {
  tank_id: string
  tank_name: string
  fuel_name: string
  liters: number
  rate: number
  amount: number
}

interface Receipt {
  id: string
  transaction_date: string
  reference: string | null
  description: string | null
  total_amount: number
  status: string
  metadata: {
    vendor_id?: string
    invoice_number?: string
    total_liters?: number
    lines?: ReceiptLine[]
    notes?: string
  }
  created_at: string
}

interface Vendor {
  id: string
  name: string
  code: string | null
}

const props = defineProps<{
  receipt: Receipt
  vendor: Vendor | null
  currency: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel Receipts', href: `/${companySlug.value}/fuel/receipts` },
  { title: props.receipt.reference || 'Receipt', href: `/${companySlug.value}/fuel/receipts/${props.receipt.id}` },
])

const currency = computed(() => currencySymbol(props.currency))

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return formatSharedDateTime(dateStr, { mode: 'date' })
}

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/receipts`)
}

const lineColumns = [
  { key: 'tank_name', label: 'Tank', kind: 'text' as const },
  { key: 'fuel_name', label: 'Fuel', kind: 'text' as const },
  { key: 'liters', label: 'Liters', kind: 'amount' as const },
  { key: 'rate', label: 'Rate', kind: 'amount' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
]

const lineTotals = computed(() => ({
  liters: `${formatCurrency(props.receipt.metadata.total_liters || 0)} L`,
  amount: `${currency.value} ${formatCurrency(props.receipt.total_amount)}`,
}))
</script>

<template>
  <Head :title="receipt.reference || 'Fuel Receipt'" />

  <PageShell
    :title="receipt.reference || 'Fuel Receipt'"
    :description="`Received on ${formatDate(receipt.transaction_date)}`"
    :icon="Droplets"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Receipt Details -->
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle class="text-base">Receipt Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div>
            <div class="text-sm text-muted-foreground">Status</div>
            <StatusBadge :status="receipt.status" />
          </div>

          <div>
            <div class="text-sm text-muted-foreground">Date</div>
            <div class="font-medium flex items-center gap-2">
              <Calendar class="h-4 w-4 text-muted-foreground" />
              {{ formatDate(receipt.transaction_date) }}
            </div>
          </div>

          <div v-if="vendor">
            <div class="text-sm text-muted-foreground">Vendor</div>
            <div class="font-medium flex items-center gap-2">
              <Truck class="h-4 w-4 text-muted-foreground" />
              {{ vendor.name }}
            </div>
          </div>

          <div v-if="receipt.metadata.invoice_number">
            <div class="text-sm text-muted-foreground">Invoice #</div>
            <div class="font-medium flex items-center gap-2">
              <FileText class="h-4 w-4 text-muted-foreground" />
              {{ receipt.metadata.invoice_number }}
            </div>
          </div>

          <Separator />

          <div>
            <div class="text-sm text-muted-foreground">Total Liters</div>
            <div class="text-xl font-bold text-status-info">
              {{ formatCurrency(receipt.metadata.total_liters || 0) }} L
            </div>
          </div>

          <div>
            <div class="text-sm text-muted-foreground">Total Amount</div>
            <div class="text-xl font-bold">
              {{ currency }} {{ formatCurrency(receipt.total_amount) }}
            </div>
          </div>

          <div v-if="receipt.metadata.notes" class="pt-2">
            <div class="text-sm text-muted-foreground">Notes</div>
            <div class="text-sm mt-1">{{ receipt.metadata.notes }}</div>
          </div>
        </CardContent>
      </Card>

      <!-- Line Items -->
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle class="text-base">Fuel Lines</CardTitle>
          <CardDescription>Details of fuel received per tank.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister
            :data="receipt.metadata.lines || []"
            :columns="lineColumns"
            :totals="lineTotals"
            key-field="tank_id"
          >
            <template #cell-tank_name="{ row }">{{ row.tank_name }}</template>
            <template #cell-fuel_name="{ row }">{{ row.fuel_name }}</template>
            <!-- Fuel arriving in a tank is the business working, not a
                 notice. Ink, like every other ordinary movement. -->
            <template #cell-liters="{ row }">{{ formatCurrency(row.liters) }} L</template>
            <template #cell-rate="{ row }">{{ currency }} {{ formatCurrency(row.rate) }}</template>
            <template #cell-amount="{ row }">{{ currency }} {{ formatCurrency(row.amount) }}</template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
