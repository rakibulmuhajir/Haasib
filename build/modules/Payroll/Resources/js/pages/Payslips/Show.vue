<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LedgerRegister from '@/components/LedgerRegister.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle, DollarSign, Printer, Trash2 } from 'lucide-vue-next';
import { formatMoneyText } from '@/lib/money'

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface Employee {
    id: string;
    first_name: string;
    last_name: string;
    employee_number: string;
    department: string | null;
    position: string | null;
}

interface Period {
    id: string;
    period_start: string;
    period_end: string;
    payment_date: string;
}

interface PayslipLine {
    id: string;
    line_type: 'earning' | 'deduction';
    description: string;
    salary_advance_id?: string | null;
    amount: number;
    quantity: number | null;
    rate: number | null;
}

interface Payslip {
    id: string;
    payslip_number: string;
    employee: Employee;
    payroll_period: Period;
    currency: string;
    exchange_rate: number | null;
    base_currency: string;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    base_gross_pay: number;
    base_total_deductions: number;
    base_net_pay: number;
    status: string;
    notes: string | null;
    lines: PayslipLine[];
    created_at: string;
    gl_transaction_id?: string | null;
    payment_gl_transaction_id?: string | null;
    voided_at?: string | null;
    void_reason?: string | null;
}

const props = defineProps<{
    company: CompanyRef;
    payslip: Payslip;
    canDeletePayslips: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Payslips', href: `/${props.company.slug}/payslips` },
    {
        title: props.payslip.payslip_number,
        href: `/${props.company.slug}/payslips/${props.payslip.id}`,
    },
];

const formatCurrency = (amount: number, currency: string) => {
    return formatMoneyText(amount, currency || 'USD');
};

const formatDate = (date: string) => {
    return formatSharedDateTime(date, { mode: 'date' });
};

/**
 * Earnings and deductions were two tables that happened to sit on the same
 * page. They are the same register: one column for what was added to the
 * pay, one for what was taken off it, read down the line items that make up
 * this payslip in the order they were entered. Quantity and rate only mean
 * anything for an earning paid by rate, so a deduction row leaves them blank
 * rather than printing a zero that was never computed.
 */
const lineColumns = [
    { key: 'description', label: 'Description', kind: 'text' as const },
    { key: 'quantity', label: 'Quantity', kind: 'amount' as const },
    { key: 'rate', label: 'Rate', kind: 'amount' as const },
    { key: 'earning', label: 'Earning', kind: 'in' as const },
    { key: 'deduction', label: 'Deduction', kind: 'out' as const },
];

const handleApprove = () => {
    router.post(`/${props.company.slug}/payslips/${props.payslip.id}/approve`);
};

const handleMarkPaid = () => {
    router.post(
        `/${props.company.slug}/payslips/${props.payslip.id}/mark-paid`,
    );
};

const handleDelete = () => {
    if (confirm('Are you sure you want to delete this draft payslip?')) {
        router.delete(
            `/${props.company.slug}/payslips/${props.payslip.id}`,
        );
    }
};

