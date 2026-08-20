<script setup lang="ts">
/**
 * DashboardTabs — a tab is a named layout, not a component or a route.
 *
 * Rendered in the ledger grammar: a ruled strip, not filled pills (law 1 —
 * rules, not elevation). The active tab carries a heavier bottom rule and the
 * primary ink; everything else sits quiet in secondary ink until touched.
 *
 * Switching a tab asks the server for the `dashboard` prop only — an Inertia
 * partial reload, so a twelve-widget dashboard on four tabs never re-fetches
 * more than the tab being looked at.
 *
 * A single tab is not a choice, so it renders no strip at all.
 */
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    tabs: Array<{ key: string; label: string }>
    activeTab: string
}>()

const switchTab = (tab: string) => {
    if (tab === props.activeTab) return
    router.get(
        window.location.pathname,
        { tab },
        { only: ['dashboard'], preserveState: true, preserveScroll: true },
    )
}
</script>

<template>
    <div v-if="tabs.length > 1" class="flex gap-1 border-b border-rule-default" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="tab.key === activeTab"
            class="border-b-2 px-3 pb-2.5 pt-1 font-mono text-xs uppercase tracking-wider transition-colors"
            :class="
                tab.key === activeTab
                    ? 'border-rule-emphasis text-text-primary'
                    : 'border-transparent text-text-secondary hover:text-text-primary'
            "
            @click="switchTab(tab.key)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
