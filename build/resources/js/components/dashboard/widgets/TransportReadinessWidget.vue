<script setup lang="ts">
/**
 * umrah.transport_readiness — Group / Travel date / Vendor (or "Not
 * assigned" via StatusBadge) / Paid (StatusBadge).
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const title = computed(() => props.data?.title ?? 'Transport Readiness')
const description = computed(() => props.data?.description ?? 'Vendor assignment and payment status by group.')
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const columns = [
    { key: 'group', label: 'Group', kind: 'text' as const },
    { key: 'travel_date', label: 'Travel Date', kind: 'date' as const },
    { key: 'vendor', label: 'Vendor', kind: 'status' as const },
    { key: 'paid', label: 'Paid', kind: 'status' as const },
]
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="rows.length === 0"
        empty-message="No groups to track transport for."
    >
        <LedgerRegister :data="rows" :columns="columns" key-field="id" :banded="true">
            <template #cell-group="{ row }">
                <div class="font-medium text-text-primary">{{ row.group_number ?? '—' }}</div>
            </template>

            <template #cell-travel_date="{ row }">
                <DateTimeText :value="row.travel_date" mode="date" />
            </template>

            <template #cell-vendor="{ row }">
                <StatusBadge :status="row.vendor_name" fallback="Not assigned" />
            </template>

            <template #cell-paid="{ row }">
                <StatusBadge :status="row.paid ? 'paid' : 'unpaid'" />
            </template>
        </LedgerRegister>
    </WidgetFrame>
</template>
