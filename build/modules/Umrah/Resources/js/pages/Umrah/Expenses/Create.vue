<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, ReceiptText, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type Account = {
    id: string;
    code: string;
    name: string;
    currency?: string | null;
};
type Currency = {
    currency_code: string;
    exchange_rate?: number | string | null;
};

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    expenseAccounts: Account[];
    paymentAccounts: Account[];
    currencies: Currency[];
}>();

const form = useForm({
    expense_date: new Date().toISOString().slice(0, 10),
    expense_account_id: '',
    payment_account_id: props.paymentAccounts[0]?.id || '',
    payee: '',
    description: '',
    reference: '',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: null as number | null,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Expenses', href: `/${props.company.slug}/umrah/expenses` },
    {
        title: 'Record expense',
        href: `/${props.company.slug}/umrah/expenses/create`,
    },
];

const isForeign = computed(() => form.currency !== props.company.base_currency);
const baseAmount = computed(
    () =>
        Number(form.amount || 0) *
        (isForeign.value ? Number(form.exchange_rate || 0) : 1),
);

watch(
    () => form.currency,
    (currency) => {
        if (currency === props.company.base_currency) {
            form.exchange_rate = null;
            return;
        }
        const configured = props.currencies.find(
            (item) => item.currency_code === currency,
        )?.exchange_rate;
        form.exchange_rate = configured ? Number(configured) : null;
    },
);

const submit = () => form.post(`/${props.company.slug}/umrah/expenses`);
</script>

<template>
    <Head title="Record Travel Expense" />
    <PageShell
        title="Record expense"
        description="Record a travel cost that has already been paid."
        :breadcrumbs="breadcrumbs"
        :icon="ReceiptText"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/expenses`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
        </template>

        <Alert
            v-if="!expenseAccounts.length || !paymentAccounts.length"
            variant="destructive"
        >
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Accounts required</AlertTitle>
            <AlertDescription
                >Add at least one expense account and one cash or bank account
                before recording an expense.</AlertDescription
            >
        </Alert>

        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >Expense details</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="expense-date">Date</Label>
                        <Input
                            id="expense-date"
                            v-model="form.expense_date"
                            type="date"
                            :aria-invalid="Boolean(form.errors.expense_date)"
                        />
                        <p
                            v-if="form.errors.expense_date"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.expense_date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Category</Label>
                        <Select v-model="form.expense_account_id">
                            <SelectTrigger
                                :class="{
                                    'border-destructive':
                                        form.errors.expense_account_id,
                                }"
                                ><SelectValue
                                    placeholder="Select expense category"
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem
                                    v-for="account in expenseAccounts"
                                    :key="account.id"
                                    :value="account.id"
                                    >{{ account.code }} -
                                    {{ account.name }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <p
                            v-if="form.errors.expense_account_id"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.expense_account_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Paid from</Label>
                        <Select v-model="form.payment_account_id">
                            <SelectTrigger
                                :class="{
                                    'border-destructive':
                                        form.errors.payment_account_id,
                                }"
                                ><SelectValue
                                    placeholder="Select cash or bank account"
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem
                                    v-for="account in paymentAccounts"
                                    :key="account.id"
                                    :value="account.id"
                                    >{{ account.code }} - {{ account.name
                                    }}<span v-if="account.currency">
                                        ({{ account.currency }})</span
                                    ></SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <p
                            v-if="form.errors.payment_account_id"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.payment_account_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="payee">Paid to</Label>
                        <Input
                            id="payee"
                            v-model="form.payee"
                            placeholder="Person or business (optional)"
                            :aria-invalid="Boolean(form.errors.payee)"
                        />
                        <p
                            v-if="form.errors.payee"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.payee }}
                        </p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="description">What was this for?</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Describe the expense"
                            :aria-invalid="Boolean(form.errors.description)"
                        />
                        <p
                            v-if="form.errors.description"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="reference">Reference</Label>
                        <Input
                            id="reference"
                            v-model="form.reference"
                            placeholder="Receipt or reference number (optional)"
                            :aria-invalid="Boolean(form.errors.reference)"
                        />
                        <p
                            v-if="form.errors.reference"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.reference }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >Amount paid</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid gap-5 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="amount">Amount</Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            min="0.000001"
                            step="0.000001"
                            placeholder="0.00"
                            :aria-invalid="Boolean(form.errors.amount)"
                        />
                        <p
                            v-if="form.errors.amount"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger
                                :class="{
                                    'border-destructive': form.errors.currency,
                                }"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem
                                    v-for="currency in currencies"
                                    :key="currency.currency_code"
                                    :value="currency.currency_code"
                                    >{{ currency.currency_code }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <p
                            v-if="form.errors.currency"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.currency }}
                        </p>
                    </div>
                    <div v-if="isForeign" class="space-y-2">
                        <Label for="exchange-rate">Exchange rate</Label>
                        <Input
                            id="exchange-rate"
                            v-model.number="form.exchange_rate"
                            type="number"
                            min="0.00000001"
                            step="0.00000001"
                            :aria-invalid="Boolean(form.errors.exchange_rate)"
                        />
                        <p class="text-xs text-muted-foreground">
                            1 {{ form.currency }} equals this many
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
                        v-if="isForeign && baseAmount > 0"
                        class="rounded-md border bg-muted/30 p-3 md:col-span-3"
                    >
                        <span class="text-sm text-muted-foreground"
                            >Recorded in base currency:
                        </span>
                        <MoneyText
                            :amount="baseAmount"
                            :currency="company.base_currency"
                            class="font-semibold"
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="router.get(`/${company.slug}/umrah/expenses`)"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    :disabled="
                        form.processing ||
                        !expenseAccounts.length ||
                        !paymentAccounts.length
                    "
                >
                    <Save class="mr-2 h-4 w-4" />{{
                        form.processing ? 'Recording...' : 'Record expense'
                    }}
                </Button>
            </div>
        </form>
    </PageShell>
</template>
