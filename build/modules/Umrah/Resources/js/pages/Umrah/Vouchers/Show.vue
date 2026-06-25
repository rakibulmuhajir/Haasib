<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Plane, ScrollText } from 'lucide-vue-next'

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
</script>

<template>
  <Head :title="voucher.voucher_number" />
  <PageShell :title="`${voucher.voucher_number} · ${voucher.title}`" :description="`${voucher.agent?.name || 'No agent'} · ${voucher.passengers?.length || 0} passengers`" :breadcrumbs="breadcrumbs" :icon="ScrollText">
    <template #actions>
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
