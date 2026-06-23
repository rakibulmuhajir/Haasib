<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { AlertTriangle, ClipboardCheck, PackageCheck, TrendingUp, Warehouse } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Filters {
  start_date: string
  end_date: string
  tank_id: string
  product_id: string
  variance_type: string
  claim_status: string
}

interface Totals {
  physical_count: number
  physical_loss_liters: number
  physical_gain_liters: number
  physical_loss_value: number
  physical_gain_value: number
  claim_count: number
  pending_claim_count: number
  pending_claim_amount: number
  final_loss_amount: number
  received_claim_amount: number
}

interface PhysicalRow {
  id: string
  date_label: string | null
  time_label: string | null
  tank_name: string
  product_name: string
  reading_type: string
  status: string
  dip_liters: number
  expected_liters: number
  variance_liters: number
  variance_type: 'loss' | 'gain' | 'none'
  variance_reason: string | null
  unit_cost: number
  value: number
  journal_entry_id: string | null
  notes: string | null
}

interface ClaimRow {
  id: string
  date_label: string | null
  product_name: string
  warehouse_name: string
  vendor_name: string
  bill_id: string | null
  bill_number: string | null
  expected_quantity: number
  received_quantity: number
  variance_quantity: number
  unit_cost: number
  claim_amount: number
  variance_reason: string | null
  variance_treatment: 'final_loss' | 'supplier_claim' | null
  claim_status: 'pending' | 'received' | 'cancelled' | null
  claim_received_at: string | null
  claim_received_amount: number | null
  claim_received_transaction_id: string | null
  claim_received_transaction_number: string | null
}

interface Option {
  id: string
  name: string
}

const props = defineProps<{
  company: Company
  filters: Filters
  totals: Totals
  physicalRows: PhysicalRow[]
  claimRows: ClaimRow[]
  tanks: Option[]
  products: Option[]
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const tankId = ref(props.filters.tank_id)
const productId = ref(props.filters.product_id)
const varianceType = ref(props.filters.variance_type)
const claimStatus = ref(props.filters.claim_status)

const symbol = computed(() => currencySymbol(props.company.base_currency))
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Stock Variance & Claims', href: `/${props.company.slug}/fuel/reports/stock-variance` },
])

const money = (amount: number) => `${symbol.value} ${new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
}).format(amount || 0)}`

const qty = (amount: number, decimals = 0) => new Intl.NumberFormat('en-US', {
  minimumFractionDigits: decimals,
  maximumFractionDigits: decimals,
}).format(amount || 0)

