<script setup lang="ts">
import SidebarHeader from './SidebarHeader.vue'
import SidebarMenuContainer from './SidebarMenuContainer.vue'
import SidebarMenu from './SidebarMenu.vue'
import { Link } from '@inertiajs/vue3'

defineProps<{ title?: string }>()

const menuSections = [
  {
    title: 'Admin',
    items: [
      { 
        label: 'Companies', 
        path: '/admin/companies', 
        icon: 'building', 
        routeName: 'admin.companies.index',
        children: [
          { label: 'All Companies', path: '/admin/companies', icon: 'list', routeName: 'admin.companies.index' },
          { label: 'Create Company', path: '/admin/companies/create', icon: 'plus', routeName: 'admin.companies.create' }
        ] 
      },
      { 
        label: 'Users', 
        path: '/admin/users', 
        icon: 'users', 
        routeName: 'admin.users.index',
        children: [
          { label: 'All Users', path: '/admin/users', icon: 'list', routeName: 'admin.users.index' },
          { label: 'Create User', path: '/admin/users/create', icon: 'user-plus', routeName: 'admin.users.create' }
        ] 
      }
    ]
  }
]
</script>

<template>
  <aside class="layout-sidebar">
    <SidebarHeader :title="title" />
    <SidebarMenuContainer>
      <SidebarMenu iconSet="line" :sections="menuSections" />
      <slot />
    </SidebarMenuContainer>
  </aside>
  
</template>
