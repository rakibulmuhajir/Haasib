<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CircleDollarSign, Plus, ReceiptText, Search } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    expenses: any;
    summary: { total: number; outstanding: number };
    filters: { search?: string; status?: string };
}>();
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
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
        },
        { preserveState: true, replace: true },
    );
const openPage = (url?: string | null) => {
    if (url) router.get(url);
};
</script>

<template>
    <Head title="Travel Expenses" />
    <PageShell
        title="Expenses"
        description="Travel operating costs, supplier bills, and outstanding amounts."
        :breadcrumbs="breadcrumbs"
        :icon="ReceiptText"
    >
        <template #actions
            ><Button @click="router.get(`/${company.slug}/bills/create`)"
                ><Plus class="mr-2 h-4 w-4" />Add Expense</Button
            ></template
        >
        <div class="grid gap-4 md:grid-cols-2">
            <Card
                ><CardHeader
                    ><CardTitle class="text-base"
                        >Total expenses</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="summary.total"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card
                ><CardHeader
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><CircleDollarSign
                            class="h-4 w-4"
                        />Outstanding</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="summary.outstanding"
                        :currency="company.base_currency" /></CardContent
            ></Card>
        </div>
        <div class="flex flex-col gap-3 md:flex-row">
            <div class="relative flex-1">
                <Search
                    class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                /><Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Expense, invoice, or vendor"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Select v-model="status" @update:model-value="applyFilters"
                ><SelectTrigger class="w-full md:w-48"
                    ><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All statuses</SelectItem
                    ><SelectItem value="draft">Draft</SelectItem
                    ><SelectItem value="received">Unpaid</SelectItem
                    ><SelectItem value="partial">Partially paid</SelectItem
                    ><SelectItem value="overdue">Overdue</SelectItem
                    ><SelectItem value="paid">Paid</SelectItem></SelectContent
                ></Select
            >
            <Button variant="outline" @click="applyFilters">Apply</Button>
        </div>
        <div
            v-if="!expenses.data.length"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            No expenses found.
        </div>
        <div v-else class="space-y-2">
            <div
                v-for="expense in expenses.data"
                :key="expense.id"
                class="grid gap-3 border-b py-4 md:grid-cols-[150px_1fr_170px_180px] md:items-center"
            >
                <div>
                    <Button
                        variant="link"
                        class="h-auto p-0 font-medium"
                        @click="
                            router.get(`/${company.slug}/bills/${expense.id}`)
                        "
                        >{{ expense.bill_number }}</Button
                    >
                    <div class="text-xs text-muted-foreground">
                        {{ expense.bill_date }}
                    </div>
                </div>
                <div>
                    <div class="font-medium">
                        {{ expense.vendor?.name || 'No vendor' }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{
                            expense.vendor_invoice_number ||
                            'No supplier reference'
                        }}
                    </div>
                </div>
                <div>
                    <Badge variant="secondary">{{ expense.status }}</Badge>
                    <div
                        v-if="Number(expense.balance) > 0"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Balance
                        <MoneyText
                            :amount="expense.balance"
                            :currency="expense.currency"
                        />
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-semibold">
                        <MoneyText
                            :amount="expense.total_amount"
                            :currency="expense.currency"
                        />
                    </div>
                    <div
                        v-if="expense.currency !== expense.base_currency"
                        class="text-xs text-muted-foreground"
                    >
                        Rate {{ expense.exchange_rate }} ·
                        <MoneyText
                            :amount="expense.base_amount"
                            :currency="expense.base_currency"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="expenses.last_page > 1"
            class="flex items-center justify-between"
        >
            <Button
                variant="outline"
                :disabled="!expenses.prev_page_url"
                @click="openPage(expenses.prev_page_url)"
                >Previous</Button
            ><span class="text-sm text-muted-foreground"
                >Page {{ expenses.current_page }} of
                {{ expenses.last_page }}</span
            ><Button
                variant="outline"
                :disabled="!expenses.next_page_url"
                @click="openPage(expenses.next_page_url)"
                >Next</Button
            >
        </div>
    </PageShell>
</template>
