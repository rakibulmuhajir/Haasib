<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import { localToday } from '@/composables/useEntryDate';
import { useFormFeedback } from '@/composables/useFormFeedback';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

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
    total_amount: number;
    currency: string;
    exchange_rate?: number | null;
}

interface CurrencyRef {
    currency_code: string;
    exchange_rate?: number | null;
}

/** Prefilled from the screen you arrived from. */
interface Preselect {
    customer_id?: string | null;
    invoice_id?: string | null;
    vendor_id?: string | null;
}

const props = defineProps<{
    company: CompanyRef;
    customers: Array<{ id: string; name: string }>;
    invoices: InvoiceRef[];
    currencies: CurrencyRef[];
    preselect?: Preselect;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.company.name, href: `/${props.company.slug}` },
    { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
    { title: 'Create' },
]);

const form = useForm({
    customer_id: props.preselect?.customer_id ?? '',
    invoice_id: props.preselect?.invoice_id ?? 'company_default',
    amount: 0,
    base_currency: props.company.base_currency,
    currency: props.company.base_currency,
    exchange_rate: null as number | null,
    reason: '',
    status: 'draft',
    credit_date: localToday(),
    notes: '',
    terms: '',
});

const { showError } = useFormFeedback();
const selectedInvoice = computed(() =>
    props.invoices.find((invoice) => invoice.id === form.invoice_id),
);
const isForeign = computed(() => form.currency !== props.company.base_currency);
if (selectedInvoice.value) {
    form.currency = selectedInvoice.value.currency;
    form.exchange_rate = selectedInvoice.value.exchange_rate ?? null;
}
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

// Filter invoices by selected customer
const customerInvoices = computed(() => {
    if (!form.customer_id) return [];
    return props.invoices.filter((inv) => inv.customer_id === form.customer_id);
});

// When customer changes, reset invoice selection
watch(
    () => form.customer_id,
    () => {
        form.invoice_id = 'company_default';
    },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        invoice_id:
            data.invoice_id === 'company_default' ? null : data.invoice_id,
    })).post(`/${props.company.slug}/credit-notes`, { onError: showError });
};
</script>

<template>
    <Head title="Create Credit Note" />

    <PageShell title="Create Credit Note" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/credit-notes`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button @click="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                Create Credit Note
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
                        <Select v-model="form.customer_id" required>
                            <SelectTrigger
                                :class="{
                                    'border-destructive':
                                        form.errors.customer_id,
                                }"
                            >
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
                        <Label for="invoice_id"
                            >Apply to Invoice (Optional)</Label
                        >
                        <Select
                            v-model="form.invoice_id"
                            :disabled="!form.customer_id"
                        >
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="
                                        form.customer_id
                                            ? 'Select an invoice'
                                            : 'Select a customer first'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="company_default"
                                    >No specific invoice</SelectItem
                                >
                                <template v-if="customerInvoices.length > 0">
                                    <SelectItem
                                        v-for="invoice in customerInvoices"
                                        :key="invoice.id"
                                        :value="invoice.id"
                                    >
                                        {{ invoice.invoice_number }} -
                                        <MoneyText
                                            :amount="invoice.total_amount"
                                            :currency="invoice.currency"
                                        />
                                    </SelectItem>
                                </template>
                                <template v-else>
                                    <SelectItem value="none" disabled>
                                        {{
                                            form.customer_id
                                                ? 'No invoices for this customer'
                                                : 'Select a customer first'
                                        }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
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
                            :class="{
                                'border-destructive': form.errors.amount,
                            }"
                        />
                        <InputError :message="form.errors.amount" />
                        <p
                            v-if="!form.errors.amount"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            <MoneyText
                                :amount="form.amount"
                                :currency="
                                    form.currency || company.base_currency
                                "
                            />
                        </p>
                    </div>
                    <div>
                        <Label for="credit_date">Credit Date *</Label>
                        <Input
                            id="credit_date"
                            v-model="form.credit_date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.credit_date" />
                    </div>
                    <div>
                        <Label for="status">Status *</Label>
                        <Select v-model="form.status" required>
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
                                !!form.invoice_id &&
                                form.invoice_id !== 'company_default'
                            "
                            required
                        >
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
                                    :value="company.base_currency"
                                    >{{ company.base_currency }}</SelectItem
                                >
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
                            :class="{
                                'border-destructive': form.errors.reason,
                            }"
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
                        <span class="font-bold"
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
                            form.customer_id ? 'Selected' : 'Not selected'
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
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
