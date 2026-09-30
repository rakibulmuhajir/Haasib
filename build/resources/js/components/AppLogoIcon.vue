<script setup lang="ts">
/**
 * Haasib's mark: an H whose crossbar is the ledger line. Two half-uprights run solid into it --
 * above the line on the left, below it on the right: debit and credit, the two sides of every
 * entry. The other two stand apart as empty outlines, so the H is still whole. Turned upside
 * down it is the same mark: it balances.
 */
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" :class="className" v-bind="$attrs">
        <rect x="4" y="3" width="5" height="15.5" rx="1" fill="currentColor" />
        <rect x="23.75" y="3.75" width="3.5" height="7.5" rx="0.6" stroke-width="1.5" fill="none" stroke="currentColor" />
        <rect x="4" y="13.5" width="24" height="5" rx="1" fill="currentColor" />
        <rect x="4.75" y="20.75" width="3.5" height="7.5" rx="0.6" stroke-width="1.5" fill="none" stroke="currentColor" />
        <rect x="23" y="13.5" width="5" height="15.5" rx="1" fill="currentColor" />
    </svg>
</template>
