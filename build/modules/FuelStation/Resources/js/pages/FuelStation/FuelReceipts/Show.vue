<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { Droplets, ArrowLeft, Truck, Calendar, FileText } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

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
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/receipts`)
}
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
            <Badge
              :class="receipt.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ receipt.status === 'posted' ? 'Posted' : receipt.status }}
            </Badge>
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
            <div class="text-xl font-bold text-sky-600">
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
        <CardContent>
          <div class="rounded-lg border">
            <table class="w-full">
              <thead class="bg-muted/50">
                <tr>
                  <th class="text-left px-4 py-3 text-sm font-medium">Tank</th>
                  <th class="text-left px-4 py-3 text-sm font-medium">Fuel</th>
                  <th class="text-right px-4 py-3 text-sm font-medium">Liters</th>
                  <th class="text-right px-4 py-3 text-sm font-medium">Rate</th>
                  <th class="text-right px-4 py-3 text-sm font-medium">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in receipt.metadata.lines" :key="index" class="border-t">
                  <td class="px-4 py-3 font-medium">{{ line.tank_name }}</td>
                  <td class="px-4 py-3">{{ line.fuel_name }}</td>
                  <td class="px-4 py-3 text-right text-sky-600 font-medium">{{ formatCurrency(line.liters) }} L</td>
                  <td class="px-4 py-3 text-right">{{ currency }} {{ formatCurrency(line.rate) }}</td>
                  <td class="px-4 py-3 text-right font-medium">{{ currency }} {{ formatCurrency(line.amount) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-muted/30">
                <tr class="border-t">
                  <td colspan="2" class="px-4 py-3 font-semibold">Total</td>
                  <td class="px-4 py-3 text-right font-semibold text-sky-600">
                    {{ formatCurrency(receipt.metadata.total_liters || 0) }} L
                  </td>
                  <td class="px-4 py-3"></td>
                  <td class="px-4 py-3 text-right font-semibold">
                    {{ currency }} {{ formatCurrency(receipt.total_amount) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
