<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { Fuel, Gauge, Plus, Eye, Pencil, Trash2, Search } from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface TankRef {
  id: string
  name: string
  warehouse_type: string
  capacity?: number | null
  linked_item?: FuelItemRef | null
}

interface PumpRow {
  id: string
  name: string
  tank_id: string
  current_meter_reading: number
  is_active: boolean
  tank?: TankRef | null
}

const props = defineProps<{
  pumps: PumpRow[]
  tanks: TankRef[]
  debug?: {
    user_id?: string | null
    user_email?: string | null
    user_role?: string | null
    can_create_pump?: boolean
    has_company_context?: boolean
    company_id?: string | null
    company_name?: string | null
  }
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/pumps` },
  { title: 'Pumps', href: `/${companySlug.value}/fuel/pumps` },
])

const search = ref('')
const activeOnly = ref(true)

const filteredPumps = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.pumps.filter((pump) => {
    if (activeOnly.value && !pump.is_active) return false
    if (!q) return true
    const tankName = pump.tank?.name ?? ''
    const itemName = pump.tank?.linked_item?.name ?? ''
    return (
      pump.name.toLowerCase().includes(q) ||
      tankName.toLowerCase().includes(q) ||
      itemName.toLowerCase().includes(q)
    )
  })
})

const tankOptions = computed(() =>
  props.tanks.map((tank) => {
    const fuelName = tank.linked_item?.name ? ` • ${tank.linked_item.name}` : ''
    return {
      value: tank.id,
      label: `${tank.name}${fuelName}`,
    }
  })
)

const stats = computed(() => {
  const total = props.pumps.length
  const active = props.pumps.filter((p) => p.is_active).length
  const inactive = total - active
  const tanks = props.tanks.length
  const fuelTypes = new Set(props.tanks.map((t) => t.linked_item?.fuel_category).filter(Boolean)).size
  return { total, active, inactive, tanks, fuelTypes }
})

const columns = [
  { key: 'name', label: 'Point' },
  { key: 'tank', label: 'Tank' },
  { key: 'fuel', label: 'Fuel' },
  { key: 'meter', label: 'Meter' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredPumps.value.map((pump) => ({
    id: pump.id,
    name: pump.name,
    tank: pump.tank?.name ?? '-',
    fuel: pump.tank?.linked_item?.name ?? pump.tank?.linked_item?.fuel_category ?? '-',
    meter: new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(pump.current_meter_reading ?? 0),
    status: pump.is_active ? 'Active' : 'Inactive',
    _actions: pump.id,
    _raw: pump,
  }))
})

const dialogOpen = ref(false)
const confirmDeleteOpen = ref(false)
const selectedPump = ref<PumpRow | null>(null)

const form = useForm<{
  name: string
  tank_id: string
  current_meter_reading: number | null
  is_active: boolean
}>({
  name: '',
  tank_id: '',
  current_meter_reading: null,
  is_active: true,
})

const openCreate = () => {
  selectedPump.value = null
  form.reset()
  form.clearErrors()

  // Generate next pump point name
  const nextPointNumber = props.pumps.length + 1
  form.name = `Point ${nextPointNumber}`

  dialogOpen.value = true
}

const openEdit = (pump: PumpRow) => {
  selectedPump.value = pump
  form.clearErrors()
  form.name = pump.name
  form.tank_id = pump.tank_id
  form.current_meter_reading = pump.current_meter_reading ?? 0
  form.is_active = pump.is_active ?? true
  dialogOpen.value = true
}

const closeDialog = () => {
  dialogOpen.value = false
  form.reset()
  form.clearErrors()
}

const submit = () => {
  const slug = companySlug.value
  if (!slug) return

  if (selectedPump.value) {
    form.put(`/${slug}/fuel/pumps/${selectedPump.value.id}`, {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
    return
  }

  form.post(`/${slug}/fuel/pumps`, {
    preserveScroll: true,
    onSuccess: () => closeDialog(),
  })
}

const openDelete = (pump: PumpRow) => {
  selectedPump.value = pump
  confirmDeleteOpen.value = true
}

const confirmDelete = () => {
  const slug = companySlug.value
  if (!slug || !selectedPump.value) return

  router.delete(`/${slug}/fuel/pumps/${selectedPump.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      confirmDeleteOpen.value = false
      selectedPump.value = null
    },
  })
}

const goToShow = (row: any) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/pumps/${row.id}`)
}

const goToWarehouses = () => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/inventory/warehouses`)
}
</script>

