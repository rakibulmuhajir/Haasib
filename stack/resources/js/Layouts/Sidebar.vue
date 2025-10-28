<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useSidebar } from '@/composables/useSidebar'
import { useCompanyContext } from '@/composables/useCompanyContext'
import SidebarMenuItem from '@/Components/Sidebar/SidebarMenuItem.vue'
import SidebarCompanySwitcher from '@/Components/Sidebar/SidebarCompanySwitcher.vue'

const {
  isMobile,
  isMobileMenuOpen,
  closeMobileMenu,
  setSlim
} = useSidebar()

const {
  currentCompany,
  hasPermission
} = useCompanyContext()

const navigation = computed(() => {
  const companyId = currentCompany.value?.id

  const organizationChildren = currentCompany.value
    ? [
        {
          label: 'Company Overview',
          path: `/companies/${companyId}`,
          icon: 'id-badge'
        },
        {
          label: 'Fiscal Year',
          path: `/companies/${companyId}/fiscal-year`,
          icon: 'calendar-alt',
          permission: 'canManage'
        },
        {
          label: 'Modules',
          path: `/companies/${companyId}/modules`,
          icon: 'th-large',
          permission: 'canManage'
        },
        {
          label: 'Audit Log',
          path: `/companies/${companyId}/audit`,
          icon: 'clipboard-list',
          permission: 'canManage'
        },
        {
          label: 'Users & Roles',
          path: '/companies/users',
          icon: 'users-cog',
          permission: 'canViewUsers'
        }
      ]
    : [
        {
          label: 'Companies',
          path: '/companies',
          icon: 'building'
        }
      ]

  const items = [
    {
      label: 'Overview',
      path: '/dashboard',
      icon: 'tachometer-alt',
      description: 'Company KPIs and high-level metrics.'
    },
    {
      label: 'Sales & Receivables',
      icon: 'hand-holding-usd',
      permission: 'canAccessInvoicing',
      description: 'Quotes, invoices, and customer collections.',
      children: [
        {
          label: 'Invoices',
          path: '/invoices',
          icon: 'file-invoice',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Payments',
          path: '/payments',
          icon: 'credit-card',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Customers',
          path: '/customers',
          icon: 'users',
          permission: 'canAccessInvoicing'
        }
      ]
    },
    {
      label: 'Banking & Cash',
      icon: 'university',
      permission: 'canAccessAccounting',
      description: 'Statement imports and reconciliation.',
      children: [
        {
          label: 'Reconciliation',
          path: '/bank-reconciliation',
          icon: 'balance-scale',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Statement Import',
          path: '/bank-import',
          icon: 'file-upload',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Bank Reports',
          path: '/bank-reports',
          icon: 'chart-bar',
          permission: 'canAccessAccounting'
        }
      ]
    },
    {
      label: 'Accounting Ops',
      icon: 'calculator',
      permission: 'canAccessAccounting',
      description: 'Journal entries, ledgers, and balances.',
      children: [
        {
          label: 'Journal Entries',
          path: '/journal-entries',
          icon: 'book',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Trial Balance',
          path: '/trial-balance',
          icon: 'list-alt',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Ledger',
          path: '/ledger',
          icon: 'book-open',
          permission: 'canAccessAccounting'
        }
      ]
    },
    {
      label: 'Period Close',
      icon: 'calendar-check',
      permission: 'canAccessPeriodClose',
      description: 'Checklist workflows and compliance tracking.',
      children: [
        {
          label: 'Period Close',
          path: '/period-close',
          icon: 'tasks',
          permission: 'canAccessPeriodClose'
        },
        {
          label: 'Audit Trail',
          path: '/audit-trail',
          icon: 'history',
          permission: 'canAccessPeriodClose'
        },
        {
          label: 'Compliance',
          path: '/compliance',
          icon: 'shield-alt',
          permission: 'canAccessPeriodClose'
        }
      ]
    },
    {
      label: 'Reporting',
      icon: 'chart-line',
      permission: 'canViewReports',
      description: 'Dashboards, statements, and saved templates.',
      children: [
        {
          label: 'Dashboard',
          path: '/reports/dashboard',
          icon: 'chart-pie',
          permission: 'canViewReports'
        },
        {
          label: 'Financial Reports',
          path: '/reports/financial',
          icon: 'file-alt',
          permission: 'canViewReports'
        },
        {
          label: 'Statements',
          path: '/reports/statements',
          icon: 'file-invoice-dollar',
          permission: 'canViewReports'
        },
        {
          label: 'Templates',
          path: '/reports/templates',
          icon: 'layer-group',
          permission: 'canViewReports'
        }
      ]
    },
    {
      label: 'Organization',
      icon: 'building',
      description: 'Manage entities, teams, and access.',
      children: organizationChildren
    },
    {
      label: 'User & Settings',
      icon: 'user-cog',
      description: 'Personal profile and workspace preferences.',
      children: [
        {
          label: 'My Profile',
          path: '/profile',
          icon: 'user'
        },
        {
          label: 'System Settings',
          path: '/settings',
          icon: 'cog',
          permission: 'canViewSettings'
        }
      ]
    }
  ]

  const sanitizeChildren = (children = []) =>
    children.filter(child => !child.permission || hasPermission(child.permission))

  return items
    .map(item => ({
      ...item,
      children: sanitizeChildren(item.children)
    }))
    .filter(item => {
      if (item.permission && !hasPermission(item.permission)) {
        return false
      }
      if (item.children?.length) {
        return true
      }
      return Boolean(item.path)
    })
})

