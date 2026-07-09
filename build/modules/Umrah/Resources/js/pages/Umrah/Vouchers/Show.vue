<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Download, Plane, Printer, ScrollText } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string }
  voucher: any
  statuses: Record<string, string>
  airlines: Record<string, string>
  airportCities: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
  { title: props.voucher.voucher_number, href: `/${props.company.slug}/umrah/vouchers/${props.voucher.id}` },
]

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

const voucherHtml = () => {
  const passengerRows = (props.voucher.passengers || []).map((passenger: any, index: number) => `
    <tr>
      <td>${index + 1}</td>
      <td>${escapeHtml(passenger.full_name)}</td>
      <td>${escapeHtml(passenger.passport_number || 'No passport')}</td>
      <td>${escapeHtml(passenger.nationality || '')}</td>
      <td>${escapeHtml(passenger.visa_status || '')}</td>
    </tr>
  `).join('')

  const hotelRows = (props.voucher.hotel_stays || []).map((stay: any) => `
    <tr>
      <td>${escapeHtml(stay.hotel_name)}</td>
      <td>${escapeHtml(stay.city || '')}</td>
      <td>${escapeHtml(stay.check_in_date || '')}</td>
      <td>${escapeHtml(stay.check_out_date || '')}</td>
      <td>${escapeHtml(stay.notes || '')}</td>
    </tr>
  `).join('')

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
  <h1>${escapeHtml(props.voucher.title)}</h1>
  <div class="muted">${escapeHtml(props.voucher.voucher_number)} · ${escapeHtml(props.statuses[props.voucher.status] || props.voucher.status)}</div>

  <div class="grid">
    <div class="box"><div class="label">Group</div>${escapeHtml(props.voucher.group?.group_number)} · ${escapeHtml(props.voucher.group?.name)}</div>
    <div class="box"><div class="label">Agent</div>${escapeHtml(props.voucher.agent?.name || '')}</div>
    <div class="box"><div class="label">Passengers</div>${(props.voucher.passengers || []).length}</div>
    <div class="box"><div class="label">Created By</div>${escapeHtml(props.voucher.created_by?.name || 'System')}</div>
  </div>

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
  </div>

  <h2>Hotel Stays</h2>
  <table>
    <thead><tr><th>Hotel</th><th>City</th><th>Check-in</th><th>Checkout</th><th>Notes</th></tr></thead>
    <tbody>${hotelRows || '<tr><td colspan="5">No hotel stays added.</td></tr>'}</tbody>
  </table>

  <h2>Passengers</h2>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Passport</th><th>Nationality</th><th>Status</th></tr></thead>
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
  const blob = new Blob([voucherHtml()], { type: 'text/html;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${props.voucher.voucher_number || 'voucher'}.html`
  link.click()
  URL.revokeObjectURL(url)
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
        Export
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups/${voucher.group.id}`)">
        <Plane class="mr-2 h-4 w-4" />
        Open Group
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-4">
      <Card><CardHeader><CardTitle>Status</CardTitle></CardHeader><CardContent><Badge variant="secondary">{{ statuses[voucher.status] || voucher.status }}</Badge></CardContent></Card>
      <Card><CardHeader><CardTitle>Group</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.group?.group_number }} · {{ voucher.group?.name }}</CardContent></Card>
      <Card><CardHeader><CardTitle>Agent</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.agent?.name || 'No agent' }}</CardContent></Card>
      <Card><CardHeader><CardTitle>Created By</CardTitle></CardHeader><CardContent class="font-medium">{{ voucher.created_by?.name || 'System' }}</CardContent></Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
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

      <Card>
        <CardHeader>
          <CardTitle>Hotel Stays</CardTitle>
          <CardDescription>Stays included in this voucher.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!voucher.hotel_stays?.length" class="text-sm text-muted-foreground">No hotel stays added.</div>
          <div v-for="(stay, index) in voucher.hotel_stays" :key="index" class="rounded-md border p-3">
            <div class="font-medium">{{ stay.hotel_name }}<span v-if="stay.city"> · {{ stay.city }}</span></div>
            <div class="text-sm text-muted-foreground">{{ stay.check_in_date }} to {{ stay.check_out_date }}</div>
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
        <div v-for="passenger in voucher.passengers" :key="passenger.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_180px_160px]">
          <div>
            <div class="font-medium">{{ passenger.full_name }}</div>
            <div class="text-sm text-muted-foreground">{{ passenger.passport_number || 'No passport' }}</div>
          </div>
          <div>{{ passenger.nationality || 'No nationality' }}</div>
          <Badge variant="secondary">{{ passenger.visa_status }}</Badge>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
