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
        <rect x="2" y="2" width="7" height="9.5" rx="1.2" fill="currentColor" />
        <rect x="24.0" y="3.0" width="5.0" height="7.5" rx="0.8" stroke-width="2.0" fill="none" stroke="currentColor" />
        <rect x="2" y="13" width="28" height="6" rx="1.2" fill="currentColor" />
        <rect x="3.0" y="21.5" width="5.0" height="7.5" rx="0.8" stroke-width="2.0" fill="none" stroke="currentColor" />
        <rect x="23" y="20.5" width="7" height="9.5" rx="1.2" fill="currentColor" />
    </svg>
</template>
