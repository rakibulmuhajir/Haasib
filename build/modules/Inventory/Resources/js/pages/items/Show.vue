<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { Pencil, ArrowLeft, Warehouse, Trash2, PackagePlus } from 'lucide-vue-next'
import MoneyText from '@/components/MoneyText.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import Hint from '@/components/Hint.vue'
import InputError from '@/components/InputError.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { MoreHorizontal, Plus } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Category {
  id: string
  name: string
  code: string
}

interface TaxRate {
  id: string
  name: string
  code: string
  rate: number
}

interface StockLevelRow {
  id: string
  warehouse: {
    id: string
    name: string
    code: string
  }
  quantity: number
  reserved_quantity: number
  available_quantity: number
}

interface Item {
  id: string
  sku: string
  name: string
  description: string | null
  item_type: string
  category: Category | null
  tax_rate: TaxRate | null
  unit_of_measure: string
  track_inventory: boolean
  is_purchasable: boolean
  is_sellable: boolean
  cost_price: number
  selling_price: number
  currency: string
  reorder_point: number
  reorder_quantity: number
  barcode: string | null
  is_active: boolean
  created_at: string
}

interface PriceRow {
  id: string
  date: string
  kind: 'sale_price' | 'fuel_rate' | 'purchase_bill' | 'month_correction'
  label: string
  price: number
  quantity: number | null
  detail: string | null
  source_label: string | null
  source_url: string | null
  in_force: boolean
  editable: boolean
  price_id: string | null
  purchase_price: number | null
  notes: string | null
}

interface PriceChange {
  id: string
  changed_at: string | null
  user: string | null
  text: string
  purchase: string | null
  note: string | null
}

const changesOpen = ref(false)
const formatWhen = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''

const props = defineProps<{
  company: CompanyRef
  item: Item
  priceHistory: { rows: PriceRow[]; is_fuel: boolean; changes?: PriceChange[] }
  stockLevels: StockLevelRow[]
  pendingReceiptsCount: number
  pendingReceiptsQuantity: number
  summary: {
    total_quantity: number
    total_available: number
  }
}>()

const { t } = useLexicon()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Items', href: `/${props.company.slug}/items` },
  { title: props.item.name, href: `/${props.company.slug}/items/${props.item.id}` },
]

const confirmOpen = ref(false)
const deleting = ref(false)

const confirmDelete = () => {
  deleting.value = true
  router.delete(`/${props.company.slug}/items/${props.item.id}`, {
    onFinish: () => {
      deleting.value = false
      confirmOpen.value = false
    },
  })
}

const priceColumns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'label', label: 'What', kind: 'text' as const },
  { key: 'price', label: 'Price', kind: 'amount' as const },
  { key: 'detail', label: 'Detail', kind: 'text' as const },
  { key: 'source', label: 'Source', kind: 'text' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]

const priceOpen = ref(false)
const editingPriceId = ref<string | null>(null)
const priceForm = useForm({ effective_date: '', sale_price: '', purchase_price: '', notes: '' })

const openAddPrice = () => {
  editingPriceId.value = null
  priceForm.reset()
  priceForm.clearErrors()
  priceForm.effective_date = new Date().toISOString().slice(0, 10)
  priceOpen.value = true
}

const openEditPrice = (row: PriceRow) => {
  editingPriceId.value = row.price_id
  priceForm.clearErrors()
  priceForm.effective_date = row.date
  priceForm.sale_price = String(row.price)
  priceForm.purchase_price = row.purchase_price === null ? '' : String(row.purchase_price)
  priceForm.notes = row.notes ?? ''
  priceOpen.value = true
}

const savePrice = () => {
  priceForm.post(`/${props.company.slug}/items/${props.item.id}/prices`, {
    preserveScroll: true,
    onSuccess: () => {
      priceOpen.value = false
    },
  })
}

const priceToDelete = ref<PriceRow | null>(null)
const priceDeleteOpen = ref(false)
const priceDeleting = ref(false)

const askDeletePrice = (row: PriceRow) => {
  priceToDelete.value = row
  priceDeleteOpen.value = true
}

const confirmDeletePrice = () => {
  if (!priceToDelete.value?.price_id) return
  priceDeleting.value = true
  router.delete(`/${props.company.slug}/items/${props.item.id}/prices/${priceToDelete.value.price_id}`, {
    preserveScroll: true,
    onFinish: () => {
      priceDeleting.value = false
      priceDeleteOpen.value = false
    },
  })
}

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const getTypeBadgeVariant = (type: string) => {
  switch (type) {
    case 'product':
      return 'default'
    case 'service':
      return 'secondary'
    case 'non_inventory':
      return 'outline'
    case 'bundle':
      return 'default'
    default:
      return 'secondary'
  }
}
</script>

