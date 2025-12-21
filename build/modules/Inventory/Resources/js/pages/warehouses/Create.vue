<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface ItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

const props = defineProps<{
  company: CompanyRef
  fuelItems?: ItemRef[] // For tank selection
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Warehouses', href: `/${props.company.slug}/warehouses` },
  { title: 'Create', href: `/${props.company.slug}/warehouses/create` },
]

const form = useForm({
  code: '',
  name: '',
  warehouse_type: 'standard',
  capacity: null,
  low_level_alert: null,
  linked_item_id: '',
  address: '',
  city: '',
  state: '',
  postal_code: '',
  country_code: '',
  is_primary: false,
  is_active: true,
  notes: '',
})

const warehouseTypes = [
  { value: 'standard', label: 'Standard Warehouse', description: 'Regular storage facility' },
  { value: 'tank', label: 'Fuel Tank', description: 'Underground or aboveground fuel storage tank' }
]

const fuelItemOptions = computed(() => {
  if (!props.fuelItems) return []
  return props.fuelItems.map(item => ({
    value: item.id,
    label: item.name,
    category: item.fuel_category
  }))
})

const submit = () => {
  form.post(`/${props.company.slug}/warehouses`)
}
</script>

<template>
  <Head title="Create Warehouse" />

  <PageShell
    title="Create Warehouse"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/warehouses`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
          <CardDescription>Warehouse identification</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input
                id="code"
                v-model="form.code"
                placeholder="e.g., WH-MAIN, TK-001"
                :class="{ 'border-destructive': form.errors.code }"
              />
              <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
            </div>

            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Warehouse name"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="warehouse_type">Warehouse Type *</Label>
            <Select v-model="form.warehouse_type">
              <SelectTrigger id="warehouse_type" :class="{ 'border-destructive': form.errors.warehouse_type }">
                <SelectValue placeholder="Select warehouse type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="type in warehouseTypes" :key="type.value" :value="type.value">
                  <div>
                    <div class="font-medium">{{ type.label }}</div>
                    <div class="text-sm text-muted-foreground">{{ type.description }}</div>
                  </div>
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.warehouse_type" class="text-sm text-destructive">{{ form.errors.warehouse_type }}</p>
          </div>

          <!-- Tank-specific fields -->
          <div v-if="form.warehouse_type === 'tank'" class="space-y-4 rounded-lg border border-sky-200 bg-sky-50 p-4">
            <div class="flex items-center gap-2">
              <div class="h-2 w-2 rounded-full bg-sky-600"></div>
              <h4 class="font-medium text-sky-900">Tank Configuration</h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="capacity">Capacity (Liters) *</Label>
                <Input
                  id="capacity"
                  v-model.number="form.capacity"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="e.g., 10000"
                  :class="{ 'border-destructive': form.errors.capacity }"
                />
                <p v-if="form.errors.capacity" class="text-sm text-destructive">{{ form.errors.capacity }}</p>
              </div>

              <div class="space-y-2">
                <Label for="low_level_alert">Low Level Alert (Liters)</Label>
                <Input
                  id="low_level_alert"
                  v-model.number="form.low_level_alert"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="e.g., 1000"
                  :class="{ 'border-destructive': form.errors.low_level_alert }"
                />
                <p v-if="form.errors.low_level_alert" class="text-sm text-destructive">{{ form.errors.low_level_alert }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="linked_item_id">Linked Fuel Item *</Label>
              <Select v-model="form.linked_item_id" :disabled="!fuelItemOptions.length">
                <SelectTrigger id="linked_item_id" :class="{ 'border-destructive': form.errors.linked_item_id }">
                  <SelectValue :placeholder="fuelItemOptions.length ? 'Select fuel item...' : 'No fuel items available'" />
                </SelectTrigger>
                <SelectContent v-if="fuelItemOptions.length">
                  <SelectItem v-for="item in fuelItemOptions" :key="item.value" :value="item.value">
                    <div class="flex items-center gap-2">
                      <span>{{ item.label }}</span>
                      <span v-if="item.category" class="rounded bg-sky-100 px-2 py-1 text-xs text-sky-700">
                        {{ item.category }}
                      </span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-sm text-muted-foreground">
                Select the fuel item stored in this tank. Each tank stores one type of fuel.
              </p>
              <p v-if="form.errors.linked_item_id" class="text-sm text-destructive">{{ form.errors.linked_item_id }}</p>
              <div v-if="!fuelItemOptions.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                <p class="text-sm text-amber-800">
                  <strong>No fuel items found.</strong> Please create fuel items first to link with tanks.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Address -->
      <Card>
        <CardHeader>
          <CardTitle>Address</CardTitle>
          <CardDescription>Warehouse location</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="address">Street Address</Label>
            <Textarea
              id="address"
              v-model="form.address"
              placeholder="Street address"
              rows="2"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="city">City</Label>
              <Input id="city" v-model="form.city" placeholder="City" />
            </div>

            <div class="space-y-2">
              <Label for="state">State / Province</Label>
              <Input id="state" v-model="form.state" placeholder="State" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="postal_code">Postal Code</Label>
              <Input id="postal_code" v-model="form.postal_code" placeholder="Postal code" />
            </div>

            <div class="space-y-2">
              <Label for="country_code">Country Code</Label>
              <Input id="country_code" v-model="form.country_code" placeholder="e.g., SA, US" maxlength="2" />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Settings</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center space-x-2">
            <Switch id="is_primary" v-model="form.is_primary" />
            <Label for="is_primary">Set as primary warehouse</Label>
          </div>
          <p class="text-sm text-muted-foreground">Primary warehouse is used as default for new stock movements</p>

          <div class="flex items-center space-x-2">
            <Switch id="is_active" v-model="form.is_active" />
            <Label for="is_active">Active</Label>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Additional notes"
              rows="3"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/warehouses`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Warehouse' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
