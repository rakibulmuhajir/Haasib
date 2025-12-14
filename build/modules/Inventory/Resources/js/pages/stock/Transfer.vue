<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft, ArrowRight } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Warehouse {
  id: string
  name: string
  code: string
}

interface Item {
  id: string
  sku: string
  name: string
  unit_of_measure: string
}

const props = defineProps<{
  company: CompanyRef
  warehouses: Warehouse[]
  items: Item[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Stock Levels', href: `/${props.company.slug}/stock` },
  { title: 'Transfer', href: `/${props.company.slug}/stock/transfer` },
]

const form = useForm({
  source_warehouse_id: '',
  destination_warehouse_id: '',
  item_id: '',
  quantity: 0,
  notes: '',
  movement_date: new Date().toISOString().split('T')[0],
})

const selectedItem = computed(() => {
  return props.items.find(i => i.id === form.item_id)
})

const sourceWarehouse = computed(() => {
  return props.warehouses.find(w => w.id === form.source_warehouse_id)
})

const destinationWarehouse = computed(() => {
  return props.warehouses.find(w => w.id === form.destination_warehouse_id)
})

const availableDestinations = computed(() => {
  return props.warehouses.filter(w => w.id !== form.source_warehouse_id)
})

const submit = () => {
  form.post(`/${props.company.slug}/stock/transfer`)
}
</script>

<template>
  <Head title="Stock Transfer" />

  <PageShell
    title="Stock Transfer"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/stock`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <Card>
        <CardHeader>
          <CardTitle>Transfer Details</CardTitle>
          <CardDescription>Move stock between warehouses</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Warehouses -->
          <div class="grid grid-cols-1 md:grid-cols-[1fr,auto,1fr] gap-4 items-end">
            <div class="space-y-2">
              <Label for="source">From Warehouse *</Label>
              <Select v-model="form.source_warehouse_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.source_warehouse_id }">
                  <SelectValue placeholder="Select source" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.name }} ({{ wh.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.source_warehouse_id" class="text-sm text-destructive">{{ form.errors.source_warehouse_id }}</p>
            </div>

            <div class="hidden md:flex items-center justify-center pb-2">
              <ArrowRight class="h-5 w-5 text-muted-foreground" />
            </div>

            <div class="space-y-2">
              <Label for="destination">To Warehouse *</Label>
              <Select v-model="form.destination_warehouse_id" :disabled="!form.source_warehouse_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.destination_warehouse_id }">
                  <SelectValue placeholder="Select destination" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="wh in availableDestinations" :key="wh.id" :value="wh.id">
                    {{ wh.name }} ({{ wh.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.destination_warehouse_id" class="text-sm text-destructive">{{ form.errors.destination_warehouse_id }}</p>
            </div>
          </div>

          <!-- Transfer Summary -->
          <div v-if="sourceWarehouse && destinationWarehouse" class="p-4 bg-muted rounded-lg text-center">
            <p class="text-sm text-muted-foreground">
              Transferring from <strong>{{ sourceWarehouse.name }}</strong> to <strong>{{ destinationWarehouse.name }}</strong>
            </p>
          </div>

          <!-- Item -->
          <div class="space-y-2">
            <Label for="item">Item *</Label>
            <Select v-model="form.item_id">
              <SelectTrigger :class="{ 'border-destructive': form.errors.item_id }">
                <SelectValue placeholder="Select item" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="item in items" :key="item.id" :value="item.id">
                  {{ item.sku }} - {{ item.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.item_id" class="text-sm text-destructive">{{ form.errors.item_id }}</p>
          </div>

          <!-- Quantity -->
          <div class="space-y-2">
            <Label for="quantity">Quantity *</Label>
            <div class="flex items-center gap-2">
              <Input
                id="quantity"
                v-model="form.quantity"
                type="number"
                step="0.001"
                min="0"
                class="max-w-[200px]"
                :class="{ 'border-destructive': form.errors.quantity }"
              />
              <span v-if="selectedItem" class="text-muted-foreground">
                {{ selectedItem.unit_of_measure }}
              </span>
            </div>
            <p v-if="form.errors.quantity" class="text-sm text-destructive">{{ form.errors.quantity }}</p>
          </div>

          <!-- Date -->
          <div class="space-y-2">
            <Label for="movement_date">Date</Label>
            <Input
              id="movement_date"
              v-model="form.movement_date"
              type="date"
              class="max-w-[200px]"
            />
          </div>

          <!-- Notes -->
          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Transfer notes"
              rows="3"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/stock`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Transferring...' : 'Complete Transfer' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
