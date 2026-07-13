<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Download, Pencil, Plane, Printer, ScrollText } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string; logo_url?: string | null }
  voucher: any
  statuses: Record<string, string>
  serviceBundles: Record<string, string>
  airlines: Record<string, string>
  airportCities: Record<string, string>
  agentCapabilities: { can_create: boolean; can_approve: boolean; can_edit: boolean; cutoff_hours: number | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
  { title: props.voucher.voucher_number, href: `/${props.company.slug}/umrah/vouchers/${props.voucher.id}` },
]
const approveForm = useForm({})
const page = usePage()
const canViewAccounting = computed(() => ['super_admin', 'owner', 'accountant'].includes(String((page.props.auth as any)?.currentCompanyRole || '')))
const canApprove = computed(() => props.agentCapabilities.can_approve)
const canEdit = computed(() => props.agentCapabilities.can_edit)
const includesTransport = computed(() => ['visa_transport', 'visa_transport_hotel', 'transport', 'transport_hotel'].includes(props.voucher.service_bundle))
const approve = () => approveForm.post(`/${props.company.slug}/umrah/vouchers/${props.voucher.id}/approve`, { preserveScroll: true, onSuccess: () => toast.success('Voucher approved successfully'), onError: () => toast.error('Failed to approve voucher') })

const escapeHtml = (value: unknown) => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;')

const formatDateTime = (value: unknown) => {
  if (!value) return ''
  const date = new Date(String(value))
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString()
}
const formatDate = (value: unknown) => {
  if (!value) return ''
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString()
}
const roomBeds = (stay: any) => Number(stay.beds_per_room || ({ sharing: 1, double: 2, triple: 3, quad: 4, quint: 5 } as Record<string, number>)[stay.room_type] || 0)

