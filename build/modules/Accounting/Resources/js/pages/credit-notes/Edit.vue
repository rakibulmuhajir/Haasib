<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { ArrowLeft, Receipt, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface Customer {
    id: string;
    name: string;
}

interface Invoice {
    id: string;
    customer_id: string;
    invoice_number: string;
    total_amount: number;
    currency: string;
    exchange_rate?: number | null;
}

interface CreditNote {
    id: string;
    credit_note_number: string;
    customer: Customer;
    invoice?: Invoice;
    amount: number;
    base_currency: string;
    currency?: string;
    exchange_rate?: number | null;
    transaction_id?: string | null;
    period_discount?: unknown;
    reason: string;
    status: string;
    credit_date: string;
    notes?: string;
    terms?: string;
    sent_at?: string;
    posted_at?: string;
    voided_at?: string;
    created_at: string;
}

interface CurrencyRef {
    currency_code: string;
    exchange_rate?: number | null;
}

const props = defineProps<{
    company: CompanyRef;
    credit_note: CreditNote;
    customers: Customer[];
    invoices: Invoice[];
    currencies: CurrencyRef[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.company.name, href: `/${props.company.slug}` },
    { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
    {
        title: props.credit_note.credit_note_number,
        href: `/${props.company.slug}/credit-notes/${props.credit_note.id}`,
    },
    { title: 'Edit' },
]);

const form = useForm({
    customer_id: props.credit_note.customer.id,
    invoice_id: props.credit_note.invoice?.id || 'company_default',
    amount: props.credit_note.amount,
    base_currency: props.company.base_currency,
    currency: props.credit_note.currency ?? props.company.base_currency,
    exchange_rate: props.credit_note.exchange_rate ?? null,
    reason: props.credit_note.reason,
    status: props.credit_note.status,
    credit_date: props.credit_note.credit_date,
    notes: props.credit_note.notes || '',
    terms: props.credit_note.terms || '',
});

// Filter invoices based on selected customer
const availableInvoices = computed(() => {
    if (!form.customer_id) return [];
    return props.invoices.filter(
        (invoice) => invoice.customer_id === form.customer_id,
    );
});

const { showError } = useFormFeedback();
const selectedInvoice = computed(() =>
    props.invoices.find((invoice) => invoice.id === form.invoice_id),
);
const isForeign = computed(() => form.currency !== props.company.base_currency);
watch(
    () => form.invoice_id,
    () => {
        if (selectedInvoice.value) {
            form.currency = selectedInvoice.value.currency;
            form.exchange_rate = selectedInvoice.value.exchange_rate ?? null;
        }
    },
);
watch(
    () => form.currency,
    () => {
        form.exchange_rate = isForeign.value
            ? (selectedInvoice.value?.exchange_rate ??
              props.currencies.find(
                  (currency) => currency.currency_code === form.currency,
              )?.exchange_rate ??
              null)
            : null;
    },
);

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'issued', label: 'Issued' },
];

const submit = () => {
    form.transform((data) => {
        const transformed = { ...data };
        if (transformed.invoice_id === 'company_default') {
            transformed.invoice_id = null;
        }
        return transformed;
    }).put(`/${props.company.slug}/credit-notes/${props.credit_note.id}`, {
        onError: showError,
    });
};

const isEditable = computed(() => {
    return (
        props.credit_note.status === 'draft' &&
        !props.credit_note.transaction_id &&
        !props.credit_note.period_discount
    );
});
</script>

