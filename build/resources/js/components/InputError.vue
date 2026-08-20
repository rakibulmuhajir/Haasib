<script setup lang="ts">
/**
 * InputError — the message that says what went wrong and, where the server
 * bothered to say so, how to fix it.
 *
 * Red is the colour here because a rejected field is genuinely adverse, but the
 * message itself is the non-colour indicator: nothing about this state depends
 * on the reader distinguishing red from ink.
 */
defineProps<{
    message?: string
}>()
</script>

<template>
    <!-- v-if rather than v-show: an empty <p> still reads as an element to a
         screen reader walking the form, and role="alert" on a hidden node is a
         promise the field cannot keep. -->
    <p v-if="message" class="input-error" role="alert">
        {{ message }}
    </p>
</template>

<style scoped>
.input-error {
    margin-top: 4px;
    font-size: 0.8125rem;
    line-height: 1.4;
    color: var(--status-critical);
}
</style>
