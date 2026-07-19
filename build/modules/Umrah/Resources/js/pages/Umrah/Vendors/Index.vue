<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eye, FileText, Pencil, Power, RotateCcw, Save, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    vendors: {
        data: any[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    vendorTypes: Record<string, string>;
    transportVendors: Array<{ id: string; name: string }>;
    nextVendorNumber: string;
    canManageVendors: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa & Transport Vendors', href: `/${props.company.slug}/umrah/vendors` },
];

const form = useForm({
    vendor_number: props.nextVendorNumber,
    name: '',
    vendor_type: 'government',
    is_company_owned: false,
    is_default: false,
    provides_mandatory_transport: false,
    mandatory_transport_vendor_id: 'none',
    phone: '',
    email: '',
    city: '',
    adult_retail_amount: '0',
    adult_cost_amount: '0',
    child_retail_amount: '0',
    child_cost_amount: '0',
    included_bus_cost_amount: '50',
    notes: '',
});

const editingVendor = ref<any | null>(null);
const statusForm = useForm({ is_active: false });
const updateStatus = (vendor: any) => {
    statusForm.is_active = !vendor.is_active;
    statusForm.patch(`/${props.company.slug}/umrah/vendors/${vendor.id}/status`, {
        preserveScroll: true,
        onSuccess: () => toast.success(vendor.is_active ? 'Vendor deactivated successfully' : 'Vendor reactivated successfully'),
        onError: () => toast.error(statusForm.errors.vendor || 'Vendor status could not be changed'),
    });
};

const sameAmount = (
    first: string | number | null | undefined,
    second: string | number | null | undefined,
) => Number(first || 0) === Number(second || 0);

watch(
    () => form.adult_retail_amount,
    (value, oldValue) => {
        if (sameAmount(form.child_retail_amount, oldValue))
            form.child_retail_amount = value;
    },
);

watch(
    () => form.adult_cost_amount,
    (value, oldValue) => {
        if (sameAmount(form.child_cost_amount, oldValue))
            form.child_cost_amount = value;
    },
);

const resetForm = () => {
    editingVendor.value = null;
    form.clearErrors();
    form.vendor_number = props.nextVendorNumber;
    form.name = '';
    form.vendor_type = 'government';
    form.is_company_owned = false;
    form.is_default = false;
    form.provides_mandatory_transport = false;
    form.mandatory_transport_vendor_id = 'none';
    form.phone = '';
    form.email = '';
    form.city = '';
    form.adult_retail_amount = '0';
    form.adult_cost_amount = '0';
    form.child_retail_amount = '0';
    form.child_cost_amount = '0';
    form.included_bus_cost_amount = '50';
    form.notes = '';
};

const startEdit = (vendor: any) => {
    editingVendor.value = vendor;
    form.clearErrors();
    form.vendor_number = vendor.vendor_number || '';
    form.name = vendor.name || '';
    form.vendor_type = vendor.vendor_type || 'government';
    form.is_company_owned = Boolean(vendor.is_company_owned);
    form.is_default = Boolean(vendor.is_default);
    form.provides_mandatory_transport = Boolean(vendor.provides_mandatory_transport);
    form.mandatory_transport_vendor_id = vendor.mandatory_transport_vendor_id || 'none';
    form.phone = vendor.phone || '';
    form.email = vendor.email || '';
    form.city = vendor.city || '';
    form.adult_retail_amount = String(vendor.adult_retail_amount ?? 0);
    form.adult_cost_amount = String(vendor.adult_cost_amount ?? 0);
    form.child_retail_amount = String(
        vendor.child_retail_amount ?? vendor.adult_retail_amount ?? 0,
    );
    form.child_cost_amount = String(
        vendor.child_cost_amount ?? vendor.adult_cost_amount ?? 0,
    );
    form.included_bus_cost_amount = String(
        vendor.included_bus_cost_amount ?? 50,
    );
    form.notes = vendor.notes || '';
};

const payload = (data: any) => ({
    ...data,
    is_company_owned:
        data.vendor_type === 'transport_provider' && data.is_company_owned,
    is_default: data.vendor_type !== 'transport_provider' && data.is_default,
    provides_mandatory_transport:
        data.vendor_type !== 'transport_provider' && data.provides_mandatory_transport,
    mandatory_transport_vendor_id:
        data.vendor_type !== 'transport_provider' &&
        !data.provides_mandatory_transport &&
        data.mandatory_transport_vendor_id !== 'none'
            ? data.mandatory_transport_vendor_id
            : null,
    adult_retail_amount: Number(data.adult_retail_amount || 0),
    adult_cost_amount: Number(data.adult_cost_amount || 0),
    child_retail_amount: Number(
        data.child_retail_amount || data.adult_retail_amount || 0,
    ),
    child_cost_amount: Number(
        data.child_cost_amount || data.adult_cost_amount || 0,
    ),
    included_bus_cost_amount: Number(data.included_bus_cost_amount || 0),
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                editingVendor.value
                    ? 'Visa vendor updated successfully'
                    : 'Visa vendor created successfully',
            );
            resetForm();
        },
        onError: () =>
            toast.error(
                editingVendor.value
                    ? 'Failed to update visa vendor'
                    : 'Failed to create visa vendor',
            ),
    };

    form.transform(payload);

    if (editingVendor.value) {
        form.put(
            `/${props.company.slug}/umrah/vendors/${editingVendor.value.id}`,
            options,
        );
        return;
    }

    form.post(`/${props.company.slug}/umrah/vendors`, options);
};
</script>

