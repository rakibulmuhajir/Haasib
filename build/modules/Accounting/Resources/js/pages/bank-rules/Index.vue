<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Wand2,
  PlusCircle,
  MoreHorizontal,
  Eye,
  Pencil,
  Trash2,
  Landmark
} from 'lucide-vue-next'
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

interface RuleRow {
  id: string
  name: string
  priority: number
  conditions: Array<{ field: string; operator: string; value: string | number }>
  actions: Record<string, string>
  is_active: boolean
  bank_account: BankAccountRef | null
  created_at: string
}

interface Filters {
  bank_account_id: string
  include_inactive: boolean
}

interface PaginatedData {
  data: RuleRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  rules: PaginatedData
  bankAccounts: BankAccountRef[]
  filters: Filters
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Banking', href: `/${props.company.slug}/banking/accounts` },
  { title: 'Rules', href: `/${props.company.slug}/banking/rules` },
]

const bankAccountFilter = ref(props.filters.bank_account_id || '__all')
const includeInactive = ref(props.filters.include_inactive)

const noneValue = '__all'

const handleFilter = () => {
  router.get(`/${props.company.slug}/banking/rules`, {
    bank_account_id: bankAccountFilter.value === noneValue ? '' : bankAccountFilter.value,
    include_inactive: includeInactive.value,
  }, { preserveState: true })
}

const handleCreate = () => {
  router.get(`/${props.company.slug}/banking/rules/create`)
}

const handleView = (id: string) => {
  router.get(`/${props.company.slug}/banking/rules/${id}`)
}

const handleEdit = (id: string) => {
  router.get(`/${props.company.slug}/banking/rules/${id}/edit`)
}

const handleDelete = (rule: RuleRow) => {
  if (!confirm(`Delete rule "${rule.name}"?`)) return
  router.delete(`/${props.company.slug}/banking/rules/${rule.id}`)
}

const formatConditions = (conditions: RuleRow['conditions']) => {
  if (!conditions || conditions.length === 0) return 'No conditions'
  if (conditions.length === 1) {
    const c = conditions[0]
    return `${c.field} ${c.operator} "${c.value}"`
  }
  return `${conditions.length} conditions`
}

const formatActions = (actions: RuleRow['actions']) => {
  if (!actions) return 'No actions'
  const keys = Object.keys(actions).filter(k => actions[k])
  if (keys.length === 0) return 'No actions'
  if (keys.length === 1) {
    const action = keys[0].replace('set_', '').replace('_', ' ')
    return `Set ${action}`
  }
  return `${keys.length} actions`
}
</script>

<template>
  <Head title="Bank Rules" />

  <PageShell
    title="Bank Rules"
    :breadcrumbs="breadcrumbs"
    :icon="Wand2"
  >
    <template #actions>
      <Button @click="handleCreate">
        <PlusCircle class="mr-2 h-4 w-4" />
        Create Rule
      </Button>
    </template>

    <!-- Filters -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="space-y-2 min-w-[200px]">
            <Label>Bank Account</Label>
            <Select v-model="bankAccountFilter" @update:model-value="handleFilter">
              <SelectTrigger>
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
          </div>

          <div class="flex items-center gap-2">
            <Checkbox
              id="include-inactive"
              :checked="includeInactive"
              @update:checked="(val) => { includeInactive = val; handleFilter() }"
            />
            <Label for="include-inactive" class="text-sm cursor-pointer">Show inactive</Label>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Rules Table -->
    <Card>
      <CardContent class="pt-6">
        <div v-if="rules.data.length === 0" class="text-center py-12 text-muted-foreground">
          <Wand2 class="mx-auto h-12 w-12 mb-4 opacity-50" />
          <p class="text-lg font-medium">No rules found</p>
          <p class="text-sm mb-4">Create rules to automatically categorize transactions</p>
          <Button @click="handleCreate">
            <PlusCircle class="mr-2 h-4 w-4" />
            Create Rule
          </Button>
        </div>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="w-16">Priority</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Bank Account</TableHead>
              <TableHead>Conditions</TableHead>
              <TableHead>Actions</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="rule in rules.data"
              :key="rule.id"
              class="cursor-pointer hover:bg-muted/50"
              @click="handleView(rule.id)"
            >
              <TableCell class="font-mono text-sm">{{ rule.priority }}</TableCell>
              <TableCell>
                <p class="font-medium">{{ rule.name }}</p>
              </TableCell>
              <TableCell>
                <div v-if="rule.bank_account" class="flex items-center gap-2">
                  <Landmark class="h-4 w-4 text-muted-foreground" />
                  <span>{{ rule.bank_account.account_name }}</span>
                </div>
                <Badge v-else variant="outline">All accounts</Badge>
              </TableCell>
              <TableCell class="text-sm text-muted-foreground">
                {{ formatConditions(rule.conditions) }}
              </TableCell>
              <TableCell class="text-sm text-muted-foreground">
                {{ formatActions(rule.actions) }}
              </TableCell>
              <TableCell>
                <Badge :variant="rule.is_active ? 'default' : 'secondary'">
                  {{ rule.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </TableCell>
              <TableCell class="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child @click.stop>
                    <Button variant="ghost" size="icon">
                      <MoreHorizontal class="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem @click.stop="handleView(rule.id)">
                      <Eye class="mr-2 h-4 w-4" />
                      View
                    </DropdownMenuItem>
                    <DropdownMenuItem @click.stop="handleEdit(rule.id)">
                      <Pencil class="mr-2 h-4 w-4" />
                      Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      @click.stop="handleDelete(rule)"
                      class="text-destructive"
                    >
                      <Trash2 class="mr-2 h-4 w-4" />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <!-- Pagination -->
    <div v-if="rules.last_page > 1" class="mt-6 flex justify-center gap-2">
      <Button
        variant="outline"
        :disabled="rules.current_page === 1"
        @click="router.get(`/${company.slug}/banking/rules`, { page: rules.current_page - 1, ...filters })"
      >
        Previous
      </Button>
      <span class="flex items-center px-4 text-sm text-muted-foreground">
        Page {{ rules.current_page }} of {{ rules.last_page }}
      </span>
      <Button
        variant="outline"
        :disabled="rules.current_page === rules.last_page"
        @click="router.get(`/${company.slug}/banking/rules`, { page: rules.current_page + 1, ...filters })"
      >
        Next
      </Button>
    </div>
  </PageShell>
</template>
