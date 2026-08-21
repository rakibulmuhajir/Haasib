<script setup lang="ts">
/**
 * umrah.refunds_awaiting_decision — refunds sitting at `requested`, the queue
 * that makes an agent's request visible to the company instead of it living
 * only in WhatsApp (docs/contracts/refunds.md, Phase 3). Oldest first: the
 * request that has waited longest is the one that most needs a person.
 *
 * `requested` reads as info-tone via StatusBadge — amber at most, never red.
 * A refund waiting for a decision needs a person, not an alarm.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Refunds waiting on you')
const description = computed(
    () => props.data?.description ?? 'Requested refunds not yet accepted or rejected.',
)
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const columns = [
    { key: 'refund_number', label: 'Refund', kind: 'ref' as const },
    { key: 'requested_at', label: 'Requested', kind: 'date' as const },
    { key: 'requested_by', label: 'By', kind: 'text' as const },
    { key: 'party_name', label: 'Party', kind: 'text' as const },
    { key: 'reason', label: 'Reason', kind: 'text' as const },
    { key: 'amount', label: 'Amount', kind: 'amount' as const },
]
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="rows.length === 0"
        empty-message="Nothing waiting on a decision."
    >
        <LedgerRegister :data="rows" :columns="columns" key-field="id" :banded="true">
            <template #cell-refund_number="{ row }">
                <a v-if="row.href" :href="row.href" class="font-medium text-text-primary hover:underline">
                    {{ row.refund_number ?? '—' }}
                </a>
                <span v-else class="font-medium text-text-primary">{{ row.refund_number ?? '—' }}</span>
                <div class="mt-0.5">
                    <StatusBadge :status="row.status" />
                </div>
            </template>

            <template #cell-requested_at="{ row }">
                <DateTimeText :value="row.requested_at" mode="date" />
            </template>

            <template #cell-requested_by="{ row }">
                {{ row.requested_by ?? '—' }}
            </template>

            <template #cell-party_name="{ row }">
                {{ row.party_name ?? '—' }}
            </template>

            <template #cell-reason="{ row }">
                <span class="text-text-secondary">{{ row.reason ?? '—' }}</span>
            </template>

            <template #cell-amount="{ row }">
                <MoneyText :amount="row.amount" :currency="row.currency ?? currency" />
            </template>
        </LedgerRegister>
    </WidgetFrame>
</template>
