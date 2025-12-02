<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import RoleBadge from '@/Components/RoleBadge.vue'

interface Member {
    id: string
    name: string
    email: string
    role: string
    is_active: boolean
    joined_at: string
}

interface Props {
    member: Member
    companySlug: string
    currentUserRole: string
    currentUserId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'roleChanged'): void
    (e: 'memberRemoved'): void
}>()

const showRoleMenu = ref(false)
const isUpdating = ref(false)
const isRemoving = ref(false)

const canChangeRole = computed(() => {
    // Can't change your own role
    if (props.member.id === props.currentUserId) {
        return false
    }

    // Only owners and admins can change roles
    if (!['owner', 'admin'].includes(props.currentUserRole)) {
        return false
    }

    // Owners can change any role except other owners
    if (props.currentUserRole === 'owner') {
        return props.member.role !== 'owner'
    }

    // Admins can ONLY change member, viewer, and accountant roles
    // They CANNOT change owners or other admins
    if (props.currentUserRole === 'admin') {
        return ['member', 'viewer', 'accountant'].includes(props.member.role)
    }

    return false
})

const canRemove = computed(() => {
    // Can't remove yourself
    if (props.member.id === props.currentUserId) {
        return false
    }

    // Can't remove owners (unless you're an owner)
    if (props.member.role === 'owner' && props.currentUserRole !== 'owner') {
        return false
    }

    // Owners and admins can remove others
    return ['owner', 'admin'].includes(props.currentUserRole)
})

const availableRoles = computed(() => {
    const roles = [
        { value: 'owner', label: 'Owner' },
        { value: 'admin', label: 'Admin' },
        { value: 'accountant', label: 'Accountant' },
        { value: 'member', label: 'Member' },
        { value: 'viewer', label: 'Viewer' },
    ]

    // Owners can assign any role
    if (props.currentUserRole === 'owner') {
        return roles
    }

    // Admins can only assign admin, accountant, member, viewer
    return roles.filter(r => r.value !== 'owner')
})

const changeRole = async (newRole: string) => {
    if (newRole === props.member.role) {
        showRoleMenu.value = false
        return
    }

    isUpdating.value = true
    showRoleMenu.value = false

    router.put(
        `/${props.companySlug}/users/${props.member.id}/role`,
        { role: newRole },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('roleChanged')
            },
            onFinish: () => {
                isUpdating.value = false
            },
        }
    )
}

const removeMember = () => {
    if (!confirm(`Are you sure you want to remove ${props.member.name} from this company?`)) {
        return
    }

    isRemoving.value = true

    router.delete(`/${props.companySlug}/users/${props.member.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('memberRemoved')
        },
        onFinish: () => {
            isRemoving.value = false
        },
    })
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ member.name }}
                        <span v-if="member.id === currentUserId" class="ml-2 text-xs text-gray-500 dark:text-gray-400">(You)</span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ member.email }}
                    </div>
                </div>
            </div>
        </td>

        <td class="px-6 py-4 whitespace-nowrap">
            <div class="relative">
                <button
                    v-if="canChangeRole"
                    @click="showRoleMenu = !showRoleMenu"
                    :disabled="isUpdating"
                    class="inline-flex items-center hover:opacity-80 transition-opacity disabled:opacity-50"
                >
                    <RoleBadge :role="member.role" />
                    <i class="fas fa-chevron-down ml-2 text-xs text-gray-400"></i>
                </button>
                <RoleBadge v-else :role="member.role" />

                <!-- Role Dropdown -->
                <div
                    v-if="showRoleMenu"
                    class="absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5"
                >
                    <div class="py-1">
                        <button
                            v-for="role in availableRoles"
                            :key="role.value"
                            @click="changeRole(role.value)"
                            :class="[
                                'block w-full text-left px-4 py-2 text-sm',
                                member.role === role.value
                                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                            ]"
                        >
                            {{ role.label }}
                            <i v-if="member.role === role.value" class="fas fa-check ml-2 text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </td>

        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ formatDate(member.joined_at) }}
        </td>

        <td class="px-6 py-4 whitespace-nowrap">
            <span :class="[
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                member.is_active
                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
            ]">
                {{ member.is_active ? 'Active' : 'Inactive' }}
            </span>
        </td>

        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <button
                v-if="canRemove"
                @click="removeMember"
                :disabled="isRemoving"
                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
            >
                <i v-if="isRemoving" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-trash"></i>
            </button>
            <span v-else class="text-gray-400">-</span>
        </td>
    </tr>
</template>
