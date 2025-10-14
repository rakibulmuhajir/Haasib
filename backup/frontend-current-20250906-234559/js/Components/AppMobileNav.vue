<script setup lang="ts">
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'

const props = defineProps<{ open: boolean }>()
</script>

<template>
  <div :class="{ block: props.open, hidden: !props.open }" class="sm:hidden">
    <div class="space-y-1 pb-3 pt-2">
      <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">Dashboard</ResponsiveNavLink>
      <ResponsiveNavLink v-if="$page.props.auth.isSuperAdmin" :href="route('admin.dashboard')" :active="route().current('admin.*')">Admin</ResponsiveNavLink>
    </div>

    <!-- Responsive Settings Options -->
    <div class="border-t border-gray-200 pb-1 pt-4">
      <div class="px-4">
        <div class="text-base font-medium text-gray-800 flex items-center gap-1">
          <span>{{ $page.props.auth.user.name }}</span>
          <span v-if="$page.props.auth.isSuperAdmin" class="rounded bg-red-100 px-1 text-xs text-red-600">Superadmin</span>
        </div>
        <div class="text-sm font-medium text-gray-500">{{ $page.props.auth.user.email }}</div>
      </div>

      <!-- Company Switcher (mobile) -->
      <div class="mt-3 px-4">
        <CompanySwitcher />
      </div>

      <div class="mt-3 space-y-1">
        <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
        <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
      </div>
    </div>
  </div>
</template>
