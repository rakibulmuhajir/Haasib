<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Plus, Plane, Save, Trash2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  nextGroupNumber: string
  agents: any[]
  vendors: any[]
  vehicleTypes: any[]
  visaServices: any[]
  transportServices: any[]
  statuses: Record<string, string>
  passengerStatuses: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
  { title: 'New Group', href: `/${props.company.slug}/umrah/groups/create` },
]

const transportEnabled = ref(false)

const form = useForm({
  group_number: props.nextGroupNumber,
  name: '',
  agent_id: '',
  vendor_id: 'none',
  visa_service_id: 'none',
  status: 'passports_received',
  travel_date: '',
  flight_airline: '',
  flight_number: '',
  flight_notes: '',
  hotel_makkah: '',
  hotel_madinah: '',
  hotel_notes: '',
  transport_required: false,
  transport_service_id: 'none',
  vehicle_type_id: 'none',
  transport_quantity: '0',
  passenger_count: '0',
  visa_sale_amount: '0',
  transport_amount: '0',
  discount_amount: '0',
  visa_cost_amount: '0',
  transport_cost_amount: '0',
  notes: '',
  passengers: [
    { full_name: '', passport_number: '', nationality: '', visa_status: 'received' },
  ],
})

const receivable = computed(() => {
  return Math.max(Number(form.visa_sale_amount || 0) + Number(form.transport_amount || 0) - Number(form.discount_amount || 0), 0)
})
const profit = computed(() => receivable.value - Number(form.visa_cost_amount || 0) - Number(form.transport_cost_amount || 0))

watch(() => form.visa_service_id, (id) => {
  if (!id || id === 'none') return

  const service = props.visaServices.find((item) => item.id === id)
  if (!service) return

  form.visa_sale_amount = String(Number(service.retail_amount || 0))
  form.visa_cost_amount = String(Number(service.cost_amount || 0))
  if (service.vendor_id && form.vendor_id === 'none') {
    form.vendor_id = service.vendor_id
  }
})

watch(() => form.transport_service_id, (id) => {
  if (!id || id === 'none') return

  const service = props.transportServices.find((item) => item.id === id)
  if (!service) return

  transportEnabled.value = true
  form.transport_amount = String(Number(service.default_sale_amount || 0))
  form.transport_cost_amount = String(Number(service.default_cost_amount || 0))
  if (service.vehicle_type_id) {
    form.vehicle_type_id = service.vehicle_type_id
  }
  if (Number(form.transport_quantity || 0) <= 0) {
    form.transport_quantity = '1'
  }
})

const addPassenger = () => {
  form.passengers.push({ full_name: '', passport_number: '', nationality: '', visa_status: 'received' })
}

const removePassenger = (index: number) => {
  form.passengers.splice(index, 1)
}

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      vendor_id: data.vendor_id === 'none' ? null : data.vendor_id,
      visa_service_id: data.visa_service_id === 'none' ? null : data.visa_service_id,
      transport_service_id: data.transport_service_id === 'none' ? null : data.transport_service_id,
      vehicle_type_id: data.vehicle_type_id === 'none' ? null : data.vehicle_type_id,
      transport_required: transportEnabled.value,
      transport_quantity: Number(data.transport_quantity || 0),
      passenger_count: Number(data.passenger_count || 0),
      visa_sale_amount: Number(data.visa_sale_amount || 0),
      transport_amount: Number(data.transport_amount || 0),
      discount_amount: Number(data.discount_amount || 0),
      visa_cost_amount: Number(data.visa_cost_amount || 0),
      transport_cost_amount: Number(data.transport_cost_amount || 0),
      passengers: data.passengers.filter((p) => p.full_name.trim() !== ''),
    }))
    .post(`/${props.company.slug}/umrah/groups`, {
      onSuccess: () => toast.success('Visa group created successfully'),
      onError: () => toast.error('Failed to create visa group'),
    })
}
</script>

