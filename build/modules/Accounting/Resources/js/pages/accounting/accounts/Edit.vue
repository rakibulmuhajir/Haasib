<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Layers, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRef {
  id: string
  code: string
  name: string
  type: string
  subtype: string
  currency: string | null
  parent_id: string | null
  description: string | null
}

interface ParentOption {
  id: string
  code: string
  name: string
  type: string
}

const props = defineProps<{
  company: CompanyRef
  account: AccountRef
  parents: ParentOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Accounts', href: `/${props.company.slug}/accounts` },
  { title: props.account.code, href: `/${props.company.slug}/accounts/${props.account.id}` },
  { title: 'Edit', href: `/${props.company.slug}/accounts/${props.account.id}/edit` },
]

const form = useForm({
  name: props.account.name,
  currency: props.account.currency ?? '',
  parent_id: props.account.parent_id ?? '',
  description: props.account.description ?? '',
  is_active: true,
})

const filteredParents = computed(() => props.parents.filter((p) => p.id !== props.account.id && p.type === props.account.type))

const handleSubmit = () => {
  form.put(`/${props.company.slug}/accounts/${props.account.id}`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`Edit Account ${account.code}`" />
  <PageShell
    :title="`Edit ${account.code}`"
    :breadcrumbs="breadcrumbs"
    :icon="Layers"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label>Code</Label>
          <Input :value="account.code" disabled />
        </div>
        <div>
          <Label>Type</Label>
          <Input :value="account.type" disabled />
        </div>
        <div>
          <Label>Subtype</Label>
          <Input :value="account.subtype" disabled />
        </div>
        <div>
          <Label for="name">Name</Label>
          <Input id="name" v-model="form.name" required />
        </div>
        <div>
          <Label for="currency">Currency (optional)</Label>
          <Input
            id="currency"
            v-model="form.currency"
            placeholder="Leave blank for base"
            maxlength="3"
          />
        </div>
        <div>
          <Label for="parent_id">Parent (optional)</Label>
          <Select v-model="form.parent_id">
            <SelectTrigger id="parent_id">
              <SelectValue placeholder="No parent" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">No parent</SelectItem>
              <SelectItem
                v-for="p in filteredParents"
                :key="p.id"
                :value="p.id"
              >
                {{ p.code }} — {{ p.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      <div>
        <Label for="description">Description</Label>
        <Input id="description" v-model="form.description" placeholder="Optional description" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
