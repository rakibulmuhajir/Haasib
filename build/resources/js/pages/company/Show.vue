<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
  Building2,
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  Settings,
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
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name },
])

const editDialogOpen = ref(false)
const inviteDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)

const editForm = useForm({
  name: props.company.name,
  industry: props.company.industry || '',
  country: props.company.country || '',
})

const inviteForm = useForm({
  email: '',
  role: 'user',
})

const roleForm = useForm({
  userId: '',
  role: '',
})

const removeForm = useForm({})

const canManage = computed(() => ['owner', 'admin'].includes(props.currentUserRole))

const availableRoles = ['owner', 'admin', 'manager', 'user']

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    admin: 'default',
    manager: 'secondary',
    user: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleEditCompany = () => {
  editForm.put(`/${props.company.slug}`, {
    onSuccess: () => {
      editDialogOpen.value = false
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
      <Button v-if="canManage" variant="outline" size="sm" @click="editDialogOpen = true">
        <Settings class="mr-2 h-4 w-4" />
        Settings
      </Button>
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

      <!-- Company Details Card -->
      <Card class="border-zinc-200/80 bg-white">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle class="text-zinc-900">Company Details</CardTitle>
              <CardDescription class="text-zinc-500">
                Basic information about your organization
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

    <!-- Edit Company Dialog -->
    <Dialog v-model:open="editDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Edit Company</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Update your company information
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="name" class="text-zinc-700">Company Name</Label>
            <Input
              id="name"
              v-model="editForm.name"
              class="border-zinc-200"
            />
            <p v-if="editForm.errors.name" class="text-xs text-red-600">
              {{ editForm.errors.name }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="industry" class="text-zinc-700">Industry</Label>
            <Input
              id="industry"
              v-model="editForm.industry"
              placeholder="e.g., Technology, Healthcare"
              class="border-zinc-200"
            />
          </div>
          <div class="space-y-2">
            <Label for="country" class="text-zinc-700">Country Code</Label>
            <Input
              id="country"
              v-model="editForm.country"
              placeholder="US"
              maxlength="2"
              class="border-zinc-200 uppercase"
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="editDialogOpen = false" :disabled="editForm.processing">
            Cancel
          </Button>
          <Button @click="handleEditCompany" :disabled="editForm.processing">
            <span v-if="editForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Save Changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

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
