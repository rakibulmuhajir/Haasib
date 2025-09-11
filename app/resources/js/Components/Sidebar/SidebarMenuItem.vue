<script setup lang="ts">
import { computed, reactive, onMounted, watch } from 'vue'
import SvgIcon from '@/Components/SvgIcon.vue'

export interface MenuItem { label: string; path?: string; routeName?: string; children?: MenuItem[] }

const props = defineProps<{
  item: MenuItem
  depth?: number
}>()

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

const here = computed(() => (typeof window !== 'undefined' ? window.location.pathname : '/'))

function isActiveSelf(): boolean {
  const r = routeFunc()
  if (props.item.routeName && r) {
    try { return r().current(props.item.routeName) } catch { /* ignore */ }
  }
  if (props.item.path) return here.value === props.item.path || here.value.startsWith(props.item.path + '/')
  return false
}

function isActiveExact(): boolean {
  const r = routeFunc()
  if (props.item.routeName && r) {
    try { return r().current(props.item.routeName) } catch { /* ignore */ }
  }
  if (props.item.path) return here.value === props.item.path
  return false
}

const hasChildren = computed(() => (props.item.children && props.item.children.length > 0) || false)
const hasActiveChild = computed(() => {
  if (!props.item.children) return false
  return props.item.children.some(child => {
    const r = routeFunc()
    if (child.routeName && r) {
      try { return r().current(child.routeName) } catch { /* ignore */ }
    }
    if (child.path) return here.value === child.path || here.value.startsWith(child.path + '/')
    return false
  })
})

// open state persistence per item key (label+routeName+path)
const key = computed(() => `sidebar.item.open.${props.item.label}.${props.item.routeName || ''}.${props.item.path || ''}`)
const open = reactive<{ v: boolean }>({ v: true })

onMounted(() => {
  const saved = localStorage.getItem(key.value)
  if (saved !== null) open.v = saved === '1'
  // auto-open if any descendant is active
  if (hasActiveChild.value) open.v = true
})

watch(() => open.v, v => { try { localStorage.setItem(key.value, v ? '1' : '0') } catch {} })

</script>

<template>
  <li>
    <a :href="buildHref()"
       class="flex w-full items-center gap-2 px-2 py-1 rounded-full"
       :class="{ 'router-link-active active-route': isActiveExact() && !hasActiveChild }"
       style="color: var(--p-text-color)"
    >
      <span class="layout-menuitem-text text-sm">{{ item.label }}</span>
      <button v-if="hasChildren" type="button" class="ms-auto inline-flex items-center" @click.prevent="open.v = !open.v">
        <SvgIcon :name="open.v ? 'chevron-down' : 'chevron-right'" set="line" class="w-3 h-3" />
      </button>
    </a>
    <ul v-if="hasChildren" v-show="open.v" class="mt-1" :style="{ paddingLeft: (depth*12+12)+'px' }">
      <SidebarMenuItem v-for="(child, idx) in item.children" :key="idx" :item="child" :depth="depth+1" />
    </ul>
  </li>
</template>
