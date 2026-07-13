<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Save, Store } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string }
  nextVendorNumber: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Hotels', href: `/${props.company.slug}/umrah/settings/hotels` },
  { title: 'Add Vendor', href: `/${props.company.slug}/umrah/settings/hotel-vendors/create` },
]
const form = useForm({ vendor_number: props.nextVendorNumber, name: '', phone: '', email: '', city: '', logo_url: '', notes: '' })
const submit = () => form.post(`/${props.company.slug}/umrah/settings/hotel-vendors`, {
  onSuccess: () => toast.success('Hotel vendor added successfully'),
  onError: () => toast.error('Failed to add hotel vendor'),
})
</script>

<template>
  <Head title="Add Hotel Vendor" />
  <PageShell title="Add Hotel Vendor" description="Add the supplier used for hotel costs and payables." :breadcrumbs="breadcrumbs" :icon="Store">
    <Card class="mx-auto max-w-2xl">
      <CardHeader><CardTitle>Vendor Details</CardTitle></CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2"><Label>Vendor #</Label><Input v-model="form.vendor_number" /><p v-if="form.errors.vendor_number" class="text-xs text-destructive">{{ form.errors.vendor_number }}</p></div>
            <div class="space-y-2"><Label>Name</Label><Input v-model="form.name" required /><p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p></div>
            <div class="space-y-2"><Label>Phone</Label><Input v-model="form.phone" /><p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p></div>
            <div class="space-y-2"><Label>Email</Label><Input v-model="form.email" type="email" /><p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p></div>
            <div class="space-y-2 md:col-span-2"><Label>City</Label><Input v-model="form.city" /><p v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</p></div>
            <div class="space-y-2 md:col-span-2">
              <Label>Logo URL</Label>
              <div class="flex items-center gap-3">
                <img v-if="form.logo_url" :src="form.logo_url" alt="Hotel vendor logo preview" class="h-12 w-12 rounded-md border object-contain" />
                <Input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" />
              </div>
              <p v-if="form.errors.logo_url" class="text-xs text-destructive">{{ form.errors.logo_url }}</p>
            </div>
            <div class="space-y-2 md:col-span-2"><Label>Notes</Label><Textarea v-model="form.notes" /><p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p></div>
          </div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" @click="router.get(`/${company.slug}/umrah/settings/hotels`)">Cancel</Button>
            <Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Vendor</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>
