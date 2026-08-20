<script setup lang="ts">
/**
 * umrah.cash_book — "what's been happening". Separate money-in / money-out
 * columns (both ink, per law 3), banded, double-ruled total row from the
 * totals the payload provides, and a "N of M movements" footer note.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import type { DashboardWidgetProps, LooseRow } from './types'

const props = defineProps<DashboardWidgetProps>()

const rows = computed<LooseRow[]>(() => props.data?.rows ?? [])
const currency = computed(() => props.data?.currency ?? '')
const title = computed(() => props.data?.title ?? 'Cash Book')
const description = computed(() => props.data?.description ?? "What's been happening.")
const footerLabel = computed(() => props.data?.footer_label ?? props.data?.footerLabel)
const footerHref = computed(() => props.data?.footer_href ?? props.data?.footerHref)

const shown = computed(() => rows.value.length)
const total = computed(() => props.data?.total_movements ?? shown.value)

const totals = computed(() => {
    const raw = props.data?.totals ?? {}
    return {
        in: raw.in,
        out: raw.out,
    }
})

const columns = [
    { key: 'date', label: 'Date', kind: 'date' as const },
    { key: 'description', label: 'Description', kind: 'text' as const },
    { key: 'in', label: 'In', kind: 'in' as const },
    { key: 'out', label: 'Out', kind: 'out' as const },
]
</script>

<template>
    <WidgetFrame
        :title="title"
        :description="description"
        :footer-label="footerLabel"
        :footer-href="footerHref"
        :is-empty="rows.length === 0"
        empty-message="No cash movements recorded."
    >
        <LedgerRegister
            :data="rows"
            :columns="columns"
            key-field="id"
            :banded="true"
            :totals="totals"
            totals-label="Total"
        >
            <template #cell-date="{ row }">
                <DateTimeText :value="row.payment_date" mode="date" />
            </template>

            <template #cell-description="{ row }">
                <div class="text-text-primary">{{ row.particulars ?? '—' }}</div>
                <div v-if="row.payment_number" class="text-xs text-text-metadata">{{ row.payment_number }}</div>
            </template>

            <template #cell-in="{ row }">
                <MoneyText
                    v-if="row.in != null"
                    :amount="row.in"
                    :currency="currency"
                    :show-currency="false"
                />
            </template>

            <template #cell-out="{ row }">
                <MoneyText
                    v-if="row.out != null"
                    :amount="row.out"
                    :currency="currency"
                    :show-currency="false"
                />
            </template>

            <template #total-in>
                <MoneyText :amount="totals.in" :currency="currency" :show-currency="false" />
            </template>

            <template #total-out>
                <MoneyText :amount="totals.out" :currency="currency" :show-currency="false" />
            </template>
        </LedgerRegister>

        <p class="mt-3 text-right text-xs text-text-metadata">
            {{ shown }} of {{ total }} movements
        </p>
    </WidgetFrame>
</template>
