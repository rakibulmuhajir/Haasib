<script setup lang="ts">
/**
 * On a settings page that belongs to a group of separate pages (Station equipment, Accounting,
 * Payroll), the group's pages as a row of tabs -- the Settings menu lists only the groups, so
 * this is how the rest of a group is reached. Reads auth.settingsMenu (App\Services\SettingsMenu).
 */
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

type Section = { title: string; href: string | null; items: Array<{ title: string; href: string }> }

const page = usePage()
const path = (href: string) => href.split(/[?#]/)[0].replace(/\/$/, '')
const current = computed(() => path(page.url))
const section = computed(() => {
  const menu = ((page.props.auth as { settingsMenu?: Section[] | null } | undefined)?.settingsMenu ?? []) as Section[]
  // Only groups of separate pages; a one-page group (Company, Station settings) has its own tabs.
  return menu.find((s) => !s.href && s.items.length > 1 && s.items.some((i) => path(i.href) === current.value)) ?? null
})
</script>

<template>
  <nav v-if="section" :aria-label="section.title" class="flex flex-wrap items-center gap-x-1 gap-y-1 border-b border-rule-default text-sm">
    <span class="mr-2 text-xs text-muted-foreground">{{ section.title }}</span>
    <Link
      v-for="item in section.items"
      :key="item.href"
      :href="item.href"
      class="-mb-px border-b-2 px-2 py-1.5"
      :class="path(item.href) === current ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
    >{{ item.title }}</Link>
  </nav>
</template>
