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
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Undo2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    partyTypes: Record<string, string>;
    services: Record<string, string>;
    agents: Array<{ id: string; name: string }>;
    visaVendors: Array<{ id: string; name: string }>;
    transportVendors: Array<{ id: string; name: string }>;
    hotelVendors: Array<{ id: string; name: string }>;
    allocationGroups: Array<{
        id: string;
        party_key: string;
        group_number: string;
        name: string;
        outstanding_amount: number;
    }>;
    currencies: Array<{ currency_code: string; exchange_rate: string | number }>;
    initial?: {
        party_type?: string;
        party_id?: string;
        visa_group_id?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Refunds', href: `/${props.company.slug}/umrah/refunds` },
    { title: 'Request Refund' },
];

const firstPartyType = Object.keys(props.partyTypes)[0] || 'agent';

const form = useForm({
    party_type: props.initial?.party_type || firstPartyType,
    party_id: props.initial?.party_id || 'none',
    visa_group_id: props.initial?.visa_group_id || 'none',
    service: Object.keys(props.services)[0] || 'other',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: '',
    reason: '',
});

const partyOptions = computed(() => {
    switch (form.party_type) {
        case 'visa_vendor':
            return props.visaVendors;
        case 'transport_vendor':
            return props.transportVendors;
        case 'hotel_vendor':
            return props.hotelVendors;
        default:
            return props.agents;
    }
});

const isAgentRefund = computed(() => form.party_type === 'agent');

const partyGroups = computed(() =>
    isAgentRefund.value && form.party_id !== 'none'
        ? props.allocationGroups.filter(
              (group) => group.party_key === `agent:${form.party_id}`,
          )
        : [],
);

const selectedCurrency = computed(() =>
    props.currencies.find(
        (currency) => currency.currency_code === form.currency,
    ),
);
const baseAmount = computed(
    () =>
        Math.round(
            Math.max(Number(form.amount || 0), 0) *
                Number(form.exchange_rate || 1) *
                100,
        ) / 100,
);

watch(
    () => form.party_type,
    () => {
        form.party_id = 'none';
        form.visa_group_id = 'none';
    },
);
watch(
    () => form.party_id,
    () => {
        form.visa_group_id = 'none';
    },
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

const submit = () =>
    form
        .transform((data) => ({
            party_type: data.party_type,
            party_id: data.party_id === 'none' ? null : data.party_id,
            visa_group_id:
                data.visa_group_id === 'none' ? null : data.visa_group_id,
            service: data.service,
            amount: Number(data.amount || 0),
            currency: data.currency,
            exchange_rate:
                data.currency === props.company.base_currency
                    ? null
                    : Number(data.exchange_rate || 0),
            reason: data.reason,
        }))
        .post(`/${props.company.slug}/umrah/refunds`, {
            onError: () => toast.error('Failed to request refund'),
        });
</script>

<template>
    <Head title="Request Refund" />
    <PageShell
        title="Request Refund"
        description="Record what is owed back. Nothing moves and nothing posts until this is accepted and settled."
        :breadcrumbs="breadcrumbs"
        :icon="Undo2"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/refunds`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
        </template>

        <form class="mx-auto max-w-xl" @submit.prevent="submit">
            <Card variant="form">
                <CardHeader><CardTitle>Refund</CardTitle></CardHeader>
                <CardContent class="space-y-5">
                    <div v-if="Object.keys(partyTypes).length > 1" class="space-y-2">
                        <Label>Owed to</Label>
                        <Select v-model="form.party_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in partyTypes"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.party_type" class="text-xs text-destructive">
                            {{ form.errors.party_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Party</Label>
                        <Select v-model="form.party_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select party" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Select party</SelectItem>
                                <SelectItem
                                    v-for="party in partyOptions"
                                    :key="party.id"
                                    :value="party.id"
                                >
                                    {{ party.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.party_id" class="text-xs text-destructive">
                            {{ form.errors.party_id }}
                        </p>
                    </div>

                    <div v-if="isAgentRefund && partyGroups.length" class="space-y-2">
                        <Label>Group (optional)</Label>
                        <Select v-model="form.visa_group_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Not tied to a group" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Not tied to a group</SelectItem>
                                <SelectItem
                                    v-for="group in partyGroups"
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

                    <div class="space-y-2">
                        <Label>Service</Label>
                        <Select v-model="form.service">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in services"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.service" class="text-xs text-destructive">
                            {{ form.errors.service }}
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

                    <div class="space-y-2">
                        <Label>Reason</Label>
                        <Textarea v-model="form.reason" required />
                        <p v-if="form.errors.reason" class="text-xs text-destructive">
                            {{ form.errors.reason }}
                        </p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <Undo2 class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Requesting…' : 'Request Refund' }}
                    </Button>
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
