<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { Building2, Plus, Search, Store } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  hotels: { data: any[]; links: Array<{ url: string | null; label: string; active: boolean }> }
  filters: { search?: string }
  roomTypes: Record<string, string>
}>()

const search = ref(props.filters.search || '')
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Hotels', href: `/${props.company.slug}/umrah/settings/hotels` },
]

const applySearch = () => router.get(`/${props.company.slug}/umrah/settings/hotels`, { search: search.value }, { preserveState: true, replace: true })
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

    <Card>
      <CardContent class="p-0">
        <div v-if="!hotels.data.length" class="p-8 text-center text-sm text-muted-foreground">No hotels found.</div>
        <div v-for="hotel in hotels.data" :key="hotel.id" class="grid gap-4 border-b p-4 last:border-b-0 lg:grid-cols-[minmax(220px,1fr)_minmax(0,2fr)]">
          <div>
            <div class="flex items-center gap-3">
              <img v-if="hotel.vendor?.logo_url" :src="hotel.vendor.logo_url" :alt="`${hotel.vendor.name} logo`" class="h-10 w-10 rounded-md border object-contain" />
              <div><div class="font-medium">{{ hotel.name }}</div><div class="text-sm text-muted-foreground">{{ hotel.city }} · {{ hotel.vendor?.name || 'No vendor' }}</div></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <div v-for="rate in hotel.room_rates" :key="rate.id" class="min-w-[150px] rounded-md border px-3 py-2 text-sm">
              <Badge variant="secondary">{{ roomTypes[rate.room_type] || rate.room_type }}</Badge>
              <div class="mt-2"><MoneyText :amount="rate.retail_amount" :currency="company.base_currency" /> retail / bed</div>
              <div class="text-muted-foreground"><MoneyText :amount="rate.cost_amount" :currency="company.base_currency" /> cost / bed</div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <div v-if="hotels.links.length > 3" class="flex flex-wrap gap-2">
      <Button v-for="link in hotels.links" :key="link.label" variant="outline" size="sm" :disabled="!link.url" :class="{ 'border-primary': link.active }" @click="link.url && router.get(link.url)">
        <span v-html="link.label" />
      </Button>
    </div>
  </PageShell>
</template>
