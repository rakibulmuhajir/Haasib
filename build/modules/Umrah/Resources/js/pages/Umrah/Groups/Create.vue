<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Plus, Plane, Save, Trash2, Upload } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

type PassengerFormRow = {
  full_name: string
  passport_number: string
  date_of_birth: string
  imported_age: string
  nationality: string
  visa_status: string
  service_type: string
  transport_charge_amount: string
}

type TransportItemFormRow = {
  transport_fare_id: string
  driver_id: string
  scheduled_at: string
  terminal: string
  quantity: string
  passenger_count: string
  notes: string
}

const props = defineProps<{
  company: { slug: string; base_currency: string }
  nextGroupNumber: string
  agents: any[]
  vendors: any[]
  transportFares: any[]
  statuses: Record<string, string>
  passengerStatuses: Record<string, string>
  passengerServiceTypes: Record<string, string>
  countries: Record<string, string>
}>()

const page = usePage()
const currentRole = computed(() => (page.props.auth as any)?.currentCompanyRole || null)
const canViewAccounting = computed(() => ['super_admin', 'owner', 'accountant'].includes(String(currentRole.value)))
const canManageSetup = computed(() => String(currentRole.value) !== 'member')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
  { title: 'New Group', href: `/${props.company.slug}/umrah/groups/create` },
]

const form = useForm({
  group_number: '',
  name: '',
  agent_id: props.agents.length === 1 ? props.agents[0].id : '',
  vendor_id: 'none',
  status: 'passports_received',
  travel_date: '',
  transport_required: true,
  transport_mode: 'standard_bus',
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
    { full_name: '', passport_number: '', date_of_birth: '', imported_age: '', nationality: 'Pakistan', visa_status: 'received', service_type: 'visa_transport', transport_charge_amount: '0' },
  ] as PassengerFormRow[],
  transport_items: [] as TransportItemFormRow[],
})

const quickAgentOpen = ref(false)
const quickVendorOpen = ref(false)
const importForm = useForm<{ mutamers_file: File | null }>({
  mutamers_file: null,
})
const appliedImportSignature = ref('')

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
  included_bus_cost_amount: '50',
  notes: '',
})

const sameAmount = (first: string | number | null | undefined, second: string | number | null | undefined) => Number(first || 0) === Number(second || 0)

watch(() => vendorForm.adult_retail_amount, (value, oldValue) => {
  if (sameAmount(vendorForm.child_retail_amount, oldValue)) vendorForm.child_retail_amount = value
})

watch(() => vendorForm.adult_cost_amount, (value, oldValue) => {
  if (sameAmount(vendorForm.child_cost_amount, oldValue)) vendorForm.child_cost_amount = value
})

const receivable = computed(() => {
  return Math.max(Number(form.visa_sale_amount || 0) + Number(form.transport_amount || 0) - Number(form.discount_amount || 0), 0)
})
const profit = computed(() => receivable.value - Number(form.visa_cost_amount || 0) - Number(form.transport_cost_amount || 0))
const selectedAgent = computed(() => props.agents.find((item) => item.id === form.agent_id))
const selectedVendor = computed(() => props.vendors.find((item) => item.id === form.vendor_id))
const defaultNationality = computed(() => selectedAgent.value?.country || 'Pakistan')
const namedPassengers = computed(() => form.passengers.filter((passenger) => passenger.full_name.trim() !== ''))
const visaPassengers = computed(() => namedPassengers.value.filter((passenger) => passenger.service_type !== 'transport_only'))
const includedBusDeduction = computed(() => form.transport_mode === 'specialized'
  ? Math.min(Number(calculateVisaPricing().cost || 0), visaPassengers.value.length * Number(selectedVendor.value?.included_bus_cost_amount || 0))
  : 0)

const fareFor = (fareId: string) => props.transportFares.find((fare) => fare.id === fareId)

const transportFareTotals = computed(() => form.transport_items.reduce((totals, item) => {
  const fare = fareFor(item.transport_fare_id)
  if (!fare || form.transport_mode !== 'specialized') return totals

  const quantity = Math.max(Number(item.quantity || 1), 1)
  const pax = Math.max(Number(item.passenger_count || namedPassengers.value.length || 1), 1)
  const factor = fare.charging_basis === 'per_passenger' ? pax : fare.charging_basis === 'flat_group' ? 1 : quantity
  const hajj = item.terminal === 'hajj'

  totals.sale += (Number(fare.sale_amount || 0) + (hajj ? Number(fare.hajj_terminal_sale_amount || 0) : 0)) * factor
  totals.cost += (Number(fare.cost_amount || 0) + (hajj ? Number(fare.hajj_terminal_cost_amount || 0) : 0)) * factor
  return totals
}, { sale: 0, cost: 0 }))

