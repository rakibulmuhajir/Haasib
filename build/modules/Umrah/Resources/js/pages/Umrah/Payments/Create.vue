<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
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
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, WalletCards } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    agents: Array<{ id: string; name: string }>;
    visaVendors: Array<{ id: string; name: string }>;
    hotelVendors: Array<{ id: string; name: string }>;
    currencies: Array<{
        currency_code: string;
        is_base: boolean;
        exchange_rate: string | number;
    }>;
    directions: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Payments', href: `/${props.company.slug}/umrah/payments` },
    { title: 'Record Payment' },
];

const form = useForm({
    payment_date: new Date().toISOString().slice(0, 10),
    direction: 'received',
    agent_id: props.agents.length === 1 ? props.agents[0].id : 'none',
    payee: 'none',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: '',
});

const selectedCurrency = computed(() =>
    props.currencies.find(
        (currency) => currency.currency_code === form.currency,
    ),
);
const baseAmount = computed(
    () =>
        Math.round(
            Number(form.amount || 0) * Number(form.exchange_rate || 1) * 100,
        ) / 100,
);

watch(
    () => form.currency,
    (currency) => {
        form.exchange_rate =
            currency === props.company.base_currency
                ? ''
                : String(selectedCurrency.value?.exchange_rate || '');
    },
);
watch(
    () => form.direction,
    (direction) => {
        form.agent_id =
            direction === 'received' && props.agents.length === 1
                ? props.agents[0].id
                : 'none';
        form.payee = 'none';
    },
);

const submit = () =>
    form
        .transform((data) => ({
            payment_date: data.payment_date,
            direction: data.direction,
            agent_id:
                data.direction === 'received' && data.agent_id !== 'none'
                    ? data.agent_id
                    : null,
            visa_vendor_id:
                data.direction === 'sent' && data.payee.startsWith('visa:')
                    ? data.payee.slice(5)
                    : null,
            hotel_vendor_id:
                data.direction === 'sent' && data.payee.startsWith('hotel:')
                    ? data.payee.slice(6)
                    : null,
            amount: Number(data.amount || 0),
            currency: data.currency,
            exchange_rate:
                data.currency === props.company.base_currency
                    ? null
                    : Number(data.exchange_rate || 0),
        }))
        .post(`/${props.company.slug}/umrah/payments`, {
            onError: () => toast.error('Failed to record payment'),
        });
</script>

<template>
    <Head title="Record Payment" />
    <PageShell
        title="Record Payment"
        description="Enter the money movement. Accounting is handled automatically."
        :breadcrumbs="breadcrumbs"
        :icon="WalletCards"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/payments`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
        </template>

        <form class="mx-auto max-w-xl" @submit.prevent="submit">
            <Card>
                <CardHeader><CardTitle>Payment</CardTitle></CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <Label>Date</Label>
                        <Input v-model="form.payment_date" type="date" required />
                        <p
                            v-if="form.errors.payment_date"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.payment_date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Received or paid</Label>
                        <Select v-model="form.direction">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(_label, value) in directions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{
                                        value === 'received'
                                            ? 'Money received'
                                            : 'Money paid'
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="form.direction === 'received'" class="space-y-2">
                        <Label>Received from</Label>
                        <Select v-model="form.agent_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select agent" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Select agent</SelectItem>
                                <SelectItem
                                    v-for="agent in agents"
                                    :key="agent.id"
                                    :value="agent.id"
                                >
                                    {{ agent.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.agent_id" class="text-xs text-destructive">
                            {{ form.errors.agent_id }}
                        </p>
                    </div>

                    <div v-else class="space-y-2">
                        <Label>Paid to</Label>
                        <Select v-model="form.payee">
                            <SelectTrigger>
                                <SelectValue placeholder="Select vendor" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Select vendor</SelectItem>
                                <SelectItem
                                    v-for="vendor in visaVendors"
                                    :key="`visa-${vendor.id}`"
                                    :value="`visa:${vendor.id}`"
                                >
                                    {{ vendor.name }} · Visa / transport
                                </SelectItem>
                                <SelectItem
                                    v-for="vendor in hotelVendors"
                                    :key="`hotel-${vendor.id}`"
                                    :value="`hotel:${vendor.id}`"
                                >
                                    {{ vendor.name }} · Hotel
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.vendor_id" class="text-xs text-destructive">
                            {{ form.errors.vendor_id }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                        <div class="space-y-2">
                            <Label>Amount</Label>
                            <Input
                                v-model="form.amount"
                                type="number"
                                min="0.000001"
                                step="0.000001"
                                autofocus
                                required
                            />
                            <p v-if="form.errors.amount" class="text-xs text-destructive">
                                {{ form.errors.amount }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Currency</Label>
                            <Select v-model="form.currency">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="currency in currencies"
                                        :key="currency.currency_code"
                                        :value="currency.currency_code"
                                    >
                                        {{ currency.currency_code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.currency" class="text-xs text-destructive">
                                {{ form.errors.currency }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="form.currency !== company.base_currency"
                        class="space-y-2"
                    >
                        <Label>Conversion rate</Label>
                        <Input
                            v-model="form.exchange_rate"
                            type="number"
                            min="0.00000001"
                            step="0.00000001"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            1 {{ form.currency }} = {{ form.exchange_rate || 0 }}
                            {{ company.base_currency }} ·
                            <MoneyText
                                :amount="baseAmount"
                                :currency="company.base_currency"
                            />
                        </p>
                        <p
                            v-if="form.errors.exchange_rate"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.exchange_rate }}
                        </p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <WalletCards class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Recording…' : 'Record Payment' }}
                    </Button>
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
