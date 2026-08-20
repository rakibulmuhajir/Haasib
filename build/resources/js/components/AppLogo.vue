<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <!-- The one red mark in the chrome. Red is reserved for adverse everywhere
         else in the app, which is exactly what makes it work as a brand glyph:
         it appears once, in a place nothing else competes for. -->
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-status-critical text-status-critical-contrast"
    >
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="mb-0.5 truncate font-display text-base leading-tight font-bold">Haasib</span>
    </div>
</template>
