<script setup>
import InputText from 'primevue/inputtext';

const model = defineModel({
    type: String,
    required: true,
});
</script>

<template>
    <InputText v-model="model" />
</template>
