<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Briefcase,
    DollarSign,
    Pencil,
    User,
    Users,
} from 'lucide-vue-next';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface Manager {
    id: string;
    first_name: string;
    last_name: string;
}

interface DirectReport {
    id: string;
    first_name: string;
    last_name: string;
    employee_number: string;
    position: string | null;
}

interface Employee {
    id: string;
    employee_number: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    hire_date: string;
    termination_date: string | null;
    employment_type: string;
    employment_status: string;
    department: string | null;
    position: string | null;
    manager: Manager | null;
    direct_reports: DirectReport[];
    pay_frequency: string;
    base_salary: number;
    currency: string;
    is_active: boolean;
    notes: string | null;
}

interface Statement {
    summary: {
        salary_due: number;
        salary_paid: number;
        advance_given: number;
        advance_recovered: number;
        advance_outstanding: number;
    };
    payslips: Array<{
        id: string;
        date: string | null;
        label: string;
        gross_pay: number;
        deductions: number;
        net_pay: number;
        status: string;
        currency: string;
    }>;
    advances: Array<{
        id: string;
        date: string;
        amount: number;
        recovered: number;
        outstanding: number;
        status: string;
        reason: string | null;
        payment_method: string;
    }>;
    recoveries: Array<{
        id: string;
        date: string;
        label: string;
        amount: number;
        recovery_type: string;
    }>;
}

const props = defineProps<{
    company: CompanyRef;
    employee: Employee;
    statement: Statement;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Employees', href: `/${props.company.slug}/employees` },
    {
        title: `${props.employee.first_name} ${props.employee.last_name}`,
        href: `/${props.company.slug}/employees/${props.employee.id}`,
    },
];

const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currencyDisplay: 'narrowSymbol',
        currency: currency || 'USD',
    }).format(amount);
};

const formatDate = (date: string | null) => {
    return formatSharedDateTime(date, { mode: 'date', fallback: '-' });
};

const getStatusVariant = (status: string) => {
    const variants: Record<
        string,
        'success' | 'secondary' | 'destructive' | 'outline'
    > = {
        active: 'success',
        on_leave: 'outline',
        suspended: 'destructive',
        terminated: 'secondary',
    };
    return variants[status] || 'secondary';
};

