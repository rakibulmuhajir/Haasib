<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link } from '@inertiajs/vue3'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'   // ← add this
import CommandPalette from '@/Components/CommandPalette.vue'
import Tooltip from '@/Components/Tooltip.vue'

const showingNavigationDropdown = ref(false)

// Collapsible left sidebar (icons when collapsed; icons+text when expanded)
const sidebarExpanded = ref(false)
try { sidebarExpanded.value = localStorage.getItem('ui.sidebar.expanded') === '1' } catch {}
watch(sidebarExpanded, (v) => { try { localStorage.setItem('ui.sidebar.expanded', v ? '1' : '0') } catch {} })
const contentPadClass = computed(() => (sidebarExpanded.value ? 'md:pl-56' : 'md:pl-16'))

function toggleSidebar() { sidebarExpanded.value = !sidebarExpanded.value }

// Keyboard shortcut: Ctrl+B toggles sidebar
function handleKeydown(e) {
  const isMac = navigator.platform.toUpperCase().includes('MAC')
  const ctrlOrMeta = isMac ? e.metaKey : e.ctrlKey
  if (ctrlOrMeta && (e.key === 'b' || e.key === 'B')) {
    e.preventDefault()
    toggleSidebar()
  }
}
onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink v-if="$page.props.auth.isSuperAdmin" :href="route('admin.dashboard')" :active="route().current('admin.*')">
                                    Admin
                                </NavLink>
                            </div>
                        </div>

                        <!-- Right side (desktop) -->
                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
                            <!-- Company Switcher (global tenant context) -->
                            <CompanySwitcher />

                            <!-- Settings Dropdown -->
                            <div class="relative ms-1">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <span
                                                    v-if="$page.props.auth.isSuperAdmin"
                                                    class="ms-2 rounded bg-red-100 px-1 text-xs text-red-600"
                                                >Superadmin</span>
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
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="$page.props.auth.isSuperAdmin" :href="route('admin.dashboard')" :active="route().current('admin.*')">
                            Admin
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800 flex items-center gap-1">
                                <span>{{ $page.props.auth.user.name }}</span>
                                <span
                                    v-if="$page.props.auth.isSuperAdmin"
                                    class="rounded bg-red-100 px-1 text-xs text-red-600"
                                >Superadmin</span>
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
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
            </nav>

            <!-- Left sidebar (desktop) -->
            <aside
                class="hidden md:flex fixed top-16 bottom-0 left-0 border-r border-gray-200 bg-white transition-[width] duration-200"
                :class="sidebarExpanded ? 'w-56' : 'w-16'"
            >
                <div class="flex w-full flex-col">
                    <div class="flex items-center justify-between px-2 py-2 border-b border-gray-200">
                        <button @click="toggleSidebar" class="rounded p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                <path fill-rule="evenodd" d="M3 4.75A.75.75 0 0 1 3.75 4h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 4.75Zm0 10.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3 10a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div v-if="sidebarExpanded" class="text-xs text-gray-500 px-2">Menu</div>
                    </div>
                    <nav class="flex-1 overflow-y-auto py-2">
                        <div v-if="sidebarExpanded" class="px-3 py-1 text-[11px] uppercase tracking-wide text-gray-500">General</div>
                        <!-- Dashboard -->
                        <Link :href="route('dashboard')" class="group mx-1 my-0.5 flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-gray-100"
                              :class="route().current('dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-700'">
                            <Tooltip v-if="!sidebarExpanded" text="Dashboard">
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                  <path d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 0 1-1.06 1.06L12 5.56 3.84 13.59a.75.75 0 1 1-1.06-1.06l8.69-8.69Z" />
                                  <path d="M12 6.56 4.5 14.06V20.25A1.75 1.75 0 0 0 6.25 22h3.5v-4.25a1.25 1.25 0 0 1 1.25-1.25h2a1.25 1.25 0 0 1 1.25 1.25V22h3.5A1.75 1.75 0 0 0 19.5 20.25V14.06L12 6.56Z" />
                              </svg>
                            </Tooltip>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 0 1-1.06 1.06L12 5.56 3.84 13.59a.75.75 0 1 1-1.06-1.06l8.69-8.69Z" />
                                <path d="M12 6.56 4.5 14.06V20.25A1.75 1.75 0 0 0 6.25 22h3.5v-4.25a1.25 1.25 0 0 1 1.25-1.25h2a1.25 1.25 0 0 1 1.25 1.25V22h3.5A1.75 1.75 0 0 0 19.5 20.25V14.06L12 6.56Z" />
                            </svg>
                            <span v-if="sidebarExpanded">Dashboard</span>
                        </Link>

                        <!-- Admin group (superadmin only) -->
                        <template v-if="$page.props.auth.isSuperAdmin">
                            <div v-if="sidebarExpanded" class="px-3 pt-3 pb-1 text-[11px] uppercase tracking-wide text-gray-500">Admin</div>
                            <Link :href="route('admin.dashboard')" class="group mx-1 my-0.5 flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-gray-100"
                                  :class="route().current('admin.dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-700'">
                                <Tooltip v-if="!sidebarExpanded" text="Admin">
                                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                      <path fill-rule="evenodd" d="M12 2.25c-.41 0-.82.1-1.19.3l-7 3.75A2.25 2.25 0 0 0 2.25 8v4a9.75 9.75 0 0 0 6.37 9.16l2.07.79c.82.31 1.72.31 2.54 0l2.07-.79A9.75 9.75 0 0 0 21.75 12V8a2.25 2.25 0 0 0-1.56-1.95l-7-3.75c-.37-.2-.78-.3-1.19-.3Z" clip-rule="evenodd" />
                                  </svg>
                                </Tooltip>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M12 2.25c-.41 0-.82.1-1.19.3l-7 3.75A2.25 2.25 0 0 0 2.25 8v4a9.75 9.75 0 0 0 6.37 9.16l2.07.79c.82.31 1.72.31 2.54 0l2.07-.79A9.75 9.75 0 0 0 21.75 12V8a2.25 2.25 0 0 0-1.56-1.95l-7-3.75c-.37-.2-.78-.3-1.19-.3Z" clip-rule="evenodd" />
                                </svg>
                                <span v-if="sidebarExpanded">Admin</span>
                            </Link>
                            <Link :href="route('admin.companies.index')" class="group mx-1 my-0.5 flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-gray-100"
                                  :class="route().current('admin.companies.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700'">
                                <Tooltip v-if="!sidebarExpanded" text="Companies">
                                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path d="M3.75 3A.75.75 0 0 0 3 3.75v16.5c0 .414.336.75.75.75H9v-3.75A2.25 2.25 0 0 1 11.25 15h1.5A2.25 2.25 0 0 1 15 17.25V21h5.25a.75.75 0 0 0 .75-.75V8.56a.75.75 0 0 0-.318-.615l-6-4.125A.75.75 0 0 0 14.25 3H3.75Z" />
                                  </svg>
                                </Tooltip>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                  <path d="M3.75 3A.75.75 0 0 0 3 3.75v16.5c0 .414.336.75.75.75H9v-3.75A2.25 2.25 0 0 1 11.25 15h1.5A2.25 2.25 0 0 1 15 17.25V21h5.25a.75.75 0 0 0 .75-.75V8.56a.75.75 0 0 0-.318-.615l-6-4.125A.75.75 0 0 0 14.25 3H3.75Z" />
                                </svg>
                                <span v-if="sidebarExpanded">Companies</span>
                            </Link>
                            <Link :href="route('admin.users.index')" class="group mx-1 my-0.5 flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-gray-100"
                                  :class="route().current('admin.users.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700'">
                                <Tooltip v-if="!sidebarExpanded" text="Users">
                                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path d="M16.5 7.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    <path d="M3.75 20.25a8.25 8.25 0 1 1 16.5 0 .75.75 0 0 1-.75.75H4.5a.75.75 0 0 1-.75-.75Z" />
                                  </svg>
                                </Tooltip>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                  <path d="M16.5 7.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                  <path d="M3.75 20.25a8.25 8.25 0 1 1 16.5 0 .75.75 0 0 1-.75.75H4.5a.75.75 0 0 1-.75-.75Z" />
                                </svg>
                                <span v-if="sidebarExpanded">Users</span>
                            </Link>
                        </template>
                    </nav>
                </div>
            </aside>

            <div :class="contentPadClass">
                <!-- Page Heading -->
                <header class="bg-white shadow" v-if="$slots.header">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <!-- Page Content -->
                <main>
                    <slot />
                </main>
            </div>
        </div>
        <CommandPalette />
    </div>
</template>
