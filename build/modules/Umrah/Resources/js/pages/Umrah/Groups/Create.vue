<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
  transportServices: any[]
  drivers: any[]
  statuses: Record<string, string>
  passengerStatuses: Record<string, string>
  countries: Record<string, string>
}>()

const page = usePage()
const currentRole = computed(() => (page.props.auth as any)?.currentCompanyRole || null)
const canViewAccounting = computed(() => ['super_admin', 'owner', 'accountant'].includes(String(currentRole.value)))

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
  { title: 'New Group', href: `/${props.company.slug}/umrah/groups/create` },
]

const form = useForm({
  group_number: props.nextGroupNumber,
  name: '',
  agent_id: '',
  vendor_id: 'none',
  status: 'passports_received',
  travel_date: '',
  transport_required: true,
  transport_service_id: 'none',
  driver_id: 'none',
  transport_quantity: '1',
  transport_pax_capacity: '',
  passenger_count: '0',
  visa_sale_amount: '0',
  transport_amount: '0',
  discount_amount: '0',
  visa_cost_amount: '0',
  transport_cost_amount: '0',
  notes: '',
  passengers: [
    { full_name: '', passport_number: '', date_of_birth: '', nationality: 'Pakistan', visa_status: 'received' },
  ],
})

const quickAgentOpen = ref(false)
const quickVendorOpen = ref(false)
const passengerBulkText = ref('')

const agentForm = useForm({
  agent_number: '',
  name: '',
  phone: '',
  email: '',
  city: '',
  country: 'Pakistan',
  notes: '',
})

const vendorForm = useForm({
  vendor_number: '',
  name: '',
  vendor_type: 'visa_provider',
  phone: '',
  email: '',
  city: '',
  adult_retail_amount: '0',
  adult_cost_amount: '0',
  child_retail_amount: '0',
  child_cost_amount: '0',
  infant_retail_amount: '0',
  infant_cost_amount: '0',
  notes: '',
})

const sameAmount = (first: string | number | null | undefined, second: string | number | null | undefined) => Number(first || 0) === Number(second || 0)

watch(() => vendorForm.adult_retail_amount, (value, oldValue) => {
  if (sameAmount(vendorForm.child_retail_amount, oldValue)) vendorForm.child_retail_amount = value
  if (sameAmount(vendorForm.infant_retail_amount, oldValue)) vendorForm.infant_retail_amount = value
})

watch(() => vendorForm.adult_cost_amount, (value, oldValue) => {
  if (sameAmount(vendorForm.child_cost_amount, oldValue)) vendorForm.child_cost_amount = value
  if (sameAmount(vendorForm.infant_cost_amount, oldValue)) vendorForm.infant_cost_amount = value
})

const receivable = computed(() => {
  return Math.max(Number(form.visa_sale_amount || 0) + Number(form.transport_amount || 0) - Number(form.discount_amount || 0), 0)
})
const profit = computed(() => receivable.value - Number(form.visa_cost_amount || 0) - Number(form.transport_cost_amount || 0))
const selectedTransport = computed(() => props.transportServices.find((item) => item.id === form.transport_service_id))
const totalTransportCapacity = computed(() => Number(form.transport_quantity || 0) * Number(form.transport_pax_capacity || 0))
const selectedAgent = computed(() => props.agents.find((item) => item.id === form.agent_id))
const selectedVendor = computed(() => props.vendors.find((item) => item.id === form.vendor_id))
const defaultNationality = computed(() => selectedAgent.value?.country || 'Pakistan')

const normalizeDate = (value: string | null | undefined) => String(value || '').slice(0, 10)

const ageBand = (dateOfBirth: string | null | undefined) => {
  const normalizedBirthDate = normalizeDate(dateOfBirth)

  if (!normalizedBirthDate) return 'adult'

  const birthDate = new Date(`${normalizedBirthDate}T00:00:00`)
  const normalizedTravelDate = normalizeDate(form.travel_date)
  const referenceDate = normalizedTravelDate ? new Date(`${normalizedTravelDate}T00:00:00`) : new Date()

  if (Number.isNaN(birthDate.getTime()) || Number.isNaN(referenceDate.getTime())) return 'adult'

  let age = referenceDate.getFullYear() - birthDate.getFullYear()
  const monthDelta = referenceDate.getMonth() - birthDate.getMonth()

  if (monthDelta < 0 || (monthDelta === 0 && referenceDate.getDate() < birthDate.getDate())) {
    age -= 1
  }

  if (age < 2) return 'infant'
  if (age < 12) return 'child'
  return 'adult'
}

