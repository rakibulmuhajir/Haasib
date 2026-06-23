<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Plane, Plus, WalletCards } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  group: any
  paymentMethods: Record<string, string>
  passengerStatuses: Record<string, string>
  accounts: any[]
}>()

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

const remainingAfterPayment = computed(() => {
  return Math.max(Number(props.group.balance || 0) - Number(paymentForm.amount || 0), 0)
})

const addPassenger = () => passengerForm.post(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers`, {
  preserveScroll: true,
  onSuccess: () => {
    toast.success('Passenger added successfully')
    passengerForm.reset()
    passengerForm.visa_status = 'received'
  },
  onError: () => toast.error('Failed to add passenger'),
})

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
    <div class="grid gap-4 md:grid-cols-4">
      <Card><CardHeader><CardTitle>Receivable</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.total_receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Paid</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.total_paid" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.balance" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Profit</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="group.profit" :currency="company.base_currency" /></CardContent></Card>
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
              <div class="font-medium">{{ group.transport_required ? `${group.transport_quantity} × ${group.transport_service?.name || group.vehicle_type?.name || 'vehicle'}` : 'Not required' }}</div>
              <div v-if="group.transport_service?.driver_name || group.transport_service?.number_plate" class="text-xs text-muted-foreground">
                {{ group.transport_service?.driver_name || 'No driver' }}<span v-if="group.transport_service?.number_plate"> · {{ group.transport_service.number_plate }}</span>
              </div>
            </div>
            <div><div class="text-sm text-muted-foreground">Visa Sale</div><div class="font-medium"><MoneyText :amount="group.visa_sale_amount" :currency="company.base_currency" /></div></div>
            <div><div class="text-sm text-muted-foreground">Transport Charge</div><div class="font-medium"><MoneyText :amount="group.transport_amount" :currency="company.base_currency" /></div></div>
            <div><div class="text-sm text-muted-foreground">Visa Cost</div><div class="font-medium"><MoneyText :amount="group.visa_cost_amount" :currency="company.base_currency" /></div></div>
            <div><div class="text-sm text-muted-foreground">Transport Cost</div><div class="font-medium"><MoneyText :amount="group.transport_cost_amount" :currency="company.base_currency" /></div></div>
            <div>
              <div class="text-sm text-muted-foreground">Sale Journal</div>
              <Button v-if="group.sale_transaction" variant="link" class="h-auto p-0" @click="router.get(`/${company.slug}/journals/${group.sale_transaction.id}`)">
                {{ group.sale_transaction.transaction_number }}
              </Button>
              <div v-else class="font-medium">Not posted</div>
            </div>
            <div>
              <div class="text-sm text-muted-foreground">Cost Journal</div>
              <Button v-if="group.cost_transaction" variant="link" class="h-auto p-0" @click="router.get(`/${company.slug}/journals/${group.cost_transaction.id}`)">
                {{ group.cost_transaction.transaction_number }}
              </Button>
              <div v-else class="font-medium">Not posted</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Passengers</CardTitle></CardHeader>
          <CardContent class="space-y-3">
            <div v-if="!group.passengers?.length" class="text-sm text-muted-foreground">No passengers added yet.</div>
            <div v-for="passenger in group.passengers" :key="passenger.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_160px_140px_120px]">
              <div>
                <div class="font-medium">{{ passenger.full_name }}</div>
                <div class="text-xs text-muted-foreground">{{ passenger.notes || 'No notes' }}</div>
              </div>
              <div>{{ passenger.passport_number || 'No passport' }}</div>
              <div>{{ passenger.nationality || 'Nationality not set' }}</div>
              <Badge variant="secondary">{{ passengerStatuses[passenger.visa_status] || passenger.visa_status }}</Badge>
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