const hoveredItem = ref(null)

const drawerChildren = computed(() => hoveredItem.value?.children ?? [])
const drawerTitle = computed(() => hoveredItem.value?.label ?? '')
const drawerDescription = computed(() => hoveredItem.value?.description ?? '')

const showDrawer = computed(() => {
  if (isMobile.value) return false
  return drawerChildren.value.length > 0
})

const sidebarClasses = computed(() => ({
  sidebar: true,
  'sidebar--mobile': isMobile.value,
  'sidebar--mobile-open': isMobile.value && isMobileMenuOpen.value
}))

const containerClasses = computed(() => ({
  'sidebar-container': true,
  'sidebar-container--with-drawer': showDrawer.value
}))

const handleItemHover = (item) => {
  if (isMobile.value) return
  if (!item?.children?.length) {
    hoveredItem.value = null
    return
  }
  hoveredItem.value = item
}

const clearHover = () => {
  hoveredItem.value = null
}

watch(isMobile, (value) => {
  if (value) {
    clearHover()
  }
})

onMounted(() => {
  setSlim(true)
})
</script>

<template>
  <div
    :class="containerClasses"
    @mouseleave="clearHover"
  >
    <div
      v-if="isMobile && isMobileMenuOpen"
      class="sidebar-overlay"
      @click="closeMobileMenu"
    />

    <aside
      :class="sidebarClasses"
      role="navigation"
      aria-label="Main navigation"
    >
      <div class="sidebar-top">
        <Link href="/" class="sidebar-logo" aria-label="Go to dashboard">
          <i class="fas fa-tachometer-alt" />
        </Link>

        <button
          v-if="isMobile"
          class="sidebar-mobile-close"
          type="button"
          aria-label="Close navigation"
          @click="closeMobileMenu"
        >
          <i class="fas fa-times" />
        </button>
      </div>

      <div class="sidebar-company">
        <SidebarCompanySwitcher :is-slim="true" />
      </div>

      <nav class="sidebar-nav" role="menu">
        <ul class="sidebar-menu">
          <SidebarMenuItem
            v-for="item in navigation"
            :key="item.label"
            :item="item"
            :depth="0"
            @hover="handleItemHover"
          />
        </ul>
      </nav>
    </aside>

    <transition name="sidebar-drawer">
      <div
        v-if="showDrawer"
        class="sidebar-drawer"
        role="menu"
      >
        <header class="sidebar-drawer__header">
          <span class="sidebar-drawer__title">{{ drawerTitle }}</span>
          <p v-if="drawerDescription" class="sidebar-drawer__description">
            {{ drawerDescription }}
          </p>
        </header>

        <nav class="sidebar-drawer__nav" role="menu">
          <ul class="sidebar-drawer__list">
            <li
              v-for="child in drawerChildren"
              :key="child.path || child.label"
              class="sidebar-drawer__item"
              role="none"
            >
              <Link
                :href="child.path"
                class="sidebar-drawer__link"
                role="menuitem"
              >
                <span class="sidebar-drawer__bullet" />
                <span class="sidebar-drawer__label">{{ child.label }}</span>
              </Link>
            </li>
          </ul>
        </nav>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.sidebar-container {
  position: relative;
  display: flex;
  min-height: 100vh;
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 45;
}

