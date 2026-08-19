<script setup lang="ts" generic="T extends Record<string, any>">
/**
 * DataTable — kept as the name 48 pages already import.
 *
 * The implementation moved to LedgerRegister.vue, which is what the thing
 * actually is. This file forwards to it so those pages inherit the register
 * grammar — banding, mono figures, right-aligned amounts, the double-ruled
 * total — without 48 edits landing in one commit.
 *
 * New code should import LedgerRegister directly. This shim exists to retire,
 * and the palette lint counts remaining importers so the number is visible
 * rather than forgotten.
 *
 * One behaviour deliberately differs: `striped` used to default to false, so
 * almost every table in the app rendered unbanded. Banding is now the default,
 * because a register without it is the thing that made these pages hard to
 * read. The prop is still accepted and still turns banding OFF when passed
 * false; it simply no longer has to be passed to turn it on.
 */
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'

interface Props {
    data: T[]
    columns: RegisterColumn<T>[]
    title?: string
    description?: string
    keyField?: keyof T
    loading?: boolean
    pagination?: {
        currentPage?: number
        perPage?: number
        total: number
        current_page?: number
        per_page?: number
        last_page?: number
    }
    density?: 'comfortable' | 'compact' | 'print'
    hoverable?: boolean
    clickable?: boolean
    /** Now means "band the rows", and now defaults to on. */
    striped?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    title: undefined,
    description: undefined,
    keyField: 'id' as keyof T,
    loading: false,
    pagination: undefined,
    density: undefined,
    hoverable: true,
    clickable: false,
    striped: true,
})

defineEmits<{
    sort: [column: keyof T | string, direction: 'asc' | 'desc' | null]
    'page-change': [page: number]
    'row-click': [row: T]
}>()
</script>

<template>
    <LedgerRegister
        :data="props.data"
        :columns="props.columns"
        :title="props.title"
        :description="props.description"
        :key-field="props.keyField"
        :loading="props.loading"
        :pagination="props.pagination"
        :density="props.density"
        :hoverable="props.hoverable"
        :clickable="props.clickable"
        :banded="props.striped"
        @sort="(c, d) => $emit('sort', c, d)"
        @page-change="(p) => $emit('page-change', p)"
        @row-click="(r) => $emit('row-click', r)"
    >
        <!-- Every named slot passes straight through, including the dynamic
             `cell-{key}` ones each page defines for itself. -->
        <template v-for="(_, name) in $slots" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps ?? {}" />
        </template>
    </LedgerRegister>
</template>
