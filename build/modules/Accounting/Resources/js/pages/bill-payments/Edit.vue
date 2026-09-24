<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
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
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CreditCard, Save } from 'lucide-vue-next';
import { computed } from 'vue';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface AccountOption {
    id: string;
    code: string;
    name: string;
    subtype?: string;
    normal_balance?: 'debit' | 'credit';
}

interface Allocation {
    bill_id: string;
    amount_allocated: number;
    bill?: {
        bill_number: string;
        balance: number | string;
    };
}

interface PaymentRef {
    id: string;
    payment_number: string;
    payment_date: string;
    amount: number;
    currency: string;
    payment_method: string;
    payment_account_id: string | null;
    reference_number: string | null;
    notes: string | null;
    vendor?: { id: string; name: string };
    allocations?: Allocation[];
}

const props = defineProps<{
    company: CompanyRef;
    payment: PaymentRef;
    bankAccounts?: AccountOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
    {
        title: props.payment.payment_number,
        href: `/${props.company.slug}/bill-payments/${props.payment.id}`,
    },
    { title: 'Edit', href: `/${props.company.slug}/bill-payments/${props.payment.id}/edit` },
];

const { showSuccess, showError } = useFormFeedback();
const page = usePage();

const form = useForm({
    payment_date: props.payment.payment_date.slice(0, 10),
    amount: Number(props.payment.amount),
    payment_method: props.payment.payment_method,
    payment_account_id: props.payment.payment_account_id ?? '',
    reference_number: props.payment.reference_number ?? '',
    notes: props.payment.notes ?? '',
});

const paymentMethods = [
    { value: 'cash', label: 'Cash' },
    { value: 'check', label: 'Check' },
    { value: 'card', label: 'Card' },
    { value: 'fuel_card', label: 'Fuel Card' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'ach', label: 'ACH' },
    { value: 'wire', label: 'Wire' },
    { value: 'other', label: 'Other' },
];

const accountLabel = (account: AccountOption) => `${account.code} — ${account.name}`;

const isSplitAcrossBills = computed(
    () => (props.payment.allocations?.length ?? 0) > 1,
);

const singleBill = computed(() =>
    !isSplitAcrossBills.value ? props.payment.allocations?.[0]?.bill : null,
);

const maxAmount = computed(() => {
    const bill = singleBill.value;
    if (!bill) return null;
    const allocation = props.payment.allocations?.[0];
    return (
        Number(bill.balance || 0) + Number(allocation?.amount_allocated || 0)
    );
});

const handleSubmit = () => {
    form.put(`/${props.company.slug}/bill-payments/${props.payment.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            const flash = (page.props as any)?.flash;
            if (flash?.error) {
                showError(flash.error);
                return;
            }
            showSuccess(flash?.success ?? 'Bill payment updated');
        },
        onError: (errors) => {
            showError(errors);
        },
    });
};
</script>

<template>
    <Head :title="`Edit Payment ${payment.payment_number}`" />
    <PageShell
        :title="`Edit Payment ${payment.payment_number}`"
        :breadcrumbs="breadcrumbs"
        :icon="CreditCard"
    >
        <form novalidate class="max-w-2xl space-y-6" @submit.prevent="handleSubmit">
            <div
                v-if="payment.vendor"
                class="text-sm text-muted-foreground"
            >
                Vendor: <span class="font-medium text-foreground">{{ payment.vendor.name }}</span>
            </div>

            <div
                v-if="isSplitAcrossBills"
                class="rounded-md border border-status-attention/30 bg-status-attention/10 p-3 text-sm text-status-attention"
            >
                This payment is split across several bills, so the amount
                can't be changed here. Void it and record separate payments
                instead if the amounts are wrong.
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <Label for="payment_date">Payment Date</Label>
                    <Input
                        id="payment_date"
                        v-model="form.payment_date"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.payment_date" />
                </div>
                <div>
                    <Label for="amount">Payment Amount</Label>
                    <Input
                        id="amount"
                        v-model.number="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        :disabled="isSplitAcrossBills"
                        required
                    />
                    <p
                        v-if="maxAmount !== null"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Up to
                        <MoneyText :amount="maxAmount" :currency="payment.currency" />
                        (the current balance on {{ singleBill?.bill_number }}
                        plus this payment).
                    </p>
                    <InputError :message="form.errors.amount" />
                </div>
                <div>
                    <Label for="payment_method">Method</Label>
                    <Select v-model="form.payment_method">
                        <SelectTrigger id="payment_method">
                            <SelectValue placeholder="Method" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="method in paymentMethods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.payment_method" />
                </div>
                <div>
                    <Label for="payment_account_id">Paid From</Label>
                    <Select v-model="form.payment_account_id">
                        <SelectTrigger id="payment_account_id">
                            <SelectValue placeholder="Select account" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="acct in props.bankAccounts || []"
                                :key="acct.id"
                                :value="acct.id"
                            >
                                {{ accountLabel(acct) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.payment_account_id" />
                </div>
                <div>
                    <Label for="reference_number">Reference</Label>
                    <Input
                        id="reference_number"
                        v-model="form.reference_number"
                    />
                    <InputError :message="form.errors.reference_number" />
                </div>
                <div class="md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <Textarea id="notes" v-model="form.notes" />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </PageShell>
</template>
