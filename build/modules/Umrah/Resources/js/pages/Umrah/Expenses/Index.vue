<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, ReceiptText, RotateCcw, Search } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    expenses: any;
    summary: { total: number; count: number };
    filters: { search?: string; status?: string; from?: string; to?: string };
    canCreate: boolean;
    canReverse: boolean;
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const reversing = ref<any | null>(null);
const reverseForm = useForm({ reason: '' });
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Expenses', href: `/${props.company.slug}/umrah/expenses` },
];

const applyFilters = () =>
    router.get(
        `/${props.company.slug}/umrah/expenses`,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );

const openReverse = (expense: any) => {
    reverseForm.reset();
    reverseForm.clearErrors();
    reversing.value = expense;
};

const reverseExpense = () => {
    if (!reversing.value) return;
    reverseForm.post(
        `/${props.company.slug}/umrah/expenses/${reversing.value.id}/reverse`,
        {
            preserveScroll: true,
            onSuccess: () => {
                reversing.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Travel Expenses" />
    <PageShell
        title="Expenses"
        description="Travel costs paid from company cash, bank, or card accounts."
        :breadcrumbs="breadcrumbs"
        :icon="ReceiptText"
    >
        <template #actions>
            <Button
                v-if="canCreate"
                @click="router.get(`/${company.slug}/umrah/expenses/create`)"
                ><Plus class="mr-2 h-4 w-4" />Record expense</Button
            >
        </template>

        <div class="grid gap-4 md:grid-cols-2">
            <Card
                ><CardHeader
                    ><CardTitle class="text-base"
                        >Posted expenses</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="summary.total"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card
                ><CardHeader
                    ><CardTitle class="text-base"
                        >Records</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold">{{
                    summary.count
                }}</CardContent></Card
            >
        </div>

        <div
            class="grid gap-3 lg:grid-cols-[minmax(14rem,1fr)_10rem_10rem_11rem_auto]"
        >
            <div class="relative">
                <Search
                    class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Number, payee, description, reference"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Input v-model="from" type="date" aria-label="From date" />
            <Input v-model="to" type="date" aria-label="To date" />
            <Select v-model="status" @update:model-value="applyFilters">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All statuses</SelectItem
                    ><SelectItem value="posted">Posted</SelectItem
                    ><SelectItem value="reversed"
                        >Reversed</SelectItem
                    ></SelectContent
                >
            </Select>
            <Button variant="outline" @click="applyFilters">Apply</Button>
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader
                        ><TableRow
                            ><TableHead>Expense</TableHead
                            ><TableHead>Date</TableHead
                            ><TableHead>Category</TableHead
                            ><TableHead>Paid to</TableHead
                            ><TableHead>Paid from</TableHead
                            ><TableHead>Status</TableHead
                            ><TableHead class="text-right">Amount</TableHead
                            ><TableHead class="w-16"
                                ><span class="sr-only">Action</span></TableHead
                            ></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableEmpty v-if="!expenses.data.length" :colspan="8"
                            >No expenses found.</TableEmpty
                        >
                        <TableRow
                            v-for="expense in expenses.data"
                            :key="expense.id"
                        >
                            <TableCell
                                ><div class="font-medium">
                                    {{ expense.expense_number }}
                                </div>
                                <div
                                    class="max-w-64 truncate text-xs text-muted-foreground"
                                    :title="expense.description"
                                >
                                    {{ expense.description }}
                                </div>
                                <div
                                    v-if="expense.reference"
                                    class="text-xs text-muted-foreground"
                                >
                                    Ref: {{ expense.reference }}
                                </div></TableCell
                            >
                            <TableCell
                                ><DateTimeText
                                    :value="expense.expense_date"
                                    mode="date"
                            /></TableCell>
                            <TableCell>{{
                                expense.expense_account?.name || '-'
                            }}</TableCell>
                            <TableCell>{{ expense.payee || '-' }}</TableCell>
                            <TableCell>{{
                                expense.payment_account?.name || '-'
                            }}</TableCell>
                            <TableCell
                                ><Badge
                                    :variant="
                                        expense.status === 'reversed'
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                    class="capitalize"
                                    >{{ expense.status }}</Badge
                                ></TableCell
                            >
                            <TableCell class="text-right"
                                ><MoneyText
                                    :amount="expense.amount"
                                    :currency="expense.currency" />
                                <div
                                    v-if="
                                        expense.currency !==
                                        expense.base_currency
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    <MoneyText
                                        :amount="expense.base_amount"
                                        :currency="expense.base_currency"
                                    /></div
                            ></TableCell>
                            <TableCell
                                ><Button
                                    v-if="
                                        canReverse &&
                                        expense.status === 'posted'
                                    "
                                    size="icon"
                                    variant="ghost"
                                    title="Reverse expense"
                                    @click="openReverse(expense)"
                                    ><RotateCcw class="h-4 w-4" /><span
                                        class="sr-only"
                                        >Reverse expense</span
                                    ></Button
                                ></TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
                <RecordPagination
                    :current-page="expenses.current_page"
                    :last-page="expenses.last_page"
                    :from="expenses.from"
                    :to="expenses.to"
                    :total="expenses.total"
                    :previous-url="expenses.prev_page_url"
                    :next-url="expenses.next_page_url"
                />
            </CardContent>
        </Card>
    </PageShell>

    <Dialog
        :open="Boolean(reversing)"
        @update:open="
            (open) => {
                if (!open) reversing = null;
            }
        "
    >
        <DialogContent>
            <DialogHeader
                ><DialogTitle
                    >Reverse {{ reversing?.expense_number }}</DialogTitle
                ><DialogDescription
                    >This creates an opposite accounting entry. The original
                    record remains visible.</DialogDescription
                ></DialogHeader
            >
            <div class="space-y-2">
                <Label for="reversal-reason">Reason</Label
                ><Textarea
                    id="reversal-reason"
                    v-model="reverseForm.reason"
                    rows="4"
                    placeholder="Explain why this expense is being reversed"
                />
                <p
                    v-if="reverseForm.errors.reason"
                    class="text-sm text-destructive"
                >
                    {{ reverseForm.errors.reason }}
                </p>
                <p
                    v-if="reverseForm.errors.expense"
                    class="text-sm text-destructive"
                >
                    {{ reverseForm.errors.expense }}
                </p>
            </div>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="reverseForm.processing"
                    @click="reversing = null"
                    >Cancel</Button
                ><Button
                    variant="destructive"
                    :disabled="reverseForm.processing"
                    @click="reverseExpense"
                    ><RotateCcw class="mr-2 h-4 w-4" />{{
                        reverseForm.processing
                            ? 'Reversing...'
                            : 'Reverse expense'
                    }}</Button
                ></DialogFooter
            >
        </DialogContent>
    </Dialog>
</template>
