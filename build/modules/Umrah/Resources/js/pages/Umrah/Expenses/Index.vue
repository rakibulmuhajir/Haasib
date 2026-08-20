<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card';
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

/**
 * The expense number is the row's reference; description and an optional
 * source reference ride along under it rather than claiming columns of their
 * own. Status is one of two book states -- posted or reversed -- so it goes
 * through the shared vocabulary rather than a page-local badge.
 */
const columns = [
    { key: 'expense_number', label: 'Expense', kind: 'ref' as const },
    { key: 'expense_date', label: 'Date', kind: 'date' as const },
    { key: 'category', label: 'Category', kind: 'text' as const },
    { key: 'payee', label: 'Paid to', kind: 'text' as const },
    { key: 'payment_account', label: 'Paid from', kind: 'text' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'amount', label: 'Amount', kind: 'amount' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

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
            <Card variant="figure"
                ><CardHeader
                    ><CardTitle
                        >Posted expenses</CardTitle
                    ></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="summary.total"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
            <Card variant="figure"
                ><CardHeader
                    ><CardTitle
                        >Records</CardTitle
                    ></CardHeader
                ><CardContent
                    ><CardFigure>{{
                        summary.count
                    }}</CardFigure></CardContent></Card
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

        <Card variant="register">
            <CardContent>
                <LedgerRegister :data="expenses.data" :columns="columns">
                    <template #empty>No expenses found.</template>

                    <template #cell-expense_number="{ row }">
                        <div class="font-medium">
                            {{ row.expense_number }}
                        </div>
                        <div
                            class="max-w-64 truncate text-xs text-muted-foreground"
                            :title="row.description"
                        >
                            {{ row.description }}
                        </div>
                        <div
                            v-if="row.reference"
                            class="text-xs text-muted-foreground"
                        >
                            Ref: {{ row.reference }}
                        </div>
                    </template>

                    <template #cell-expense_date="{ row }">
                        <DateTimeText :value="row.expense_date" mode="date" />
                    </template>

                    <template #cell-category="{ row }">{{
                        row.expense_account?.name || '—'
                    }}</template>

                    <template #cell-payee="{ row }">{{ row.payee || '—' }}</template>

                    <template #cell-payment_account="{ row }">{{
                        row.payment_account?.name || '—'
                    }}</template>

                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" />
                    </template>

                    <template #cell-amount="{ row }">
                        <MoneyText :amount="row.amount" :currency="row.currency" />
                        <div
                            v-if="row.currency !== row.base_currency"
                            class="text-xs text-muted-foreground"
                        >
                            <MoneyText :amount="row.base_amount" :currency="row.base_currency" />
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="canReverse && row.status === 'posted'"
                                size="icon"
                                variant="ghost"
                                title="Reverse expense"
                                @click="openReverse(row)"
                                ><RotateCcw class="h-4 w-4" /><span class="sr-only"
                                    >Reverse expense</span
                                ></Button
                            >
                        </div>
                    </template>
                </LedgerRegister>
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