<template>
    <Head :title="`Edit Credit Note ${credit_note.credit_note_number}`" />

    <PageShell :title="`Edit Credit Note`" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/credit-notes/${credit_note.id}`,
                    )
                "
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Cancel
            </Button>
            <Button @click="submit" :disabled="form.processing || !isEditable">
                <Save class="mr-2 h-4 w-4" />
                Save Changes
            </Button>
        </template>

        <!-- Credit Note Header with Clickable Number -->
        <Card class="mb-6" variant="detail">
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <Receipt class="h-5 w-5 text-muted-foreground" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Credit Note</span
                            >
                            <StatusBadge :status="credit_note.status" />
                        </div>
                        <button
                            @click="
                                router.get(
                                    `/${company.slug}/credit-notes/${credit_note.id}`,
                                )
                            "
                            class="text-2xl font-bold transition-colors hover:text-primary"
                        >
                            {{ credit_note.credit_note_number }}
                        </button>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-status-success">
                            <MoneyText
                                :amount="credit_note.amount"
                                :currency="credit_note.base_currency"
                            />
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ credit_note.base_currency }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="!isEditable" class="mb-6">
            <Card
                class="border-status-attention/30 bg-status-attention/10"
                variant="detail"
            >
                <CardContent class="pt-6">
                    <div class="flex items-center">
                        <StatusBadge
                            :status="credit_note.status"
                            class="mr-2"
                        />
                        <span class="text-sm"
                            >This credit note cannot be edited in its current
                            status.</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <form novalidate @submit.prevent="submit" class="space-y-6">
            <!-- Customer Information -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Customer Information</CardTitle>
                    <CardDescription
                        >Select the customer for this credit
                        note</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="customer_id">Customer *</Label>
                        <Select
                            v-model="form.customer_id"
                            required
                            :disabled="!isEditable"
                            @update:modelValue="
                                form.invoice_id = 'company_default'
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a customer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="customer in customers"
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
                        <Label for="invoice_id"
                            >Apply to Invoice (Optional)</Label
                        >
                        <Select
                            v-model="form.invoice_id"
                            :disabled="!isEditable || !form.customer_id"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select an invoice" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="company_default"
                                    >No specific invoice</SelectItem
                                >
                                <SelectItem
                                    v-for="invoice in availableInvoices"
                                    :key="invoice.id"
                                    :value="invoice.id"
                                >
                                    {{ invoice.invoice_number }} -
                                    <MoneyText
                                        :amount="invoice.total_amount"
                                        :currency="invoice.currency"
                                    />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="
                                form.customer_id &&
                                availableInvoices.length === 0
                            "
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            No invoices found for this customer
                        </p>
                        <p
                            v-if="!form.customer_id"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Select a customer first to see available invoices
                        </p>
                        <InputError :message="form.errors.invoice_id" />
                    </div>
                </CardContent>
            </Card>

            <!-- Credit Note Details -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Credit Note Details</CardTitle>
                    <CardDescription
                        >Basic information about the credit
                        note</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            :disabled="!isEditable"
                        />
                        <p class="mt-1 text-sm text-muted-foreground">
                            <MoneyText
                                :amount="form.amount"
                                :currency="
                                    form.currency || company.base_currency
                                "
                            />
                        </p>
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div>
                        <Label for="credit_date">Credit Date *</Label>
                        <Input
                            id="credit_date"
                            v-model="form.credit_date"
                            type="date"
                            required
                            :disabled="!isEditable"
                        />
                        <InputError :message="form.errors.credit_date" />
                    </div>
                    <div>
                        <Label for="status">Status *</Label>
                        <Select
                            v-model="form.status"
                            required
                            :disabled="!isEditable"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div>
                        <Label for="base_currency">Currency *</Label>
                        <Select
                            v-model="form.currency"
                            :disabled="
                                !isEditable ||
                                (!!form.invoice_id &&
                                    form.invoice_id !== 'company_default')
                            "
                            required
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="currency in currencies"
                                    :key="currency.currency_code"
                                    :value="currency.currency_code"
                                >
                                    {{ currency.currency_code
                                    }}{{
                                        currency.currency_code ===
                                        company.base_currency
                                            ? ' (Base)'
                                            : ''
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="
                                form.errors.currency ||
                                form.errors.base_currency
                            "
                        />
                    </div>
                    <div v-if="isForeign">
                        <Label for="credit-exchange-rate"
                            >Exchange rate · 1 {{ form.currency }} in
                            {{ company.base_currency }}</Label
                        >
                        <Input
                            id="credit-exchange-rate"
                            v-model.number="form.exchange_rate"
                            type="number"
                            min="0.00000001"
                            step="0.00000001"
                            :disabled="!!selectedInvoice"
                        />
                        <InputError :message="form.errors.exchange_rate" />
                    </div>
                </CardContent>
            </Card>

            <!-- Reason -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Reason for Credit</CardTitle>
                    <CardDescription
                        >Explain why this credit note is being
                        issued</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div>
                        <Label for="reason">Reason *</Label>
                        <Input
                            id="reason"
                            v-model="form.reason"
                            placeholder="e.g., Product return, Service discount, Billing error"
                            required
                            :disabled="!isEditable"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Information -->
            <Card variant="form">
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                    <CardDescription>Optional terms and notes</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="terms">Terms</Label>
                        <Textarea
                            id="terms"
                            v-model="form.terms"
                            placeholder="Credit note terms and conditions..."
                            rows="3"
                            :disabled="!isEditable"
                        />
                        <InputError :message="form.errors.terms" />
                    </div>
                    <div>
                        <Label for="notes">Internal Notes</Label>
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Internal notes about this credit note..."
                            rows="3"
                            :disabled="!isEditable"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </CardContent>
            </Card>

            <!-- Summary -->
            <Card variant="detail">
                <CardHeader>
                    <CardTitle>Credit Note Summary</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex justify-between">
                        <span>Credit Amount:</span>
                        <span class="font-bold text-status-success"
                            ><MoneyText
                                :amount="form.amount"
                                :currency="
                                    form.currency || company.base_currency
                                "
                        /></span>
                    </div>
                    <div
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Customer:</span>
                        <span>{{
                            customers.find((c) => c.id === form.customer_id)
                                ?.name || credit_note.customer.name
                        }}</span>
                    </div>
                    <div
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Status:</span>
                        <span>{{
                            statusOptions.find((s) => s.value === form.status)
                                ?.label
                        }}</span>
                    </div>
                    <div
                        v-if="form.reason"
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Reason:</span>
                        <span>{{ form.reason }}</span>
                    </div>
                    <div
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span>Applied to:</span>
                        <span v-if="form.invoice_id === 'company_default'"
                            >No specific invoice</span
                        >
                        <span v-else>{{
                            availableInvoices.find(
                                (i) => i.id === form.invoice_id,
                            )?.invoice_number ||
                            credit_note.invoice?.invoice_number ||
                            'Unknown invoice'
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