<template>
  <Head title="New Visa Group" />
  <PageShell title="New Visa Group" description="Create the group, financials, travel info, transport need, and starting passport list." :breadcrumbs="breadcrumbs" :icon="Plane">
    <form class="space-y-6" @submit.prevent="submit">
      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
          <Card>
            <CardHeader><CardTitle>Group</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Group #</Label>
                <Input v-model="form.group_number" />
              </div>
              <div class="space-y-2">
                <Label>Group Name</Label>
                <Input v-model="form.name" placeholder="Ramzan Group 1" required />
                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
              </div>
              <div class="space-y-2">
                <Label>Agent</Label>
                <Select v-model="form.agent_id">
                  <SelectTrigger><SelectValue placeholder="Select agent" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }} · {{ agent.agent_number }}</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.agent_id" class="text-xs text-destructive">{{ form.errors.agent_id }}</p>
              </div>
              <div class="space-y-2">
                <Label>Visa Vendor</Label>
                <Select v-model="form.vendor_id">
                  <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No vendor yet</SelectItem>
                    <SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Visa Service</Label>
                <Select v-model="form.visa_service_id">
                  <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No service</SelectItem>
                    <SelectItem v-for="service in visaServices" :key="service.id" :value="service.id">
                      {{ service.name }} · Retail {{ Number(service.retail_amount || 0).toLocaleString() }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Status</Label>
                <Select v-model="form.status">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Travel Date</Label>
                <Input v-model="form.travel_date" type="date" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Flight and Hotel Info</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2"><Label>Airline</Label><Input v-model="form.flight_airline" /></div>
              <div class="space-y-2"><Label>Flight #</Label><Input v-model="form.flight_number" /></div>
              <div class="space-y-2"><Label>Makkah Hotel</Label><Input v-model="form.hotel_makkah" /></div>
              <div class="space-y-2"><Label>Madinah Hotel</Label><Input v-model="form.hotel_madinah" /></div>
              <div class="space-y-2 md:col-span-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Transport</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
              <div class="flex items-center gap-2">
                <Checkbox id="transport_required" v-model:checked="transportEnabled" />
                <Label for="transport_required">Transport required</Label>
              </div>
              <div class="space-y-2">
                <Label>Transport Service</Label>
                <Select v-model="form.transport_service_id" :disabled="!transportEnabled">
                  <SelectTrigger><SelectValue placeholder="Select transport" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Not selected</SelectItem>
                    <SelectItem v-for="service in transportServices" :key="service.id" :value="service.id">
                      {{ service.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Quantity</Label>
                <Input v-model="form.transport_quantity" type="number" min="0" :disabled="!transportEnabled" />
              </div>
              <div class="space-y-2">
                <Label>Vehicle Type</Label>
                <Select v-model="form.vehicle_type_id" :disabled="!transportEnabled">
                  <SelectTrigger><SelectValue placeholder="Auto from transport" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Not selected</SelectItem>
                    <SelectItem v-for="vehicle in vehicleTypes" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Passengers</CardTitle></CardHeader>
            <CardContent class="space-y-3">
              <div v-for="(passenger, index) in form.passengers" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_160px_130px_130px_40px]">
                <Input v-model="passenger.full_name" placeholder="Full name" />
                <Input v-model="passenger.passport_number" placeholder="Passport #" />
                <Input v-model="passenger.nationality" placeholder="Nationality" />
                <Select v-model="passenger.visa_status">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in passengerStatuses" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
                <Button type="button" variant="ghost" size="icon" @click="removePassenger(index)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button type="button" variant="outline" @click="addPassenger"><Plus class="mr-2 h-4 w-4" />Add Passenger</Button>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader><CardTitle>Amounts</CardTitle></CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2"><Label>Visa Sale</Label><Input v-model="form.visa_sale_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Transport Charge</Label><Input v-model="form.transport_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Discount</Label><Input v-model="form.discount_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Visa Cost</Label><Input v-model="form.visa_cost_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Transport Cost</Label><Input v-model="form.transport_cost_amount" type="number" min="0" step="0.01" /></div>
              <div class="rounded-md border p-3">
                <div class="flex justify-between text-sm"><span>Receivable</span><MoneyText :amount="receivable" :currency="company.base_currency" /></div>
                <div class="mt-2 flex justify-between font-semibold"><span>Profit</span><MoneyText :amount="profit" :currency="company.base_currency" /></div>
              </div>
            </CardContent>
          </Card>

          <div class="flex gap-2">
            <Button type="button" variant="outline" class="flex-1" @click="router.get(`/${company.slug}/umrah/groups`)">Cancel</Button>
            <Button type="submit" class="flex-1" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Group</Button>
          </div>
        </div>
      </div>
    </form>
  </PageShell>
</template>
