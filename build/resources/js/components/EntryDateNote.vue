<script setup lang="ts">
/**
 * Says so when an entry is dated anything other than today.
 *
 * The date decides which daily close the entry belongs to, and forms now start on the last
 * date used rather than always on today. That is what makes back-filling a month from a
 * register practical, and it is also the one way it could surprise someone doing ordinary
 * same-day work. This line is the guard: the date on screen is never not-today silently.
 */
import { computed } from 'vue'
import { localToday } from '@/composables/useEntryDate'

const props = defineProps<{ date: string | null | undefined }>()

const label = computed(() => {
    if (!props.date || props.date === localToday()) return null

    const d = new Date(`${props.date}T00:00:00`)
    if (Number.isNaN(d.getTime())) return null

    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
})
</script>

<template>
    <p v-if="label" class="mt-1 text-xs text-status-attention">
        Dated {{ label }} — not today. This entry counts for that day.
    </p>
</template>
