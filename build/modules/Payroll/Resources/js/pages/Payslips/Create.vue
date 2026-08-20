<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import LedgerRegister from '@/components/LedgerRegister.vue';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { formatMoneyText } from '@/lib/money';
import MoneyText from '@/components/MoneyText.vue';

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
    base_salary: number;
    currency: string;
    outstanding_advances?: number | null;
}

interface Period {
    id: string;
    period_start: string;
    period_end: string;
    status: string;
}

interface EarningType {
    id: string;
    name: string;
    code: string;
    is_taxable: boolean;
}

interface DeductionType {
    id: string;
    name: string;
    code: string;
}

interface PayslipLine {
    line_type: 'earning' | 'deduction';
    earning_type_id: string;
    deduction_type_id: string;
    salary_advance_id?: string | null;
    description: string;
    amount: number;
    quantity: number | null;
    rate: number | null;
}

interface CurrencyOption {
    currency_code: string;
    exchange_rate?: number | string | null;
}

const props = defineProps<{
    company: CompanyRef;
    employees: Employee[];
    periods: Period[];
    earningTypes: EarningType[];
    deductionTypes: DeductionType[];
    currencies: CurrencyOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Payslips', href: `/${props.company.slug}/payslips` },
    { title: 'Create', href: `/${props.company.slug}/payslips/create` },
];

const selectedEmployee = ref<Employee | null>(null);

const form = useForm({
    employee_id: '',
    payroll_period_id: '',
    currency: props.company.base_currency,
    exchange_rate: null as number | null,
    notes: '',
    lines: [] as PayslipLine[],
});

// Watch for employee selection to set currency
watch(
    () => form.employee_id,
    (newVal) => {
        const emp = props.employees.find((e) => e.id === newVal);
        if (emp) {
            selectedEmployee.value = emp;
            form.currency = emp.currency;
            const configuredRate = props.currencies.find(
                (currency) => currency.currency_code === emp.currency,
            )?.exchange_rate;
            form.exchange_rate =
                emp.currency === props.company.base_currency
                    ? null
                    : Number(configuredRate || 0) || null;
            // Add base salary as default earning if no lines exist
            if (form.lines.length === 0) {
                addEarning();
                form.lines[0].description = 'Base Salary';
                form.lines[0].amount = emp.base_salary;
            }
        }
    },
);

const addEarning = () => {
    form.lines.push({
        line_type: 'earning',
        earning_type_id: '',
        deduction_type_id: '',
        description: '',
        amount: 0,
        quantity: 1,
        rate: 0,
    });
};

