<script setup lang="ts">
/** Compatibility wrapper. New module integrations use Hint directly. */
import Hint from '@/components/Hint.vue'
defineProps<{ preview?: string }>()
defineEmits<{ explore: [] }>()
</script>

<template>
    <Hint :trail="true" :preview="preview" @explore="$emit('explore')"><slot /></Hint>
</template>
