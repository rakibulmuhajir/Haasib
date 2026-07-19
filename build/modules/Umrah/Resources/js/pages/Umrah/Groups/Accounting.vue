<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Calculator, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

type Vendor = {
    id: string;
    name: string;
    is_default?: boolean;
    is_company_owned?: boolean;
    provides_mandatory_transport?: boolean;
    mandatory_transport_vendor_id?: string | null;
};

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    group: any;
    passengerSummary: { total: number; adults: number; children: number; infants: number; visa: number; transport_only: number };
    services: Array<{ stage: 'group' | 'voucher'; service: string; quantity: number; charge: number }>;
    voucherBreakdown: Array<{
        id: string;
        voucher_number: string;
        status: string;
        passengers: number;
        company_stays: number;
        self_stays: number;
        hotel_sale_amount: number;
        hotel_cost_amount: number;
        accounting_state: string;
        billing_voucher_number?: string | null;
    }>;
    vendors: Vendor[];
    transportVendors: Vendor[];
    canUpdate: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
    { title: props.group.group_number, href: `/${props.company.slug}/umrah/groups/${props.group.id}` },
    { title: 'Accounting', href: `/${props.company.slug}/umrah/groups/${props.group.id}/accounting` },
];

const form = useForm({
    vendor_id: props.group.vendor_id || 'none',
    mandatory_transport_vendor_id: props.group.mandatory_transport_vendor_id || 'none',
    visa_sale_amount: String(props.group.visa_sale_amount ?? 0),
    transport_amount: String(props.group.transport_amount ?? 0),
    discount_amount: String(props.group.discount_amount ?? 0),
    reason: '',
});

watch(
    () => form.vendor_id,
    (vendorId, previous) => {
        if (!previous) return;
        const vendor = props.vendors.find((item) => item.id === vendorId);
        if (!vendor) return;
        form.mandatory_transport_vendor_id = vendor.provides_mandatory_transport
            ? vendor.id
            : vendor.mandatory_transport_vendor_id || 'none';
    },
);

const grossCharges = computed(() =>
    Number(form.visa_sale_amount || 0) + Number(form.transport_amount || 0) + Number(props.group.hotel_amount || 0),
);
const receivable = computed(() => Math.max(grossCharges.value - Number(form.discount_amount || 0), 0));
const totalCost = computed(() =>
    Number(props.group.visa_cost_amount || 0) + Number(props.group.transport_cost_amount || 0) + Number(props.group.hotel_cost_amount || 0),
);
const profit = computed(() => receivable.value - totalCost.value);
const balance = computed(() => Math.max(receivable.value - Number(props.group.total_paid || 0), 0));
const accountingStates: Record<string, string> = {
    pending: 'Pending approval', posted: 'Posted', shared: 'Shared billing', reversed: 'Reversed',
    superseded: 'Superseded', no_charge: 'No charge', unposted: 'Needs review',
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            mandatory_transport_vendor_id:
                props.group.transport_mode === 'standard_bus' && data.mandatory_transport_vendor_id !== 'none'
                    ? data.mandatory_transport_vendor_id
                    : null,
            visa_sale_amount: Number(data.visa_sale_amount || 0),
            transport_amount: Number(data.transport_amount || 0),
            discount_amount: Number(data.discount_amount || 0),
        }))
        .put(`/${props.company.slug}/umrah/groups/${props.group.id}/accounting`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reason = '';
                toast.success('Group accounting updated successfully');
            },
            onError: () => toast.error('Group accounting could not be updated'),
        });
};
</script>

