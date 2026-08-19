<script setup lang="ts" generic="T extends Record<string, any>">
/**
 * LedgerRegister — the one table in this application.
 *
 * Before this existed the app spoke four table dialects at once: invoices
 * right-aligned their amounts and used ruled status chips, bills left-aligned
 * and used filled badges, journals rendered status as bare lowercase text, and
 * stock movements painted ordinary fuel dispensing in alarm red. Same product,
 * four grammars, because every page hand-rolled its own markup.
 *
 * So the decisions live here and nowhere else:
 *
 *   banding      alternate rows carry the green bar, because that is what a
 *                register is for — keeping your eye on one row across a wide
 *                span of columns
 *   alignment    figures right, text left, and no page gets a vote
 *   typeface     mono for figures, dates, references and column headers;
 *                the body face for everything a person reads as language
 *   direction    IN and OUT are separate columns. Both are ink. Money leaving
 *                the business is not an emergency, and red that means "outflow"
 *                cannot also mean "overdue"
 *   totals       a double rule, the way a sum is ruled off on paper
 *
 * A column declares its `kind` and inherits all of the above. `numeric: true`
 * is kept as an alias for `kind: 'amount'` so the call sites written against
 * the old DataTable keep working untouched.
 */
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'
import { formatDateTimeForDisplay } from '@/lib/datetime'
import {
    ChevronLeft,
    ChevronRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
} from 'lucide-vue-next'

/**
 * What a column IS, which decides how it looks. Alignment, typeface and
 * wrapping are consequences of meaning rather than choices made per screen.
 *
 *   text    language — the body face, left, wraps
 *   date    when it happened — mono, quiet, never wraps
 *   ref     a document number or folio — mono, quiet, never wraps
 *   in      money arriving — mono, right, ink
 *   out     money leaving — mono, right, ink, carries no sign because the
 *           column heading already said which way it went
 *   amount  a figure with no direction — mono, right
 *   status  a state — right-aligned nothing; the chip carries itself
 */
export type ColumnKind = 'text' | 'date' | 'ref' | 'in' | 'out' | 'amount' | 'status'

export interface RegisterColumn<Row> {
    key: keyof Row | string
    label: string
    kind?: ColumnKind
    sortable?: boolean
    class?: string
    headerClass?: string
    render?: (row: Row) => any
    /** Back-compat alias for `kind: 'amount'`. */
    numeric?: boolean
}

interface Props {
    data: T[]
    columns: RegisterColumn<T>[]
    title?: string
    description?: string
    /**
     * Which field identifies a row. Some registers -- a statement of account,
     * a reconciliation -- are built from computed rows that have no id and no
     * single unique column, so a function may be given instead of a field name.
     */
    keyField?: keyof T | ((row: T, index: number) => string | number)
    /**
     * Which rows are showing their detail. A register that can be opened up --
     * a member and their permissions, a transaction and its match candidates --
     * used to mean writing a second table by hand, and a second table by hand
     * is how a page ends up with its own alignment and its own banding.
     */
    expanded?: (row: T, index: number) => boolean
    loading?: boolean
    pagination?: {
        currentPage?: number
        perPage?: number
        total: number
        current_page?: number
        per_page?: number
        last_page?: number
    }
    /**
     * Which density contract this register works at. Reconciliation and
     * journals are compact because reconciliation is dense work, not because
     * the person doing it prefers small text.
     */
    density?: 'comfortable' | 'compact' | 'print'
    hoverable?: boolean
    clickable?: boolean
    /**
     * The green bar. On by default — it is the register's defining feature and
     * the reason a wide row stays readable. Turn it off for a table of three
     * rows, where banding is stripes for their own sake.
     */
    banded?: boolean
    /**
     * The sprocket margin of continuous-feed stock. Decoration, and the only
     * decoration in the component, so it is opt-in and never appears on a
     * table that is merely a list.
     */
    sprockets?: boolean
    /**
     * Totals keyed by column. Rendered under a double rule, the way a column
     * is ruled off and summed on paper.
     */
    totals?: Partial<Record<string, string | number>>
    /** What the total row is called. */
    totalsLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
    title: undefined,
    description: undefined,
    keyField: 'id' as keyof T,
    expanded: undefined,

