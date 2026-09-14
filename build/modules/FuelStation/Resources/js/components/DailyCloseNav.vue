<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

const props = defineProps<{ company: { slug: string }; history?: boolean }>()
const page = usePage()
const allowed = computed<string[]>(() => (page.props.auth as any)?.fuelNavigation?.allowed ?? [])
const tabs = computed(() => [
  { title: 'Current Close', href: `/${props.company.slug}/fuel/daily-close`, active: !props.history, permission: 'dailyClose' },
  { title: 'History', href: `/${props.company.slug}/fuel/daily-close/history`, active: !!props.history, permission: 'closeHistory' },
].filter(tab => allowed.value.includes(tab.permission)))
</script>

<template>
  <nav aria-label="Daily close navigation" class="flex gap-2 border-b border-rule-subtle pb-2">
    <Button v-for="tab in tabs" :key="tab.title" :variant="tab.active ? 'secondary' : 'ghost'" as-child>
      <Link :href="tab.href" :aria-current="tab.active ? 'page' : undefined">{{ tab.title }}</Link>
    </Button>
  </nav>
</template>
