<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Wand2, Save, X, Plus, Trash2 } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BankAccountOption {
  id: string
  account_name: string
  account_number: string
  currency: string
}

interface GlAccountOption {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

interface FieldOption {
  value: string
  label: string
}

interface OperatorOption {
  value: string
  label: string
  types: string[]
}

interface ActionTypeOption {
  value: string
  label: string
  inputType: 'text' | 'select'
}

interface RuleRef {
  id: string
  name: string
  priority: number
  bank_account_id: string | null
  conditions: Array<{ field: string; operator: string; value: string | number }>
  actions: Record<string, string>
  is_active: boolean
}

const props = defineProps<{
  company: CompanyRef
  rule: RuleRef
  bankAccounts: BankAccountOption[]
  glAccounts: GlAccountOption[]
  conditionFields: FieldOption[]
  conditionOperators: OperatorOption[]
  actionTypes: ActionTypeOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Rules', href: `/${props.company.slug}/banking/rules` },
  { title: props.rule.name, href: `/${props.company.slug}/banking/rules/${props.rule.id}` },
  { title: 'Edit', href: `/${props.company.slug}/banking/rules/${props.rule.id}/edit` },
]

const noneValue = '__none'

interface Condition {
  field: string
  operator: string
  value: string
}

interface Actions {
  set_category: string
  set_payee: string
  set_gl_account_id: string
  set_transaction_type: string
}

// Initialize conditions from rule
const conditions = ref<Condition[]>(
  props.rule.conditions?.length > 0
    ? props.rule.conditions.map(c => ({
        field: c.field,
        operator: c.operator,
        value: String(c.value),
      }))
    : [{ field: 'description', operator: 'contains', value: '' }]
)

// Initialize actions from rule
const actions = ref<Actions>({
  set_category: props.rule.actions?.set_category || '',
  set_payee: props.rule.actions?.set_payee || '',
  set_gl_account_id: props.rule.actions?.set_gl_account_id || '',
  set_transaction_type: props.rule.actions?.set_transaction_type || '',
})

const form = useForm({
  name: props.rule.name,
  bank_account_id: props.rule.bank_account_id || noneValue,
  priority: props.rule.priority,
  is_active: props.rule.is_active,
})

const transactionTypes = [
  { value: 'deposit', label: 'Deposit' },
  { value: 'withdrawal', label: 'Withdrawal' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'fee', label: 'Fee' },
  { value: 'interest', label: 'Interest' },
  { value: 'adjustment', label: 'Adjustment' },
]

const addCondition = () => {
  conditions.value.push({ field: 'description', operator: 'contains', value: '' })
}

const removeCondition = (index: number) => {
  if (conditions.value.length > 1) {
    conditions.value.splice(index, 1)
  }
}

const hasActiveAction = computed(() => {
  return Object.values(actions.value).some(v => v && v !== noneValue)
})

const handleSubmit = () => {
  // Filter out empty actions
  const filteredActions: Record<string, string> = {}
  for (const [key, value] of Object.entries(actions.value)) {
    if (value && value !== noneValue) {
      filteredActions[key] = value
    }
  }

  const payload = {
    ...form.data(),
    bank_account_id: form.bank_account_id === noneValue ? null : form.bank_account_id,
    conditions: conditions.value.filter(c => c.value),
    actions: filteredActions,
  }

  router.put(`/${props.company.slug}/banking/rules/${props.rule.id}`, payload, {
    preserveScroll: true,
  })
}

const handleCancel = () => {
  router.get(`/${props.company.slug}/banking/rules/${props.rule.id}`)
}
</script>

<template>
  <Head :title="`Edit ${rule.name}`" />
  <PageShell
    :title="`Edit ${rule.name}`"
    :breadcrumbs="breadcrumbs"
    :icon="Wand2"
  >
    <form class="space-y-6 max-w-3xl" @submit.prevent="handleSubmit">
      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Rule Details</CardTitle>
          <CardDescription>Update rule settings</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="name">Rule Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="e.g., Utilities - Electric Bill"
                required
              />
            </div>

