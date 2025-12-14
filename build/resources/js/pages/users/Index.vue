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
import {
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  ArrowLeft,
  Settings,
} from 'lucide-vue-next'

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
}

const props = defineProps<{
  company: CompanyRef
  users: UserRow[]
}>()

const searchQuery = ref('')
const roleDialogOpen = ref(false)
const inviteDialogOpen = ref(false)
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
    admin: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const roleForm = useForm({
  userId: '',
  role: '',
})

const inviteForm = useForm({
  email: '',
  role: 'member',
})

const availableRoles = ['owner', 'admin', 'accountant', 'viewer', 'member']

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

const handleInvite = () => {
  inviteForm.post(`/${props.company.slug}/users/invite`, {
    onSuccess: () => {
      inviteForm.reset()
      inviteDialogOpen.value = false
    },
  })
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
      Manage users for <span class="font-medium text-slate-300">{{ company.name }}</span>
    </template>

    <template #actions>
      <Button size="sm" @click="inviteDialogOpen = true">
        <UserPlus class="mr-2 h-4 w-4" />
        Invite User
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
        <Button v-if="!searchQuery" @click="inviteDialogOpen = true" size="sm">
          <UserPlus class="mr-2 h-4 w-4" />
          Invite User
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
          <span class="font-medium text-slate-100">{{ row.name || 'Unknown' }}</span>
          <div class="flex items-center gap-1 text-slate-400">
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
          {{ row.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </template>

      <template #cell-joined_at="{ row }">
        <div v-if="row.joined_at" class="flex items-center gap-1 text-slate-300">
          <Calendar class="h-3 w-3" />
          <span>{{ new Date(row.joined_at).toLocaleDateString() }}</span>
        </div>
        <span v-else class="text-slate-500">—</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-2">
          <DropdownMenu>
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
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </template>

      <template #mobile-card="{ row }">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="font-medium text-slate-100">{{ row.name || 'Unknown' }}</div>
            <div class="flex items-center gap-1 text-xs text-slate-400 mt-1">
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
          <Button size="sm" variant="ghost" @click="openRoleDialog(row)">
            <Settings class="h-4 w-4" />
          </Button>
        </div>
      </template>
    </DataTable>

    <!-- Role Assignment Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-slate-100">Change User Role</DialogTitle>
          <DialogDescription class="text-slate-400">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-slate-200">Role</Label>
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
            <p v-if="roleForm.errors.role" class="text-xs text-red-400">
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

    <!-- Invite User Dialog -->
    <Dialog v-model:open="inviteDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-slate-100">Invite User</DialogTitle>
          <DialogDescription class="text-slate-400">
            Send an invitation to join {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="email" class="text-slate-200">Email</Label>
            <Input
              id="email"
              v-model="inviteForm.email"
              type="email"
              placeholder="user@example.com"
              class="bg-slate-950/50 border-slate-700"
            />
            <p v-if="inviteForm.errors.email" class="text-xs text-red-400">
              {{ inviteForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-slate-200">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between">
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
            <p v-if="inviteForm.errors.role" class="text-xs text-red-400">
              {{ inviteForm.errors.role }}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="inviteDialogOpen = false" :disabled="inviteForm.processing">
            Cancel
          </Button>
          <Button @click="handleInvite" :disabled="inviteForm.processing">
            <span
              v-if="inviteForm.processing"
              class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            />
            Send Invitation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
