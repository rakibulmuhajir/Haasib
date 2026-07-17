<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Pencil, Power, RotateCcw, Save, Users, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string }
  drivers: Array<{ id: string; name: string; phone?: string | null; notes?: string | null; is_active: boolean }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Drivers', href: `/${props.company.slug}/umrah/settings/drivers` },
]

const form = useForm({
  name: '',
  phone: '',
  notes: '',
})

const statusForm = useForm({ is_active: false })
const editingDriver = ref<(typeof props.drivers)[number] | null>(null)
const driverToRemove = ref<{ id: string; name: string } | null>(null)
const removeDialogOpen = ref(false)

const resetForm = () => { editingDriver.value = null; form.reset(); form.clearErrors() }
const startEdit = (driver: (typeof props.drivers)[number]) => {
  editingDriver.value = driver
  form.name = driver.name
  form.phone = driver.phone || ''
  form.notes = driver.notes || ''
  form.clearErrors()
}
const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => { toast.success(editingDriver.value ? 'Driver updated successfully' : 'Driver added successfully'); resetForm() },
    onError: () => toast.error(editingDriver.value ? 'Failed to update driver' : 'Failed to add driver'),
  }
  if (editingDriver.value) form.put(`/${props.company.slug}/umrah/settings/drivers/${editingDriver.value.id}`, options)
  else form.post(`/${props.company.slug}/umrah/settings/drivers`, options)
}

const removeDriver = (driver: { id: string; name: string }) => {
  driverToRemove.value = driver
  removeDialogOpen.value = true
}

const confirmRemoveDriver = () => {
  if (!driverToRemove.value) return

  statusForm.is_active = false
  statusForm.patch(`/${props.company.slug}/umrah/settings/drivers/${driverToRemove.value.id}/status`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Driver deactivated successfully')
      removeDialogOpen.value = false
      driverToRemove.value = null
    },
    onError: () => toast.error(statusForm.errors.driver || 'Failed to deactivate driver'),
  })
}
const reactivateDriver = (driver: (typeof props.drivers)[number]) => {
  statusForm.is_active = true
  statusForm.patch(`/${props.company.slug}/umrah/settings/drivers/${driver.id}/status`, {
    preserveScroll: true,
    onSuccess: () => toast.success('Driver reactivated successfully'),
    onError: () => toast.error(statusForm.errors.driver || 'Failed to reactivate driver'),
  })
}
</script>

<template>
  <Head title="Drivers" />
  <PageShell title="Drivers" description="Drivers available for Umrah group transport." :breadcrumbs="breadcrumbs" :icon="Users">
    <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
      <Card class="min-w-0">
        <CardHeader><CardTitle>{{ editingDriver ? 'Edit Driver' : 'Add Driver' }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Driver name" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Phone</Label>
              <Input v-model="form.phone" placeholder="+966..." />
              <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
            </div>
            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
            </div>
            <div class="grid gap-2" :class="editingDriver ? 'grid-cols-2' : ''"><Button v-if="editingDriver" type="button" variant="outline" @click="resetForm"><X class="mr-2 size-4" />Cancel</Button><Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />{{ editingDriver ? 'Save Changes' : 'Save Driver' }}</Button></div>
          </form>
        </CardContent>
      </Card>

      <Card class="min-w-0">
        <CardHeader><CardTitle>Available Drivers</CardTitle></CardHeader>
        <CardContent class="p-0">
          <Table>
            <TableHeader><TableRow><TableHead>Driver</TableHead><TableHead>Phone</TableHead><TableHead>Notes</TableHead><TableHead>Status</TableHead><TableHead class="w-16 text-right">Action</TableHead></TableRow></TableHeader>
            <TableBody>
              <TableEmpty v-if="!drivers.length" :colspan="5">No drivers yet.</TableEmpty>
              <TableRow v-for="driver in drivers" :key="driver.id" :class="{ 'opacity-60': !driver.is_active }">
                <TableCell class="font-medium">{{ driver.name }}</TableCell>
                <TableCell>{{ driver.phone || '-' }}</TableCell>
                <TableCell class="max-w-72 truncate text-muted-foreground">{{ driver.notes || '-' }}</TableCell>
                <TableCell><Badge :variant="driver.is_active ? 'secondary' : 'outline'">{{ driver.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                <TableCell><div class="flex justify-end gap-1"><Button type="button" variant="ghost" size="icon" title="Edit driver" @click="startEdit(driver)"><Pencil class="size-4" /></Button><Button v-if="driver.is_active" type="button" variant="ghost" size="icon" title="Deactivate driver" :disabled="statusForm.processing" @click="removeDriver(driver)"><Power class="size-4" /></Button><Button v-else type="button" variant="ghost" size="icon" title="Reactivate driver" :disabled="statusForm.processing" @click="reactivateDriver(driver)"><RotateCcw class="size-4" /></Button></div></TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Deactivate Driver"
      :description="`Deactivate ${driverToRemove?.name || 'this driver'} for future assignments? Existing groups keep their history.`"
      confirm-text="Deactivate Driver"
      :loading="statusForm.processing"
      @confirm="confirmRemoveDriver"
    />
  </PageShell>
</template>
