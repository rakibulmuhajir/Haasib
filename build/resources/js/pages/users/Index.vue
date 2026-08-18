<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
import { formatDateTime } from '@/lib/datetime'
import {
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  ArrowLeft,
  Settings,
  Trash2,
} from 'lucide-vue-next'

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface UserRow {
  id: string
  name: string | null
  email: string
  role: string
  is_active: boolean
  joined_at: string | null
  permissions: string[]
  capabilities: { label: string; allowed: boolean; detail: string | null }[]
}

const props = defineProps<{
  company: CompanyRef
  users: UserRow[]
  currentUserRole: string
}>()

const searchQuery = ref('')
const roleDialogOpen = ref(false)
const createDialogOpen = ref(false)
const permissionDialogOpen = ref(false)
const selectedUser = ref<UserRow | null>(null)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name },
  { title: 'Users' },
])

const filteredUsers = computed(() => {
  if (!searchQuery.value) return props.users

  const query = searchQuery.value.toLowerCase()
  return props.users.filter(
    (user) =>
      user.name?.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query) ||
      user.role.toLowerCase().includes(query)
  )
})

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    manager: 'default',
    accountant: 'secondary',
    operations: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const roleForm = useForm({
  userId: '',
  role: '',
})

const createForm = useForm({
  name: '',
  email: '',
  role: 'operations',
  password: '',
  password_confirmation: '',
})

const availableRoles = ['manager', 'accountant', 'operations']
const roleLabels: Record<string, string> = {
  owner: 'Owner',
  manager: 'Manager',
  accountant: 'Accountant',
  operations: 'Operations Clerk',
  agent: 'Agent',
}
const canManageUsers = computed(() => ['owner', 'manager'].includes(props.currentUserRole))
const canManageUser = (user: UserRow) => canManageUsers.value && user.role !== 'owner'

const permissionGroup = (permission: string) => {
  const prefix = permission.split('.')[0]
  if (prefix === 'company') return 'Company & team'
  if (['customer', 'vendor'].includes(prefix)) return 'Contacts'
  if (['invoice', 'bill', 'credit_note', 'vendor_credit'].includes(prefix)) return 'Sales & purchases'
  if (['account', 'journal', 'posting_template', 'tax'].includes(prefix)) return 'Accounting'
  if (['payment', 'bank_account', 'bank_transaction', 'bank_feed', 'bank_reconciliation', 'bank_rule'].includes(prefix)) return 'Banking & payments'
  if (['item', 'item_category', 'warehouse', 'stock'].includes(prefix)) return 'Inventory'
  if (['employee', 'payroll', 'payroll_run', 'leave_request', 'payslip'].includes(prefix)) return 'Payroll'
  if (prefix === 'umrah') return 'Umrah operations'
  return 'Other'
}

const permissionLabel = (permission: string) => permission
  .split('.')
  .map((part) => part.replaceAll('_', ' ').replaceAll('-', ' '))
  .join(' · ')
  .replace(/\b\w/g, (letter) => letter.toUpperCase())

const permissionGroups = (permissions: string[]) => permissions.reduce<Record<string, string[]>>((groups, permission) => {
  const group = permissionGroup(permission)
  groups[group] ||= []
  groups[group].push(permission)
  return groups
}, {})

const openPermissions = (user: UserRow) => {
  selectedUser.value = user
  permissionDialogOpen.value = true
}

const openRoleDialog = (user: UserRow) => {
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
    },
  })
}

const handleCreate = () => {
  createForm.post(`/${props.company.slug}/users`, {
    onSuccess: () => {
      createForm.reset()
      createForm.role = 'operations'
      createDialogOpen.value = false
    },
  })
}

const handleRemove = (user: UserRow) => {
  if (!canManageUser(user) || !window.confirm(`Remove ${user.name || user.email} from ${props.company.name}?`)) return
  router.delete(`/${props.company.slug}/users/${user.id}`, { preserveScroll: true })
}

