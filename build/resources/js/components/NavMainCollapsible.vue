<script setup lang="ts">
import { ref, watch } from 'vue'
import { ChevronRight } from 'lucide-vue-next'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubItem,
  SidebarMenuSubButton,
} from '@/components/ui/sidebar'
import { urlIsActive, toUrl } from '@/lib/utils'
import type { NavGroup } from '@/types'

const props = defineProps<{
  groups: NavGroup[]
}>()

const page = usePage()

// Track open state for collapsible parents
const openItems = ref<Record<string, boolean>>({})

// Check if any child is active
function hasActiveChild(children: NonNullable<NavGroup['items'][0]['children']>): boolean {
  return children.some(child => child.href && urlIsActive(child.href, page.url))
}

// Initialize open state based on active children
watch(() => page.url, () => {
  props.groups.forEach(group => {
    group.items.forEach(item => {
      if (item.children && item.children.length > 0 && hasActiveChild(item.children)) {
        openItems.value[item.title] = true
      }
    })
  })
}, { immediate: true })
</script>

<template>
  <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
    <SidebarGroupLabel class="text-nav-section-text text-xs uppercase tracking-wider font-medium mb-1">
      {{ group.label }}
    </SidebarGroupLabel>

    <SidebarMenu>
      <template v-for="item in group.items" :key="item.title">
        <!-- Parent with children (collapsible) -->
        <Collapsible
          v-if="item.children && item.children.length > 0"
          v-model:open="openItems[item.title]"
          as-child
        >
          <SidebarMenuItem>
            <CollapsibleTrigger as-child>
              <SidebarMenuButton
                :tooltip="item.title"
                class="text-nav-item-text hover:text-nav-item-text-hover"
                :class="{ 'text-nav-item-text-active font-medium': hasActiveChild(item.children) }"
              >
                <component :is="item.icon" v-if="item.icon" class="size-4" />
                <span>{{ item.title }}</span>
                <ChevronRight
                  class="ml-auto size-4 shrink-0 transition-transform duration-200"
                  :class="{ 'rotate-90': openItems[item.title] }"
                />
              </SidebarMenuButton>
            </CollapsibleTrigger>

            <CollapsibleContent>
              <SidebarMenuSub>
                <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                  <SidebarMenuSubButton
                    as-child
                    :is-active="child.href ? urlIsActive(child.href, page.url) : false"
                  >
                    <Link v-if="child.href && !child.external" :href="child.href">
                      <component :is="child.icon" v-if="child.icon" class="size-4" />
                      <span>{{ child.title }}</span>
                    </Link>
                    <a
                      v-else-if="child.href && child.external"
                      :href="toUrl(child.href)"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <component :is="child.icon" v-if="child.icon" class="size-4" />
                      <span>{{ child.title }}</span>
                    </a>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              </SidebarMenuSub>
            </CollapsibleContent>
          </SidebarMenuItem>
        </Collapsible>

        <!-- Standalone item (no children) -->
        <SidebarMenuItem v-else>
          <SidebarMenuButton
            as-child
            :is-active="item.href ? urlIsActive(item.href, page.url) : false"
            :tooltip="item.title"
          >
            <Link v-if="item.href && !item.external" :href="item.href">
              <component :is="item.icon" v-if="item.icon" class="size-4" />
              <span>{{ item.title }}</span>
            </Link>
            <a
              v-else-if="item.href && item.external"
              :href="toUrl(item.href)"
              target="_blank"
              rel="noopener noreferrer"
            >
              <component :is="item.icon" v-if="item.icon" class="size-4" />
              <span>{{ item.title }}</span>
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </template>
    </SidebarMenu>
  </SidebarGroup>
</template>
