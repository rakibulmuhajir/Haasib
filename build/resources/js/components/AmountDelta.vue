<script setup lang="ts">
/**
 * A figure that is about to change, shown as the change.
 *
 * Editing a group's accounting used to move the numbers in the summary
 * with no record of what they had been, so the one question being asked
 * -- what am I about to do to this group -- was the one thing the screen
 * could not answer. The old figure stays visible, struck through, until
 * the change is saved.
 *
 * The new figure keeps the line it always had and the history sits under
 * it in small type: a summary row is read down its right edge, and an old
 * value spliced in beside the new one pushes the column out of line just
 * when the reader most needs to compare figures.
 *
 * Unchanged is the common case and renders as a plain amount, so this can
 * stand in for MoneyText anywhere a form feeds a summary.
 */
import MoneyText from '@/components/MoneyText.vue'
import { computed } from 'vue'

const props = withDefaults(
    defineProps<{
        before: number | string | null | undefined
        after: number | string | null | undefined
        currency: string
        /** For the one figure a card exists to deliver. */
        scale?: 'default' | 'conclusion'
        /** `end` for a summary column; `start` under the field being edited. */
        align?: 'end' | 'start'
    }>(),
    { scale: 'default', align: 'end' },
)

const toNumber = (value: number | string | null | undefined) =>
    Math.round((Number(value ?? 0) || 0) * 100) / 100

const changed = computed(() => toNumber(props.before) !== toNumber(props.after))
const delta = computed(() => Math.round((toNumber(props.after) - toNumber(props.before)) * 100) / 100)
</script>

<template>
    <MoneyText v-if="!changed" :amount="after" :currency="currency" :scale="scale" />
    <span
        v-else
        class="-my-0.5 inline-flex flex-col rounded-sm bg-muted px-1.5 py-0.5 leading-tight"
        :class="align === 'end' ? 'items-end text-right' : 'items-start'"
    >
        <MoneyText :amount="after" :currency="currency" :scale="scale" />
        <span class="text-xs font-normal tabular-nums text-muted-foreground">
            <span class="line-through">
                <MoneyText :amount="before" :currency="currency" :show-currency="false" />
            </span>
            <span class="px-1" aria-hidden="true">·</span>
            <span>{{ delta > 0 ? '+' : '−' }}</span
            ><MoneyText :amount="Math.abs(delta)" :currency="currency" :show-currency="false" />
        </span>
    </span>
</template>
