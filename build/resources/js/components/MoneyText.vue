<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    amount: number | string;
    currency: string;
    locale?: string;
};

const props = withDefaults(defineProps<Props>(), {
    locale: 'en-US',
});

const parts = computed(() => {
    const formatter = new Intl.NumberFormat(props.locale, {
        style: 'currency',
        currency: props.currency,
        currencyDisplay: 'narrowSymbol',
    });

    const amount = Number(props.amount);
    const formattedParts = formatter.formatToParts(
        Number.isFinite(amount) ? amount : 0,
    );
    const currencyPart =
        formattedParts.find((p) => p.type === 'currency')?.value ??
        props.currency;
    const value = formattedParts
        .filter((p) => p.type !== 'currency')
        .map((p) => p.value)
        .join('')
        .trim();

    return { currency: currencyPart, value };
});
</script>

<template>
    <span class="inline-flex items-baseline gap-1 tabular-nums">
        <span class="text-[0.65em] text-zinc-500">{{ parts.currency }}</span>
        <span>{{ parts.value }}</span>
    </span>
</template>
