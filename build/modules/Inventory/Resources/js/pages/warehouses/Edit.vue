<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Warehouse {
  id: string
  code: string
  name: string
  address: string | null
  city: string | null
  state: string | null
  postal_code: string | null
  country_code: string | null
  is_primary: boolean
  is_active: boolean
  notes: string | null
}

const props = defineProps<{
  company: CompanyRef
  warehouse: Warehouse
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Warehouses', href: `/${props.company.slug}/warehouses` },
  { title: props.warehouse.name, href: `/${props.company.slug}/warehouses/${props.warehouse.id}` },
  { title: 'Edit', href: `/${props.company.slug}/warehouses/${props.warehouse.id}/edit` },
]

const form = useForm({
  code: props.warehouse.code,
  name: props.warehouse.name,
  address: props.warehouse.address ?? '',
  city: props.warehouse.city ?? '',
  state: props.warehouse.state ?? '',
  postal_code: props.warehouse.postal_code ?? '',
  country_code: props.warehouse.country_code ?? '',
  is_primary: props.warehouse.is_primary,
  is_active: props.warehouse.is_active,
  notes: props.warehouse.notes ?? '',
})

const submit = () => {
  form.put(`/${props.company.slug}/warehouses/${props.warehouse.id}`)
}
</script>

<template>
  <Head :title="`Edit ${warehouse.name}`" />

  <PageShell
    :title="`Edit ${warehouse.name}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/warehouses/${warehouse.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
          <CardDescription>Warehouse identification</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input
                id="code"
                v-model="form.code"
                placeholder="e.g., WH-MAIN"
                :class="{ 'border-destructive': form.errors.code }"
              />
              <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
            </div>

            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Warehouse name"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Address -->
      <Card>
        <CardHeader>
          <CardTitle>Address</CardTitle>
          <CardDescription>Warehouse location</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="address">Street Address</Label>
            <Textarea
              id="address"
              v-model="form.address"
              placeholder="Street address"
              rows="2"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="city">City</Label>
              <Input id="city" v-model="form.city" placeholder="City" />
            </div>

            <div class="space-y-2">
              <Label for="state">State / Province</Label>
              <Input id="state" v-model="form.state" placeholder="State" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="postal_code">Postal Code</Label>
              <Input id="postal_code" v-model="form.postal_code" placeholder="Postal code" />
            </div>

            <div class="space-y-2">
              <Label for="country_code">Country Code</Label>
              <Input id="country_code" v-model="form.country_code" placeholder="e.g., SA, US" maxlength="2" />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Settings</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center space-x-2">
            <Switch id="is_primary" v-model:checked="form.is_primary" />
            <Label for="is_primary">Set as primary warehouse</Label>
          </div>
          <p class="text-sm text-muted-foreground">Primary warehouse is used as default for new stock movements</p>

          <div class="flex items-center space-x-2">
            <Switch id="is_active" v-model:checked="form.is_active" />
            <Label for="is_active">Active</Label>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              placeholder="Additional notes"
              rows="3"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/warehouses/${warehouse.id}`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Update Warehouse' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
