<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'
import SidebarNavItem from '@/Components/SidebarNavItem.vue'
import { navLinks } from '@/nav/links'

const props = defineProps<{ expanded: boolean }>()
const emit = defineEmits<{ (e: 'toggle'): void }>()
</script>

<template>
  <aside
    class="hidden md:flex fixed top-16 bottom-0 left-0 border-r border-gray-200 bg-white transition-[width] duration-200"
    :class="props.expanded ? 'w-56' : 'w-16'"
  >
    <div class="flex w-full flex-col">
      <div class="flex items-center justify-between px-2 py-2 border-b border-gray-200">
        <button @click="emit('toggle')" class="rounded p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path fill-rule="evenodd" d="M3 4.75A.75.75 0 0 1 3.75 4h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 4.75Zm0 10.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3 10a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
          </svg>
        </button>
        <div v-if="props.expanded" class="text-xs text-gray-500 px-2">Menu</div>
      </div>

      <nav class="flex-1 overflow-y-auto py-2">
        <div v-if="props.expanded" class="px-3 py-1 text-[11px] uppercase tracking-wide text-gray-500">General</div>

        <SidebarNavItem v-for="item in navLinks.filter(l => l.group==='general' && l.includeInSidebar)"
                        :key="item.id"
                        :href="route(item.route)"
                        :active="route().current(item.match)"
                        :expanded="props.expanded"
                        :tooltip="item.label">
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
              <path d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 0 1-1.06 1.06L12 5.56 3.84 13.59a.75.75 0 1 1-1.06-1.06l8.69-8.69Z" />
              <path d="M12 6.56 4.5 14.06V20.25A1.75 1.75 0 0 0 6.25 22h3.5v-4.25a1.25 1.25 0 0 1 1.25-1.25h2a1.25 1.25 0 0 1 1.25 1.25V22h3.5A1.75 1.75 0 0 0 19.5 20.25V14.06L12 6.56Z" />
            </svg>
          </template>
          {{ item.label }}
        </SidebarNavItem>

        <template v-if="$page.props.auth.isSuperAdmin">
          <div v-if="props.expanded" class="px-3 pt-3 pb-1 text-[11px] uppercase tracking-wide text-gray-500">Admin</div>

          <SidebarNavItem v-for="item in navLinks.filter(l => l.group==='admin' && l.includeInSidebar)"
                          :key="item.id"
                          :href="route(item.route)"
                          :active="route().current(item.match)"
                          :expanded="props.expanded"
                          :tooltip="item.label">
            <template #icon>
              <svg v-if="item.id==='admin.dashboard'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M12 2.25c-.41 0-.82.1-1.19.3l-7 3.75A2.25 2.25 0 0 0 2.25 8v4a9.75 9.75 0 0 0 6.37 9.16l2.07.79c.82.31 1.72.31 2.54 0l2.07-.79A9.75 9.75 0 0 0 21.75 12V8a2.25 2.25 0 0 0-1.56-1.95l-7-3.75c-.37-.2-.78-.3-1.19-.3Z" clip-rule="evenodd" /></svg>
              <svg v-else-if="item.id==='admin.companies.index'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M3.75 3A.75.75 0 0 0 3 3.75v16.5c0 .414.336.75.75.75H9v-3.75A2.25 2.25 0 0 1 11.25 15h1.5A2.25 2.25 0 0 1 15 17.25V21h5.25a.75.75 0 0 0 .75-.75V8.56a.75.75 0 0 0-.318-.615l-6-4.125A.75.75 0 0 0 14.25 3H3.75Z" /></svg>
              <svg v-else-if="item.id==='admin.users.index'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M16.5 7.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" /><path d="M3.75 20.25a8.25 8.25 0 1 1 16.5 0 .75.75 0 0 1-.75.75H4.5a.75.75 0 0 1-.75-.75Z" /></svg>
            </template>
            {{ item.label }}
          </SidebarNavItem>
        </template>
      </nav>
    </div>
  </aside>
</template>
