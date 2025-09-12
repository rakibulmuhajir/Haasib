<script setup lang="ts">
import SidebarMenuItem from './SidebarMenuItem.vue'
import type { MenuItem } from './SidebarMenuItem.vue'

export interface MenuSection { title?: string; items: MenuItem[] }

const props = defineProps<{ sections: MenuSection[]; iconSet?: 'solid'|'line', isSlim?: boolean }>()
</script>

<template>
  <ul class="layout-menu">
    <li v-for="(section, si) in sections" :key="si" class="layout-root-menuitem">
      <div v-if="section.title" class="layout-menuitem-root-text flex items-center justify-between">
        <span>{{ section.title }}</span>
      </div>
      <ul class="layout-root-submenulist">
        <SidebarMenuItem
          v-for="(item, ii) in section.items"
          :key="ii"
          :item="item"
          :is-slim="isSlim"
        />
      </ul>
    </li>
  </ul>
</template>