const human = (value: string | null | undefined) => {
  if (!value) return '—'
  return value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

const badgeVariant = (status: string | null) => {
  if (status === 'received' || status === 'posted') return 'default'
  if (status === 'pending' || status === 'supplier_claim') return 'secondary'
  return 'outline'
}

const applyFilters = () => {
  router.get(`/${props.company.slug}/fuel/reports/stock-variance`, {
    start_date: startDate.value,
    end_date: endDate.value,
    tank_id: tankId.value,
    product_id: productId.value,
    variance_type: varianceType.value,
    claim_status: claimStatus.value,
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const isoDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const setRange = (range: 'today' | 'last7' | 'month' | 'lastMonth') => {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  if (range === 'today') {
    startDate.value = isoDate(today)
    endDate.value = isoDate(today)
  } else if (range === 'last7') {
    const start = new Date(today)
    start.setDate(today.getDate() - 6)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  } else if (range === 'lastMonth') {
    const start = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const end = new Date(today.getFullYear(), today.getMonth(), 0)
    startDate.value = isoDate(start)
    endDate.value = isoDate(end)
  } else {
    const start = new Date(today.getFullYear(), today.getMonth(), 1)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  }

  applyFilters()
}
</script>

<template>
  <Head title="Stock Variance & Claims" />

  <PageShell
    title="Stock Variance & Claims"
    description="Physical tank dip variance, delivery shortages, final losses, and supplier claims."
    :icon="TrendingUp"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-5">
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Filters</CardTitle>
          <CardDescription>Filter physical tank variances and supplier delivery claims together.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" @click="setRange('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setRange('last7')">Last 7 days</Button>
              <Button variant="outline" size="sm" @click="setRange('month')">This month</Button>
              <Button variant="outline" size="sm" @click="setRange('lastMonth')">Last month</Button>
            </div>

            <div class="grid gap-1.5">
              <Label for="start_date">From</Label>
              <Input id="start_date" v-model="startDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label for="end_date">To</Label>
              <Input id="end_date" v-model="endDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label>Location</Label>
              <Select v-model="tankId">
                <SelectTrigger class="w-44">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All locations</SelectItem>
                  <SelectItem v-for="tank in tanks" :key="tank.id" :value="tank.id">
                    {{ tank.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Product</Label>
              <Select v-model="productId">
                <SelectTrigger class="w-44">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All products</SelectItem>
                  <SelectItem v-for="product in products" :key="product.id" :value="product.id">
                    {{ product.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Dip variance</Label>
              <Select v-model="varianceType">
                <SelectTrigger class="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="loss">Loss</SelectItem>
                  <SelectItem value="gain">Gain</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Claim status</Label>
              <Select v-model="claimStatus">
                <SelectTrigger class="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="pending">Pending claim</SelectItem>
                  <SelectItem value="received">Received claim</SelectItem>
                  <SelectItem value="final_loss">Final loss</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Button @click="applyFilters">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Physical loss</CardDescription>
            <CardTitle class="text-2xl text-red-700">{{ qty(totals.physical_loss_liters) }} L</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <AlertTriangle class="h-4 w-4 text-red-700" />
            {{ money(totals.physical_loss_value) }}
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Physical gain</CardDescription>
            <CardTitle class="text-2xl text-emerald-700">{{ qty(totals.physical_gain_liters) }} L</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <PackageCheck class="h-4 w-4 text-emerald-700" />
            {{ money(totals.physical_gain_value) }}
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Pending supplier claims</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.pending_claim_amount) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <ClipboardCheck class="h-4 w-4 text-amber-700" />
            {{ totals.pending_claim_count }} pending
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Final delivery loss</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.final_loss_amount) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Warehouse class="h-4 w-4 text-muted-foreground" />
            Claims received {{ money(totals.received_claim_amount) }}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Physical Tank Variance</CardTitle>
          <CardDescription>Difference between expected stock and latest posted dip readings.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="physicalRows.length === 0" class="py-12 text-center text-muted-foreground">
            No tank dip variance found for this range.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Tank / Product</TableHead>
                  <TableHead class="text-right">Expected</TableHead>
                  <TableHead class="text-right">Dip</TableHead>
                  <TableHead class="text-right">Variance</TableHead>
                  <TableHead class="text-right">Value</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead>Posting</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in physicalRows" :key="row.id">
                  <TableCell>
                    <div class="font-medium">{{ row.date_label }}</div>
                    <div class="text-xs text-muted-foreground">{{ row.time_label }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-medium">{{ row.tank_name }}</div>
                    <div class="text-xs text-muted-foreground">{{ row.product_name }} · {{ human(row.reading_type) }}</div>
                  </TableCell>
                  <TableCell class="text-right">{{ qty(row.expected_liters) }} L</TableCell>
                  <TableCell class="text-right">{{ qty(row.dip_liters) }} L</TableCell>
                  <TableCell class="text-right">
                    <span :class="row.variance_type === 'loss' ? 'text-red-700' : 'text-emerald-700'">
                      {{ row.variance_type === 'loss' ? '-' : '+' }}{{ qty(Math.abs(row.variance_liters)) }} L
                    </span>
                  </TableCell>
                  <TableCell class="text-right">{{ money(row.value) }}</TableCell>
                  <TableCell>{{ human(row.variance_reason) }}</TableCell>
                  <TableCell>
                    <Badge :variant="badgeVariant(row.status)">{{ human(row.status) }}</Badge>
                    <Link
                      v-if="row.journal_entry_id"
                      :href="`/${company.slug}/journals/${row.journal_entry_id}`"
                      class="ml-2 text-xs text-primary underline-offset-4 hover:underline"
                    >
                      Journal
                    </Link>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Delivery Shortage Claims</CardTitle>
          <CardDescription>Short received quantities from purchase receipts, split into supplier claims and final losses.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="claimRows.length === 0" class="py-12 text-center text-muted-foreground">
            No delivery shortage claims or final losses found for this range.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Vendor / Bill</TableHead>
                  <TableHead>Product</TableHead>
                  <TableHead class="text-right">Expected</TableHead>
                  <TableHead class="text-right">Received</TableHead>
                  <TableHead class="text-right">Short</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in claimRows" :key="row.id">
                  <TableCell>{{ row.date_label }}</TableCell>
                  <TableCell>
                    <div class="font-medium">{{ row.vendor_name }}</div>
                    <Link
                      v-if="row.bill_id"
                      :href="`/${company.slug}/bills/${row.bill_id}`"
                      class="text-xs text-primary underline-offset-4 hover:underline"
                    >
                      {{ row.bill_number ?? 'Open bill' }}
                    </Link>
                  </TableCell>
                  <TableCell>
                    <div class="font-medium">{{ row.product_name }}</div>
                    <div class="text-xs text-muted-foreground">{{ row.warehouse_name }}</div>
                  </TableCell>
                  <TableCell class="text-right">{{ qty(row.expected_quantity) }}</TableCell>
                  <TableCell class="text-right">{{ qty(row.received_quantity) }}</TableCell>
                  <TableCell class="text-right text-red-700">{{ qty(Math.abs(row.variance_quantity)) }}</TableCell>
                  <TableCell class="text-right">{{ money(row.claim_amount) }}</TableCell>
                  <TableCell>
                    <div class="flex flex-wrap items-center gap-2">
                      <Badge :variant="badgeVariant(row.claim_status ?? row.variance_treatment)">
                        {{ row.variance_treatment === 'final_loss' ? 'Final loss' : human(row.claim_status) }}
                      </Badge>
                      <Link
                        v-if="row.claim_received_transaction_id"
                        :href="`/${company.slug}/journals/${row.claim_received_transaction_id}`"
                        class="text-xs text-primary underline-offset-4 hover:underline"
                      >
                        {{ row.claim_received_transaction_number ?? 'Claim journal' }}
                      </Link>
                    </div>
                    <div v-if="row.claim_received_at" class="mt-1 text-xs text-muted-foreground">
                      Received {{ row.claim_received_at }}
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
