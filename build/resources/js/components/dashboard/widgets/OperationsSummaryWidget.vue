<script setup lang="ts">
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { DashboardWidgetProps, LooseRow } from './types';

const props = defineProps<DashboardWidgetProps>();

const summary = computed<LooseRow[]>(() => props.data?.summary ?? []);
const footerLabel = computed(() => props.data?.footer_label);
const footerHref = computed(() => props.data?.footer_href);

const openMovement = (href?: string) => {
    if (href) router.get(href);
};
</script>

<template>
    <WidgetFrame
        title="Today's movements"
        description="People moving into Saudi Arabia, leaving, or travelling between the holy cities."
        :footer-label="footerLabel"
        :footer-href="footerHref"
    >
        <div
            class="grid overflow-hidden border-y border-rule-default sm:grid-cols-2 lg:grid-cols-4"
        >
            <Button
                v-for="item in summary"
                :key="item.key"
                variant="ghost"
                class="h-auto min-h-16 justify-between rounded-none border-b border-rule-subtle px-3 py-2.5 text-left last:border-b-0 lg:border-r lg:border-b-0 lg:last:border-r-0 sm:[&:nth-child(odd)]:border-r sm:[&:nth-last-child(-n+2)]:border-b-0"
                :aria-label="`Open ${item.label.toLowerCase()} movements`"
                @click="openMovement(item.href)"
            >
                <span class="text-sm font-medium text-text-secondary">{{
                    item.label
                }}</span>
                <span
                    class="font-mono text-xl font-semibold text-text-primary tabular-nums"
                >
                    {{ Number(item.value ?? 0).toLocaleString() }}
                </span>
            </Button>
        </div>
    </WidgetFrame>
</template>
