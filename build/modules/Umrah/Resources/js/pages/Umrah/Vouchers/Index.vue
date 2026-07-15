<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { LoaderCircle, Plus, Search, ScrollText, X } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string }
  vouchers: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  filters: { search?: string }
  statuses: Record<string, string>
  serviceBundles: Record<string, string>
}>()

const search = ref(props.filters.search || '')
const searching = ref(false)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
]

const applySearch = () => {
  search.value = search.value.trim()
  router.get(`/${props.company.slug}/umrah/vouchers`, { search: search.value }, {
    preserveState: true,
    onStart: () => { searching.value = true },
    onFinish: () => { searching.value = false },
  })
}
const clearSearch = () => {
  search.value = ''
  applySearch()
}
const isPast = (voucher: any) => {
  const end = voucher.service_bundle === 'hotel'
    ? voucher.hotel_stays?.at(-1)?.check_out_date
    : voucher.return_arrival_at
  return Boolean(end && new Date(end) < new Date())
}
const serviceDate = (voucher: any) => voucher.service_bundle === 'hotel' ? voucher.hotel_stays?.[0]?.check_in_date : voucher.onward_departure_at
</script>

<template>
  <Head title="Vouchers" />
  <PageShell title="Vouchers" description="Journey schedules for selected group members." :breadcrumbs="breadcrumbs" :icon="ScrollText">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/umrah/vouchers/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Voucher
      </Button>
    </template>

    <div class="grid max-w-3xl gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" class="pl-10" placeholder="Group number, name, passenger, or passport" @keyup.enter="applySearch" />
      </div>
      <Button variant="secondary" :disabled="searching" @click="applySearch">
        <LoaderCircle v-if="searching" class="mr-2 h-4 w-4 animate-spin" />
        <Search v-else class="mr-2 h-4 w-4" />Search
      </Button>
      <Button v-if="search" variant="ghost" :disabled="searching" @click="clearSearch">
        <X class="mr-2 h-4 w-4" />Clear
      </Button>
    </div>

    <Card>
      <CardContent class="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Voucher #</TableHead>
              <TableHead>Title</TableHead>
              <TableHead>Group</TableHead>
              <TableHead>Agent</TableHead>
              <TableHead>Service</TableHead>
              <TableHead>Service Date</TableHead>
              <TableHead class="text-center">Pax</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Journey</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableEmpty v-if="!vouchers.data.length" :colspan="9">{{ search ? 'No vouchers match your search.' : 'No vouchers yet.' }}</TableEmpty>
            <TableRow v-for="voucher in vouchers.data" :key="voucher.id" class="cursor-pointer" @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}`)">
              <TableCell class="font-medium">{{ voucher.voucher_number }}</TableCell>
              <TableCell class="max-w-56 truncate">{{ voucher.title || '-' }}</TableCell>
              <TableCell>{{ voucher.group?.group_number || '-' }}</TableCell>
              <TableCell>{{ voucher.agent?.name || '-' }}</TableCell>
              <TableCell><Badge variant="outline">{{ serviceBundles[voucher.service_bundle] || voucher.service_bundle }}</Badge></TableCell>
              <TableCell><DateTimeText :value="serviceDate(voucher)" mode="date" /></TableCell>
              <TableCell class="text-center font-medium">{{ voucher.passengers_count }}</TableCell>
              <TableCell><Badge variant="secondary">{{ statuses[voucher.status] || voucher.status }}</Badge></TableCell>
              <TableCell><Badge variant="outline">{{ isPast(voucher) ? 'Past' : 'Upcoming' }}</Badge></TableCell>
            </TableRow>
          </TableBody>
        </Table>
        <RecordPagination :current-page="vouchers.current_page" :last-page="vouchers.last_page" :from="vouchers.from" :to="vouchers.to" :total="vouchers.total" :previous-url="vouchers.prev_page_url" :next-url="vouchers.next_page_url" />
      </CardContent>
    </Card>
  </PageShell>
</template>