const voucherHtml = () => {
  const passengerRows = (props.voucher.passengers || []).map((passenger: any, index: number) => `
    <tr>
      <td>${index + 1}</td>
      <td>${escapeHtml(passenger.full_name)}</td>
      <td>${escapeHtml(passenger.passport_number || 'No passport')}</td>
      <td>${escapeHtml(passenger.nationality || '')}</td>
      <td>${escapeHtml(passenger.date_of_birth || (passenger.imported_age !== null ? `Age ${passenger.imported_age}` : ''))}</td>
      <td>${escapeHtml(passenger.visa_status || '')}</td>
    </tr>
  `).join('')

  const hotelRows = (props.voucher.hotel_stays || []).map((stay: any) => `
    <tr>
      <td>${escapeHtml(stay.hotel_name)}</td>
      <td>${escapeHtml(stay.city || '')}</td>
      <td>${escapeHtml(`${stay.room_count || 1} ${stay.room_type || ''} (${roomBeds(stay)} beds each)`)}</td>
      <td>${escapeHtml(formatDate(stay.check_in_date))}</td>
      <td>${escapeHtml(formatDate(stay.check_out_date))}</td>
      <td>${escapeHtml(stay.notes || '')}</td>
    </tr>
  `).join('')
  const hotelSection = `
  <h2>Hotel Stays</h2>
  <table>
    <thead><tr><th>Hotel</th><th>City</th><th>Room</th><th>Check-in</th><th>Checkout</th><th>Notes</th></tr></thead>
    <tbody>${hotelRows || '<tr><td colspan="6">No hotel stays added.</td></tr>'}</tbody>
  </table>`
  const transportRows = (props.voucher.group?.transport_items || []).map((item: any) => `
    <tr>
      <td>${escapeHtml(item.sector?.name || item.description || 'Transport')}</td>
      <td>${escapeHtml(item.service?.name || item.service?.vehicle_type || '')}</td>
      <td>${escapeHtml(formatDateTime(item.scheduled_at))}</td>
      <td>${escapeHtml(item.driver?.name || item.service?.driver_name || '')}</td>
      <td>${escapeHtml(item.driver?.phone || item.service?.driver_contact || '')}</td>
    </tr>
  `).join('')
  const transportSection = includesTransport.value ? `
  <h2>Transport</h2>
  <table>
    <thead><tr><th>Sector</th><th>Vehicle</th><th>Schedule</th><th>Driver</th><th>Contact</th></tr></thead>
    <tbody>${transportRows || `<tr><td colspan="5">${escapeHtml(props.voucher.group?.transport_mode === 'specialized' ? 'Specialized transport' : 'Standard bus transport')}</td></tr>`}</tbody>
  </table>` : ''

  const flightSection = props.voucher.service_bundle === 'hotel' ? '' : `
  <h2>Flights</h2>
  <div class="grid">
    <div class="box">
      <strong>Onward</strong><br>
      ${escapeHtml(props.voucher.onward_airline)} · ${escapeHtml(props.airlines[props.voucher.onward_airline] || '')} ${escapeHtml(props.voucher.onward_flight_number || '')}<br>
      ${escapeHtml(props.voucher.onward_departure_city)} · ${escapeHtml(props.airportCities[props.voucher.onward_departure_city] || '')}
      to ${escapeHtml(props.voucher.onward_arrival_city)} · ${escapeHtml(props.airportCities[props.voucher.onward_arrival_city] || '')}<br>
      Depart ${escapeHtml(formatDateTime(props.voucher.onward_departure_at))}<br>
      Arrive ${escapeHtml(formatDateTime(props.voucher.onward_arrival_at))}
    </div>
    <div class="box">
      <strong>Return</strong><br>
      ${escapeHtml(props.voucher.return_airline)} · ${escapeHtml(props.airlines[props.voucher.return_airline] || '')} ${escapeHtml(props.voucher.return_flight_number || '')}<br>
      ${escapeHtml(props.voucher.return_departure_city)} · ${escapeHtml(props.airportCities[props.voucher.return_departure_city] || '')}
      to ${escapeHtml(props.voucher.return_arrival_city)} · ${escapeHtml(props.airportCities[props.voucher.return_arrival_city] || '')}<br>
      Depart ${escapeHtml(formatDateTime(props.voucher.return_departure_at))}<br>
      Arrive ${escapeHtml(formatDateTime(props.voucher.return_arrival_at))}
    </div>
  </div>`

  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(props.voucher.voucher_number)} - Voucher</title>
  <style>
    body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
    h1, h2 { margin: 0; }
    h1 { font-size: 24px; }
    h2 { border-bottom: 1px solid #d1d5db; font-size: 15px; margin-top: 28px; padding-bottom: 6px; }
    .muted { color: #6b7280; }
    .grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 18px; }
    .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; }
    .label { color: #6b7280; font-size: 12px; margin-bottom: 4px; }
    table { border-collapse: collapse; margin-top: 10px; width: 100%; }
    th, td { border: 1px solid #d1d5db; font-size: 12px; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; }
    @media print { body { margin: 18mm; } }
  </style>
</head>
<body>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px">
    ${props.company.logo_url ? `<img src="${escapeHtml(props.company.logo_url)}" alt="Company logo" style="height:60px;max-width:180px;object-fit:contain">` : '<div></div>'}
    ${props.voucher.agent?.logo_url ? `<img src="${escapeHtml(props.voucher.agent.logo_url)}" alt="Agent logo" style="height:52px;max-width:150px;object-fit:contain">` : '<div></div>'}
  </div>
  <h1>${escapeHtml(props.voucher.title)}</h1>
  <div class="muted">${escapeHtml(props.voucher.voucher_number)} · ${escapeHtml(props.statuses[props.voucher.status] || props.voucher.status)} · ${escapeHtml(props.serviceBundles[props.voucher.service_bundle] || props.voucher.service_bundle)}</div>

  <div class="grid">
    <div class="box"><div class="label">Group</div>${escapeHtml(props.voucher.group?.group_number)} · ${escapeHtml(props.voucher.group?.name)}</div>
    <div class="box"><div class="label">Agent</div>${escapeHtml(props.voucher.agent?.name || '')}</div>
    <div class="box"><div class="label">Passengers</div>${(props.voucher.passengers || []).length}</div>
    <div class="box"><div class="label">Created By</div>${escapeHtml(props.voucher.created_by?.name || 'System')}</div>
  </div>

  ${flightSection}

  ${hotelSection}

  ${transportSection}

  <h2>Passengers</h2>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Passport</th><th>Nationality</th><th>DOB / Age</th><th>Status</th></tr></thead>
    <tbody>${passengerRows}</tbody>
  </table>
</body>
</html>`
}

const printVoucher = () => {
  const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=900,height=700')
  if (!printWindow) return

  printWindow.document.open()
  printWindow.document.write(voucherHtml())
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

const exportVoucher = () => {
  window.location.assign(`/${props.company.slug}/umrah/vouchers/${props.voucher.id}/pdf`)
}
</script>

<template>
  <Head :title="voucher.voucher_number" />
  <PageShell :title="`${voucher.voucher_number} · ${voucher.title}`" :description="`${voucher.agent?.name || 'No agent'} · ${voucher.passengers?.length || 0} passengers`" :breadcrumbs="breadcrumbs" :icon="ScrollText">
    <template #actions>
      <Button variant="outline" @click="printVoucher">
        <Printer class="mr-2 h-4 w-4" />
        Print
      </Button>
      <Button variant="outline" @click="exportVoucher">
        <Download class="mr-2 h-4 w-4" />
        Export PDF
      </Button>
      <Button v-if="voucher.status === 'draft' && canEdit" variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button v-if="voucher.status === 'draft' && canApprove" :disabled="approveForm.processing" @click="approve">Approve Voucher</Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups/${voucher.group.id}`)">
        <Plane class="mr-2 h-4 w-4" />
        Open Group
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-4">
      <Card><CardHeader><CardTitle>Status</CardTitle></CardHeader><CardContent class="flex flex-wrap gap-2"><Badge variant="secondary">{{ statuses[voucher.status] || voucher.status }}</Badge><Badge variant="outline">{{ serviceBundles[voucher.service_bundle] || voucher.service_bundle }}</Badge></CardContent></Card>
      <Card><CardHeader><CardTitle>Group</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.group?.group_number }} · {{ voucher.group?.name }}</CardContent></Card>
      <Card><CardHeader><CardTitle>Agent</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.agent?.name || 'No agent' }}</CardContent></Card>
      <Card><CardHeader><CardTitle>Created By</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.created_by?.name || 'System' }}</CardContent></Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card v-if="voucher.service_bundle !== 'hotel'">
        <CardHeader>
          <CardTitle>Flights</CardTitle>
          <CardDescription>Onward and return ticket details.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="rounded-md border p-3">
            <div class="font-medium">Onward · {{ voucher.onward_airline }} · {{ airlines[voucher.onward_airline] || 'Airline' }} {{ voucher.onward_flight_number || '' }}</div>
            <div class="text-sm font-medium">
              {{ voucher.onward_departure_city }} · {{ airportCities[voucher.onward_departure_city] || 'Departure city not set' }}
              →
              {{ voucher.onward_arrival_city }} · {{ airportCities[voucher.onward_arrival_city] || 'Arrival city not set' }}
            </div>
            <div class="text-sm text-muted-foreground">
              Depart <DateTimeText :value="voucher.onward_departure_at" mode="datetime" />
              · Arrive <DateTimeText :value="voucher.onward_arrival_at" mode="datetime" />
            </div>
          </div>
          <div class="rounded-md border p-3">
            <div class="font-medium">Return · {{ voucher.return_airline }} · {{ airlines[voucher.return_airline] || 'Airline' }} {{ voucher.return_flight_number || '' }}</div>
            <div class="text-sm font-medium">
              {{ voucher.return_departure_city }} · {{ airportCities[voucher.return_departure_city] || 'Departure city not set' }}
              →
              {{ voucher.return_arrival_city }} · {{ airportCities[voucher.return_arrival_city] || 'Arrival city not set' }}
            </div>
            <div class="text-sm text-muted-foreground">
              Depart <DateTimeText :value="voucher.return_departure_at" mode="datetime" />
              · Arrive <DateTimeText :value="voucher.return_arrival_at" mode="datetime" />
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-if="includesTransport">
        <CardHeader>
          <CardTitle>Transport</CardTitle>
          <CardDescription>Transport included with this voucher.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!voucher.group?.transport_items?.length" class="rounded-md border p-3 text-sm">
            {{ voucher.group?.transport_mode === 'specialized' ? 'Specialized transport' : 'Standard bus transport' }}
          </div>
          <div v-for="item in voucher.group?.transport_items || []" :key="item.id" class="rounded-md border p-3">
            <div class="font-medium">{{ item.sector?.name || item.description || 'Transport' }}</div>
            <div class="text-sm">{{ item.service?.name || item.service?.vehicle_type || 'Vehicle not assigned' }}<span v-if="item.service?.number_plate"> · {{ item.service.number_plate }}</span></div>
            <div v-if="item.scheduled_at" class="text-sm text-muted-foreground"><DateTimeText :value="item.scheduled_at" mode="datetime" /></div>
            <div v-if="item.driver?.name || item.service?.driver_name" class="text-sm text-muted-foreground">{{ item.driver?.name || item.service?.driver_name }} · {{ item.driver?.phone || item.service?.driver_contact || 'No contact' }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Hotel Stays</CardTitle>
          <CardDescription>Stays included in this voucher.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!voucher.hotel_stays?.length" class="text-sm text-muted-foreground">No hotel stays added.</div>
          <div v-for="(stay, index) in voucher.hotel_stays" :key="index" class="rounded-md border p-3">
            <div class="font-medium">{{ stay.hotel_name }}<span v-if="stay.city"> · {{ stay.city }}</span></div>
            <div class="text-sm">{{ stay.room_count || 1 }} × {{ stay.room_type }} · {{ roomBeds(stay) }} beds each · {{ stay.source === 'company' ? 'Company supplied' : 'Self arranged' }}</div>
            <div class="text-sm text-muted-foreground"><DateTimeText :value="stay.check_in_date" mode="date" /> to <DateTimeText :value="stay.check_out_date" mode="date" /></div>
            <div v-if="['visa_transport_hotel', 'transport_hotel', 'hotel'].includes(voucher.service_bundle) && stay.source === 'company'" class="text-sm text-muted-foreground">Charge <MoneyText :amount="stay.total_retail_amount" :currency="company.base_currency" /><span v-if="canViewAccounting"> · Cost <MoneyText :amount="stay.total_cost_amount" :currency="company.base_currency" /></span></div>
            <div v-else class="text-sm text-muted-foreground">Itinerary only · No hotel charge</div>
            <div v-if="stay.notes" class="text-sm text-muted-foreground">{{ stay.notes }}</div>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Passengers</CardTitle>
        <CardDescription>Members covered by this voucher.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-for="passenger in voucher.passengers" :key="passenger.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_180px_140px_160px]">
          <div>
            <div class="font-medium">{{ passenger.full_name }}</div>
            <div class="text-sm text-muted-foreground">{{ passenger.passport_number || 'No passport' }}</div>
          </div>
          <div>{{ passenger.nationality || 'No nationality' }}</div>
          <div>{{ passenger.date_of_birth || (passenger.imported_age !== null ? `Age ${passenger.imported_age}` : 'Age not set') }}</div>
          <Badge variant="secondary">{{ passenger.visa_status }}</Badge>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
