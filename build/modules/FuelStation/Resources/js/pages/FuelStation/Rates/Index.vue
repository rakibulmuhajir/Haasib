<script setup lang="ts">
import { localToday } from '@/composables/useEntryDate'
import { computed, ref, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import EmptyState from '@/components/EmptyState.vue'
import Hint from '@/components/Hint.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import { Plus, TrendingUp } from 'lucide-vue-next'
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
  // Price each fuel was last bought at (latest bill line) - where a new purchase rate starts.
  lastPurchasePrices?: Record<string, { rate: number; bill_number: string; bill_date: string }>
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
  { title: 'Fuel prices', href: `/${companySlug.value}/fuel/rates` },
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
  { key: 'item', label: 'Fuel', kind: 'text' as const },
  { key: 'purchase_rate', label: 'Purchase', kind: 'amount' as const },
  { key: 'sale_rate', label: 'Sale', kind: 'amount' as const },
  { key: 'margin', label: 'Margin', kind: 'amount' as const },
  { key: 'impact', label: 'Impact', kind: 'amount' as const },
]

const tableData = computed(() =>
  filteredRates.value
    .slice()
    .sort((a, b) => new Date(b.effective_date).getTime() - new Date(a.effective_date).getTime())
    .map((r) => ({
      id: r.id,
      effective_date: formatEffectiveDate(r.effective_date),
      item: r.item?.name ?? props.items.find((i) => i.id === r.item_id)?.name ?? '—',
      purchase_rate: formatMoney(r.purchase_rate),
      sale_rate: formatMoney(r.sale_rate),
      margin: formatMoney(spreadFor(r)),
      impact: r.stock_quantity_at_change ? `${formatMoney(r.margin_impact ?? 0)} @ ${formatLiters(r.stock_quantity_at_change)}L` : '—',
      _raw: r,
      _isCurrent: byItemCurrent.value.get(r.item_id)?.id === r.id,
    }))
)

const currentColumns = [
  { key: 'fuel', label: 'Fuel', kind: 'text' as const },
  { key: 'sale', label: 'Sale', kind: 'amount' as const },
  { key: 'purchase', label: 'Purchase', kind: 'amount' as const },
  { key: 'margin', label: 'Margin', kind: 'amount' as const },
  { key: 'since', label: 'Since', kind: 'text' as const },
]
const currentRows = computed(() =>
  currentCards.value.map((c) => ({
    id: c.item.id,
    fuel: c.item.name,
    sale: c.rate ? `${formatMoney(c.rate.sale_rate)} / L` : '—',
    purchase: c.rate ? `${formatMoney(c.rate.purchase_rate)} / L` : '—',
    margin: c.rate ? `${formatMoney(spreadFor(c.rate))} / L` : '—',
    since: !c.rate ? 'No rate' : c.source === 'history' ? formatEffectiveDate(c.rate.effective_date) : 'Product setup',
    _negative: c.rate ? spreadFor(c.rate) < 0 : false,
    _card: c,
  })),
)

