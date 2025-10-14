<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { useTheme } from '@/composables/useTheme'
import { useSidebar } from '@/composables/useSidebar'
import { useCompanyContext } from '@/composables/useCompanyContext'
import { useI18n } from 'vue-i18n'
import SvgIcon from '@/Components/SvgIcon.vue'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'
import CompanyContextSwitcher from '@/Components/Company/ContextSwitcher.vue'

const { toggleTheme, isDark } = useTheme()
const { toggleSidebar, closeMobileSidebar } = useSidebar()
const { currentCompany, user } = useCompanyContext()
const page = usePage()
const { t } = useI18n()

const userMenu = ref()

const userMenuItems = computed(() => [
  {
    label: 'Profile',
    icon: 'pi pi-user',
    command: () => router.visit('/profile')
  },
  {
    label: 'Settings',
    icon: 'pi pi-cog',
    command: () => router.visit('/settings')
  },
  {
    separator: true
  },
  {
    label: 'Logout',
    icon: 'pi pi-sign-out',
    command: () => {
      router.post('/logout')
    }
  }
])

const themeTooltip = computed(() =>
  isDark.value ? 'Switch to light mode' : 'Switch to dark mode'
)

const toggleUserMenu = (event) => {
  userMenu.value?.toggle(event)
}

const handleLogout = async () => {
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

// Keyboard shortcuts
const onKey = (e) => {
  if (['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return
  
  // Only handle shortcuts if modifier keys are not pressed
  if (e.ctrlKey || e.metaKey || e.altKey) return
  
  switch (e.key.toLowerCase()) {
    case 'm':
      toggleSidebar()
      break
    case 't':
      toggleTheme()
      break
    case 'escape':
      closeMobileSidebar()
      break
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
})

const getUserInitials = () => {
  if (!user.value?.name) return '?'
  return user.value.name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>

<template>
  <div class="layout-topbar">
    <!-- Logo and Menu Toggle -->
    <div class="layout-topbar-logo-container lg:hidden">
      <button class="layout-menu-button layout-topbar-action" @click="toggleSidebar">
        <SvgIcon name="bars" set="line" class="w-5 h-5" />
      </button>
      <Link href="/" class="layout-topbar-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-8 h-8">
          <circle cx="20" cy="20" r="18" fill="currentColor" />
          <path d="M20 10 L30 20 L20 30 L10 20 Z" fill="white" />
        </svg>
        <span>Haasib</span>
      </Link>
    </div>

    <!-- Desktop Menu Toggle -->
    <div class="hidden lg:block">
      <button class="layout-menu-button layout-topbar-action" @click="toggleSidebar">
        <SvgIcon name="bars" set="line" class="w-5 h-5" />
      </button>
    </div>

    <!-- Page Title -->
    <div class="layout-topbar-title">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
        {{ page.props.title || 'Dashboard' }}
      </h1>
    </div>

    <!-- Topbar Actions -->
    <div class="layout-topbar-actions">
      <!-- Theme Toggle -->
      <button 
        type="button" 
        class="layout-topbar-action"
        @click="toggleTheme"
        v-tooltip.bottom="themeTooltip"
      >
        <i v-if="isDark" class="fas fa-sun text-yellow-400"></i>
        <i v-else class="fas fa-moon text-gray-600"></i>
      </button>

      <!-- Company Context Switcher -->
      <CompanyContextSwitcher v-if="currentCompany" />

      <!-- Desktop User Menu -->
      <div class="hidden lg:block">
        <button
          type="button"
          class="user-menu-button"
          @click="toggleUserMenu"
        >
          <Avatar
            :label="getUserInitials()"
            class="bg-gray-600 text-white"
            size="normal"
            shape="circle"
          />
          <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ user?.name }}
          </span>
          <i class="fas fa-chevron-down text-gray-400 text-xs ml-1"></i>
        </button>
      </div>

      <!-- Mobile Menu Button -->
      <button
        type="button"
        class="layout-topbar-action lg:hidden"
        @click="toggleUserMenu"
      >
        <i class="fas fa-ellipsis-v"></i>
      </button>

      <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
    </div>
  </div>
</template>

<style scoped>
.layout-topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4rem;
  z-index: 997;
  display: flex;
  align-items: center;
  padding: 0 1rem;
  background-color: var(--p-surface-0, #ffffff);
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
  transition: all 0.3s ease;
}

:root[data-theme="dark"] .layout-topbar {
  background-color: var(--p-surface-950, #1f2937);
  border-bottom-color: var(--p-content-border-color, #374151);
}

.layout-topbar-logo-container {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  width: auto;
}

.layout-topbar-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: var(--p-text-color, #1f2937);
  font-size: 1.25rem;
  font-weight: 600;
  transition: color 0.2s;
}

.layout-topbar-logo:hover {
  color: var(--p-primary-color, #3b82f6);
}

:root[data-theme="dark"] .layout-topbar-logo {
  color: var(--p-text-color, #f3f4f6);
}

.layout-menu-button {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  margin-right: 1rem;
  transition: background-color 0.2s;
  border: none;
  background: transparent;
  color: var(--p-text-color, #6b7280);
}

.layout-menu-button:hover {
  background-color: var(--p-content-hover-background, #f3f4f6);
  color: var(--p-primary-color, #3b82f6);
}

.layout-topbar-title {
  flex: 1;
  padding: 0 1rem;
  min-width: 0;
}

.layout-topbar-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.layout-topbar-action {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
  border: none;
  background: transparent;
  color: var(--p-text-color, #6b7280);
}

.layout-topbar-action:hover {
  background-color: var(--p-content-hover-background, #f3f4f6);
  color: var(--p-primary-color, #3b82f6);
}

.user-menu-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 50px;
  transition: background-color 0.2s;
  border: none;
  background: transparent;
  color: var(--p-text-color, #374151);
}

.user-menu-button:hover {
  background-color: var(--p-content-hover-background, #f3f4f6);
}

:root[data-theme="dark"] .layout-menu-button,
:root[data-theme="dark"] .layout-topbar-action,
:root[data-theme="dark"] .user-menu-button {
  color: var(--p-text-color, #9ca3af);
}

:root[data-theme="dark"] .layout-menu-button:hover,
:root[data-theme="dark"] .layout-topbar-action:hover,
:root[data-theme="dark"] .user-menu-button:hover {
  background-color: var(--p-content-hover-background, #374151);
}

@media (max-width: 1023px) {
  .layout-topbar {
    padding: 0 0.75rem;
  }
  
  .layout-menu-button {
    margin-right: 0.5rem;
  }
  
  .layout-topbar-title {
    display: none;
  }
}
</style>