    loading: false,
    pagination: undefined,
    density: undefined,
    hoverable: true,
    clickable: false,
    banded: true,
    sprockets: false,
    totals: undefined,
    totalsLabel: 'Total',
})

const emit = defineEmits<{
    sort: [column: keyof T | string, direction: 'asc' | 'desc' | null]
    'page-change': [page: number]
    'row-click': [row: T]
}>()

/**
 * Rows with no usable identity used to key on `undefined`, which makes every
 * row look like the same row to Vue and lets it reuse the wrong DOM node when
 * the data changes. The index is a poor key but an honest one, and it is only
 * reached when the row genuinely has nothing better.
 */
const rowKey = (row: T, index: number): string => {
    const field = props.keyField
    if (typeof field === 'function') return String(field(row, index))

    // Indexed as a plain record: `withDefaults` widens `keyof T` past the point
    // where TypeScript will accept it as an index, and the runtime lookup is
    // the same either way.
    const value = (row as Record<string, unknown>)[String(field ?? 'id')]
    return value === undefined || value === null ? `row-${index}` : String(value)
}

/**
 * A column that does not declare its kind still has one — the key almost always
 * says what it is. Inferring it here is what stops a page from rendering an
 * undecided column: a page that forgets `kind: 'amount'` on `total_amount`
 * still gets right-aligned tabular figures, and a page that means something
 * else says so explicitly and wins.
 *
 * Matching is on whole word-parts rather than substrings, so `status_note` is
 * text and `paid_at` is a date.
 */
const FIGURE_KEYS = new Set([
    'amount', 'total', 'subtotal', 'balance', 'price', 'cost', 'rate',
    'quantity', 'qty', 'value', 'paid', 'due', 'credit', 'debit', 'tax',
    'discount', 'unit', 'outstanding', 'owed',
])
const DATE_KEYS = new Set(['date', 'at', 'on', 'when', 'period'])

const inferKind = (key: string): ColumnKind => {
    const parts = key.replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase().split(/[_.\s-]+/)
    const last = parts[parts.length - 1]

    if (parts.includes('status') || parts.includes('state')) return 'status'
    if (DATE_KEYS.has(last)) return 'date'
    if (parts.some((part) => FIGURE_KEYS.has(part))) return 'amount'
    if (last === 'number' || last === 'ref' || last === 'reference' || last === 'code') return 'ref'

    return 'text'
}

const kindOf = (column: RegisterColumn<T>): ColumnKind =>
    column.kind ?? (column.numeric ? 'amount' : inferKind(String(column.key)))

/** Figures sit right so the decimal points line up down the column. */
const isFigure = (column: RegisterColumn<T>) =>
    ['in', 'out', 'amount'].includes(kindOf(column))

const sortState = ref<{ column: string | null; direction: 'asc' | 'desc' | null }>({
    column: null,
    direction: null,
})

const handleSort = (column: RegisterColumn<T>) => {
    if (!column.sortable) return

    const columnKey = String(column.key)

    if (sortState.value.column === columnKey) {
        if (sortState.value.direction === 'asc') {
            sortState.value.direction = 'desc'
        } else if (sortState.value.direction === 'desc') {
            sortState.value.column = null
            sortState.value.direction = null
        }
    } else {
        sortState.value.column = columnKey
        sortState.value.direction = 'asc'
    }

    emit('sort', columnKey as keyof T | string, sortState.value.direction)
}

const getSortIcon = (column: RegisterColumn<T>): Component => {
    if (sortState.value.column !== String(column.key)) return ArrowUpDown
    return sortState.value.direction === 'asc' ? ArrowUp : ArrowDown
}

/** One shape for the rest of the component to reason about. */
const page = computed(() => {
    const p = props.pagination
    if (!p) return null

    return {
        currentPage: p.currentPage ?? p.current_page ?? 1,
        perPage: p.perPage ?? p.per_page ?? 0,
        total: p.total ?? 0,
    }
})

const totalPages = computed(() => {
    const p = page.value
    if (!p) return 1
    // A paginator that reports its own page count is more trustworthy than one
    // derived from a per-page figure that may have been filtered.
    if (props.pagination?.last_page) return props.pagination.last_page
    if (!p.perPage) return 1
    return Math.ceil(p.total / p.perPage)
})

