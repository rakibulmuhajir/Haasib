<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RelatedActions from '@/components/RelatedActions.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
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
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { entryDateDefault, rememberEntryDate } from '@/composables/useEntryDate';
import EntryDateNote from '@/components/EntryDateNote.vue';

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
    preselect?: {
        customer_id?: string | null;
        invoice_id?: string | null;
    };
    amanat?: { enabled: boolean } | null;
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

// Only offer a currency picker when this company actually uses more than one
// currency (CompanyCurrencyOptions always includes the base currency itself,
// so length 1 means "base only"). Otherwise the base currency travels as a
// hidden value nobody has to think about.
const showCurrencyPicker = computed(() => props.currencies.length > 1);

const form = useForm({
    customer_id: props.preselect?.customer_id || '',
    amount: moneyNumber(preselectedInvoice?.balance),
    transaction_charge: 0,
    currency: preselectedInvoice?.currency || props.company.base_currency,
    payment_method: '' as string,
    reference_number: '',
    payment_date: entryDateDefault(props.company.slug),
    notes: '',
    deposit_account_id: '',
    received_as: 'invoices' as 'invoices' | 'amanat',
});

// Amanat is base-currency only (see AmanatService::deposit) - force the currency field
// back to base whenever the user switches into that path, since the field is hidden and
// nothing else would keep it in sync.
watch(
    () => form.received_as,
    (value) => {
        if (value === 'amanat') {
            form.currency = props.company.base_currency;
        }
    },
);

// Invoices this payment has been ticked to settle. Empty means "let the server
// auto-allocate oldest-first" (Payment\CreateAction's own default), exactly like
// leaving every row untouched on the daily close's payments panel does.
const selectedInvoiceIds = ref<string[]>(
    props.preselect?.invoice_id ? [props.preselect.invoice_id] : [],
);

function toggleInvoice(invoiceId: string, checked: boolean) {
    if (checked) {
        if (!selectedInvoiceIds.value.includes(invoiceId)) {
            selectedInvoiceIds.value = [...selectedInvoiceIds.value, invoiceId];
        }
    } else {
        selectedInvoiceIds.value = selectedInvoiceIds.value.filter(
            (id) => id !== invoiceId,
        );
    }
}

// Filter invoices by selected customer, oldest first (matches
// Payment\CreateAction's own oldest-invoice_date-then-invoice_number tie-break, so the
// live "pays X of N" preview below lands on the same invoices the backend would choose).
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

// When customer changes, reset the ticked invoices and preselect the sole invoice's
// balance (and currency) when there is exactly one, mirroring the old single-select
// convenience.
watch(
    () => form.customer_id,
    () => {
        selectedInvoiceIds.value = [];
        if (customerInvoices.value.length === 1) {
            const only = customerInvoices.value[0];
            form.currency = only.currency;
            if (form.amount === 0) form.amount = moneyNumber(only.balance);
        }
    },
);

// Live preview of how this payment would land: the same oldest-first greedy fill
// Payment\CreateAction::autoAllocate() runs server-side, over whichever invoices are
// in scope (the ticked ones, or every open invoice for this buyer when none are ticked).
const allocationPreview = computed(() => {
    const scope = selectedInvoiceIds.value.length
        ? customerInvoices.value.filter((inv) =>
              selectedInvoiceIds.value.includes(inv.id),
          )
        : customerInvoices.value;

    let remaining = Number(form.amount || 0);
    let paid = 0;
    for (const inv of scope) {
        if (remaining <= 0.001) break;
        const take = Math.min(remaining, Number(inv.balance));
        if (take > 0.001) paid++;
        remaining = moneyNumber(remaining - take);
    }

    return {
        paid,
        total: scope.length,
        credit: moneyNumber(Math.max(0, remaining)),
    };
});

const netMovement = computed(() =>
    Math.max(
        0,
        Number(form.amount || 0) - Number(form.transaction_charge || 0),
    ),
);

const showBankCharge = ref(false);

const submit = () => {
    form.transform((data) => ({
        ...data,
        invoice_ids: selectedInvoiceIds.value,
        // The server derives payment_method from the deposit account's own subtype
        // (cash vs bank) when this is left blank - see PaymentController::store().
        payment_method: data.payment_method || undefined,
        currency: showCurrencyPicker.value ? data.currency : props.company.base_currency,
    })).post(`/${props.company.slug}/payments`, {
        onError: (errors) => showError(errors),
        onFinish: () => form.transform((data) => data),
    });
};

// Keep the account identity explicit when a Reka Select changes. This is
// especially useful when several bank accounts have similar labels: the
// selected UUID, not the display text, is what the payment request needs.
// Also defaults the payment method from the account's own subtype (cash vs
// bank), matching how the daily close's payments panel derives it - the user
// can still override it via the compact select next to the account.
const setDepositAccount = (value: string) => {
    form.deposit_account_id = value;
    form.clearErrors('deposit_account_id');
    const account = (props.depositAccounts || []).find((a) => a.id === value);
    form.payment_method = account?.subtype === 'cash' ? 'cash' : 'bank_transfer';
};

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'card', label: 'Card' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'other', label: 'Other' },
];