            <div class="space-y-2">
              <Label for="priority">Priority *</Label>
              <Input
                id="priority"
                v-model.number="form.priority"
                type="number"
                min="1"
                required
              />
              <p class="text-xs text-muted-foreground">Lower numbers run first</p>
            </div>

            <div class="space-y-2">
              <Label for="bank_account_id">Bank Account (optional)</Label>
              <Select v-model="form.bank_account_id">
                <SelectTrigger id="bank_account_id">
                  <SelectValue placeholder="All accounts" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue">All accounts</SelectItem>
                  <SelectItem
                    v-for="account in bankAccounts"
                    :key="account.id"
                    :value="account.id"
                  >
                    {{ account.account_name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-muted-foreground">Leave blank to apply to all accounts</p>
            </div>

            <div class="flex items-center gap-3 pt-6">
              <Checkbox
                id="is_active"
                :checked="form.is_active"
                @update:checked="(val) => form.is_active = val"
              />
              <Label for="is_active" class="cursor-pointer">
                Rule is active
              </Label>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Conditions -->
      <Card>
        <CardHeader>
          <CardTitle>Conditions</CardTitle>
          <CardDescription>Define when this rule should match (all conditions must be true)</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div
            v-for="(condition, index) in conditions"
            :key="index"
            class="flex items-end gap-3"
          >
            <div class="flex-1 grid gap-3 md:grid-cols-3">
              <div class="space-y-2">
                <Label :for="`field-${index}`">Field</Label>
                <Select v-model="condition.field">
                  <SelectTrigger :id="`field-${index}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="field in conditionFields"
                      :key="field.value"
                      :value="field.value"
                    >
                      {{ field.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="space-y-2">
                <Label :for="`operator-${index}`">Operator</Label>
                <Select v-model="condition.operator">
                  <SelectTrigger :id="`operator-${index}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="op in conditionOperators"
                      :key="op.value"
                      :value="op.value"
                    >
                      {{ op.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="space-y-2">
                <Label :for="`value-${index}`">Value</Label>
                <Input
                  :id="`value-${index}`"
                  v-model="condition.value"
                  placeholder="e.g., ELECTRIC"
                />
              </div>
            </div>

            <Button
              type="button"
              variant="ghost"
              size="icon"
              :disabled="conditions.length === 1"
              @click="removeCondition(index)"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>

          <Button type="button" variant="outline" @click="addCondition">
            <Plus class="mr-2 h-4 w-4" />
            Add Condition
          </Button>
        </CardContent>
      </Card>

      <!-- Actions -->
      <Card>
        <CardHeader>
          <CardTitle>Actions</CardTitle>
          <CardDescription>What to do when this rule matches</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="set_category">Set Category</Label>
              <Input
                id="set_category"
                v-model="actions.set_category"
                placeholder="e.g., Utilities"
              />
            </div>

            <div class="space-y-2">
              <Label for="set_payee">Set Payee Name</Label>
              <Input
                id="set_payee"
                v-model="actions.set_payee"
                placeholder="e.g., Electric Company"
              />
            </div>

            <div class="space-y-2">
              <Label for="set_gl_account_id">Set GL Account</Label>
              <Select v-model="actions.set_gl_account_id">
                <SelectTrigger id="set_gl_account_id">
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue">None</SelectItem>
                  <SelectItem
                    v-for="gl in glAccounts"
                    :key="gl.id"
                    :value="gl.id"
                  >
                    {{ gl.code }} — {{ gl.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="set_transaction_type">Set Transaction Type</Label>
              <Select v-model="actions.set_transaction_type">
                <SelectTrigger id="set_transaction_type">
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="noneValue">None</SelectItem>
                  <SelectItem
                    v-for="type in transactionTypes"
                    :key="type.value"
                    :value="type.value"
                  >
                    {{ type.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <p v-if="!hasActiveAction" class="text-sm text-amber-600">
            At least one action is required
          </p>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="handleCancel">
          <X class="mr-2 h-4 w-4" />
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing || !hasActiveAction">
          <Save class="mr-2 h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
