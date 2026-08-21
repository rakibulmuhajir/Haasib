<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { CalendarClock, Droplet, Plus, TrendingUp } from 'lucide-vue-next'
import { formatMoneyText } from '@/lib/money'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
  cost_price?: number | string | null
  avg_cost?: number | string | null
  selling_price?: number | string | null
}

interface RateChangeRow {
  id: string
  item_id: string
  effective_date: string
  purchase_rate: number
  sale_rate: number
  stock_quantity_at_change?: number | null
  margin_impact?: number | null
  snapshot_tank_id?: string | null
  snapshot_stick_reading?: number | null
  snapshot_dip_liters?: number | null
  snapshot_nozzle_readings?: SnapshotNozzleReading[] | null
  notes?: string | null
  item?: FuelItemRef | null
}

interface TankRef {
  id: string
  code: string
  name: string
  linked_item_id: string
  capacity?: number | string | null
}

interface NozzleRef {
  id: string
  code: string
  label?: string | null
  pump_name?: string | null
  tank_id: string
  item_id: string
  last_closing_reading: number
  last_manual_reading?: number | null
  has_electronic_meter: boolean
}

interface SnapshotNozzleReading {
  nozzle_id: string
  electronic_reading: number | null
  manual_reading: number | null
}

const props = defineProps<{
  rates: RateChangeRow[]
  items: FuelItemRef[]
  stockLevels: Record<string, number>
  tanks: TankRef[]
  nozzles: NozzleRef[]
}>()

const page = usePage()
const { companySlug } = useCompanyRoute()

