<script setup lang="ts">
/**
 * MoneyText — the single way an amount reaches the screen.
 *
 * Three things are deliberately separate here, because collapsing any two of
 * them is how financial interfaces start lying to people:
 *
 *   direction  which way the money moved      → decides the SIGN
 *   tone       how settled or adverse it is   → decides the COLOUR
 *   scale      how loud the figure is         → decides the SIZE
 *
 * Paying rent is an outflow, not an emergency. It renders as ink with a minus
 * sign. Red appears only when something is genuinely adverse — an overdue
 * receivable — and never merely because money left the account.
 *
 * The original props (`amount`, `currency`, `locale`) are unchanged, so the 23
 * existing call sites keep working untouched and adopt the new behaviour a prop
 * at a time.
 */
import { computed } from 'vue'
import {
    formatMoney,
    moneyLabel,
    type MoneyDirection,
    type MoneyTone,
    type NegativeConvention,
} from '@/lib/money'

type Props = {
    amount: number | string | null | undefined
    currency: string
    locale?: string

    /** `auto` trusts the sign on the number; `outflow` forces a minus. */
    direction?: MoneyDirection
    /** Severity, independent of direction. */
    tone?: MoneyTone
    /** `parens` belongs to printed reports. Never mix conventions on a screen. */
    negative?: NegativeConvention

    /**
     * The same figure in the company's base currency. Rendered as quiet
     * metadata beside the original, so the amount the customer was actually
     * billed stays the headline and the conversion stays a footnote.
     */
    baseAmount?: number | string | null
    baseCurrency?: string

    /** `conclusion` is for the one figure a page exists to deliver. */
    scale?: 'default' | 'conclusion'
    /** Show the currency symbol. Off inside a column already headed with it. */
    showCurrency?: boolean
    /** Render zero as an em dash — a long register scans better without 0.00. */
    dashZero?: boolean
    /** Override the currency's natural decimals, e.g. 0 for whole rupees. */
    fractionDigits?: number
}

const props = withDefaults(defineProps<Props>(), {
    locale: 'en-US',
    direction: 'auto',
    tone: 'default',
    negative: 'minus',
    scale: 'default',
    showCurrency: true,
    dashZero: false,
    baseAmount: null,
    baseCurrency: undefined,
    fractionDigits: undefined,
})

const parts = computed(() =>
    formatMoney(props.amount, {
        currency: props.currency,
        locale: props.locale,
        direction: props.direction,
        negative: props.negative,
        fractionDigits: props.fractionDigits,
        dashZero: props.dashZero,
    }),
)

const base = computed(() => {
    if (props.baseAmount === null || props.baseAmount === undefined) return null
    if (!props.baseCurrency || props.baseCurrency === props.currency) return null
    return formatMoney(props.baseAmount, {
        currency: props.baseCurrency,
        locale: props.locale,
        direction: props.direction,
        negative: props.negative,
    })
})

const label = computed(() => moneyLabel(parts.value, props.currency))
</script>

<template>
    <span
        class="money"
        :class="[`money--${tone}`, `money--${scale}`, { 'money--nil': parts.isNil }]"
        :aria-label="label"
    >
        <template v-if="parts.isNil">
            <span aria-hidden="true">{{ parts.value }}</span>
        </template>

        <template v-else>
            <span aria-hidden="true">
                <span v-if="parts.open">{{ parts.open }}</span>
                <span v-if="parts.sign" class="money__sign">{{ parts.sign }}</span>
                <span v-if="showCurrency" class="money__currency">{{ parts.currency }}</span>
                <span class="money__value">{{ parts.value }}</span>
                <span v-if="parts.close">{{ parts.close }}</span>
            </span>

            <span v-if="base" class="money__base" aria-hidden="true">
                {{ base.open }}{{ base.sign }}{{ base.currency }}&nbsp;{{ base.value }}{{ base.close }}
            </span>
        </template>
    </span>
</template>

<style scoped>
/* Tabular figures and no wrapping are non-negotiable: a column of amounts is
   read by scanning the decimal point, and a wrapped figure breaks the column. */
.money {
    display: inline-flex;
    align-items: baseline;
    gap: 0.28em;
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum';
    white-space: nowrap;
}

/* The symbol is context, not content — small and quiet so the digits carry the
   line, and consistent in width so columns still align when currencies differ. */
.money__currency {
    font-size: 0.7em;
    color: var(--text-metadata);
    margin-inline-end: 0.3em;
}

/* A minus sign that disappears at small sizes is a defect, not a subtlety. */
.money__sign {
    margin-inline-end: 0.05em;
}

/* ── Tone: severity only, never direction ────────────────────────────── */
.money--default {
    color: inherit;
}

/* Not yet fact. Italic carries the meaning where colour cannot be trusted. */
.money--estimated {
    color: var(--amount-estimated);
    font-style: italic;
}

/* Genuinely adverse — the one place an amount is allowed to be red. */
.money--overdue {
    color: var(--amount-overdue);
}

.money--muted {
    color: var(--text-metadata);
}

/* Nothing to show. An em dash, not a zero: they mean different things. */
.money--nil {
    color: var(--text-metadata);
}

/* ── Scale ───────────────────────────────────────────────────────────── */
/* Ordinary amounts stay in the body face — setting every figure in monospace
   makes an accounts page read as a terminal. The conclusion is the exception:
   it is the one number a page exists to deliver, it has no column to align
   with, and at 44px the typewriter is the point. Sized from --text-conclusion
   so the step is a token decision, not a number typed into a component. */
.money--conclusion {
    font-family: var(--mono-family);
    font-size: var(--text-conclusion);
    line-height: 1;
    font-weight: 600;
    letter-spacing: -0.035em;
}

.money--conclusion .money__currency {
    font-size: 0.44em;
    font-weight: 500;
    color: var(--text-secondary);
}

/* The base-currency equivalent is a footnote to the figure beside it. */
.money__base {
    font-family: var(--mono-family);
    font-size: 0.75em;
    color: var(--text-metadata);
}
</style>
