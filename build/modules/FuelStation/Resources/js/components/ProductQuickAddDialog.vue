<script setup lang="ts">
/**
 * The station's product quick add: product, rate, opening stock and storage in one dialog,
 * posted to fuel.products.setup (CommandBus). Shared by the Products & stock page.
 */
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useFormFeedback } from '@/composables/useFormFeedback'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Loader2, ChevronDown, Plus, Trash2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface FuelTankOption {
  id: string
  name: string
  code: string
  capacity: number | null
  linked_item_id: string | null
}

const props = defineProps<{
  open: boolean
  companySlug: string
  fuelTanks: FuelTankOption[]
  /** Page props to refresh after a save. */
  reloadOnly?: string[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  saved: []
}>()

const { showError } = useFormFeedback()

const productsDialogOpen = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
})
const tankDialogOpen = ref(false)
const activeTankRowIndex = ref<number | null>(null)
const tankDraft = ref({
  name: '',
  code: '',
  capacity: '',
  low_level_alert: '',
})
const tankDraftErrors = ref<Record<string, string>>({})

const todayLocal = () => {
  const date = new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const buildNozzleRows = (count = 2) => Array.from({ length: count }, (_, index) => ({
  code: '',
  label: index === 0 ? 'Front' : 'Back',
  opening_electronic: '',
  opening_manual: '',
}))

const buildPumpSetup = (index = 0) => ({
  name: `Point ${index + 1}`,
  nozzle_count: 2,
  nozzles: buildNozzleRows(2),
})

const buildProductRow = (index = 0) => ({
  type: 'fuel',
  name: 'Petrol',
  sku: '',
  fuel_category: 'petrol',
  lubricant_format: 'packaged',
  packaging: 'open',
  category_name: '',
  unit_of_measure: 'liters',
  track_inventory: true,
  purchase_rate: '',
  sale_rate: '',
  opening_quantity: '',
  tank_id: '',
  new_tank: null as null | {
    name: string
    code: string
    capacity: string
    low_level_alert: string
  },
  create_pump_points: true,
  pump_setups: [buildPumpSetup(index)],
})

const productsForm = useForm({
  effective_date: todayLocal(),
  products: [buildProductRow()],
})

const defaultFuelNames: Record<string, string> = {
  petrol: 'Petrol',
  diesel: 'Diesel',
  high_octane: 'Hi-Octane',
}

const isOpenPackaging = (row: ReturnType<typeof buildProductRow>) => {
  if (row.type === 'fuel') return true
  if (row.type === 'lubricant') return row.lubricant_format === 'open'
  if (row.type === 'other') return row.packaging === 'open'
  return false
}

const shouldTrackInventory = (row: ReturnType<typeof buildProductRow>) => {
  return row.track_inventory || Number(row.opening_quantity || 0) > 0 || Boolean(row.tank_id || row.new_tank)
}

const setRowDefaults = (row: ReturnType<typeof buildProductRow>) => {
  if (row.type === 'fuel') {
    row.fuel_category = row.fuel_category || 'petrol'
    row.packaging = 'open'
    row.lubricant_format = 'packaged'
    row.track_inventory = true
    row.unit_of_measure = row.unit_of_measure || 'liters'
    row.create_pump_points = true
    if (!row.pump_setups?.length) {
      row.pump_setups = [buildPumpSetup()]
    }
    if (!row.name) {
      row.name = defaultFuelNames[row.fuel_category] || 'Fuel'
    }
    return
  }

  if (row.type === 'lubricant') {
    row.lubricant_format = row.lubricant_format || 'packaged'
    row.packaging = row.lubricant_format
    row.track_inventory = true
    row.fuel_category = row.lubricant_format === 'open' ? 'lubricant' : ''
    row.unit_of_measure = row.unit_of_measure || (row.lubricant_format === 'open' ? 'liters' : 'pack')
    row.create_pump_points = false
    row.pump_setups = []
    return
  }

  row.packaging = row.packaging || 'packaged'
  row.fuel_category = ''
  row.lubricant_format = 'packaged'
  row.unit_of_measure = row.unit_of_measure || 'unit'
  if (row.track_inventory === null || row.track_inventory === undefined) {
    row.track_inventory = true
  }
  row.create_pump_points = false
  row.pump_setups = []
}

const handleTypeChange = (row: ReturnType<typeof buildProductRow>) => {
  row.name = row.type === 'fuel' ? '' : row.name
  row.sku = row.sku || ''
  row.category_name = row.type === 'other' ? row.category_name : ''
  row.packaging = ''
  row.lubricant_format = ''
  row.fuel_category = ''
  row.opening_quantity = ''
  row.tank_id = ''
  row.new_tank = null
  row.create_pump_points = true
  row.pump_setups = [buildPumpSetup()]
  // A new kind of product starts from its own unit, not the previous kind's (fuel's litres).
  row.unit_of_measure = ''
  setRowDefaults(row)
}

const handleFuelCategoryChange = (row: ReturnType<typeof buildProductRow>) => {
  if (!row.name) {
    row.name = defaultFuelNames[row.fuel_category] || 'Fuel'
  }
}

const handleStorageTypeChange = (row: ReturnType<typeof buildProductRow>) => {
  row.track_inventory = true

  if (row.type === 'lubricant') {
    row.packaging = row.lubricant_format
    row.fuel_category = row.lubricant_format === 'open' ? 'lubricant' : ''
    if (row.lubricant_format === 'open' && (!row.unit_of_measure || ['bottle', 'pack'].includes(row.unit_of_measure))) {
      row.unit_of_measure = 'liters'
    }
    if (row.lubricant_format === 'packaged' && (!row.unit_of_measure || ['liters', 'bottle'].includes(row.unit_of_measure))) {
      row.unit_of_measure = 'pack'
    }
  }

  if (row.type === 'other') {
    if (row.packaging === 'open' && (!row.unit_of_measure || row.unit_of_measure === 'unit')) {
      row.unit_of_measure = 'liters'
    }
    if (row.packaging === 'packaged' && (!row.unit_of_measure || row.unit_of_measure === 'liters')) {
      row.unit_of_measure = 'unit'
    }
  }

  if (!isOpenPackaging(row)) {
    row.tank_id = ''
    row.new_tank = null
    row.create_pump_points = false
    row.pump_setups = []
  }
}

const syncNozzleRows = (row: ReturnType<typeof buildProductRow>, pumpIndex = 0) => {
  const pumpSetup = row.pump_setups[pumpIndex]
  if (!pumpSetup) return

  const count = Math.max(1, Math.min(2, Number(pumpSetup.nozzle_count || 2)))
  pumpSetup.nozzle_count = count
  const existing = pumpSetup.nozzles || []
  pumpSetup.nozzles = Array.from({ length: count }, (_, index) => ({
    code: existing[index]?.code || '',
    label: existing[index]?.label || (index === 0 ? 'Front' : 'Back'),
    opening_electronic: existing[index]?.opening_electronic || '',
    opening_manual: existing[index]?.opening_manual || '',
  }))
}

const handlePumpSetupToggle = (row: ReturnType<typeof buildProductRow>, checked: boolean) => {
  row.create_pump_points = checked

  if (checked) {
    if (!row.pump_setups.length) {
      row.pump_setups = [buildPumpSetup()]
    }
    row.pump_setups.forEach((_, index) => syncNozzleRows(row, index))
    return
  }

  row.pump_setups = []
}

const addPumpPoint = (row: ReturnType<typeof buildProductRow>) => {
  row.create_pump_points = true
  row.pump_setups.push(buildPumpSetup(row.pump_setups.length))
}

const removePumpPoint = (row: ReturnType<typeof buildProductRow>, pumpIndex: number) => {
  row.pump_setups.splice(pumpIndex, 1)
  row.pump_setups.forEach((pumpSetup, index) => {
    if (!pumpSetup.name.trim()) {
      pumpSetup.name = `Point ${index + 1}`
    }
  })

  if (row.pump_setups.length === 0) {
    row.create_pump_points = false
  }
}

const clearNewTank = (row: ReturnType<typeof buildProductRow>) => {
  row.new_tank = null
}

const handleTankSelection = (row: ReturnType<typeof buildProductRow>) => {
  if (row.tank_id) {
    row.new_tank = null
  }
}

const addProductRow = () => {
  productsForm.products.push(buildProductRow(productsForm.products.length))
}

const removeProductRow = (index: number) => {
  if (productsForm.products.length <= 1) return
  productsForm.products.splice(index, 1)
}

const openTankDialog = (index: number) => {
  activeTankRowIndex.value = index
  const row = productsForm.products[index]
  tankDraft.value = {
    name: row?.new_tank?.name || '',
    code: row?.new_tank?.code || '',
    capacity: row?.new_tank?.capacity || '',
    low_level_alert: row?.new_tank?.low_level_alert || '',
  }
  tankDraftErrors.value = {}
  tankDialogOpen.value = true
}

const saveTankDraft = () => {
  if (activeTankRowIndex.value === null) return
  const row = productsForm.products[activeTankRowIndex.value]
  if (!row) return
  tankDraftErrors.value = {}
  if (!tankDraft.value.name.trim()) {
    tankDraftErrors.value.name = 'Tank name is required.'
  }
  if (!tankDraft.value.code.trim()) {
    tankDraftErrors.value.code = 'Tank code is required.'
  }
  if (tankDraft.value.capacity === '' || Number(tankDraft.value.capacity) < 1) {
    tankDraftErrors.value.capacity = 'Tank capacity is required.'
  }
  if (Object.keys(tankDraftErrors.value).length > 0) {
    showError('Fill all required tank details.')
    return
  }

  if (!row.name.trim()) {
    showError('Enter a product name before creating a tank.')
    return
  }
  if (row.type === 'fuel' && !row.fuel_category) {
    showError('Select a fuel type before creating a tank.')
    return
  }

  row.tank_id = ''
  row.new_tank = {
    name: tankDraft.value.name.trim(),
    code: tankDraft.value.code.trim(),
    capacity: tankDraft.value.capacity,
    low_level_alert: tankDraft.value.low_level_alert,
  }
  tankDraft.value = {
    name: '',
    code: '',
    capacity: '',
    low_level_alert: '',
  }
  tankDialogOpen.value = false
  toast.success('Tank added to this product setup')
}

const buildProductSetupPayload = () => ({
  effective_date: productsForm.effective_date,
  products: productsForm.products.slice(0, 1).map((row) => ({
    type: row.type,
    name: row.name,
    sku: row.sku,
    fuel_category: row.fuel_category,
    lubricant_format: row.lubricant_format,
    packaging: row.packaging,
    category_name: row.category_name,
    unit_of_measure: row.unit_of_measure,
    track_inventory: shouldTrackInventory(row),
    purchase_rate: row.purchase_rate,
    sale_rate: row.sale_rate,
    opening_quantity: row.opening_quantity,
    tank_id: row.new_tank ? null : (row.tank_id || null),
    new_tank: row.new_tank,
    pump_setups: row.type === 'fuel' && row.track_inventory && row.create_pump_points
      ? row.pump_setups
      : [],
  })),
})

const submitProducts = () => {
  let handled = false
  productsForm
    .transform(() => buildProductSetupPayload())
    .post(`/${props.companySlug}/fuel/products/setup`, {
    preserveScroll: true,
    onSuccess: (page) => {
      handled = true
      const flash = (page.props as { flash?: { error?: string; success?: string } })?.flash
      if (flash?.error) {
        return
      }
      if (!flash?.error && !flash?.success) {
        toast.success('Products saved successfully')
      }
      productsForm.reset()
      productsForm.products = [buildProductRow()]
      productsForm.clearErrors()
      productsDialogOpen.value = false
      // reload() sets preserveScroll itself, after spreading these options, so passing
      // it here never had any effect.
      emit('saved')
      router.reload({
        only: props.reloadOnly ?? ['rows', 'summary', 'fuelTanks'],
      })
    },
    onError: (errors) => {
      handled = true
      showError(errors)
    },
    onFinish: () => {
      if (!handled) {
        toast.error('Failed to save products. Please try again.')
      }
    },
  })
}

</script>

<template>
<!-- Product Setup Dialog -->
<Dialog v-model:open="productsDialogOpen">
  <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
    <DialogHeader>
      <DialogTitle class="text-foreground">Add Product</DialogTitle>
      <DialogDescription class="text-text-secondary">
        Add the product, rate, opening stock, and storage in one place.
      </DialogDescription>
    </DialogHeader>

    <div v-for="(row, index) in productsForm.products.slice(0, 1)" :key="index" class="space-y-5 py-2">
      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-foreground">Product</h3>
            <p class="text-xs text-text-secondary">What the station sells.</p>
          </div>
          <Badge variant="secondary">{{ row.type === 'fuel' ? 'Fuel' : row.type === 'lubricant' ? 'Lubricant' : 'Other' }}</Badge>
        </div>

        <div class="grid gap-3 md:grid-cols-[150px_170px_1fr]">
          <div class="space-y-2">
            <Label>Type</Label>
            <Select v-model="row.type" @update:modelValue="handleTypeChange(row)">
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="fuel">Fuel</SelectItem>
                <SelectItem value="lubricant">Lubricant</SelectItem>
                <SelectItem value="other">Other</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="row.type === 'fuel'" class="space-y-2">
            <Label>Fuel Type</Label>
            <Select v-model="row.fuel_category" @update:modelValue="handleFuelCategoryChange(row)">
              <SelectTrigger>
                <SelectValue placeholder="Select fuel" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="petrol">Petrol</SelectItem>
                <SelectItem value="diesel">Diesel</SelectItem>
                <SelectItem value="high_octane">High Octane</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="productsForm.errors[`products.${index}.fuel_category`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.fuel_category`] }}
            </p>
          </div>

          <div v-if="row.type === 'lubricant'" class="space-y-2">
            <Label>Storage Type</Label>
            <Select v-model="row.lubricant_format" @update:modelValue="handleStorageTypeChange(row)">
              <SelectTrigger>
                <SelectValue placeholder="Select storage" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="open">Open / Bulk stock</SelectItem>
                <SelectItem value="packaged">Packaged item</SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-text-secondary">
              Bulk needs storage setup. Packaged is counted as bottles/units.
            </p>
            <p v-if="productsForm.errors[`products.${index}.lubricant_format`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.lubricant_format`] }}
            </p>
          </div>

          <div v-if="row.type === 'other'" class="space-y-2">
            <Label>Storage Type</Label>
            <Select v-model="row.packaging" @update:modelValue="handleStorageTypeChange(row)">
              <SelectTrigger>
                <SelectValue placeholder="Select storage" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="open">Open / Bulk stock</SelectItem>
                <SelectItem value="packaged">Packaged item</SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-text-secondary">
              Bulk needs storage setup. Packaged is counted as units.
            </p>
            <p v-if="productsForm.errors[`products.${index}.packaging`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.packaging`] }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>Name</Label>
            <Input v-model="row.name" placeholder="Product name" />
            <p v-if="productsForm.errors[`products.${index}.name`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.name`] }}
            </p>
          </div>
        </div>
      </section>

      <section class="space-y-3 border-t border-rule-subtle pt-4">
        <div>
          <h3 class="text-sm font-semibold text-foreground">Rate and Opening Stock</h3>
          <p class="text-xs text-text-secondary">Starting numbers for the product.</p>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
          <div class="space-y-2">
            <Label>Effective Date</Label>
            <Input v-model="productsForm.effective_date" type="date" />
            <p v-if="productsForm.errors.effective_date" class="text-xs text-status-critical">
              {{ productsForm.errors.effective_date }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>Purchase Rate</Label>
            <Input v-model="row.purchase_rate" type="number" step="0.01" min="0" />
            <p v-if="productsForm.errors[`products.${index}.purchase_rate`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.purchase_rate`] }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>Sale Rate</Label>
            <Input v-model="row.sale_rate" type="number" step="0.01" min="0" />
            <p v-if="productsForm.errors[`products.${index}.sale_rate`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.sale_rate`] }}
            </p>
          </div>

          <div v-if="row.track_inventory" class="space-y-2">
            <Label>Opening Stock</Label>
            <Input v-model="row.opening_quantity" type="number" step="0.001" min="0" placeholder="Optional" />
            <p v-if="productsForm.errors[`products.${index}.opening_quantity`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.opening_quantity`] }}
            </p>
          </div>
        </div>
      </section>

      <section v-if="row.track_inventory && isOpenPackaging(row)" class="space-y-3 border-t border-rule-subtle pt-4">
        <div>
          <h3 class="text-sm font-semibold text-foreground">Storage and Pump</h3>
          <p class="text-xs text-text-secondary">Where the product is stored and how it is served.</p>
        </div>

        <div class="grid gap-3 md:grid-cols-[1fr_auto]">
          <div class="space-y-2">
            <Label>Tank / Storage Source</Label>
            <Select v-model="row.tank_id" :disabled="!!row.new_tank" @update:modelValue="handleTankSelection(row)">
              <SelectTrigger>
                <SelectValue placeholder="Select tank" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="tank in fuelTanks" :key="tank.id" :value="tank.id">
                  {{ tank.name }} ({{ tank.code }})
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="productsForm.errors[`products.${index}.tank_id`]" class="text-xs text-status-critical">
              {{ productsForm.errors[`products.${index}.tank_id`] }}
            </p>
            <div v-if="row.new_tank" class="flex items-center justify-between gap-3 rounded-md border border-status-success/30 bg-status-success/10 px-3 py-2">
              <div class="min-w-0">
                <div class="truncate text-sm font-medium text-status-success">
                  {{ row.new_tank.name }} ({{ row.new_tank.code }})
                </div>
                <div class="text-xs text-status-success">
                  Will be created with this product · {{ row.new_tank.capacity }} liters
                </div>
              </div>
              <Button type="button" variant="ghost" size="sm" @click="clearNewTank(row)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
          <div class="flex items-end">
            <Button type="button" variant="outline" @click="openTankDialog(index)">
              {{ row.new_tank ? 'Edit Tank' : 'Add Tank' }}
            </Button>
          </div>
        </div>

        <div v-if="row.type === 'fuel'" class="flex items-center justify-between gap-3 rounded-lg border border-rule-subtle bg-surface-sunken p-3">
          <div>
            <div class="text-sm font-medium text-foreground">Create pump points</div>
            <div class="text-xs text-text-secondary">Turn off only if pump/nozzles already exist.</div>
          </div>
          <Switch
            :checked="row.create_pump_points"
            @update:checked="(checked) => handlePumpSetupToggle(row, checked)"
          />
        </div>

        <div v-if="row.type === 'fuel' && row.track_inventory && row.create_pump_points" class="space-y-3">
          <div
            v-for="(pumpSetup, pumpIndex) in row.pump_setups"
            :key="pumpIndex"
            class="grid gap-3 rounded-lg border border-rule-subtle p-3 md:grid-cols-[1fr_160px_auto] md:items-end"
          >
            <div class="space-y-2">
              <Label>{{ pumpIndex === 0 ? 'Pump Point' : `Pump Point ${pumpIndex + 1}` }}</Label>
              <Input v-model="pumpSetup.name" :placeholder="`Point ${pumpIndex + 1}`" />
              <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.name`]" class="text-xs text-status-critical">
                {{ productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.name`] }}
              </p>
            </div>
            <div class="space-y-2">
              <Label>Nozzles</Label>
              <Select v-model="pumpSetup.nozzle_count" @update:modelValue="syncNozzleRows(row, pumpIndex)">
                <SelectTrigger>
                  <SelectValue placeholder="Nozzles" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="1">1 nozzle</SelectItem>
                  <SelectItem :value="2">2 nozzles</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              :disabled="row.pump_setups.length === 1"
              @click="removePumpPoint(row, pumpIndex)"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>

          <Button type="button" variant="outline" class="w-full border-dashed" @click="addPumpPoint(row)">
            <Plus class="mr-2 h-4 w-4" />
            Add Another Pump Point
          </Button>
        </div>

      </section>

      <Collapsible>
        <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-rule-default px-3 py-2 text-sm text-text-secondary transition-colors hover:bg-surface-sunken hover:text-foreground">
          <span>Advanced setup</span>
          <ChevronDown class="h-4 w-4" />
        </CollapsibleTrigger>

        <CollapsibleContent class="mt-4 space-y-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-2">
              <Label>SKU</Label>
              <Input v-model="row.sku" placeholder="Auto-generated if blank" />
              <p v-if="productsForm.errors[`products.${index}.sku`]" class="text-xs text-status-critical">
                {{ productsForm.errors[`products.${index}.sku`] }}
              </p>
            </div>

            <div class="space-y-2">
              <Label>Unit</Label>
              <Input
                v-model="row.unit_of_measure"
                :disabled="row.type === 'fuel'"
                placeholder="e.g., liters, bottle, unit"
              />
            </div>

            <div v-if="row.type === 'other'" class="space-y-2">
              <Label>Category</Label>
              <Input v-model="row.category_name" placeholder="Free-form category" />
            </div>
          </div>

          <div v-if="row.type === 'fuel' && row.track_inventory && row.create_pump_points" class="space-y-3 rounded-lg border border-rule-subtle bg-surface-sunken p-3">
            <div>
              <h3 class="text-sm font-semibold text-foreground">Nozzle Details</h3>
              <p class="text-xs text-text-secondary">Leave IDs blank for automatic numbering.</p>
            </div>

            <div
              v-for="(pumpSetup, pumpIndex) in row.pump_setups"
              :key="pumpIndex"
              class="space-y-3 rounded-md border border-rule-default bg-surface-raised p-3"
            >
              <div class="text-xs font-medium text-text-secondary">{{ pumpSetup.name || `Point ${pumpIndex + 1}` }}</div>
              <div
                v-for="(nozzle, nozzleIndex) in pumpSetup.nozzles"
                :key="nozzleIndex"
                class="grid gap-3 md:grid-cols-4"
              >
                <div class="space-y-2">
                  <Label>Nozzle ID</Label>
                  <Input v-model="nozzle.code" placeholder="Auto" />
                  <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.nozzles.${nozzleIndex}.code`]" class="text-xs text-status-critical">
                    {{ productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.nozzles.${nozzleIndex}.code`] }}
                  </p>
                </div>
                <div class="space-y-2">
                  <Label>Nozzle Name</Label>
                  <Input v-model="nozzle.label" placeholder="Front" />
                </div>
                <div class="space-y-2">
                  <Label>Electronic Reading</Label>
                  <Input v-model="nozzle.opening_electronic" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-2">
                  <Label>Manual Reading</Label>
                  <Input v-model="nozzle.opening_manual" type="number" step="0.01" min="0" placeholder="Optional" />
                </div>
              </div>
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>

    <DialogFooter class="mt-2">
      <Button type="button" variant="outline" @click="productsDialogOpen = false">
        Cancel
      </Button>
      <Button type="button" :disabled="productsForm.processing" @click="submitProducts">
        <Loader2 v-if="productsForm.processing" class="mr-2 h-4 w-4 animate-spin" />
        Create Product
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>