// Start on the last date used in this tab, and remember changes - see useEntryDate.
rememberEntryDate(props.company.slug, () => form.payment_date);
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

            <Card variant="form">
                <CardHeader>
                    <CardTitle>Record payment</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                        <div v-if="showCurrencyPicker">
                            <Label for="currency">Currency *</Label>
                            <Select v-model="form.currency" required>
                                <SelectTrigger id="currency">
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
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.currency" />
                        </div>
                    </div>

                    <div v-if="amanat?.enabled">
                        <Label>Received as</Label>
                        <div class="mt-1 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="relative">
                                <input
                                    id="received_as_invoices"
                                    v-model="form.received_as"
                                    value="invoices"
                                    type="radio"
                                    class="peer sr-only"
                                />
                                <label
                                    for="received_as_invoices"
                                    class="flex cursor-pointer flex-col rounded-lg border-2 border-muted bg-popover p-3 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground hover:bg-accent hover:text-accent-foreground"
                                >
                                    <span class="text-sm font-medium"
                                        >Payment against invoices</span
                                    >
                                </label>
                            </div>
                            <div class="relative">
                                <input
                                    id="received_as_amanat"
                                    v-model="form.received_as"
                                    value="amanat"
                                    type="radio"
                                    class="peer sr-only"
                                />
                                <label
                                    for="received_as_amanat"
                                    class="flex cursor-pointer flex-col rounded-lg border-2 border-muted bg-popover p-3 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground hover:bg-accent hover:text-accent-foreground"
                                >
                                    <span class="text-sm font-medium"
                                        >Amanat (advance held for the
                                        customer)</span
                                    >
                                </label>
                            </div>
                        </div>
                        <p
                            v-if="form.received_as === 'amanat'"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Held for the customer and used up against fuel.
                            Shows on their amanat page.
                        </p>
                        <InputError :message="form.errors.received_as" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            <EntryDateNote :date="form.payment_date" />
                            <InputError :message="form.errors.payment_date" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <Label for="deposit_account_id">Received into *</Label>
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
                        <div v-if="form.deposit_account_id">
                            <Label for="payment_method">Method</Label>
                            <Select v-model="form.payment_method">
                                <SelectTrigger id="payment_method">
                                    <SelectValue placeholder="Method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="method in paymentMethodOptions"
                                        :key="method.value"
                                        :value="method.value"
                                    >
                                        {{ method.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.payment_method" />
                        </div>
                    </div>

                    <!-- Invoices -->
                    <div v-if="form.customer_id && form.received_as !== 'amanat'">
                        <Label>Invoices</Label>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tick any invoices this payment should settle. Leave
                            all unticked to apply it oldest-first
                            automatically.
                        </p>
                        <div
                            v-if="customerInvoices.length > 0"
                            class="mt-2 space-y-2 rounded-md border p-3"
                        >
                            <div
                                v-for="invoice in customerInvoices"
                                :key="invoice.id"
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`invoice_${invoice.id}`"
                                        :checked="
                                            selectedInvoiceIds.includes(invoice.id)
                                        "
                                        @update:checked="
                                            (val) => toggleInvoice(invoice.id, val)
                                        "
                                    />
                                    <Label
                                        :for="`invoice_${invoice.id}`"
                                        class="cursor-pointer font-normal"
                                    >
                                        {{ invoice.invoice_number }}
                                        <span class="text-muted-foreground">
                                            ({{ invoice.invoice_date }})</span
                                        >
                                    </Label>
                                </div>
                                <MoneyText
                                    :amount="invoice.balance"
                                    :currency="invoice.currency"
                                    class="text-sm"
                                />
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            This buyer has no open invoices — the full payment
                            will be recorded on their account.
                        </p>
                        <p
                            v-if="customerInvoices.length > 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            Pays {{ allocationPreview.paid }} of
                            {{ allocationPreview.total }} invoices ·
                            <MoneyText
                                :amount="allocationPreview.credit"
                                :currency="form.currency || 'USD'"
                            />
                            stays as credit
                        </p>
                        <InputError :message="form.errors.invoice_ids" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="reference_number">Reference Number</Label>
                            <Input
                                id="reference_number"
                                v-model="form.reference_number"
                                placeholder="Check #, transaction ID, etc."
                            />
                            <InputError :message="form.errors.reference_number" />
                        </div>
                        <div>
                            <Label for="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                placeholder="Additional payment notes..."
                                rows="1"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div v-if="form.received_as !== 'amanat'">
                        <Button
                            v-if="!showBankCharge"
                            type="button"
                            variant="link"
                            size="sm"
                            class="h-auto p-0 text-muted-foreground"
                            @click="showBankCharge = true"
                        >
                            Bank charge?
                        </Button>
                        <div v-else>
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
                                class="max-w-xs"
                            />
                            <InputError
                                :message="form.errors.transaction_charge"
                            />
                            <p class="mt-1 text-sm text-muted-foreground">
                                Bank or transfer fee deducted from the
                                deposit. Net movement:
                                <MoneyText
                                    :amount="netMovement"
                                    :currency="form.currency || 'USD'"
                                />
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </form>

        <RelatedActions screen="payment.create" :slug="company.slug" />
    </PageShell>
</template>
