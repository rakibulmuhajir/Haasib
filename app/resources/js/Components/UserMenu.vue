<script setup>
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import ThemeSwitcher from '@/Components/ThemeSwitcher.vue'
</script>

<template>
  <div class="relative ms-1">
    <Dropdown align="right" width="48">
      <template #trigger>
        <span class="inline-flex rounded-md">
          <button
            type="button"
            class="inline-flex items-center rounded-md border border-transparent bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium leading-4 text-gray-500 dark:text-gray-400 transition duration-150 ease-in-out hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none"
          >
            {{ $page.props.auth.user.name }}
            <span v-if="$page.props.auth.isSuperAdmin" class="ms-2 rounded bg-red-100 px-1 text-xs text-red-600">Superadmin</span>
            <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </span>
      </template>

      <template #content>
        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
      </template>
    </Dropdown>
    <ThemeSwitcher class="ms-3" />
  </div>
</template>
