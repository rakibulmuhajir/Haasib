<script setup lang="ts">
/**
 * LedgerDocument — the one document in this application.
 *
 * An invoice, a bill, a credit note, a vendor credit, a quote and an Umrah
 * voucher are the same artefact wearing different words. Each says: this party
 * issued it, that party receives it, here is what was supplied, here is what is
 * owed. Before this component the app had no document at all — no letterhead,
 * no logo position, no printable output, just a Card with a table in it. So
 * every one of those decisions is made here, once:
 *
 *   logo         top-left, capped at 44px tall, because the first question a
 *                document answers is who sent it
 *   type         the document's name in the display serif — the one place a
 *                page names what it IS, and the only serif on the sheet
 *                besides the amount owed
 *   number       mono, beside the type, because it is a reference and not a
 *                title
 *   parties      issuer under the letterhead, recipient in its own ruled block;
 *                ship-to appears only when there is one
 *   lines        the register, at print density — same grammar as every table
 *                in the app, so the document is not a second dialect
 *   money owed   the last thing on the sheet and the largest thing on it, under
 *                the double rule, because it is what the reader came for
 *   overprint    draft / void / paid stamped across the body. A document whose
 *                validity is in question must say so before it is read, not in
 *                a chip at the bottom
 *
 * On screen and on paper are the same component. The print rules at the bottom
 * strip the chrome; they do not restyle the document, because a document that
 * looks different when printed was never really a document.
 */
import { computed } from 'vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'

export interface DocumentParty {
    name: string
    /** Address and anything else belonging under the name, one line each. */
    lines?: (string | null | undefined)[]
    email?: string
    phone?: string
    /** NTN / STRN / VAT — whatever this jurisdiction calls it. */
    taxId?: string
    taxIdLabel?: string
}

export interface DocumentIssuer extends DocumentParty {
    logoUrl?: string
}

export interface DocumentLine {
    description: string
    /** A quieter second line — SKU, period covered, passenger name. */
    detail?: string
    quantity?: number | string | null
    unit?: string
    unitPrice?: number | string | null
    amount: number | string | null
}

export interface DocumentTotal {
    label: string
    amount: number | string | null
    /** Shown in the sign gutter so a deduction reads as one. */
    sign?: '+' | '−' | null
    /** Tax breakdowns and the like — present, but not competing. */
    muted?: boolean
}

export interface DocumentDate {
    label: string
    value: string
}

const props = withDefaults(
    defineProps<{
        /** What this document is called: 'Invoice', 'Bill', 'Credit Note'. */
        docType: string
        docNumber?: string
        issuer: DocumentIssuer
        /**
         * Who owes or is owed. Labelled, because "Bill to" and "Vendor" are
         * not the same relationship and the sheet should not pretend they are.
         */
        billTo?: DocumentParty
        billToLabel?: string
        shipTo?: DocumentParty
        shipToLabel?: string
        dates?: DocumentDate[]
        lines: DocumentLine[]
        /** Subtotal, tax, discount — everything between the lines and the answer. */
        totals?: DocumentTotal[]
        grandTotalLabel?: string
        grandTotalAmount: number | string | null
        /** After part-payment, what is still owed. Rendered below the total. */
        amountDueLabel?: string
        amountDueAmount?: number | string | null
        currency: string
        locale?: string
        /** Stamped across the body: 'DRAFT', 'VOID', 'PAID'. */
        overprint?: string | null
        /** Quantities and rates are noise on a document with one flat charge. */
        showQuantity?: boolean
    }>(),
    {
        docNumber: undefined,
        billTo: undefined,
        billToLabel: 'Bill to',
        shipTo: undefined,
        shipToLabel: 'Ship to',
        dates: () => [],
        totals: () => [],
        grandTotalLabel: 'Total',
        amountDueLabel: 'Amount due',
        amountDueAmount: undefined,
        locale: 'en-US',
        overprint: null,
        showQuantity: true,
    },
)

/**
 * The line table's columns. `amount` is the only one always present — a
 * document can charge a flat fee with no quantity, and printing "1 × Rs 40,000"
 * for it is arithmetic nobody asked for.
 */
