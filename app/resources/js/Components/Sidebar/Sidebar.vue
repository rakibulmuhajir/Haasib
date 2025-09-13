<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useSidebar } from '@/composables/useSidebar'
import { useMenu } from '@/composables/useMenu'
import SvgIcon from '@/Components/SvgIcon.vue'
import SidebarMenuItem from './SidebarMenuItem.vue'

interface MenuItem { label: string; path?: string; routeName?: string; icon?: string; permission?: string | string[]; children?: MenuItem[] }

defineProps<{ title?: string }>()

const { isSlim } = useSidebar()

// Define menu structure compatible with useMenu
const menuItems: MenuItem[] = [
  {
    label: 'Ledger',
    children: [
      {
        label: 'Journal Entries',
        path: '/ledger',
        icon: 'book',
        routeName: 'ledger.index',
        permission: 'ledger.view',
        children: [
          { label: 'All Entries', path: '/ledger', icon: 'list', routeName: 'ledger.index', permission: 'ledger.view' },
          { label: 'Create Entry', path: '/ledger/create', icon: 'plus', routeName: 'ledger.create', permission: 'ledger.create' }
        ]
      },
      {
        label: 'Chart of Accounts',
        path: '/ledger/accounts',
        icon: 'pie-chart',
        routeName: 'ledger.accounts.index',
        permission: 'ledger.accounts.view',
        children: [
          { label: 'Browse Accounts', path: '/ledger/accounts', icon: 'list', routeName: 'ledger.accounts.index', permission: 'ledger.accounts.view' }
        ]
      }
    ]
  },
  {
    label: 'Admin',
    children: [
      {
        label: 'Companies',
        path: '/admin/companies',
        icon: 'building',
        routeName: 'admin.companies.index',
        permission: 'admin.companies.view',
        children: [
          { label: 'All Companies', path: '/admin/companies', icon: 'list', routeName: 'admin.companies.index', permission: 'admin.companies.view' },
          { label: 'Create Company', path: '/admin/companies/create', icon: 'plus', routeName: 'admin.companies.create', permission: 'admin.companies.create' }
        ]
      },
      {
        label: 'Users',
        path: '/admin/users',
        icon: 'users',
        routeName: 'admin.users.index',
        permission: 'admin.users.view',
        children: [
          { label: 'All Users', path: '/admin/users', icon: 'list', routeName: 'admin.users.index', permission: 'admin.users.view' },
          { label: 'Create User', path: '/admin/users/create', icon: 'plus', routeName: 'admin.users.create', permission: 'admin.users.create' }
        ]
      }
    ]
  }
]

// Initialize the menu composable with the menu data.
useMenu(menuItems)

// Keep sections structure for template rendering
const menuSections = [
  {
    title: 'Ledger',
    items: menuItems[0].children || []
  },
  {
    title: 'Admin', 
    items: menuItems[1].children || []
  }
]

const sidebarClass = computed(() => ({
  'layout-sidebar': true,
  'layout-sidebar-slim': isSlim.value
}))
</script>

<template>
  <aside :class="sidebarClass">
    <!-- Logo/Brand -->
    <div class="layout-sidebar-logo hidden lg:block">
      <Link href="/" class="layout-sidebar-logo-link">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-8 h-8">
          <circle cx="20" cy="20" r="18" fill="currentColor" />
          <path d="M20 10 L30 20 L20 30 L10 20 Z" fill="white" />
        </svg>
        <span v-if="!isSlim" class="layout-sidebar-logo-text">Haasib</span>
      </Link>
    </div>

    <!-- Menu Container -->
    <div class="layout-menu-container">
      <!-- Menu Sections -->
      <nav class="layout-menu">
        <template v-for="section in menuSections" :key="section.title">
          <div class="menu-section" v-if="section.items.length > 0">
            <div v-if="!isSlim && section.title" class="menu-section-title">
              {{ section.title }}
            </div>
            <ul class="menu-section-items">
              <SidebarMenuItem
                v-for="item in section.items"
                :key="item.routeName || item.path"
                :item="item"
                :is-slim="isSlim"
                :root="true"
              />
            </ul>
          </div>
        </template>
      </nav>

      <!-- Slot for additional content -->
      <slot />
    </div>

    <!-- Sidebar Footer with Toggle Button -->
    <div class="layout-sidebar-footer">
      <button
        @click="isSlim = !isSlim"
        class="layout-sidebar-toggle"
        v-tooltip.right="isSlim ? 'Expand Sidebar' : 'Collapse Sidebar'"
      >
        <SvgIcon 
          :name="isSlim ? 'arrow-right' : 'arrow-left'" 
          set="line" 
          class="w-4 h-4" 
        />
      </button>
    </div>
  </aside>
</template>

<style scoped>
.layout-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  height: 100vh;
  width: 280px;
  background-color: var(--surface-card);
  border-right: 1px solid var(--surface-border);
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  z-index: 998;
}

.layout-sidebar-slim {
  width: 4rem;
}

.layout-sidebar-logo {
  height: 4rem;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 1rem;
  border-bottom: 1px solid var(--surface-border);
  flex-shrink: 0;
}

.layout-sidebar-logo-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  color: var(--text-color);
  font-size: 1.25rem;
  font-weight: 600;
  transition: color 0.2s;
}

.layout-sidebar-logo-link:hover {
  color: var(--primary-color);
}

.layout-sidebar-logo-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.layout-menu-container {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0;
}

.menu-section {
  margin-bottom: 1rem;
}

.menu-section:last-child {
  margin-bottom: 0;
}

.menu-section-title {
  padding: 0.75rem 1.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-color-secondary);
  letter-spacing: 0.05em;
}

.menu-section-items {
  list-style: none;
  margin: 0;
  padding: 0;
}

.layout-sidebar-footer {
  padding: 1rem;
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: center;
}

.layout-sidebar-toggle {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background-color: transparent;
  border: none;
  color: var(--text-color-secondary);
  cursor: pointer;
  transition: all 0.2s;
}

.layout-sidebar-toggle:hover {
  background-color: var(--surface-hover);
  color: var(--primary-color);
}

@media (max-width: 991px) {
  .layout-sidebar {
    transform: translateX(-100%);
  }
  
  .layout-wrapper.layout-mobile-active .layout-sidebar {
    transform: translateX(0);
  }
}
</style>