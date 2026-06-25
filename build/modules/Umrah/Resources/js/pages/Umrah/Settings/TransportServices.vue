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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Bus, Pencil, Save, Trash2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  transportServices: any[]
  drivers: any[]
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
</script>

<template>
  <Head title="Transport Services" />
  <PageShell title="Transport Services" description="Vehicles, drivers, and default transport charges for groups." :breadcrumbs="breadcrumbs" :icon="Bus">
    <div class="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
      <Card>
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
              <div class="space-y-2"><Label>Default Charge</Label><Input v-model="form.default_sale_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Default Cost</Label><Input v-model="form.default_cost_amount" type="number" min="0" step="0.01" /></div>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <div class="grid gap-2 sm:grid-cols-2">
              <Button v-if="editingService" type="button" variant="outline" @click="resetForm"><X class="mr-2 h-4 w-4" />Cancel</Button>
              <Button type="submit" :class="editingService ? '' : 'sm:col-span-2'" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />{{ editingService ? 'Save Changes' : 'Save Transport' }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Available Transport</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!transportServices.length" class="text-sm text-muted-foreground">No transport services yet.</div>
          <div v-for="service in transportServices" :key="service.id" class="grid gap-3 rounded-md border p-3 lg:grid-cols-[1fr_150px_150px_auto]">
            <div>
              <div class="font-medium">{{ service.name }}</div>
              <div class="text-sm text-muted-foreground">
                {{ service.vehicle_type || 'No type' }}
                <span v-if="service.pax_capacity"> · {{ service.pax_capacity }} pax</span>
                <span v-if="service.make || service.model"> · {{ [service.make, service.model].filter(Boolean).join(' ') }}</span>
                <span v-if="service.number_plate"> · {{ service.number_plate }}</span>
              </div>
              <div class="text-sm text-muted-foreground">
                {{ service.driver?.name || service.driver_name || 'No driver' }}<span v-if="service.driver?.phone || service.driver_contact"> · {{ service.driver?.phone || service.driver_contact }}</span>
              </div>
            </div>
            <div><div class="text-xs text-muted-foreground">Charge</div><MoneyText :amount="service.default_sale_amount" :currency="company.base_currency" /></div>
            <div><div class="text-xs text-muted-foreground">Cost</div><MoneyText :amount="service.default_cost_amount" :currency="company.base_currency" /></div>
            <div class="flex items-center justify-end gap-1">
              <Button type="button" variant="ghost" size="icon" @click="startEdit(service)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeService(service)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

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
