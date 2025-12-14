<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Pencil, ArrowLeft, Star, Package, MapPin } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface StockLevelRow {
  id: string
  item: {
    id: string
    sku: string
    name: string
    unit_of_measure: string
  }
  quantity: number
  reserved_quantity: number
  available_quantity: number
}

interface PaginatedStockLevels {
  data: StockLevelRow[]
  current_page: number
  last_page: number
  total: number
}

interface Warehouse {
  id: string
  code: string
  name: string
  address: string | null
  city: string | null
  state: string | null
  postal_code: string | null
  country_code: string | null
  is_primary: boolean
  is_active: boolean
  notes: string | null
}

const props = defineProps<{
  company: CompanyRef
  warehouse: Warehouse
  stockLevels: PaginatedStockLevels
  summary: {
    item_count: number
    total_units: number
    total_reserved: number
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Warehouses', href: `/${props.company.slug}/warehouses` },
  { title: props.warehouse.name, href: `/${props.company.slug}/warehouses/${props.warehouse.id}` },
]

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const formatAddress = () => {
  const parts = [
    props.warehouse.address,
    props.warehouse.city,
    props.warehouse.state,
    props.warehouse.postal_code,
    props.warehouse.country_code,
  ].filter(Boolean)
  return parts.join(', ')
}
</script>

<template>
  <Head :title="warehouse.name" />

  <PageShell
    :title="warehouse.name"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/warehouses`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="router.get(`/${company.slug}/warehouses/${warehouse.id}/edit`)">
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
                  {{ warehouse.name }}
                  <Star v-if="warehouse.is_primary" class="h-5 w-5 text-yellow-500 fill-yellow-500" />
                </CardTitle>
                <CardDescription>Code: {{ warehouse.code }}</CardDescription>
              </div>
              <Badge :variant="warehouse.is_active ? 'success' : 'secondary'">
                {{ warehouse.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div v-if="formatAddress()" class="flex items-start gap-2 text-muted-foreground">
              <MapPin class="h-4 w-4 mt-1 flex-shrink-0" />
              <span>{{ formatAddress() }}</span>
            </div>
            <p v-if="warehouse.notes" class="mt-4 text-muted-foreground">{{ warehouse.notes }}</p>
          </CardContent>
        </Card>

        <!-- Stock Items -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Package class="h-5 w-5" />
              Stock in Warehouse
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="stockLevels.data.length === 0" class="text-center py-8 text-muted-foreground">
              No items in this warehouse
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="level in stockLevels.data"
                :key="level.id"
                class="flex items-center justify-between py-3 border-b last:border-0 cursor-pointer hover:bg-muted/50 -mx-2 px-2 rounded"
                @click="router.get(`/${company.slug}/items/${level.item.id}`)"
              >
                <div>
                  <p class="font-medium">{{ level.item.name }}</p>
                  <p class="text-sm text-muted-foreground">{{ level.item.sku }}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium">{{ formatQuantity(level.quantity) }} {{ level.item.unit_of_measure }}</p>
                  <p v-if="level.reserved_quantity > 0" class="text-sm text-muted-foreground">
                    {{ formatQuantity(level.available_quantity) }} available
                  </p>
                </div>
              </div>
            </div>

            <div v-if="stockLevels.total > stockLevels.data.length" class="mt-4 text-center">
              <Button variant="outline" size="sm">
                View all {{ stockLevels.total }} items
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Summary -->
        <Card>
          <CardHeader>
            <CardTitle>Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Total Items</span>
              <span class="font-medium text-lg">{{ summary.item_count }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Total Units</span>
              <span class="font-medium text-lg">{{ formatQuantity(summary.total_units) }}</span>
            </div>
            <div v-if="summary.total_reserved > 0" class="flex justify-between items-center">
              <span class="text-muted-foreground">Reserved</span>
              <span class="font-medium">{{ formatQuantity(summary.total_reserved) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Quick Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <Button
              variant="outline"
              class="w-full justify-start"
              @click="router.get(`/${company.slug}/stock/adjustment`)"
            >
              Stock Adjustment
            </Button>
            <Button
              variant="outline"
              class="w-full justify-start"
              @click="router.get(`/${company.slug}/stock/transfer`)"
            >
              Transfer Stock
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
