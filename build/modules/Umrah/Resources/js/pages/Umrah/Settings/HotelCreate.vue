<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Building2, Save } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  vendors: any[]
  roomTypes: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Hotels', href: `/${props.company.slug}/umrah/settings/hotels` },
  { title: 'Add Hotel', href: `/${props.company.slug}/umrah/settings/hotels/create` },
]
const vendorOptions = computed(() => props.vendors.map((vendor) => ({ value: vendor.id, label: vendor.name })))
const form = useForm({
  hotel_vendor_id: '', name: '', city: '', notes: '',
  room_rates: [] as Array<{ room_type: string; retail_amount: string; cost_amount: string }>,
})
const toggleRoomType = (roomType: string, checked: boolean | 'indeterminate') => {
  form.room_rates = checked === true
    ? [...form.room_rates, { room_type: roomType, retail_amount: '0', cost_amount: '0' }]
    : form.room_rates.filter((rate) => rate.room_type !== roomType)
}
const roomRate = (roomType: string) => form.room_rates.find((rate) => rate.room_type === roomType)
const setRoomRate = (roomType: string, field: 'retail_amount' | 'cost_amount', value: string | number) => {
  const rate = roomRate(roomType)
  if (rate) rate[field] = String(value)
}
const submit = () => form.transform((data) => ({
  ...data,
  room_rates: data.room_rates.map((rate) => ({ ...rate, retail_amount: Number(rate.retail_amount || 0), cost_amount: Number(rate.cost_amount || 0) })),
})).post(`/${props.company.slug}/umrah/settings/hotels`, {
  onSuccess: () => toast.success('Hotel added successfully'),
  onError: () => toast.error('Failed to add hotel'),
})
</script>

<template>
  <Head title="Add Hotel" />
  <PageShell title="Add Hotel" description="Set the vendor and per-bed nightly rates for available room types." :breadcrumbs="breadcrumbs" :icon="Building2">
    <Card class="mx-auto max-w-4xl">
      <CardHeader><CardTitle>Hotel Details</CardTitle></CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-2"><Label>Name</Label><Input v-model="form.name" required /><p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p></div>
            <div class="space-y-2"><Label>City</Label><Select v-model="form.city"><SelectTrigger><SelectValue placeholder="Select city" /></SelectTrigger><SelectContent><SelectItem value="Makkah">Makkah</SelectItem><SelectItem value="Madinah">Madinah</SelectItem></SelectContent></Select><p v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</p></div>
            <div class="space-y-2"><Label>Vendor</Label><SearchableSelect v-model="form.hotel_vendor_id" :options="vendorOptions" :show-value="false" placeholder="Select vendor" search-placeholder="Type vendor name" /><p v-if="form.errors.hotel_vendor_id" class="text-xs text-destructive">{{ form.errors.hotel_vendor_id }}</p></div>
          </div>

          <div class="space-y-2">
            <Label>Rooms and Per-Bed Nightly Rates</Label>
            <div class="divide-y rounded-md border">
              <div v-for="(label, roomType) in roomTypes" :key="roomType" class="grid min-h-[76px] gap-3 p-3 sm:grid-cols-[140px_1fr_1fr] sm:items-end">
                <Label class="flex cursor-pointer items-center gap-2 pb-2"><Checkbox :model-value="Boolean(roomRate(String(roomType)))" @update:model-value="toggleRoomType(String(roomType), $event)" />{{ label }}</Label>
                <div class="space-y-1"><Label class="text-xs text-muted-foreground">Retail / bed / night</Label><Input v-if="roomRate(String(roomType))" :model-value="roomRate(String(roomType))?.retail_amount" type="number" min="0" step="0.01" @update:model-value="setRoomRate(String(roomType), 'retail_amount', $event)" /></div>
                <div class="space-y-1"><Label class="text-xs text-muted-foreground">Cost / bed / night</Label><Input v-if="roomRate(String(roomType))" :model-value="roomRate(String(roomType))?.cost_amount" type="number" min="0" step="0.01" @update:model-value="setRoomRate(String(roomType), 'cost_amount', $event)" /></div>
              </div>
            </div>
            <p v-if="form.errors.room_rates" class="text-xs text-destructive">{{ form.errors.room_rates }}</p>
          </div>

          <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /><p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p></div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" @click="router.get(`/${company.slug}/umrah/settings/hotels`)">Cancel</Button>
            <Button type="submit" :disabled="form.processing || !form.room_rates.length"><Save class="mr-2 h-4 w-4" />Save Hotel</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>