<template>
  <Head :title="item.name" />

  <PageShell
    :title="item.name"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/items`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button
        v-if="pendingReceiptsCount > 0"
        variant="outline"
        @click="router.get(`/${company.slug}/bills?item_id=${item.id}&needs_receiving=1`)"
      >
        {{ t('receiveStock') }}
      </Button>
      <Button v-if="item.track_inventory" variant="outline" @click="router.get(`/${company.slug}/stock/adjustment?item=${item.id}`)">
        <PackagePlus class="mr-2 h-4 w-4" />
        Adjust stock
      </Button>
      <Button @click="router.get(`/${company.slug}/items/${item.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button variant="outline" class="text-destructive" @click="confirmOpen = true">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </Button>
    </template>

    <ConfirmDialog
      v-model:open="confirmOpen"
      variant="destructive"
      title="Delete item?"
      :description="item.name"
      confirm-text="Delete"
      :loading="deleting"
      @confirm="confirmDelete"
    />

    <ConfirmDialog
      v-model:open="priceDeleteOpen"
      variant="destructive"
      title="Delete price?"
      :description="priceToDelete ? `${priceToDelete.date} · ${priceToDelete.price}` : ''"
      confirm-text="Delete"
      :loading="priceDeleting"
      @confirm="confirmDeletePrice"
    />

    <Dialog v-model:open="changesOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader><DialogTitle>Price changes</DialogTitle></DialogHeader>
        <ul class="max-h-[60vh] space-y-3 overflow-y-auto">
          <li v-for="change in priceHistory.changes" :key="change.id" class="border-b border-border pb-3 text-sm last:border-0">
            <div class="text-xs text-muted-foreground">{{ formatWhen(change.changed_at) }} · {{ change.user || 'Unknown' }}</div>
            <div class="font-medium">{{ change.text }}</div>
            <div v-if="change.purchase" class="text-xs text-muted-foreground">{{ change.purchase }}</div>
            <div v-if="change.note" class="text-xs text-muted-foreground">Note: {{ change.note }}</div>
          </li>
        </ul>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="priceOpen">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader><DialogTitle>{{ editingPriceId ? 'Change price' : 'Add price' }}</DialogTitle></DialogHeader>
        <div class="space-y-3">
          <div class="space-y-1.5">
            <Label for="price-date">
              <Hint>From<template #content>The price applies from this date until a later one. A date already priced is replaced.</template></Hint>
            </Label>
            <Input id="price-date" v-model="priceForm.effective_date" type="date" />
            <InputError :message="priceForm.errors.effective_date" />
          </div>
          <div class="space-y-1.5">
            <Label for="price-sale">Sale price</Label>
            <Input id="price-sale" v-model="priceForm.sale_price" type="number" step="0.01" min="0" class="text-right tabular-nums" />
            <InputError :message="priceForm.errors.sale_price" />
          </div>
          <div class="space-y-1.5">
            <Label for="price-purchase">
              <Hint>Purchase price (optional)<template #content>For reference only. Stock cost still comes from bills.</template></Hint>
            </Label>
            <Input id="price-purchase" v-model="priceForm.purchase_price" type="number" step="0.01" min="0" class="text-right tabular-nums" />
            <InputError :message="priceForm.errors.purchase_price" />
          </div>
          <div class="space-y-1.5">
            <Label for="price-notes">Note</Label>
            <Input id="price-notes" v-model="priceForm.notes" maxlength="255" />
            <InputError :message="priceForm.errors.notes" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="priceOpen = false">Cancel</Button>
          <Button :disabled="priceForm.processing || !priceForm.effective_date || priceForm.sale_price === ''" @click="savePrice">Save</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Info -->
      <div class="lg:col-span-2 space-y-6">
        <Card variant="detail">
          <CardHeader>
            <div class="flex items-center justify-between">
              <div>
                <CardTitle class="flex items-center gap-2">
                  {{ item.name }}
                  <Badge :variant="getTypeBadgeVariant(item.item_type)">
                    {{ item.item_type }}
                  </Badge>
                </CardTitle>
                <CardDescription>SKU: {{ item.sku }}</CardDescription>
              </div>
              <Badge :variant="item.is_active ? 'success' : 'secondary'">
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <p v-if="item.description" class="text-muted-foreground">{{ item.description }}</p>

            <Separator />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <p class="text-muted-foreground">Category</p>
                <p class="font-medium">{{ item.category?.name ?? '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Unit</p>
                <p class="font-medium">{{ item.unit_of_measure }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Barcode</p>
                <p class="font-medium">{{ item.barcode || '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Tax Rate</p>
                <p class="font-medium">{{ item.tax_rate ? `${item.tax_rate.name} (${item.tax_rate.rate}%)` : '-' }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Price history -->
        <Card variant="detail">
          <CardHeader>
            <div class="flex items-center justify-between gap-2">
              <CardTitle>
                <Hint>Price history<template #content>A price runs from its date until the next one. Months with a locked daily close cannot be changed.</template></Hint>
              </CardTitle>
              <div class="flex items-center gap-2">
              <Button v-if="!priceHistory.is_fuel && priceHistory.changes?.length" variant="link" size="sm" @click="changesOpen = true">
                Changes
              </Button>
              <Button v-if="priceHistory.is_fuel" variant="outline" size="sm" @click="router.get(`/${company.slug}/fuel/rates`)">
                Fuel prices
              </Button>
              <Button v-else size="sm" @click="openAddPrice">
                <Plus class="mr-2 h-4 w-4" />
                Add price
              </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <LedgerRegister :data="priceHistory.rows" :columns="priceColumns" key-field="id">
              <template #empty>No prices yet.</template>
              <template #cell-label="{ row }">
                <span>{{ row.label }}</span>
                <Badge v-if="row.in_force" variant="success" class="ml-2">In force</Badge>
              </template>
              <template #cell-price="{ row }">
                <MoneyText :amount="row.price" :currency="item.currency" />
                <span v-if="row.quantity !== null" class="ml-1 text-xs text-muted-foreground">× {{ formatQuantity(row.quantity) }}</span>
              </template>
              <template #cell-detail="{ row }">{{ row.detail ?? row.notes ?? '' }}</template>
              <template #cell-source="{ row }">
                <a v-if="row.source_url" :href="row.source_url" class="underline" @click.prevent="router.get(row.source_url)">{{ row.source_label }}</a>
              </template>
              <template #cell-actions="{ row }">
                <div v-if="row.kind === 'sale_price' && row.editable" class="flex justify-end">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon" aria-label="Price actions"><MoreHorizontal class="h-4 w-4" /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem @click="openEditPrice(row)"><Pencil class="mr-2 h-4 w-4" />Edit</DropdownMenuItem>
                      <DropdownMenuItem class="text-destructive" @click="askDeletePrice(row)"><Trash2 class="mr-2 h-4 w-4" />Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </template>
            </LedgerRegister>
          </CardContent>
        </Card>

        <!-- Stock Levels -->
        <Card v-if="item.track_inventory" variant="detail">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Warehouse class="h-5 w-5" />
              Stock by Location
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div
              v-if="pendingReceiptsCount > 0"
              class="mb-4 rounded-md border border-dashed bg-muted/40 p-3 text-sm"
            >
              <p class="text-xs text-muted-foreground">{{ t('expectedInbound') }}</p>
              <div class="mt-1 flex items-baseline justify-between">
                <p class="text-base font-semibold">
                  {{ formatQuantity(pendingReceiptsQuantity) }} {{ item.unit_of_measure }}
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ pendingReceiptsCount }} {{ t('bills') }}
                </p>
              </div>
            </div>
            <div v-if="stockLevels.length === 0" class="text-center py-8 text-muted-foreground">
              No stock recorded yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="level in stockLevels"
                :key="level.id"
                class="flex items-center justify-between py-2 border-b last:border-0"
              >
                <div>
                  <p class="font-medium">{{ level.warehouse.name }}</p>
                  <p class="text-sm text-muted-foreground">{{ level.warehouse.code }}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium">{{ formatQuantity(level.quantity) }} {{ item.unit_of_measure }}</p>
                  <p v-if="level.reserved_quantity > 0" class="text-sm text-muted-foreground">
                    {{ formatQuantity(level.available_quantity) }} available
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Pricing Card -->
        <Card variant="detail">
          <CardHeader>
            <CardTitle>Pricing</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Cost Price</span>
              <span class="font-medium"><MoneyText :amount="item.cost_price" :currency="item.currency" /></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Selling Price</span>
              <span class="font-medium text-lg"><MoneyText :amount="item.selling_price" :currency="item.currency" /></span>
            </div>
            <Separator />
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Margin</span>
              <span class="font-medium">
                {{ item.cost_price > 0 ? Math.round((item.selling_price - item.cost_price) / item.cost_price * 100) : 0 }}%
              </span>
            </div>
          </CardContent>
        </Card>

        <!-- Stock Summary -->
        <Card v-if="item.track_inventory" variant="detail">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Package class="h-5 w-5" />
              Stock Summary
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Total On Hand</span>
              <span class="font-medium text-lg">{{ formatQuantity(summary.total_quantity) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Available</span>
              <span class="font-medium">{{ formatQuantity(summary.total_available) }}</span>
            </div>
            <Separator />
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Reorder Point</span>
              <span class="font-medium">{{ formatQuantity(item.reorder_point) }}</span>
            </div>
            <div v-if="summary.total_available < item.reorder_point" class="mt-2">
              <Badge variant="destructive">Low Stock</Badge>
            </div>
          </CardContent>
        </Card>

        <!-- Settings -->
        <Card variant="detail">
          <CardHeader>
            <CardTitle>Settings</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Sellable</span>
              <Badge :variant="item.is_sellable ? 'success' : 'secondary'">
                {{ item.is_sellable ? 'Yes' : 'No' }}
              </Badge>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Purchasable</span>
              <Badge :variant="item.is_purchasable ? 'success' : 'secondary'">
                {{ item.is_purchasable ? 'Yes' : 'No' }}
              </Badge>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Track Inventory</span>
              <Badge :variant="item.track_inventory ? 'success' : 'secondary'">
                {{ item.track_inventory ? 'Yes' : 'No' }}
              </Badge>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
