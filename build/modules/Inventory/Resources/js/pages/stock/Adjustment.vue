<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft, Plus, Minus } from 'lucide-vue-next'

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
  { title: 'Adjustment', href: `/${props.company.slug}/stock/adjustment` },
]

const adjustmentType = ref<'increase' | 'decrease'>('increase')

const form = useForm({
  warehouse_id: '',
  item_id: '',
  quantity: 0,
  reason: '',
  notes: '',
  movement_date: new Date().toISOString().split('T')[0],
})

const selectedItem = computed(() => {
  return props.items.find(i => i.id === form.item_id)
})

const submit = () => {
  const qty = adjustmentType.value === 'decrease' ? -Math.abs(form.quantity) : Math.abs(form.quantity)
  form.transform((data) => ({
    ...data,
    quantity: qty,
  })).post(`/${props.company.slug}/stock/adjustment`)
}

const reasons = [
  'Physical count adjustment',
  'Damaged goods',
  'Theft/loss',
  'Found inventory',
  'Opening balance',
  'Other',
]
</script>

<template>
  <Head title="Stock Adjustment" />

  <PageShell
    title="Stock Adjustment"
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
          <CardTitle>Adjustment Details</CardTitle>
          <CardDescription>Record a stock adjustment</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Warehouse -->
          <div class="space-y-2">
            <Label for="warehouse">Warehouse *</Label>
            <Select v-model="form.warehouse_id">
              <SelectTrigger :class="{ 'border-destructive': form.errors.warehouse_id }">
                <SelectValue placeholder="Select warehouse" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                  {{ wh.name }} ({{ wh.code }})
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.warehouse_id" class="text-sm text-destructive">{{ form.errors.warehouse_id }}</p>
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

          <!-- Adjustment Type -->
          <div class="space-y-3">
            <Label>Adjustment Type *</Label>
            <RadioGroup v-model="adjustmentType" class="flex gap-4">
              <div class="flex items-center space-x-2">
                <RadioGroupItem value="increase" id="increase" />
                <Label for="increase" class="flex items-center gap-1 cursor-pointer">
                  <Plus class="h-4 w-4 text-green-600" />
                  Increase
                </Label>
              </div>
              <div class="flex items-center space-x-2">
                <RadioGroupItem value="decrease" id="decrease" />
                <Label for="decrease" class="flex items-center gap-1 cursor-pointer">
                  <Minus class="h-4 w-4 text-red-600" />
                  Decrease
                </Label>
              </div>
            </RadioGroup>
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

          <!-- Reason -->
          <div class="space-y-2">
            <Label for="reason">Reason</Label>
            <Select v-model="form.reason">
              <SelectTrigger>
                <SelectValue placeholder="Select reason" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="reason in reasons" :key="reason" :value="reason">
                  {{ reason }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Notes -->
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
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/stock`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Adjustment' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