const formatStatus = (status: string) => {
    return status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatEmploymentType = (type: string) => {
    return type.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatPayFrequency = (freq: string) => {
    const labels: Record<string, string> = {
        weekly: 'Weekly',
        biweekly: 'Bi-weekly',
        semimonthly: 'Semi-monthly',
        monthly: 'Monthly',
    };
    return labels[freq] || freq;
};
</script>

<template>
    <Head :title="`${employee.first_name} ${employee.last_name}`" />

    <PageShell
        :title="`${employee.first_name} ${employee.last_name}`"
        :breadcrumbs="breadcrumbs"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/employees`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button
                @click="
                    router.get(`/${company.slug}/employees/${employee.id}/edit`)
                "
            >
                <Pencil class="mr-2 h-4 w-4" />
                Edit
            </Button>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Personal Information -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <User class="h-5 w-5" />
                                Personal Information
                            </CardTitle>
                            <Badge
                                :variant="
                                    getStatusVariant(employee.employment_status)
                                "
                            >
                                {{ formatStatus(employee.employment_status) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-muted-foreground">Employee ID</p>
                                <p class="font-medium">
                                    {{ employee.employee_number }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Full Name</p>
                                <p class="font-medium">
                                    {{ employee.first_name }}
                                    {{ employee.last_name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Email</p>
                                <p class="font-medium">
                                    {{ employee.email ?? '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Phone</p>
                                <p class="font-medium">
                                    {{ employee.phone ?? '-' }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Employment Details -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Briefcase class="h-5 w-5" />
                            Employment Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-muted-foreground">Department</p>
                                <p class="font-medium">
                                    {{ employee.department ?? '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Position</p>
                                <p class="font-medium">
                                    {{ employee.position ?? '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">
                                    Employment Type
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatEmploymentType(
                                            employee.employment_type,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Hire Date</p>
                                <p class="font-medium">
                                    {{ formatDate(employee.hire_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Manager</p>
                                <p class="font-medium">
                                    {{
                                        employee.manager
                                            ? `${employee.manager.first_name} ${employee.manager.last_name}`
                                            : '-'
                                    }}
                                </p>
                            </div>
                            <div v-if="employee.termination_date">
                                <p class="text-muted-foreground">
                                    Termination Date
                                </p>
                                <p class="font-medium">
                                    {{ formatDate(employee.termination_date) }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Direct Reports -->
                <Card
                    v-if="
                        employee.direct_reports &&
                        employee.direct_reports.length > 0
                    "
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Direct Reports
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="report in employee.direct_reports"
                                :key="report.id"
                                class="flex cursor-pointer items-center justify-between rounded-lg border px-3 py-2 hover:bg-muted/50"
                                @click="
                                    router.get(
                                        `/${company.slug}/employees/${report.id}`,
                                    )
                                "
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ report.first_name }}
                                        {{ report.last_name }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ report.employee_number }}
                                    </p>
                                </div>
                                <Badge variant="secondary">{{
                                    report.position ?? 'No position'
                                }}</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Employee Statement</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div class="rounded-lg border p-3">
                                <p class="text-xs text-muted-foreground">
                                    Salary due
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatCurrency(
                                            statement.summary.salary_due,
                                            company.base_currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-xs text-muted-foreground">
                                    Salary paid
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatCurrency(
                                            statement.summary.salary_paid,
                                            company.base_currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-xs text-muted-foreground">
                                    Advances given
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatCurrency(
                                            statement.summary.advance_given,
                                            company.base_currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-xs text-muted-foreground">
                                    Recovered
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatCurrency(
                                            statement.summary.advance_recovered,
                                            company.base_currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-xs text-muted-foreground">
                                    Advance balance
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatCurrency(
                                            statement.summary
                                                .advance_outstanding,
                                            company.base_currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <div>
                                <h3 class="mb-3 text-sm font-medium">
                                    Recent Payslips
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="payslip in statement.payslips"
                                        :key="payslip.id"
                                        class="cursor-pointer rounded-lg border p-3 text-sm hover:bg-muted/50"
                                        @click="
                                            router.get(
                                                `/${company.slug}/payslips/${payslip.id}`,
                                            )
                                        "
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span class="font-medium">{{
                                                payslip.label
                                            }}</span>
                                            <Badge>{{ payslip.status }}</Badge>
                                        </div>
                                        <div
                                            class="mt-2 flex items-center justify-between text-muted-foreground"
                                        >
                                            <span>{{
                                                formatDate(payslip.date)
                                            }}</span>
                                            <span>{{
                                                formatCurrency(
                                                    payslip.net_pay,
                                                    payslip.currency,
                                                )
                                            }}</span>
                                        </div>
                                    </div>
                                    <p
                                        v-if="statement.payslips.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        No payslips yet.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-3 text-sm font-medium">
                                    Salary Advances
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="advance in statement.advances"
                                        :key="advance.id"
                                        class="rounded-lg border p-3 text-sm"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span class="font-medium">{{
                                                formatCurrency(
                                                    advance.amount,
                                                    company.base_currency,
                                                )
                                            }}</span>
                                            <Badge>{{ advance.status }}</Badge>
                                        </div>
                                        <p class="mt-1 text-muted-foreground">
                                            {{ formatDate(advance.date) }} ·
                                            {{ advance.payment_method }}
                                        </p>
                                        <p class="mt-1 text-muted-foreground">
                                            Remaining
                                            {{
                                                formatCurrency(
                                                    advance.outstanding,
                                                    company.base_currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="statement.advances.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        No advances yet.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-3 text-sm font-medium">
                                    Advance Recoveries
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="recovery in statement.recoveries"
                                        :key="recovery.id"
                                        class="rounded-lg border p-3 text-sm"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span class="font-medium">{{
                                                recovery.label
                                            }}</span>
                                            <span>{{
                                                formatCurrency(
                                                    recovery.amount,
                                                    company.base_currency,
                                                )
                                            }}</span>
                                        </div>
                                        <p class="mt-1 text-muted-foreground">
                                            {{ formatDate(recovery.date) }} ·
                                            {{ recovery.recovery_type }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="statement.recoveries.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        No recoveries yet.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Notes -->
                <Card v-if="employee.notes">
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-wrap">
                            {{ employee.notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <DollarSign class="h-5 w-5" />
                            Compensation
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Base Salary</span
                            >
                            <span class="text-lg font-medium">{{
                                formatCurrency(
                                    employee.base_salary,
                                    employee.currency,
                                )
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Pay Frequency</span
                            >
                            <span class="font-medium">{{
                                formatPayFrequency(employee.pay_frequency)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Currency</span>
                            <span class="font-medium">{{
                                employee.currency
                            }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PageShell>
</template>