<template>
    <Head :title="`${group.group_number} Accounting`" />
    <PageShell
        title="Group Accounting"
        :description="`${group.group_number} · ${group.agent?.name || 'No agent'}`"
        :breadcrumbs="breadcrumbs"
        :icon="Calculator"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Operational View
            </Button>
        </template>

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-3 xl:grid-cols-6">
            <div v-for="item in [
                ['Total pax', passengerSummary.total],
                ['Adults', passengerSummary.adults],
                ['Children', passengerSummary.children],
                ['Infants', passengerSummary.infants],
                ['Visa', passengerSummary.visa],
                ['Transport only', passengerSummary.transport_only],
            ]" :key="String(item[0])" class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ item[0] }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ item[1] }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-6">
                <Card>
                    <CardHeader><CardTitle>Used Services</CardTitle></CardHeader>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader><TableRow><TableHead>Stage</TableHead><TableHead>Service</TableHead><TableHead class="text-center">Quantity</TableHead><TableHead class="text-right">Charge</TableHead></TableRow></TableHeader>
                            <TableBody>
                                <TableEmpty v-if="!services.length" :colspan="4">No services recorded.</TableEmpty>
                                <TableRow v-for="service in services" :key="`${service.service}-${service.quantity}`">
                                    <TableCell><Badge variant="secondary">{{ service.stage === 'group' ? 'Group' : 'Voucher' }}</Badge></TableCell>
                                    <TableCell class="font-medium">{{ service.service }}</TableCell>
                                    <TableCell class="text-center">{{ service.quantity }}</TableCell>
                                    <TableCell class="text-right"><MoneyText :amount="service.charge" :currency="company.base_currency" /></TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Voucher Accounting</CardTitle></CardHeader>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader><TableRow><TableHead>Voucher</TableHead><TableHead class="text-center">Pax</TableHead><TableHead class="text-center">Company stays</TableHead><TableHead>Status</TableHead><TableHead class="text-right">Hotel charge</TableHead><TableHead class="w-12" /></TableRow></TableHeader>
                            <TableBody>
                                <TableEmpty v-if="!voucherBreakdown.length" :colspan="6">No vouchers created yet. Group visa and transport accounting remains posted.</TableEmpty>
                                <TableRow v-for="voucher in voucherBreakdown" :key="voucher.id">
                                    <TableCell class="font-medium">{{ voucher.voucher_number }}</TableCell>
                                    <TableCell class="text-center">{{ voucher.passengers }}</TableCell>
                                    <TableCell class="text-center">{{ voucher.company_stays }}</TableCell>
                                    <TableCell><Badge variant="secondary">{{ accountingStates[voucher.accounting_state] || voucher.accounting_state }}</Badge></TableCell>
                                    <TableCell class="text-right"><MoneyText :amount="voucher.hotel_sale_amount" :currency="company.base_currency" /></TableCell>
                                    <TableCell><Button size="icon" variant="ghost" title="Open voucher accounting" @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}/accounting`)"><Calculator class="h-4 w-4" /></Button></TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <form id="group-accounting-form" class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader><CardTitle>Supplier Assignment</CardTitle></CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Visa vendor</Label>
                                <Select v-model="form.vendor_id" :disabled="!canUpdate">
                                    <SelectTrigger><SelectValue placeholder="Select visa vendor" /></SelectTrigger>
                                    <SelectContent><SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}{{ vendor.is_default ? ' · Default' : '' }}</SelectItem></SelectContent>
                                </Select>
                                <p v-if="form.errors.vendor_id" class="text-xs text-destructive">{{ form.errors.vendor_id }}</p>
                            </div>
                            <div v-if="group.transport_mode === 'standard_bus'" class="space-y-2">
                                <Label>Mandatory transport vendor</Label>
                                <Select v-model="form.mandatory_transport_vendor_id" :disabled="!canUpdate">
                                    <SelectTrigger><SelectValue placeholder="Select transport vendor" /></SelectTrigger>
                                    <SelectContent><SelectItem v-for="vendor in transportVendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}{{ vendor.is_company_owned ? ' · Company-owned' : '' }}</SelectItem></SelectContent>
                                </Select>
                                <p v-if="form.errors.mandatory_transport_vendor_id" class="text-xs text-destructive">{{ form.errors.mandatory_transport_vendor_id }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Charges</CardTitle></CardHeader>
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2"><Label>Visa charge</Label><Input v-model="form.visa_sale_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" /><p v-if="form.errors.visa_sale_amount" class="text-xs text-destructive">{{ form.errors.visa_sale_amount }}</p></div>
                            <div class="space-y-2"><Label>Transport charge</Label><Input v-model="form.transport_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" /><p v-if="form.errors.transport_amount" class="text-xs text-destructive">{{ form.errors.transport_amount }}</p></div>
                            <div class="space-y-2"><Label>Hotel charge</Label><Input :model-value="String(group.hotel_amount || 0)" type="number" disabled /><p class="text-xs text-muted-foreground">Controlled by approved hotel vouchers.</p></div>
                            <div class="space-y-2"><Label>Discount</Label><Input v-model="form.discount_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" /><p v-if="form.errors.discount_amount" class="text-xs text-destructive">{{ form.errors.discount_amount }}</p></div>
                            <div v-if="canUpdate" class="space-y-2 sm:col-span-2"><Label>Adjustment reason</Label><Textarea v-model="form.reason" placeholder="Reason for changing charges, discount, or suppliers" /><p v-if="form.errors.reason" class="text-xs text-destructive">{{ form.errors.reason }}</p></div>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader><CardTitle>Financial Position</CardTitle></CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex justify-between"><span>Gross charges</span><MoneyText :amount="grossCharges" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Discount</span><MoneyText :amount="Number(form.discount_amount || 0)" :currency="company.base_currency" /></div>
                        <div class="flex justify-between border-t pt-3 font-medium"><span>Total receivable</span><MoneyText :amount="receivable" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Received</span><MoneyText :amount="group.total_paid" :currency="company.base_currency" /></div>
                        <div class="flex justify-between border-t pt-3 text-base font-semibold"><span>Balance</span><MoneyText :amount="balance" :currency="company.base_currency" /></div>
                        <Badge :variant="balance <= 0 ? 'default' : 'secondary'">{{ balance <= 0 ? 'Paid' : 'Unpaid' }}</Badge>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Cost & Margin</CardTitle></CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex justify-between"><span>Visa cost</span><MoneyText :amount="group.visa_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Mandatory transport</span><MoneyText :amount="group.mandatory_transport_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Total transport cost</span><MoneyText :amount="group.transport_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Hotel cost</span><MoneyText :amount="group.hotel_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between border-t pt-3"><span>Total cost</span><MoneyText :amount="totalCost" :currency="company.base_currency" /></div>
                        <div class="flex justify-between border-t pt-3 text-base font-semibold"><span>Profit</span><MoneyText :amount="profit" :currency="company.base_currency" /></div>
                    </CardContent>
                </Card>

                <Button v-if="canUpdate" type="submit" form="group-accounting-form" class="w-full" :disabled="form.processing || form.reason.trim().length < 5">
                    <Save class="mr-2 h-4 w-4" />Save Accounting
                </Button>
            </div>
        </div>
    </PageShell>
</template>