const calculateVisaPricing = () => {
  const vendor = selectedVendor.value

  if (!vendor) {
    return { sale: 0, cost: 0 }
  }

  const namedPassengers = form.passengers.filter((passenger) => passenger.full_name.trim() !== '')
  const passengersForPricing = namedPassengers.length > 0
    ? namedPassengers
    : Array.from({ length: Math.max(Number(form.passenger_count || 0), 0) }, () => ({ date_of_birth: '' }))

  return passengersForPricing.reduce((totals, passenger) => {
    const band = ageBand(passenger.date_of_birth)
    return {
      sale: totals.sale + Number(vendor[`${band}_retail_amount`] || 0),
      cost: totals.cost + Number(vendor[`${band}_cost_amount`] || 0),
    }
  }, { sale: 0, cost: 0 })
}

const updateVisaPricing = () => {
  const pricing = calculateVisaPricing()
  form.visa_sale_amount = String(pricing.sale.toFixed(2))
  form.visa_cost_amount = String(pricing.cost.toFixed(2))
}

watch(() => form.agent_id, () => {
  const nationality = defaultNationality.value
  form.passengers.forEach((passenger) => {
    if (!passenger.nationality || passenger.nationality === 'Pakistan') {
      passenger.nationality = nationality
    }
  })
  updateVisaPricing()
})

watch(() => [form.vendor_id, form.travel_date, form.passenger_count, form.passengers], updateVisaPricing, { deep: true })

watch(() => form.transport_service_id, (id) => {
  if (!id || id === 'none') return

  const service = props.transportServices.find((item) => item.id === id)
  if (!service) return

  form.transport_amount = String(Number(service.default_sale_amount || 0))
  form.transport_cost_amount = String(Number(service.default_cost_amount || 0))
  form.transport_pax_capacity = service.pax_capacity ? String(service.pax_capacity) : ''
  if (service.driver_id) {
    form.driver_id = service.driver_id
  }
  if (Number(form.transport_quantity || 0) <= 0) {
    form.transport_quantity = '1'
  }
})

const addPassenger = () => {
  form.passengers.push({ full_name: '', passport_number: '', date_of_birth: '', nationality: defaultNationality.value, visa_status: 'received' })
}

const importPassengers = () => {
  const rows = passengerBulkText.value
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)

  if (form.passengers.length === 1 && !form.passengers[0].full_name.trim()) {
    form.passengers.splice(0, 1)
  }

  rows.forEach((line) => {
    const [name, passportNumber, dateOfBirth, nationality] = line.split(/[\t,]/).map((part) => part?.trim() || '')
    if (!name) return

    form.passengers.push({
      full_name: name,
      passport_number: passportNumber || '',
      date_of_birth: dateOfBirth || '',
      nationality: nationality || defaultNationality.value,
      visa_status: 'received',
    })
  })

  passengerBulkText.value = ''
}

const removePassenger = (index: number) => {
  form.passengers.splice(index, 1)
}

const createAgent = () => {
  agentForm.post(`/${props.company.slug}/umrah/agents/quick-store`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Agent created successfully')
      agentForm.reset()
      agentForm.country = 'Pakistan'
      quickAgentOpen.value = false
      router.reload({ only: ['agents'] })
    },
    onError: () => toast.error('Failed to create agent'),
  })
}

