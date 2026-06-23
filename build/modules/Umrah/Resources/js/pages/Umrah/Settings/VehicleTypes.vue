<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Bus, Save } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string }
  vehicleTypes: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Vehicle Types', href: `/${props.company.slug}/umrah/settings/vehicle-types` },
]

const form = useForm({ name: '', seats: '', notes: '' })
const submit = () => form.post(`/${props.company.slug}/umrah/settings/vehicle-types`, {
  preserveScroll: true,
  onSuccess: () => {
    toast.success('Vehicle type added successfully')
    form.reset()
  },
  onError: () => toast.error('Failed to add vehicle type'),
})
</script>

<template>
  <Head title="Vehicle Types" />
  <PageShell title="Vehicle Types" description="Add transport options like car, 7 seater, coaster, or bus." :breadcrumbs="breadcrumbs" :icon="Bus">
    <div class="grid gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
      <Card>
        <CardHeader><CardTitle>Add Vehicle Type</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="7 seater" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Seats</Label>
              <Input v-model="form.seats" type="number" min="1" />
            </div>
            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Type</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Available Types</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!vehicleTypes.length" class="text-sm text-muted-foreground">No vehicle types yet.</div>
          <div v-for="vehicle in vehicleTypes" :key="vehicle.id" class="rounded-md border p-3">
            <div class="font-medium">{{ vehicle.name }}</div>
            <div class="text-sm text-muted-foreground">{{ vehicle.seats ? `${vehicle.seats} seats` : 'Seats not set' }}</div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
