<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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

interface AccountTemplateOption {
  id: string
  code: string
  name: string
  type: string
  subtype: string
  normal_balance: string
  is_contra: boolean
  description?: string | null
}

const props = defineProps<{
  company: CompanyRef
  parents: ParentOption[]
  templates: AccountTemplateOption[]
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

const normalBalanceMap: Record<string, string> = {
  asset: 'debit',
  expense: 'debit',
  cogs: 'debit',
  other_expense: 'debit',
  liability: 'credit',
  equity: 'credit',
  revenue: 'credit',
  other_income: 'credit',
}

const noneParentValue = '__none'

const form = useForm({
  template_id: '',
  code: '',
  name: '',
  type: '',
  subtype: '',
  currency: '',
  parent_id: noneParentValue,
  description: '',
  normal_balance: '',
})

const availableSubtypes = computed(() => subtypeMap[form.type] || [])
const filteredParents = computed(() => props.parents.filter((p) => p.type === form.type))

watch(
  () => form.type,
  (newType) => {
    form.normal_balance = normalBalanceMap[newType] ?? ''
    if (!subtypeMap[newType]?.includes(form.subtype)) {
      form.subtype = ''
    }
  }
)

watch(
  () => form.template_id,
  (templateId) => {
    const template = props.templates.find((t) => t.id === templateId)
    if (!template) {
      return
    }

    form.code = template.code
    form.name = template.name
    form.type = template.type
    form.subtype = template.subtype
    form.normal_balance = template.normal_balance
  }
)

const handleSubmit = () => {
  form
    .transform((data) => ({
      ...data,
      parent_id: data.parent_id === noneParentValue ? null : data.parent_id,
    }))
    .post(`/${props.company.slug}/accounts`, {
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
          <Label for="template_id">Start from template (optional)</Label>
          <Select v-model="form.template_id">
            <SelectTrigger id="template_id">
              <SelectValue placeholder="Pick a template" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">No template</SelectItem>
              <SelectItem
                v-for="t in templates"
                :key="t.id"
                :value="t.id"
              >
                {{ t.code }} — {{ t.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
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
            <SelectItem :value="noneParentValue">No parent</SelectItem>
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