const addDeduction = () => {
    form.lines.push({
        line_type: 'deduction',
        earning_type_id: '',
        deduction_type_id: '',
        description: '',
        amount: 0,
        quantity: 1,
        rate: 0,
    });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

/**
 * Earnings and deductions are being entered into one payslip, not two --
 * they were only ever split into separate tables because the old table
 * component had no notion of a column carrying either direction. One column
 * for what is added, one for what is taken off, edited in place as the line
 * is built. Quantity and rate only apply to a rate-based earning, so a
 * deduction row leaves them out rather than showing controls that do
 * nothing.
 */
const lineColumns = [
    { key: 'type', label: 'Type', kind: 'text' as const },
    { key: 'description', label: 'Description', kind: 'text' as const },
    { key: 'quantity', label: 'Qty', kind: 'amount' as const },
    { key: 'rate', label: 'Rate', kind: 'amount' as const },
    { key: 'earning', label: 'Earning', kind: 'in' as const },
    { key: 'deduction', label: 'Deduction', kind: 'out' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const earnings = computed(() =>
    form.lines.filter((l) => l.line_type === 'earning'),
);
const deductions = computed(() =>
    form.lines.filter((l) => l.line_type === 'deduction'),
);

const grossPay = computed(() => {
    return earnings.value.reduce(
        (sum, line) => sum + Number(line.amount || 0),
        0,
    );
});

const totalDeductions = computed(() => {
    return deductions.value.reduce(
        (sum, line) => sum + Number(line.amount || 0),
        0,
    );
});

const netPay = computed(() => grossPay.value - totalDeductions.value);
const isForeignCurrency = computed(
    () => form.currency !== props.company.base_currency,
);
const conversionRate = computed(() =>
    isForeignCurrency.value ? Number(form.exchange_rate || 0) : 1,
);
const baseNetPay = computed(() => netPay.value * conversionRate.value);

const estimatedAdvanceRecovery = computed(() => {
    const outstanding =
        Number(selectedEmployee.value?.outstanding_advances || 0) /
        conversionRate.value;
    if (outstanding <= 0 || grossPay.value <= 0) return 0;
    return Math.min(
        outstanding,
        grossPay.value * 0.5,
        Math.max(0, grossPay.value - totalDeductions.value),
    );
});

const formatCurrency = (amount: number, currency: string) => {
    return formatMoneyText(amount, currency || 'USD');
};

const formatDate = (date: string) => {
    return formatDateTime(date, { mode: 'date' });
};

const submit = () => {
    form.post(`/${props.company.slug}/payslips`);
};
</script>

<template>
    <Head title="Create Payslip" />

    <PageShell title="Create Payslip" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button
                variant="outline"
                @click="$inertia.get(`/${company.slug}/payslips`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Payslip Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Payslip Information</CardTitle>
                            <CardDescription
                                >Select the employee and payroll
                                period</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="employee">Employee *</Label>
                                    <Select v-model="form.employee_id">
                                        <SelectTrigger
                                            :class="{
                                                'border-destructive':
                                                    form.errors.employee_id,
                                            }"
                                        >
                                            <SelectValue
                                                placeholder="Select employee"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="emp in employees"
                                                :key="emp.id"
                                                :value="emp.id"
                                            >
                                                {{ emp.first_name }}
                                                {{ emp.last_name }} ({{
                                                    emp.employee_number
                                                }})
                                                <span
                                                    v-if="
                                                        Number(
                                                            emp.outstanding_advances ||
                                                                0,
                                                        ) > 0
                                                    "
                                                >
                                                    · Advance
                                                    <MoneyText
                                                        :amount="Number(emp.outstanding_advances)"
                                                        :currency="emp.currency || company.base_currency"
                                                    />
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="form.errors.employee_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.employee_id }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="period">Payroll Period *</Label>
                                    <Select v-model="form.payroll_period_id">
                                        <SelectTrigger
                                            :class="{
                                                'border-destructive':
                                                    form.errors
                                                        .payroll_period_id,
                                            }"
                                        >
                                            <SelectValue
                                                placeholder="Select period"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="period in periods"
                                                :key="period.id"
                                                :value="period.id"
                                            >
                                                {{
                                                    formatDate(
                                                        period.period_start,
                                                    )
                                                }}
                                                -
                                                {{
                                                    formatDate(
                                                        period.period_end,
                                                    )
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="form.errors.payroll_period_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.payroll_period_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="currency">Currency</Label>
                                <Input
                                    id="currency"
                                    v-model="form.currency"
                                    maxlength="3"
                                    readonly
                                    class="w-24"
                                />
                                <p
                                    v-if="form.errors.currency"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.currency }}
                                </p>
                            </div>

                            <div
                                v-if="isForeignCurrency"
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <div class="space-y-2">
                                    <Label for="exchange-rate"
                                        >Exchange Rate *</Label
                                    >
                                    <Input
                                        id="exchange-rate"
                                        v-model.number="form.exchange_rate"
                                        type="number"
                                        min="0.00000001"
                                        step="0.00000001"
                                        :class="{
                                            'border-destructive':
                                                form.errors.exchange_rate,
                                        }"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        1 {{ form.currency }} = this many
                                        {{ company.base_currency }}
                                    </p>
                                    <p
                                        v-if="form.errors.exchange_rate"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.exchange_rate }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-md border bg-muted/30 p-3 text-sm"
                                >
                                    <p class="text-muted-foreground">
                                        Estimated net in
                                        {{ company.base_currency }}
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        <MoneyText
                                            :amount="baseNetPay"
                                            :currency="company.base_currency"
                                        />
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="
                                    selectedEmployee &&
                                    Number(
                                        selectedEmployee.outstanding_advances ||
                                            0,
                                    ) > 0
                                "
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">
                                    Salary advance will be recovered
                                    automatically.
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Outstanding advance:
                                    <MoneyText
                                        :amount="Number(selectedEmployee.outstanding_advances || 0)"
                                        :currency="company.base_currency"
                                    />
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Estimated recovery on this payslip:
                                    <MoneyText
                                        :amount="estimatedAdvanceRecovery"
                                        :currency="form.currency"
                                    />
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Payslip lines -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Payslip Lines</CardTitle>
                                    <CardDescription
                                        >Add earnings and deductions for this
                                        payslip. Salary advance recoveries are
                                        added automatically from outstanding
                                        advances.</CardDescription
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addEarning"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add Earning
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addDeduction"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add Deduction
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <LedgerRegister
                                :data="form.lines"
                                :columns="lineColumns"
                                :key-field="(_row: PayslipLine, i: number) => i"
                                :totals="{ earning: formatCurrency(grossPay, form.currency), deduction: formatCurrency(totalDeductions, form.currency) }"
                            >
                                <template #empty
                                    >No lines added. Add an earning or a
                                    deduction to start.</template
                                >

                                <template #cell-type="{ row }">
                                    <Select
                                        v-if="row.line_type === 'earning'"
                                        v-model="row.earning_type_id"
                                    >
                                        <SelectTrigger class="w-32">
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="et in earningTypes"
                                                :key="et.id"
                                                :value="et.id"
                                            >
                                                {{ et.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        v-else
                                        v-model="row.deduction_type_id"
                                    >
                                        <SelectTrigger class="w-32">
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="dt in deductionTypes"
                                                :key="dt.id"
                                                :value="dt.id"
                                            >
                                                {{ dt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </template>

                                <template #cell-description="{ row }">
                                    <Input
                                        v-model="row.description"
                                        placeholder="Description"
                                    />
                                </template>

                                <template #cell-quantity="{ row }">
                                    <Input
                                        v-if="row.line_type === 'earning'"
                                        v-model.number="row.quantity"
                                        type="number"
                                        step="0.01"
                                        placeholder="Qty"
                                    />
                                    <span v-else>—</span>
                                </template>

                                <template #cell-rate="{ row }">
                                    <Input
                                        v-if="row.line_type === 'earning'"
                                        v-model.number="row.rate"
                                        type="number"
                                        step="0.01"
                                        placeholder="Rate"
                                    />
                                    <span v-else>—</span>
                                </template>

                                <template #cell-earning="{ row }">
                                    <Input
                                        v-if="row.line_type === 'earning'"
                                        v-model.number="row.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <span v-else>—</span>
                                </template>

                                <template #cell-deduction="{ row }">
                                    <Input
                                        v-if="row.line_type === 'deduction'"
                                        v-model.number="row.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <span v-else>—</span>
                                </template>

                                <template #cell-actions="{ row }">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive"
                                            @click="removeLine(form.lines.indexOf(row))"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </template>
                            </LedgerRegister>
                        </CardContent>
                    </Card>

                    <!-- Notes -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                v-model="form.notes"
                                placeholder="Any additional notes..."
                                rows="3"
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Summary Sidebar -->
                <div class="space-y-6">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle>Pay Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Gross Pay</span
                                >
                                <span class="font-medium">
                                    <MoneyText :amount="grossPay" :currency="form.currency" />
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Deductions</span
                                >
                                <span class="font-medium text-destructive">
                                    <MoneyText
                                        :amount="totalDeductions"
                                        :currency="form.currency"
                                        direction="outflow"
                                    />
                                </span>
                            </div>
                            <div
                                v-if="estimatedAdvanceRecovery > 0"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Auto advance recovery</span
                                >
                                <span class="font-medium text-destructive">
                                    <MoneyText
                                        :amount="estimatedAdvanceRecovery"
                                        :currency="form.currency"
                                        direction="outflow"
                                    />
                                </span>
                            </div>
                            <hr />
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">Net Pay</span>
                                <span class="text-xl font-bold text-primary">
                                    <MoneyText
                                        :amount="netPay - estimatedAdvanceRecovery"
                                        :currency="form.currency"
                                    />
                                </span>
                            </div>

                            <div class="pt-4">
                                <Button
                                    type="submit"
                                    class="w-full"
                                    :disabled="form.processing"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{
                                        form.processing
                                            ? 'Creating...'
                                            : 'Create Payslip'
                                    }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    </PageShell>
</template>