const dialogOpen = ref(false)
const openCreate = () => {
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

// A saved rate opens in the same form, filled with what was saved; saving the same fuel and
// date replaces it (RateChangeService updates the day's row).
const openEdit = (row: { _raw: RateChangeRow }) => {
  form.reset()
  form.clearErrors()
  form.effective_date = String(row._raw.effective_date).slice(0, 10)
  form.item_id = row._raw.item_id
  form.purchase_rate = Number(row._raw.purchase_rate)
  form.sale_rate = Number(row._raw.sale_rate)
  dialogOpen.value = true
}

// A current-rate line opens that fuel's saved rate; a fuel with only product-setup prices
// starts a new rate change for it.
const openCurrent = (c: { item: FuelItemRef; rate: RateChangeRow | null; source: string }) => {
  if (c.rate && c.source === 'history') return openEdit({ _raw: c.rate })
  openCreate()
  form.item_id = c.item.id
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
  effective_date: localToday(),
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

const lastPurchase = computed(() => (form.item_id ? props.lastPurchasePrices?.[form.item_id] ?? null : null))

// A rate already saved for this fuel on this date: editing it starts from what was saved.
const savedForDate = computed(() =>
  form.item_id && form.effective_date
    ? props.rates.find((r) => r.item_id === form.item_id && String(r.effective_date).slice(0, 10) === form.effective_date) ?? null
    : null,
)

const prefillFromCurrent = () => {
  if (savedForDate.value) {
    form.purchase_rate = Number(savedForDate.value.purchase_rate)
    form.sale_rate = Number(savedForDate.value.sale_rate)
    return
  }
  // The last bill's price, not the purchase rate on the last rate change (that one goes stale
  // as soon as a delivery comes in at a different price).
  if (form.purchase_rate === null && lastPurchase.value) form.purchase_rate = Number(lastPurchase.value.rate)
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
  // Another fuel: start from its own figures, never the previous fuel's.
  form.purchase_rate = null
  form.sale_rate = null
  prefillFromCurrent()
  syncSnapshotRows()
})

watch(() => form.effective_date, () => {
  if (savedForDate.value) prefillFromCurrent()
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
  <Head title="Fuel prices" />

  <PageShell title="Fuel prices" :icon="TrendingUp" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        Add rate change
      </Button>
    </template>

    <div class="space-y-4">
      <section>
        <h2 class="mb-1 text-xs text-muted-foreground">
          <Hint>
            Current rates
            <template #content>Govt (OGRA) sale rate from 00:00 of its date. Purchase rate is a reference for new deliveries; actual cost comes from bills. Click a line to edit.</template>
          </Hint>
        </h2>
        <!-- One line per fuel, banded like the history below; click a line to edit it. -->
        <LedgerRegister :data="currentRows" :columns="currentColumns" banded @row-click="(row: any) => openCurrent(row._card)">
          <template #empty>
            <div class="px-3 py-3 text-muted-foreground">No fuels yet.</div>
          </template>
          <template #cell-margin="{ row }">
            <span :class="row._negative ? 'text-status-attention' : ''">{{ row.margin }}</span>
          </template>
          <template #cell-since="{ row }">
            <span class="text-muted-foreground">{{ row.since }}</span>
          </template>
        </LedgerRegister>
      </section>

      <h2 class="mb-1 text-xs text-muted-foreground">Rate history</h2>
      <div class="flex flex-wrap items-end gap-3">
        <div class="grid gap-1.5">
          <Label>Fuel</Label>
          <Select v-model="itemFilter">
            <SelectTrigger class="w-48">
              <SelectValue placeholder="All fuels" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All fuels</SelectItem>
              <SelectItem v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <LedgerRegister :data="tableData" :columns="columns" banded @row-click="openEdit">
        <template #empty>
          <EmptyState title="No rate changes yet" description="Add a rate change to start the history.">
            <template #actions>
              <Button @click="openCreate"><Plus class="mr-2 h-4 w-4" />Add rate</Button>
            </template>
          </EmptyState>
        </template>

        <template #cell-effective_date="{ row }">
          <div class="flex items-center gap-2">
            <Badge v-if="row._isCurrent" class="bg-status-success text-status-success-contrast hover:bg-status-success">Current</Badge>
            <span>{{ row.effective_date }}</span>
          </div>
        </template>

        <template #cell-margin="{ row }">
          <span :class="spreadFor(row._raw) < 0 ? 'text-status-attention' : ''">{{ row.margin }}</span>
        </template>
      </LedgerRegister>
    </div>

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
              <p v-if="lastPurchase" class="text-xs text-muted-foreground">
                Last purchase {{ formatMoney(lastPurchase.rate) }} / L on {{ lastPurchase.bill_number }} ({{ lastPurchase.bill_date }}).
              </p>
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
