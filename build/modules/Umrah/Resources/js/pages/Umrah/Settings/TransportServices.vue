<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Bus, Save } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  transportServices: any[]
  vehicleTypes: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Transport Services', href: `/${props.company.slug}/umrah/settings/transport-services` },
]

const form = useForm({
  name: '',
  vehicle_type_id: 'none',
  make: '',
  model: '',
  color: '',
  number_plate: '',
  driver_name: '',
  driver_contact: '',
  default_sale_amount: '0',
  default_cost_amount: '0',
  notes: '',
})

const submit = () => form
  .transform((data) => ({
    ...data,
    vehicle_type_id: data.vehicle_type_id === 'none' ? null : data.vehicle_type_id,
    default_sale_amount: Number(data.default_sale_amount || 0),
    default_cost_amount: Number(data.default_cost_amount || 0),
  }))
  .post(`/${props.company.slug}/umrah/settings/transport-services`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Transport service added successfully')
      form.reset()
      form.vehicle_type_id = 'none'
      form.default_sale_amount = '0'
      form.default_cost_amount = '0'
    },
    onError: () => toast.error('Failed to add transport service'),
  })
</script>

<template>
  <Head title="Transport Services" />
  <PageShell title="Transport Services" description="Vehicles, drivers, and default transport charges for groups." :breadcrumbs="breadcrumbs" :icon="Bus">
    <div class="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
      <Card>
        <CardHeader><CardTitle>Add Transport Service</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Jeddah airport pickup" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Vehicle Type</Label>
              <Select v-model="form.vehicle_type_id">
                <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No type</SelectItem>
                  <SelectItem v-for="vehicle in vehicleTypes" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-2"><Label>Make</Label><Input v-model="form.make" placeholder="Toyota" /></div>
              <div class="space-y-2"><Label>Model</Label><Input v-model="form.model" placeholder="Hiace" /></div>
              <div class="space-y-2"><Label>Color</Label><Input v-model="form.color" /></div>
              <div class="space-y-2"><Label>Number Plate</Label><Input v-model="form.number_plate" /></div>
              <div class="space-y-2"><Label>Driver</Label><Input v-model="form.driver_name" /></div>
              <div class="space-y-2"><Label>Driver Contact</Label><Input v-model="form.driver_contact" /></div>
              <div class="space-y-2"><Label>Default Charge</Label><Input v-model="form.default_sale_amount" type="number" min="0" step="0.01" /></div>
              <div class="space-y-2"><Label>Default Cost</Label><Input v-model="form.default_cost_amount" type="number" min="0" step="0.01" /></div>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <Button type="submit" class="w-full" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Transport</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Available Transport</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!transportServices.length" class="text-sm text-muted-foreground">No transport services yet.</div>
          <div v-for="service in transportServices" :key="service.id" class="grid gap-3 rounded-md border p-3 lg:grid-cols-[1fr_150px_150px]">
            <div>
              <div class="font-medium">{{ service.name }}</div>
              <div class="text-sm text-muted-foreground">
                {{ service.vehicle_type?.name || 'No type' }}
                <span v-if="service.make || service.model"> · {{ [service.make, service.model].filter(Boolean).join(' ') }}</span>
                <span v-if="service.number_plate"> · {{ service.number_plate }}</span>
              </div>
              <div class="text-sm text-muted-foreground">
                {{ service.driver_name || 'No driver' }}<span v-if="service.driver_contact"> · {{ service.driver_contact }}</span>
              </div>
            </div>
            <div><div class="text-xs text-muted-foreground">Charge</div><MoneyText :amount="service.default_sale_amount" :currency="company.base_currency" /></div>
            <div><div class="text-xs text-muted-foreground">Cost</div><MoneyText :amount="service.default_cost_amount" :currency="company.base_currency" /></div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
