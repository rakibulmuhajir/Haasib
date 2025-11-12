<template>
    <button
        :type="props.type"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
    >
        <slot />
    </button>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ type?: 'button' | 'submit' | 'reset' }>(), {
    type: 'button'
})
</script>
