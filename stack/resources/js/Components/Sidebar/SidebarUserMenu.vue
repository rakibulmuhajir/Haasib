<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'
import { useCompanyContext } from '@/composables/useCompanyContext'

const { user } = useCompanyContext()
const userMenu = ref()

const userInitials = computed(() => {
  if (!user.value?.name) return '?'
  return user.value.name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const userMenuItems = computed(() => [
  {
    label: 'Profile',
    icon: 'pi pi-user',
    command: () => {
      window.location.href = '/profile'
    }
  },
  {
    label: 'Settings',
    icon: 'pi pi-cog',
    command: () => {
      window.location.href = '/settings'
    }
  },
  {
    separator: true
  },
  {
    label: 'Logout',
    icon: 'pi pi-sign-out',
    command: async () => {
      try {
        await fetch('/logout', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        })
        window.location.href = '/'
      } catch (error) {
        console.error('Logout failed:', error)
      }
    }
  }
])

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}
</script>

<template>
  <div class="sidebar-user-menu">
    <Menu ref="userMenu" :model="userMenuItems" :popup="true">
      <template #start>
        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-3">
            <Avatar
              :label="userInitials"
              class="bg-gray-600 text-white"
            />
            <div>
              <p class="font-medium text-gray-900 dark:text-white">
                {{ user?.name || 'User' }}
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ user?.email }}
              </p>
            </div>
          </div>
        </div>
      </template>
    </Menu>

    <div
      class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      @click="toggleUserMenu"
      role="button"
      tabindex="0"
      @keydown.enter="toggleUserMenu"
      @keydown.space.prevent="toggleUserMenu"
      aria-label="User menu"
      aria-haspopup="true"
    >
      <Avatar
        :label="userInitials"
        class="bg-gray-600 text-white"
      />
      <div class="flex-1">
        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
          {{ user?.name || 'User' }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
          {{ user?.system_role || 'employee' }}
        </p>
      </div>
      <i class="pi pi-chevron-down text-gray-400"></i>
    </div>
  </div>
</template>

<style scoped>
.sidebar-user-menu {
  border-top: 1px solid var(--surface-border);
}

.sidebar-user-menu > div {
  transition: all 0.2s ease;
}

.sidebar-user-menu > div:hover {
  transform: translateY(-1px);
}
</style>
