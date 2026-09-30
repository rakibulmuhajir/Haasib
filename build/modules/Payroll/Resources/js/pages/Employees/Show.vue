<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowLeft,
    Briefcase,
    DollarSign,
    Pencil,
    User,
    Users,
} from 'lucide-vue-next';
import MoneyText from '@/components/MoneyText.vue';

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
    rows: Array<{ date: string; type: string; reference: string | null; description: string; money_in: number; money_out: number; balance: number; link: string | null }>;
    opening_balance: number;
    closing_balance: number;
    from: string;
    to: string;
    totals: { salary: number; earned: number; advances: number; advance_count: number; repaid: number; deductions: number; paid: number };
}

const props = defineProps<{
    company: CompanyRef;
    employee: Employee;
    statement: Statement;
    month: string;
}>();

const monthLabel = computed(() => new Date(`${props.month}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const shiftMonth = (step: number) => {
    const d = new Date(`${props.month}-01T00:00:00`);
    d.setMonth(d.getMonth() + step);
    router.get(`/${props.company.slug}/employees/${props.employee.id}`, { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true });
};
const statementCurrency = computed(() => props.employee.currency || props.company.base_currency || 'PKR');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Employees', href: `/${props.company.slug}/employees` },
    {
        title: `${props.employee.first_name} ${props.employee.last_name}`,
        href: `/${props.company.slug}/employees/${props.employee.id}`,
    },
];

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
            <!-- Salary earned against advances taken and salary paid, with a running balance. -->
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/reports/statements`, { kind: 'employee', id: employee.id })"
            >
                Statement
            </Button>
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

                <!-- Their statement for the month: salary (expected until payroll runs) against advances and pay. -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-3 space-y-0">
                        <CardTitle>Statement · {{ monthLabel }}</CardTitle>
                        <div class="flex gap-1">
                            <Button variant="outline" size="sm" aria-label="Previous month" @click="shiftMonth(-1)">‹</Button>
                            <Button variant="outline" size="sm" aria-label="Next month" @click="shiftMonth(1)">›</Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="rounded-lg border p-3">
                                <div class="text-xs text-muted-foreground">Salary</div>
                                <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.salary" :currency="statementCurrency" :fraction-digits="0" /></div>
                            </div>
                            <div class="rounded-lg border p-3">
                                <div class="text-xs text-muted-foreground">Advances · {{ statement.totals.advance_count }}</div>
                                <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.advances - statement.totals.repaid" :currency="statementCurrency" :fraction-digits="0" /></div>
                            </div>
                            <div class="rounded-lg border p-3">
                                <div class="text-xs text-muted-foreground">Salary paid</div>
                                <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.paid" :currency="statementCurrency" :fraction-digits="0" /></div>
                            </div>
                            <div class="rounded-lg border p-3" :class="statement.closing_balance < 0 ? 'border-status-critical/40 bg-status-critical/10' : ''">
                                <div class="text-xs text-muted-foreground">{{ statement.closing_balance < 0 ? 'They owe us' : 'We owe' }}</div>
                                <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="Math.abs(statement.closing_balance)" :currency="statementCurrency" :fraction-digits="0" /></div>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="border-b text-left text-xs text-muted-foreground">
                                <tr>
                                    <th class="py-1.5">Date</th>
                                    <th class="py-1.5">What</th>
                                    <th class="py-1.5 text-right">Earned</th>
                                    <th class="py-1.5 text-right">Taken / paid</th>
                                    <th class="py-1.5 text-right">We owe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in statement.rows" :key="i" class="border-b last:border-0" :class="['opening_balance', 'closing_balance'].includes(row.type) ? 'font-medium' : ''">
                                    <td class="py-1.5 tabular-nums">{{ formatDate(row.date) }}</td>
                                    <td class="py-1.5">
                                        <Link v-if="row.link" :href="`/${company.slug}/${row.link}`" class="text-primary underline-offset-2 hover:underline">{{ row.description }}</Link>
                                        <span v-else>{{ row.description }}</span>
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums"><MoneyText :amount="row.money_in" :currency="statementCurrency" :show-currency="false" :fraction-digits="0" dash-zero /></td>
                                    <td class="py-1.5 text-right tabular-nums"><MoneyText :amount="row.money_out" :currency="statementCurrency" :show-currency="false" :fraction-digits="0" dash-zero /></td>
                                    <td class="py-1.5 text-right tabular-nums"><MoneyText :amount="row.balance" :currency="statementCurrency" :show-currency="false" :fraction-digits="0" /></td>
                                </tr>
                            </tbody>
                        </table>
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
                            <span class="text-lg font-medium"><MoneyText
                                :amount="employee.base_salary"
                                :currency="employee.currency"
                            /></span>
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
