<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
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
const statusForm = useForm({ is_active: false })
const updateStatus = (type: 'hotels' | 'hotel-vendors', record: any) => {
  statusForm.is_active = !record.is_active
  statusForm.patch(`/${props.company.slug}/umrah/settings/${type}/${record.id}/status`, {
    preserveScroll: true,
    onSuccess: () => toast.success(`${record.name} ${record.is_active ? 'deactivated' : 'reactivated'} successfully`),
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
        <Card><CardContent class="p-0">
          <Table>
            <TableHeader><TableRow><TableHead>Hotel</TableHead><TableHead>City</TableHead><TableHead>Vendor</TableHead><template v-for="([roomType, label]) in roomTypeEntries" :key="roomType"><TableHead class="text-right">{{ label }} Retail</TableHead><TableHead class="text-right">{{ label }} Cost</TableHead></template><TableHead>Status</TableHead><TableHead class="text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>
              <TableEmpty v-if="!hotels.data.length" :colspan="5 + roomTypeEntries.length * 2">No hotels found.</TableEmpty>
              <TableRow v-for="hotel in hotels.data" :key="hotel.id" :class="{ 'opacity-60': !hotel.is_active }">
                <TableCell class="font-medium">{{ hotel.name }}</TableCell>
                <TableCell>{{ hotel.city }}</TableCell>
                <TableCell>
                  <div class="flex items-center gap-2">
                    <img v-if="hotel.vendor?.logo_url" :src="hotel.vendor.logo_url" :alt="`${hotel.vendor.name} logo`" class="h-8 w-8 rounded border object-contain" />
                    <span>{{ hotel.vendor?.name || '-' }}</span>
                  </div>
                </TableCell>
                <template v-for="([roomType]) in roomTypeEntries" :key="roomType">
                  <TableCell class="text-right"><MoneyText v-if="roomRate(hotel, roomType, 'retail_amount') !== undefined" :amount="roomRate(hotel, roomType, 'retail_amount')" :currency="company.base_currency" /><span v-else>-</span></TableCell>
                  <TableCell class="text-right"><MoneyText v-if="roomRate(hotel, roomType, 'cost_amount') !== undefined" :amount="roomRate(hotel, roomType, 'cost_amount')" :currency="company.base_currency" /><span v-else>-</span></TableCell>
                </template>
                <TableCell><Badge :variant="hotel.is_active ? 'default' : 'secondary'">{{ hotel.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                <TableCell><div class="flex justify-end gap-1"><Button variant="ghost" size="icon" title="Edit hotel" @click="router.get(`/${company.slug}/umrah/settings/hotels/${hotel.id}/edit`)"><Pencil class="size-4" /></Button><Button variant="ghost" size="icon" :title="hotel.is_active ? 'Deactivate hotel' : 'Reactivate hotel'" :disabled="statusForm.processing" @click="updateStatus('hotels', hotel)"><Power v-if="hotel.is_active" class="size-4" /><RotateCcw v-else class="size-4" /></Button></div></TableCell>
              </TableRow>
            </TableBody>
          </Table>
          <RecordPagination :current-page="hotels.current_page" :last-page="hotels.last_page" :from="hotels.from" :to="hotels.to" :total="hotels.total" :previous-url="hotels.prev_page_url" :next-url="hotels.next_page_url" />
        </CardContent></Card>
      </TabsContent>
      <TabsContent value="vendors">
        <Card><CardContent class="p-0">
          <Table>
            <TableHeader><TableRow><TableHead>Vendor #</TableHead><TableHead>Vendor</TableHead><TableHead>Phone</TableHead><TableHead>Email</TableHead><TableHead>City</TableHead><TableHead class="text-center">Hotels</TableHead><TableHead class="text-right">Total Cost</TableHead><TableHead class="text-right">Paid</TableHead><TableHead class="text-right">Payable</TableHead><TableHead>Status</TableHead><TableHead class="text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>
              <TableEmpty v-if="!hotelVendors.data.length" :colspan="11">No hotel vendors found.</TableEmpty>
              <TableRow v-for="vendor in hotelVendors.data" :key="vendor.id" :class="{ 'opacity-60': !vendor.is_active }">
                <TableCell class="font-medium">{{ vendor.vendor_number }}</TableCell>
                <TableCell><div class="flex items-center gap-2"><img v-if="vendor.logo_url" :src="vendor.logo_url" :alt="`${vendor.name} logo`" class="h-8 w-8 rounded border object-contain" /><span>{{ vendor.name }}</span></div></TableCell>
                <TableCell>{{ vendor.phone || '-' }}</TableCell>
                <TableCell>{{ vendor.email || '-' }}</TableCell>
                <TableCell>{{ vendor.city || '-' }}</TableCell>
                <TableCell class="text-center">{{ vendor.hotels_count }}</TableCell>
                <TableCell class="text-right"><MoneyText :amount="vendor.total_cost" :currency="company.base_currency" /></TableCell>
                <TableCell class="text-right"><MoneyText :amount="vendor.total_paid" :currency="company.base_currency" /></TableCell>
                <TableCell class="text-right font-semibold"><MoneyText :amount="vendor.balance" :currency="company.base_currency" /></TableCell>
                <TableCell><Badge :variant="vendor.is_active ? 'default' : 'secondary'">{{ vendor.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                <TableCell><div class="flex justify-end gap-1"><Button variant="ghost" size="icon" title="Edit hotel vendor" @click="router.get(`/${company.slug}/umrah/settings/hotel-vendors/${vendor.id}/edit`)"><Pencil class="size-4" /></Button><Button variant="ghost" size="icon" :title="vendor.is_active ? 'Deactivate hotel vendor' : 'Reactivate hotel vendor'" :disabled="statusForm.processing" @click="updateStatus('hotel-vendors', vendor)"><Power v-if="vendor.is_active" class="size-4" /><RotateCcw v-else class="size-4" /></Button></div></TableCell>
              </TableRow>
            </TableBody>
          </Table>
          <RecordPagination :current-page="hotelVendors.current_page" :last-page="hotelVendors.last_page" :from="hotelVendors.from" :to="hotelVendors.to" :total="hotelVendors.total" :previous-url="hotelVendors.prev_page_url" :next-url="hotelVendors.next_page_url" />
        </CardContent></Card>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
