<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { CheckCircle2, Plane, Plus, ScrollText, WalletCards } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  group: any
  paymentMethods: Record<string, string>
  passengerStatuses: Record<string, string>
  accounts: any[]
}>()

const page = usePage()
const currentRole = computed(() => (page.props.auth as any)?.currentCompanyRole || null)
const canViewAccounting = computed(() => ['super_admin', 'owner', 'accountant'].includes(String(currentRole.value)))

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
  { title: props.group.group_number, href: `/${props.company.slug}/umrah/groups/${props.group.id}` },
]

const passengerForm = useForm({
  full_name: '',
  passport_number: '',
  nationality: '',
  date_of_birth: '',
  imported_age: '',
  service_type: 'visa_transport',
  transport_charge_amount: '0',
  visa_status: 'received',
  notes: '',
})

const paymentForm = useForm({
  payment_number: '',
  payment_date: new Date().toISOString().slice(0, 10),
  amount: '',
  method: 'cash',
  account_id: 'none',
  reference: '',
  notes: '',
})

const bulkForm = useForm({
  visa_status: 'approved',
})
const singleStatusForm = useForm({
  visa_status: '',
})
const selectedPassengerIds = ref<string[]>([])

const remainingAfterPayment = computed(() => {
  return Math.max(Number(props.group.balance || 0) - Number(paymentForm.amount || 0), 0)
})

const passengers = computed(() => props.group.passengers || [])

const normalizeDate = (value: string | null | undefined) => String(value || '').slice(0, 10)

const calculateAge = (dateOfBirth: string | null | undefined) => {
  const normalizedBirthDate = normalizeDate(dateOfBirth)

  if (!normalizedBirthDate) {
    return null
  }

  const birthDate = new Date(`${normalizedBirthDate}T00:00:00`)
  const referenceDate = props.group.travel_date
    ? new Date(`${normalizeDate(props.group.travel_date)}T00:00:00`)
    : new Date()

  if (Number.isNaN(birthDate.getTime()) || Number.isNaN(referenceDate.getTime())) {
    return null
  }

  let age = referenceDate.getFullYear() - birthDate.getFullYear()
  const monthDelta = referenceDate.getMonth() - birthDate.getMonth()

  if (monthDelta < 0 || (monthDelta === 0 && referenceDate.getDate() < birthDate.getDate())) {
    age -= 1
  }

  return Math.max(age, 0)
}

const passengerAgeText = (passenger: any) => {
  const age = calculateAge(passenger.date_of_birth)

  if (age === null) {
    return passenger.imported_age !== null && passenger.imported_age !== undefined
      ? `Age ${passenger.imported_age}`
      : 'Age not set'
  }

  return `${normalizeDate(passenger.date_of_birth)} · Age ${age}`
}

const actionableStatuses = computed(() => {
  return Object.fromEntries(
    Object.entries(props.passengerStatuses).filter(([value]) => ['approved', 'rejected', 'embassy'].includes(value)),
  )
})

const allPassengersSelected = computed(() => {
  return passengers.value.length > 0 && selectedPassengerIds.value.length === passengers.value.length
})

const somePassengersSelected = computed(() => {
  return selectedPassengerIds.value.length > 0 && selectedPassengerIds.value.length < passengers.value.length
})

const isChecked = (checked: boolean | 'indeterminate') => checked === true

const togglePassengerSelection = (passengerId: string, checked: boolean | 'indeterminate') => {
  const shouldSelect = isChecked(checked)

  if (shouldSelect && !selectedPassengerIds.value.includes(passengerId)) {
    selectedPassengerIds.value = [...selectedPassengerIds.value, passengerId]
    return
  }

  if (!shouldSelect) {
    selectedPassengerIds.value = selectedPassengerIds.value.filter((id) => id !== passengerId)
  }
}

const toggleAllPassengers = (checked: boolean | 'indeterminate') => {
  selectedPassengerIds.value = isChecked(checked)
    ? passengers.value.map((passenger: any) => passenger.id)
    : []
}

const addPassenger = () => passengerForm
  .transform((data) => ({
    ...data,
    imported_age: data.imported_age === '' ? null : Number(data.imported_age),
  }))
  .post(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Passenger added successfully')
      passengerForm.reset()
      passengerForm.visa_status = 'received'
      passengerForm.service_type = 'visa_transport'
      passengerForm.transport_charge_amount = '0'
    },
    onError: () => toast.error('Failed to add passenger'),
  })

