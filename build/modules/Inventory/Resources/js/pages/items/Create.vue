<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

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

interface Currency {
  currency_code: string
  is_base: boolean
}

interface TaxRate {
  id: string
  name: string
  code: string
  rate: number
}

interface Account {
  id: string
  code: string
  name: string
}

const props = defineProps<{
  company: CompanyRef
  categories: Category[]
  currencies: Currency[]
  taxRates: TaxRate[]
  incomeAccounts: Account[]
  expenseAccounts: Account[]
  assetAccounts: Account[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Items', href: `/${props.company.slug}/items` },
  { title: 'Create', href: `/${props.company.slug}/items/create` },
]

const form = useForm({
  sku: '',
  name: '',
  description: '',
  item_type: 'product',
  category_id: null,
  unit_of_measure: 'unit',
  track_inventory: true,
  is_purchasable: true,
  is_sellable: true,
  cost_price: 0,
  selling_price: 0,
  currency: props.company.base_currency,
  tax_rate_id: null,
  income_account_id: null,
  expense_account_id: null,
  asset_account_id: null,
  reorder_point: 0,
  reorder_quantity: 0,
  barcode: '',
  is_active: true,
})

const submit = () => {
  form.post(`/${props.company.slug}/items`)
}
</script>

<template>
  <Head title="Create Item" />

  <PageShell
    title="Create Item"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/items`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
          <CardDescription>Item details and identification</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="sku">SKU *</Label>
              <Input
                id="sku"
                v-model="form.sku"
                placeholder="e.g., PROD-001"
                :class="{ 'border-destructive': form.errors.sku }"
              />
              <p v-if="form.errors.sku" class="text-sm text-destructive">{{ form.errors.sku }}</p>
            </div>

            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Item name"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              v-model="form.description"
              placeholder="Item description"
              rows="3"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="item_type">Type *</Label>
              <Select v-model="form.item_type">
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="product">Product</SelectItem>
                  <SelectItem value="service">Service</SelectItem>
                  <SelectItem value="non_inventory">Non-Inventory</SelectItem>
                  <SelectItem value="bundle">Bundle</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="category">Category</Label>
              <Select v-model="form.category_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select category (optional)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="unit">Unit of Measure</Label>
              <Input
                id="unit"
                v-model="form.unit_of_measure"
                placeholder="e.g., unit, kg, hour"
              />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="barcode">Barcode</Label>
            <Input
              id="barcode"
              v-model="form.barcode"
              placeholder="Barcode / UPC / EAN"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Pricing -->
      <Card>
        <CardHeader>
          <CardTitle>Pricing</CardTitle>
          <CardDescription>Cost and selling prices</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="cost_price">Cost Price</Label>
              <Input
                id="cost_price"
                v-model="form.cost_price"
                type="number"
                step="0.01"
                min="0"
              />
            </div>

            <div class="space-y-2">
              <Label for="selling_price">Selling Price</Label>
              <Input
                id="selling_price"
                v-model="form.selling_price"
                type="number"
                step="0.01"
                min="0"
              />
            </div>

            <div class="space-y-2">
              <Label for="currency">Currency</Label>
              <Select v-model="form.currency">
                <SelectTrigger>
                  <SelectValue placeholder="Select currency" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="cur in currencies" :key="cur.currency_code" :value="cur.currency_code">
                    {{ cur.currency_code }} {{ cur.is_base ? '(Base)' : '' }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="tax_rate">Tax Rate</Label>
            <Select v-model="form.tax_rate_id">
              <SelectTrigger class="w-full md:w-1/3">
                <SelectValue placeholder="Select tax rate (optional)" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="rate in taxRates" :key="rate.id" :value="rate.id">
                  {{ rate.name }} ({{ rate.rate }}%)
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <!-- Inventory -->
      <Card v-if="form.item_type === 'product'">
        <CardHeader>
          <CardTitle>Inventory</CardTitle>
          <CardDescription>Stock tracking settings</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center space-x-2">
            <Switch id="track_inventory" v-model:checked="form.track_inventory" />
            <Label for="track_inventory">Track inventory for this item</Label>
          </div>

          <div v-if="form.track_inventory" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="reorder_point">Reorder Point</Label>
              <Input
                id="reorder_point"
                v-model="form.reorder_point"
                type="number"
                step="0.001"
                min="0"
              />
              <p class="text-sm text-muted-foreground">Alert when stock falls below this level</p>
            </div>

            <div class="space-y-2">
              <Label for="reorder_quantity">Reorder Quantity</Label>
              <Input
                id="reorder_quantity"
                v-model="form.reorder_quantity"
                type="number"
                step="0.001"
                min="0"
              />
              <p class="text-sm text-muted-foreground">Suggested order quantity when reordering</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Settings</CardTitle>
          <CardDescription>Item availability options</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center space-x-2">
            <Switch id="is_sellable" v-model:checked="form.is_sellable" />
            <Label for="is_sellable">Available for sale (appears on invoices)</Label>
          </div>

          <div class="flex items-center space-x-2">
            <Switch id="is_purchasable" v-model:checked="form.is_purchasable" />
            <Label for="is_purchasable">Available for purchase (appears on bills)</Label>
          </div>

          <div class="flex items-center space-x-2">
            <Switch id="is_active" v-model:checked="form.is_active" />
            <Label for="is_active">Active</Label>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/items`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Item' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
