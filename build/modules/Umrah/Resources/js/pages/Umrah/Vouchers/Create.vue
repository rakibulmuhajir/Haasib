<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
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
}

const props = defineProps<{
  company: { slug: string }
  nextVoucherNumber: string
  groups: any[]
  selectedGroup: any | null
  availablePassengers: Passenger[]
  assignedPassengers: Passenger[]
  statuses: Record<string, string>
  airlines: Record<string, string>
  airportCities: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
  { title: 'New Voucher', href: `/${props.company.slug}/umrah/vouchers/create` },
]

const selectedPassengerIds = ref<string[]>(props.availablePassengers.map((passenger) => passenger.id))

const form = useForm({
  voucher_number: props.nextVoucherNumber,
  visa_group_id: props.selectedGroup?.id || 'none',
  title: props.selectedGroup ? `${props.selectedGroup.group_number} Journey Voucher` : '',
  status: 'issued',
  onward_airline: '',
  onward_flight_number: '',
  onward_departure_city: '',
  onward_arrival_city: '',
  onward_departure_at: '',
  onward_arrival_at: '',
  return_airline: '',
  return_flight_number: '',
  return_departure_city: '',
  return_arrival_city: '',
  return_departure_at: '',
  return_arrival_at: '',
  hotel_stays: [
    { hotel_name: '', city: 'Makkah', check_in_date: '', check_out_date: '', notes: '' },
  ],
  notes: '',
})

const journeyStartDate = computed(() => form.onward_departure_at ? form.onward_departure_at.slice(0, 10) : undefined)
const journeyEndDate = computed(() => form.return_arrival_at ? form.return_arrival_at.slice(0, 10) : undefined)
const allSelected = computed(() => props.availablePassengers.length > 0 && selectedPassengerIds.value.length === props.availablePassengers.length)
const someSelected = computed(() => selectedPassengerIds.value.length > 0 && selectedPassengerIds.value.length < props.availablePassengers.length)

watch(
  () => props.availablePassengers,
  (passengers) => {
    selectedPassengerIds.value = passengers.map((passenger) => passenger.id)
  },
)

const changeGroup = (groupId: string) => {
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
  form.hotel_stays.push({ hotel_name: '', city: '', check_in_date: '', check_out_date: '', notes: '' })
}

const removeHotelStay = (index: number) => {
  form.hotel_stays.splice(index, 1)
}

const submit = () => form
  .transform((data) => ({
    ...data,
    visa_group_id: data.visa_group_id === 'none' ? null : data.visa_group_id,
    passenger_ids: selectedPassengerIds.value,
    hotel_stays: data.hotel_stays.filter((stay) => stay.hotel_name.trim() !== ''),
  }))
  .post(`/${props.company.slug}/umrah/vouchers`, {
    onSuccess: () => toast.success('Voucher created successfully'),
    onError: () => toast.error('Failed to create voucher'),
  })
</script>

