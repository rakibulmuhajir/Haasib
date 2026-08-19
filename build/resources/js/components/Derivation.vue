<script setup lang="ts">
/**
 * Derivation — arithmetic shown as arithmetic.
 *
 * A dashboard that reports "Cash position: Rs 0" has told you a number and
 * nothing else. A derivation shows the working: what you hold, less what you
 * owe soon, plus what is likely to arrive, struck through, and the figure that
 * falls out the bottom. The reader can disagree with a line instead of having
 * to trust the total.
 *
 * The strike rule is the whole device. It is the mark an accountant draws under
 * a column before writing the sum, and it is why this reads as a reckoning
 * rather than a list of statistics.
 *
 * Signs live in a fixed-width gutter so they form their own column and the
 * descriptions still start on a common left edge. A row with no sign leaves
 * the gutter empty rather than shifting — the opening balance is not "plus".
 */
import MoneyText from '@/components/MoneyText.vue'

export interface DerivationLine {
    /** Plain-language description of what this line is. */
    label: string
    amount: number | string | null | undefined
    /** `null` for the opening line, which is neither added nor subtracted. */
    sign?: '+' | '−' | null
    /** Mark a line as not-yet-fact — renders italic and muted. */
    estimated?: boolean
}

withDefaults(
    defineProps<{
        lines: DerivationLine[]
        /** What the arithmetic produces. */
        totalLabel: string
        totalAmount: number | string | null | undefined
        currency: string
        locale?: string
    }>(),
    { locale: 'en-US' },
)
</script>

<template>
    <div class="derivation">
        <div v-for="(line, i) in lines" :key="i" class="dline">
            <span class="dline__what">
                <span class="dline__sign" aria-hidden="true">{{ line.sign ?? '' }}</span>
                <span>{{ line.label }}</span>
            </span>
            <MoneyText
                class="dline__amt"
                :amount="line.amount"
                :currency="currency"
                :locale="locale"
                :tone="line.estimated ? 'estimated' : 'default'"
                :show-currency="false"
            />
        </div>

        <!-- Ruled off. Everything above is working; everything below is the answer. -->
        <div class="strikewrap" aria-hidden="true"><div class="strike"></div></div>

        <div class="total">
            <span class="total__what">{{ totalLabel }}</span>
            <MoneyText
                :amount="totalAmount"
                :currency="currency"
                :locale="locale"
                scale="conclusion"
            />
        </div>

        <p v-if="$slots.footnote" class="footnote"><slot name="footnote" /></p>
    </div>
</template>

<style scoped>
.derivation {
    margin-top: 20px;
    max-width: 620px;
}

.dline {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: baseline;
    padding: 9px 0;
}

.dline__what {
    font-size: 15px;
    color: var(--text-secondary);
}

/* Fixed width so the signs stack into a column of their own and the labels
   still share a left edge whether or not a line carries one. */
.dline__sign {
    display: inline-block;
    width: 14px;
    font-family: var(--mono-family);
    color: var(--text-tertiary);
}

.dline__amt {
    font-family: var(--mono-family);
    font-size: 19px;
    font-weight: 500;
}

.strikewrap {
    position: relative;
    height: 7px;
    margin: 5px 0;
}

.strike {
    position: absolute;
    inset: 0;
    width: 100%;
    border-top: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    transform-origin: left center;
    animation: strike-in 0.5s cubic-bezier(0.7, 0, 0.3, 1) 0.35s both;
}

.total {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: baseline;
    padding-top: 13px;
}

/* The only serif on the block, and only because this line names the answer. */
.total__what {
    font-family: var(--display-family);
    font-size: 19px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text-primary);
}

.footnote {
    margin-top: 16px;
    max-width: 52ch;
    font-size: 13px;
    color: var(--text-secondary);
}

/* The rule drawing itself is the page completing its sum. It runs once, on
   arrival, and never on a re-render — hence a CSS animation rather than a
   transition on state. */
@keyframes strike-in {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .strike {
        animation: none;
    }
}

@media (max-width: 600px) {
    .dline__what {
        font-size: 14px;
    }
}
</style>
