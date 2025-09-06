<script setup lang="ts">
import NavLink from '@/Components/NavLink.vue'
import type { NavLinkItem } from '@/types'
const props = defineProps<{ items: NavLinkItem[] }>()
</script>

<template>
  <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    <template v-for="item in props.items" :key="item.id">
      <NavLink v-if="!item.superadmin || $page.props.auth.isSuperAdmin"
               :href="route(item.route)"
               :active="route().current(item.match)">
        {{ item.label }}
      </NavLink>
    </template>
  </div>
</template>
