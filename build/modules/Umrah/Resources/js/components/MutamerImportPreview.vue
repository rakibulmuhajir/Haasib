<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { computed, ref, watch } from 'vue';

type Row = {
    source_row: number;
    full_name: string;
    passport_number: string;
    imported_age: number | null;
    nationality: string;
    errors: string[];
};
const props = defineProps<{
    rows: Row[];
    existingPassports: string[];
    existingCount: number;
}>();
const emit = defineEmits<{ add: [rows: Row[]] }>();
const open = ref(false);
const page = ref(0);
const selected = ref<number[]>([]);
const normalize = (value: string) => value.replace(/\s/g, '').toUpperCase();
const existing = computed(
    () => new Set(props.existingPassports.map(normalize).filter(Boolean)),
);
const issues = (row: Row) => [
    ...row.errors,
    ...(existing.value.has(normalize(row.passport_number))
        ? ['Passport already exists in this form.']
        : []),
];
const chosen = computed(() =>
    props.rows.filter((row) => selected.value.includes(row.source_row)),
);
const blocked = computed(
    () =>
        !chosen.value.length ||
        chosen.value.some((row) => issues(row).length) ||
        props.existingCount + chosen.value.length > 500,
);
const visible = computed(() =>
    props.rows.slice(page.value * 50, (page.value + 1) * 50),
);
watch(
    () => props.rows,
    (rows) => {
        if (!rows.length) return;
        selected.value = rows.map((row) => row.source_row);
        page.value = 0;
        open.value = true;
    },
    { immediate: true },
);
const confirm = () => {
    if (blocked.value) return;
    emit('add', chosen.value);
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>Review imported passengers</DialogTitle>
                <DialogDescription
                    >{{ rows.length }} spreadsheet rows. Nothing has been added
                    or charged. Correct errors in the workbook and upload again,
                    or explicitly untick rows to exclude
                    them.</DialogDescription
                >
            </DialogHeader>
            <p class="text-sm">
                {{ chosen.length }} selected ·
                {{ rows.length - chosen.length }} excluded. Repeat passports on
                other journeys are allowed.
            </p>
            <div class="overflow-x-auto">
                <Table class="w-full text-left text-sm">
                    <TableHeader
                        ><TableRow
                            ><TableHead>Add</TableHead><TableHead>Row</TableHead
                            ><TableHead>Name / passport</TableHead
                            ><TableHead>Age</TableHead
                            ><TableHead>Nationality</TableHead
                            ><TableHead>Check</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody
                        ><TableRow
                            v-for="row in visible"
                            :key="row.source_row"
                            class="align-top"
                        >
                            <TableCell
                                ><Checkbox
                                    :aria-label="`Include spreadsheet row ${row.source_row}`"
                                    :model-value="
                                        selected.includes(row.source_row)
                                    "
                                    @update:model-value="
                                        (value) =>
                                            (selected =
                                                value === true
                                                    ? [
                                                          ...selected,
                                                          row.source_row,
                                                      ]
                                                    : selected.filter(
                                                          (id) =>
                                                              id !==
                                                              row.source_row,
                                                      ))
                                    "
                            /></TableCell>
                            <TableCell>{{ row.source_row }}</TableCell>
                            <TableCell class="break-words"
                                >{{ row.full_name || 'Name missing' }}<br />{{
                                    row.passport_number || 'Passport missing'
                                }}</TableCell
                            >
                            <TableCell>{{ row.imported_age ?? '—' }}</TableCell
                            ><TableCell>{{ row.nationality }}</TableCell>
                            <TableCell
                                ><span v-if="!issues(row).length">Ready</span>
                                <p
                                    v-for="issue in issues(row)"
                                    :key="issue"
                                    class="text-destructive"
                                >
                                    {{ issue }}
                                </p></TableCell
                            >
                        </TableRow></TableBody
                    >
                </Table>
            </div>
            <div class="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="page === 0"
                    @click="page--"
                    >Previous</Button
                ><span class="text-sm"
                    >Page {{ page + 1 }} of
                    {{ Math.ceil(rows.length / 50) }}</span
                ><Button
                    type="button"
                    variant="outline"
                    :disabled="(page + 1) * 50 >= rows.length"
                    @click="page++"
                    >Next</Button
                >
            </div>
            <p
                v-if="existingCount + chosen.length > 500"
                role="alert"
                class="text-sm text-destructive"
            >
                A group can contain at most 500 passengers.
            </p>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="open = false"
                    >Cancel</Button
                ><Button type="button" :disabled="blocked" @click="confirm"
                    >Add {{ chosen.length }} passengers to form</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