.sidebar {
  width: 92px;
  min-width: 92px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  background: var(--p-surface-0, #ffffff);
  border-right: 1px solid var(--surface-border, #e5e7eb);
  padding: 1.5rem 1rem;
  position: relative;
  z-index: 50;
  transition: transform 0.3s ease;
}

.sidebar--mobile {
  position: fixed;
  left: 0;
  top: 0;
  transform: translateX(-100%);
}

.sidebar--mobile-open {
  transform: translateX(0);
}

.sidebar-top {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  margin-bottom: 1.5rem;
  gap: 0.5rem;
}

.sidebar-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 1rem;
  background: linear-gradient(135deg, #60A5FA, #2563EB);
  color: #ffffff;
  text-decoration: none;
  box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
}

.sidebar-logo i {
  font-size: 1.25rem;
}

.sidebar-mobile-close {
  border: none;
  background: transparent;
  color: #60A5FA;
  padding: 0.25rem;
  border-radius: 9999px;
}

.sidebar-mobile-close i {
  font-size: 1rem;
}

.sidebar-company {
  width: 100%;
  margin-bottom: 1.5rem;
}

.sidebar-nav {
  width: 100%;
  flex: 1;
  display: flex;
  justify-content: flex-start;
}

.sidebar-menu {
  list-style: none;
  margin: 0;
  padding: 0;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.sidebar-drawer-enter-active,
.sidebar-drawer-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.sidebar-drawer-enter-from,
.sidebar-drawer-leave-to {
  opacity: 0;
  transform: translateX(8px);
}

.sidebar-drawer {
  width: 260px;
  height: 100vh;
  background: var(--p-surface-0, #ffffff);
  border-right: 1px solid var(--surface-border, #e5e7eb);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  position: absolute;
  left: 92px;
  top: 0;
  z-index: 40;
}

.sidebar-drawer__header {
  padding: 1.25rem 1.5rem 0.75rem;
  border-bottom: 1px solid var(--surface-border, #e5e7eb);
}

.sidebar-drawer__title {
  display: block;
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--text-900, #0f172a);
}

.sidebar-drawer__description {
  margin-top: 0.35rem;
  font-size: 0.8rem;
  color: var(--text-500, #64748b);
  line-height: 1.4;
}

.sidebar-drawer__nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem 0.75rem 1.5rem;
}

.sidebar-drawer__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.25rem;
}

.sidebar-drawer__item {
  display: flex;
}

.sidebar-drawer__link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.9rem;
  width: 100%;
  border-radius: 0.75rem;
  text-decoration: none;
  font-size: 0.9rem;
  color: var(--text-800, #1e293b);
  transition: background-color 0.15s ease, transform 0.15s ease;
}

.sidebar-drawer__link:hover {
  background: rgba(96, 165, 250, 0.12);
  transform: translateX(2px);
}

.sidebar-drawer__bullet {
  width: 10px;
  height: 10px;
  border-radius: 9999px;
  background: rgba(37, 99, 235, 0.35);
  flex-shrink: 0;
}

.sidebar-drawer__label {
  flex: 1;
}

@media (max-width: 1023px) {
  .sidebar-drawer {
    display: none;
  }
}

@media (min-width: 1024px) {
  .sidebar--mobile {
    position: static;
    transform: none !important;
  }
}
</style>
