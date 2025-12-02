<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
} from 'lucide-vue-next'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  created_at: string
  industry?: string
  country?: string
}

interface Stats {
  total_users: number
  active_users: number
  admins: number
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
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name },
])

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

    <template #actions>
    </template>

    <div class="space-y-6">
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

      <!-- Pending Invitations (visible to owners/admins) -->
      <Card v-if="currentUserRole === 'owner'" class="border-amber-100 bg-amber-50">
        <CardHeader class="pb-3">
          <CardTitle class="text-amber-900">Pending Invitations</CardTitle>
          <CardDescription class="text-amber-700">
            Invites sent from {{ company.name }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="pendingInvitations.length === 0" class="text-sm text-amber-700">
            No pending invitations.
          </div>
          <div v-else class="space-y-3">
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

      <!-- Company Details Card (read-only) -->
      <Card class="border-zinc-200/80 bg-white">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle class="text-zinc-900">Company Details</CardTitle>
              <CardDescription class="text-zinc-500">
                Created values cannot be changed
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Company Name</div>
              <div class="text-base text-zinc-900">{{ company.name }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Slug</div>
              <div class="font-mono text-base text-zinc-900">{{ company.slug }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Base Currency</div>
              <div class="font-mono text-base text-zinc-900">{{ company.base_currency }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Industry</div>
              <div class="text-base text-zinc-900">{{ company.industry || '—' }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Country</div>
              <div class="text-base text-zinc-900">{{ company.country || '—' }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="text-sm font-medium text-zinc-500">Created</div>
              <div class="flex items-center gap-1.5 text-base text-zinc-900">
                <Calendar class="h-3.5 w-3.5 text-zinc-400" />
                {{ new Date(company.created_at).toLocaleDateString() }}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- User Management -->
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
    </div>

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
