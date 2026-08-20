<script setup lang="ts">
/**
 * TotalRow — a figure the reader is meant to stop on.
 *
 * The rules do the work. A subtotal sits under a hairline, a total under a
 * full rule, and a grand total is struck above and below — the double rule that
 * has meant "this is the answer" on paper for a century, and reads as final
 * without needing to be big or loud or coloured.
 *
 * Not a <tr>. Table footers already get their rules from the register styles;
 * this is for the summary blocks that sit beside or beneath a table — invoice
 * totals, a derivation, a reconciliation difference — where the arithmetic
 * needs to be visible as arithmetic.
 */
import MoneyText from '@/components/MoneyText.vue'
import type { MoneyDirection, MoneyTone, NegativeConvention } from '@/lib/money'

withDefaults(
    defineProps<{
        label: string
        amount: number | string | null | undefined
        currency: string
        locale?: string

        /** How much weight the rules carry. */
        level?: 'line' | 'subtotal' | 'total' | 'grand'

        direction?: MoneyDirection
        tone?: MoneyTone
        negative?: NegativeConvention

        /** Quiet second line under the label — "3 invoices", "at 17%". */
        note?: string
    }>(),
    {
        locale: 'en-US',
        level: 'total',
        direction: 'auto',
        tone: 'default',
        negative: 'minus',
        note: undefined,
    },
)
</script>

<template>
    <div class="total-row" :data-level="level">
        <div class="total-row__label">
            <span><slot name="label">{{ label }}</slot></span>
            <span v-if="note || $slots.note" class="total-row__note">
                <slot name="note">{{ note }}</slot>
            </span>
        </div>

        <div class="total-row__amount">
            <MoneyText
                :amount="amount"
                :currency="currency"
                :locale="locale"
                :direction="direction"
                :tone="tone"
                :negative="negative"
                :scale="level === 'grand' ? 'conclusion' : 'default'"
            />
        </div>
    </div>
</template>

<style scoped>
/* Padding comes from the density contract, so a totals block in a compact
   reconciliation view tightens with everything around it. */
.total-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-4, 1rem);
    padding: var(--cell-py) 0;
}

.total-row__label {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.total-row__note {
    font-size: 12px;
    color: var(--text-metadata);
}

.total-row__amount {
    text-align: right;
    white-space: nowrap;
}

/* ── Levels ──────────────────────────────────────────────────────────── */
/* A component line in the derivation. No rule, no weight, and deliberately no
   grey either: these are the real figures, and dimming them would be a second
   device doing the job the rules already do. Emphasis is subtraction here —
   the totals stand out because the lines are quiet, not because they are dim. */
.total-row[data-level='line'] {
    color: inherit;
}

.total-row[data-level='subtotal'] {
    border-top: 1px solid var(--rule-subtle);
}

.total-row[data-level='total'] {
    border-top: 1.5px solid var(--rule-emphasis);
    font-weight: 600;
}

/* The struck balance: ruled above, double-ruled below. */
.total-row[data-level='grand'] {
    border-top: 1.5px solid var(--rule-emphasis);
    border-bottom: 4px double var(--rule-emphasis);
    padding-block: var(--space-3, 0.75rem);
    font-weight: 600;
}

.total-row[data-level='grand'] .total-row__label {
    font-family: var(--display-family);
    font-size: 1.05rem;
}
</style>
