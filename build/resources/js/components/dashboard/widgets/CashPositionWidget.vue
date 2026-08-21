<script setup lang="ts">
/**
 * umrah.cash_position — "what's actually yours". The dashboard's hero
 * figure: a derivation, not a stat card. Arithmetic shown as arithmetic via
 * Derivation.vue — cash and bank, less what's held for agents, less what's
 * owed to vendors.
 */
import { computed } from 'vue'
import WidgetFrame from '@/components/dashboard/WidgetFrame.vue'
import Derivation from '@/components/Derivation.vue'
import type { DashboardWidgetProps } from './types'

const props = defineProps<DashboardWidgetProps>()

const lines = computed(() => props.data?.lines ?? [])
const totalLabel = computed(() => props.data?.total_label)
const total = computed(() => props.data?.total)
const currency = computed(() => props.data?.currency)
</script>

<template>
    <WidgetFrame
        title="What's actually yours"
        description="Cash and bank, less what you are holding for agents and owe to vendors."
        :is-empty="lines.length === 0"
        empty-message="No posted ledger activity yet."
    >
        <Derivation
            :lines="lines"
            :total-label="totalLabel"
            :total-amount="total"
            :currency="currency"
        />
    </WidgetFrame>
</template>
