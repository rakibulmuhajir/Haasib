<script setup lang="ts">
/**
 * DefinitionList — the label/value pairs that make up every detail page.
 *
 * A real <dl>, not a grid of divs. Screen readers announce the pairing, which
 * is the entire content of a detail page: this field, that value.
 *
 * Labels are metadata and are set as such — small, quiet, and never competing
 * with the value they name. Any label whose term needs explaining carries an
 * `explain` key and picks up the glossary affordance automatically.
 */
import Explain from '@/components/Explain.vue'

export interface DefinitionItem {
    term: string
    /** Omitted or null renders an em dash — "not set" is information too. */
    value?: string | number | null
    /** Glossary key, if the term warrants an explainer. */
    explain?: string
}

withDefaults(
    defineProps<{
        items?: DefinitionItem[]
        /**
         * `columns` puts the label beside the value and is right for dense
         * reference blocks. `stacked` puts it above, and is right when values
         * are long enough to wrap.
         */
        layout?: 'columns' | 'stacked'
    }>(),
    { items: () => [], layout: 'columns' },
)
</script>

<template>
    <dl class="deflist" :data-layout="layout">
        <slot>
            <template v-for="item in items" :key="item.term">
                <dt class="deflist__term" dir="auto">
                    <Explain v-if="item.explain" :term="item.explain" :label="item.term" />
                    <template v-else>{{ item.term }}</template>
                </dt>
                <dd class="deflist__value" dir="auto">
                    <template v-if="item.value === null || item.value === undefined || item.value === ''">
                        <span class="deflist__unset">—</span>
                    </template>
                    <template v-else>{{ item.value }}</template>
                </dd>
            </template>
        </slot>
    </dl>
</template>

<style scoped>
.deflist {
    display: grid;
    margin: 0;
    row-gap: var(--cell-py);
    column-gap: var(--space-4, 1rem);
}

/* Labels take a fixed share rather than sizing to content, so the values form
   a single column the eye can run down instead of a ragged left edge. */
.deflist[data-layout='columns'] {
    grid-template-columns: minmax(9rem, 34%) 1fr;
    align-items: baseline;
}

.deflist[data-layout='stacked'] {
    grid-template-columns: 1fr;
    row-gap: var(--space-1, 4px);
}

.deflist[data-layout='stacked'] .deflist__value {
    margin-bottom: var(--space-3, 0.75rem);
}

.deflist__term {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-metadata);
    min-width: 0;
}

/* dir="auto" on each term and value, not on the list. Urdu is RTL and will
   appear mixed with Latin invoice numbers and figures in the same list, so
   direction has to be resolved per item rather than declared for the page. */
.deflist__value {
    margin: 0;
    min-width: 0;
    overflow-wrap: anywhere;
}

/* Empty is not the same as zero, and neither is the same as an error. */
.deflist__unset {
    color: var(--text-metadata);
}

/* Below the fold of a phone, a two-column list stops being two columns. */
@media (max-width: 30rem) {
    .deflist[data-layout='columns'] {
        grid-template-columns: 1fr;
        row-gap: var(--space-1, 4px);
    }

    .deflist[data-layout='columns'] .deflist__value {
        margin-bottom: var(--space-3, 0.75rem);
    }
}
</style>