<template>
  <Head title="New Voucher" />
  <PageShell title="New Voucher" description="Create a journey schedule for all or selected group members." :breadcrumbs="breadcrumbs" :icon="ScrollText">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Voucher Details</CardTitle>
            <CardDescription>Pick the group first. Remaining members appear below.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Group</Label>
                <Select v-model="form.visa_group_id" @update:model-value="(value) => changeGroup(String(value))">
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
            </div>
          </CardContent>
        </Card>

        <Card>
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
              <div v-for="passenger in availablePassengers" :key="passenger.id" class="grid gap-3 rounded-md border p-3 md:grid-cols-[32px_1fr_160px]">
                <Checkbox
                  :model-value="selectedPassengerIds.includes(passenger.id)"
                  @update:model-value="(checked) => togglePassenger(passenger.id, checked)"
                />
                <div>
                  <div class="font-medium">{{ passenger.full_name }}</div>
                  <div class="text-sm text-muted-foreground">{{ passenger.passport_number || 'No passport' }} · {{ passenger.nationality || 'No nationality' }}</div>
                </div>
                <Badge variant="secondary">{{ passenger.visa_status || 'pending' }}</Badge>
              </div>
            </template>
            <p v-if="form.errors.passenger_ids" class="text-xs text-destructive">{{ form.errors.passenger_ids }}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Flights</CardTitle>
            <CardDescription>Hotel stay dates must fit between these flight dates.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Onward Airline</Label>
                <Select v-model="form.onward_airline">
                  <SelectTrigger><SelectValue placeholder="Select airline" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(name, code) in airlines" :key="code" :value="code">
                      {{ code }} · {{ name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.onward_airline" class="text-xs text-destructive">{{ form.errors.onward_airline }}</p>
              </div>
              <div class="space-y-2"><Label>Onward Flight #</Label><Input v-model="form.onward_flight_number" /></div>
              <div class="space-y-2">
                <Label>Departure City</Label>
                <Select v-model="form.onward_departure_city">
                  <SelectTrigger><SelectValue placeholder="Select departure city" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(city, code) in airportCities" :key="code" :value="code">
                      {{ code }} · {{ city }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.onward_departure_city" class="text-xs text-destructive">{{ form.errors.onward_departure_city }}</p>
              </div>
              <div class="space-y-2">
                <Label>Arrival City</Label>
                <Select v-model="form.onward_arrival_city">
                  <SelectTrigger><SelectValue placeholder="Select arrival city" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(city, code) in airportCities" :key="code" :value="code">
                      {{ code }} · {{ city }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.onward_arrival_city" class="text-xs text-destructive">{{ form.errors.onward_arrival_city }}</p>
              </div>
              <div class="space-y-2"><Label>Onward Departure</Label><Input v-model="form.onward_departure_at" type="datetime-local" required /></div>
              <div class="space-y-2"><Label>Onward Arrival</Label><Input v-model="form.onward_arrival_at" type="datetime-local" required /></div>
              <div class="space-y-2">
                <Label>Return Airline</Label>
                <Select v-model="form.return_airline">
                  <SelectTrigger><SelectValue placeholder="Select airline" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(name, code) in airlines" :key="code" :value="code">
                      {{ code }} · {{ name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.return_airline" class="text-xs text-destructive">{{ form.errors.return_airline }}</p>
              </div>
              <div class="space-y-2"><Label>Return Flight #</Label><Input v-model="form.return_flight_number" /></div>
              <div class="space-y-2">
                <Label>Departure City</Label>
                <Select v-model="form.return_departure_city">
                  <SelectTrigger><SelectValue placeholder="Select departure city" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(city, code) in airportCities" :key="code" :value="code">
                      {{ code }} · {{ city }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.return_departure_city" class="text-xs text-destructive">{{ form.errors.return_departure_city }}</p>
              </div>
              <div class="space-y-2">
                <Label>Arrival City</Label>
                <Select v-model="form.return_arrival_city">
                  <SelectTrigger><SelectValue placeholder="Select arrival city" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(city, code) in airportCities" :key="code" :value="code">
                      {{ code }} · {{ city }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.return_arrival_city" class="text-xs text-destructive">{{ form.errors.return_arrival_city }}</p>
              </div>
              <div class="space-y-2"><Label>Return Departure</Label><Input v-model="form.return_departure_at" type="datetime-local" required /></div>
              <div class="space-y-2"><Label>Return Arrival</Label><Input v-model="form.return_arrival_at" type="datetime-local" required /></div>
            </div>
            <p v-if="form.errors.onward_departure_at" class="text-xs text-destructive">{{ form.errors.onward_departure_at }}</p>
            <p v-if="form.errors.return_arrival_at" class="text-xs text-destructive">{{ form.errors.return_arrival_at }}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Hotel Stays</CardTitle>
            <CardDescription>Add each stay included in the voucher.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-for="(stay, index) in form.hotel_stays" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_130px_150px_150px_40px]">
              <div class="space-y-2"><Label>Hotel</Label><Input v-model="stay.hotel_name" /></div>
              <div class="space-y-2"><Label>City</Label><Input v-model="stay.city" /></div>
              <div class="space-y-2"><Label>Check-in</Label><Input v-model="stay.check_in_date" type="date" :min="journeyStartDate" :max="journeyEndDate" /></div>
              <div class="space-y-2"><Label>Checkout</Label><Input v-model="stay.check_out_date" type="date" :min="stay.check_in_date || journeyStartDate" :max="journeyEndDate" /></div>
              <div class="flex items-end">
                <Button type="button" variant="ghost" size="icon" :disabled="form.hotel_stays.length === 1" @click="removeHotelStay(index)">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
              <div class="space-y-2 md:col-span-5"><Label>Notes</Label><Textarea v-model="stay.notes" /></div>
              <p v-if="form.errors[`hotel_stays.${index}.check_in_date`]" class="text-xs text-destructive md:col-span-5">{{ form.errors[`hotel_stays.${index}.check_in_date`] }}</p>
              <p v-if="form.errors[`hotel_stays.${index}.check_out_date`]" class="text-xs text-destructive md:col-span-5">{{ form.errors[`hotel_stays.${index}.check_out_date`] }}</p>
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
            <CardDescription>Issue the voucher for selected members.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="rounded-md border p-3 text-sm">
              <div class="font-medium">{{ selectedGroup?.group_number || 'No group selected' }}</div>
              <div class="text-muted-foreground">{{ selectedGroup?.agent?.name || 'No agent' }}</div>
              <div class="mt-2">{{ selectedPassengerIds.length }} passengers selected</div>
            </div>
            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
            </div>
            <Button class="w-full" :disabled="form.processing || !selectedGroup || selectedPassengerIds.length === 0" @click="submit">
              <Save class="mr-2 h-4 w-4" />
              Save Voucher
            </Button>
          </CardContent>
        </Card>

        <Card v-if="assignedPassengers.length">
          <CardHeader>
            <CardTitle>Already Voucher Issued</CardTitle>
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