const pageRange = computed(() => {
    if (!page.value) return []
    const current = page.value.currentPage
    const total = totalPages.value
    const range: (number | 'ellipsis')[] = []

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
            range.push(i)
        } else if (range[range.length - 1] !== 'ellipsis') {
            range.push('ellipsis')
        }
    }

    return range
})

const goToPage = (target: number) => {
    if (!page.value) return
    if (target < 1 || target > totalPages.value) return
    emit('page-change', target)
}

const getCellValue = (row: T, column: RegisterColumn<T>) =>
    column.render ? column.render(row) : row[column.key as keyof T]

const getDisplayCellValue = (row: T, column: RegisterColumn<T>) =>
    formatDateTimeForDisplay(getCellValue(row, column), String(column.key))

const handleRowClick = (row: T) => {
    if (props.clickable) emit('row-click', row)
}
</script>

<template>
    <div class="register" :data-density="density">
        <!-- Masthead -->
        <div v-if="title || description || $slots.header" class="reghead">
            <div>
                <h3 v-if="title" class="reghead__title">{{ title }}</h3>
                <p v-if="description" class="reghead__desc">{{ description }}</p>
            </div>
            <div v-if="$slots.header"><slot name="header" /></div>
        </div>

        <!-- The paper -->
        <div class="paperwrap" :class="{ 'paperwrap--sprockets': sprockets }">
            <table class="hidden lg:table">
                <thead>
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="String(column.key)"
                            scope="col"
                            :class="[
                                'reg-head',
                                isFigure(column) && 'reg-head--figure',
                                column.sortable && 'reg-head--sortable',
                                column.headerClass,
                            ]"
                            @click="handleSort(column)"
                        >
                            <span class="reg-head__inner" :class="isFigure(column) && 'justify-end'">
                                <span>{{ column.label }}</span>
                                <component
                                    :is="getSortIcon(column)"
                                    v-if="column.sortable"
                                    class="h-3 w-3"
                                    :class="
                                        sortState.column === String(column.key)
                                            ? 'text-text-primary'
                                            : 'text-text-quaternary'
                                    "
                                />
                            </span>
                        </th>
                    </tr>
                </thead>

                <tbody :class="{ 'tbody--banded': banded }">
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="reg-cell reg-cell--wide text-center">
                            <span class="inline-flex items-center gap-3">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-rule-default border-t-rule-emphasis"
                                />
                                <span class="text-sm text-text-secondary">Loading…</span>
                            </span>
                        </td>
                    </tr>

                    <tr v-else-if="data.length === 0">
                        <td :colspan="columns.length" class="reg-cell reg-cell--wide">
                            <slot name="empty">
                                <div class="text-center text-sm text-text-secondary">
                                    Nothing recorded yet.
                                </div>
                            </slot>
                        </td>
                    </tr>

                    <template v-else>
                    <template v-for="(row, rowIndex) in data" :key="rowKey(row, rowIndex)">
                    <tr
                        :class="[hoverable && 'reg-row--hover', clickable && 'cursor-pointer']"
                        @click="handleRowClick(row)"
                    >
                        <td
                            v-for="column in columns"
                            :key="String(column.key)"
                            :class="['reg-cell', `reg-cell--${kindOf(column)}`, column.class]"
                        >
                            <slot
                                :name="`cell-${String(column.key)}`"
                                :row="row"
                                :value="getCellValue(row, column)"
                            >
                                {{ getDisplayCellValue(row, column) }}
                            </slot>
                        </td>
                    </tr>

                    <!-- The detail spans the whole register and keeps the
                         banding of the row it belongs to, so it reads as part
                         of that row rather than as a new one. -->
                    <tr
                        v-if="expanded && expanded(row, rowIndex)"
                        class="reg-row--detail"
                    >
                        <td :colspan="columns.length" class="reg-cell reg-cell--wide">
                            <slot name="row-detail" :row="row" :index="rowIndex" />
                        </td>
                    </tr>
                    </template>
                    </template>
                </tbody>

                <!-- Ruled off and summed. -->
                <tfoot v-if="totals && data.length">
                    <tr>
                        <td
                            v-for="(column, i) in columns"
                            :key="String(column.key)"
                            :class="[
                                'reg-total',
                                isFigure(column) && 'reg-total--figure',
                                i === 0 && 'reg-total--label',
                            ]"
                        >
                            <slot :name="`total-${String(column.key)}`">
                                <template v-if="i === 0">{{ totalsLabel }}</template>
                                <template v-else>{{ totals[String(column.key)] ?? '' }}</template>
                            </slot>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Narrow screens get the same rows as stacked slips. A register with
             five columns does not survive a 360px viewport, and a horizontally
             scrolling table is a table nobody reads the right-hand side of. -->
        <div class="slips lg:hidden">
            <div v-if="loading" class="slips__note">Loading…</div>
            <div v-else-if="data.length === 0" class="slips__note">
                <slot name="empty">Nothing recorded yet.</slot>
            </div>
            <template v-else>
                <slot
                    v-for="(row, rowIndex) in data"
                    :key="rowKey(row, rowIndex)"
                    name="mobile-card"
                    :row="row"
                >
                    <div class="slip" :class="{ 'cursor-pointer': clickable }" @click="handleRowClick(row)">
                        <div
                            v-for="column in columns"
                            :key="String(column.key)"
                            class="slip__line"
                        >
                            <span class="slip__label">{{ column.label }}</span>
                            <span class="slip__value" :class="isFigure(column) && 'slip__value--figure'">
                                <slot
                                    :name="`cell-${String(column.key)}`"
                                    :row="row"
                                    :value="getCellValue(row, column)"
                                >
                                    {{ getDisplayCellValue(row, column) }}
                                </slot>
                            </span>
                        </div>
                    </div>
                </slot>
            </template>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && totalPages > 1" class="regfoot">
            <p v-if="page" class="regfoot__count">
                Showing
                <span class="tabular">{{ (page.currentPage - 1) * page.perPage + 1 }}</span>
                to
                <span class="tabular">{{
                    Math.min(page.currentPage * page.perPage, page.total)
                }}</span>
                of
                <span class="tabular">{{ page.total }}</span>
            </p>

            <nav class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 w-8 p-0"
                    :disabled="page?.currentPage === 1"
                    @click="goToPage((page?.currentPage ?? 1) - 1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                    <span class="sr-only">Previous page</span>
                </Button>

                <template v-for="(p, index) in pageRange" :key="index">
                    <span v-if="p === 'ellipsis'" class="px-2 text-text-tertiary">…</span>
                    <Button
                        v-else
                        :variant="p === page?.currentPage ? 'default' : 'outline'"
                        size="sm"
                        class="h-8 w-8 p-0"
                        :aria-current="p === page?.currentPage ? 'page' : undefined"
                        @click="goToPage(p)"
                    >
                        {{ p }}
                    </Button>
                </template>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 w-8 p-0"
                    :disabled="page?.currentPage === totalPages"
                    @click="goToPage((page?.currentPage ?? 1) + 1)"
                >
                    <ChevronRight class="h-4 w-4" />
                    <span class="sr-only">Next page</span>
                </Button>
            </nav>
        </div>
    </div>
