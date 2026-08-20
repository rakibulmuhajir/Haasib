<script setup lang="ts">
/**
 * umrah.vendor_balances — Vendor / Kind (StatusBadge) / Balance.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Vendor Balances')
const description = computed(() => props.data?.description ?? 'What is owed to vendors.')
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const columns = [
    { key: 'vendor', label: 'Vendor', kind: 'text' as const },
    { key: 'kind', label: 'Kind', kind: 'status' as const },
    { key: 'balance', label: 'Balance', kind: 'amount' as const },
]
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="rows.length === 0"
        empty-message="No vendor balances to show."
    >
        <LedgerRegister :data="rows" :columns="columns" key-field="id" :banded="true">
            <template #cell-vendor="{ row }">
                {{ row.name ?? '—' }}
            </template>

            <template #cell-kind="{ row }">
                <StatusBadge :status="row.kind" />
            </template>

            <template #cell-balance="{ row }">
                <MoneyText :amount="row.balance" :currency="currency" />
            </template>
        </LedgerRegister>
    </WidgetFrame>
</template>
