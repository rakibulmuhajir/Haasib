<script setup lang="ts">
import SidebarHeader from './SidebarHeader.vue'
import SidebarMenuContainer from './SidebarMenuContainer.vue'

defineProps<{ title?: string }>()
</script>

<template>
  <aside class="layout-sidebar">
    <SidebarHeader :title="title" />
    <SidebarMenuContainer>
      <slot />
    </SidebarMenuContainer>
  </aside>
  
</template>
