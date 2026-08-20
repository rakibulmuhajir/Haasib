<script setup lang="ts">
/**
 * umrah.departures — groups ordered by their next travel date.
 * Columns: Group / Travel date (+ days-until MetaChip) / Agent / Pax / Balance.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import MetaChip from '@/components/MetaChip.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Departures')
const description = computed(() => props.data?.description ?? 'Groups ordered by their next travel date.')
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const columns = [
    { key: 'group', label: 'Group', kind: 'text' as const },
    { key: 'travel_date', label: 'Travel Date', kind: 'date' as const },
    { key: 'agent', label: 'Agent', kind: 'text' as const },
    { key: 'pax', label: 'Pax', kind: 'amount' as const },
    { key: 'balance', label: 'Balance', kind: 'amount' as const },
]

const daysUntilTone = (days: number | null | undefined) => {
    if (days == null) return 'neutral'
    if (days < 0) return 'late'
    if (days <= 7) return 'attention'
    return 'neutral'
}
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="rows.length === 0"
        empty-message="No upcoming departures."
    >
        <LedgerRegister :data="rows" :columns="columns" key-field="id" :banded="true">
            <template #cell-group="{ row }">
                <div class="font-medium text-text-primary">{{ row.group_number ?? '—' }}</div>
                <div class="truncate text-xs text-text-secondary">{{ row.name ?? '' }}</div>
            </template>

            <template #cell-travel_date="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <DateTimeText :value="row.travel_date" mode="date" />
                    <MetaChip
                        v-if="row.days_until != null"
                        :tone="daysUntilTone(row.days_until)"
                    >
                        {{ row.days_until }}d
                    </MetaChip>
                </div>
            </template>

            <template #cell-agent="{ row }">
                {{ row.agent_name ?? '—' }}
            </template>

            <template #cell-pax="{ row }">
                {{ row.passenger_count ?? 0 }}
            </template>

            <template #cell-balance="{ row }">
                <MoneyText :amount="row.balance" :currency="currency" />
            </template>
        </LedgerRegister>
    </WidgetFrame>
</template>
