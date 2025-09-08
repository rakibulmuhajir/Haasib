<script setup lang="ts">
import SvgIcon from '@/Components/SvgIcon.vue'
import { computed, reactive, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

export interface MenuItem { label: string; path?: string; icon?: string; routeName?: string }
export interface MenuSection { title?: string; items: MenuItem[] }

const props = defineProps<{ sections: MenuSection[]; iconSet?: 'solid'|'line' }>()
const page = usePage()
const here = computed(() => (page?.url as string) || (typeof window !== 'undefined' ? window.location.pathname : '/'))

// Collapsible state per section index
const open = reactive<Record<number, boolean>>({})
onMounted(() => {
  props.sections.forEach((_, i) => {
    const key = `sidebar.open.${i}`
    open[i] = localStorage.getItem(key) !== '0'
  })
})
watch(open, (val) => {
  Object.entries(val).forEach(([k, v]) => localStorage.setItem(`sidebar.open.${k}`, v ? '1' : '0'))
}, { deep: true })

function isActive(item: MenuItem): boolean {
  if (item.routeName && (window as any).route) {
    try { return (window as any).route().current(item.routeName) } catch { /* ignore */ }
  }
  if (item.path) return here.value === item.path || here.value.startsWith(item.path + '/')
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
        <li v-for="(item, ii) in section.items" :key="ii">
          <a :href="item.path || '#'" class="inline-flex items-center gap-2 px-2 py-1 rounded-full"
             :class="{ 'router-link-active active-route': isActive(item) }"
             style="color: var(--p-text-color)"
          >
            <SvgIcon :name="item.icon || 'placeholder'" :set="iconSet || 'line'" class="opacity-90" />
            <span class="layout-menuitem-text text-sm">{{ item.label }}</span>
          </a>
        </li>
      </ul>
    </li>
  </ul>
</template>
