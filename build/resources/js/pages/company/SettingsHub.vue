<script setup lang="ts">
/** Settings: everything the company sets up, grouped. Entries come from SettingsHubController. */
import { Head, Link } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { ChevronRight, Settings } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  sections: Array<{ title: string; items: Array<{ title: string; description: string; href: string }> }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Settings', href: `/${props.company.slug}/setup` },
]
</script>

<template>
  <Head title="Settings" />

  <PageShell title="Settings" :description="company.name" :icon="Settings" :breadcrumbs="breadcrumbs">
    <div class="grid max-w-5xl gap-8">
      <section v-for="section in sections" :key="section.title" class="space-y-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ section.title }}</h2>
        <div class="grid gap-2 sm:grid-cols-2">
          <Link
            v-for="item in section.items"
            :key="item.href"
            :href="item.href"
            class="group flex items-center justify-between gap-3 rounded-lg border p-4 transition-colors hover:border-primary/50 hover:bg-muted/40"
          >
            <span>
              <span class="block font-medium">{{ item.title }}</span>
              <span class="block text-sm text-muted-foreground">{{ item.description }}</span>
            </span>
            <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
          </Link>
        </div>
      </section>
    </div>
  </PageShell>
</template>
