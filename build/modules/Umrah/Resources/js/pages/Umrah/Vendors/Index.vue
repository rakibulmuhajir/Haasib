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
import { FileText, Save } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  vendors: { data: any[] }
  vendorTypes: Record<string, string>
  nextVendorNumber: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Vendors', href: `/${props.company.slug}/umrah/vendors` },
]

const form = useForm({
  vendor_number: props.nextVendorNumber,
  name: '',
  vendor_type: 'government',
  phone: '',
  email: '',
  city: '',
  notes: '',
})

const submit = () => form.post(`/${props.company.slug}/umrah/vendors`, {
  preserveScroll: true,
  onSuccess: () => {
    toast.success('Visa vendor created successfully')
    form.reset('name', 'phone', 'email', 'city', 'notes')
  },
  onError: () => toast.error('Failed to create visa vendor'),
})
</script>

<template>
  <Head title="Visa Vendors" />
  <PageShell title="Visa Vendors" description="Government or visa service providers used for visa cost tracking." :breadcrumbs="breadcrumbs" :icon="FileText">
    <div class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">
      <Card>
        <CardHeader><CardTitle>Add Vendor</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Vendor #</Label>
              <Input v-model="form.vendor_number" />
            </div>
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Type</Label>
              <Select v-model="form.vendor_type">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="(label, value) in vendorTypes" :key="value" :value="value">{{ label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <div class="space-y-2"><Label>Phone</Label><Input v-model="form.phone" /></div>
              <div class="space-y-2"><Label>City</Label><Input v-model="form.city" /></div>
            </div>
            <div class="space-y-2"><Label>Email</Label><Input v-model="form.email" type="email" /></div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <Button type="submit" class="w-full" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Vendor</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Vendor List</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!vendors.data.length" class="text-sm text-muted-foreground">No visa vendors yet.</div>
          <div v-for="vendor in vendors.data" :key="vendor.id" class="flex items-center justify-between rounded-md border p-3">
            <div>
              <div class="font-medium">{{ vendor.name }}</div>
              <div class="text-sm text-muted-foreground">{{ vendor.vendor_number }} · {{ vendorTypes[vendor.vendor_type] || vendor.vendor_type }}</div>
            </div>
            <div class="text-right">
              <div class="font-semibold"><MoneyText :amount="vendor.balance" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">payable</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
