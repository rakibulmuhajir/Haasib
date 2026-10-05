<script setup lang="ts">
import { localToday } from '@/composables/useEntryDate'
import { ref, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import InputError from '@/components/InputError.vue'
import MoneyText from '@/components/MoneyText.vue'
import PageShell from '@/components/PageShell.vue'
import Hint from '@/components/Hint.vue'
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
  base_currency: string
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
  cost_price: number | string | null
  avg_cost: number | string | null
}

const props = defineProps<{
  company: CompanyRef
  warehouses: Warehouse[]
  items: Item[]
  expenseAccounts: { id: string; code: string; name: string }[]
  preselect?: { item_id: string; warehouse_id: string | null } | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Stock Levels', href: `/${props.company.slug}/stock` },
  { title: 'Adjustment', href: `/${props.company.slug}/stock/adjustment` },
]

const adjustmentType = ref<'increase' | 'decrease'>('increase')

const form = useForm({
  warehouse_id: props.preselect?.warehouse_id ?? '',
  item_id: props.preselect?.item_id ?? '',
  quantity: 0,
  unit_cost: '',
  reason: '',
  expense_account_id: '',
  notes: '',
  movement_date: localToday(),
})

const selectedItem = computed(() => {
  return props.items.find(i => i.id === form.item_id)
})

const defaultUnitCost = computed(() => {
  const item = selectedItem.value
  if (!item) return ''

  const cost = Number(item.avg_cost || item.cost_price || 0)
  return cost > 0 ? cost.toFixed(6).replace(/\.?0+$/, '') : ''
})

watch(
  () => form.item_id,
  () => {
    form.unit_cost = defaultUnitCost.value
  }
)

const estimatedValue = computed(() => {
  const quantity = Math.abs(Number(form.quantity || 0))
  const unitCost = Number(form.unit_cost || 0)
  return quantity * unitCost
})

const increaseReasons = [
  { value: 'already_had', label: 'Stock we already had', hint: 'Stock you owned but never recorded. Goes to opening balance, not income.' },
  { value: 'gain', label: 'Stock gain', hint: 'Found or counted more than the books. Counts as income.' },
]
const decreaseReasons = [
  { value: 'lost_damaged', label: 'Lost / damaged / expired', hint: 'Counts as a stock loss.' },
  { value: 'own_use', label: 'Own use', hint: 'Used by the station. Goes to the expense account you pick.' },
  { value: 'counted_less', label: 'Counted less than the books', hint: 'Counts as a stock loss.' },
]
const reasonOptions = computed(() => (adjustmentType.value === 'increase' ? increaseReasons : decreaseReasons))

// A reason from the other direction no longer applies: clear it so a choice is forced.
watch(adjustmentType, () => {
  form.reason = ''
  form.expense_account_id = ''
})

const submit = () => {
  const qty = adjustmentType.value === 'decrease' ? -Math.abs(form.quantity) : Math.abs(form.quantity)
  form.transform((data) => ({
    ...data,
    quantity: qty,
    unit_cost: data.unit_cost === '' ? null : data.unit_cost,
    expense_account_id: data.reason === 'own_use' ? data.expense_account_id : null,
  })).post(`/${props.company.slug}/stock/adjustment`)
}

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

    <form novalidate @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <Card variant="form">
        <CardHeader>
          <CardTitle>Adjustment Details</CardTitle>
          <CardDescription>Use this for corrections. Use bills and receiving for normal fuel purchases.</CardDescription>
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
            <InputError :message="form.errors.warehouse_id" />
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
            <InputError :message="form.errors.item_id" />
          </div>

          <!-- Adjustment Type -->
          <div class="space-y-3">
            <Label>Adjustment Type *</Label>
            <RadioGroup v-model="adjustmentType" class="flex gap-4">
              <div class="flex items-center space-x-2">
                <RadioGroupItem value="increase" id="increase" />
                <Label for="increase" class="flex items-center gap-1 cursor-pointer">
                  <Plus class="h-4 w-4 text-status-success" />
                  Increase
                </Label>
              </div>
              <div class="flex items-center space-x-2">
                <RadioGroupItem value="decrease" id="decrease" />
                <Label for="decrease" class="flex items-center gap-1 cursor-pointer">
                  <Minus class="h-4 w-4 text-status-critical" />
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
            <InputError :message="form.errors.quantity" />
          </div>

          <!-- Unit Cost -->
          <div class="space-y-2">
            <Label for="unit_cost">Value per unit *</Label>
            <div class="flex items-center gap-2">
              <Input
                id="unit_cost"
                v-model="form.unit_cost"
                type="number"
                step="0.000001"
                min="0"
                class="max-w-[200px]"
                :class="{ 'border-destructive': form.errors.unit_cost }"
              />
              <span v-if="selectedItem" class="text-muted-foreground">
                per {{ selectedItem.unit_of_measure }}
              </span>
            </div>
            <p class="text-sm text-muted-foreground">
              Used to post the inventory value. Existing item cost is filled automatically when available.
            </p>
            <p v-if="estimatedValue > 0" class="text-sm text-text-secondary">
              Estimated value: <MoneyText :amount="estimatedValue" :currency="company.base_currency" />
            </p>
            <InputError :message="form.errors.unit_cost" />
          </div>

          <!-- Date -->
          <div class="space-y-2">
            <Label for="movement_date">{{ form.reason === 'already_had' ? 'As of' : 'Date' }}</Label>
            <Input
              id="movement_date"
              v-model="form.movement_date"
              type="date"
              class="max-w-[200px]"
            />
            <InputError :message="form.errors.movement_date" />
          </div>

          <!-- Why -->
          <div class="space-y-3">
            <Label>Why *</Label>
            <RadioGroup v-model="form.reason" class="space-y-2">
              <div v-for="r in reasonOptions" :key="r.value" class="flex items-center space-x-2">
                <RadioGroupItem :value="r.value" :id="`reason-${r.value}`" />
                <Label :for="`reason-${r.value}`" class="cursor-pointer">
                  <Hint>
                    {{ r.label }}
                    <template #content>{{ r.hint }}</template>
                  </Hint>
                </Label>
              </div>
            </RadioGroup>
            <p v-if="adjustmentType === 'increase'" class="text-sm text-muted-foreground">
              Received without a bill? Enter it under
              <a :href="`/${company.slug}/bills/create`" class="underline">Purchases, Bills</a>.
            </p>
            <InputError :message="form.errors.reason" />
          </div>

          <div v-if="form.reason === 'own_use'" class="space-y-2">
            <Label for="expense_account">Expense account *</Label>
            <Select v-model="form.expense_account_id">
              <SelectTrigger id="expense_account" :class="{ 'border-destructive': form.errors.expense_account_id }">
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in expenseAccounts" :key="a.id" :value="a.id">
                  {{ a.code }} - {{ a.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.expense_account_id" />
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
            <InputError :message="form.errors.notes" />
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
