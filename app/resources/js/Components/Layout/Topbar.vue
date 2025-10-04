<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { useTheme } from '@/composables/useTheme'
import { useSidebar } from '@/composables/useSidebar'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue'
import { useI18n } from 'vue-i18n'

const { toggleTheme, isDarkTheme } = useTheme()
const { isSlim, toggleSlim } = useSidebar()
const page = usePage()

const user = computed(() => page.props.auth?.user)
const userMenu = ref()
const { t } = useI18n()

const userMenuItems = computed(() => ([
  {
    label: t('navigation.profile'),
    icon: 'pi pi-user',
    command: () => router.visit('/profile')
  },
  {
    label: t('navigation.settings'),
    icon: 'pi pi-cog',
    command: () => router.visit('/settings')
  },
  {
    separator: true
  },
  {
    label: t('navigation.logout'),
    icon: 'pi pi-sign-out',
    command: () => {
      router.post('/logout')
    }
  }
]))

const themeTooltip = computed(() =>
  isDarkTheme.value ? t('navigation.lightModeTooltip') : t('navigation.darkModeTooltip'),
)

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}

function toggleMobileSidebar() {
  document.querySelector('.layout-wrapper')?.classList.toggle('layout-mobile-active')
}

// Keyboard shortcuts
const onKey = (e: KeyboardEvent) => {
  if (['INPUT', 'TEXTAREA'].includes((e.target as HTMLElement)?.tagName)) return
  if (e.key.toLowerCase() === 'm') {
    toggleMobileSidebar()
  } else if (e.key.toLowerCase() === 's') {
    toggleSlim()
  } else if (e.key.toLowerCase() === 't') {
    toggleTheme()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div class="layout-topbar">
    <!-- Logo and Menu Toggle -->
    <div class="layout-topbar-logo-container lg:hidden">
      <button class="layout-menu-button layout-topbar-action" @click="toggleMobileSidebar">
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

    <!-- Mobile Menu Toggle (Desktop) -->
    <div class="hidden lg:block">
      <button class="layout-menu-button layout-topbar-action" @click="toggleMobileSidebar">
        <SvgIcon name="bars" set="line" class="w-5 h-5" />
      </button>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="layout-topbar-breadcrumb hidden lg:block">
      <Breadcrumb />
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
        <span class="theme-icon">
          <svg 
            v-if="isDarkTheme" 
            class="w-5 h-5" 
            fill="currentColor" 
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
          </svg>
          <svg 
            v-else 
            class="w-5 h-5" 
            fill="currentColor" 
            viewBox="0 0 20 20"
          >
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
          </svg>
        </span>
      </button>

      <!-- Locale Switcher -->
      <LocaleSwitcher class="hidden sm:block" />

      <!-- Company Switcher (always show) -->
      <CompanySwitcher />

      <!-- Mobile Menu Button -->
      <button
        type="button"
        class="layout-topbar-action lg:hidden"
        @click="toggleUserMenu"
      >
        <SvgIcon name="dots-vertical" set="line" class="w-5 h-5" />
      </button>

      <!-- Desktop User Menu -->
      <div class="hidden lg:block">
        <button
          type="button"
          class="user-menu-button"
          @click="toggleUserMenu"
        >
          <Avatar
            :image="user?.avatar_url"
            :label="user?.name?.charAt(0)?.toUpperCase()"
            size="normal"
            shape="circle"
          />
          <span class="ml-2 text-sm font-medium">{{ user?.name }}</span>
          <SvgIcon name="chevron-down" set="line" class="w-4 h-4 ml-1" />
        </button>
      </div>

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
  background-color: var(--p-surface-0, var(--surface-card));
  border-bottom: 1px solid var(--p-content-border-color, var(--surface-border));
  transition: all 0.3s ease;
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
  color: var(--p-text-color, var(--text-color));
  font-size: 1.25rem;
  font-weight: 600;
  transition: color 0.2s;
}

.layout-topbar-logo:hover {
  color: var(--p-primary-color, var(--primary-color));
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
}

.layout-menu-button:hover {
  background-color: var(--p-content-hover-background, var(--surface-hover));
}

.layout-topbar-breadcrumb {
  flex: 1;
  padding: 0 1rem;
  overflow: hidden;
}

.layout-topbar-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* Desktop-only menu button */
.layout-topbar .hidden.lg:block + .layout-topbar-breadcrumb {
  margin-left: 1rem;
}

.layout-topbar-action {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
}

.layout-topbar-action:hover {
  background-color: var(--p-content-hover-background, var(--surface-hover));
  color: var(--p-primary-color, var(--primary-color));
}

.theme-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
}

.theme-icon svg {
  transition: transform 0.2s ease;
}

.layout-topbar-action:hover .theme-icon svg {
  transform: scale(1.1);
}

.user-menu-button {
  width: auto;
  height: 2.5rem;
  padding: 0 0.75rem;
  gap: 0.5rem;
}

@media (max-width: 991px) {
  .layout-topbar {
    padding: 0 0.75rem;
  }
  
  .layout-topbar-logo-container {
    width: auto;
  }
  
  .layout-menu-button {
    margin-right: 0.5rem;
  }
  
  .layout-topbar-breadcrumb {
    display: none;
  }
}
</style>