const transportOnlyCharges = computed(() => namedPassengers.value
  .filter((passenger) => passenger.service_type === 'transport_only')
  .reduce((total, passenger) => total + Number(passenger.transport_charge_amount || 0), 0))

const normalizeDate = (value: string | null | undefined) => String(value || '').slice(0, 10)

const ageBand = (passenger: { date_of_birth?: string | null; imported_age?: string | number | null }) => {
  const importedAge = passenger.imported_age !== null && passenger.imported_age !== undefined && passenger.imported_age !== ''
    ? Number(passenger.imported_age)
    : null
  const normalizedBirthDate = normalizeDate(passenger.date_of_birth)

  if (!normalizedBirthDate) return importedAge !== null && importedAge < 12 ? 'child' : 'adult'

  const birthDate = new Date(`${normalizedBirthDate}T00:00:00`)
  const normalizedTravelDate = normalizeDate(form.travel_date)
  const referenceDate = normalizedTravelDate ? new Date(`${normalizedTravelDate}T00:00:00`) : new Date()

  if (Number.isNaN(birthDate.getTime()) || Number.isNaN(referenceDate.getTime())) return 'adult'

  let dobAge = referenceDate.getFullYear() - birthDate.getFullYear()
  const monthDelta = referenceDate.getMonth() - birthDate.getMonth()

  if (monthDelta < 0 || (monthDelta === 0 && referenceDate.getDate() < birthDate.getDate())) {
    dobAge -= 1
  }

  if (dobAge < 12) return 'child'
  return 'adult'
}

const calculateVisaPricing = () => {
  const vendor = selectedVendor.value

  if (!vendor) {
    return { sale: 0, cost: 0 }
  }

  const passengersForPricing = namedPassengers.value.length > 0
    ? visaPassengers.value
    : Array.from({ length: Math.max(Number(form.passenger_count || 0), 0) }, () => ({ date_of_birth: '', imported_age: '' }))

  return passengersForPricing.reduce((totals, passenger) => {
    const band = ageBand(passenger)
    return {
      sale: totals.sale + Number(vendor[`${band}_retail_amount`] || 0),
      cost: totals.cost + Number(vendor[`${band}_cost_amount`] || 0),
    }
  }, { sale: 0, cost: 0 })
}

const updateVisaPricing = () => {
  const pricing = calculateVisaPricing()
  form.visa_sale_amount = String(pricing.sale.toFixed(2))
  form.visa_cost_amount = String((pricing.cost - includedBusDeduction.value).toFixed(2))
  form.transport_amount = String((transportFareTotals.value.sale + transportOnlyCharges.value).toFixed(2))
  form.transport_cost_amount = String(transportFareTotals.value.cost.toFixed(2))
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
watch(() => [form.transport_mode, form.transport_items], updateVisaPricing, { deep: true })

const addPassenger = () => {
  form.passengers.push({ full_name: '', passport_number: '', date_of_birth: '', imported_age: '', nationality: defaultNationality.value, visa_status: 'received', service_type: 'visa_transport', transport_charge_amount: '0' })
}

const addTransportItem = () => {
  form.transport_items.push({ transport_fare_id: '', driver_id: 'none', scheduled_at: '', terminal: 'standard', quantity: '1', passenger_count: String(namedPassengers.value.length || 1), notes: '' })
}

const removeTransportItem = (index: number) => form.transport_items.splice(index, 1)

const appendImportedMutamers = (rows: any[]) => {
  if (form.passengers.length === 1 && !form.passengers[0].full_name.trim()) {
    form.passengers.splice(0, 1)
  }

  rows.forEach((row) => {
    form.passengers.push({
      full_name: String(row.full_name || ''),
      passport_number: String(row.passport_number || ''),
      date_of_birth: '',
      imported_age: row.imported_age === null || row.imported_age === undefined ? '' : String(row.imported_age),
      nationality: row.nationality || defaultNationality.value,
      visa_status: row.visa_status || 'received',
      service_type: row.service_type || 'visa_transport',
      transport_charge_amount: String(row.transport_charge_amount || 0),
    })
  })

  form.passenger_count = String(form.passengers.filter((passenger) => passenger.full_name.trim() !== '').length)
  updateVisaPricing()
}

const importedMutamersFlash = computed(() => ((page.props.flash as any)?.umrahImportedMutamers || []) as any[])

watch(importedMutamersFlash, (rows) => {
  if (!rows.length) return

  const signature = JSON.stringify(rows)
  if (signature === appliedImportSignature.value) return

  appliedImportSignature.value = signature
  appendImportedMutamers(rows)
}, { immediate: true })

const handleMutamersFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  importForm.mutamers_file = target.files?.[0] || null
}

