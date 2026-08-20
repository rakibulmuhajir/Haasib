<script setup lang="ts">
import LedgerRegister from '@/components/LedgerRegister.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageShell from '@/components/PageShell.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/datetime';
import { currencySymbol } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { useCompanyRoute } from '@/composables/useCompanyRoute';
import {
    AlertTriangle,
    CheckCircle,
    Clock,
    LoaderCircle,
    Search,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MoneyText from '@/components/MoneyText.vue';

interface Advance {
    id: string;
    employee_id: string;
    employee_name: string;
    employee_position: string | null;
    advance_date: string;
    amount: number;
    amount_recovered: number;
    amount_outstanding: number;
    status: string;
    reason: string | null;
    payment_method: string;
}

interface Employee {
    id: string;
    name: string;
    position: string | null;
    base_salary: number;
    base_salary_in_company_currency: number;
    currency: string;
}

interface PaymentAccount {
    id: string;
    code: string;
    name: string;
}

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface Stats {
    total_advances: number;
    total_amount: number;
    total_outstanding: number;
    total_recovered: number;
    pending_count: number;
    partially_recovered_count: number;
}

const props = defineProps<{
    company: CompanyRef;
    advances: Advance[];
    employees: Employee[];
    stats: Stats;
    currency: string;
    usesDailyClose: boolean;
    paymentAccounts: PaymentAccount[];
}>();

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: `/${companySlug.value}` },
    { title: 'Salary Advances', href: `/${companySlug.value}/salary-advances` },
]);

const currency = computed(() => currencySymbol(props.currency));

const search = ref('');
const statusFilter = ref('all');
const employeeFilter = ref('all');

const advanceForm = useForm({
    employee_id: '',
    advance_date: new Date().toISOString().slice(0, 10),
    amount: null as number | null,
    payment_method: 'cash',
    bank_account_id: props.paymentAccounts[0]?.id || '',
    reference: '',
    reason: '',
});

const selectedEmployee = computed(() =>
    props.employees.find((employee) => employee.id === advanceForm.employee_id),
);
const advanceMonths = computed(() => {
    const salary = selectedEmployee.value?.base_salary_in_company_currency || 0;
    const amount = Number(advanceForm.amount || 0);
    return salary > 0 && amount > 0 ? amount / salary : 0;
});
const estimatedRecoveryPayrolls = computed(() =>
    advanceMonths.value > 0 ? Math.ceil(advanceMonths.value * 2) : 0,
);
const submitAdvance = () => {
    advanceForm.post(`/${props.company.slug}/salary-advances`, {
        preserveScroll: true,
        onSuccess: () => {
            advanceForm.reset('employee_id', 'amount', 'reference', 'reason');
        },
    });
};

