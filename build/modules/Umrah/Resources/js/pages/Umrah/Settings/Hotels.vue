<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { BreadcrumbItem } from '@/types'
import { Building2, Pencil, Plus, Power, RotateCcw, Search, Store } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  hotels: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  hotelVendors: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  filters: { search?: string; tab?: string }
  roomTypes: Record<string, string>
}>()

const search = ref(props.filters.search || '')
const activeTab = ref(props.filters.tab || 'hotels')
const roomTypeEntries = Object.entries(props.roomTypes)
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Hotels', href: `/${props.company.slug}/umrah/settings/hotels` },
]

const applySearch = () => router.get(`/${props.company.slug}/umrah/settings/hotels`, { search: search.value, tab: activeTab.value }, { preserveState: true, replace: true })
const changeTab = (tab: string | number) => {
  activeTab.value = String(tab)
  router.get(`/${props.company.slug}/umrah/settings/hotels`, { search: search.value, tab: activeTab.value }, { preserveState: true, replace: true })
}
const roomRate = (hotel: any, roomType: string, field: 'retail_amount' | 'cost_amount') => hotel.room_rates.find((rate: any) => rate.room_type === roomType)?.[field]
/**
 * The rate columns are whatever room types this company sells, so the register
 * is described rather than written out. Each room type contributes a retail and
 * a cost column, both figures, and the register right-aligns them for free.
 */
const hotelColumns = [
  { key: 'name', label: 'Hotel', kind: 'text' as const },
  { key: 'city', label: 'City', kind: 'text' as const },
  { key: 'vendor', label: 'Vendor', kind: 'text' as const },
  ...roomTypeEntries.flatMap(([roomType, label]) => [
    { key: `retail_${roomType}`, label: `${label} retail`, kind: 'amount' as const },
    { key: `cost_${roomType}`, label: `${label} cost`, kind: 'amount' as const },
  ]),
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]

/** The same list again, as slot names, so the cells can be bound in one loop. */
const rateCells = roomTypeEntries.flatMap(([roomType]) => [
  { slot: `cell-retail_${roomType}`, roomType, field: 'retail_amount' as const },
  { slot: `cell-cost_${roomType}`, roomType, field: 'cost_amount' as const },
])

