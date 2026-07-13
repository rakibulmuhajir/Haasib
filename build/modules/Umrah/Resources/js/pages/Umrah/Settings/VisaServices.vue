<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { FileText, Pencil, Save, Trash2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  visaServices: any[]
  vendors: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Services', href: `/${props.company.slug}/umrah/settings/visa-services` },
]

const form = useForm({
  name: '',
  vendor_id: 'none',
  retail_amount: '0',
  cost_amount: '0',
  child_retail_amount: '0',
  child_cost_amount: '0',
  notes: '',
})

const removeForm = useForm({})
const editingService = ref<any | null>(null)
const serviceToRemove = ref<any | null>(null)
const removeDialogOpen = ref(false)

const sameAmount = (first: string | number | null | undefined, second: string | number | null | undefined) => {
  return Number(first || 0) === Number(second || 0)
}

const resetForm = () => {
  editingService.value = null
  form.clearErrors()
  form.name = ''
  form.vendor_id = 'none'
  form.retail_amount = '0'
  form.cost_amount = '0'
  form.child_retail_amount = '0'
  form.child_cost_amount = '0'
  form.notes = ''
}

const startEdit = (service: any) => {
  editingService.value = service
  form.clearErrors()
  form.name = service.name || ''
  form.vendor_id = service.vendor_id || 'none'
  form.retail_amount = String(service.retail_amount ?? 0)
  form.cost_amount = String(service.cost_amount ?? 0)
  form.child_retail_amount = String(service.child_retail_amount ?? service.retail_amount ?? 0)
  form.child_cost_amount = String(service.child_cost_amount ?? service.cost_amount ?? 0)
  form.notes = service.notes || ''
}

const payload = (data: any) => ({
  ...data,
  vendor_id: data.vendor_id === 'none' ? null : data.vendor_id,
  retail_amount: Number(data.retail_amount || 0),
  cost_amount: Number(data.cost_amount || 0),
  child_retail_amount: Number(data.child_retail_amount || data.retail_amount || 0),
  child_cost_amount: Number(data.child_cost_amount || data.cost_amount || 0),
})

watch(() => form.retail_amount, (value, oldValue) => {
  if (sameAmount(form.child_retail_amount, oldValue)) form.child_retail_amount = value
})

watch(() => form.cost_amount, (value, oldValue) => {
  if (sameAmount(form.child_cost_amount, oldValue)) form.child_cost_amount = value
})

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(editingService.value ? 'Visa service updated successfully' : 'Visa service added successfully')
      resetForm()
    },
    onError: () => toast.error(editingService.value ? 'Failed to update visa service' : 'Failed to add visa service'),
  }

  form.transform(payload)

  if (editingService.value) {
    form.put(`/${props.company.slug}/umrah/settings/visa-services/${editingService.value.id}`, options)
    return
  }

  form.post(`/${props.company.slug}/umrah/settings/visa-services`, options)
}

const removeService = (service: any) => {
  serviceToRemove.value = service
  removeDialogOpen.value = true
}

const confirmRemoveService = () => {
  if (!serviceToRemove.value) return

  removeForm.delete(`/${props.company.slug}/umrah/settings/visa-services/${serviceToRemove.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Visa service removed successfully')
      if (editingService.value?.id === serviceToRemove.value?.id) resetForm()
      removeDialogOpen.value = false
      serviceToRemove.value = null
    },
    onError: () => toast.error('Failed to remove visa service'),
  })
}
</script>

<template>
  <Head title="Visa Services" />
  <PageShell title="Visa Services" description="Reusable visa packages with default retail and cost." :breadcrumbs="breadcrumbs" :icon="FileText">
    <div class="grid gap-6 lg:grid-cols-[520px_minmax(0,1fr)]">
      <Card>
        <CardHeader><CardTitle>{{ editingService ? 'Edit Visa Service' : 'Add Visa Service' }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Umrah visa" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Vendor</Label>
              <Select v-model="form.vendor_id">
                <SelectTrigger><SelectValue placeholder="Optional" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No default vendor</SelectItem>
                  <SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-3 rounded-md border p-3">
              <div class="font-medium">Adult</div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-2"><Label>Adult Retail</Label><Input v-model="form.retail_amount" type="number" min="0" step="0.01" /></div>
                <div class="space-y-2"><Label>Adult Cost</Label><Input v-model="form.cost_amount" type="number" min="0" step="0.01" /></div>
              </div>
            </div>
            <div class="space-y-3 rounded-md border p-3">
              <div class="font-medium">Child</div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-2"><Label>Child Retail</Label><Input v-model="form.child_retail_amount" type="number" min="0" step="0.01" /></div>
                <div class="space-y-2"><Label>Child Cost</Label><Input v-model="form.child_cost_amount" type="number" min="0" step="0.01" /></div>
              </div>
            </div>
            <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
            <div class="grid gap-2 sm:grid-cols-2">
              <Button v-if="editingService" type="button" variant="outline" @click="resetForm"><X class="mr-2 h-4 w-4" />Cancel</Button>
              <Button type="submit" :class="editingService ? '' : 'sm:col-span-2'" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />{{ editingService ? 'Save Changes' : 'Save Service' }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Available Services</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!visaServices.length" class="text-sm text-muted-foreground">No visa services yet.</div>
          <div v-for="service in visaServices" :key="service.id" class="grid gap-3 rounded-md border p-3 xl:grid-cols-[1fr_170px_170px_auto]">
            <div>
              <div class="font-medium">{{ service.name }}</div>
              <div class="text-sm text-muted-foreground">{{ service.vendor?.name || 'No default vendor' }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Adult</div>
              <div><MoneyText :amount="service.retail_amount" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">Cost <MoneyText :amount="service.cost_amount" :currency="company.base_currency" /></div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Child</div>
              <div><MoneyText :amount="service.child_retail_amount ?? service.retail_amount" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">Cost <MoneyText :amount="service.child_cost_amount ?? service.cost_amount" :currency="company.base_currency" /></div>
            </div>
            <div class="flex items-center justify-end gap-1">
              <Button type="button" variant="ghost" size="icon" @click="startEdit(service)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeService(service)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove Visa Service"
      :description="`Remove ${serviceToRemove?.name || 'this service'} from future groups? Existing groups keep their history.`"
      confirm-text="Remove Service"
      :loading="removeForm.processing"
      @confirm="confirmRemoveService"
    />
  </PageShell>
</template>
