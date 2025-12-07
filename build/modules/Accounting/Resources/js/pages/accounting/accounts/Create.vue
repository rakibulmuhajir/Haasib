<script setup lang="ts">
import { computed, ref } from 'vue'
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

interface ParentOption {
  id: string
  code: string
  name: string
  type: string
}

const props = defineProps<{
  company: CompanyRef
  parents: ParentOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Accounts', href: `/${props.company.slug}/accounts` },
  { title: 'Create', href: `/${props.company.slug}/accounts/create` },
]

const typeOptions = [
  'asset',
  'liability',
  'equity',
  'revenue',
  'expense',
  'cogs',
  'other_income',
  'other_expense',
]

const subtypeMap: Record<string, string[]> = {
  asset: ['bank', 'cash', 'accounts_receivable', 'other_current_asset', 'inventory', 'fixed_asset', 'other_asset'],
  liability: ['accounts_payable', 'credit_card', 'other_current_liability', 'other_liability', 'loan_payable'],
  equity: ['equity', 'retained_earnings'],
  revenue: ['revenue'],
  expense: ['expense'],
  cogs: ['cogs'],
  other_income: ['other_income'],
  other_expense: ['other_expense'],
}

const form = useForm({
  code: '',
  name: '',
  type: '',
  subtype: '',
  currency: '',
  parent_id: '',
  description: '',
})

const availableSubtypes = computed(() => subtypeMap[form.type] || [])
const filteredParents = computed(() => props.parents.filter((p) => p.type === form.type))

const handleSubmit = () => {
  form.post(`/${props.company.slug}/accounts`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Create Account" />
  <PageShell
    title="Create Account"
    :breadcrumbs="breadcrumbs"
    :icon="Layers"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="code">Code</Label>
          <Input id="code" v-model="form.code" required />
        </div>
        <div>
          <Label for="name">Name</Label>
          <Input id="name" v-model="form.name" required />
        </div>
        <div>
          <Label for="type">Type</Label>
          <Select v-model="form.type">
            <SelectTrigger id="type">
              <SelectValue placeholder="Select type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="t in typeOptions" :key="t" :value="t">{{ t }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="subtype">Subtype</Label>
          <Select v-model="form.subtype">
            <SelectTrigger id="subtype">
              <SelectValue placeholder="Select subtype" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="s in availableSubtypes" :key="s" :value="s">{{ s }}</SelectItem>
            </SelectContent>
          </Select>
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
        <div>
          <Label for="currency">Currency (optional)</Label>
          <Input
            id="currency"
            v-model="form.currency"
            placeholder="Leave blank for base"
            maxlength="3"
          />
        </div>
      </div>
      <div>
        <Label for="description">Description</Label>
        <Input id="description" v-model="form.description" placeholder="Optional description" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Account
        </Button>
      </div>
    </form>
  </PageShell>
</template>
