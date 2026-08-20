<script setup lang="ts">
/**
 * umrah.agent_balances — Agent / Balance.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Agent Balances')
const description = computed(() => props.data?.description ?? 'What agents owe or are owed.')
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const columns = [
    { key: 'agent', label: 'Agent', kind: 'text' as const },
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
        empty-message="No agent balances to show."
    >
        <LedgerRegister :data="rows" :columns="columns" key-field="id" :banded="true">
            <template #cell-agent="{ row }">
                {{ row.name ?? '—' }}
            </template>

            <template #cell-balance="{ row }">
                <MoneyText :amount="row.balance" :currency="currency" />
            </template>
        </LedgerRegister>
    </WidgetFrame>
</template>
