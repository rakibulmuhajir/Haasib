<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Wand2, Pencil, Trash2, Landmark, ArrowRight } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankAccountRef {
  id: string
  account_name: string
  account_number: string
}

interface UserRef {
  id: string
  name: string
}

interface FieldOption {
  value: string
  label: string
}

interface OperatorOption {
  value: string
  label: string
}

interface ActionTypeOption {
  value: string
  label: string
}

interface RuleRef {
  id: string
  name: string
  priority: number
  conditions: Array<{ field: string; operator: string; value: string | number }>
  actions: Record<string, string>
  is_active: boolean
  bank_account: BankAccountRef | null
  created_by_user: UserRef | null
  created_at: string
  updated_at: string
}

interface GlAccountRef {
  id: string
  code: string
  name: string
}

const props = defineProps<{
  company: CompanyRef
  rule: RuleRef
  glAccounts: Record<string, GlAccountRef>
  conditionFields: FieldOption[]
  conditionOperators: OperatorOption[]
  actionTypes: ActionTypeOption[]
  canEdit: boolean
  canDelete: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Rules', href: `/${props.company.slug}/banking/rules` },
  { title: props.rule.name, href: `/${props.company.slug}/banking/rules/${props.rule.id}` },
]

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const getFieldLabel = (value: string) => {
  return props.conditionFields.find(f => f.value === value)?.label || value
}

const getOperatorLabel = (value: string) => {
  return props.conditionOperators.find(o => o.value === value)?.label || value
}

const getActionLabel = (key: string) => {
  return props.actionTypes.find(a => a.value === key)?.label || key.replace('set_', '').replace('_', ' ')
}

const formatActionValue = (key: string, value: string) => {
  if (key === 'set_gl_account_id' && props.glAccounts[value]) {
    const gl = props.glAccounts[value]
    return `${gl.code} — ${gl.name}`
  }
  if (key === 'set_transaction_type') {
    return value.charAt(0).toUpperCase() + value.slice(1)
  }
  return value
}

const handleEdit = () => {
  router.get(`/${props.company.slug}/banking/rules/${props.rule.id}/edit`)
}

const handleDelete = () => {
  if (!confirm(`Delete rule "${props.rule.name}"?`)) return
  router.delete(`/${props.company.slug}/banking/rules/${props.rule.id}`)
}
</script>

<template>
  <Head :title="rule.name" />
  <PageShell
    :title="rule.name"
    :breadcrumbs="breadcrumbs"
    :icon="Wand2"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button v-if="canEdit" variant="outline" @click="handleEdit">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button v-if="canDelete" variant="destructive" @click="handleDelete">
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Conditions -->
        <Card>
          <CardHeader>
            <CardTitle>Conditions</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="!rule.conditions || rule.conditions.length === 0" class="text-muted-foreground">
              No conditions defined
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(condition, index) in rule.conditions"
                :key="index"
                class="flex items-center gap-3 p-3 rounded-lg bg-muted/50"
              >
                <Badge variant="outline">{{ index + 1 }}</Badge>
                <span class="font-medium">{{ getFieldLabel(condition.field) }}</span>
                <span class="text-muted-foreground">{{ getOperatorLabel(condition.operator) }}</span>
                <span class="font-mono bg-background px-2 py-1 rounded">"{{ condition.value }}"</span>
              </div>
              <p class="text-xs text-muted-foreground mt-2">
                All conditions must match for the rule to apply
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="!rule.actions || Object.keys(rule.actions).length === 0" class="text-muted-foreground">
              No actions defined
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(value, key) in rule.actions"
                :key="key"
                class="flex items-center gap-3 p-3 rounded-lg bg-muted/50"
              >
                <span class="font-medium">{{ getActionLabel(key as string) }}</span>
                <ArrowRight class="h-4 w-4 text-muted-foreground" />
                <span class="font-mono bg-background px-2 py-1 rounded">
                  {{ formatActionValue(key as string, value) }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Details -->
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <p class="text-sm text-muted-foreground">Status</p>
              <Badge :variant="rule.is_active ? 'default' : 'secondary'" class="mt-1">
                {{ rule.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>

            <div>
              <p class="text-sm text-muted-foreground">Priority</p>
              <p class="font-medium font-mono">{{ rule.priority }}</p>
            </div>

            <div>
              <p class="text-sm text-muted-foreground">Bank Account</p>
              <div v-if="rule.bank_account" class="flex items-center gap-2 mt-1">
                <Landmark class="h-4 w-4 text-muted-foreground" />
                <span>{{ rule.bank_account.account_name }}</span>
              </div>
              <Badge v-else variant="outline" class="mt-1">All accounts</Badge>
            </div>

            <div class="pt-4 border-t">
              <p class="text-sm text-muted-foreground">Created</p>
              <p class="font-medium">{{ formatDate(rule.created_at) }}</p>
              <p v-if="rule.created_by_user" class="text-xs text-muted-foreground">
                by {{ rule.created_by_user.name }}
              </p>
            </div>

            <div>
              <p class="text-sm text-muted-foreground">Updated</p>
              <p class="font-medium">{{ formatDate(rule.updated_at) }}</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