const lineColumns = computed<RegisterColumn<DocumentLine>[]>(() => {
    const columns: RegisterColumn<DocumentLine>[] = [
        { key: 'description', label: 'Description', kind: 'text' },
    ]
    if (props.showQuantity) {
        columns.push(
            { key: 'quantity', label: 'Qty', kind: 'amount' },
            { key: 'unitPrice', label: 'Rate', kind: 'amount' },
        )
    }
    columns.push({ key: 'amount', label: 'Amount', kind: 'amount' })
    return columns
})

const hasParties = computed(() => Boolean(props.billTo || props.shipTo))

/** Skip blank address lines rather than printing a gap where one was missing. */
const partyLines = (party: DocumentParty) =>
    (party.lines ?? []).filter((line): line is string => Boolean(line && line.trim()))
</script>

<template>
    <article class="doc">
        <!-- Stamped, not labelled. Above the content, so the reader cannot get
             three lines in before learning the thing is void. -->
        <div v-if="overprint" class="overprint" aria-hidden="true">{{ overprint }}</div>
        <p v-if="overprint" class="sr-only">This document is {{ overprint }}.</p>

        <header class="letterhead">
            <div class="issuer">
                <slot name="logo">
                    <img v-if="issuer.logoUrl" :src="issuer.logoUrl" :alt="issuer.name" class="logo" />
                </slot>
                <p class="issuer__name">{{ issuer.name }}</p>
                <p v-for="(line, i) in partyLines(issuer)" :key="i" class="issuer__line">{{ line }}</p>
                <p v-if="issuer.email" class="issuer__line">{{ issuer.email }}</p>
                <p v-if="issuer.phone" class="issuer__line">{{ issuer.phone }}</p>
                <p v-if="issuer.taxId" class="issuer__line issuer__line--ref">
                    {{ issuer.taxIdLabel ?? 'NTN' }} {{ issuer.taxId }}
                </p>
            </div>

            <div class="masthead">
                <h1 class="masthead__type">{{ docType }}</h1>
                <p v-if="docNumber" class="masthead__number">{{ docNumber }}</p>
                <!-- A document leaves the app and loses the base-currency context that
                     makes a bare figure readable, so it states its currency once, here,
                     rather than marking every amount on the sheet. -->
                <p class="masthead__currency">Currency: {{ currency.toUpperCase() }}</p>
                <dl v-if="dates.length" class="dates">
                    <template v-for="date in dates" :key="date.label">
                        <dt>{{ date.label }}</dt>
                        <dd>{{ date.value }}</dd>
                    </template>
                </dl>
                <slot name="masthead" />
            </div>
        </header>

        <section v-if="hasParties" class="parties">
            <div v-if="billTo" class="party">
                <p class="party__label">{{ billToLabel }}</p>
                <p class="party__name">{{ billTo.name }}</p>
                <p v-for="(line, i) in partyLines(billTo)" :key="i" class="party__line">{{ line }}</p>
                <p v-if="billTo.email" class="party__line">{{ billTo.email }}</p>
                <p v-if="billTo.phone" class="party__line">{{ billTo.phone }}</p>
                <p v-if="billTo.taxId" class="party__line party__line--ref">
                    {{ billTo.taxIdLabel ?? 'NTN' }} {{ billTo.taxId }}
                </p>
            </div>

            <div v-if="shipTo" class="party">
                <p class="party__label">{{ shipToLabel }}</p>
                <p class="party__name">{{ shipTo.name }}</p>
                <p v-for="(line, i) in partyLines(shipTo)" :key="i" class="party__line">{{ line }}</p>
            </div>

            <slot name="parties-extra" />
        </section>

        <section class="lines">
            <LedgerRegister
                :data="lines"
                :columns="lineColumns"
                density="print"
                key-field="description"
                :hoverable="false"
            >
                <template #cell-description="{ row }">
                    <span class="line__desc">{{ (row as DocumentLine).description }}</span>
                    <span v-if="(row as DocumentLine).detail" class="line__detail">
                        {{ (row as DocumentLine).detail }}
                    </span>
                </template>
                <template #cell-quantity="{ row }">
                    <span class="line__qty">
                        {{ (row as DocumentLine).quantity ?? '—' }}
                        <span v-if="(row as DocumentLine).unit" class="line__unit">
                            {{ (row as DocumentLine).unit }}
                        </span>
                    </span>
                </template>
                <template #cell-unitPrice="{ row }">
                    <MoneyText
                        :amount="(row as DocumentLine).unitPrice"
                        :currency="currency"
                        :locale="locale"
                        :show-currency="false"
                    />
                </template>
                <template #cell-amount="{ row }">
                    <MoneyText
                        :amount="(row as DocumentLine).amount"
                        :currency="currency"
                        :locale="locale"
                        :show-currency="false"
                    />
                </template>
            </LedgerRegister>
        </section>

        <section class="reckoning">
            <div class="reckoning__col">
                <div
                    v-for="(total, i) in totals"
                    :key="i"
                    class="subtotal"
                    :data-muted="total.muted ? '' : undefined"
                >
                    <span class="subtotal__what">
                        <span class="subtotal__sign" aria-hidden="true">{{ total.sign ?? '' }}</span>
                        {{ total.label }}
                    </span>
                    <MoneyText
                        class="subtotal__amt"
                        :amount="total.amount"
                        :currency="currency"
                        :locale="locale"
                        :show-currency="false"
                    />
                </div>

                <!-- The rule an accountant draws before writing the sum. -->
                <div class="strike" aria-hidden="true"></div>

                <div class="grand">
                    <span class="grand__what">{{ grandTotalLabel }}</span>
                    <MoneyText
                        :amount="grandTotalAmount"
                        :currency="currency"
                        :locale="locale"
                        :show-currency="false"
                        scale="conclusion"
                    />
                </div>

                <div v-if="amountDueAmount !== undefined && amountDueAmount !== null" class="due">
                    <span class="due__what">{{ amountDueLabel }}</span>
                    <MoneyText
                        class="due__amt"
                        :amount="amountDueAmount"
                        :currency="currency"
                        :locale="locale"
                        :show-currency="false"
                    />
                </div>
            </div>
        </section>

        <footer v-if="$slots.terms || $slots.footer" class="colophon">
            <div v-if="$slots.terms" class="terms">
                <p class="terms__label">Terms</p>
                <slot name="terms" />
            </div>
            <slot name="footer" />
        </footer>
    </article>
