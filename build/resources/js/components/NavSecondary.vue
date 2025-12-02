<script setup lang="ts">
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { urlIsActive } from '@/lib/utils'
import type { NavItem } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'

defineProps<{
  items: NavItem[]
}>()

const page = usePage()
</script>

<template>
  <SidebarGroup class="mt-auto">
    <SidebarGroupContent>
      <SidebarMenu>
        <SidebarMenuItem v-for="item in items" :key="item.title">
          <SidebarMenuButton
            as-child
            :is-active="urlIsActive(item.href, page.url)"
            :tooltip="item.title"
            size="sm"
          >
            <Link :href="item.href">
              <component :is="item.icon" v-if="item.icon" />
              <span>{{ item.title }}</span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarGroupContent>
  </SidebarGroup>
</template>
