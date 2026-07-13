<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import DateTimePicker from '@/components/DateTimePicker.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Plus, Save, ScrollText, Trash2, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

type Passenger = {
  id: string
  full_name: string
  passport_number?: string | null
  nationality?: string | null
  visa_status?: string | null
  service_type?: 'visa_transport' | 'transport_only'
}

const props = defineProps<{
  company: { slug: string; base_currency: string }
  nextVoucherNumber: string
  groups: any[]
  selectedGroup: any | null
  availablePassengers: Passenger[]
  assignedPassengers: Passenger[]
  statuses: Record<string, string>
  serviceBundles: Record<string, string>
  airlines: Record<string, string>
  airportCities: Record<string, string>
  hotels: any[]
  editingVoucher: any | null
  agentCapabilities: { can_create: boolean; can_approve: boolean; can_edit: boolean; cutoff_hours: number | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
  { title: props.editingVoucher ? 'Edit Voucher' : 'New Voucher', href: props.editingVoucher ? `/${props.company.slug}/umrah/vouchers/${props.editingVoucher.id}/edit` : `/${props.company.slug}/umrah/vouchers/create` },
]
const page = usePage()
const canViewAccounting = computed(() => ['super_admin', 'owner', 'accountant'].includes(String((page.props.auth as any)?.currentCompanyRole || '')))
const canApprove = computed(() => props.agentCapabilities.can_approve)

const selectedPassengerIds = ref<string[]>(props.availablePassengers.map((passenger) => passenger.id))
const passengerServices = ref<Record<string, 'visa_transport' | 'transport_only'>>(Object.fromEntries(props.availablePassengers.map((passenger) => [passenger.id, passenger.service_type || 'visa_transport'])))
const editingVoucher = computed(() => props.editingVoucher)
const localDateTime = (value: unknown) => value ? String(value).slice(0, 16) : ''
const localDate = (value: unknown) => value ? String(value).slice(0, 10) : ''
const editableStays = props.editingVoucher?.hotel_stays?.map((stay: any) => ({
  source: stay.source === 'company' ? 'company' : 'self', hotel_id: stay.hotel_id || 'none', hotel_name: stay.hotel_name || '', city: stay.city || '',
  room_type: stay.room_type || 'none', room_count: String(stay.room_count || 1), check_in_date: localDate(stay.check_in_date), check_out_date: localDate(stay.check_out_date), notes: stay.notes || '',
}))

const form = useForm({
  voucher_number: props.editingVoucher?.voucher_number || props.nextVoucherNumber,
  visa_group_id: props.selectedGroup?.id || 'none',
  title: props.editingVoucher?.title || (props.selectedGroup ? `${props.selectedGroup.group_number} Journey Voucher` : ''),
  service_bundle: props.editingVoucher?.service_bundle || 'visa_transport',
  status: props.editingVoucher?.status || 'draft',
  onward_airline: props.editingVoucher?.onward_airline || '', onward_flight_number: props.editingVoucher?.onward_flight_number || '',
  onward_departure_city: props.editingVoucher?.onward_departure_city || '', onward_arrival_city: props.editingVoucher?.onward_arrival_city || '',
  onward_departure_at: localDateTime(props.editingVoucher?.onward_departure_at), onward_arrival_at: localDateTime(props.editingVoucher?.onward_arrival_at),
  return_airline: props.editingVoucher?.return_airline || '', return_flight_number: props.editingVoucher?.return_flight_number || '',
  return_departure_city: props.editingVoucher?.return_departure_city || '', return_arrival_city: props.editingVoucher?.return_arrival_city || '',
  return_departure_at: localDateTime(props.editingVoucher?.return_departure_at), return_arrival_at: localDateTime(props.editingVoucher?.return_arrival_at),
  hotel_stays: editableStays?.length ? editableStays : [
    { source: 'self', hotel_id: 'none', hotel_name: '', city: 'Makkah', room_type: 'double', room_count: '1', check_in_date: '', check_out_date: '', notes: '' },
    { source: 'self', hotel_id: 'none', hotel_name: '', city: 'Madinah', room_type: 'double', room_count: '1', check_in_date: '', check_out_date: '', notes: '' },
    { source: 'self', hotel_id: 'none', hotel_name: '', city: 'Makkah', room_type: 'double', room_count: '1', check_in_date: '', check_out_date: '', notes: '' },
  ],
  notes: props.editingVoucher?.notes || '',
})

const hotelOnly = ref(form.service_bundle === 'hotel')
const stayWindowStart = computed(() => hotelOnly.value ? undefined : localDate(form.onward_arrival_at) || undefined)
const stayWindowEnd = computed(() => hotelOnly.value ? undefined : localDate(form.return_departure_at) || undefined)
const airlineOptions = computed(() => Object.entries(props.airlines).map(([value, label]) => ({ value, label })))
const cityOptions = computed(() => Object.entries(props.airportCities).map(([value, label]) => ({ value, label })))
const hotelOptionsFor = (city: string) => props.hotels
  .filter((hotel) => hotel.city === city)
  .map((hotel) => ({ value: hotel.id, label: hotel.name }))
const allSelected = computed(() => props.availablePassengers.length > 0 && selectedPassengerIds.value.length === props.availablePassengers.length)
const someSelected = computed(() => selectedPassengerIds.value.length > 0 && selectedPassengerIds.value.length < props.availablePassengers.length)

watch(
  () => props.availablePassengers,
  (passengers) => {
    selectedPassengerIds.value = passengers.map((passenger) => passenger.id)
    passengerServices.value = Object.fromEntries(passengers.map((passenger) => [passenger.id, passenger.service_type || 'visa_transport']))
  },
)

watch(() => form.onward_airline, (value, previous) => {
  if (!form.return_airline || form.return_airline === previous) form.return_airline = value
})

watch(() => form.onward_departure_city, (value, previous) => {
  if (!form.return_arrival_city || form.return_arrival_city === previous) form.return_arrival_city = value
})

watch(() => form.onward_arrival_city, (value, previous) => {
  if (!form.return_departure_city || form.return_departure_city === previous) form.return_departure_city = value
})

const changeGroup = (groupId: string) => {
  if (editingVoucher.value) return
  const params = groupId === 'none' ? {} : { group_id: groupId }
  router.get(`/${props.company.slug}/umrah/vouchers/create`, params, { preserveState: false })
}

const isChecked = (value: boolean | 'indeterminate') => value === true

const togglePassenger = (passengerId: string, checked: boolean | 'indeterminate') => {
  if (isChecked(checked)) {
    if (!selectedPassengerIds.value.includes(passengerId)) {
      selectedPassengerIds.value = [...selectedPassengerIds.value, passengerId]
    }
    return
  }

  selectedPassengerIds.value = selectedPassengerIds.value.filter((id) => id !== passengerId)
}

const toggleAll = (checked: boolean | 'indeterminate') => {
  selectedPassengerIds.value = isChecked(checked) ? props.availablePassengers.map((passenger) => passenger.id) : []
}

const addHotelStay = () => {
  const previousCheckout = form.hotel_stays.at(-1)?.check_out_date || ''
  form.hotel_stays.push({ source: 'self', hotel_id: 'none', hotel_name: '', city: '', room_type: 'double', room_count: '1', check_in_date: previousCheckout, check_out_date: '', notes: '' })
}

const selectHotel = (index: number, hotelId: string) => {
  const stay = form.hotel_stays[index]
  const hotel = props.hotels.find((item) => item.id === hotelId)
  stay.hotel_id = hotelId
  if (!hotel) return
  stay.hotel_name = hotel.name
  stay.city = hotel.city
  if (!hotel.room_rates.some((rate: any) => rate.room_type === stay.room_type)) stay.room_type = hotel.room_rates[0]?.room_type || 'none'
}

const setStayCity = (index: number, city: string) => {
  const stay = form.hotel_stays[index]
  stay.city = city
  const hotel = selectedHotel(stay.hotel_id)
  if (hotel && hotel.city !== city) {
    stay.hotel_id = 'none'
    stay.hotel_name = ''
    stay.room_type = 'none'
  }
}

const selectedHotel = (hotelId: string) => props.hotels.find((hotel) => hotel.id === hotelId)
const bedsPerRoom: Record<string, number> = { sharing: 1, double: 2, triple: 3, quad: 4, quint: 5 }
const roomTypeLabels: Record<string, string> = { sharing: 'Sharing', double: 'Double', triple: 'Triple', quad: 'Quad', quint: 'Quint' }
const stayAmount = (stay: any, field: 'retail_amount' | 'cost_amount') => {
  if (stay.source !== 'company') return 0
  const rate = selectedHotel(stay.hotel_id)?.room_rates?.find((item: any) => item.room_type === stay.room_type)
  if (!rate || !stay.check_in_date || !stay.check_out_date) return 0
  const nights = Math.max(Math.round((new Date(stay.check_out_date).setHours(0, 0, 0, 0) - new Date(stay.check_in_date).setHours(0, 0, 0, 0)) / 86_400_000), 1)
  return Number(rate[field] || 0) * (bedsPerRoom[stay.room_type] || 1) * Number(stay.room_count || 1) * nights
}
const hotelSale = computed(() => form.hotel_stays.reduce((total, stay) => total + stayAmount(stay, 'retail_amount'), 0))
const hotelCost = computed(() => form.hotel_stays.reduce((total, stay) => total + stayAmount(stay, 'cost_amount'), 0))
const includesHotelService = computed(() => hotelOnly.value || form.hotel_stays.some((stay) => stay.source === 'company'))
const voucherServiceLabel = computed(() => hotelOnly.value ? 'Hotel only' : 'Passenger services selected below')

const plusOneMinute = (value?: string) => {
  if (!value) return undefined
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  date.setMinutes(date.getMinutes() + 1)
  const offset = date.getTimezoneOffset()
  return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 16)
}