</template>

<style scoped>
.register {
    background: var(--surface-raised);
}

/* ── Masthead ────────────────────────────────────────────────────────── */
.reghead {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 17px 22px 15px;
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

.reghead__title {
    font-family: var(--display-family);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-primary);
}

.reghead__desc {
    margin-top: 5px;
    font-size: 12.5px;
    color: var(--text-secondary);
}

/* ── The paper ───────────────────────────────────────────────────────── */
.paperwrap {
    position: relative;
    overflow-x: auto;
}

/* Continuous-feed stock. Purely decorative, hence opt-in and hidden the moment
   the viewport is tight enough that 15px of margin is 15px of content. */
.paperwrap--sprockets::before,
.paperwrap--sprockets::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 15px;
    z-index: 1;
    pointer-events: none;
    background-image: radial-gradient(
        circle at 7.5px 8px,
        var(--rule-subtle) 2.6px,
        transparent 2.8px
    );
    background-size: 15px 25px;
}

.paperwrap--sprockets::before {
    left: 0;
}

.paperwrap--sprockets::after {
    right: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 610px;
}

/* ── Column headings ─────────────────────────────────────────────────── */
/* Mono and tracked out, because a heading over a column of figures is a label
   on a machine, not a sentence. */
.reg-head {
    padding: 9px var(--cell-px, 14px);
    font-family: var(--mono-family);
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-align: left;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--rule-default);
    background: var(--surface-raised);
}

