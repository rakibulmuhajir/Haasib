<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Building2,
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  MoreVertical,
  Trash2,
  UserCog,
  CheckCircle2,
  XCircle,
  Receipt,
  AlertTriangle,
  Wallet,
  Ban,
  BarChart3,
  Settings,
  Globe,
  Languages,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  created_at: string
  industry?: string
  country?: string
  language?: string
  locale?: string
  fiscal_year_start_month?: number
}

interface Stats {
  total_users: number
  active_users: number
  admins: number
}

interface Financials {
  ar_outstanding: number
  ar_outstanding_count: number
  ar_overdue: number
  ar_overdue_count: number
  payments_mtd: number
  expenses_mtd_placeholder: string
  aging: {
    current: number
    bucket_1_30: number
    bucket_31_60: number
    bucket_61_90: number
    bucket_90_plus: number
  }
  quick_stats: {
    invoices_sent_this_month: number
    payments_received_this_month: number
    new_customers_this_month: number
  }
  recent_activity: Array<{
    type: string
    label: string
    amount?: number
    currency?: string
    status?: string
    occurred_at: string
  }>
}

interface PendingInvitation {
  id: string
  email: string
  role: string
  expires_at: string
  created_at: string
  inviter_name: string | null
  inviter_email: string | null
  token: string
}

interface User {
  id: string
  name: string | null
  email: string
  role: string
  is_active: boolean
  joined_at: string | null
}

const props = defineProps<{
  company: Company
  stats: Stats
  users: User[]
  currentUserRole: string
  pendingInvitations: PendingInvitation[]
  financials: Financials
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name },
])

// Tab state
const activeTab = ref('overview')