const plusOneDay = (value?: string) => {
  if (!value) return undefined
  const date = new Date(`${localDate(value)}T00:00:00Z`)
  if (Number.isNaN(date.getTime())) return localDate(value)
  date.setUTCDate(date.getUTCDate() + 1)
  return date.toISOString().slice(0, 10)
}

const stayCheckInMin = (index: number) => index > 0
  ? form.hotel_stays[index - 1]?.check_out_date || undefined
  : stayWindowStart.value

const stayCheckOutMin = (index: number) => plusOneDay(form.hotel_stays[index]?.check_in_date)

const setStayCheckout = (index: number, value: string | number) => {
  const checkoutDate = String(value)
  form.hotel_stays[index].check_out_date = checkoutDate
  if (form.hotel_stays[index + 1]) form.hotel_stays[index + 1].check_in_date = checkoutDate
}

const removeHotelStay = (index: number) => {
  form.hotel_stays.splice(index, 1)
}

const submit = () => {
  form.transform((data) => ({
    ...data,
    service_bundle: hotelOnly.value ? 'hotel' : (data.hotel_stays.some((stay) => stay.source === 'company') ? 'visa_transport_hotel' : 'visa_transport'),
    visa_group_id: data.visa_group_id === 'none' ? null : data.visa_group_id,
    passenger_ids: selectedPassengerIds.value,
    passenger_services: passengerServices.value,
    hotel_stays: data.hotel_stays.map((stay) => ({ ...stay, hotel_id: stay.hotel_id === 'none' ? null : stay.hotel_id, room_type: stay.room_type === 'none' ? null : stay.room_type, room_count: Number(stay.room_count || 1) })),
  }))

  const options = {
    onSuccess: () => toast.success(editingVoucher.value ? 'Voucher updated successfully' : 'Voucher created successfully'),
    onError: () => toast.error(editingVoucher.value ? 'Failed to update voucher' : 'Failed to create voucher'),
  }

  if (editingVoucher.value) {
    form.put(`/${props.company.slug}/umrah/vouchers/${editingVoucher.value.id}`, options)
    return
  }

  form.post(`/${props.company.slug}/umrah/vouchers`, options)
}
</script>

