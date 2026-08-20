<script setup lang="ts">
/**
 * StatusBadge — one appearance per state, everywhere.
 *
 * The chip is a rule and a weight before it is a colour. The left edge is a
 * heavy rule tinted by tone, the label states the case in words, and voided or
 * reversed records are struck through. Read it in greyscale and nothing is
 * lost, which is the test every state indicator in this app has to pass.
 *
 * Deliberately not built on shadcn's Badge: that component fills its background
 * with a solid colour, which turns a table of statuses into a row of stickers
 * and puts colour in charge of meaning. Same tokens, different grammar.
 */
import { computed } from 'vue'
import { resolveStatus } from '@/lib/status'
import Explain from '@/components/Explain.vue'

const props = withDefaults(
    defineProps<{
        status: string | null | undefined
        /** Attach the glossary explainer when the state has one. */
        explain?: boolean
        /** Fall back to this when `status` is empty. */
        fallback?: string
    }>(),
    { explain: false, fallback: '—' },
)

const meta = computed(() => resolveStatus(props.status))
</script>

<template>
    <Explain
        v-if="meta && explain && meta.explain"
        :term="meta.explain"
        :label="meta.label"
    >
        <span class="status" :data-tone="meta.tone" :class="{ 'status--struck': meta.struck }">
            {{ meta.label }}
        </span>
    </Explain>

    <span
        v-else-if="meta"
        class="status"
        :data-tone="meta.tone"
        :class="{ 'status--struck': meta.struck }"
    >
        {{ meta.label }}
    </span>

    <span v-else class="status status--empty">{{ fallback }}</span>
</template>

<style scoped>
.status {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border: 1px solid var(--rule-default);
    border-left-width: 3px;
    border-radius: var(--radius);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
    color: inherit;
}

/* Tone tints the rule, not the fill. The label stays ink so it keeps its
   contrast ratio no matter which tone it lands on. */
.status[data-tone='neutral'] {
    border-left-color: var(--rule-emphasis);
}
.status[data-tone='info'] {
    border-left-color: var(--status-info);
}
.status[data-tone='success'] {
    border-left-color: var(--status-success);
}
.status[data-tone='attention'] {
    border-left-color: var(--status-attention);
}

/* Critical is the one tone allowed to colour the text as well — it is the
   only state where the app is asking for action rather than reporting. */
.status[data-tone='critical'] {
    border-left-color: var(--status-critical);
    color: var(--status-critical);
}

.status[data-tone='muted'] {
    border-left-color: var(--rule-default);
    color: var(--text-metadata);
}

/* Second non-colour indicator: this record no longer counts. */
.status--struck {
    text-decoration: line-through;
    text-decoration-thickness: 1px;
}

.status--empty {
    border-color: transparent;
    color: var(--text-metadata);
    font-weight: 400;
}
</style>
