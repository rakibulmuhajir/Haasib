<script setup lang="ts">
/**
 * umrah.needs_attention — not a table. An item list: a MetaChip whose text
 * and tone carry the severity, a title, a context sub-line, an amount, and
 * one action link per item.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import MoneyText from '@/components/MoneyText.vue'
import MetaChip from '@/components/MetaChip.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const items = computed<LooseRow[]>(() => props.data?.items ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Needs Attention')
const description = computed(() => props.data?.description ?? 'What needs you.')
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const chipTone = (severity: string | null | undefined) => {
    switch (severity) {
        case 'critical':
        case 'late':
            return 'late'
        case 'info':
            return 'info'
        case 'success':
            return 'success'
        case 'attention':
        default:
            return 'attention'
    }
}
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="items.length === 0"
        empty-message="Nothing needs your attention."
    >
        <ul class="divide-y divide-rule-subtle">
            <li
                v-for="(item, index) in items"
                :key="item.key ?? index"
                class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <MetaChip :tone="chipTone(item.severity)">
                            {{ item.chip ?? 'Note' }}
                        </MetaChip>
                        <span class="truncate text-sm font-medium text-text-primary">
                            {{ item.title }}
                        </span>
                    </div>
                    <p v-if="item.context" class="mt-1 text-xs text-text-secondary">
                        {{ item.context }}
                    </p>
                    <a
                        v-if="item.href"
                        :href="item.href"
                        class="mt-1.5 inline-block font-mono text-xs uppercase tracking-wider text-text-secondary hover:text-text-primary"
                    >
                        View →
                    </a>
                </div>

                <MoneyText
                    v-if="item.amount != null"
                    class="shrink-0"
                    :amount="item.amount"
                    :currency="currency"
                />
            </li>
        </ul>
    </WidgetFrame>
</template>