<template>
  <Head title="Fuel Pump Points" />

  <PageShell
    title="Fuel Pump Points"
    description="Track pump point meters, tanks, and operational status. Each pumping machine is identified as a point."
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <!-- DEBUG INFO - Check permissions -->
    <div v-if="props.debug" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
      <h3 class="font-semibold text-amber-900 mb-2">🔍 Debug Information</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <div>
          <strong>User:</strong> {{ props.debug.user_email || 'N/A' }}
        </div>
        <div>
          <strong>Role:</strong>
          <span :class="props.debug.can_create_pump ? 'text-green-700 font-bold' : 'text-red-700 font-bold'">
            {{ props.debug.user_role || 'No Role' }}
          </span>
        </div>
        <div>
          <strong>Can Create Pump:</strong>
          <span :class="props.debug.can_create_pump ? 'text-green-700' : 'text-red-700'">
            {{ props.debug.can_create_pump ? '✅ YES' : '❌ NO' }}
          </span>
        </div>
        <div>
          <strong>Company Context:</strong>
          <span :class="props.debug.has_company_context ? 'text-green-700' : 'text-red-700'">
            {{ props.debug.has_company_context ? '✅ YES' : '❌ NO' }}
          </span>
        </div>
        <div>
          <strong>Company:</strong> {{ props.debug.company_name || 'N/A' }}
        </div>
        <div>
          <strong>Company ID:</strong> {{ props.debug.company_id || 'N/A' }}
        </div>
      </div>
    </div>
        <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        Add Pump Point
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Pumps</CardDescription>
          <CardTitle class="text-2xl">{{ stats.total }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Gauge class="h-4 w-4 text-sky-600" />
            <span>Across {{ stats.tanks }} tank(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Active</CardDescription>
          <CardTitle class="text-2xl">{{ stats.active }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">Operational</Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Inactive</CardDescription>
          <CardTitle class="text-2xl">{{ stats.inactive }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="secondary" class="bg-amber-100 text-amber-800 hover:bg-amber-100">Needs Attention</Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Fuel Types</CardDescription>
          <CardTitle class="text-2xl">{{ stats.fuelTypes }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-sky-200 text-sky-700">Linked via tanks</Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Pump List</CardTitle>
            <CardDescription>Search by pump, tank, or fuel item.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[280px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search pumps..." class="pl-9" />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="activeOnly" v-model:checked="activeOnly" />
              <Label for="activeOnly" class="text-sm text-text-secondary">Active only</Label>
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable
          :data="tableData"
          :columns="columns"
          clickable
          @row-click="goToShow"
        >
          <template #empty>
            <EmptyState
              title="No pump points yet"
              description="Create your first pump point and link it to a tank. Each pumping machine is identified as a point."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Pump Point
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.is_active ? 'bg-emerald-600 text-white hover:bg-emerald-600' : 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200'"
            >
              {{ row._raw.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-fuel="{ row }">
            <div class="flex items-center gap-2">
              <div class="h-2.5 w-2.5 rounded-full bg-sky-500/60" />
              <span class="font-medium text-text-primary">
                {{ row._raw.tank?.linked_item?.name ?? row.fuel }}
              </span>
              <Badge
                v-if="row._raw.tank?.linked_item?.fuel_category"
                variant="secondary"
                class="bg-sky-100 text-sky-800 hover:bg-sky-100"
              >
                {{ row._raw.tank?.linked_item?.fuel_category }}
              </Badge>
            </div>
          </template>

          <template #cell-_actions="{ row }">
            <div class="flex items-center justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                @click.stop="router.get(`/${companySlug}/fuel/pumps/${row.id}`)"
              >
                <Eye class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="sm" @click.stop="openEdit(row._raw)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button
                variant="destructive"
                size="sm"
                @click.stop="openDelete(row._raw)"
              >
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Fuel class="h-5 w-5 text-sky-600" />
            {{ selectedPump ? 'Edit Pump Point' : 'Add Pump Point' }}
          </DialogTitle>
          <DialogDescription>
            Link each pump point to a tank so sales and readings map to the correct fuel item.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="space-y-2">
            <Label for="name">Pump Point</Label>
            <Input
              id="name"
              v-model="form.name"
              placeholder="e.g., Point 1, Point 2"
              :class="{ 'border-destructive': form.errors.name }"
            />
            <p class="text-sm text-text-secondary">Each pumping machine is identified as a point.</p>
            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
          </div>

          <div class="space-y-2">
            <Label for="tank_id">Tank</Label>
            <Select v-model="form.tank_id" :disabled="tankOptions.length === 0">
              <SelectTrigger id="tank_id" :class="{ 'border-destructive': form.errors.tank_id }">
                <SelectValue :placeholder="tankOptions.length === 0 ? 'No tanks available' : 'Select a tank...'" />
              </SelectTrigger>
              <SelectContent v-if="tankOptions.length > 0">
                <SelectItem v-for="opt in tankOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.tank_id" class="text-sm text-destructive">{{ form.errors.tank_id }}</p>
            <div v-if="tankOptions.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
              <p class="text-sm text-amber-800">
                <strong>No tanks available.</strong> You need to create tank warehouses first before adding pumps.
              </p>
              <div class="mt-2">
                <Button
                  variant="outline"
                  size="sm"
                  class="border-amber-300 text-amber-700 hover:bg-amber-100"
                  @click="goToWarehouses"
                >
                  Create Tank
                </Button>
              </div>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="meter">Current meter</Label>
              <Input
                id="meter"
                v-model.number="form.current_meter_reading"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :class="{ 'border-destructive': form.errors.current_meter_reading }"
              />
              <p v-if="form.errors.current_meter_reading" class="text-sm text-destructive">
                {{ form.errors.current_meter_reading }}
              </p>
            </div>

            <div class="flex items-end gap-3 rounded-lg border border-border/70 bg-muted/40 px-4 py-3">
              <div class="flex-1">
                <Label for="isActive">Active</Label>
                <p class="text-sm text-text-secondary">Hide pumps under maintenance.</p>
              </div>
              <Switch id="isActive" v-model:checked="form.is_active" />
            </div>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="closeDialog">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing || tankOptions.length === 0">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              {{ selectedPump ? 'Save changes' : 'Create pump point' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <ConfirmDialog
      v-model:open="confirmDeleteOpen"
      variant="destructive"
      title="Delete pump?"
      confirm-text="Delete"
      :loading="false"
      @confirm="confirmDelete"
    >
      <template #description>
        <p>Deleting a pump is only allowed if it has no readings.</p>
        <p v-if="selectedPump" class="mt-2 font-medium text-text-primary">
          {{ selectedPump.name }}
        </p>
      </template>
    </ConfirmDialog>
  </PageShell>
</template>

