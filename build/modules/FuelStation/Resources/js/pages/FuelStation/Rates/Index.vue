<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
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
import type { BreadcrumbItem } from '@/types'
import { CalendarClock, Droplet, Plus, TrendingUp } from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface RateChangeRow {
  id: string
  item_id: string
  effective_date: string
  purchase_rate: number
  sale_rate: number
  stock_quantity_at_change?: number | null
  margin_impact?: number | null
  notes?: string | null
  item?: FuelItemRef | null
}

const props = defineProps<{
  rates: RateChangeRow[]
  items: FuelItemRef[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

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
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currencyCode.value,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount ?? 0)
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
      return { item, rate: r }
    })
    .sort((a, b) => (a.item.name || '').localeCompare(b.item.name || ''))
})

const itemFilter = ref<string>('all')
const filteredRates = computed(() => {
  if (itemFilter.value === 'all') return props.rates
  return props.rates.filter((r) => r.item_id === itemFilter.value)
})

const columns = [
  { key: 'effective_date', label: 'Effective' },
  { key: 'item', label: 'Fuel item' },
  { key: 'purchase_rate', label: 'Supplier purchase' },
  { key: 'sale_rate', label: 'Govt sale' },
  { key: 'margin', label: 'Spread' },
  { key: 'impact', label: 'Impact' },
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
  notes: string
}>({
  item_id: '',
  effective_date: new Date().toISOString().slice(0, 10),
  purchase_rate: null,
  sale_rate: null,
  stock_quantity_at_change: null,
  notes: '',
})

const currentRateForSelectedItem = computed(() => {
  if (!form.item_id) return null
  return byItemCurrent.value.get(form.item_id) ?? null
})

const prefillFromCurrent = () => {
  const current = currentRateForSelectedItem.value
  if (!current) return

  if (form.purchase_rate === null) form.purchase_rate = Number(current.purchase_rate)
  if (form.sale_rate === null) form.sale_rate = Number(current.sale_rate)
}

const submit = () => {
  const slug = companySlug.value
  if (!slug) return

  form.post(`/${slug}/fuel/rates`, {
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
        v-for="{ item, rate } in currentCards"
        :key="item.id"
        class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10"
      >
        <CardHeader class="pb-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <CardTitle class="flex items-center gap-2 text-base">
                <Droplet class="h-4 w-4 text-sky-600" />
                {{ item.name }}
              </CardTitle>
              <CardDescription class="mt-1">
                <span v-if="item.fuel_category">Category: {{ item.fuel_category }}</span>
                <span v-else>Fuel item</span>
              </CardDescription>
            </div>
            <Badge v-if="rate" class="bg-emerald-600 text-white hover:bg-emerald-600">Current</Badge>
            <Badge v-else variant="secondary" class="bg-zinc-200 text-zinc-800 hover:bg-zinc-200">No rate</Badge>
          </div>
        </CardHeader>

        <CardContent class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-border/70 bg-white/50 p-3">
              <p class="text-xs font-medium text-text-tertiary">Supplier purchase (reference)</p>
              <p class="mt-1 text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(rate.purchase_rate)} / L` : '—' }}
              </p>
            </div>
            <div class="rounded-lg border border-border/70 bg-white/50 p-3">
              <p class="text-xs font-medium text-text-tertiary">Govt sale (OGRA)</p>
              <p class="mt-1 text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(rate.sale_rate)} / L` : '—' }}
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/40 px-3 py-2">
            <div class="flex items-center gap-2">
              <Badge variant="secondary" class="bg-sky-100 text-sky-800 hover:bg-sky-100">
                Spread
              </Badge>
              <span class="text-sm font-semibold text-text-primary">
                {{ rate ? `${formatMoney(spreadFor(rate))} / L` : '—' }}
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-text-secondary">
              <CalendarClock class="h-4 w-4 text-text-tertiary" />
              <span>{{ rate ? `${formatEffectiveDate(rate.effective_date)} (from 00:00)` : 'No effective date' }}</span>
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
        <DataTable :data="tableData" :columns="columns" striped>
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
                class="bg-emerald-600 text-white hover:bg-emerald-600"
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
                class="bg-sky-100 text-sky-800 hover:bg-sky-100"
              >
                {{ row._raw.item.fuel_category }}
              </Badge>
            </div>
          </template>

          <template #cell-margin="{ row }">
            <Badge
              :class="spreadFor(row._raw) >= 0 ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100' : 'bg-red-100 text-red-800 hover:bg-red-100'"
            >
              {{ row.margin }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <TrendingUp class="h-5 w-5 text-sky-600" />
            Add Rate Change
          </DialogTitle>
          <DialogDescription>
            Add the OGRA (govt) sale rate for the effective date (enforced from 00:00). Supplier purchase here is a reference rate for new deliveries.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="item_id">Fuel item</Label>
              <Select v-model="form.item_id" @update:modelValue="prefillFromCurrent">
                <SelectTrigger id="item_id" :class="{ 'border-destructive': form.errors.item_id }">
                  <SelectValue placeholder="Select fuel item..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="item in items" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.item_id" class="text-sm text-destructive">{{ form.errors.item_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="effective_date">Effective date (from 00:00)</Label>
              <Input
                id="effective_date"
                v-model="form.effective_date"
                type="date"
                :class="{ 'border-destructive': form.errors.effective_date }"
              />
              <p v-if="form.errors.effective_date" class="text-sm text-destructive">{{ form.errors.effective_date }}</p>
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
              <p v-if="form.errors.purchase_rate" class="text-sm text-destructive">{{ form.errors.purchase_rate }}</p>
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
              <p v-if="form.errors.sale_rate" class="text-sm text-destructive">{{ form.errors.sale_rate }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-border/70 bg-muted/30 p-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-text-primary">Optional margin impact</p>
                <p class="text-sm text-text-secondary">
                  Enter stock on hand at the time of the change to estimate impact. This does not update inventory or accounting.
                </p>
              </div>
              <Badge variant="outline" class="border-sky-200 text-sky-700">
                {{ currencyCode }}
              </Badge>
            </div>
            <div class="mt-3 space-y-2">
              <Label for="stock_quantity_at_change">Stock on hand (liters)</Label>
              <Input
                id="stock_quantity_at_change"
                v-model.number="form.stock_quantity_at_change"
                type="number"
                min="0"
                step="0.01"
                placeholder="Leave empty to skip"
                :class="{ 'border-destructive': form.errors.stock_quantity_at_change }"
              />
              <p v-if="form.errors.stock_quantity_at_change" class="text-sm text-destructive">
                {{ form.errors.stock_quantity_at_change }}
              </p>
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
            <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
          </div>

          <DialogFooter class="gap-2">
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
