<script setup lang="ts">
import { Button } from '@/components/ui/button'
</script>

<template>
    <Button type="button" variant="ghost" class="explanation-trigger">
        <span class="explanation-trigger__label"><slot /></span>
    </Button>
</template>

<style scoped>
.explanation-trigger {
    display: inline;
    height: auto;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: none;
    box-shadow: none;
    font: inherit;
    color: inherit;
    text-align: inherit;
    white-space: inherit;
    cursor: help;
}
.explanation-trigger:hover { background: none; color: inherit; }
/* A border stays visible around inline-flex amounts such as MoneyText, where
   an ancestor's text-decoration does not propagate to the digits. */
.explanation-trigger__label {
    border-bottom: 1px dotted var(--text-metadata, currentColor);
    padding-bottom: 2px;
}
.explanation-trigger:hover .explanation-trigger__label { border-bottom-color: currentColor; }
.explanation-trigger:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 3px; }
</style>
