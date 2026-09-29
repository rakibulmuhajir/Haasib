<script setup lang="ts">
/**
 * Haasib's mark: an H whose crossbar is the ledger line and whose uprights are each only half
 * there -- one above the line on the left, one below it on the right. Debit and credit, the two
 * sides of every entry, joined by the line that makes them balance.
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
        <rect x="4" y="13.5" width="24" height="5" rx="1" fill="currentColor" />
        <rect x="23" y="13.5" width="5" height="15.5" rx="1" fill="currentColor" />
    </svg>
</template>
