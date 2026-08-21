<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    currencies: Array<{ currency_code: string; exchange_rate: string | number }>;
    methods: Record<string, string>;
    allocationGroups: Array<{
        id: string;
        party_key: string;
        group_number: string;
        name: string;
        outstanding_amount: number;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Payments', href: `/${props.company.slug}/umrah/payments` },
    { title: 'Submit Payment' },
];

const form = useForm({
    visa_group_id: 'none',
    payment_date: new Date().toISOString().slice(0, 10),
    amount: '',
    currency: props.company.base_currency,
    method: 'cash',
    reference: '',
    notes: '',
});

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            visa_group_id: data.visa_group_id === 'none' ? null : data.visa_group_id,
            amount: Number(data.amount || 0),
        }))
        .post(`/${props.company.slug}/umrah/payments/submit`, {
            onError: () => toast.error('Failed to submit payment'),
        });
</script>

<template>
    <Head title="Submit Payment" />
    <PageShell
        title="Submit Payment"
        description="Report a payment you collected. An accountant will review and post it before it counts against your balance."
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

        <form novalidate class="mx-auto max-w-xl" @submit.prevent="submit">
            <Card variant="form">
                <CardHeader><CardTitle>Payment details</CardTitle></CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <Label>Date collected</Label>
                        <Input v-model="form.payment_date" type="date" required />
                        <p v-if="form.errors.payment_date" class="text-xs text-destructive">
                            {{ form.errors.payment_date }}
                        </p>
                    </div>

                    <div v-if="allocationGroups.length" class="space-y-2">
                        <Label>Group (optional)</Label>
                        <Select v-model="form.visa_group_id">
                            <SelectTrigger><SelectValue placeholder="Select group" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No specific group</SelectItem>
                                <SelectItem
                                    v-for="group in allocationGroups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.group_number }} · {{ group.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.visa_group_id" class="text-xs text-destructive">
                            {{ form.errors.visa_group_id }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                        <div class="space-y-2">
                            <Label>Amount</Label>
                            <Input
                                v-model="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
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
                    <p class="text-xs text-muted-foreground">
                        The accountant sets the exchange rate and books the payment when
                        they review it.
                    </p>

                    <div class="space-y-2">
                        <Label>Method</Label>
                        <Select v-model="form.method">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in methods"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.method" class="text-xs text-destructive">
                            {{ form.errors.method }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Reference (optional)</Label>
                        <Input v-model="form.reference" type="text" />
                        <p v-if="form.errors.reference" class="text-xs text-destructive">
                            {{ form.errors.reference }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Notes (optional)</Label>
                        <Textarea v-model="form.notes" />
                        <p v-if="form.errors.notes" class="text-xs text-destructive">
                            {{ form.errors.notes }}
                        </p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <WalletCards class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Submitting…' : 'Submit for Review' }}
                    </Button>
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