<template>
    <Head title="Visa & Transport Vendors" />
    <PageShell
        title="Visa & Transport Vendors"
        description="Visa suppliers and transport providers with rates and payable balances."
        :breadcrumbs="breadcrumbs"
        :icon="FileText"
    >
        <div
            class="grid gap-6"
            :class="
                canManageVendors ? 'lg:grid-cols-[520px_minmax(0,1fr)]' : ''
            "
        >
            <Card v-if="canManageVendors" class="min-w-0">
                <CardHeader
                    ><CardTitle>{{
                        editingVendor ? 'Edit Vendor' : 'Add Vendor'
                    }}</CardTitle></CardHeader
                >
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Vendor #</Label>
                            <Input v-model="form.vendor_number" />
                        </div>
                        <div class="space-y-2">
                            <Label>Name</Label>
                            <Input v-model="form.name" required />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Type</Label>
                            <Select v-model="form.vendor_type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in vendorTypes"
                                        :key="value"
                                        :value="value"
                                        >{{ label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <Label
                            v-if="form.vendor_type === 'transport_provider'"
                            class="flex items-center gap-3 rounded-md border p-3"
                        >
                            <Checkbox v-model="form.is_company_owned" />
                            <span>Company-owned transport provider</span>
                        </Label>
                        <div
                            v-if="form.vendor_type !== 'transport_provider'"
                            class="space-y-3 rounded-md border p-3"
                        >
                            <Label class="flex items-center gap-3">
                                <Checkbox v-model="form.is_default" />
                                <span>Default visa vendor for new groups</span>
                            </Label>
                            <Label class="flex items-center gap-3">
                                <Checkbox v-model="form.provides_mandatory_transport" />
                                <span>Also provides mandatory bus transport</span>
                            </Label>
                            <div v-if="!form.provides_mandatory_transport" class="space-y-2">
                                <Label>Mandatory transport provider</Label>
                                <Select v-model="form.mandatory_transport_vendor_id">
                                    <SelectTrigger><SelectValue placeholder="Select provider" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Select provider</SelectItem>
                                        <SelectItem v-for="vendor in transportVendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.mandatory_transport_vendor_id" class="text-xs text-destructive">{{ form.errors.mandatory_transport_vendor_id }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Phone</Label
                                ><Input v-model="form.phone" />
                            </div>
                            <div class="space-y-2">
                                <Label>City</Label><Input v-model="form.city" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Email</Label
                            ><Input v-model="form.email" type="email" />
                        </div>
                        <div
                            v-if="form.vendor_type !== 'transport_provider'"
                            class="space-y-3 rounded-md border p-3"
                        >
                            <div class="font-medium">Adult Visa Rate</div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Retail</Label
                                    ><Input
                                        v-model="form.adult_retail_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Cost</Label
                                    ><Input
                                        v-model="form.adult_cost_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="form.vendor_type !== 'transport_provider'"
                            class="space-y-3 rounded-md border p-3"
                        >
                            <div class="font-medium">Child Visa Rate</div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Retail</Label
                                    ><Input
                                        v-model="form.child_retail_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Cost</Label
                                    ><Input
                                        v-model="form.child_cost_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="form.vendor_type !== 'transport_provider'"
                            class="space-y-2 rounded-md border p-3"
                        >
                            <Label
                                >Included Standard Bus Cost per Passenger</Label
                            >
                            <Input
                                v-model="form.included_bus_cost_amount"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <p class="text-xs text-muted-foreground">
                                Usually SAR 50 and already included in the visa
                                cost. It is always deducted from the visa vendor
                                and assigned to the selected transport provider.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Notes</Label
                            ><Textarea v-model="form.notes" />
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Button
                                v-if="editingVendor"
                                type="button"
                                variant="outline"
                                @click="resetForm"
                                ><X class="mr-2 h-4 w-4" />Cancel</Button
                            >
                            <Button
                                type="submit"
                                :class="editingVendor ? '' : 'sm:col-span-2'"
                                :disabled="form.processing"
                                ><Save class="mr-2 h-4 w-4" />{{
                                    editingVendor
                                        ? 'Save Changes'
                                        : 'Save Vendor'
                                }}</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader><CardTitle>Vendor List</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Vendor #</TableHead
                                ><TableHead>Vendor</TableHead
                                ><TableHead>Type</TableHead>
                                <TableHead class="text-right"
                                    >Adult Retail</TableHead
                                ><TableHead class="text-right"
                                    >Adult Cost</TableHead
                                >
                                <TableHead class="text-right"
                                    >Child Retail</TableHead
                                ><TableHead class="text-right"
                                    >Child Cost</TableHead
                                >
                                <TableHead class="text-right"
                                    >Bus Included</TableHead
                                ><TableHead class="text-right"
                                    >Payable</TableHead
                                >
                                <TableHead>Status</TableHead>
                                <TableHead
                                    class="w-24 text-right"
                                    >Action</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty
                                v-if="!vendors.data.length"
                                :colspan="11"
                                >No visa vendors yet.</TableEmpty
                            >
                            <TableRow
                                v-for="vendor in vendors.data"
                                :key="vendor.id"
                                :class="{ 'opacity-60': !vendor.is_active }"
                            >
                                <TableCell class="font-medium">{{
                                    vendor.vendor_number
                                }}</TableCell>
                                <TableCell>
                                    <div>{{ vendor.name }}</div>
                                    <div class="mt-1 flex gap-1">
                                        <Badge v-if="vendor.is_default" variant="secondary">Default</Badge>
                                        <Badge v-if="vendor.provides_mandatory_transport" variant="outline">Visa + transport</Badge>
                                    </div>
                                </TableCell>
                                <TableCell
                                    ><Badge variant="outline">{{
                                        vendorTypes[vendor.vendor_type] ||
                                        vendor.vendor_type
                                    }}</Badge></TableCell
                                >
                                <TableCell class="text-right"
                                    ><MoneyText
                                        :amount="vendor.adult_retail_amount"
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell class="text-right"
                                    ><MoneyText
                                        :amount="vendor.adult_cost_amount"
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell class="text-right"
                                    ><MoneyText
                                        :amount="vendor.child_retail_amount"
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell class="text-right"
                                    ><MoneyText
                                        :amount="vendor.child_cost_amount"
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell class="text-right"
                                    ><MoneyText
                                        :amount="
                                            vendor.included_bus_cost_amount
                                        "
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell class="text-right font-semibold"
                                    ><MoneyText
                                        :amount="vendor.balance"
                                        :currency="company.base_currency"
                                /></TableCell>
                                <TableCell><Badge :variant="vendor.is_active ? 'default' : 'secondary'">{{ vendor.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                                <TableCell
                                    class="text-right"
                                    ><Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="router.get(`/${company.slug}/umrah/vendors/${vendor.id}`)"
                                        ><Eye class="h-4 w-4" /><span
                                            class="sr-only"
                                            >View {{ vendor.name }} statement</span
                                        ></Button
                                    ><Button
                                        v-if="canManageVendors"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="startEdit(vendor)"
                                        ><Pencil class="h-4 w-4" /><span
                                            class="sr-only"
                                            >Edit {{ vendor.name }}</span
                                        ></Button
                                    ><Button
                                        v-if="canManageVendors"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :title="vendor.is_active ? 'Deactivate vendor' : 'Reactivate vendor'"
                                        :disabled="statusForm.processing"
                                        @click="updateStatus(vendor)"
                                        ><Power v-if="vendor.is_active" class="h-4 w-4" /><RotateCcw v-else class="h-4 w-4" /></Button
                                    ></TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                    <RecordPagination
                        :current-page="vendors.current_page"
                        :last-page="vendors.last_page"
                        :from="vendors.from"
                        :to="vendors.to"
                        :total="vendors.total"
                        :previous-url="vendors.prev_page_url"
                        :next-url="vendors.next_page_url"
                    />
                </CardContent>
            </Card>
        </div>
    </PageShell>
</template>
