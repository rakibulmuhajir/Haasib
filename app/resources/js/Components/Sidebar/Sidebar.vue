<script setup lang="ts">
import SidebarHeader from './SidebarHeader.vue'
import SidebarMenuContainer from './SidebarMenuContainer.vue'
import SidebarMenu from './SidebarMenu.vue'
import { Link } from '@inertiajs/vue3'
import SvgIcon from '@/Components/SvgIcon.vue'
import { useSidebar } from '@/composables/useSidebar'
import { useMenu } from '@/composables/useMenu'

defineProps<{ title?: string }>()

const { isSlim } = useSidebar()

const menuSections = [
  {
    title: 'Ledger',
    items: [
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
    title: 'Admin',
    items: [
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
          { label: 'All Users', path: '/admin/users', routeName: 'admin.users.index', permission: 'admin.users.view' },
          { label: 'Create User', path: '/admin/users/create', routeName: 'admin.users.create', permission: 'admin.users.create' }
        ]
      }
    ]
  }
]

// Initialize the menu composable with the menu data.
useMenu(menuSections)
</script>

<template>
  <aside class="layout-sidebar" :class="{ 'sidebar-slim': isSlim }">
    <SidebarHeader :title="isSlim ? '' : title" />
    <SidebarMenuContainer>
      <SidebarMenu iconSet="line" :sections="menuSections" :is-slim="isSlim" />
      <slot />
    </SidebarMenuContainer>
    <div class="sidebar-footer">
      <button @click="isSlim = !isSlim" class="sidebar-toggle-button" v-tooltip.right="isSlim ? 'Expand Sidebar' : 'Collapse Sidebar'">
        <span>
          <SvgIcon :name="isSlim ? 'arrow-right-from-bracket' : 'arrow-left-from-bracket'" set="line" class="w-5 h-5" />
        </span>
      </button>
    </div>
  </aside>

</template>