const updatePassengerStatus = (passenger: any, status: string) => {
  singleStatusForm.visa_status = status
  singleStatusForm.put(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${passenger.id}/status`, {
    preserveScroll: true,
    onSuccess: () => toast.success('Passenger visa status updated'),
    onError: () => toast.error('Failed to update passenger status'),
  })
}

const bulkUpdatePassengerStatus = () => {
  bulkForm
    .transform((data) => ({
      ...data,
      passenger_ids: [...selectedPassengerIds.value],
    }))
    .put(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers/status`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Selected passenger statuses updated')
        selectedPassengerIds.value = []
      },
      onError: () => toast.error('Failed to update selected passengers'),
    })
}

const addPayment = () => paymentForm
  .transform((data) => ({
    ...data,
    amount: Number(data.amount || 0),
    account_id: data.account_id === 'none' ? null : data.account_id,
  }))
  .post(`/${props.company.slug}/umrah/groups/${props.group.id}/payments`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Payment recorded successfully')
      paymentForm.reset('payment_number', 'amount', 'reference', 'notes')
      paymentForm.method = 'cash'
      paymentForm.account_id = 'none'
      paymentForm.payment_date = new Date().toISOString().slice(0, 10)
    },
    onError: () => toast.error('Failed to record payment'),
  })
</script>

