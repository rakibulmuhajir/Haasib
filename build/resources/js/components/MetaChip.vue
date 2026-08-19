<script setup lang="ts">
/**
 * MetaChip — the ledger's marginal annotation.
 *
 * The small tick beside an entry that says how much time is involved: "3 MIN",
 * "7 DAYS", "15 DAYS LATE". Also serves add-on markers, folio references and
 * any other scrap of metadata that needs to sit next to content without
 * competing with it.
 *
 * It is beige on purpose. An annotation is a note in the margin, not a siren —
 * the moment every chip on a page shouts, none of them is read twice. Only the
 * `late` tone inverts to a solid fill, because a thing that is already overdue
 * has stopped being an annotation and become the point.
 *
 * The text is the indicator. Read the page in greyscale and "15 DAYS LATE"
 * still says what it says; the colour is agreement, not information.
 */
type Tone = 'neutral' | 'attention' | 'late' | 'info' | 'success'

withDefaults(
    defineProps<{
        /**
         * How much attention this deserves. `attention` is the beige default
         * for a deadline that is approaching; `late` is the solid red for one
         * that has passed.
         */
        tone?: Tone
        /** Drop the wash and keep only the text — for folio refs and IDs. */
        bare?: boolean
    }>(),
    { tone: 'attention', bare: false },
)
</script>

<template>
    <span class="chip" :data-tone="tone" :class="{ 'chip--bare': bare }">
        <slot />
    </span>
</template>

<style scoped>
.chip {
    display: inline-block;
    padding: 2px 6px;
    font-family: var(--mono-family);
    font-size: 10px;
    font-weight: 500;
    line-height: 1.5;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    white-space: nowrap;
    border-radius: var(--radius-sm);
    background: var(--status-attention-soft);
    color: var(--status-attention);
}

.chip[data-tone='neutral'] {
    background: var(--surface-sunken);
    color: var(--text-secondary);
}

.chip[data-tone='info'] {
    background: var(--status-info-soft);
    color: var(--status-info);
}

.chip[data-tone='success'] {
    background: var(--status-success-soft);
    color: var(--status-success);
}

/* The one tone that fills. Past due is not a note in the margin. */
.chip[data-tone='late'] {
    background: var(--status-critical);
    color: var(--status-critical-contrast);
    font-weight: 600;
}

/* A reference number wants the typeface and the tracking, not the wash. */
.chip--bare {
    padding-inline: 0;
    background: transparent;
    color: var(--text-metadata);
}
</style>
