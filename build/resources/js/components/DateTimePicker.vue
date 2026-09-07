<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        min?: string;
        max?: string;
        label?: string;
        required?: boolean;
    }>(),
    {
        label: 'Flight',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const date = ref('');
const time = ref('');
const editing = ref(false);

const datePart = (value?: string) => (value ? value.slice(0, 10) : '');
const timePart = (value?: string) => (value ? value.slice(11, 16) : '');

watch(
    () => props.modelValue,
    (value) => {
        if (editing.value) return;
        date.value = datePart(value);
        time.value = timePart(value);
    },
    { immediate: true },
);

const minimumTime = computed(() =>
    date.value && date.value === datePart(props.min)
        ? timePart(props.min) || undefined
        : undefined,
);
const maximumTime = computed(() =>
    date.value && date.value === datePart(props.max)
        ? timePart(props.max) || undefined
        : undefined,
);

const updateModel = () => {
    emit(
        'update:modelValue',
        date.value && time.value ? `${date.value}T${time.value}` : '',
    );
};
</script>

<template>
    <div
        class="grid min-w-0 grid-cols-[minmax(0,1fr)_7rem] gap-2"
        @focusin="editing = true"
        @focusout="editing = false"
    >
        <Input
            v-model="date"
            type="date"
            class="rounded-none shadow-none"
            :aria-label="`${label} date`"
            :min="datePart(min) || undefined"
            :max="datePart(max) || undefined"
            :required="required"
            @update:model-value="updateModel"
        />
        <Input
            v-model="time"
            type="time"
            class="rounded-none tabular-nums shadow-none"
            :aria-label="`${label} time`"
            :min="minimumTime"
            :max="maximumTime"
            :required="required"
            step="300"
            @update:model-value="updateModel"
        />
    </div>
</template>