<!-- Add Tank Dialog -->
<Dialog v-model:open="tankDialogOpen">
  <DialogContent class="sm:max-w-lg">
    <DialogHeader>
      <DialogTitle class="text-foreground">Add Tank</DialogTitle>
      <DialogDescription class="text-text-secondary">
        This tank will be created when you create the product.
      </DialogDescription>
    </DialogHeader>
    <div class="space-y-4 py-4">
      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Tank Name</Label>
          <Input v-model="tankDraft.name" placeholder="e.g., Petrol Tank 1" />
          <p v-if="tankDraftErrors.name" class="text-xs text-status-critical">{{ tankDraftErrors.name }}</p>
        </div>
        <div class="space-y-2">
          <Label>Tank Code</Label>
          <Input v-model="tankDraft.code" placeholder="e.g., TANK-PET" />
          <p v-if="tankDraftErrors.code" class="text-xs text-status-critical">{{ tankDraftErrors.code }}</p>
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Capacity (Liters)</Label>
          <Input v-model="tankDraft.capacity" type="number" step="0.01" min="1" />
          <p v-if="tankDraftErrors.capacity" class="text-xs text-status-critical">{{ tankDraftErrors.capacity }}</p>
        </div>
        <div class="space-y-2">
          <Label>Low Level Alert (optional)</Label>
          <Input v-model="tankDraft.low_level_alert" type="number" step="0.01" min="0" />
        </div>
      </div>
    </div>
    <DialogFooter>
      <Button type="button" variant="outline" @click="tankDialogOpen = false">
        Cancel
      </Button>
      <Button type="button" @click="saveTankDraft">
        Use This Tank
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>

</template>
