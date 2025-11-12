<template>
    <button
        :type="props.type"
        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
    >
        <slot />
    </button>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ type?: 'button' | 'submit' | 'reset' }>(), {
    type: 'button'
})
</script>
