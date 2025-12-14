<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { Pencil, ArrowLeft, Package, Warehouse } from 'lucide-vue-next'

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

const props = defineProps<{
  company: CompanyRef
  item: Item
  stockLevels: StockLevelRow[]
  summary: {
    total_quantity: number
    total_available: number
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Items', href: `/${props.company.slug}/items` },
  { title: props.item.name, href: `/${props.company.slug}/items/${props.item.id}` },
]

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
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
      <Button @click="router.get(`/${company.slug}/items/${item.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Info -->
      <div class="lg:col-span-2 space-y-6">
        <Card>
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

        <!-- Stock Levels -->
        <Card v-if="item.track_inventory">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Warehouse class="h-5 w-5" />
              Stock by Location
            </CardTitle>
          </CardHeader>
          <CardContent>
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
        <Card>
          <CardHeader>
            <CardTitle>Pricing</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Cost Price</span>
              <span class="font-medium">{{ formatCurrency(item.cost_price, item.currency) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Selling Price</span>
              <span class="font-medium text-lg">{{ formatCurrency(item.selling_price, item.currency) }}</span>
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
        <Card v-if="item.track_inventory">
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
        <Card>
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
