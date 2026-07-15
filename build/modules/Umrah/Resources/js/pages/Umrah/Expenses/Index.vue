<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import DateTimeText from '@/components/DateTimeText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
        <Card>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Expense #</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Vendor</TableHead>
                  <TableHead>Supplier Ref.</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Total</TableHead>
                  <TableHead class="text-right">Balance</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableEmpty v-if="!expenses.data.length" :colspan="7">No expenses found.</TableEmpty>
                <TableRow v-for="expense in expenses.data" :key="expense.id">
                  <TableCell>
                    <Button
                        variant="link"
                        class="h-auto p-0 font-medium"
                        @click="
                            router.get(`/${company.slug}/bills/${expense.id}`)
                        "
                        >{{ expense.bill_number }}</Button
                    >
                  </TableCell>
                  <TableCell><DateTimeText :value="expense.bill_date" mode="date" /></TableCell>
                  <TableCell>
                    {{ expense.vendor?.name || '-' }}
                  </TableCell>
                  <TableCell>{{ expense.vendor_invoice_number || '-' }}</TableCell>
                  <TableCell>
                    <Badge variant="secondary" class="capitalize">{{ String(expense.status).replaceAll('_', ' ') }}</Badge>
                  </TableCell>
                  <TableCell class="text-right">
                    <MoneyText :amount="expense.total_amount" :currency="expense.currency" />
                  </TableCell>
                  <TableCell class="text-right font-medium"><MoneyText :amount="expense.balance" :currency="expense.currency" /></TableCell>
                </TableRow>
              </TableBody>
            </Table>
            <RecordPagination :current-page="expenses.current_page" :last-page="expenses.last_page" :from="expenses.from" :to="expenses.to" :total="expenses.total" :previous-url="expenses.prev_page_url" :next-url="expenses.next_page_url" />
          </CardContent>
        </Card>
    </PageShell>
</template>
