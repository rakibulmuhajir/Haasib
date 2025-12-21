<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface ParentCategory {
  id: string
  name: string
  code: string
}

const props = defineProps<{
  company: CompanyRef
  parentCategories: ParentCategory[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Item Categories', href: `/${props.company.slug}/item-categories` },
  { title: 'Create', href: `/${props.company.slug}/item-categories/create` },
]

const form = useForm({
  code: '',
  name: '',
  description: '',
  parent_id: null,
  sort_order: 0,
  is_active: true,
})

const submit = () => {
  form.post(`/${props.company.slug}/item-categories`)
}
</script>

<template>
  <Head title="Create Category" />

  <PageShell
    title="Create Category"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/item-categories`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <Card>
        <CardHeader>
          <CardTitle>Category Details</CardTitle>
          <CardDescription>Create a new item category</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input
                id="code"
                v-model="form.code"
                placeholder="e.g., ELEC"
                :class="{ 'border-destructive': form.errors.code }"
              />
              <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
            </div>

            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Category name"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              v-model="form.description"
              placeholder="Category description"
              rows="3"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="parent">Parent Category</Label>
              <Select v-model="form.parent_id">
                <SelectTrigger>
                  <SelectValue placeholder="None (top level)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="cat in parentCategories" :key="cat.id" :value="cat.id">
                    {{ cat.name }} ({{ cat.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-sm text-muted-foreground">Leave empty for top-level category</p>
            </div>

            <div class="space-y-2">
              <Label for="sort_order">Sort Order</Label>
              <Input
                id="sort_order"
                v-model="form.sort_order"
                type="number"
                min="0"
              />
              <p class="text-sm text-muted-foreground">Lower numbers appear first</p>
            </div>
          </div>

          <div class="flex items-center space-x-2">
            <Switch id="is_active" v-model="form.is_active" />
            <Label for="is_active">Active</Label>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/item-categories`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Save Category' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
