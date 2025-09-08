<script setup lang="ts">
import { computed, reactive, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SidebarMenuItem, { type MenuItem } from './SidebarMenuItem.vue'

export interface MenuSection { title?: string; items: MenuItem[] }

const props = defineProps<{ sections: MenuSection[]; iconSet?: 'solid'|'line' }>()
const page = usePage()
const here = computed(() => (page?.url as string) || (typeof window !== 'undefined' ? window.location.pathname : '/'))

// Collapsible state per section index
const open = reactive<Record<number, boolean>>({})
onMounted(() => {
  props.sections.forEach((section, i) => {
    const key = `sidebar.open.${i}`
    // auto-open if any child item is active
    const active = section.items?.some((it) => isActiveDeep(it))
    const saved = localStorage.getItem(key)
    open[i] = saved !== null ? saved === '1' : !!active
  })
})
watch(open, (val) => {
  Object.entries(val).forEach(([k, v]) => localStorage.setItem(`sidebar.open.${k}`, v ? '1' : '0'))
}, { deep: true })

function isActiveDeep(item: MenuItem): boolean {
  const r = (window as any).route
  if (item.routeName && typeof r === 'function') {
    try { if (r().current(item.routeName)) return true } catch {}
  }
  if (item.path && (here.value === item.path || here.value.startsWith(item.path + '/'))) return true
  if (item.children) return item.children.some(isActiveDeep)
  return false
}
</script>

<template>
  <ul class="layout-menu">
    <li v-for="(section, si) in sections" :key="si" class="layout-root-menuitem">
      <div v-if="section.title" class="layout-menuitem-root-text flex items-center justify-between">
        <span>{{ section.title }}</span>
        <button class="inline-flex items-center" @click="open[si] = !open[si]" aria-label="Toggle">
          <SvgIcon :name="open[si] ? 'chevron-down' : 'chevron-right'" :set="iconSet || 'line'" />
        </button>
      </div>
      <ul class="layout-root-submenulist" v-show="open[si]">
        <SidebarMenuItem v-for="(item, ii) in section.items" :key="ii" :item="item" :icon-set="iconSet || 'line'" />
      </ul>
    </li>
  </ul>
</template>