const createVendor = () => {
  vendorForm.post(`/${props.company.slug}/umrah/vendors/quick-store`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Visa vendor created successfully')
      vendorForm.reset()
      vendorForm.vendor_type = 'visa_provider'
      vendorForm.adult_retail_amount = '0'
      vendorForm.adult_cost_amount = '0'
      vendorForm.child_retail_amount = '0'
      vendorForm.child_cost_amount = '0'
      vendorForm.infant_retail_amount = '0'
      vendorForm.infant_cost_amount = '0'
      quickVendorOpen.value = false
      router.reload({ only: ['vendors'] })
    },
    onError: () => toast.error('Failed to create visa vendor'),
  })
}

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      vendor_id: data.vendor_id === 'none' ? null : data.vendor_id,
      visa_service_id: null,
      transport_service_id: data.transport_service_id === 'none' ? null : data.transport_service_id,
      driver_id: data.driver_id === 'none' ? null : data.driver_id,
      transport_required: true,
      transport_quantity: Number(data.transport_quantity || 0),
      transport_pax_capacity: data.transport_pax_capacity ? Number(data.transport_pax_capacity) : null,
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
  <PageShell title="New Visa Group" description="Create the group, pricing, transport need, and starting passport list." :breadcrumbs="breadcrumbs" :icon="Plane">
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
                <div class="flex items-center justify-between gap-3">
                  <Label>Agent</Label>
                  <Button type="button" variant="ghost" size="sm" @click="quickAgentOpen = !quickAgentOpen">
                    <Plus class="mr-2 h-4 w-4" />
                    New Agent
                  </Button>
                </div>
                <Select v-model="form.agent_id">
                  <SelectTrigger><SelectValue placeholder="Select agent" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }} · {{ agent.agent_number }}</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.agent_id" class="text-xs text-destructive">{{ form.errors.agent_id }}</p>
              </div>
              <div v-if="quickAgentOpen" class="space-y-3 rounded-md border p-3 md:col-span-2">
                <div class="grid gap-3 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label>Agent Name</Label>
                    <Input v-model="agentForm.name" placeholder="Agent or company name" />
                    <p v-if="agentForm.errors.name" class="text-xs text-destructive">{{ agentForm.errors.name }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label>Phone</Label>
                    <Input v-model="agentForm.phone" />
                  </div>
                  <div class="space-y-2">
                    <Label>Email</Label>
                    <Input v-model="agentForm.email" type="email" />
                  </div>
                  <div class="space-y-2">
                    <Label>Default Nationality</Label>
                    <Select v-model="agentForm.country">
                      <SelectTrigger><SelectValue placeholder="Nationality" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="(label, value) in countries" :key="value" :value="value">{{ label }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <div class="flex justify-end gap-2">
                  <Button type="button" variant="outline" :disabled="agentForm.processing" @click="quickAgentOpen = false">Cancel</Button>
                  <Button type="button" :disabled="agentForm.processing || !agentForm.name" @click="createAgent">Save Agent</Button>
                </div>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                  <Label>Visa Vendor</Label>
                  <Button type="button" variant="ghost" size="sm" @click="quickVendorOpen = !quickVendorOpen">
                    <Plus class="mr-2 h-4 w-4" />
                    New Vendor
                  </Button>
                </div>
                <Select v-model="form.vendor_id">
                  <SelectTrigger><SelectValue placeholder="Select visa vendor" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Select vendor</SelectItem>
                    <SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
                      {{ vendor.name }} · Adult {{ Number(vendor.adult_retail_amount || 0).toLocaleString() }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.vendor_id" class="text-xs text-destructive">{{ form.errors.vendor_id }}</p>
                <div v-if="selectedVendor" class="rounded-md border p-3 text-xs text-muted-foreground">
                  Adult {{ Number(selectedVendor.adult_retail_amount || 0).toLocaleString() }} / {{ Number(selectedVendor.adult_cost_amount || 0).toLocaleString() }}
                  · Child {{ Number(selectedVendor.child_retail_amount || 0).toLocaleString() }} / {{ Number(selectedVendor.child_cost_amount || 0).toLocaleString() }}
                  · Infant {{ Number(selectedVendor.infant_retail_amount || 0).toLocaleString() }} / {{ Number(selectedVendor.infant_cost_amount || 0).toLocaleString() }}
                </div>
              </div>
              <div v-if="quickVendorOpen" class="space-y-3 rounded-md border p-3 md:col-span-2">
                <div class="grid gap-3 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label>Vendor Name</Label>
                    <Input v-model="vendorForm.name" placeholder="Visa provider name" />
                    <p v-if="vendorForm.errors.name" class="text-xs text-destructive">{{ vendorForm.errors.name }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label>Type</Label>
                    <Select v-model="vendorForm.vendor_type">
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="government">Government</SelectItem>
                        <SelectItem value="visa_provider">Visa Provider</SelectItem>
                        <SelectItem value="transport_provider">Transport Provider</SelectItem>
                        <SelectItem value="hotel">Hotel</SelectItem>
                        <SelectItem value="other">Other</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-2">
                    <Label>Phone</Label>
                    <Input v-model="vendorForm.phone" />
                  </div>
                  <div class="space-y-2">
                    <Label>Email</Label>
                    <Input v-model="vendorForm.email" type="email" />
                  </div>
                  <div class="space-y-2">
                    <Label>City</Label>
                    <Input v-model="vendorForm.city" />
                  </div>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                  <div class="space-y-3 rounded-md border p-3">
                    <div class="font-medium">Adult</div>
                    <div class="space-y-2"><Label>Retail</Label><Input v-model="vendorForm.adult_retail_amount" type="number" min="0" step="0.01" /></div>
                    <div class="space-y-2"><Label>Cost</Label><Input v-model="vendorForm.adult_cost_amount" type="number" min="0" step="0.01" /></div>
                  </div>
                  <div class="space-y-3 rounded-md border p-3">
                    <div class="font-medium">Child</div>
                    <div class="space-y-2"><Label>Retail</Label><Input v-model="vendorForm.child_retail_amount" type="number" min="0" step="0.01" /></div>
                    <div class="space-y-2"><Label>Cost</Label><Input v-model="vendorForm.child_cost_amount" type="number" min="0" step="0.01" /></div>
                  </div>
                  <div class="space-y-3 rounded-md border p-3">
                    <div class="font-medium">Infant</div>
                    <div class="space-y-2"><Label>Retail</Label><Input v-model="vendorForm.infant_retail_amount" type="number" min="0" step="0.01" /></div>
                    <div class="space-y-2"><Label>Cost</Label><Input v-model="vendorForm.infant_cost_amount" type="number" min="0" step="0.01" /></div>
                  </div>
                </div>
                <div class="flex justify-end gap-2">
                  <Button type="button" variant="outline" :disabled="vendorForm.processing" @click="quickVendorOpen = false">Cancel</Button>
                  <Button type="button" :disabled="vendorForm.processing || !vendorForm.name" @click="createVendor">Save Vendor</Button>
                </div>
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
              <div class="space-y-2 md:col-span-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Transport Required</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
              <div class="space-y-2">
                <Label>Transport Service</Label>
                <Select v-model="form.transport_service_id">
                  <SelectTrigger><SelectValue placeholder="Select transport" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Not selected</SelectItem>
                    <SelectItem v-for="service in transportServices" :key="service.id" :value="service.id">
                      {{ service.name }}<span v-if="service.pax_capacity"> · {{ service.pax_capacity }} pax</span>
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.transport_service_id" class="text-xs text-destructive">{{ form.errors.transport_service_id }}</p>
              </div>
              <div class="space-y-2">
                <Label>Vehicles</Label>
                <Input v-model="form.transport_quantity" type="number" min="1" />
                <p v-if="form.errors.transport_quantity" class="text-xs text-destructive">{{ form.errors.transport_quantity }}</p>
              </div>
              <div class="space-y-2">
                <Label>Pax per Vehicle</Label>
                <Input v-model="form.transport_pax_capacity" type="number" min="1" />
              </div>
              <div class="space-y-2">
                <Label>Driver</Label>
                <Select v-model="form.driver_id">
                  <SelectTrigger><SelectValue placeholder="Select driver" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No driver assigned</SelectItem>
                    <SelectItem v-for="driver in drivers" :key="driver.id" :value="driver.id">
                      {{ driver.name }}<span v-if="driver.phone"> · {{ driver.phone }}</span>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-if="selectedTransport" class="rounded-md border p-3 text-sm text-muted-foreground md:col-span-3">
                {{ selectedTransport.vehicle_type || 'Vehicle' }}
                <span v-if="selectedTransport.make || selectedTransport.model"> · {{ [selectedTransport.make, selectedTransport.model].filter(Boolean).join(' ') }}</span>
                <span v-if="selectedTransport.number_plate"> · {{ selectedTransport.number_plate }}</span>
                <span v-if="selectedTransport.driver?.name || selectedTransport.driver_name"> · Default driver: {{ selectedTransport.driver?.name || selectedTransport.driver_name }}</span>
                <span v-if="totalTransportCapacity"> · Capacity: {{ totalTransportCapacity }} pax total</span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Passengers</CardTitle></CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-md border p-3">
                <div class="space-y-2">
                  <Label>Paste Passenger List</Label>
                  <Textarea
                    v-model="passengerBulkText"
                    placeholder="One passenger per line. Use: Full name, Passport #, Date of birth, Nationality"
                  />
                </div>
                <div class="mt-3 flex justify-end">
                  <Button type="button" variant="outline" :disabled="!passengerBulkText.trim()" @click="importPassengers">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Pasted Passengers
                  </Button>
                </div>
              </div>
              <div v-for="(passenger, index) in form.passengers" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_150px_150px_130px_130px_40px]">
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Full Name</Label>
                  <Input v-model="passenger.full_name" placeholder="Full name" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Passport #</Label>
                  <Input v-model="passenger.passport_number" placeholder="Passport #" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Date of Birth</Label>
                  <Input v-model="passenger.date_of_birth" type="date" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Nationality</Label>
                  <Select v-model="passenger.nationality">
                    <SelectTrigger><SelectValue placeholder="Nationality" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="(label, value) in countries" :key="value" :value="value">{{ label }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Visa Status</Label>
                  <Select v-model="passenger.visa_status">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="(label, value) in passengerStatuses" :key="value" :value="value">{{ label }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="flex items-end">
                  <Button type="button" variant="ghost" size="icon" @click="removePassenger(index)"><Trash2 class="h-4 w-4" /></Button>
                </div>
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
              <div v-if="canViewAccounting" class="space-y-2"><Label>Visa Cost</Label><Input v-model="form.visa_cost_amount" type="number" min="0" step="0.01" /></div>
              <div v-if="canViewAccounting" class="space-y-2"><Label>Transport Cost</Label><Input v-model="form.transport_cost_amount" type="number" min="0" step="0.01" /></div>
              <div class="rounded-md border p-3">
                <div class="flex justify-between text-sm"><span>Receivable</span><MoneyText :amount="receivable" :currency="company.base_currency" /></div>
                <div v-if="canViewAccounting" class="mt-2 flex justify-between font-semibold"><span>Profit</span><MoneyText :amount="profit" :currency="company.base_currency" /></div>
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
