<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RelatedActions from '@/components/RelatedActions.vue';
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
import { useFormFeedback } from '@/composables/useFormFeedback';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building,
    CreditCard,
    DollarSign,
    FileText,
    Save,
} from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface InvoiceRef {
    id: string;
    customer_id: string;
    invoice_number: string;
    invoice_date: string;
    total_amount: number;
    balance: number;
    currency: string;
}

interface CurrencyRef {
    currency_code: string;
}

interface AccountOption {
    id: string;
    code: string;
    name: string;
    subtype?: string;
}

const props = defineProps<{
    company: CompanyRef;
    customers: Array<{ id: string; name: string }>;
    invoices: InvoiceRef[];
    currencies: CurrencyRef[];
    depositAccounts?: AccountOption[];
    arAccounts?: AccountOption[];
    preselect?: {
        customer_id?: string | null;
        invoice_id?: string | null;
    };
}>();

// API decimal values may arrive as six-place strings (the database keeps
// extra precision for posting). Keep money inputs at the user-facing two-place
// precision used throughout this form.
const moneyNumber = (value: unknown) =>
    Number(Number(value ?? 0).toFixed(2));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.company.name, href: `/${props.company.slug}` },
    { title: 'Payments', href: `/${props.company.slug}/payments` },
    { title: 'Record Payment' },
]);

// Get preselected invoice details for initial amount
const preselectedInvoice = props.preselect?.invoice_id
    ? props.invoices.find((inv) => inv.id === props.preselect?.invoice_id)
    : null;

const { showError } = useFormFeedback();

const form = useForm({
    customer_id: props.preselect?.customer_id || '',
    amount: moneyNumber(preselectedInvoice?.balance),
    transaction_charge: 0,
    currency: preselectedInvoice?.currency || props.company.base_currency,
    payment_method: 'bank_transfer',
    reference_number: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
    deposit_account_id: '',
    ar_account_id: 'company_default',
});

// Per-invoice amount the buyer's payment settles, keyed by invoice id. Any invoice not
// present here (or present at 0) contributes nothing and is omitted from the
// `allocations` sent to the server; whatever is left of form.amount after these lands
// on the buyer's account, exactly like leaving every row untouched always has.
const invoiceAmounts = reactive<Record<string, number>>({});

const paymentMethods = [
    { value: 'cash', label: 'Cash', icon: DollarSign },
    { value: 'bank_transfer', label: 'Bank Transfer', icon: Building },
    { value: 'card', label: 'Card', icon: CreditCard },
    { value: 'cheque', label: 'Cheque', icon: FileText },
    { value: 'other', label: 'Other', icon: DollarSign },
];

// Filter invoices by selected customer, oldest first (matches
// Payment\CreateAction's own oldest-invoice_date-then-invoice_number tie-break, so the
// "auto-fill oldest-first" button below allocates in the same order the backend would
// have chosen on its own).
const customerInvoices = computed(() => {
    if (!form.customer_id) return [];
    return props.invoices
        .filter((inv) => inv.customer_id === form.customer_id)
        .slice()
        .sort((a, b) =>
            a.invoice_date === b.invoice_date
                ? a.invoice_number.localeCompare(b.invoice_number)
                : a.invoice_date.localeCompare(b.invoice_date),
        );
});

const netMovement = computed(() =>
    Math.max(
        0,
        Number(form.amount || 0) - Number(form.transaction_charge || 0),
    ),
);

const allocatedTotal = computed(() =>
    moneyNumber(
        customerInvoices.value.reduce(
            (sum, inv) => sum + Number(invoiceAmounts[inv.id] || 0),
            0,
        ),
    ),
);

const onAccountRemainder = computed(() =>
    moneyNumber(Math.max(0, Number(form.amount || 0) - allocatedTotal.value)),
);

const overAllocated = computed(
    () => allocatedTotal.value > Number(form.amount || 0) + 0.001,
);

function resetInvoiceAmounts() {
    for (const key of Object.keys(invoiceAmounts)) delete invoiceAmounts[key];
    for (const inv of customerInvoices.value) invoiceAmounts[inv.id] = 0;
}

// Distributes the payment amount across this buyer's open invoices, oldest first,
// capping each at its own outstanding balance -- the same greedy fill
// Payment\CreateAction::autoAllocate() performs server-side, just made visible and
// editable before it is submitted.
function autoFillOldestFirst() {
    let remaining = Number(form.amount || 0);
    for (const inv of customerInvoices.value) {
        const take = Math.max(0, Math.min(remaining, Number(inv.balance)));
        invoiceAmounts[inv.id] = moneyNumber(take);
        remaining = moneyNumber(remaining - take);
    }
}

function clearAllocations() {
    for (const inv of customerInvoices.value) invoiceAmounts[inv.id] = 0;
}

// When customer changes, reset per-invoice amounts and preselect the sole invoice's
// balance (and currency) when there is exactly one, mirroring the old single-select
// convenience.
watch(
    () => form.customer_id,
    () => {
        resetInvoiceAmounts();
        if (customerInvoices.value.length === 1) {
            const only = customerInvoices.value[0];
            form.currency = only.currency;
            if (form.amount === 0) form.amount = moneyNumber(only.balance);
        }
    },
);

