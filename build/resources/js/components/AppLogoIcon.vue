<script setup lang="ts">
/**
 * Haasib's mark: an H in three parts, its crossbar the ledger line. Of the four half-uprights,
 * two are solid -- above the line on the left, below it on the right: debit and credit -- and the
 * other two stay as empty outlines, so the H is still whole. A gap keeps each upright off the line.
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
        <rect x="4" y="3" width="5" height="9" rx="1" fill="currentColor" />
        <rect x="23.6" y="3.6" width="3.8" height="7.8" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2" />
        <rect x="4" y="13.5" width="24" height="5" rx="1" fill="currentColor" />
        <rect x="4.6" y="20.6" width="3.8" height="7.8" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2" />
        <rect x="23" y="20" width="5" height="9" rx="1" fill="currentColor" />
    </svg>
</template>