// Setup inline editing
const inlineEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/settings`,
  successMessage: 'Setting updated successfully',
  errorMessage: 'Failed to update setting',
})

// Register editable fields
const nameField = inlineEdit.registerField('name', props.company.name)
const languageField = inlineEdit.registerField('language', props.company.language || 'en')
const localeField = inlineEdit.registerField('locale', props.company.locale || 'en_US')
const fiscalYearField = inlineEdit.registerField('fiscal_year_start_month', props.company.fiscal_year_start_month || 1)

// Invite dialog
const inviteDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)

const inviteForm = useForm({
  email: '',
  role: 'member',
})

const roleForm = useForm({
  userId: '',
  role: '',
})

const removeForm = useForm({})

const canManage = computed(() => ['owner', 'admin'].includes(props.currentUserRole))

const availableRoles = ['owner', 'admin', 'accountant', 'viewer', 'member']

const languageOptions = [
  { value: 'en', label: 'English' },
  { value: 'ar', label: 'Arabic' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'es', label: 'Spanish' },
]

const localeOptions = [
  { value: 'en_US', label: 'English (US)' },
  { value: 'en_GB', label: 'English (UK)' },
  { value: 'ar_SA', label: 'Arabic (Saudi Arabia)' },
  { value: 'ar_AE', label: 'Arabic (UAE)' },
  { value: 'fr_FR', label: 'French (France)' },
  { value: 'de_DE', label: 'German (Germany)' },
  { value: 'es_ES', label: 'Spanish (Spain)' },
]

const monthOptions = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    admin: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleInvite = () => {
  inviteForm.post(`/${props.company.slug}/users/invite`, {
    onSuccess: () => {
      inviteForm.reset()
      inviteDialogOpen.value = false
      toast.success('Invitation sent successfully')
    },
    onError: () => {
      toast.error('Failed to send invitation')
    },
  })
}

const openRoleDialog = (user: User) => {
  selectedUser.value = user
  roleForm.userId = user.id
  roleForm.role = user.role
  roleDialogOpen.value = true
}

const handleRoleUpdate = () => {
  roleForm.put(`/${props.company.slug}/users/${roleForm.userId}/role`, {
    onSuccess: () => {
      roleDialogOpen.value = false
      selectedUser.value = null
      toast.success('Role updated successfully')
    },
    onError: () => {
      toast.error('Failed to update role')
    },
  })
}

const openRemoveDialog = (user: User) => {
  selectedUser.value = user
  removeDialogOpen.value = true
}

const handleRemoveUser = () => {
  if (!selectedUser.value) return

  removeForm.delete(`/${props.company.slug}/users/${selectedUser.value.id}`, {
    onSuccess: () => {
      removeDialogOpen.value = false
      selectedUser.value = null
      toast.success('User removed successfully')
    },
    onError: () => {
      toast.error('Failed to remove user')
    },
  })
}

const tableColumns = [
  { key: 'name', label: 'User', sortable: true },
  { key: 'role', label: 'Role', sortable: true },
  { key: 'is_active', label: 'Status', sortable: true },
  { key: 'joined_at', label: 'Joined', sortable: true },
  { key: 'actions', label: '', class: 'text-right' },
]

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.company.base_currency || 'USD',
  }).format(amount)
}
</script>

<template>
  <Head :title="company.name" />
  <PageShell
    :title="company.name"
    :icon="Building2"
    :breadcrumbs="breadcrumbs"
    :badge="{ text: company.is_active ? 'Active' : 'Inactive', variant: company.is_active ? 'default' : 'secondary' }"
    compact
  >
    <template #description>
      <span class="font-mono text-zinc-400">{{ company.slug }}</span>
      <span class="mx-2 text-zinc-300">•</span>
      <span>{{ company.base_currency }}</span>
    </template>

    <Tabs v-model="activeTab" class="w-full">
      <TabsList class="mb-6 bg-zinc-100">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Overview
        </TabsTrigger>
        <TabsTrigger value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger value="users" class="gap-2">
          <Users class="h-4 w-4" />
          Users
        </TabsTrigger>
      </TabsList>

      <!-- Overview Tab -->
      <TabsContent value="overview" class="space-y-6">
        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-3">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Total Users</CardTitle>
              <Users class="h-4 w-4 text-zinc-400" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ stats.total_users }}</div>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Active Users</CardTitle>
              <CheckCircle2 class="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ stats.active_users }}</div>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Administrators</CardTitle>
              <Shield class="h-4 w-4 text-teal-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ stats.admins }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Financial Snapshot -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">AR Outstanding</CardTitle>
              <Receipt class="h-4 w-4 text-zinc-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ formatMoney(financials.ar_outstanding) }}</div>
              <p class="text-xs text-zinc-500 mt-1">{{ financials.ar_outstanding_count }} open invoice{{ financials.ar_outstanding_count === 1 ? '' : 's' }}</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">AR Overdue</CardTitle>
              <AlertTriangle class="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ formatMoney(financials.ar_overdue) }}</div>
              <p class="text-xs text-zinc-500 mt-1">{{ financials.ar_overdue_count }} overdue</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Payments MTD</CardTitle>
              <Wallet class="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ formatMoney(financials.payments_mtd) }}</div>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Expenses MTD</CardTitle>
              <Ban class="h-4 w-4 text-zinc-400" />
            </CardHeader>
            <CardContent>
              <div class="text-xl font-semibold text-zinc-500">Placeholder</div>
              <p class="text-xs text-zinc-500 mt-1">{{ financials.expenses_mtd_placeholder }}</p>
            </CardContent>
          </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-sm font-medium text-zinc-500">AR Aging</CardTitle>
              <CardDescription>Aging by due date</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-zinc-700">
              <div class="flex justify-between"><span>Current</span><span class="font-medium text-zinc-900">{{ formatMoney(financials.aging.current) }}</span></div>
              <div class="flex justify-between"><span>1-30 days</span><span class="font-medium text-zinc-900">{{ formatMoney(financials.aging.bucket_1_30) }}</span></div>
              <div class="flex justify-between"><span>31-60 days</span><span class="font-medium text-zinc-900">{{ formatMoney(financials.aging.bucket_31_60) }}</span></div>
              <div class="flex justify-between"><span>61-90 days</span><span class="font-medium text-zinc-900">{{ formatMoney(financials.aging.bucket_61_90) }}</span></div>
              <div class="flex justify-between"><span>90+ days</span><span class="font-medium text-zinc-900">{{ formatMoney(financials.aging.bucket_90_plus) }}</span></div>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-sm font-medium text-zinc-500">Quick Stats</CardTitle>
              <CardDescription>This month</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-zinc-700">
              <div class="flex justify-between">
                <span>Invoices sent</span>
                <span class="font-medium text-zinc-900">{{ financials.quick_stats.invoices_sent_this_month }}</span>
              </div>
              <div class="flex justify-between">
                <span>Payments received</span>
                <span class="font-medium text-zinc-900">{{ financials.quick_stats.payments_received_this_month }}</span>
              </div>
              <div class="flex justify-between">
                <span>New customers</span>
                <span class="font-medium text-zinc-900">{{ financials.quick_stats.new_customers_this_month }}</span>
              </div>
              <div class="pt-2 flex flex-wrap gap-2">
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/invoices/create`)">Create Invoice</Button>
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/payments/create`)">Record Payment</Button>
                <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/customers/create`)">Add Customer</Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-500">Recent Activity</CardTitle>
            <CardDescription>Last few accounting events</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm text-zinc-700">
            <div v-for="(item, idx) in financials.recent_activity" :key="idx" class="flex justify-between border-b border-zinc-200 pb-2 last:border-b-0 last:pb-0">
              <div>
                <p class="font-medium text-zinc-900">{{ item.label }}</p>
                <p class="text-xs text-zinc-500">{{ new Date(item.occurred_at).toLocaleDateString() }}</p>
              </div>
              <div v-if="item.amount" class="font-mono text-sm text-zinc-800">
                {{ formatMoney(item.amount) }}
              </div>
            </div>
            <div v-if="financials.recent_activity.length === 0" class="text-sm text-zinc-500">No recent activity.</div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent value="settings" class="space-y-6">
        <!-- Editable Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Company Settings</CardTitle>
            <CardDescription class="text-zinc-500">
              {{ canManage ? 'Click on the pencil icon to edit a setting' : 'Contact an owner or admin to make changes' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Company Name (Editable) -->
              <InlineEditable
                v-model="nameField.value.value"
                label="Company Name"
                :editing="nameField.isEditing.value"
                :saving="nameField.isSaving.value"
                :can-edit="canManage"
                type="text"
                @start-edit="nameField.startEditing()"
                @save="nameField.save()"
                @cancel="nameField.cancelEditing()"
              />

              <!-- Slug (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Slug</Label>
                <div class="font-mono text-base text-zinc-900">{{ company.slug }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Base Currency (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Base Currency</Label>
                <div class="font-mono text-base text-zinc-900">{{ company.base_currency }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed after creation</p>
              </div>

              <!-- Country (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Country</Label>
                <div class="text-base text-zinc-900">{{ company.country || '—' }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Industry (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Industry</Label>
                <div class="text-base text-zinc-900 capitalize">{{ company.industry || '—' }}</div>
              </div>

              <!-- Created Date (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Created</Label>
                <div class="flex items-center gap-1.5 text-base text-zinc-900">
                  <Calendar class="h-3.5 w-3.5 text-zinc-400" />
                  {{ new Date(company.created_at).toLocaleDateString() }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Regional Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900 flex items-center gap-2">
              <Globe class="h-4 w-4" />
              Regional Settings
            </CardTitle>
            <CardDescription class="text-zinc-500">
              Language, locale, and fiscal year preferences
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Language (Editable) -->
              <InlineEditable
                v-model="languageField.value.value"
                label="Language"
                :editing="languageField.isEditing.value"
                :saving="languageField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="languageOptions"
                :icon="Languages"
                @start-edit="languageField.startEditing()"
                @save="languageField.save()"
                @cancel="languageField.cancelEditing()"
              />

              <!-- Locale (Editable) -->
              <InlineEditable
                v-model="localeField.value.value"
                label="Locale"
                :editing="localeField.isEditing.value"
                :saving="localeField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="localeOptions"
                @start-edit="localeField.startEditing()"
                @save="localeField.save()"
                @cancel="localeField.cancelEditing()"
              />

              <!-- Fiscal Year Start Month (Editable) -->
              <InlineEditable
                v-model="fiscalYearField.value.value"
                label="Fiscal Year Start"
                :editing="fiscalYearField.isEditing.value"
                :saving="fiscalYearField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="monthOptions"
                :icon="Calendar"
                helper-text="Month when your fiscal year begins"
                @start-edit="fiscalYearField.startEditing()"
                @save="fiscalYearField.save()"
                @cancel="fiscalYearField.cancelEditing()"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Pending Invitations (visible to owners) -->
        <Card v-if="currentUserRole === 'owner' && pendingInvitations.length > 0" class="border-amber-100 bg-amber-50">
          <CardHeader class="pb-3">
            <CardTitle class="text-amber-900">Pending Invitations</CardTitle>
            <CardDescription class="text-amber-700">
              Invites sent from {{ company.name }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div
                v-for="invite in pendingInvitations"
                :key="invite.id"
                class="flex flex-col gap-1 rounded-lg border border-amber-200/80 bg-white px-4 py-3 shadow-xs"
              >
                <div class="flex items-center justify-between">
                  <div class="font-medium text-amber-900">{{ invite.email }}</div>
                  <Badge variant="outline" class="capitalize text-amber-800">
                    {{ invite.role }}
                  </Badge>
                </div>
                <div class="text-xs text-amber-700">
                  Expires {{ new Date(invite.expires_at).toLocaleDateString() }}
                  <span v-if="invite.inviter_name"> • Invited by {{ invite.inviter_name }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Users Tab -->
      <TabsContent value="users" class="space-y-6">
        <DataTable
          :data="users"
          :columns="tableColumns"
          title="Team Members"
          :description="`${users.length} ${users.length === 1 ? 'member' : 'members'} in this company`"
          key-field="id"
          hoverable
        >
          <template #header>
            <Button v-if="canManage" size="sm" @click="inviteDialogOpen = true">
              <UserPlus class="mr-2 h-4 w-4" />
              Invite User
            </Button>
          </template>

          <template #cell-name="{ row }">
            <div class="flex flex-col">
              <span class="font-medium text-zinc-900">{{ row.name || 'Unknown' }}</span>
              <div class="flex items-center gap-1 text-zinc-500">
                <Mail class="h-3 w-3" />
                <span class="text-xs">{{ row.email }}</span>
              </div>
            </div>
          </template>

          <template #cell-role="{ row }">
            <Badge :variant="getRoleBadgeVariant(row.role)" class="capitalize">
              <Shield class="mr-1 h-3 w-3" />
              {{ row.role }}
            </Badge>
          </template>

          <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'default' : 'secondary'">
              <component :is="row.is_active ? CheckCircle2 : XCircle" class="mr-1 h-3 w-3" />
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-joined_at="{ row }">
            <div v-if="row.joined_at" class="flex items-center gap-1 text-zinc-700">
              <Calendar class="h-3 w-3 text-zinc-400" />
              <span>{{ new Date(row.joined_at).toLocaleDateString() }}</span>
            </div>
            <span v-else class="text-zinc-400">—</span>
          </template>

          <template #cell-actions="{ row }">
            <div class="flex justify-end">
              <DropdownMenu v-if="canManage">
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="sm">
                    <MoreVertical class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click="openRoleDialog(row)">
                    <UserCog class="mr-2 h-4 w-4" />
                    Change Role
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="openRemoveDialog(row)" class="text-red-600 focus:text-red-600">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Remove User
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </template>
        </DataTable>
      </TabsContent>
    </Tabs>

    <!-- Invite User Dialog -->
    <Dialog v-model:open="inviteDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Invite User</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Send an invitation to join {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="email" class="text-zinc-700">Email</Label>
            <Input
              id="email"
              v-model="inviteForm.email"
              type="email"
              placeholder="user@example.com"
              class="border-zinc-200"
            />
            <p v-if="inviteForm.errors.email" class="text-xs text-red-600">
              {{ inviteForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
                  <span class="capitalize">{{ inviteForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="inviteForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="inviteDialogOpen = false" :disabled="inviteForm.processing">
            Cancel
          </Button>
          <Button @click="handleInvite" :disabled="inviteForm.processing">
            <span v-if="inviteForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Send Invitation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Change Role Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Change User Role</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
                  <span class="capitalize">{{ roleForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="roleForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="roleDialogOpen = false" :disabled="roleForm.processing">
            Cancel
          </Button>
          <Button @click="handleRoleUpdate" :disabled="roleForm.processing">
            <span v-if="roleForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Update Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Remove User Confirmation -->
    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove User"
      :description="`Are you sure you want to remove ${selectedUser?.name || selectedUser?.email} from ${company.name}? This action cannot be undone.`"
      confirm-text="Remove User"
      :loading="removeForm.processing"
      @confirm="handleRemoveUser"
    />
  </PageShell>
</template>
