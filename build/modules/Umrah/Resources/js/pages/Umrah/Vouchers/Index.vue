<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { Plus, Search, ScrollText } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string }
  vouchers: { data: any[] }
  filters: { search?: string }
  statuses: Record<string, string>
  serviceBundles: Record<string, string>
}>()

const search = ref(props.filters.search || '')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
]

const applySearch = () => router.get(`/${props.company.slug}/umrah/vouchers`, { search: search.value }, { preserveState: true })
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

    <div class="relative max-w-xl">
      <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input v-model="search" class="pl-10" placeholder="Search voucher, group, or agent..." @keyup.enter="applySearch" />
    </div>

    <Card>
      <CardContent class="p-0">
        <div v-if="!vouchers.data.length" class="p-8 text-center text-sm text-muted-foreground">No vouchers yet.</div>
        <div
          v-for="voucher in vouchers.data"
          :key="voucher.id"
          class="grid cursor-pointer gap-3 border-b p-4 last:border-b-0 md:grid-cols-[1fr_170px_150px_110px]"
          @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}`)"
        >
          <div>
            <div class="font-medium">{{ voucher.voucher_number }} · {{ voucher.title }}</div>
            <div class="text-sm text-muted-foreground">
              {{ voucher.group?.group_number || 'No group' }} · {{ voucher.group?.name || 'No group name' }} · {{ voucher.agent?.name || 'No agent' }}
            </div>
            <Badge variant="outline" class="mt-2">{{ serviceBundles[voucher.service_bundle] || voucher.service_bundle }}</Badge>
          </div>
          <div class="text-sm">
            <div class="text-muted-foreground">Travel</div>
            <DateTimeText :value="serviceDate(voucher)" mode="date" />
          </div>
          <div class="text-sm">
            <div class="text-muted-foreground">Passengers</div>
            <div class="font-medium">{{ voucher.passengers_count }}</div>
          </div>
          <div class="flex items-start justify-end gap-2">
            <Badge variant="secondary">{{ statuses[voucher.status] || voucher.status }}</Badge>
            <Badge v-if="isPast(voucher)" variant="outline">Past</Badge>
          </div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
