<script setup lang="ts">
/**
 * Products & stock (StationProductsController@index): everything the station sells with its
 * price, cost, margin and what is on hand. Fuels are on hand as the tank dip at the last close;
 * everything else from the stock ledger. New products start here; adjustments and statements
 * open from each row.
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ProductQuickAddDialog from '@/components/ProductQuickAddDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import { MoreHorizontal, Package, Plus, SlidersHorizontal } from 'lucide-vue-next'

type ProductType = 'fuel' | 'open' | 'pack' | 'other'

interface Row {
  id: string
  name: string
  sku: string | null
  type: ProductType
  unit: string | null
  is_active: boolean
  sale_price: number
  cost: number
  margin: number
  on_hand: number
  percent_full: number | null
  value: number
  low_level: number | null
  low: boolean
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  rows: Row[]
  summary: { total: number; low: number }
  period: { from: string; to: string }
  fuelTanks: Array<{ id: string; name: string; code: string; capacity: number | null; linked_item_id: string | null }>
}>()

const base = `/${props.company.slug}`

const search = ref('')
const typeFilter = ref<'all' | ProductType>('all')
const lowOnly = ref(false)
const adding = ref(false)
const deleting = ref<Row | null>(null)
const deleteOpen = computed({
  get: () => deleting.value !== null,
  set: (v: boolean) => { if (!v) deleting.value = null },
})

const typeLabel: Record<ProductType, string> = { fuel: 'Fuel', open: 'Open', pack: 'Pack', other: 'Other' }
const typeOrder: Record<ProductType, number> = { fuel: 0, open: 1, pack: 2, other: 3 }

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.rows
    .filter((r) => (typeFilter.value === 'all' || r.type === typeFilter.value)
      && (!lowOnly.value || r.low)
      && (!q || r.name.toLowerCase().includes(q) || (r.sku ?? '').toLowerCase().includes(q)))
    .sort((a, b) => typeOrder[a.type] - typeOrder[b.type] || a.name.localeCompare(b.name))
})

const columns: RegisterColumn<Row>[] = [
  { key: 'name', label: 'Product', kind: 'text' },
  { key: 'type', label: 'Type', kind: 'text' },
  { key: 'unit', label: 'Unit', kind: 'text' },
  { key: 'sale_price', label: 'Sale price', kind: 'amount' },
  { key: 'cost', label: 'Cost', kind: 'amount' },
  { key: 'margin', label: 'Margin', kind: 'amount' },
  { key: 'on_hand', label: 'On hand', kind: 'amount' },
  { key: 'value', label: 'Value at cost', kind: 'amount' },
  { key: 'actions', label: '', kind: 'text' },
]

const qty = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)

const openItem = (r: Row) => router.visit(`${base}/items/${r.id}`)
const statementHref = (r: Row) => `${base}/fuel/reports/stock-statement?item=${r.id}&start_date=${props.period.from}&end_date=${props.period.to}`
const topSellersHref = `${base}/fuel/reports/product-profitability?start_date=${props.period.from}&end_date=${props.period.to}`

const confirmDelete = () => {
  const row = deleting.value
  if (!row) return
  router.delete(`${base}/items/${row.id}`, {
    data: { return_to: 'back' },
    preserveScroll: true,
    only: ['rows', 'summary'],
    onSuccess: () => { deleting.value = null },
  })
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: base },
  { title: 'Products & stock', href: `${base}/fuel/products` },
]
</script>

<template>
  <Head title="Products & stock" />

  <PageShell title="Products & stock" :icon="Package" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.visit(`${base}/stock/adjustment`)"><SlidersHorizontal class="mr-2 h-4 w-4" />Adjust stock</Button>
      <Button @click="adding = true"><Plus class="mr-2 h-4 w-4" />Add product</Button>
    </template>

    <ProductQuickAddDialog v-model:open="adding" :company-slug="company.slug" :fuel-tanks="fuelTanks" />

    <ConfirmDialog
      v-model:open="deleteOpen"
      variant="destructive"
      title="Delete product"
      :description="`Delete ${deleting?.name ?? 'this product'}? A product with stock on hand cannot be deleted.`"
      confirm-text="Delete"
      @confirm="confirmDelete"
    />

    <div class="space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <Button size="sm" :variant="lowOnly ? 'default' : 'outline'" :aria-pressed="lowOnly" @click="lowOnly = !lowOnly">
          Low stock <span class="ml-1.5 tabular-nums" :class="!lowOnly && summary.low > 0 ? 'text-status-attention' : ''">{{ summary.low }}</span>
        </Button>
        <Link :href="topSellersHref" class="text-sm underline-offset-2 hover:underline">Top sellers</Link>
        <div class="ml-auto flex flex-wrap items-center gap-3">
          <Input v-model="search" type="search" placeholder="Search products" class="w-56" />
          <Select v-model="typeFilter">
            <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All</SelectItem>
              <SelectItem value="fuel">Fuel</SelectItem>
              <SelectItem value="open">Open</SelectItem>
              <SelectItem value="pack">Packs</SelectItem>
              <SelectItem value="other">Other</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <LedgerRegister :data="shown" :columns="columns" key-field="id" @row-click="openItem">
        <template #empty>
          <div class="space-y-3 text-center text-sm text-text-secondary">
            <p>{{ rows.length ? 'No products match.' : 'No products yet.' }}</p>
            <Button v-if="!rows.length" size="sm" @click="adding = true"><Plus class="mr-2 h-4 w-4" />Add product</Button>
          </div>
        </template>

        <template #cell-name="{ row }">
          <span class="font-medium">{{ row.name }}</span>
          <span v-if="!row.is_active" class="ml-2 text-xs text-muted-foreground">Inactive</span>
          <div v-if="row.sku" class="text-xs text-muted-foreground">{{ row.sku }}</div>
        </template>
        <template #cell-type="{ row }">{{ typeLabel[row.type] }}</template>
        <template #cell-unit="{ row }"><span class="text-muted-foreground">{{ row.unit ?? '' }}</span></template>
        <template #cell-sale_price="{ row }">
          <MoneyText :amount="row.sale_price" :currency="company.base_currency" :show-currency="false" :fraction-digits="2" />
        </template>
        <template #cell-cost="{ row }">
          <MoneyText :amount="row.cost" :currency="company.base_currency" :show-currency="false" :fraction-digits="2" />
        </template>
        <template #cell-margin="{ row }">
          <MoneyText :amount="row.margin" :currency="company.base_currency" :show-currency="false" :fraction-digits="2" :tone="row.margin < 0 ? 'overdue' : 'default'" />
        </template>
        <template #cell-on_hand="{ row }">
          <span v-if="row.low" class="mr-2 text-xs text-status-attention">
            <Hint side="left">
              Low
              <template #content>At or below {{ qty(row.low_level ?? 0) }} {{ row.unit ?? '' }}.</template>
            </Hint>
          </span>
          <Hint v-if="row.percent_full !== null" side="left">
            {{ qty(row.on_hand) }}
            <template #content>{{ row.percent_full }}% full · tank dip at the last close.</template>
          </Hint>
          <template v-else>{{ qty(row.on_hand) }}</template>
        </template>
        <template #cell-value="{ row }">
          <MoneyText :amount="row.value" :currency="company.base_currency" :show-currency="false" :fraction-digits="0" />
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end" @click.stop @keydown.stop>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="h-8 w-8" :aria-label="`Actions for ${row.name}`"><MoreHorizontal class="h-4 w-4" /></Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @click="router.visit(`${base}/items/${row.id}/edit`)">Edit</DropdownMenuItem>
                <DropdownMenuItem @click="router.visit(`${base}/stock/adjustment?item=${row.id}`)">Adjust stock</DropdownMenuItem>
                <DropdownMenuItem @click="router.visit(statementHref(row))">Statement</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem class="text-status-critical" @click="deleting = row">Delete</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