const vendorColumns = [
  { key: 'vendor_number', label: 'Vendor #', kind: 'ref' as const },
  { key: 'name', label: 'Vendor', kind: 'text' as const },
  { key: 'phone', label: 'Phone', kind: 'text' as const },
  { key: 'email', label: 'Email', kind: 'text' as const },
  { key: 'city', label: 'City', kind: 'text' as const },
  { key: 'hotels_count', label: 'Hotels', kind: 'amount' as const },
  { key: 'total_cost', label: 'Total cost', kind: 'amount' as const },
  { key: 'total_paid', label: 'Paid', kind: 'amount' as const },
  { key: 'balance', label: 'Payable', kind: 'amount' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]

const statusForm = useForm({ is_active: false })
const updateStatus = (type: 'hotels' | 'hotel-vendors', record: any) => {
  statusForm.is_active = !record.is_active
  statusForm.patch(`/${props.company.slug}/umrah/settings/${type}/${record.id}/status`, {
    preserveScroll: true,
    onError: () => toast.error(statusForm.errors.hotel || statusForm.errors.vendor || 'Status could not be changed'),
  })
}
</script>

<template>
  <Head title="Hotels" />
  <PageShell title="Hotels" description="Hotels and per-bed nightly rates used in vouchers." :breadcrumbs="breadcrumbs" :icon="Building2">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/settings/hotel-vendors/create`)">
        <Store class="mr-2 h-4 w-4" />
        Add Vendor
      </Button>
      <Button @click="router.get(`/${company.slug}/umrah/settings/hotels/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Hotel
      </Button>
    </template>

    <div class="relative max-w-xl">
      <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input v-model="search" class="pl-10" placeholder="Search hotel, city, or vendor" @keyup.enter="applySearch" />
    </div>

    <Tabs :model-value="activeTab" @update:model-value="changeTab">
      <TabsList><TabsTrigger value="hotels">Hotels</TabsTrigger><TabsTrigger value="vendors">Hotel Vendors</TabsTrigger></TabsList>
      <TabsContent value="hotels">
        <Card variant="register"><CardContent>
          <LedgerRegister :data="hotels.data" :columns="hotelColumns">
            <template #empty>No hotels found.</template>

            <template #cell-vendor="{ row }">
              <div class="flex items-center gap-2">
                <img v-if="row.vendor?.logo_url" :src="row.vendor.logo_url" :alt="`${row.vendor.name} logo`" class="h-8 w-8 rounded-sm border object-contain" />
                <span>{{ row.vendor?.name || '—' }}</span>
              </div>
            </template>

            <template v-for="cell in rateCells" :key="cell.slot" #[cell.slot]="{ row }">
              <MoneyText
                v-if="roomRate(row, cell.roomType, cell.field) !== undefined"
                :amount="roomRate(row, cell.roomType, cell.field)"
                :currency="company.base_currency"
              />
              <span v-else>—</span>
            </template>

            <template #cell-status="{ row }">
              <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>

            <template #cell-actions="{ row }">
              <div class="flex justify-end gap-1">
                <Button variant="ghost" size="icon" title="Edit hotel" @click="router.get(`/${company.slug}/umrah/settings/hotels/${row.id}/edit`)">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" :title="row.is_active ? 'Deactivate hotel' : 'Reactivate hotel'" :disabled="statusForm.processing" @click="updateStatus('hotels', row)">
                  <Power v-if="row.is_active" class="size-4" />
                  <RotateCcw v-else class="size-4" />
                </Button>
              </div>
            </template>
          </LedgerRegister>
          <RecordPagination :current-page="hotels.current_page" :last-page="hotels.last_page" :from="hotels.from" :to="hotels.to" :total="hotels.total" :previous-url="hotels.prev_page_url" :next-url="hotels.next_page_url" />
        </CardContent></Card>
      </TabsContent>
      <TabsContent value="vendors">
        <Card variant="register"><CardContent>
          <LedgerRegister :data="hotelVendors.data" :columns="vendorColumns">
            <template #empty>No hotel vendors found.</template>

            <template #cell-name="{ row }">
              <div class="flex items-center gap-2">
                <img v-if="row.logo_url" :src="row.logo_url" :alt="`${row.name} logo`" class="h-8 w-8 rounded-sm border object-contain" />
                <span>{{ row.name }}</span>
              </div>
            </template>

            <template #cell-phone="{ row }">{{ row.phone || '—' }}</template>
            <template #cell-email="{ row }">{{ row.email || '—' }}</template>
            <template #cell-city="{ row }">{{ row.city || '—' }}</template>

            <template #cell-total_cost="{ row }">
              <MoneyText :amount="row.total_cost" :currency="company.base_currency" />
            </template>
            <template #cell-total_paid="{ row }">
              <MoneyText :amount="row.total_paid" :currency="company.base_currency" />
            </template>
            <template #cell-balance="{ row }">
              <MoneyText :amount="row.balance" :currency="company.base_currency" class="font-semibold" />
            </template>

            <template #cell-status="{ row }">
              <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>

            <template #cell-actions="{ row }">
              <div class="flex justify-end gap-1">
                <Button variant="ghost" size="icon" title="Edit hotel vendor" @click="router.get(`/${company.slug}/umrah/settings/hotel-vendors/${row.id}/edit`)">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" :title="row.is_active ? 'Deactivate hotel vendor' : 'Reactivate hotel vendor'" :disabled="statusForm.processing" @click="updateStatus('hotel-vendors', row)">
                  <Power v-if="row.is_active" class="size-4" />
                  <RotateCcw v-else class="size-4" />
                </Button>
              </div>
            </template>
          </LedgerRegister>
          <RecordPagination :current-page="hotelVendors.current_page" :last-page="hotelVendors.last_page" :from="hotelVendors.from" :to="hotelVendors.to" :total="hotelVendors.total" :previous-url="hotelVendors.prev_page_url" :next-url="hotelVendors.next_page_url" />
        </CardContent></Card>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
