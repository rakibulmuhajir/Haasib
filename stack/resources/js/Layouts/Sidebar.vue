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

const applyPermissions = (items = []) => items.map(item => ({
  ...item,
  children: (item.children ?? []).filter(child => !child.permission || hasPermission(child.permission))
}))

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

  // Quick links for Phase 2 verification (core auth + company setup flows).
  const phaseTwoChildren = [
    {
      label: 'Setup Wizard',
      path: '/setup/page',
      icon: 'clipboard-check'
    },
    {
      label: 'Auth: Login',
      path: '/login',
      icon: 'sign-in-alt'
    },
    {
      label: 'Auth: Register',
      path: '/register',
      icon: 'user-plus'
    },
    {
      label: 'Auth: Forgot Password',
      path: '/forgot-password',
      icon: 'question-circle'
    },
    {
      label: 'Auth: Verify Email',
      path: '/verify-email',
      icon: 'envelope-open'
    },
    {
      label: 'Companies Directory',
      path: '/companies',
      icon: 'building'
    },
    {
      label: 'Create Company',
      path: '/companies/create',
      icon: 'plus-square'
    }
  ]

  if (companyId) {
    phaseTwoChildren.push(
      {
        label: 'Active Company Modules',
        path: `/companies/${companyId}/modules`,
        icon: 'th-large'
      },
      {
        label: 'Company Audit Center',
        path: `/companies/${companyId}/audit`,
        icon: 'clipboard-list'
      }
    )
  }

  const items = [
    {
      label: 'Phase 2 QA Links',
      icon: 'vials',
      description: 'Direct access to Hybrid Core + RBAC routes outlined in the migration plan.',
      children: phaseTwoChildren
    },
    {
      label: 'Overview & Insights',
      path: '/dashboard',
      icon: 'tachometer-alt',
      description: 'Dashboard landing, KPIs, and announcements to anchor the workspace.'
    },
    {
      label: 'Sales & Receivables',
      icon: 'hand-holding-usd',
      description: 'Customer-facing cash flows: invoices, payments, and receivable health at a glance.',
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
        },
        {
          label: 'Credit Limits',
          path: '/customers/credit-limits',
          icon: 'balance-scale',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Statements & Aging',
          path: '/reports/statements',
          icon: 'file-invoice-dollar',
          permission: 'canAccessInvoicing'
        }
      ]
    },
    {
      label: 'Expense Cycle',
      icon: 'shopping-cart',
      description: 'Vendor management, purchase orders, bills, and expense processing.',
      children: [
        {
          label: 'Vendors',
          path: '/vendors',
          icon: 'building',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Purchase Orders',
          path: '/purchase-orders',
          icon: 'file-invoice',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Bills',
          path: '/bills',
          icon: 'file-invoice-dollar',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Expense Reports',
          path: '/expenses',
          icon: 'receipt',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Expense Categories',
          path: '/expense-categories',
          icon: 'tags',
          permission: 'canAccessInvoicing'
        },
        {
          label: 'Vendor Payments',
          path: '/vendor-payments',
          icon: 'credit-card',
          permission: 'canAccessInvoicing'
        }
      ]
    },
    {
      label: 'Tax Management',
      icon: 'receipt',
      description: 'Tax agencies, rates, settings, and reporting for sales and purchase taxes.',
      children: [
        {
          label: 'Tax Dashboard',
          path: '/tax/dashboard',
          icon: 'tachometer-alt',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Tax Agencies',
          path: '/tax/agencies',
          icon: 'building',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Tax Rates',
          path: '/tax/rates',
          icon: 'percent',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Tax Settings',
          path: '/tax/settings',
          icon: 'cog',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Tax Returns',
          path: '/tax/returns',
          icon: 'file-contract',
          permission: 'canAccessAccounting'
        },
        {
          label: 'Tax Reports',
          path: '/tax/reports/sales-tax',
          icon: 'chart-bar',
          permission: 'canAccessAccounting'
        }
      ]
    },
    {
      label: 'Banking & Cash',
      icon: 'university',
      description: 'Manage reconciliations, statement imports, and bank-side reporting.',
      children: [
        {
          label: 'Reconciliation Workspace',
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
        },
        {
          label: 'Bank Accounts',
          path: '/bank-accounts',
          icon: 'university',
          permission: 'canAccessAccounting'
        }
      ]
    },
    {
      label: 'Accounting Operations',
      icon: 'calculator',
      description: 'Journal flows, ledgers, and trial balances accountants maintain daily.',
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
        },
        {
          label: 'Adjustments & Batches',
          path: '/journal-entries/batches',
          icon: 'layer-group',
          permission: 'canAccessAccounting'
        }
      ]
    },
    {
      label: 'Period Close & Compliance',
      icon: 'calendar-check',
      description: 'Monthly and annual close, audit evidence, and reopen workflows.',
      children: [
        {
          label: 'Period Close Checklist',
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
          label: 'Policy & Compliance',
          path: '/compliance',
          icon: 'shield-alt',
          permission: 'canAccessPeriodClose'
        }
      ]
    },
    {
      label: 'Reporting & Analytics',
      icon: 'chart-line',
      description: 'Dashboards, schedules, statements, and analytics tooling.',
      children: [
        {
          label: 'Reporting Dashboard',
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
          label: 'Reporting Schedules',
          path: '/reports/schedules',
          icon: 'calendar',
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
      label: 'Organization & Administration',
      icon: 'building',
      description: 'Manage companies, switch context, and administer users.',
      children: organizationChildren
    },
    {
      label: 'User & Settings',
      icon: 'user-cog',
      description: 'Global configuration for the workspace.',
      children: [
        {
          label: 'System Settings',
          path: '/settings',
          icon: 'cog',
          permission: 'canViewSettings'
        }
      ]
    }
  ]

  return applyPermissions(items)
})

const hoveredItem = ref(null)

const drawerChildren = computed(() => hoveredItem.value?.children ?? [])
const drawerTitle = computed(() => hoveredItem.value?.label ?? '')
const drawerDescription = computed(() => hoveredItem.value?.description ?? '')

const footerNavigation = computed(() => applyPermissions([
  {
    label: 'Lifecycle Utilities',
    icon: 'life-ring',
    description: 'Onboarding, support resources, and product highlights.',
    children: [
      {
        label: 'Getting Started',
        path: '/welcome',
        icon: 'rocket'
      },
      {
        label: 'Help & Resources',
        path: '/help',
        icon: 'question-circle'
      },
      {
        label: 'What’s New',
        path: '/whats-new',
        icon: 'star'
      }
    ]
  }
]))

const showDrawer = computed(() => {
  if (isMobile.value) return false
  return Boolean(hoveredItem.value)
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

      <div v-if="footerNavigation.length" class="sidebar-footer">
        <nav role="menu" aria-label="Support navigation">
          <ul class="sidebar-menu">
            <SidebarMenuItem
              v-for="item in footerNavigation"
              :key="item.label"
              :item="item"
              :depth="0"
              @hover="handleItemHover"
            />
          </ul>
        </nav>
      </div>
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
          <template v-if="drawerChildren.length">
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
          </template>
          <p v-else class="sidebar-drawer__empty">
            No quick links available yet.
          </p>
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

.sidebar-drawer__empty {
  font-size: 0.85rem;
  color: var(--text-500, #64748b);
  padding: 1rem 1.25rem;
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
