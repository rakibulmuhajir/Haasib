<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Bus, Map, Package, Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  transportServices: any[]
  drivers: any[]
  sectors: any[]
  packages: any[]
  fares: any[]
  chargingBases: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Transport Services', href: `/${props.company.slug}/umrah/settings/transport-services` },
]

const form = useForm({
  name: '',
  driver_id: 'none',
  vehicle_type: '',
  pax_capacity: '',
  make: '',
  model: '',
  color: '',
  number_plate: '',
  driver_name: '',
  driver_contact: '',
  default_sale_amount: '0',
  default_cost_amount: '0',
  notes: '',
})

const removeForm = useForm({})
const catalogRemoveForm = useForm({})
const sectorForm = useForm({ code: '', name: '', origin: '', destination: '' })
const packageForm = useForm({ name: '', notes: '', sector_ids: [] as string[] })
const fareForm = useForm({
  name: '', transport_service_id: '', fare_target: '', charging_basis: 'per_vehicle',
  sale_amount: '0', cost_amount: '0', hajj_terminal_sale_amount: '90', hajj_terminal_cost_amount: '0',
})
const editingService = ref<any | null>(null)
const serviceToRemove = ref<any | null>(null)
const removeDialogOpen = ref(false)

const resetForm = () => {
  editingService.value = null
  form.clearErrors()
  form.name = ''
  form.driver_id = 'none'
  form.vehicle_type = ''
  form.pax_capacity = ''
  form.make = ''
  form.model = ''
  form.color = ''
  form.number_plate = ''
  form.driver_name = ''
  form.driver_contact = ''
  form.default_sale_amount = '0'
  form.default_cost_amount = '0'
  form.notes = ''
}

const startEdit = (service: any) => {
  editingService.value = service
  form.clearErrors()
  form.name = service.name || ''
  form.driver_id = service.driver_id || 'none'
  form.vehicle_type = service.vehicle_type || ''
  form.pax_capacity = service.pax_capacity ? String(service.pax_capacity) : ''
  form.make = service.make || ''
  form.model = service.model || ''
  form.color = service.color || ''
  form.number_plate = service.number_plate || ''
  form.driver_name = service.driver_name || ''
  form.driver_contact = service.driver_contact || ''
  form.default_sale_amount = String(service.default_sale_amount ?? 0)
  form.default_cost_amount = String(service.default_cost_amount ?? 0)
  form.notes = service.notes || ''
}

const payload = (data: any) => ({
  ...data,
  driver_id: data.driver_id === 'none' ? null : data.driver_id,
  pax_capacity: data.pax_capacity ? Number(data.pax_capacity) : null,
  default_sale_amount: Number(data.default_sale_amount || 0),
  default_cost_amount: Number(data.default_cost_amount || 0),
})

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(editingService.value ? 'Transport service updated successfully' : 'Transport service added successfully')
      resetForm()
    },
    onError: () => toast.error(editingService.value ? 'Failed to update transport service' : 'Failed to add transport service'),
  }

  form.transform(payload)

  if (editingService.value) {
    form.put(`/${props.company.slug}/umrah/settings/transport-services/${editingService.value.id}`, options)
    return
  }

  form.post(`/${props.company.slug}/umrah/settings/transport-services`, options)
}

const removeService = (service: any) => {
  serviceToRemove.value = service
  removeDialogOpen.value = true
}

