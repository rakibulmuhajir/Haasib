<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import ThemeSwitcher from '@/Components/ThemeSwitcher.vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import SvgIcon from '@/Components/SvgIcon.vue'

import Toolbar from 'primevue/toolbar'
import Toast from 'primevue/toast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const toast = useToast()
const rows = ref([
  { id: 'C-1021', company: 'Blue Ocean LLC', users: 12, status: 'Active' },
  { id: 'C-1022', company: 'Coral Reef Inc.', users: 7, status: 'Invited' },
  { id: 'C-1023', company: 'Kelp Labs', users: 3, status: 'Active' }
])

function notify() {
  toast.add({ severity: 'info', summary: 'Welcome', detail: 'Blue Whale is active', life: 18000 })
}
const shellRef = ref()
</script>

<template>
    <Head title="Dashboard" />

    <LayoutShell ref="shellRef">
      <template #sidebar>
        <Sidebar title="Blue Whale">
          <SidebarMenu iconSet="line" :sections="[
            { title: 'Dashboards', items: [
              { label: 'Banking', path: '/dashboard-banking', icon: 'dashboard', routeName: 'dashboard' },
              { label: 'Analytics', path: '/dashboard-analytics', icon: 'analytics', routeName: 'dashboard', children: [
                { label: 'Trends', path: '/dashboard-analytics/trends', icon: 'analytics' },
                { label: 'Overview', path: '/dashboard-analytics/overview', icon: 'dashboard' }
              ] }
            ]},
            { title: 'Apps', items: [
              { label: 'Companies', path: '/admin/companies', icon: 'companies', routeName: 'admin.companies.index' },
              { label: 'Users', path: '/admin/users', icon: 'users', routeName: 'admin.users.index', children: [
                { label: 'All Users', path: '/admin/users', icon: 'users', routeName: 'admin.users.index' },
                { label: 'Create User', path: '/admin/users/create', icon: 'user-plus', routeName: 'admin.users.create' },
                { label: 'Profile', path: '/profile', icon: 'profile', routeName: 'profile.edit' }
              ] },
              { label: 'Files', path: '/files', icon: 'files', routeName: 'files.index' }
            ]},
            { title: 'System', items: [
              { label: 'Settings', path: '/settings', icon: 'settings', routeName: 'settings' },
              { label: 'Profile', path: '/profile', icon: 'profile', routeName: 'profile.edit' },
              { label: 'Logout', path: '/logout', icon: 'logout', routeName: 'logout' }
            ]}
          ]"/>
        </Sidebar>
      </template>

      <template #topbar>
        <Toolbar class="!bg-transparent !border-0">
          <template #start>
            <div class="flex items-center gap-2">
              <Button text @click="shellRef?.toggleMobile?.()">
                <SvgIcon name="menu" />
              </Button>
              <Button text @click="shellRef?.toggleSlim?.()" class="hidden md:inline-flex">Sidebar</Button>
              <div class="text-sm font-semibold" style="color:var(--p-text-color)">Dashboard</div>
            </div>
          </template>
          <template #end>
            <div class="flex items-center gap-2">
              <ThemeSwitcher />
              <Button label="Show Toast" @click="notify" />
            </div>
          </template>
        </Toolbar>
      </template>

      <!-- content widgets as translucent cards -->
      <div class="mx-auto max-w-7xl space-y-6">
        <Card>
          <template #title>
            <span class="text-[color:var(--p-text-color)]">Welcome</span>
          </template>
          <template #content>
            <p class="mb-3 text-sm" style="color: var(--p-text-muted-color)">You’re logged in. Cards render as overlays on the canvas.</p>
            <Button label="Primary Action" />
          </template>
        </Card>

        <Card>
          <template #title>
            <span class="text-[color:var(--p-text-color)]">Companies</span>
          </template>
          <template #content>
            <DataTable :value="rows" size="small" class="w-full">
              <Column field="id" header="ID" style="width: 120px" />
              <Column field="company" header="Company" />
              <Column field="users" header="Users" style="width: 120px" />
              <Column field="status" header="Status" style="width: 140px" />
            </DataTable>
            <Divider />
            <div class="flex gap-2">
              <Link :href="route('admin.companies.create')">
              <Button label="New Company" severity="primary" />
            </Link>
              <Link :href="route('admin.users.create')">
                <Button label="Invite User" severity="secondary" outlined />
              </Link>
            </div>
          </template>
        </Card>
      </div>

      <Toast position="top-right" />
    </LayoutShell>
</template>
