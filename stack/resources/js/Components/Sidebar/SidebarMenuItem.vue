<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useCompanyContext } from '@/composables/useCompanyContext'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    default: 0
  },
  isSlim: {
    type: Boolean,
    default: false
  }
})

const { hasPermission } = useCompanyContext()
const page = usePage()

const depth = computed(() => props.depth ?? 0)

function buildHref() {
  return props.item.path || '#'
}

const hasChildren = computed(() => (props.item.children && props.item.children.length > 0) || false)

// Check if current route matches this item
const isActive = computed(() => {
  const currentPath = page.props.url || window.location.pathname
  return currentPath.startsWith(props.item.path || '#')
})

// Check if user has permission to view this item
const hasPermissionToShow = computed(() => {
  if (!props.item.permission) return true
  return hasPermission(props.item.permission)
})

// Filter children based on permissions
const visibleChildren = computed(() => {
  if (!props.item.children) return []
  return props.item.children.filter(child => {
    if (!child.permission) return true
    return hasPermission(child.permission)
  })
})
</script>

<template>
  <li v-if="hasPermissionToShow">
    <Link
       :href="buildHref()"
       class="flex w-full items-center gap-3 px-3 py-2 rounded-lg transition-colors"
       :class="{
         'bg-primary text-primary-contrast': isActive && !hasChildren,
         'text-primary-600 dark:text-primary-400': isActive && hasChildren,
         'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800': !isActive
       }"
       v-tooltip.right="{ value: isSlim ? item.label : '', disabled: !isSlim }"
    >
      <i v-if="item.icon" :class="`fas fa-${item.icon}`" class="w-4 h-4" />
      <span v-if="!isSlim" class="layout-menuitem-text text-sm flex-grow">{{ item.label }}</span>
      <i v-if="hasChildren && !isSlim" 
         :class="isActive ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" 
         class="w-3 h-3 ms-auto opacity-60" />
    </Link>
    <ul v-if="hasChildren && visibleChildren.length > 0" v-show="isActive" class="mt-1" :style="{ paddingLeft: (depth*12+12)+'px' }">
      <!-- Pass active state down to children -->
      <SidebarMenuItem 
        v-for="(child, idx) in visibleChildren" 
        :key="idx" 
        :item="child" 
        :depth="depth+1" 
        :is-slim="isSlim" 
      />
    </ul>
  </li>
</template>