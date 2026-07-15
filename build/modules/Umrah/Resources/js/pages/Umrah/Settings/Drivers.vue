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
import { Save, Trash2, Users } from 'lucide-vue-next'
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

const removeForm = useForm({})
const driverToRemove = ref<{ id: string; name: string } | null>(null)
const removeDialogOpen = ref(false)

const submit = () => form.post(`/${props.company.slug}/umrah/settings/drivers`, {
  preserveScroll: true,
  onSuccess: () => {
    toast.success('Driver added successfully')
    form.reset()
  },
  onError: () => toast.error('Failed to add driver'),
})

const removeDriver = (driver: { id: string; name: string }) => {
  driverToRemove.value = driver
  removeDialogOpen.value = true
}

const confirmRemoveDriver = () => {
  if (!driverToRemove.value) return

  removeForm.delete(`/${props.company.slug}/umrah/settings/drivers/${driverToRemove.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Driver removed successfully')
      removeDialogOpen.value = false
      driverToRemove.value = null
    },
    onError: () => toast.error('Failed to remove driver'),
  })
}
</script>

<template>
  <Head title="Drivers" />
  <PageShell title="Drivers" description="Drivers available for Umrah group transport." :breadcrumbs="breadcrumbs" :icon="Users">
    <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
      <Card class="min-w-0">
        <CardHeader><CardTitle>Add Driver</CardTitle></CardHeader>
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
            <Button type="submit" class="w-full" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              Save Driver
            </Button>
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
              <TableRow v-for="driver in drivers" :key="driver.id">
                <TableCell class="font-medium">{{ driver.name }}</TableCell>
                <TableCell>{{ driver.phone || '-' }}</TableCell>
                <TableCell class="max-w-72 truncate text-muted-foreground">{{ driver.notes || '-' }}</TableCell>
                <TableCell><Badge :variant="driver.is_active ? 'secondary' : 'outline'">{{ driver.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                <TableCell class="text-right"><Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeDriver(driver)"><Trash2 class="h-4 w-4" /><span class="sr-only">Remove {{ driver.name }}</span></Button></TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove Driver"
      :description="`Remove ${driverToRemove?.name || 'this driver'} from future assignments? Existing groups keep their history.`"
      confirm-text="Remove Driver"
      :loading="removeForm.processing"
      @confirm="confirmRemoveDriver"
    />
  </PageShell>
</template>