const importMutamers = () => {
  if (!importForm.mutamers_file) return

  importForm.post(`/${props.company.slug}/umrah/groups/import-mutamers`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      importForm.reset()
    },
    onError: () => toast.error('Failed to import mutamers'),
  })
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
      vendorForm.included_bus_cost_amount = '50'
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
      transport_mode: data.transport_mode,
      transport_quantity: Number(data.transport_quantity || 0),
      transport_pax_capacity: data.transport_pax_capacity ? Number(data.transport_pax_capacity) : null,
      passenger_count: Number(data.passenger_count || 0),
      visa_sale_amount: Number(data.visa_sale_amount || 0),
      transport_amount: Number(data.transport_amount || 0),
      discount_amount: Number(data.discount_amount || 0),
      visa_cost_amount: Number(data.visa_cost_amount || 0),
      transport_cost_amount: Number(data.transport_cost_amount || 0),
      passengers: data.passengers
        .filter((p) => p.full_name.trim() !== '')
        .map((passenger) => ({
          ...passenger,
          imported_age: passenger.imported_age === '' ? null : Number(passenger.imported_age),
          transport_charge_amount: Number(passenger.transport_charge_amount || 0),
        })),
      transport_items: data.transport_mode === 'specialized'
        ? data.transport_items.map((item) => ({
          ...item,
          driver_id: item.driver_id === 'none' ? null : item.driver_id,
          quantity: Number(item.quantity || 1),
          passenger_count: Number(item.passenger_count || 1),
          scheduled_at: item.scheduled_at || null,
        }))
        : [],
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
                <Input v-model="form.group_number" :placeholder="`Auto: ${nextGroupNumber}`" />
              </div>
              <div class="space-y-2">
                <Label>Group Name</Label>
                <Input v-model="form.name" placeholder="Auto: agent name, pax, date and time" />
                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                  <Label>Agent</Label>
                  <Button v-if="canManageSetup" type="button" variant="ghost" size="sm" @click="quickAgentOpen = !quickAgentOpen">
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
              <div v-if="quickAgentOpen && canManageSetup" class="space-y-3 rounded-md border p-3 md:col-span-2">
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
                  <Button v-if="canManageSetup" type="button" variant="ghost" size="sm" @click="quickVendorOpen = !quickVendorOpen">
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
                </div>
              </div>
              <div v-if="quickVendorOpen && canManageSetup" class="space-y-3 rounded-md border p-3 md:col-span-2">
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
                <div class="grid gap-3 md:grid-cols-2">
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
                </div>
                <div class="space-y-2">
                  <Label>Included Standard Bus Cost per Passenger</Label>
                  <Input v-model="vendorForm.included_bus_cost_amount" type="number" min="0" step="0.01" />
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
            <CardHeader><CardTitle>Transport</CardTitle></CardHeader>
            <CardContent class="space-y-4">
              <RadioGroup v-model="form.transport_mode" class="grid gap-3 md:grid-cols-2">
                <Label for="transport-standard" class="flex cursor-pointer items-start gap-3 rounded-md border p-4">
                  <RadioGroupItem id="transport-standard" value="standard_bus" />
                  <span><span class="block font-medium">Standard bus</span><span class="mt-1 block text-xs text-muted-foreground">Complete journey included with the visa cost.</span></span>
                </Label>
                <Label for="transport-specialized" class="flex cursor-pointer items-start gap-3 rounded-md border p-4">
                  <RadioGroupItem id="transport-specialized" value="specialized" />
                  <span><span class="block font-medium">Specialized transport</span><span class="mt-1 block text-xs text-muted-foreground">Choose complete-journey or sector fares by vehicle.</span></span>
                </Label>
              </RadioGroup>

              <div v-if="form.transport_mode === 'standard_bus'" class="rounded-md border p-3 text-sm">
                <div class="font-medium">Mandatory bus transport included</div>
                <div class="mt-1 text-muted-foreground">The vendor's included bus cost remains inside the visa cost. No separate transport charge is added.</div>
              </div>

              <div v-else class="space-y-3">
                <div v-if="!transportFares.length" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                  No specialized fares are configured. Add vehicle sector or complete-journey fares in Transport Services first.
                </div>
                <div v-for="(item, index) in form.transport_items" :key="index" class="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(220px,1fr)_130px_130px_160px_170px_40px]">
                  <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">Journey Fare</Label>
                    <Select v-model="item.transport_fare_id">
                      <SelectTrigger><SelectValue placeholder="Select sector or journey" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="fare in transportFares" :key="fare.id" :value="fare.id">
                          {{ fare.name }} · {{ fare.service?.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-1"><Label class="text-xs text-muted-foreground">Vehicles</Label><Input v-model="item.quantity" type="number" min="1" /></div>
                  <div class="space-y-1"><Label class="text-xs text-muted-foreground">Passengers</Label><Input v-model="item.passenger_count" type="number" min="1" /></div>
                  <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">Terminal</Label>
                    <Select v-model="item.terminal">
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent><SelectItem value="standard">Standard terminal</SelectItem><SelectItem value="hajj">Hajj Terminal</SelectItem></SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-1"><Label class="text-xs text-muted-foreground">Schedule</Label><Input v-model="item.scheduled_at" type="datetime-local" /></div>
                  <div class="flex items-end"><Button type="button" variant="ghost" size="icon" @click="removeTransportItem(index)"><Trash2 class="h-4 w-4" /></Button></div>
                  <div v-if="fareFor(item.transport_fare_id)" class="text-xs text-muted-foreground lg:col-span-6">
                    {{ fareFor(item.transport_fare_id)?.sector?.name || fareFor(item.transport_fare_id)?.package?.name }} · {{ fareFor(item.transport_fare_id)?.service?.vehicle_type || 'Vehicle' }} · {{ fareFor(item.transport_fare_id)?.charging_basis?.replaceAll('_', ' ') }}
                    <span v-if="item.terminal === 'hajj'"> · Hajj Terminal surcharge applied</span>
                  </div>
                </div>
                <Button type="button" variant="outline" :disabled="!transportFares.length" @click="addTransportItem"><Plus class="mr-2 h-4 w-4" />Add Journey or Sector</Button>
                <p v-if="form.errors.transport_items" class="text-xs text-destructive">{{ form.errors.transport_items }}</p>
                <div class="rounded-md border p-3 text-sm">
                  <div class="flex justify-between"><span>Included bus cost removed from visa cost</span><MoneyText :amount="includedBusDeduction" :currency="company.base_currency" /></div>
                  <div class="mt-1 text-xs text-muted-foreground">The selected specialized fare cost is added below as transport cost.</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Passengers</CardTitle></CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-md border p-3">
                <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                  <div class="space-y-2">
                    <Label>Go VT Mutamers Sheet</Label>
                    <Input type="file" accept=".xlsx" @change="handleMutamersFileChange" />
                    <p v-if="importForm.errors.mutamers_file" class="text-xs text-destructive">{{ importForm.errors.mutamers_file }}</p>
                  </div>
                  <Button type="button" variant="outline" :disabled="!importForm.mutamers_file || importForm.processing" @click="importMutamers">
                    <Upload class="mr-2 h-4 w-4" />
                    Import
                  </Button>
                </div>
              </div>
              <div v-for="(passenger, index) in form.passengers" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_140px_90px_140px_220px_130px_40px]">
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Full Name</Label>
                  <Input v-model="passenger.full_name" placeholder="Full name" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Passport #</Label>
                  <Input v-model="passenger.passport_number" placeholder="Passport #" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Age</Label>
                  <Input v-model="passenger.imported_age" type="number" min="0" max="130" />
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
                  <Label class="text-xs text-muted-foreground">Service</Label>
                  <Select v-model="passenger.service_type">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem v-for="(label, value) in passengerServiceTypes" :key="value" :value="value">{{ label }}</SelectItem></SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Transport Charge</Label>
                  <Input v-model="passenger.transport_charge_amount" type="number" min="0" step="0.01" :disabled="passenger.service_type !== 'transport_only'" />
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
              <div class="space-y-2"><Label>Visa Sale</Label><Input v-model="form.visa_sale_amount" type="number" disabled /></div>
              <div class="space-y-2"><Label>Transport Charge</Label><Input v-model="form.transport_amount" type="number" disabled /></div>
              <div class="space-y-2"><Label>Discount</Label><Input v-model="form.discount_amount" type="number" min="0" step="0.01" /></div>
              <div v-if="canViewAccounting" class="space-y-2"><Label>Visa Cost</Label><Input v-model="form.visa_cost_amount" type="number" disabled /></div>
              <div v-if="canViewAccounting" class="space-y-2"><Label>Transport Cost</Label><Input v-model="form.transport_cost_amount" type="number" disabled /></div>
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
