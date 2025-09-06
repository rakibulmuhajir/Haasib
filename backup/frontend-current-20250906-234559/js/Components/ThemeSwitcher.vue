<script setup>
import { computed } from 'vue';
import { useTheme } from '../utils/theme';

const { theme, font, cycleTheme, cycleFont } = useTheme();

const currentIcon = computed(() => {
    if (theme.value === 'light') return 'sun';
    if (theme.value === 'dark') return 'moon';
    return 'computer-desktop';
});
</script>

<template>
    <div class="flex space-x-2">
        <button
            @click="cycleTheme"
            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out"
        >
            <span class="sr-only">Switch theme</span>

            <!-- Sun Icon -->
            <svg v-if="currentIcon === 'sun'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>

            <!-- Moon Icon -->
            <svg v-if="currentIcon === 'moon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>

            <!-- System Icon -->
            <svg v-if="currentIcon === 'computer-desktop'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </button>
        <button
            @click="cycleFont"
            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out"
        >
            <span class="sr-only">Switch font</span>
            <span :class="{'font-serif': font === 'serif', 'font-mono': font === 'mono', 'font-sans': font === 'sans'}">A</span>
        </button>
    </div>
</template>