// Keep the invoice-amount map in sync as the invoice list for this customer settles
// (e.g. after the initial customer_id prop resolves customerInvoices for the first time).
watch(
    customerInvoices,
    (list) => {
        for (const inv of list) {
            if (!(inv.id in invoiceAmounts)) invoiceAmounts[inv.id] = 0;
        }
    },
    { immediate: true },
);

if (props.preselect?.invoice_id) {
    invoiceAmounts[props.preselect.invoice_id] = moneyNumber(
        preselectedInvoice?.balance,
    );
}

const submit = () => {
    form.transform((data) => ({
        ...data,
        allocations: customerInvoices.value
            .map((inv) => ({
                invoice_id: inv.id,
                amount: Number(invoiceAmounts[inv.id] || 0),
            }))
            .filter((row) => row.amount > 0.001),
    })).post(`/${props.company.slug}/payments`, {
        onError: (errors) => showError(errors),
        onFinish: () => form.transform((data) => data),
    });
};

// Keep the account identity explicit when a Reka Select changes. This is
// especially useful when several bank accounts have similar labels: the
// selected UUID, not the display text, is what the payment request needs.
const setDepositAccount = (value: string) => {
    form.deposit_account_id = value;
    form.clearErrors('deposit_account_id');
};
</script>

<template>
    <Head title="Record Payment" />

    <PageShell title="Record Payment" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/payments`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button @click="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                Record Payment
            </Button>
        </template>

        <form novalidate @submit.prevent="submit" class="space-y-6">
            <!-- General Errors -->
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-md bg-destructive/15 p-4"
            >
                <div class="text-sm text-destructive">
                    <p class="font-medium">Please fix the following errors:</p>
                    <ul class="mt-2 list-inside list-disc">
                        <li v-for="(error, field) in form.errors" :key="field">
                            {{ error }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Payment Information -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Payment Information</CardTitle>
                    <CardDescription
                        >Enter the basic payment details</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label for="customer_id">Customer *</Label>
                        <Select v-model="form.customer_id" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a customer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="customer in customers.filter(
                                        (c) => c.id && c.id !== '',
                                    )"
                                    :key="customer.id"
                                    :value="customer.id"
                                >
                                    {{ customer.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.customer_id" />
                    </div>
                    <div>
                        <Label for="ar_account_id">AR Account</Label>
                        <Select v-model="form.ar_account_id">
                            <SelectTrigger id="ar_account_id">
                                <SelectValue
                                    placeholder="Use company default"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="company_default"
                                    >Use company default</SelectItem
                                >
                                <SelectItem
                                    v-for="acct in props.arAccounts || []"
                                    :key="acct.id"
                                    :value="acct.id"
                                >
                                    {{ acct.code }} — {{ acct.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.ar_account_id" />
                    </div>
                    <div>
                        <Label for="amount">Amount *</Label>
                        <Input
                            id="amount"
                            v-model.number="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                        <p class="mt-1 text-sm text-muted-foreground">
                            <MoneyText
                                :amount="form.amount"
                                :currency="form.currency || 'USD'"
                            />
                        </p>
                    </div>
                    <div>
                        <Label for="payment_date">Payment Date *</Label>
                        <Input
                            id="payment_date"
                            v-model="form.payment_date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.payment_date" />
                    </div>
                    <div>
                        <Label for="transaction_charge"
                            >Transaction Charges</Label
                        >
                        <Input
                            id="transaction_charge"
                            v-model.number="form.transaction_charge"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <InputError :message="form.errors.transaction_charge" />
                        <p class="mt-1 text-sm text-muted-foreground">
                            Bank or transfer fee deducted from the deposit.
                        </p>
                    </div>
                    <div>
                        <Label for="deposit_account_id">Deposit To *</Label>
                        <Select
                            :model-value="form.deposit_account_id"
                            @update:model-value="setDepositAccount"
                            required
                        >
                            <SelectTrigger id="deposit_account_id">
                                <SelectValue
                                    placeholder="Select bank/cash account"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="acct in props.depositAccounts || []"
                                    :key="acct.id"
                                    :value="acct.id"
                                >
                                    {{ acct.code }} — {{ acct.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.deposit_account_id" />
                    </div>
                    <div>
                        <Label for="currency">Currency *</Label>
                        <Select v-model="form.currency" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="curr in currencies"
                                    :key="curr.currency_code"
                                    :value="curr.currency_code"
                                >
                                    {{ curr.currency_code
                                    }}{{
                                        curr.currency_code ===
                                        company.base_currency
                                            ? ' (Base)'
                                            : ''
                                    }}
                                </SelectItem>
                                <SelectItem
                                    v-if="currencies.length === 0"
                                    value="USD"
                                    >USD</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.currency" />
                    </div>
                    <div>
                        <Label for="reference_number">Reference Number</Label>
                        <Input
                            id="reference_number"
                            v-model="form.reference_number"
                            placeholder="Check #, transaction ID, etc."
                        />
                        <InputError :message="form.errors.reference_number" />
                    </div>
                </CardContent>
            </Card>

            <!-- Invoice Allocation -->
            <Card v-if="form.customer_id" variant="form">
                <CardHeader>
                    <CardTitle>Invoice Allocation</CardTitle>
                    <CardDescription>
                        Choose how much of this payment settles each open
                        invoice. Anything left over is recorded on the
                        buyer's account.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="customerInvoices.length > 0">
                        <div class="mb-3 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="autoFillOldestFirst"
                            >
                                Auto-fill oldest first
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="clearAllocations"
                            >
                                Clear
                            </Button>
                        </div>

                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left">
                                    <tr>
                                        <th class="p-2 font-medium">
                                            Invoice
                                        </th>
                                        <th class="p-2 font-medium">Date</th>
                                        <th class="p-2 text-right font-medium">
                                            Total
                                        </th>
                                        <th class="p-2 text-right font-medium">
                                            Outstanding
                                        </th>
                                        <th class="p-2 text-right font-medium">
                                            Apply
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="invoice in customerInvoices"
                                        :key="invoice.id"
                                        class="border-t"
                                    >
                                        <td class="p-2">
                                            {{ invoice.invoice_number }}
                                        </td>
                                        <td class="p-2">
                                            {{ invoice.invoice_date }}
                                        </td>
                                        <td class="p-2 text-right">
                                            <MoneyText
                                                :amount="invoice.total_amount"
                                                :currency="invoice.currency"
                                            />
                                        </td>
                                        <td class="p-2 text-right">
                                            <MoneyText
                                                :amount="invoice.balance"
                                                :currency="invoice.currency"
                                            />
                                        </td>
                                        <td class="p-2 text-right">
                                            <Input
                                                v-model.number="
                                                    invoiceAmounts[invoice.id]
                                                "
                                                type="number"
                                                min="0"
                                                :max="invoice.balance"
                                                step="0.01"
                                                class="ml-auto w-32 text-right"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/40 p-3 text-sm"
                        >
                            <span>
                                Allocated
                                <MoneyText
                                    :amount="allocatedTotal"
                                    :currency="form.currency || 'USD'"
                                />
                                of
                                <MoneyText
                                    :amount="form.amount"
                                    :currency="form.currency || 'USD'"
                                />
                            </span>
                            <span>
                                <MoneyText
                                    :amount="onAccountRemainder"
                                    :currency="form.currency || 'USD'"
                                />
                                left on account
                            </span>
                        </div>
                        <p
                            v-if="overAllocated"
                            class="text-sm text-destructive"
                        >
                            Allocated amount exceeds the payment amount by
                            <MoneyText
                                :amount="allocatedTotal - Number(form.amount || 0)"
                                :currency="form.currency || 'USD'"
                            />.
                        </p>
                        <InputError :message="form.errors.allocations" />
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        This buyer has no open invoices — the full payment
                        will be recorded on their account.
                    </p>
                </CardContent>
            </Card>

            <!-- Payment Method -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Payment Method</CardTitle>
                    <CardDescription
                        >How was this payment made?</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                        <div
                            v-for="method in paymentMethods"
                            :key="method.value"
                            class="relative"
                        >
                            <input
                                :id="method.value"
                                v-model="form.payment_method"
                                :value="method.value"
                                type="radio"
                                class="peer sr-only"
                            />
                            <label
                                :for="method.value"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-muted bg-popover p-4 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground hover:bg-accent hover:text-accent-foreground"
                            >
                                <component
                                    :is="method.icon"
                                    class="mb-2 h-6 w-6"
                                />
                                <span class="text-sm font-medium">{{
                                    method.label
                                }}</span>
                            </label>
                        </div>
                    </div>
                    <InputError :message="form.errors.payment_method" />
                </CardContent>
            </Card>

            <!-- Notes -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                    <CardDescription
                        >Any additional notes about this
                        payment</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div>
                        <Label for="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Additional payment notes..."
                            rows="3"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </CardContent>
            </Card>

            <!-- Summary -->
            <Card variant="detail">
                <CardHeader>
                    <CardTitle>Payment Summary</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex justify-between">
                        <span>Payment Amount:</span>
                        <span class="font-bold"
                            ><MoneyText
                                :amount="form.amount"
                                :currency="form.currency || 'USD'"
                        /></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Transaction Charges:</span>
                        <span
                            ><MoneyText
                                :amount="form.transaction_charge"
                                :currency="form.currency || 'USD'"
                        /></span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span>Net Bank/Cash Movement:</span>
                        <span
                            ><MoneyText
                                :amount="netMovement"
                                :currency="form.currency || 'USD'"
                        /></span>
                    </div>
                    <div
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Payment Method:</span>
                        <span>{{
                            paymentMethods.find(
                                (m) => m.value === form.payment_method,
                            )?.label
                        }}</span>
                    </div>
                    <div
                        v-if="form.reference_number"
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Reference:</span>
                        <span>{{ form.reference_number }}</span>
                    </div>
                </CardContent>
            </Card>
        </form>

        <RelatedActions screen="payment.create" :slug="company.slug" />
    </PageShell>
</template>
