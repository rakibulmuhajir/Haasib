<script setup lang="ts" generic="T extends MenuItem">
import { computed } from 'vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import { Link } from '@inertiajs/vue3'
import { useMenu } from '@/composables/useMenu'
import { usePermissions } from '@/composables/usePermissions'

export interface MenuItem { label: string; path?: string; routeName?: string; icon?: string; permission?: string | string[]; children?: T[] }

const props = defineProps<{
  item: MenuItem
  depth?: number
  isSlim?: boolean
}>()

const { activeMenuKeys, getKey } = useMenu([]) // Pass empty array as menu is initialized in Sidebar.vue
const { has, hasSystemPermission, isSuperAdmin } = usePermissions()

const depth = computed(() => props.depth ?? 0)

function routeFunc(): any | null {
  const r = (window as any).route
  return typeof r === 'function' ? r : null
}

function buildHref(): string {
  const r = routeFunc()
  if (props.item.routeName && r) {
    try { return r(props.item.routeName) as string } catch { /* ignore */ }
  }
  return props.item.path || '#'
}

const hasChildren = computed(() => (props.item.children && props.item.children.length > 0) || false)

const itemKey = computed(() => getKey(props.item))
const isActive = computed(() => activeMenuKeys.value.has(itemKey.value))
const isExpanded = computed(() => activeMenuKeys.value.has(itemKey.value))

// Check if user has permission to view this item
const hasPermission = computed(() => {
  if (!props.item.permission) return true
  
  // Super admins can access everything
  if (isSuperAdmin.value) return true
  
  const permissions = Array.isArray(props.item.permission) 
    ? props.item.permission 
    : [props.item.permission]
  
  // Check system permissions first (for super admin)
  for (const permission of permissions) {
    if (permission.startsWith('system.') && hasSystemPermission(permission)) {
      return true
    }
  }
  
  // Check company permissions
  for (const permission of permissions) {
    if (!permission.startsWith('system.') && has(permission)) {
      return true
    }
  }
  
  return false
})

// Filter children based on permissions
const visibleChildren = computed(() => {
  if (!props.item.children) return []
  return props.item.children.filter(child => {
    if (!child.permission) return true
    
    // Super admins can access everything
    if (isSuperAdmin.value) return true
    
    const permissions = Array.isArray(child.permission) 
      ? child.permission 
      : [child.permission]
    
    // Check system permissions first
    for (const permission of permissions) {
      if (permission.startsWith('system.') && hasSystemPermission(permission)) {
        return true
      }
    }
    
    // Check company permissions
    for (const permission of permissions) {
      if (!permission.startsWith('system.') && has(permission)) {
        return true
      }
    }
    
    return false
  })
})
</script>

<template>
  <li v-if="hasPermission">
    <Link
       :href="buildHref()"
       class="flex w-full items-center gap-3 px-3 py-2 rounded-lg"
       :class="{
         'router-link-active active-route': isActive && !hasChildren,
         'active-parent': isActive && hasChildren
       }"
       style="color: var(--p-text-color)"
       v-tooltip.right="{ value: isSlim ? item.label : '', disabled: !isSlim }"
    >
      <SvgIcon v-if="item.icon" :name="item.icon" set="line" class="w-4 h-4" :monochrome="true" />
      <span v-if="!isSlim" class="layout-menuitem-text text-sm flex-grow">{{ item.label }}</span>
      <button v-if="hasChildren && !isSlim" type="button" class="ms-auto inline-flex items-center">
        <SvgIcon :name="isExpanded ? 'chevron-down' : 'chevron-right'" set="line" class="w-3 h-3" />
      </button>
    </Link>
    <ul v-if="hasChildren && visibleChildren.length > 0" v-show="isExpanded" class="mt-1" :style="{ paddingLeft: (depth*12+12)+'px' }">
      <!-- Pass active state down to children -->
      <SidebarMenuItem v-for="(child, idx) in visibleChildren" :key="idx" :item="child" :depth="depth+1" :is-slim="isSlim" />
    </ul>
  </li>
</template>
