<script setup lang="ts" generic="T extends MenuItem">
import { computed } from 'vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import { Link } from '@inertiajs/vue3'
import { useMenu } from '@/composables/useMenu'

export interface MenuItem { label: string; path?: string; routeName?: string; icon?: string; permission?: string | string[]; children?: T[] }

const props = defineProps<{
  item: MenuItem
  depth?: number
  isSlim?: boolean
}>()

const { activeMenuKeys, getKey } = useMenu([]) // Pass empty array as menu is initialized in Sidebar.vue

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
</script>

<template>
  <li>
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
    <ul v-if="hasChildren" v-show="isExpanded" class="mt-1" :style="{ paddingLeft: (depth*12+12)+'px' }">
      <!-- Pass active state down to children -->
      <SidebarMenuItem v-for="(child, idx) in item.children" :key="idx" :item="child" :depth="depth+1" :is-slim="isSlim" />
    </ul>
  </li>
</template>