const filteredAdvances = computed(() => {
    return props.advances.filter((adv) => {
        // Status filter
        if (statusFilter.value !== 'all' && adv.status !== statusFilter.value)
            return false;

        // Employee filter
        if (
            employeeFilter.value !== 'all' &&
            adv.employee_id !== employeeFilter.value
        )
            return false;

        // Search filter
        const q = search.value.trim().toLowerCase();
        if (!q) return true;
        return (
            adv.employee_name.toLowerCase().includes(q) ||
            adv.reason?.toLowerCase().includes(q)
        );
    });
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatDate = (dateStr: string) => {
    return formatDateTime(dateStr, { mode: 'date' });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return {
                class: 'bg-status-attention/10 text-status-attention',
                label: 'Outstanding',
            };
        case 'partially_recovered':
            return {
                class: 'bg-status-info/10 text-status-info',
                label: 'Partly recovered',
            };
        case 'fully_recovered':
            return {
                class: 'bg-status-success/10 text-status-success',
                label: 'Recovered',
            };
        case 'cancelled':
            return { class: 'bg-surface-sunken text-text-primary', label: 'Cancelled' };
        default:
            return { class: 'bg-surface-sunken text-text-primary', label: status };
    }
};

const columns = [
    { key: 'date', label: 'Date', kind: 'date' as const },
    { key: 'employee', label: 'Employee', kind: 'text' as const },
    { key: 'amount', label: 'Amount', kind: 'out' as const },
    { key: 'recovered', label: 'Recovered', kind: 'in' as const },
    { key: 'outstanding', label: 'Outstanding', kind: 'amount' as const },
    { key: 'status', label: 'Recovery', kind: 'status' as const },
];

const tableData = computed(() => {
    return filteredAdvances.value.map((adv) => ({
        id: adv.id,
        date: formatDate(adv.advance_date),
        employee: adv.employee_name,
        amount: `${currency.value} ${formatCurrency(adv.amount)}`,
        recovered: `${currency.value} ${formatCurrency(adv.amount_recovered)}`,
        outstanding: `${currency.value} ${formatCurrency(adv.amount_outstanding)}`,
        status: adv.status,
        _raw: adv,
    }));
});

const recoveryPercentage = computed(() => {
    if (props.stats.total_amount === 0) return 0;
    return Math.round(
        (props.stats.total_recovered / props.stats.total_amount) * 100,
    );
});
</script>

<template>
    <Head title="Salary Advances" />

    <PageShell
        title="Salary Advances"
        :description="
            usesDailyClose
                ? 'View advances recorded from Daily Close. Recovery happens automatically through payroll deductions.'
                : 'Record employee advances and recover them automatically through future payrolls.'
        "
        :icon="Wallet"
        :breadcrumbs="breadcrumbs"
    >
        <Card v-if="!usesDailyClose" class="border-border/80">
            <CardHeader>
                <CardTitle class="text-base">Record Advance</CardTitle>
                <CardDescription
                    >The amount is not limited by salary. Recovery is capped
                    during each payroll.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 lg:grid-cols-12"
                    @submit.prevent="submitAdvance"
                >
                    <div class="space-y-2 lg:col-span-3">
                        <Label for="advance-employee">Employee</Label>
                        <Select v-model="advanceForm.employee_id">
                            <SelectTrigger
                                id="advance-employee"
                                :aria-invalid="
                                    Boolean(advanceForm.errors.employee_id)
                                "
                            >
                                <SelectValue placeholder="Select employee" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="employee in employees"
                                    :key="employee.id"
                                    :value="employee.id"
                                >
                                    {{ employee.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="advanceForm.errors.employee_id"
                            class="text-sm text-destructive"
                        >
                            {{ advanceForm.errors.employee_id }}
                        </p>
                    </div>

                    <div class="space-y-2 lg:col-span-2">
                        <Label for="advance-date">Date</Label>
                        <Input
                            id="advance-date"
                            v-model="advanceForm.advance_date"
                            type="date"
                            :aria-invalid="
                                Boolean(advanceForm.errors.advance_date)
                            "
                        />
                        <p
                            v-if="advanceForm.errors.advance_date"
                            class="text-sm text-destructive"
                        >
                            {{ advanceForm.errors.advance_date }}
                        </p>
                    </div>

                    <div class="space-y-2 lg:col-span-2">
                        <Label for="advance-amount"
                            >Amount ({{ currency }})</Label
                        >
                        <Input
                            id="advance-amount"
                            v-model.number="advanceForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            :aria-invalid="Boolean(advanceForm.errors.amount)"
                        />
                        <p
                            v-if="advanceForm.errors.amount"
                            class="text-sm text-destructive"
                        >
                            {{ advanceForm.errors.amount }}
                        </p>
                    </div>

                    <div class="space-y-2 lg:col-span-2">
                        <Label>Paid From</Label>
                        <Select v-model="advanceForm.bank_account_id">
                            <SelectTrigger
                                :aria-invalid="
                                    Boolean(advanceForm.errors.bank_account_id)
                                "
                            >
                                <SelectValue placeholder="Default account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in paymentAccounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.code }} - {{ account.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="advanceForm.errors.bank_account_id"
                            class="text-sm text-destructive"
                        >
                            {{ advanceForm.errors.bank_account_id }}
                        </p>
                    </div>

                    <div class="space-y-2 lg:col-span-2">
                        <Label>Method</Label>
                        <Select v-model="advanceForm.payment_method">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="bank_transfer"
                                    >Bank transfer</SelectItem
                                >
                                <SelectItem value="cheque">Cheque</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end lg:col-span-1">
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="
                                advanceForm.processing ||
                                !advanceForm.employee_id ||
                                !advanceForm.amount
                            "
                        >
                            <LoaderCircle
                                v-if="advanceForm.processing"
                                class="animate-spin"
                            />
                            Record
                        </Button>
                    </div>

                    <div class="space-y-2 lg:col-span-4">
                        <Label for="advance-reference">Reference</Label>
                        <Input
                            id="advance-reference"
                            v-model="advanceForm.reference"
                            placeholder="Optional"
                        />
                    </div>
                    <div class="space-y-2 lg:col-span-8">
                        <Label for="advance-reason">Reason or notes</Label>
                        <Input
                            id="advance-reason"
                            v-model="advanceForm.reason"
                            placeholder="Optional"
                        />
                    </div>

                    <Alert
                        v-if="advanceForm.amount && selectedEmployee"
                        class="border-status-attention/30 bg-status-attention/10 text-status-attention lg:col-span-12"
                    >
                        <AlertTriangle />
                        <AlertTitle>Advance warning</AlertTitle>
                        <AlertDescription v-if="advanceMonths > 0">
                            <MoneyText
                                :amount="Number(advanceForm.amount)"
                                :currency="props.currency"
                            />
                            equals {{ advanceMonths.toFixed(1) }} months of
                            {{ selectedEmployee.name }}'s salary. At the
                            automatic 50% recovery cap, this may take at least
                            {{ estimatedRecoveryPayrolls }} payrolls to recover.
                        </AlertDescription>
                        <AlertDescription v-else>
                            {{ selectedEmployee.name }} has no base salary
                            available for an estimated recovery schedule. The
                            full advance will remain outstanding until
                            recovered.
                        </AlertDescription>
                    </Alert>
                </form>
            </CardContent>
        </Card>

        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card
                class="relative overflow-hidden border-border/80 bg-surface-sunken"
            >
                <CardHeader class="pb-2">
                    <CardDescription>Total Given</CardDescription>
                    <CardTitle class="text-2xl">
                        <MoneyText :amount="stats.total_amount" :currency="props.currency" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <div
                        class="flex items-center gap-2 text-sm text-text-secondary"
                    >
                        <TrendingUp class="h-4 w-4 text-status-info" />
                        <span>{{ stats.total_advances }} advances</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/80">
                <CardHeader class="pb-2">
                    <CardDescription>Outstanding</CardDescription>
                    <CardTitle class="text-2xl text-status-attention">
                        <MoneyText :amount="stats.total_outstanding" :currency="props.currency" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <div
                        class="flex items-center gap-2 text-sm text-text-secondary"
                    >
                        <Clock class="h-4 w-4 text-status-attention" />
                        <span
                            >{{
                                stats.pending_count +
                                stats.partially_recovered_count
                            }}
                            outstanding</span
                        >
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/80">
                <CardHeader class="pb-2">
                    <CardDescription>Recovered</CardDescription>
                    <CardTitle class="text-2xl text-status-success">
                        <MoneyText :amount="stats.total_recovered" :currency="props.currency" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <div
                        class="flex items-center gap-2 text-sm text-text-secondary"
                    >
                        <CheckCircle class="h-4 w-4 text-status-success" />
                        <span>Via payroll</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/80">
                <CardHeader class="pb-2">
                    <CardDescription>Recovery Rate</CardDescription>
                    <CardTitle class="text-2xl"
                        >{{ recoveryPercentage }}%</CardTitle
                    >
                </CardHeader>
                <CardContent class="pt-0">
                    <Progress :model-value="recoveryPercentage" class="h-2" />
                </CardContent>
            </Card>
        </div>

        <!-- List -->
        <Card class="border-border/80">
            <CardHeader class="pb-3">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle class="text-base">Advance History</CardTitle>
                        <CardDescription>{{
                            usesDailyClose
                                ? 'Daily Close is the source of truth for station cash advances.'
                                : 'All advances and payroll recoveries for this company.'
                        }}</CardDescription>
                    </div>

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <div class="relative w-full sm:w-[200px]">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-tertiary"
                            />
                            <Input
                                v-model="search"
                                placeholder="Search..."
                                class="pl-9"
                            />
                        </div>

                        <Select v-model="employeeFilter">
                            <SelectTrigger class="w-full sm:w-[180px]">
                                <SelectValue placeholder="All Employees" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >All Employees</SelectItem
                                >
                                <SelectItem
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="statusFilter">
                            <SelectTrigger class="w-full sm:w-[150px]">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >All Recovery</SelectItem
                                >
                                <SelectItem value="pending"
                                    >Outstanding</SelectItem
                                >
                                <SelectItem value="partially_recovered"
                                    >Partly recovered</SelectItem
                                >
                                <SelectItem value="fully_recovered"
                                    >Recovered</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <LedgerRegister :data="tableData" :columns="columns">
                    <template #empty>
                        <EmptyState
                            title="No salary advances yet"
                            :description="
                                usesDailyClose
                                    ? 'Record employee cash advances in Daily Close so station cash stays reconciled.'
                                    : 'Record the first employee advance above.'
                            "
                        />
                    </template>

                    <template #cell-employee="{ row }">
                        <div>
                            <div class="font-medium">
                                {{ row._raw.employee_name }}
                            </div>
                            <div
                                v-if="row._raw.employee_position"
                                class="text-sm text-muted-foreground"
                            >
                                {{ row._raw.employee_position }}
                            </div>
                        </div>
                    </template>

                    <template #cell-amount="{ row }">
                        <span class="font-medium">
                            <MoneyText :amount="row._raw.amount" :currency="props.currency" />
                        </span>
                    </template>

                    <template #cell-recovered="{ row }">
                        <span class="text-status-success">
                            <MoneyText :amount="row._raw.amount_recovered" :currency="props.currency" />
                        </span>
                    </template>

                    <template #cell-outstanding="{ row }">
                        <span
                            :class="
                                row._raw.amount_outstanding > 0
                                    ? 'font-medium text-status-attention'
                                    : 'text-muted-foreground'
                            "
                        >
                            <MoneyText :amount="row._raw.amount_outstanding" :currency="props.currency" />
                        </span>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge :class="getStatusBadge(row._raw.status).class">
                            {{ getStatusBadge(row._raw.status).label }}
                        </Badge>
                    </template>
                </LedgerRegister>
            </CardContent>
        </Card>
    </PageShell>
</template>
