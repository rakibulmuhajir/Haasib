<script setup lang="ts">
/**
 * Hint — the working behind a figure, or the why behind a field, kept out of the
 * way until asked for.
 *
 * Explain is for words (a glossary entry, the same everywhere). Hint is for this
 * screen's own detail: "7000 delivered + 2000 opening − 1836 sold", or why a
 * reading is taken the morning after. The short version stays on screen; the
 * long one lives here.
 *
 * A tooltip, but not hover-only: hover opens it on a desk, a tap opens it on a
 * phone (where the daily close is actually entered), Enter opens it from the
 * keyboard, Escape or a tap elsewhere closes it. Same affordance as Explain — a
 * dotted underline, never an icon in a circle.
 */
import { ref } from 'vue'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

withDefaults(defineProps<{ side?: 'top' | 'right' | 'bottom' | 'left' }>(), { side: 'top' })

const open = ref(false)
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip v-model:open="open" disable-closing-trigger>
            <TooltipTrigger as-child>
                <button type="button" class="hint" @click="open = true">
                    <slot />
                </button>
            </TooltipTrigger>
            <TooltipContent :side="side" class="max-w-xs space-y-1 text-left leading-relaxed">
                <slot name="content" />
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>

<style scoped>
.hint {
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

.hint:hover {
    text-decoration-color: currentColor;
}

.hint:focus-visible {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}
</style>
