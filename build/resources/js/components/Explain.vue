<script setup lang="ts">
/**
 * Explain — inline help, attached to the word that needs it.
 *
 * This is what the app got instead of an owner/accountant mode toggle. The
 * vocabulary stays singular and plain; anything a non-accountant might not know
 * carries one of these, and the explanation arrives where the confusion is
 * rather than in a manual nobody opens.
 *
 * Deliberately a <button> inside a popover rather than a hover tooltip. Help
 * that only appears on hover is unreachable by keyboard, unreachable on touch,
 * and vanishes the moment you move toward it. This one takes focus, opens on
 * Enter, and stays open until dismissed.
 *
 * The affordance is a dotted underline, never an icon in a circle. A row of
 * question marks down a form reads as an apology for the form.
 */
import { computed } from 'vue'
import { lookup } from '@/lib/glossary'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const props = defineProps<{
    /** Glossary key. See lib/glossary.ts. */
    term: string
    /** Visible text. Defaults to the glossary label; the slot overrides both. */
    label?: string
}>()

const entry = computed(() => lookup(props.term))
const text = computed(() => props.label ?? entry.value?.label ?? props.term)

// `see also` recurses into this component; <script setup> resolves the
// self-reference from the filename, so no explicit name registration is needed.
const seeAlso = computed(() => entry.value?.see ?? [])
</script>

<template>
    <!-- No entry means no affordance. A dotted underline that explains nothing
         is worse than plain text, so the term renders bare and the missing
         glossary key stays a content bug rather than becoming a UI one. -->
    <span v-if="!entry">
        <slot>{{ text }}</slot>
    </span>

    <Popover v-else>
        <PopoverTrigger as-child>
            <button type="button" class="explain" :aria-label="`What does ${text} mean?`">
                <slot>{{ text }}</slot>
            </button>
        </PopoverTrigger>

        <PopoverContent class="explain-panel" align="start" :side-offset="6">
            <p class="explain-panel__term">
                {{ entry.label }}
                <span v-if="entry.also" class="explain-panel__also">also called {{ entry.also }}</span>
            </p>

            <p class="explain-panel__short">{{ entry.short }}</p>

            <!-- The part a renamed heading could never have told anyone. -->
            <p v-if="entry.why" class="explain-panel__why">{{ entry.why }}</p>

            <p v-if="seeAlso.length" class="explain-panel__see">
                See also
                <template v-for="(key, i) in seeAlso" :key="key">
                    <Explain :term="key" /><template v-if="i < seeAlso.length - 1">, </template>
                </template>
            </p>
        </PopoverContent>
    </Popover>
</template>

<style scoped>
.explain {
    display: inline;
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    color: inherit;
    text-align: inherit;
    cursor: help;
    text-decoration: underline dotted;
    text-underline-offset: 3px;
    text-decoration-thickness: 1px;
    text-decoration-color: var(--text-metadata);
}

.explain:hover {
    text-decoration-color: currentColor;
}

.explain:focus-visible {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}

.explain-panel__term {
    font-weight: 600;
    margin-bottom: 6px;
}

/* Naming the formal term teaches the word without making it the price of entry. */
.explain-panel__also {
    display: block;
    font-weight: 400;
    font-size: 12px;
    color: var(--text-metadata);
}

.explain-panel__short {
    font-size: 13px;
    line-height: 1.5;
}

/* Set off by a rule rather than a colour: it is a second thought, not a warning. */
.explain-panel__why {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--rule-subtle);
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-metadata);
}

.explain-panel__see {
    margin-top: 10px;
    font-size: 12px;
    color: var(--text-metadata);
}
</style>