<template>
  <Head :title="editingVoucher ? 'Edit Voucher' : 'New Voucher'" />
  <PageShell :title="editingVoucher ? 'Edit Voucher' : 'New Voucher'" :description="editingVoucher ? 'Update the draft journey schedule and stays.' : 'Create a journey schedule for all or selected group members.'" :breadcrumbs="breadcrumbs" :icon="ScrollText">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Voucher Details</CardTitle>
            <CardDescription>{{ editingVoucher ? 'The group and passengers are fixed after voucher creation.' : 'Pick the group first. Remaining members appear below.' }}</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Group</Label>
                <Select v-model="form.visa_group_id" :disabled="!!editingVoucher" @update:model-value="(value) => changeGroup(String(value))">
                  <SelectTrigger><SelectValue placeholder="Select group" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Select group</SelectItem>
                    <SelectItem v-for="group in groups" :key="group.id" :value="group.id">
                      {{ group.group_number }} · {{ group.name }} · {{ group.agent?.name || 'No agent' }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.visa_group_id" class="text-xs text-destructive">{{ form.errors.visa_group_id }}</p>
              </div>
              <div class="space-y-2">
                <Label>Voucher #</Label>
                <Input v-model="form.voucher_number" />
                <p v-if="form.errors.voucher_number" class="text-xs text-destructive">{{ form.errors.voucher_number }}</p>
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label>Title</Label>
                <Input v-model="form.title" required />
                <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
              </div>
              <div class="flex items-start gap-3 rounded-md border p-3 md:col-span-2">
                <Checkbox id="hotel-only" v-model="hotelOnly" />
                <div><Label for="hotel-only">Hotel-only voucher</Label><p class="text-sm text-muted-foreground">No flight or transport schedule is required.</p></div>
                <p v-if="form.errors.service_bundle" class="text-xs text-destructive">{{ form.errors.service_bundle }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card v-if="!editingVoucher">
          <CardHeader>
            <CardTitle>Passengers</CardTitle>
            <CardDescription>Select all or some remaining group members.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-if="!selectedGroup" class="text-sm text-muted-foreground">Select a group to choose passengers.</div>
            <div v-else-if="!availablePassengers.length" class="rounded-md border p-4 text-sm text-muted-foreground">All current group members already have vouchers.</div>
            <template v-else>
              <div class="flex items-center justify-between rounded-md border p-3">
                <div class="flex items-center gap-3">
                  <Checkbox :model-value="someSelected ? 'indeterminate' : allSelected" @update:model-value="toggleAll" />
                  <div class="text-sm font-medium">{{ selectedPassengerIds.length }} selected</div>
                </div>
                <Button type="button" variant="outline" size="sm" @click="selectedPassengerIds = []">Clear</Button>
              </div>
              <div v-for="passenger in availablePassengers" :key="passenger.id" class="grid gap-3 rounded-md border p-3 md:grid-cols-[32px_minmax(0,1fr)_190px] md:items-center">
                <Checkbox
                  :model-value="selectedPassengerIds.includes(passenger.id)"
                  @update:model-value="(checked) => togglePassenger(passenger.id, checked)"
                />
                <div>
                  <div class="font-medium">{{ passenger.full_name }}</div>
                  <div class="text-sm text-muted-foreground">{{ passenger.passport_number || 'No passport' }} · {{ passenger.nationality || 'No nationality' }}</div>
                </div>
                <div v-if="!hotelOnly" class="flex items-center gap-2">
                  <Checkbox :id="`transport-only-${passenger.id}`" :model-value="passengerServices[passenger.id] === 'transport_only'" @update:model-value="(checked) => passengerServices[passenger.id] = checked === true ? 'transport_only' : 'visa_transport'" />
                  <Label :for="`transport-only-${passenger.id}`">Transport only</Label>
                </div>
                <Badge v-else variant="secondary">Hotel guest</Badge>
              </div>
            </template>
            <p v-if="form.errors.passenger_ids" class="text-xs text-destructive">{{ form.errors.passenger_ids }}</p>
          </CardContent>
        </Card>

        <Card v-if="!hotelOnly">
          <CardHeader>
            <CardTitle>Flights</CardTitle>
            <CardDescription>Hotel stay dates must fit between these flight dates.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="hidden gap-3 px-3 text-xs text-muted-foreground 2xl:grid 2xl:grid-cols-[72px_90px_95px_100px_100px_minmax(160px,1fr)_minmax(160px,1fr)]">
              <span>Sector</span><span>Airline</span><span>Flight #</span><span>From</span><span>To</span><span>Takeoff</span><span>Landing</span>
            </div>
            <div class="grid min-w-0 gap-3 rounded-md border p-3 md:grid-cols-2 2xl:grid-cols-[72px_90px_95px_100px_100px_minmax(160px,1fr)_minmax(160px,1fr)] 2xl:items-end">
              <div class="font-medium 2xl:pb-2">Onward</div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Airline</Label><SearchableSelect v-model="form.onward_airline" :options="airlineOptions" open-on-focus placeholder="Code" search-placeholder="Type airline or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Flight #</Label><Input v-model="form.onward_flight_number" maxlength="5" placeholder="PK741" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">From</Label><SearchableSelect v-model="form.onward_departure_city" :options="cityOptions" open-on-focus placeholder="City" search-placeholder="Type city or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">To</Label><SearchableSelect v-model="form.onward_arrival_city" :options="cityOptions" open-on-focus placeholder="City" search-placeholder="Type city or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Takeoff</Label><DateTimePicker v-model="form.onward_departure_at" required /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Landing</Label><DateTimePicker v-model="form.onward_arrival_at" :min="plusOneMinute(form.onward_departure_at)" required /></div>
            </div>
            <div class="grid min-w-0 gap-3 rounded-md border p-3 md:grid-cols-2 2xl:grid-cols-[72px_90px_95px_100px_100px_minmax(160px,1fr)_minmax(160px,1fr)] 2xl:items-end">
              <div class="font-medium 2xl:pb-2">Return</div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Airline</Label><SearchableSelect v-model="form.return_airline" :options="airlineOptions" open-on-focus placeholder="Code" search-placeholder="Type airline or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Flight #</Label><Input v-model="form.return_flight_number" maxlength="5" placeholder="PK742" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">From</Label><SearchableSelect v-model="form.return_departure_city" :options="cityOptions" open-on-focus placeholder="City" search-placeholder="Type city or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">To</Label><SearchableSelect v-model="form.return_arrival_city" :options="cityOptions" open-on-focus placeholder="City" search-placeholder="Type city or code" /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Takeoff</Label><DateTimePicker v-model="form.return_departure_at" :min="plusOneMinute(form.onward_arrival_at)" required /></div>
              <div class="min-w-0 space-y-1"><Label class="2xl:hidden">Landing</Label><DateTimePicker v-model="form.return_arrival_at" :min="plusOneMinute(form.return_departure_at)" required /></div>
            </div>
            <div v-for="field in ['onward_airline', 'onward_flight_number', 'onward_departure_city', 'onward_arrival_city', 'onward_departure_at', 'onward_arrival_at', 'return_airline', 'return_flight_number', 'return_departure_city', 'return_arrival_city', 'return_departure_at', 'return_arrival_at']" :key="field">
              <p v-if="form.errors[field]" class="text-xs text-destructive">{{ form.errors[field] }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Hotel Stays</CardTitle>
            <CardDescription>{{ includesHotelService ? 'Stays are included and charged by the company.' : 'Stays complete the itinerary and are not charged on this voucher.' }}</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-for="(stay, index) in form.hotel_stays" :key="index" class="grid min-w-0 gap-3 rounded-md border p-3 md:grid-cols-2 lg:grid-cols-12 lg:items-end">
              <div class="font-medium lg:col-span-1 lg:pb-2">{{ index + 1 }}</div>
              <div class="min-w-0 space-y-1 lg:col-span-2"><Label>City</Label><Select :model-value="stay.city" @update:model-value="setStayCity(index, String($event))"><SelectTrigger><SelectValue placeholder="Select city" /></SelectTrigger><SelectContent><SelectItem value="Makkah">Makkah</SelectItem><SelectItem value="Madinah">Madinah</SelectItem></SelectContent></Select></div>
              <div class="min-w-0 space-y-1 lg:col-span-2"><Label>Source</Label><Select v-model="stay.source"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="company">Company</SelectItem><SelectItem value="self">Self</SelectItem></SelectContent></Select></div>
              <div v-if="stay.source === 'company'" class="min-w-0 space-y-1 lg:col-span-3"><Label>Hotel</Label><SearchableSelect :model-value="stay.hotel_id" :options="hotelOptionsFor(stay.city)" :show-value="false" open-on-focus placeholder="Select hotel" search-placeholder="Type hotel name" @update:model-value="selectHotel(index, String($event))" /></div>
              <div v-else class="min-w-0 space-y-1 lg:col-span-3"><Label>Hotel</Label><Input v-model="stay.hotel_name" required /></div>
              <div class="min-w-0 space-y-1 lg:col-span-2"><Label>Room</Label><Select v-model="stay.room_type"><SelectTrigger><SelectValue placeholder="Type" /></SelectTrigger><SelectContent><SelectItem v-for="rate in (stay.source === 'company' ? selectedHotel(stay.hotel_id)?.room_rates || [] : [{room_type:'sharing'},{room_type:'double'},{room_type:'triple'},{room_type:'quad'},{room_type:'quint'}])" :key="rate.room_type" :value="rate.room_type">{{ roomTypeLabels[rate.room_type] || rate.room_type }}</SelectItem></SelectContent></Select></div>
              <div class="min-w-0 space-y-1 lg:col-span-1"><Label>{{ stay.room_type === 'sharing' ? 'Beds' : 'Rooms' }}</Label><Input v-model="stay.room_count" type="number" min="1" required /></div>
              <div class="min-w-0 space-y-1 lg:col-start-2 lg:col-span-3"><Label>Check-in</Label><Input v-model="stay.check_in_date" type="date" :min="stayCheckInMin(index)" :max="stayWindowEnd" required /></div>
              <div class="min-w-0 space-y-1 lg:col-span-3"><Label>Checkout</Label><Input :model-value="stay.check_out_date" type="date" :min="stayCheckOutMin(index)" :max="stayWindowEnd" required @update:model-value="setStayCheckout(index, $event)" /></div>
              <div class="min-w-0 space-y-1 lg:col-span-5"><Label>Notes</Label><Input v-model="stay.notes" /></div>
              <div class="flex items-end lg:col-start-12 lg:row-start-1">
                <Button type="button" variant="ghost" size="icon" :disabled="form.hotel_stays.length === 1" @click="removeHotelStay(index)">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
              <p v-if="form.errors[`hotel_stays.${index}.hotel_name`]" class="text-xs text-destructive lg:col-span-12">{{ form.errors[`hotel_stays.${index}.hotel_name`] }}</p>
              <p v-if="form.errors[`hotel_stays.${index}.check_in_date`]" class="text-xs text-destructive lg:col-span-12">{{ form.errors[`hotel_stays.${index}.check_in_date`] }}</p>
              <p v-if="form.errors[`hotel_stays.${index}.check_out_date`]" class="text-xs text-destructive lg:col-span-12">{{ form.errors[`hotel_stays.${index}.check_out_date`] }}</p>
            </div>
            <Button type="button" variant="outline" @click="addHotelStay">
              <Plus class="mr-2 h-4 w-4" />
              Add Stay
            </Button>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Save</CardTitle>
            <CardDescription>Save as draft or approve the completed voucher.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="rounded-md border p-3 text-sm">
              <div class="font-medium">{{ selectedGroup?.group_number || 'No group selected' }}</div>
              <div class="text-muted-foreground">{{ selectedGroup?.agent?.name || 'No agent' }}</div>
              <div class="mt-2">{{ editingVoucher ? editingVoucher.passengers?.length || 0 : selectedPassengerIds.length }} passengers</div>
              <div class="mt-2 text-muted-foreground">{{ voucherServiceLabel }}</div>
              <div v-if="includesHotelService" class="mt-2 flex justify-between"><span>Hotel charge</span><MoneyText :amount="hotelSale" :currency="company.base_currency" /></div>
              <div v-if="includesHotelService && canViewAccounting" class="flex justify-between text-muted-foreground"><span>Hotel cost</span><MoneyText :amount="hotelCost" :currency="company.base_currency" /></div>
            </div>
            <div v-if="canApprove && !editingVoucher" class="space-y-2">
              <Label>Status</Label>
              <Select v-model="form.status">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</SelectItem></SelectContent>
              </Select>
            </div>
            <div v-else class="rounded-md border p-3 text-sm"><span class="text-muted-foreground">Status</span><div class="font-medium">Draft</div></div>
            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
            </div>
            <Button class="w-full" :disabled="form.processing || !selectedGroup || (!editingVoucher && selectedPassengerIds.length === 0)" @click="submit">
              <Save class="mr-2 h-4 w-4" />
              {{ editingVoucher ? 'Update Voucher' : 'Save Voucher' }}
            </Button>
          </CardContent>
        </Card>

        <Card v-if="assignedPassengers.length">
          <CardHeader>
            <CardTitle>Already Assigned</CardTitle>
            <CardDescription>These members will not appear in the available list.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-for="passenger in assignedPassengers" :key="passenger.id" class="rounded-md border p-3">
              <div class="font-medium">{{ passenger.full_name }}</div>
              <div class="text-sm text-muted-foreground">{{ passenger.passport_number || 'No passport' }}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Quick Links</CardTitle>
          </CardHeader>
          <CardContent class="grid gap-2">
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups`)">
              <Users class="mr-2 h-4 w-4" />
              Visa Groups
            </Button>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers`)">Voucher List</Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
