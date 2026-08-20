<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MetaChip from '@/components/MetaChip.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
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

/**
 * Service bundle and journey are annotations — which package this is, and
 * whether it has already happened. Neither is a state of the voucher, so
 * neither gets the status chip; only `status` does.
 */
const columns = [
  { key: 'voucher_number', label: 'Voucher #', kind: 'ref' as const },
  { key: 'title', label: 'Title', kind: 'text' as const },
  { key: 'group', label: 'Group', kind: 'text' as const },
  { key: 'agent', label: 'Agent', kind: 'text' as const },
  { key: 'service_bundle', label: 'Service', kind: 'text' as const },
  { key: 'service_date', label: 'Service date', kind: 'date' as const },
  { key: 'passengers_count', label: 'Pax', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'journey', label: 'Journey', kind: 'text' as const },
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

    <Card variant="register">
      <CardContent>
        <LedgerRegister
          :data="vouchers.data"
          :columns="columns"
          clickable
          @row-click="(row) => router.get(`/${company.slug}/umrah/vouchers/${row.id}`)"
        >
          <template #empty>{{ search ? 'No vouchers match your search.' : 'No vouchers yet.' }}</template>

          <template #cell-title="{ row }">
            <span class="block max-w-56 truncate">{{ row.title || '—' }}</span>
          </template>
          <template #cell-group="{ row }">{{ row.group?.group_number || '—' }}</template>
          <template #cell-agent="{ row }">{{ row.agent?.name || '—' }}</template>

          <template #cell-service_bundle="{ row }">
            <MetaChip tone="neutral" bare>{{ serviceBundles[row.service_bundle] || row.service_bundle }}</MetaChip>
          </template>

          <template #cell-service_date="{ row }">
            <DateTimeText :value="serviceDate(row)" mode="date" />
          </template>

          <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :fallback="statuses[row.status] || row.status" />
          </template>

          <template #cell-journey="{ row }">
            <MetaChip :tone="isPast(row) ? 'neutral' : 'attention'">{{ isPast(row) ? 'Past' : 'Upcoming' }}</MetaChip>
          </template>
        </LedgerRegister>
        <RecordPagination :current-page="vouchers.current_page" :last-page="vouchers.last_page" :from="vouchers.from" :to="vouchers.to" :total="vouchers.total" :previous-url="vouchers.prev_page_url" :next-url="vouchers.next_page_url" />
      </CardContent>
    </Card>
  </PageShell>
</template>
