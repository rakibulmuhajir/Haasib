<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { formatMoneyText } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
}

interface EarningType {
    id: string;
    code: string;
    name: string;
}

interface DeductionType {
    id: string;
    code: string;
    name: string;
}

interface StoredLine {
    id: string;
    line_type: 'earning' | 'deduction' | 'employer';
    earning_type_id: string | null;
    deduction_type_id: string | null;
    salary_advance_id: string | null;
    description: string | null;
    quantity: number | string | null;
    rate: number | string | null;
    amount: number | string;
    sort_order: number | null;
}

interface FormLine {
    line_type: 'earning' | 'deduction' | 'employer';
    earning_type_id: string;
    deduction_type_id: string;
    description: string;
    quantity: number;
    rate: number;
    amount: number;
}

interface Payslip {
    id: string;
    payslip_number: string;
    currency: string;
    base_currency: string;
    exchange_rate: number | string | null;
    status: string;
    notes: string | null;
    lines: StoredLine[];
}

const props = defineProps<{
    company: CompanyRef;
    payslip: Payslip;
    earningTypes: EarningType[];
    deductionTypes: DeductionType[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Payslips', href: `/${props.company.slug}/payslips` },
    {
        title: props.payslip.payslip_number,
        href: `/${props.company.slug}/payslips/${props.payslip.id}`,
    },
    {
        title: 'Edit',
        href: `/${props.company.slug}/payslips/${props.payslip.id}/edit`,
    },
];

/**
 * Salary advance recovery lines are the posting service's, not the form's: it
 * deletes and recomputes them from the outstanding advances on every save. So
 * they are not loaded into the editable set — sending them back would only
 * have them discarded, and showing them as editable would promise an edit that
 * does not survive the request.
 */
const advanceLines = computed(() =>
    props.payslip.lines.filter((line) => line.salary_advance_id),
);

const toFormLine = (line: StoredLine): FormLine => ({
    line_type: line.line_type,
    earning_type_id: line.earning_type_id ?? '',
    deduction_type_id: line.deduction_type_id ?? '',
    description: line.description ?? '',
    quantity: Number(line.quantity ?? 1),
    rate: Number(line.rate ?? 0),
    amount: Number(line.amount ?? 0),
});

const isForeignCurrency = computed(
    () => props.payslip.currency !== props.payslip.base_currency,
);

const form = useForm({
    notes: props.payslip.notes ?? '',
    exchange_rate: isForeignCurrency.value
        ? Number(props.payslip.exchange_rate ?? 0) || null
        : null,
    lines: props.payslip.lines
        .filter((line) => !line.salary_advance_id)
        .map(toFormLine) as FormLine[],
});

const addLine = (lineType: 'earning' | 'deduction') => {
    form.lines.push({
        line_type: lineType,
        earning_type_id: '',
        deduction_type_id: '',
        description: '',
        quantity: 1,
        rate: 0,
        amount: 0,
    });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

/** Laravel returns these as `lines.0.amount`; `row` is matched back to its
 *  position because the register slot hands back the row, not the index. */
const lineError = (row: FormLine, field: string) =>
    (form.errors as Record<string, string>)[
        `lines.${form.lines.indexOf(row)}.${field}`
    ];

const lineColumns = [
    { key: 'type', label: 'Type', kind: 'text' as const },
    { key: 'description', label: 'Description', kind: 'text' as const },
    { key: 'quantity', label: 'Qty', kind: 'amount' as const },
    { key: 'rate', label: 'Rate', kind: 'amount' as const },
    { key: 'earning', label: 'Earning', kind: 'in' as const },
    { key: 'deduction', label: 'Deduction', kind: 'out' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const grossPay = computed(() =>
    form.lines
        .filter((line) => line.line_type === 'earning')
        .reduce((sum, line) => sum + Number(line.amount || 0), 0),
);

const manualDeductions = computed(() =>
    form.lines
        .filter((line) => line.line_type === 'deduction')
        .reduce((sum, line) => sum + Number(line.amount || 0), 0),
);

const advanceRecovery = computed(() =>
    advanceLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0),
);

const netPay = computed(
    () => grossPay.value - manualDeductions.value - advanceRecovery.value,
);

const submit = () => {
    form.put(`/${props.company.slug}/payslips/${props.payslip.id}`);
};
</script>

<template>
    <Head :title="`Edit payslip ${payslip.payslip_number}`" />

    <PageShell
        :title="`Edit payslip ${payslip.payslip_number}`"
        description="Change the lines and notes of a draft payslip."
        :breadcrumbs="breadcrumbs"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/payslips/${payslip.id}`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
        </template>

        <form novalidate @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <Card v-if="isForeignCurrency">
                        <CardHeader>
                            <CardTitle class="text-base">Conversion</CardTitle>
                            <CardDescription>
                                This payslip is in {{ payslip.currency }}; the
                                books are kept in {{ payslip.base_currency }}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="max-w-xs space-y-2">
                                <Label for="exchange-rate">Exchange rate *</Label>
                                <Input
                                    id="exchange-rate"
                                    v-model.number="form.exchange_rate"
                                    type="number"
                                    min="0.00000001"
                                    step="0.00000001"
                                />
                                <p class="text-xs text-text-secondary">
                                    1 {{ payslip.currency }} = this many
                                    {{ payslip.base_currency }}
                                </p>
                                <InputError :message="form.errors.exchange_rate" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-base">Payslip lines</CardTitle>
                                    <CardDescription>
                                        Earnings and deductions on this payslip.
                                        Salary advance recoveries are
                                        recalculated automatically when you
                                        save.
                                    </CardDescription>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addLine('earning')"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add earning
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addLine('deduction')"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add deduction
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <LedgerRegister
                                :data="form.lines"
                                :columns="lineColumns"
                                :key-field="(_row: FormLine, i: number) => i"
                                :totals="{
                                    earning: formatMoneyText(grossPay, payslip.currency),
                                    deduction: formatMoneyText(manualDeductions, payslip.currency),
                                }"
                            >
                                <template #empty>
                                    No lines on this payslip. Add an earning or
                                    a deduction.
                                </template>

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
                                    <Select v-else v-model="row.deduction_type_id">
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
                                    <InputError
                                        :message="row.line_type === 'earning'
                                            ? lineError(row, 'earning_type_id')
                                            : lineError(row, 'deduction_type_id')"
                                    />
                                </template>

                                <template #cell-description="{ row }">
                                    <Input v-model="row.description" placeholder="Description" />
                                    <InputError :message="lineError(row, 'description')" />
                                </template>

                                <template #cell-quantity="{ row }">
                                    <Input
                                        v-model.number="row.quantity"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <InputError :message="lineError(row, 'quantity')" />
                                </template>

                                <template #cell-rate="{ row }">
                                    <Input
                                        v-model.number="row.rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <InputError :message="lineError(row, 'rate')" />
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
                                    <InputError
                                        v-if="row.line_type === 'earning'"
                                        :message="lineError(row, 'amount')"
                                    />
                                </template>

                                <template #cell-deduction="{ row }">
                                    <Input
                                        v-if="row.line_type !== 'earning'"
                                        v-model.number="row.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <span v-else>—</span>
                                    <InputError
                                        v-if="row.line_type !== 'earning'"
                                        :message="lineError(row, 'amount')"
                                    />
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

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                v-model="form.notes"
                                placeholder="Any additional notes..."
                                rows="3"
                            />
                            <InputError :message="form.errors.notes" />
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle class="text-base">Pay summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-text-secondary">Gross pay</span>
                                <MoneyText :amount="grossPay" :currency="payslip.currency" />
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-text-secondary">Deductions</span>
                                <MoneyText
                                    :amount="manualDeductions"
                                    :currency="payslip.currency"
                                    direction="outflow"
                                />
                            </div>
                            <div
                                v-if="advanceRecovery > 0"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-text-secondary">Advance recovery</span>
                                <MoneyText
                                    :amount="advanceRecovery"
                                    :currency="payslip.currency"
                                    direction="outflow"
                                />
                            </div>
                            <hr />
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">Net pay</span>
                                <MoneyText
                                    :amount="netPay"
                                    :currency="payslip.currency"
                                    scale="conclusion"
                                />
                            </div>

                            <div class="pt-4">
                                <Button
                                    type="submit"
                                    class="w-full"
                                    :disabled="form.processing"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    </PageShell>
</template>