const handleVoid = () => {
    const reason = window.prompt('Why is this payslip being voided?')?.trim();
    if (reason) {
        router.post(
            `/${props.company.slug}/payslips/${props.payslip.id}/void`,
            { reason },
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <Head :title="`Payslip ${payslip.payslip_number}`" />

    <PageShell
        :title="`Payslip ${payslip.payslip_number}`"
        :breadcrumbs="breadcrumbs"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/payslips`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button v-if="payslip.status === 'draft'" @click="handleApprove">
                <CheckCircle class="mr-2 h-4 w-4" />
                Approve
            </Button>
            <Button
                v-if="payslip.status === 'approved'"
                @click="handleMarkPaid"
            >
                <DollarSign class="mr-2 h-4 w-4" />
                Mark Paid
            </Button>
            <Button
                v-if="canDeletePayslips && payslip.status === 'draft'"
                variant="destructive"
                @click="handleDelete"
            >
                <Trash2 class="mr-2 h-4 w-4" />
                Delete
            </Button>
            <Button
                v-if="canDeletePayslips && ['approved', 'paid'].includes(payslip.status)"
                variant="destructive"
                @click="handleVoid"
            >
                <Ban class="mr-2 h-4 w-4" />
                Void Payslip
            </Button>
            <Button variant="outline">
                <Printer class="mr-2 h-4 w-4" />
                Print
            </Button>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Employee & Period Info -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Payslip Details</CardTitle>
                            <StatusBadge :status="payslip.status" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-muted-foreground">Employee</p>
                                <p class="font-medium">
                                    {{ payslip.employee.first_name }}
                                    {{ payslip.employee.last_name }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ payslip.employee.employee_number }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Department</p>
                                <p class="font-medium">
                                    {{ payslip.employee.department ?? '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Pay Period</p>
                                <p class="font-medium">
                                    {{
                                        formatDate(
                                            payslip.payroll_period.period_start,
                                        )
                                    }}
                                    -
                                    {{
                                        formatDate(
                                            payslip.payroll_period.period_end,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">
                                    Payment Date
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatDate(
                                            payslip.payroll_period.payment_date,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Earnings -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payslip Lines</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <LedgerRegister
                            :data="payslip.lines"
                            :columns="lineColumns"
                            :key-field="(row: PayslipLine, i: number) => row.id ?? `${row.line_type}-${i}`"
                            :totals="{ earning: formatCurrency(payslip.gross_pay, payslip.currency), deduction: formatCurrency(payslip.total_deductions, payslip.currency) }"
                        >
                            <template #empty>No lines recorded</template>

                            <template #cell-description="{ row }">
                                <div>{{ row.description }}</div>
                                <div
                                    v-if="row.salary_advance_id"
                                    class="text-xs text-muted-foreground"
                                >
                                    Salary advance recovery
                                </div>
                            </template>

                            <template #cell-quantity="{ row }">
                                {{ row.line_type === 'earning' ? (row.quantity ?? '—') : '—' }}
                            </template>

                            <template #cell-rate="{ row }">
                                {{ row.line_type === 'earning' && row.rate ? formatCurrency(row.rate, payslip.currency) : '—' }}
                            </template>

                            <template #cell-earning="{ row }">
                                {{ row.line_type === 'earning' ? formatCurrency(row.amount, payslip.currency) : '—' }}
                            </template>

                            <template #cell-deduction="{ row }">
                                {{ row.line_type === 'deduction' ? formatCurrency(row.amount, payslip.currency) : '—' }}
                            </template>
                        </LedgerRegister>
                    </CardContent>
                </Card>

                <!-- Notes -->
                <Card v-if="payslip.notes">
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-wrap">
                            {{ payslip.notes }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Accounting</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border p-3 text-sm">
                            <p class="font-medium">Draft</p>
                            <p class="mt-1 text-muted-foreground">
                                No accounting entry yet.
                            </p>
                        </div>
                        <div class="rounded-lg border p-3 text-sm">
                            <p class="font-medium">Approval</p>
                            <p class="mt-1 text-muted-foreground">
                                Salary expense increases and salary payable is
                                created.
                            </p>
                            <Button
                                v-if="payslip.gl_transaction_id"
                                variant="link"
                                class="mt-2 h-auto p-0"
                                @click="
                                    router.get(
                                        `/${company.slug}/journals/${payslip.gl_transaction_id}`,
                                    )
                                "
                            >
                                View approval journal
                            </Button>
                        </div>
                        <div class="rounded-lg border p-3 text-sm">
                            <p class="font-medium">Payment</p>
                            <p class="mt-1 text-muted-foreground">
                                Salary payable reduces and cash or bank reduces.
                            </p>
                            <Button
                                v-if="payslip.payment_gl_transaction_id"
                                variant="link"
                                class="mt-2 h-auto p-0"
                                @click="
                                    router.get(
                                        `/${company.slug}/journals/${payslip.payment_gl_transaction_id}`,
                                    )
                                "
                            >
                                View payment journal
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Summary Sidebar -->
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Pay Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Gross Pay</span>
                            <span class="font-medium">{{
                                formatCurrency(
                                    payslip.gross_pay,
                                    payslip.currency,
                                )
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Deductions</span
                            >
                            <span class="font-medium text-destructive">
                                -{{
                                    formatCurrency(
                                        payslip.total_deductions,
                                        payslip.currency,
                                    )
                                }}
                            </span>
                        </div>
                        <hr />
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Net Pay</span>
                            <span class="text-xl font-bold text-primary">
                                {{
                                    formatCurrency(
                                        payslip.net_pay,
                                        payslip.currency,
                                    )
                                }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Payslip #</span>
                            <span class="font-medium">{{
                                payslip.payslip_number
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Currency</span>
                            <span class="font-medium">{{
                                payslip.currency
                            }}</span>
                        </div>
                        <div
                            v-if="payslip.currency !== payslip.base_currency"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground"
                                >Exchange rate</span
                            >
                            <span class="font-medium"
                                >1 {{ payslip.currency }} =
                                {{ payslip.exchange_rate }}
                                {{ payslip.base_currency }}</span
                            >
                        </div>
                        <div
                            v-if="payslip.currency !== payslip.base_currency"
                            class="flex justify-between border-t pt-3"
                        >
                            <span class="text-muted-foreground"
                                >Net in {{ payslip.base_currency }}</span
                            >
                            <span class="font-semibold">{{
                                formatCurrency(
                                    payslip.base_net_pay,
                                    payslip.base_currency,
                                )
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Created</span>
                            <span class="font-medium">{{
                                formatDate(payslip.created_at)
                            }}</span>
                        </div>
                        <div v-if="payslip.voided_at" class="border-t pt-3">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Voided</span>
                                <span class="font-medium">{{ formatDate(payslip.voided_at) }}</span>
                            </div>
                            <p v-if="payslip.void_reason" class="mt-2 text-sm text-muted-foreground">
                                {{ payslip.void_reason }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PageShell>
</template>