const currencyCode = computed(() => {
  const code = (page.props as any)?.auth?.currentCompany?.base_currency as string | undefined
  return code || 'PKR'
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/rates` },
  { title: 'Rates', href: `/${companySlug.value}/fuel/rates` },
])

const formatMoney = (amount: number) => {
  try {
    return formatMoneyText(amount ?? 0, currencyCode.value, { fractionDigits: 2 })
  } catch (_e) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount ?? 0)
  }
}

const formatLiters = (liters: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(liters ?? 0)

const spreadFor = (r: RateChangeRow) => (Number(r.sale_rate ?? 0) - Number(r.purchase_rate ?? 0))

const formatEffectiveDate = (value: string) => {
  if (!value) return '—'
  // Inertia may serialize date columns as ISO strings (e.g., 2025-12-17T00:00:00.000000Z).
  return value.includes('T') ? value.split('T')[0] : value
}

const byItemCurrent = computed(() => {
  const current = new Map<string, RateChangeRow>()
  for (const r of props.rates) {
    const existing = current.get(r.item_id)
    if (!existing) {
      current.set(r.item_id, r)
      continue
    }
    if (new Date(r.effective_date).getTime() > new Date(existing.effective_date).getTime()) {
      current.set(r.item_id, r)
    }
  }
  return current
})

const currentCards = computed(() => {
  return props.items
    .map((item) => {
      const r = byItemCurrent.value.get(item.id) || null
      const productPurchase = Number(item.avg_cost || item.cost_price || 0)
      const productSale = Number(item.selling_price || 0)
      const fallbackRate = !r && (productPurchase > 0 || productSale > 0)
        ? {
            id: `product-${item.id}`,
            item_id: item.id,
            effective_date: '',
            purchase_rate: productPurchase,
            sale_rate: productSale,
            item,
          } as RateChangeRow
        : null
      return { item, rate: r || fallbackRate, source: r ? 'history' : fallbackRate ? 'product' : 'none' }
    })
    .sort((a, b) => (a.item.name || '').localeCompare(b.item.name || ''))
})

const itemFilter = ref<string>('all')
const filteredRates = computed(() => {
  if (itemFilter.value === 'all') return props.rates
  return props.rates.filter((r) => r.item_id === itemFilter.value)
})

const columns = [
  { key: 'effective_date', label: 'Effective', kind: 'date' as const },
  { key: 'item', label: 'Fuel item', kind: 'text' as const },
  { key: 'purchase_rate', label: 'Supplier purchase', kind: 'amount' as const },
  { key: 'sale_rate', label: 'Govt sale', kind: 'amount' as const },
  { key: 'margin', label: 'Spread', kind: 'amount' as const },
  { key: 'impact', label: 'Impact', kind: 'text' as const },
]

const tableData = computed(() =>
  filteredRates.value
    .slice()
    .sort((a, b) => new Date(b.effective_date).getTime() - new Date(a.effective_date).getTime())
    .map((r) => ({
      id: r.id,
      effective_date: formatEffectiveDate(r.effective_date),
      item: r.item?.name ?? props.items.find((i) => i.id === r.item_id)?.name ?? '—',
      purchase_rate: `${formatMoney(r.purchase_rate)} / L`,
      sale_rate: `${formatMoney(r.sale_rate)} / L`,
      margin: `${formatMoney(spreadFor(r))} / L`,
      impact: r.stock_quantity_at_change ? `${formatMoney(r.margin_impact ?? 0)} @ ${formatLiters(r.stock_quantity_at_change)}L` : '—',
      _raw: r,
      _isCurrent: byItemCurrent.value.get(r.item_id)?.id === r.id,
    }))
)

const dialogOpen = ref(false)
const openCreate = () => {
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

const closeDialog = () => {
  dialogOpen.value = false
  form.reset()
  form.clearErrors()
}

const form = useForm<{
  item_id: string
  effective_date: string
  purchase_rate: number | null
  sale_rate: number | null
  stock_quantity_at_change: number | null
  snapshot_tank_id: string
  snapshot_stick_reading: number | null
  snapshot_dip_liters: number | null
  snapshot_nozzle_readings: SnapshotNozzleReading[]
  notes: string
}>({
  item_id: '',
  effective_date: new Date().toISOString().slice(0, 10),
  purchase_rate: null,
  sale_rate: null,
  stock_quantity_at_change: null,
  snapshot_tank_id: '',
  snapshot_stick_reading: null,
  snapshot_dip_liters: null,
  snapshot_nozzle_readings: [],
  notes: '',
})

const currentRateForSelectedItem = computed(() => {
  if (!form.item_id) return null
  return byItemCurrent.value.get(form.item_id) ?? null
})

const selectedItemTanks = computed(() => {
  if (!form.item_id) return []
  return props.tanks.filter((tank) => tank.linked_item_id === form.item_id)
})

const selectedItemNozzles = computed(() => {
  if (!form.item_id) return []
  return props.nozzles.filter((nozzle) => nozzle.item_id === form.item_id)
})

const selectedStockLevel = computed(() => {
  if (!form.item_id) return 0
  return Number(props.stockLevels?.[form.item_id] ?? 0)
})

const prefillFromCurrent = () => {
  const current = currentRateForSelectedItem.value
  if (!current) return

  if (form.purchase_rate === null) form.purchase_rate = Number(current.purchase_rate)
  if (form.sale_rate === null) form.sale_rate = Number(current.sale_rate)
}

const syncSnapshotRows = () => {
  const existing = new Map(form.snapshot_nozzle_readings.map((row) => [row.nozzle_id, row]))
  form.snapshot_nozzle_readings = selectedItemNozzles.value.map((nozzle) => ({
    nozzle_id: nozzle.id,
    electronic_reading: existing.get(nozzle.id)?.electronic_reading ?? null,
    manual_reading: existing.get(nozzle.id)?.manual_reading ?? null,
  }))

  if (!selectedItemTanks.value.some((tank) => tank.id === form.snapshot_tank_id)) {
    form.snapshot_tank_id = selectedItemTanks.value[0]?.id ?? ''
  }
  if (form.stock_quantity_at_change === null && selectedStockLevel.value > 0) {
    form.stock_quantity_at_change = selectedStockLevel.value
  }
}

watch(() => form.item_id, () => {
  prefillFromCurrent()
  syncSnapshotRows()
})

const nozzleForSnapshot = (nozzleId: string) => props.nozzles.find((nozzle) => nozzle.id === nozzleId)

/** Laravel returns these as `snapshot_nozzle_readings.0.electronic_reading`. */
const snapshotError = (index: number, field: string) =>
  (form.errors as Record<string, string>)[`snapshot_nozzle_readings.${index}.${field}`]

const submit = () => {
  const slug = companySlug.value
  if (!slug) return

  form
    .transform((data) => ({
      ...data,
      snapshot_tank_id: data.snapshot_tank_id || null,
      snapshot_nozzle_readings: data.snapshot_nozzle_readings.filter((row) =>
        row.electronic_reading !== null || row.manual_reading !== null
      ),
    }))
    .post(`/${slug}/fuel/rates`, {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
}
</script>

<template>
  <Head title="Fuel Rates" />

  <PageShell
    title="Fuel Rates"
    description="Record OGRA (govt) sale rates by effective date (from 00:00). Supplier purchase rate here is a reference for new deliveries; your actual delivery cost comes from bills."
    :icon="TrendingUp"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        Add Rate Change
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <Card
        v-for="{ item, rate, source } in currentCards"
        :key="item.id"
        class="relative overflow-hidden border-border/80 bg-surface-sunken"
      >
        <CardHeader class="pb-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <CardTitle class="flex items-center gap-2 text-base">
                <Droplet class="h-4 w-4 text-status-info" />
                {{ item.name }}
              </CardTitle>
              <CardDescription class="mt-1">
                <span v-if="item.fuel_category">Category: {{ item.fuel_category }}</span>
                <span v-else>Fuel item</span>
              </CardDescription>
            </div>
            <Badge v-if="rate && source === 'history'" class="bg-status-success text-status-success-contrast hover:bg-status-success">Current</Badge>
            <Badge v-else-if="rate" variant="secondary" class="bg-status-info/10 text-status-info hover:bg-status-info/10">Product rate</Badge>
            <Badge v-else variant="secondary" class="bg-surface-sunken text-text-primary hover:bg-surface-sunken">No rate</Badge>
          </div>
        </CardHeader>

        <CardContent class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-border/70 bg-surface-raised/50 p-3">
              <p class="text-xs font-medium text-text-tertiary">Supplier purchase (reference)</p>
              <p class="mt-1 text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(rate.purchase_rate)} / L` : '—' }}
              </p>
            </div>
            <div class="rounded-lg border border-border/70 bg-surface-raised/50 p-3">
              <p class="text-xs font-medium text-text-tertiary">Govt sale (OGRA)</p>
              <p class="mt-1 text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(rate.sale_rate)} / L` : '—' }}
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/40 px-3 py-2">
            <div class="flex items-center gap-2">
              <Badge variant="secondary" class="bg-status-info/10 text-status-info hover:bg-status-info/10">
                Spread
              </Badge>
              <span class="text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(spreadFor(rate))} / L` : '—' }}
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-text-secondary">
              <CalendarClock class="h-4 w-4 text-text-tertiary" />
              <span>{{ rate && source === 'history' ? `${formatEffectiveDate(rate.effective_date)} (from 00:00)` : rate ? 'From product setup' : 'No effective date' }}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Rate History</CardTitle>
            <CardDescription>Every change is preserved for audit and disputes.</CardDescription>
          </div>

          <div class="flex items-center gap-2">
            <Label class="text-sm text-text-secondary">Fuel item</Label>
            <Select v-model="itemFilter">
              <SelectTrigger class="w-[220px]">
                <SelectValue placeholder="All items" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All items</SelectItem>
                <SelectItem v-for="item in items" :key="item.id" :value="item.id">
                  {{ item.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <LedgerRegister :data="tableData" :columns="columns" banded>
          <template #empty>
            <EmptyState
              title="No rate changes yet"
              description="Add your first rate change to track margins and preserve history."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Rate Change
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-effective_date="{ row }">
            <div class="flex items-center gap-2">
              <Badge
                v-if="row._isCurrent"
                class="bg-status-success text-status-success-contrast hover:bg-status-success"
              >
                Current
              </Badge>
              <span class="font-medium text-text-primary">{{ row.effective_date }}</span>
            </div>
          </template>

          <template #cell-item="{ row }">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-text-primary">{{ row.item }}</span>
              <Badge
                v-if="row._raw.item?.fuel_category"
                variant="secondary"
                class="bg-status-info/10 text-status-info hover:bg-status-info/10"
              >
                {{ row._raw.item.fuel_category }}
              </Badge>
            </div>
          </template>

          <template #cell-margin="{ row }">
<!-- A positive margin is the ordinary case and needs no colour. A
                 negative one means the pump is selling below what the fuel cost,
                 which is the rare thing on this page somebody must act on. -->
            <Badge
              :class="spreadFor(row._raw) >= 0 ? '' : 'bg-status-attention/10 text-status-attention hover:bg-status-attention/10'"
            >
              {{ row.margin }}
            </Badge>
          </template>
        </LedgerRegister>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <TrendingUp class="h-5 w-5 text-status-info" />
            Add Rate Change
          </DialogTitle>
          <DialogDescription>
            Add the OGRA (govt) sale rate for the effective date (enforced from 00:00). Supplier purchase here is a reference rate for new deliveries.
          </DialogDescription>
        </DialogHeader>

        <form novalidate class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="item_id">Fuel item</Label>
              <Select v-model="form.item_id">
                <SelectTrigger id="item_id" :class="{ 'border-destructive': form.errors.item_id }">
                  <SelectValue placeholder="Select fuel item..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="item in items" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.item_id" />
            </div>

            <div class="space-y-2">
              <Label for="effective_date">Effective date (from 00:00)</Label>
              <Input
                id="effective_date"
                v-model="form.effective_date"
                type="date"
                :class="{ 'border-destructive': form.errors.effective_date }"
              />
              <InputError :message="form.errors.effective_date" />
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="purchase_rate">Supplier purchase rate (new deliveries)</Label>
              <Input
                id="purchase_rate"
                v-model.number="form.purchase_rate"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :class="{ 'border-destructive': form.errors.purchase_rate }"
              />
              <p class="text-xs text-muted-foreground">
                This does not change your current stock cost. Use the delivery bill for actual purchase cost.
              </p>
              <InputError :message="form.errors.purchase_rate" />
            </div>
            <div class="space-y-2">
              <Label for="sale_rate">Govt sale rate (OGRA)</Label>
              <Input
                id="sale_rate"
                v-model.number="form.sale_rate"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :class="{ 'border-destructive': form.errors.sale_rate }"
              />
              <p class="text-xs text-muted-foreground">
                Shift Close uses this to calculate revenue for the day/shift (unless overridden).
              </p>
              <InputError :message="form.errors.sale_rate" />
            </div>
          </div>

          <div class="rounded-xl border border-border/70 bg-muted/30 p-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-text-primary">Optional rate-change snapshot</p>
                <p class="text-sm text-text-secondary">
                  If staff records midnight dip and meters, Daily Close can split sales before and after the rate change.
                </p>
              </div>
              <Badge variant="outline" class="border-status-info/30 text-status-info">
                {{ currencyCode }}
              </Badge>
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="stock_quantity_at_change">System stock at change (L)</Label>
                <Input
                  id="stock_quantity_at_change"
                  v-model.number="form.stock_quantity_at_change"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="Optional"
                  :class="{ 'border-destructive': form.errors.stock_quantity_at_change }"
                />
                <p class="text-xs text-muted-foreground">
                  Current estimate: {{ formatLiters(selectedStockLevel) }} L.
                </p>
                <InputError :message="form.errors.stock_quantity_at_change" />
              </div>

              <div class="space-y-2">
                <Label for="snapshot_tank_id">Tank</Label>
                <Select v-model="form.snapshot_tank_id" :disabled="selectedItemTanks.length === 0">
                  <SelectTrigger id="snapshot_tank_id" :class="{ 'border-destructive': form.errors.snapshot_tank_id }">
                    <SelectValue placeholder="Select tank..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="tank in selectedItemTanks" :key="tank.id" :value="tank.id">
                      {{ tank.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="selectedItemTanks.length === 0" class="text-xs text-muted-foreground">
                  No tank is linked to this product.
                </p>
                <InputError :message="form.errors.snapshot_tank_id" />
              </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="snapshot_stick_reading">Recorded stick reading</Label>
                <Input
                  id="snapshot_stick_reading"
                  v-model.number="form.snapshot_stick_reading"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="cm"
                  :class="{ 'border-destructive': form.errors.snapshot_stick_reading }"
                />
                <InputError :message="form.errors.snapshot_stick_reading" />
              </div>

              <div class="space-y-2">
                <Label for="snapshot_dip_liters">Recorded dip quantity</Label>
                <Input
                  id="snapshot_dip_liters"
                  v-model.number="form.snapshot_dip_liters"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="liters"
                  :class="{ 'border-destructive': form.errors.snapshot_dip_liters }"
                />
                <p class="text-xs text-muted-foreground">
                  Used for revaluation if entered.
                </p>
                <InputError :message="form.errors.snapshot_dip_liters" />
              </div>
            </div>

            <div v-if="selectedItemNozzles.length > 0" class="mt-4 space-y-3">
              <div>
                <p class="text-sm font-medium text-text-primary">Pump point meters at rate change</p>
                <p class="text-xs text-muted-foreground">Leave blank if not recorded.</p>
              </div>
              <div
                v-for="(snapshot, index) in form.snapshot_nozzle_readings"
                :key="snapshot.nozzle_id"
                class="grid gap-3 rounded-lg border border-border/70 bg-background p-3 sm:grid-cols-[1fr_140px_140px]"
              >
                <div>
                  <p class="text-sm font-medium text-text-primary">
                    {{ nozzleForSnapshot(snapshot.nozzle_id)?.pump_name || 'Pump point' }}
                    · {{ nozzleForSnapshot(snapshot.nozzle_id)?.code }}
                  </p>
                  <p class="text-xs text-muted-foreground">
                    Last: {{ formatLiters(nozzleForSnapshot(snapshot.nozzle_id)?.last_closing_reading || 0) }}
                    <span v-if="nozzleForSnapshot(snapshot.nozzle_id)?.last_manual_reading !== null">
                      · manual {{ formatLiters(nozzleForSnapshot(snapshot.nozzle_id)?.last_manual_reading || 0) }}
                    </span>
                  </p>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">Auto meter</Label>
                  <Input v-model.number="snapshot.electronic_reading" type="number" min="0" step="0.01" />
                  <InputError :message="snapshotError(index, 'electronic_reading')" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">Manual meter</Label>
                  <Input v-model.number="snapshot.manual_reading" type="number" min="0" step="0.01" />
                  <InputError :message="snapshotError(index, 'manual_reading')" />
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              rows="3"
              placeholder="Optional note for the change (e.g., government notification #, effective time)."
              :class="{ 'border-destructive': form.errors.notes }"
            />
            <InputError :message="form.errors.notes" />
          </div>

          </div>

          <DialogFooter class="mt-4 shrink-0 gap-2 border-t pt-4">
            <Button type="button" variant="outline" :disabled="form.processing" @click="closeDialog">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Save rate
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