.reg-head:first-child {
    padding-left: 26px;
}

.reg-head:last-child {
    padding-right: 26px;
}

.reg-head--figure {
    text-align: right;
}

.reg-head--sortable {
    cursor: pointer;
    user-select: none;
}

.reg-head--sortable:hover {
    color: var(--text-primary);
}

.reg-head__inner {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Rows ────────────────────────────────────────────────────────────── */
/* The green bar. Banding is what lets an eye hold one row across a wide span
   of columns, which is the whole reason ledger paper was printed this way. */
.tbody--banded tr:nth-child(odd) {
    background: var(--surface-band);
}

/* Hover is an outline, not a fill — a fill would fight the banding and the
   row underneath would appear to change meaning as the pointer passed. */
.reg-row--hover:hover {
    outline: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    outline-offset: calc(var(--rule-w-base, 1.5px) * -1);
}

.reg-cell {
    padding: var(--cell-py, 11px) var(--cell-px, 14px);
    font-size: 13.5px;
    color: var(--text-primary);
    vertical-align: middle;
}

.reg-cell:first-child {
    padding-left: 26px;
}

.reg-cell:last-child {
    padding-right: 26px;
}

.reg-cell--wide {
    padding-block: calc(var(--cell-py, 11px) * 3);
}

/* When it happened is context, not content. */
.reg-cell--date {
    font-family: var(--mono-family);
    font-size: 12px;
    color: var(--text-secondary);
    white-space: nowrap;
}

/* A document number is an address, not a word. */
.reg-cell--ref {
    font-family: var(--mono-family);
    font-size: 11.5px;
    color: var(--text-metadata);
    white-space: nowrap;
}

/* Figures. Tabular even in mono, because mono guarantees advance width and
   `tnum` guarantees the shapes — a lining zero next to a slashed one still
   ruins a column otherwise. */
.reg-cell--in,
.reg-cell--out,
.reg-cell--amount {
    font-family: var(--mono-family);
    font-size: 13.5px;
    font-weight: 500;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum';
}

/* Deliberately identical to `in`. The heading says which way the money went;
   colour is not needed to repeat it, and red here would collide with the one
   meaning red is allowed to carry. */
.reg-cell--out {
    color: var(--amount-outflow);
}

.reg-cell--in {
    color: var(--amount-inflow);
}

/* An opened row is the same paper, set back a shade so the eye can see where
   the detail starts and stops without another rule being drawn. */
.reg-row--detail > td {
    background: var(--surface-band);
    border-bottom: 1px solid var(--rule-default);
}

/* ── The sum ─────────────────────────────────────────────────────────── */
/* Two rules, top and bottom, the way a column is closed off on paper. */
.reg-total {
    padding: 15px var(--cell-px, 14px);
    font-family: var(--mono-family);
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    border-top: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

.reg-total--figure {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

/* The label is language, so it keeps the body face. */
.reg-total--label {
    padding-left: 26px;
    font-family: var(--font-sans);
    font-size: 13px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.reg-total:last-child {
    padding-right: 26px;
}

/* ── Slips (narrow screens) ──────────────────────────────────────────── */
.slips {
    padding: 12px 16px 16px;
}

.slips__note {
    padding: 40px 0;
    text-align: center;
    font-size: 13px;
    color: var(--text-secondary);
}

.slip {
    padding: 12px 0;
    border-bottom: 1px solid var(--rule-subtle);
}

.slip:last-child {
    border-bottom: 0;
}

.slip__line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding: 3px 0;
}

.slip__label {
    font-family: var(--mono-family);
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.slip__value {
    text-align: right;
    font-size: 13.5px;
    color: var(--text-primary);
}

.slip__value--figure {
    font-family: var(--mono-family);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* ── Foot ────────────────────────────────────────────────────────────── */
.regfoot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 26px;
    border-top: 1px solid var(--rule-default);
}

.regfoot__count {
    font-size: 13px;
    color: var(--text-secondary);
}

.tabular {
    font-family: var(--mono-family);
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
}

@media (max-width: 600px) {
    .paperwrap--sprockets::before,
    .paperwrap--sprockets::after {
        display: none;
    }
}
</style>