</template>

<style scoped>
.doc {
    position: relative;
    max-width: 860px;
    margin-inline: auto;
    padding: 40px;
    background: var(--surface-raised);
    border: 1px solid var(--rule-default);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
}

/* ── Letterhead ───────────────────────────────────────────────────────── */

.letterhead {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 40px;
    align-items: start;
    padding-bottom: 24px;
    /* The one emphatic rule on the sheet. It separates who-and-what from the
       substance, and it is the document's spine. Base weight, not heavier —
       on paper a 1.5px ink rule already reads as structural, and 3px reads as
       a box someone drew. */
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

.logo {
    display: block;
    max-height: 44px;
    max-width: 200px;
    margin-bottom: 12px;
    object-fit: contain;
}

.issuer__name {
    font-family: var(--display-family);
    font-size: 19px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.issuer__line {
    margin-top: 2px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-secondary);
}

.issuer__line--ref {
    font-family: var(--mono-family);
    font-size: 11.5px;
    color: var(--text-metadata);
}

.masthead {
    text-align: right;
}

/* The document names itself. This is the serif's job on the sheet. */
.masthead__type {
    font-family: var(--display-family);
    font-size: var(--text-slug, 32px);
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.02em;
    text-transform: uppercase;
}

.masthead__number {
    margin-top: 6px;
    font-family: var(--mono-family);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.02em;
    color: var(--text-secondary);
}

/* Metadata, not a figure — mono and quiet, same register as the rest of the
   masthead. States the currency once so the body's amounts don't have to. */
.masthead__currency {
    margin-top: 6px;
    font-family: var(--mono-family);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.dates {
    display: grid;
    grid-template-columns: auto auto;
    justify-content: end;
    gap: 3px 16px;
    margin-top: 16px;
    text-align: right;
}

.dates dt {
    font-family: var(--mono-family);
    font-size: 10px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-metadata);
}

.dates dd {
    font-family: var(--mono-family);
    font-size: 12.5px;
    color: var(--text-primary);
    white-space: nowrap;
}

/* ── Parties ──────────────────────────────────────────────────────────── */

.parties {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 32px;
    padding: 24px 0;
    border-bottom: 1px solid var(--rule-default);
}

.party__label {
    margin-bottom: 6px;
    font-family: var(--mono-family);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-metadata);
}

.party__name {
    font-size: 15px;
    font-weight: 600;
}