<template>
  <Head :title="group.group_number" />
  <PageShell :title="`${group.group_number} · ${group.name}`" :description="`${group.agent?.name || 'No agent'} · ${group.passenger_count} passengers`" :breadcrumbs="breadcrumbs" :icon="Plane">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers/create?group_id=${group.id}`)">
        <ScrollText class="mr-2 h-4 w-4" />
        Create Voucher
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-4">
      <Card><CardHeader><CardTitle>Receivable</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.total_receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Paid</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.total_paid" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.balance" :currency="company.base_currency" /></CardContent></Card>
      <Card>
        <CardHeader><CardTitle>Payment Status</CardTitle></CardHeader>
        <CardContent>
          <Badge :variant="Number(group.balance || 0) <= 0 ? 'default' : 'secondary'">
            {{ Number(group.balance || 0) <= 0 ? 'Paid' : 'Unpaid' }}
          </Badge>
        </CardContent>
      </Card>
      <Card v-if="canViewAccounting"><CardHeader><CardTitle>Profit</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.profit" :currency="company.base_currency" /></CardContent></Card>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Group Info</CardTitle>
            <CardDescription>Travel and service details.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-3">
            <div><div class="text-sm text-muted-foreground">Status</div><Badge variant="secondary">{{ group.status }}</Badge></div>
            <div><div class="text-sm text-muted-foreground">Travel Date</div><div class="font-medium">{{ group.travel_date || 'Not set' }}</div></div>
            <div><div class="text-sm text-muted-foreground">Vendor</div><div class="font-medium">{{ group.vendor?.name || 'Not set' }}</div></div>
            <div><div class="text-sm text-muted-foreground">Visa Service</div><div class="font-medium">{{ group.visa_service?.name || 'Custom' }}</div></div>
            <div><div class="text-sm text-muted-foreground">Flight</div><div class="font-medium">{{ group.flight_info?.airline || 'Not set' }} {{ group.flight_info?.number || '' }}</div></div>
            <div><div class="text-sm text-muted-foreground">Makkah Hotel</div><div class="font-medium">{{ group.hotel_info?.makkah || 'Not set' }}</div></div>
            <div><div class="text-sm text-muted-foreground">Madinah Hotel</div><div class="font-medium">{{ group.hotel_info?.madinah || 'Not set' }}</div></div>
            <div>
              <div class="text-sm text-muted-foreground">Transport</div>
              <div class="font-medium">{{ group.transport_mode === 'specialized' ? 'Specialized transport' : 'Standard bus included' }}</div>
              <div v-if="group.transport_required && (group.transport_pax_capacity || group.transport_service?.vehicle_type)" class="text-xs text-muted-foreground">
                <span v-if="group.transport_service?.vehicle_type">{{ group.transport_service.vehicle_type }}</span>
                <span v-if="group.transport_pax_capacity"> · {{ group.transport_pax_capacity }} pax each</span>
              </div>
              <div v-if="group.driver || group.transport_service?.driver_name || group.transport_service?.number_plate" class="text-xs text-muted-foreground">
                {{ group.driver?.name || group.transport_service?.driver_name || 'No driver' }}
                <span v-if="group.driver?.phone"> · {{ group.driver.phone }}</span>
                <span v-if="group.transport_service?.number_plate"> · {{ group.transport_service.number_plate }}</span>
              </div>
            </div>
            <div><div class="text-sm text-muted-foreground">Visa Sale</div><div class="font-medium"><MoneyText :amount="group.visa_sale_amount" :currency="company.base_currency" /></div></div>
            <div><div class="text-sm text-muted-foreground">Transport Charge</div><div class="font-medium"><MoneyText :amount="group.transport_amount" :currency="company.base_currency" /></div></div>
            <div><div class="text-sm text-muted-foreground">Hotel Charge</div><div class="font-medium"><MoneyText :amount="group.hotel_amount" :currency="company.base_currency" /></div></div>
            <div v-if="canViewAccounting"><div class="text-sm text-muted-foreground">Visa Cost</div><div class="font-medium"><MoneyText :amount="group.visa_cost_amount" :currency="company.base_currency" /></div></div>
            <div v-if="canViewAccounting && Number(group.included_bus_cost_deduction || 0) > 0"><div class="text-sm text-muted-foreground">Included Bus Cost Deducted</div><div class="font-medium"><MoneyText :amount="group.included_bus_cost_deduction" :currency="company.base_currency" /></div></div>
            <div v-if="canViewAccounting"><div class="text-sm text-muted-foreground">Transport Cost</div><div class="font-medium"><MoneyText :amount="group.transport_cost_amount" :currency="company.base_currency" /></div></div>
            <div v-if="canViewAccounting"><div class="text-sm text-muted-foreground">Hotel Cost</div><div class="font-medium"><MoneyText :amount="group.hotel_cost_amount" :currency="company.base_currency" /></div></div>
            <div v-if="canViewAccounting">
              <div class="text-sm text-muted-foreground">Sale Journal</div>
              <Button v-if="group.sale_transaction" variant="link" class="h-auto p-0" @click="router.get(`/${company.slug}/journals/${group.sale_transaction.id}`)">
                {{ group.sale_transaction.transaction_number }}
              </Button>
              <div v-else class="font-medium">Not posted</div>
            </div>
            <div v-if="canViewAccounting">
              <div class="text-sm text-muted-foreground">Cost Journal</div>
              <Button v-if="group.cost_transaction" variant="link" class="h-auto p-0" @click="router.get(`/${company.slug}/journals/${group.cost_transaction.id}`)">
                {{ group.cost_transaction.transaction_number }}
              </Button>
              <div v-else class="font-medium">Not posted</div>
            </div>
          </CardContent>
        </Card>

        <Card v-if="group.transport_mode === 'specialized'">
          <CardHeader><CardTitle>Transport Schedule</CardTitle><CardDescription>Selected journey and sector fare snapshots.</CardDescription></CardHeader>
          <CardContent class="space-y-3">
            <div v-for="item in group.transport_items" :key="item.id" class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_140px_120px_150px]">
              <div><div class="font-medium">{{ item.description }}</div><div class="text-xs text-muted-foreground">{{ item.sector?.name || item.package?.name }} · {{ item.service?.name }}<span v-if="item.terminal === 'hajj'"> · Hajj Terminal</span></div></div>
              <div><div class="text-xs text-muted-foreground">Schedule</div><div>{{ item.scheduled_at || 'Not scheduled' }}</div></div>
              <div><div class="text-xs text-muted-foreground">Vehicles / Pax</div><div>{{ item.quantity }} / {{ item.passenger_count }}</div></div>
              <div><div class="text-xs text-muted-foreground">Charge</div><MoneyText :amount="item.total_sale_amount" :currency="company.base_currency" /><div v-if="canViewAccounting" class="text-xs text-muted-foreground">Cost <MoneyText :amount="item.total_cost_amount" :currency="company.base_currency" /></div></div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Passengers</CardTitle>
            <CardDescription>Update visa status one by one or select multiple passengers for a bulk change.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-if="!group.passengers?.length" class="text-sm text-muted-foreground">No passengers added yet.</div>
            <div v-else class="flex flex-col gap-3 rounded-md border p-3 md:flex-row md:items-center md:justify-between">
              <div class="flex items-center gap-3">
                <Checkbox :model-value="somePassengersSelected ? 'indeterminate' : allPassengersSelected" @update:model-value="toggleAllPassengers" />
                <div class="text-sm text-muted-foreground">{{ selectedPassengerIds.length }} selected</div>
              </div>
              <div class="grid gap-2 sm:grid-cols-[180px_auto]">
                <Select v-model="bulkForm.visa_status">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in actionableStatuses" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
                <Button type="button" :disabled="bulkForm.processing || selectedPassengerIds.length === 0" @click="bulkUpdatePassengerStatus">
                  <CheckCircle2 class="mr-2 h-4 w-4" />
                  Apply to Selected
                </Button>
              </div>
            </div>
            <div v-for="passenger in group.passengers" :key="passenger.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[32px_1fr_150px_120px_130px_190px_160px]">
              <div class="flex items-start pt-1">
                <Checkbox
                  :model-value="selectedPassengerIds.includes(passenger.id)"
                  @update:model-value="(checked) => togglePassengerSelection(passenger.id, checked)"
                />
              </div>
              <div>
                <div class="font-medium">{{ passenger.full_name }}</div>
                <div class="text-xs text-muted-foreground">{{ passenger.notes || 'No notes' }}</div>
              </div>
              <div>{{ passenger.passport_number || 'No passport' }}</div>
              <div>{{ passengerAgeText(passenger) }}</div>
              <div>{{ passenger.nationality || 'Nationality not set' }}</div>
              <div><div>{{ passenger.service_type === 'transport_only' ? 'Transport only' : 'Visa included' }}</div><div v-if="passenger.service_type === 'transport_only'" class="text-xs text-muted-foreground"><MoneyText :amount="passenger.transport_charge_amount" :currency="company.base_currency" /></div></div>
              <div class="space-y-2">
                <Badge variant="secondary">{{ passengerStatuses[passenger.visa_status] || passenger.visa_status }}</Badge>
                <Select :model-value="passenger.visa_status" @update:model-value="(status) => updatePassengerStatus(passenger, String(status))">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in actionableStatuses" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Payments</CardTitle></CardHeader>
          <CardContent class="space-y-3">
            <div v-if="!group.payments?.length" class="text-sm text-muted-foreground">No payments recorded yet.</div>
            <div v-for="payment in group.payments" :key="payment.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_150px_170px]">
              <div>
                <div class="font-medium">{{ payment.payment_number }}</div>
                <div class="text-sm text-muted-foreground">{{ payment.payment_date }} · {{ paymentMethods[payment.method] || payment.method }} · {{ payment.reference || 'No reference' }}</div>
                <Button v-if="payment.transaction" variant="link" class="h-auto p-0 text-xs" @click="router.get(`/${company.slug}/journals/${payment.transaction.id}`)">
                  Journal {{ payment.transaction.transaction_number }}
                </Button>
              </div>
              <div>{{ payment.account ? `${payment.account.code} — ${payment.account.name}` : 'No account selected' }}</div>
              <div class="text-right font-semibold"><MoneyText :amount="payment.amount" :currency="company.base_currency" /></div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-6">
        <Card>
          <CardHeader><CardTitle>Add Passenger</CardTitle></CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="addPassenger">
              <div class="space-y-2"><Label>Name</Label><Input v-model="passengerForm.full_name" required /></div>
              <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2"><Label>Passport #</Label><Input v-model="passengerForm.passport_number" /></div>
                <div class="space-y-2"><Label>Age</Label><Input v-model="passengerForm.imported_age" type="number" min="0" max="130" /></div>
                <div class="space-y-2"><Label>Nationality</Label><Input v-model="passengerForm.nationality" /></div>
              </div>
              <div class="space-y-2">
                <Label>Status</Label>
                <Select v-model="passengerForm.visa_status">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in passengerStatuses" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Service</Label>
                <Select v-model="passengerForm.service_type">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="visa_transport">Visa included</SelectItem><SelectItem value="transport_only">Already has visa - transport only</SelectItem></SelectContent>
                </Select>
              </div>
              <div v-if="passengerForm.service_type === 'transport_only'" class="space-y-2"><Label>Transport Charge</Label><Input v-model="passengerForm.transport_charge_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Notes</Label><Textarea v-model="passengerForm.notes" /></div>
              <Button type="submit" class="w-full" :disabled="passengerForm.processing"><Plus class="mr-2 h-4 w-4" />Add Passenger</Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Record Payment</CardTitle>
            <CardDescription>Remaining after this payment: <MoneyText :amount="remainingAfterPayment" :currency="company.base_currency" /></CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="addPayment">
              <div class="space-y-2"><Label>Date</Label><Input v-model="paymentForm.payment_date" type="date" required /></div>
              <div class="space-y-2">
                <Label>Amount</Label>
                <Input v-model="paymentForm.amount" type="number" min="0.01" step="0.01" required />
                <p v-if="paymentForm.errors.amount" class="text-xs text-destructive">{{ paymentForm.errors.amount }}</p>
              </div>
              <div class="space-y-2">
                <Label>Method</Label>
                <Select v-model="paymentForm.method">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Cash / Bank Account</Label>
                <Select v-model="paymentForm.account_id">
                  <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Not selected</SelectItem>
                    <SelectItem v-for="account in accounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2"><Label>Reference</Label><Input v-model="paymentForm.reference" /></div>
              <Button type="submit" class="w-full" :disabled="paymentForm.processing || Number(group.balance || 0) <= 0"><WalletCards class="mr-2 h-4 w-4" />Record Payment</Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
