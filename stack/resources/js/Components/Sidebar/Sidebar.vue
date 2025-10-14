<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useSidebar } from '@/composables/useSidebar'
import { useMenu } from '@/composables/useMenu'
import { useCompanyContext } from '@/composables/useCompanyContext'
import SvgIcon from '@/Components/SvgIcon.vue'
import SidebarMenuItem from './SidebarMenuItem.vue'

const { isSlim } = useSidebar()
const { currentCompany, userCompanies, hasPermission } = useCompanyContext()

// Define menu structure compatible with useMenu and company context
const menuItems = computed(() => {
  const items = [
    {
      label: 'Dashboard',
      path: '/dashboard',
      icon: 'home',
      routeName: 'dashboard',
      permission: null // Always visible
    },
    {
      label: 'Companies',
      path: '/companies',
      icon: 'building',
      routeName: 'companies.index',
      permission: null // Always visible
    }
  ]

  // Add company-specific items if a company is selected
  if (currentCompany.value) {
    items.push(
      {
        label: 'Invoicing',
        path: '/invoices',
        icon: 'file-text',
        routeName: 'invoices.index',
        permission: 'invoices.view'
      },
      {
        label: 'Accounting',
        path: '/accounting',
        icon: 'calculator',
        routeName: 'accounting.index',
        permission: 'accounting.view'
      },
      {
        label: 'Reports',
        path: '/reports',
        icon: 'chart-bar',
        routeName: 'reports.index',
        permission: 'reports.view'
      }
    )
  }

  return items
})

// Initialize the menu composable with the menu data
useMenu(menuItems.value)

// Filter items based on permissions
const filteredMenuItems = computed(() => {
  return menuItems.value.filter(item => {
    if (!item.permission) return true
    return hasPermission(item.permission)
  })
})

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

    <!-- Company Context -->
    <div v-if="hasCompanies" class="layout-company-context">
      <SidebarCompanySwitcher :is-slim="isSlim" />
    </div>

    <!-- Menu Container -->
    <div class="layout-menu-container">
      <!-- Menu Sections -->
      <nav class="layout-menu">
        <ul class="menu-section-items">
          <SidebarMenuItem
            v-for="item in filteredMenuItems"
            :key="item.routeName || item.path"
            :item="item"
            :is-slim="isSlim"
            :root="true"
          />
        </ul>
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
  /* Fully opaque, readable surface in light mode */
  background-color: var(--surface-card, #ffffff);
  color: var(--text-color, #0f172a);
  border-right: 1px solid var(--surface-border);
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  z-index: 998;
  transform: translateX(-100%);
  box-shadow: 2px 0 12px rgba(0,0,0,.08);
}

/* Also ensure header and scroll area inherit opaque background */
.layout-sidebar-logo,
.layout-menu-container,
.layout-sidebar-footer {
  background-color: inherit;
}

/* Explicitly respond to app theme attribute for dark/light */
:root[data-theme="blue-whale"] .layout-sidebar {
  /* Prefer theme tokens; fallbacks keep component readable */
  background-color: var(--p-surface-0, var(--surface-card, #ffffff));
  color: var(--p-text-color, var(--text-color, #0f172a));
  border-right-color: var(--p-content-border-color, var(--surface-border, rgba(0,0,0,0.08)));
}

:root[data-theme="blue-whale-dark"] .layout-sidebar {
  /* Use opaque-ish background in dark to avoid ground bleed-through */
  background-color: var(--p-surface-950, var(--surface-card, #0f172a));
  color: var(--p-text-color, var(--text-color, #e5e7eb));
  border-right-color: var(--p-content-border-color, rgba(255,255,255,0.12));
  box-shadow: 2px 0 14px rgba(0,0,0,.35);
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

.layout-company-context {
  padding: 1rem;
  border-bottom: 1px solid var(--surface-border);
  flex-shrink: 0;
}

.layout-menu-container {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0;
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
  /* Square button (no curved borders) */
  border-radius: 0;
  background-color: transparent;
  border: none;
  color: var(--text-color-secondary);
  cursor: pointer;
  transition: all 0.2s;
}

.layout-sidebar-toggle:hover {
  /* Align hover surface with theme tokens */
  background-color: var(--p-content-hover-background, var(--surface-hover, rgba(0,0,0,.04)));
  color: var(--primary-color);
}

.layout-wrapper.layout-mobile-active .layout-sidebar {
  transform: translateX(0);
}
</style>