const tableColumns = [
  {
    key: 'name',
    label: 'User',
    sortable: true,
  },
  {
    key: 'role',
    label: 'Role',
    sortable: true,
  },
  {
    key: 'is_active',
    label: 'Status',
    sortable: true,
  },
  {
    key: 'permissions',
    label: 'Permissions',
  },
  {
    key: 'joined_at',
    label: 'Joined',
    sortable: true,
  },
  {
    key: 'actions',
    label: 'Actions',
    class: 'text-right',
  },
]
</script>

<template>
  <Head :title="`Users - ${company.name}`" />
  <PageShell
    title="Team Members"
    :icon="Users"
    :breadcrumbs="breadcrumbs"
    :back-button="{
      label: 'Back to Companies',
      onClick: () => router.visit('/companies'),
      icon: ArrowLeft,
    }"
  searchable
  v-model:search="searchQuery"
    search-placeholder="Search users by name, email, or role..."
  >
    <template #description>
      Manage users for <span class="font-medium text-text-quaternary">{{ company.name }}</span>
    </template>

    <template #actions>
      <Button v-if="canManageUsers" size="sm" @click="createDialogOpen = true">
        <UserPlus class="mr-2 h-4 w-4" />
        Add User
      </Button>
    </template>

    <!-- Empty State -->
    <EmptyState
      v-if="filteredUsers.length === 0"
      :icon="Users"
      title="No users found"
      :description="searchQuery ? 'Try adjusting your search terms' : 'This company has no team members yet'"
    >
      <template #actions>
        <Button v-if="!searchQuery && canManageUsers" @click="createDialogOpen = true" size="sm">
          <UserPlus class="mr-2 h-4 w-4" />
          Add User
        </Button>
      </template>
    </EmptyState>

    <!-- Users Table -->
    <DataTable
      v-else
      :data="filteredUsers"
      :columns="tableColumns"
      title="Team Members"
      :description="`${filteredUsers.length} ${filteredUsers.length === 1 ? 'user' : 'users'} in this company`"
      key-field="id"
    >
      <template #cell-name="{ row }">
        <div class="flex flex-col">
          <span class="font-medium text-text-primary">{{ row.name || 'Unknown' }}</span>
          <div class="flex items-center gap-1 text-text-tertiary">
            <Mail class="h-3 w-3" />
            <span class="text-xs">{{ row.email }}</span>
          </div>
        </div>
      </template>

      <template #cell-role="{ row }">
        <Badge :variant="getRoleBadgeVariant(row.role)" class="capitalize">
          <Shield class="mr-1 h-3 w-3" />
          {{ roleLabels[row.role] || row.role }}
        </Badge>
      </template>

      <template #cell-is_active="{ row }">
        <Badge :variant="row.is_active ? 'default' : 'secondary'">
          {{ row.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </template>

      <template #cell-permissions="{ row }">
        <Button size="sm" variant="outline" @click="openPermissions(row)">
          {{ row.permissions.length }} module permissions
        </Button>
      </template>

      <template #cell-joined_at="{ row }">
        <div v-if="row.joined_at" class="flex items-center gap-1 text-text-quaternary">
          <Calendar class="h-3 w-3" />
          <span>{{ formatDate(row.joined_at) }}</span>
        </div>
        <span v-else class="text-text-secondary">—</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-2">
          <DropdownMenu v-if="canManageUser(row)">
            <DropdownMenuTrigger as-child>
              <Button size="sm" variant="ghost">
                <Settings class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem @click="openRoleDialog(row)">
                <Shield class="mr-2 h-4 w-4" />
                Change Role
              </DropdownMenuItem>
              <DropdownMenuItem class="text-destructive" @click="handleRemove(row)">
                <Trash2 class="mr-2 h-4 w-4" />
                Remove User
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </template>

      <template #mobile-card="{ row }">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="font-medium text-text-primary">{{ row.name || 'Unknown' }}</div>
            <div class="flex items-center gap-1 text-xs text-text-tertiary mt-1">
              <Mail class="h-3 w-3" />
              <span>{{ row.email }}</span>
            </div>
            <div class="flex items-center gap-3 mt-2">
              <Badge :variant="getRoleBadgeVariant(row.role)" size="sm" class="capitalize">
                <Shield class="mr-1 h-3 w-3" />
                {{ row.role }}
              </Badge>
              <Badge :variant="row.is_active ? 'default' : 'secondary'" size="sm">
                {{ row.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </div>
          <Button v-if="canManageUser(row)" size="sm" variant="ghost" @click="openRoleDialog(row)">
            <Settings class="h-4 w-4" />
          </Button>
        </div>
      </template>
    </DataTable>

    <!-- Role Assignment Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-text-primary">Change User Role</DialogTitle>
          <DialogDescription class="text-text-tertiary">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-text-quaternary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between">
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
            <p v-if="roleForm.errors.role" class="text-xs text-status-critical">
              {{ roleForm.errors.role }}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="roleDialogOpen = false" :disabled="roleForm.processing">
            Cancel
          </Button>
          <Button @click="handleRoleUpdate" :disabled="roleForm.processing">
            <span
              v-if="roleForm.processing"
              class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            />
            Update Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="permissionDialogOpen">
      <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{{ selectedUser?.name || selectedUser?.email }} permissions</DialogTitle>
          <DialogDescription>
            Effective access granted by the {{ roleLabels[selectedUser?.role || ''] || selectedUser?.role }} role. Read-only.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedUser?.capabilities.length" class="grid gap-2 md:grid-cols-2">
          <div v-for="capability in selectedUser.capabilities" :key="capability.label" class="rounded-md border p-3">
            <p class="text-sm font-medium">{{ capability.label }}</p>
            <p class="mt-1 text-xs" :class="capability.allowed ? 'text-status-success' : 'text-destructive'">
              {{ capability.allowed ? 'Allowed' : 'Not allowed' }}<span v-if="capability.detail"> · {{ capability.detail }}</span>
            </p>
          </div>
        </div>
        <div v-if="selectedUser?.permissions.length" class="grid gap-5 py-2 md:grid-cols-2">
          <section v-for="([group, permissions]) in Object.entries(permissionGroups(selectedUser.permissions))" :key="group" class="space-y-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group }}</h3>
            <ul class="space-y-1.5">
              <li v-for="permission in permissions" :key="permission" class="text-sm">{{ permissionLabel(permission) }}</li>
            </ul>
          </section>
        </div>
        <p v-else class="py-4 text-sm text-muted-foreground">No permissions are currently granted.</p>
        <DialogFooter>
          <Button variant="outline" @click="permissionDialogOpen = false">Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Create User Dialog -->
    <Dialog v-model:open="createDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-text-primary">Add User</DialogTitle>
          <DialogDescription class="text-text-tertiary">
            Create a login for {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="new-user-name" class="text-text-quaternary">Full name</Label>
            <Input id="new-user-name" v-model="createForm.name" autocomplete="name" />
            <p v-if="createForm.errors.name" class="text-xs text-status-critical">{{ createForm.errors.name }}</p>
          </div>
          <div class="space-y-2">
            <Label for="new-user-email" class="text-text-quaternary">Email</Label>
            <Input
              id="new-user-email"
              v-model="createForm.email"
              type="email"
              placeholder="user@example.com"
              class="bg-surface-sunken border-rule-default"
            />
            <p v-if="createForm.errors.email" class="text-xs text-status-critical">
              {{ createForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-text-quaternary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between">
                  <span>{{ roleLabels[createForm.role] }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="createForm.role = role"
                  class="capitalize"
                >
                  {{ roleLabels[role] }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <p v-if="createForm.errors.role" class="text-xs text-status-critical">
              {{ createForm.errors.role }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="new-user-password" class="text-text-quaternary">Password</Label>
            <Input id="new-user-password" v-model="createForm.password" type="password" autocomplete="new-password" />
            <p v-if="createForm.errors.password" class="text-xs text-status-critical">{{ createForm.errors.password }}</p>
          </div>
          <div class="space-y-2">
            <Label for="new-user-password-confirmation" class="text-text-quaternary">Confirm password</Label>
            <Input id="new-user-password-confirmation" v-model="createForm.password_confirmation" type="password" autocomplete="new-password" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="createDialogOpen = false" :disabled="createForm.processing">
            Cancel
          </Button>
          <Button @click="handleCreate" :disabled="createForm.processing">
            <span
              v-if="createForm.processing"
              class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            />
            Create User
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