const confirmRemoveService = () => {
  if (!serviceToRemove.value) return

  removeForm.delete(`/${props.company.slug}/umrah/settings/transport-services/${serviceToRemove.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Transport service removed successfully')
      if (editingService.value?.id === serviceToRemove.value?.id) resetForm()
      removeDialogOpen.value = false
      serviceToRemove.value = null
    },
    onError: () => toast.error('Failed to remove transport service'),
  })
}

const submitSector = () => sectorForm.post(`/${props.company.slug}/umrah/settings/transport-sectors`, {
  preserveScroll: true,
  onSuccess: () => { sectorForm.reset(); toast.success('Transport sector added successfully') },
  onError: () => toast.error('Failed to add transport sector'),
})

const togglePackageSector = (sectorId: string, checked: boolean | 'indeterminate') => {
  packageForm.sector_ids = checked === true
    ? [...packageForm.sector_ids, sectorId]
    : packageForm.sector_ids.filter((id) => id !== sectorId)
}

const submitPackage = () => packageForm.post(`/${props.company.slug}/umrah/settings/transport-packages`, {
  preserveScroll: true,
  onSuccess: () => { packageForm.reset(); packageForm.sector_ids = []; toast.success('Journey package added successfully') },
  onError: () => toast.error('Failed to add journey package'),
})

const submitFare = () => {
  const [targetType, targetId] = fareForm.fare_target.split(':')
  fareForm.transform((data) => ({
    ...data,
    fare_target: undefined,
    transport_sector_id: targetType === 'sector' ? targetId : null,
    transport_package_id: targetType === 'package' ? targetId : null,
    sale_amount: Number(data.sale_amount || 0),
    cost_amount: Number(data.cost_amount || 0),
    hajj_terminal_sale_amount: Number(data.hajj_terminal_sale_amount || 0),
    hajj_terminal_cost_amount: Number(data.hajj_terminal_cost_amount || 0),
  })).post(`/${props.company.slug}/umrah/settings/transport-fares`, {
    preserveScroll: true,
    onSuccess: () => {
      fareForm.reset()
      fareForm.charging_basis = 'per_vehicle'
      fareForm.hajj_terminal_sale_amount = '90'
      toast.success('Transport fare added successfully')
    },
    onError: () => toast.error('Failed to add transport fare'),
  })
}

const removeCatalogRecord = (path: string, successMessage: string) => catalogRemoveForm.delete(`/${props.company.slug}/umrah/settings/${path}`, {
  preserveScroll: true,
  onSuccess: () => toast.success(successMessage),
  onError: () => toast.error('This record is in use and cannot be removed'),
})
</script>

<template>
  <Head title="Transport Services" />
  <PageShell title="Transport Services" description="Vehicles, sector fares, complete journeys, and terminal charges." :breadcrumbs="breadcrumbs" :icon="Bus">
    <div class="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
      <Card class="min-w-0">
        <CardHeader><CardTitle>{{ editingService ? 'Edit Transport Service' : 'Add Transport Service' }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Jeddah airport pickup" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>Default Driver</Label>
                <Select v-model="form.driver_id">
                  <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No default driver</SelectItem>
                    <SelectItem v-for="driver in drivers" :key="driver.id" :value="driver.id">
                      {{ driver.name }}<span v-if="driver.phone"> · {{ driver.phone }}</span>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2"><Label>Vehicle Type</Label><Input v-model="form.vehicle_type" placeholder="Car, 7-seater, coaster, bus" /></div>
              <div class="space-y-2"><Label>Pax Capacity</Label><Input v-model="form.pax_capacity" type="number" min="1" placeholder="7" /></div>
              <div class="space-y-2"><Label>Make</Label><Input v-model="form.make" placeholder="Toyota" /></div>
              <div class="space-y-2"><Label>Model</Label><Input v-model="form.model" placeholder="Hiace" /></div>
              <div class="space-y-2"><Label>Color</Label><Input v-model="form.color" /></div>
              <div class="space-y-2"><Label>Number Plate</Label><Input v-model="form.number_plate" /></div>
              <div class="space-y-2"><Label>Driver</Label><Input v-model="form.driver_name" /></div>
              <div class="space-y-2"><Label>Driver Contact</Label><Input v-model="form.driver_contact" /></div>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <div class="grid gap-2 sm:grid-cols-2">
              <Button v-if="editingService" type="button" variant="outline" @click="resetForm"><X class="mr-2 h-4 w-4" />Cancel</Button>
              <Button type="submit" :class="editingService ? '' : 'sm:col-span-2'" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />{{ editingService ? 'Save Changes' : 'Save Transport' }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card class="min-w-0">
        <CardHeader><CardTitle>Available Transport</CardTitle></CardHeader>
        <CardContent class="p-0">
          <Table>
            <TableHeader><TableRow><TableHead>Service</TableHead><TableHead>Vehicle Type</TableHead><TableHead>Make</TableHead><TableHead>Model</TableHead><TableHead class="text-center">Capacity</TableHead><TableHead>Plate</TableHead><TableHead>Driver</TableHead><TableHead>Contact</TableHead><TableHead class="w-24 text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>
              <TableEmpty v-if="!transportServices.length" :colspan="9">No transport services yet.</TableEmpty>
              <TableRow v-for="service in transportServices" :key="service.id">
                <TableCell class="font-medium">{{ service.name }}</TableCell>
                <TableCell>{{ service.vehicle_type || '-' }}</TableCell>
                <TableCell>{{ service.make || '-' }}</TableCell>
                <TableCell>{{ service.model || '-' }}</TableCell>
                <TableCell class="text-center">{{ service.pax_capacity || '-' }}</TableCell>
                <TableCell>{{ service.number_plate || '-' }}</TableCell>
                <TableCell>{{ service.driver?.name || service.driver_name || '-' }}</TableCell>
                <TableCell>{{ service.driver?.phone || service.driver_contact || '-' }}</TableCell>
                <TableCell><div class="flex justify-end gap-1"><Button type="button" variant="ghost" size="icon" @click="startEdit(service)"><Pencil class="h-4 w-4" /><span class="sr-only">Edit {{ service.name }}</span></Button><Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeService(service)"><Trash2 class="h-4 w-4" /><span class="sr-only">Remove {{ service.name }}</span></Button></div></TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
      <Card class="min-w-0">
        <CardHeader><CardTitle class="flex items-center gap-2"><Map class="h-4 w-4" />Transport Sectors</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitSector">
            <div class="space-y-2"><Label>Code</Label><Input v-model="sectorForm.code" placeholder="JED-MAK" required /></div>
            <div class="space-y-2"><Label>Name</Label><Input v-model="sectorForm.name" placeholder="Airport to Makkah" required /></div>
            <div class="space-y-2"><Label>Origin</Label><Input v-model="sectorForm.origin" placeholder="Jeddah Airport" required /></div>
            <div class="space-y-2"><Label>Destination</Label><Input v-model="sectorForm.destination" placeholder="Makkah Hotel" required /></div>
            <Button type="submit" class="sm:col-span-2" :disabled="sectorForm.processing"><Plus class="mr-2 h-4 w-4" />Add Sector</Button>
          </form>
          <Table>
            <TableHeader><TableRow><TableHead>Code</TableHead><TableHead>Sector</TableHead><TableHead>Origin</TableHead><TableHead>Destination</TableHead><TableHead class="w-16 text-right">Action</TableHead></TableRow></TableHeader>
            <TableBody><TableEmpty v-if="!sectors.length" :colspan="5">No transport sectors yet.</TableEmpty><TableRow v-for="sector in sectors" :key="sector.id"><TableCell class="font-medium">{{ sector.code }}</TableCell><TableCell>{{ sector.name }}</TableCell><TableCell>{{ sector.origin }}</TableCell><TableCell>{{ sector.destination }}</TableCell><TableCell class="text-right"><Button type="button" variant="ghost" size="icon" :disabled="catalogRemoveForm.processing" @click="removeCatalogRecord(`transport-sectors/${sector.id}`, 'Transport sector removed successfully')"><Trash2 class="h-4 w-4" /><span class="sr-only">Remove {{ sector.name }}</span></Button></TableCell></TableRow></TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card class="min-w-0">
        <CardHeader><CardTitle class="flex items-center gap-2"><Package class="h-4 w-4" />Journey Packages</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <form class="space-y-3" @submit.prevent="submitPackage">
            <div class="space-y-2"><Label>Package Name</Label><Input v-model="packageForm.name" placeholder="Complete Umrah Journey" required /></div>
            <div class="space-y-2">
              <Label>Included Sectors</Label>
              <div class="grid gap-2 rounded-md border p-3 sm:grid-cols-2">
                <Label v-for="sector in sectors" :key="sector.id" class="flex cursor-pointer items-start gap-2 text-sm font-normal">
                  <Checkbox :model-value="packageForm.sector_ids.includes(sector.id)" @update:model-value="togglePackageSector(sector.id, $event)" />
                  <span>{{ sector.name }}</span>
                </Label>
              </div>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="packageForm.notes" /></div>
            <Button type="submit" class="w-full" :disabled="packageForm.processing || !packageForm.sector_ids.length"><Plus class="mr-2 h-4 w-4" />Add Journey Package</Button>
          </form>
          <Table>
            <TableHeader><TableRow><TableHead>Journey</TableHead><TableHead>Included Sectors</TableHead><TableHead class="w-16 text-right">Action</TableHead></TableRow></TableHeader>
            <TableBody><TableEmpty v-if="!packages.length" :colspan="3">No journey packages yet.</TableEmpty><TableRow v-for="journey in packages" :key="journey.id"><TableCell class="font-medium">{{ journey.name }}</TableCell><TableCell class="text-muted-foreground">{{ journey.sectors.map((sector) => sector.code).join(' → ') }}</TableCell><TableCell class="text-right"><Button type="button" variant="ghost" size="icon" :disabled="catalogRemoveForm.processing" @click="removeCatalogRecord(`transport-packages/${journey.id}`, 'Journey package removed successfully')"><Trash2 class="h-4 w-4" /><span class="sr-only">Remove {{ journey.name }}</span></Button></TableCell></TableRow></TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>

    <Card class="mt-6 min-w-0">
      <CardHeader><CardTitle>Sector and Journey Fares</CardTitle></CardHeader>
      <CardContent class="space-y-5">
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="submitFare">
          <div class="space-y-2"><Label>Fare Name</Label><Input v-model="fareForm.name" placeholder="Sedan complete journey" required /></div>
          <div class="space-y-2">
            <Label>Vehicle</Label>
            <Select v-model="fareForm.transport_service_id"><SelectTrigger><SelectValue placeholder="Select vehicle" /></SelectTrigger><SelectContent><SelectItem v-for="service in transportServices" :key="service.id" :value="service.id">{{ service.name }} · {{ service.vehicle_type || 'Vehicle' }}</SelectItem></SelectContent></Select>
          </div>
          <div class="space-y-2">
            <Label>Coverage</Label>
            <Select v-model="fareForm.fare_target"><SelectTrigger><SelectValue placeholder="Sector or complete journey" /></SelectTrigger><SelectContent>
              <SelectItem v-for="sector in sectors" :key="`sector:${sector.id}`" :value="`sector:${sector.id}`">Sector · {{ sector.name }}</SelectItem>
              <SelectItem v-for="journey in packages" :key="`package:${journey.id}`" :value="`package:${journey.id}`">Journey · {{ journey.name }}</SelectItem>
            </SelectContent></Select>
          </div>
          <div class="space-y-2">
            <Label>Charging Basis</Label>
            <Select v-model="fareForm.charging_basis"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem v-for="(label, value) in chargingBases" :key="value" :value="value">{{ label }}</SelectItem></SelectContent></Select>
          </div>
          <div class="space-y-2"><Label>Retail Fare</Label><Input v-model="fareForm.sale_amount" type="number" min="0" step="0.01" required /></div>
          <div class="space-y-2"><Label>Cost</Label><Input v-model="fareForm.cost_amount" type="number" min="0" step="0.01" required /></div>
          <div class="space-y-2"><Label>Hajj Terminal Extra</Label><Input v-model="fareForm.hajj_terminal_sale_amount" type="number" min="0" step="0.01" /></div>
          <div class="space-y-2"><Label>Hajj Terminal Extra Cost</Label><Input v-model="fareForm.hajj_terminal_cost_amount" type="number" min="0" step="0.01" /></div>
          <Button type="submit" class="md:col-span-2 xl:col-span-4" :disabled="fareForm.processing || !fareForm.transport_service_id || !fareForm.fare_target"><Save class="mr-2 h-4 w-4" />Save Fare</Button>
        </form>

        <Table>
          <TableHeader><TableRow><TableHead>Fare</TableHead><TableHead>Vehicle</TableHead><TableHead>Coverage</TableHead><TableHead>Basis</TableHead><TableHead class="text-right">Retail</TableHead><TableHead class="text-right">Cost</TableHead><TableHead class="text-right">Hajj Extra</TableHead><TableHead class="w-16 text-right">Action</TableHead></TableRow></TableHeader>
          <TableBody><TableEmpty v-if="!fares.length" :colspan="8">No sector or journey fares yet.</TableEmpty><TableRow v-for="fare in fares" :key="fare.id"><TableCell class="font-medium">{{ fare.name }}</TableCell><TableCell>{{ fare.service?.name || '-' }}</TableCell><TableCell>{{ fare.sector?.name || fare.package?.name || '-' }}</TableCell><TableCell>{{ chargingBases[fare.charging_basis] }}</TableCell><TableCell class="text-right"><MoneyText :amount="fare.sale_amount" :currency="company.base_currency" /></TableCell><TableCell class="text-right"><MoneyText :amount="fare.cost_amount" :currency="company.base_currency" /></TableCell><TableCell class="text-right"><MoneyText :amount="fare.hajj_terminal_sale_amount" :currency="company.base_currency" /></TableCell><TableCell class="text-right"><Button type="button" variant="ghost" size="icon" :disabled="catalogRemoveForm.processing" @click="removeCatalogRecord(`transport-fares/${fare.id}`, 'Transport fare removed successfully')"><Trash2 class="h-4 w-4" /><span class="sr-only">Remove {{ fare.name }}</span></Button></TableCell></TableRow></TableBody>
        </Table>
      </CardContent>
    </Card>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove Transport Service"
      :description="`Remove ${serviceToRemove?.name || 'this transport'} from future groups? Existing groups keep their history.`"
      confirm-text="Remove Transport"
      :loading="removeForm.processing"
      @confirm="confirmRemoveService"
    />
  </PageShell>
</template>
