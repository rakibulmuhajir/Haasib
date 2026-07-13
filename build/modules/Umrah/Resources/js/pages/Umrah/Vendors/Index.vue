<script setup lang="ts">
import { ref, watch } from 'vue'
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
import { FileText, Pencil, Save, X } from 'lucide-vue-next'
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
  adult_retail_amount: '0',
  adult_cost_amount: '0',
  child_retail_amount: '0',
  child_cost_amount: '0',
  included_bus_cost_amount: '50',
  notes: '',
})

const editingVendor = ref<any | null>(null)

const sameAmount = (first: string | number | null | undefined, second: string | number | null | undefined) => Number(first || 0) === Number(second || 0)

watch(() => form.adult_retail_amount, (value, oldValue) => {
  if (sameAmount(form.child_retail_amount, oldValue)) form.child_retail_amount = value
})

watch(() => form.adult_cost_amount, (value, oldValue) => {
  if (sameAmount(form.child_cost_amount, oldValue)) form.child_cost_amount = value
})

const resetForm = () => {
  editingVendor.value = null
  form.clearErrors()
  form.vendor_number = props.nextVendorNumber
  form.name = ''
  form.vendor_type = 'government'
  form.phone = ''
  form.email = ''
  form.city = ''
  form.adult_retail_amount = '0'
  form.adult_cost_amount = '0'
  form.child_retail_amount = '0'
  form.child_cost_amount = '0'
  form.included_bus_cost_amount = '50'
  form.notes = ''
}

const startEdit = (vendor: any) => {
  editingVendor.value = vendor
  form.clearErrors()
  form.vendor_number = vendor.vendor_number || ''
  form.name = vendor.name || ''
  form.vendor_type = vendor.vendor_type || 'government'
  form.phone = vendor.phone || ''
  form.email = vendor.email || ''
  form.city = vendor.city || ''
  form.adult_retail_amount = String(vendor.adult_retail_amount ?? 0)
  form.adult_cost_amount = String(vendor.adult_cost_amount ?? 0)
  form.child_retail_amount = String(vendor.child_retail_amount ?? vendor.adult_retail_amount ?? 0)
  form.child_cost_amount = String(vendor.child_cost_amount ?? vendor.adult_cost_amount ?? 0)
  form.included_bus_cost_amount = String(vendor.included_bus_cost_amount ?? 50)
  form.notes = vendor.notes || ''
}

const payload = (data: any) => ({
  ...data,
  adult_retail_amount: Number(data.adult_retail_amount || 0),
  adult_cost_amount: Number(data.adult_cost_amount || 0),
  child_retail_amount: Number(data.child_retail_amount || data.adult_retail_amount || 0),
  child_cost_amount: Number(data.child_cost_amount || data.adult_cost_amount || 0),
  included_bus_cost_amount: Number(data.included_bus_cost_amount || 0),
})

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(editingVendor.value ? 'Visa vendor updated successfully' : 'Visa vendor created successfully')
      resetForm()
    },
    onError: () => toast.error(editingVendor.value ? 'Failed to update visa vendor' : 'Failed to create visa vendor'),
  }

  form.transform(payload)

  if (editingVendor.value) {
    form.put(`/${props.company.slug}/umrah/vendors/${editingVendor.value.id}`, options)
    return
  }

  form.post(`/${props.company.slug}/umrah/vendors`, options)
}
</script>

<template>
  <Head title="Visa Vendors" />
  <PageShell title="Visa Vendors" description="Government or visa service providers used for visa cost tracking." :breadcrumbs="breadcrumbs" :icon="FileText">
    <div class="grid gap-6 lg:grid-cols-[520px_minmax(0,1fr)]">
      <Card>
        <CardHeader><CardTitle>{{ editingVendor ? 'Edit Vendor' : 'Add Vendor' }}</CardTitle></CardHeader>
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
            <div class="space-y-3 rounded-md border p-3">
              <div class="font-medium">Adult Visa Rate</div>
              <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2"><Label>Retail</Label><Input v-model="form.adult_retail_amount" type="number" min="0" step="0.01" /></div>
                <div class="space-y-2"><Label>Cost</Label><Input v-model="form.adult_cost_amount" type="number" min="0" step="0.01" /></div>
              </div>
            </div>
            <div class="space-y-3 rounded-md border p-3">
              <div class="font-medium">Child Visa Rate</div>
              <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2"><Label>Retail</Label><Input v-model="form.child_retail_amount" type="number" min="0" step="0.01" /></div>
                <div class="space-y-2"><Label>Cost</Label><Input v-model="form.child_cost_amount" type="number" min="0" step="0.01" /></div>
              </div>
            </div>
            <div class="space-y-2 rounded-md border p-3">
              <Label>Included Standard Bus Cost per Passenger</Label>
              <Input v-model="form.included_bus_cost_amount" type="number" min="0" step="0.01" />
              <p class="text-xs text-muted-foreground">Usually SAR 50 and already included in the visa cost. It is deducted when specialized transport replaces the bus.</p>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <div class="grid gap-2 sm:grid-cols-2">
              <Button v-if="editingVendor" type="button" variant="outline" @click="resetForm"><X class="mr-2 h-4 w-4" />Cancel</Button>
              <Button type="submit" :class="editingVendor ? '' : 'sm:col-span-2'" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />{{ editingVendor ? 'Save Changes' : 'Save Vendor' }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Vendor List</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!vendors.data.length" class="text-sm text-muted-foreground">No visa vendors yet.</div>
          <div v-for="vendor in vendors.data" :key="vendor.id" class="grid gap-3 rounded-md border p-3 xl:grid-cols-[1fr_150px_150px_80px]">
            <div>
              <div class="font-medium">{{ vendor.name }}</div>
              <div class="text-sm text-muted-foreground">{{ vendor.vendor_number }} · {{ vendorTypes[vendor.vendor_type] || vendor.vendor_type }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Adult</div>
              <div><MoneyText :amount="vendor.adult_retail_amount" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">Cost <MoneyText :amount="vendor.adult_cost_amount" :currency="company.base_currency" /></div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Child</div>
              <div><MoneyText :amount="vendor.child_retail_amount" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">Cost <MoneyText :amount="vendor.child_cost_amount" :currency="company.base_currency" /></div>
            </div>
            <div class="flex items-center justify-end gap-1">
              <Button type="button" variant="ghost" size="icon" @click="startEdit(vendor)">
                <Pencil class="h-4 w-4" />
              </Button>
            </div>
            <div class="text-right xl:col-span-4">
              <div class="text-xs text-muted-foreground">Included bus cost: <MoneyText :amount="vendor.included_bus_cost_amount" :currency="company.base_currency" /> per visa passenger</div>
              <div class="font-semibold"><MoneyText :amount="vendor.balance" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">payable</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