.party__line {
    margin-top: 2px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-secondary);
}

.party__line--ref {
    font-family: var(--mono-family);
    font-size: 11.5px;
    color: var(--text-metadata);
}

/* ── Lines ────────────────────────────────────────────────────────────── */

.lines {
    padding-top: 24px;
}

.line__desc {
    display: block;
}

/* Secondary information about a charge, kept subordinate to the charge. */
.line__detail {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    color: var(--text-secondary);
}

.line__qty {
    white-space: nowrap;
}

.line__unit {
    margin-left: 3px;
    font-size: 0.8em;
    color: var(--text-metadata);
}

/* ── The reckoning ────────────────────────────────────────────────────── */

.reckoning {
    display: flex;
    justify-content: flex-end;
    padding-top: 16px;
    /* A page break through the total is the one break that must never happen. */
    break-inside: avoid;
    /* A conclusion figure sized for a dashboard hero collides with its own
       label inside a 340px document column. The document steps it down — it is
       already the largest thing on the sheet at this size, and dominance is
       relative to what surrounds it, not absolute. */
    --text-conclusion: clamp(26px, 2.6vw, 34px);
}

.reckoning__col {
    width: min(420px, 100%);
}

.subtotal {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: baseline;
    padding: 6px 0;
}

.subtotal__what {
    font-size: 13.5px;
    color: var(--text-secondary);
}

.subtotal__sign {
    display: inline-block;
    width: 12px;
    font-family: var(--mono-family);
    color: var(--text-tertiary);
}

.subtotal__amt {
    font-family: var(--mono-family);
    font-size: 14px;
}

.subtotal[data-muted] .subtotal__what,
.subtotal[data-muted] .subtotal__amt {
    color: var(--text-metadata);
}

.strike {
    margin: 8px 0 12px;
    height: 5px;
    border-top: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

.grand {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: baseline;
}

.grand__what {
    font-family: var(--display-family);
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.01em;
    /* "Invoice total" broken across two lines beside its own figure reads as a
       layout accident. The label holds one line and the figure takes the rest. */
    white-space: nowrap;
}

/* What is still owed after part-payment. Separate from the total, because
   "you were charged X" and "you owe Y" are two different facts. */
.due {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: baseline;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--rule-default);
}

.due__what {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-secondary);
}

.due__amt {
    font-family: var(--mono-family);
    font-size: 17px;
    font-weight: 600;
}

/* ── Colophon ─────────────────────────────────────────────────────────── */

.colophon {
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid var(--rule-default);
    font-size: 13px;
    line-height: 1.6;
    color: var(--text-secondary);
}

.terms__label {
    margin-bottom: 4px;
    font-family: var(--mono-family);
    font-size: 10px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-metadata);
}

/* ── Overprint ────────────────────────────────────────────────────────── */

/* Outlined rather than filled, so it reads as a stamp over the page instead of
   a block that hides what is underneath it. The document must stay legible
   through its own cancellation. */
.overprint {
    position: absolute;
    top: 46%;
    left: 50%;
    z-index: 2;
    transform: translate(-50%, -50%) rotate(-11deg);
    font-family: var(--display-family);
    font-size: clamp(64px, 13vw, 140px);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
    color: transparent;
    -webkit-text-stroke: 3px var(--rule-emphasis);
    opacity: 0.16;
    pointer-events: none;
    user-select: none;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

@media (max-width: 640px) {
    .doc {
        padding: 24px 20px;
    }

    .letterhead {
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
    }

    .masthead {
        text-align: left;
    }

    .dates {
        justify-content: start;
        text-align: left;
    }

    .reckoning__col {
        width: 100%;
    }
}

/* ── Print ────────────────────────────────────────────────────────────── */

/* Strip the chrome, keep the document. Nothing here changes a measurement or a
   typeface — a document that looks different on paper was never a document. */
@media print {
    .doc {
        max-width: none;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        /* The green bar and the red rules are the document's structure, not
           decoration a printer may drop at its discretion. */
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .overprint {
        opacity: 0.22;
    }

    .colophon,
    .parties {
        break-inside: avoid;
    }
}
</style>

<!-- Unscoped: @page cannot live in a scoped block, and a document is the only
     thing in this app with an opinion about the sheet it is printed on. -->
<style>
@media print {
    @page {
        size: A4;
        margin: 16mm;
    }
}
</style>
