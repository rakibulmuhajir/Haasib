<script setup>
import Button from 'primevue/button';

defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <Button :type="type" severity="secondary">
        <slot />
    </Button>
</template>
