<script setup lang="ts">
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import {
    Eye,
    FileText,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Save,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    nextVendorNumber: string;
    canManageVendors: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    {
        title: 'Visa Vendors',
        href: `/${props.company.slug}/umrah/vendors`,
    },
];

/**
 * Four rate columns and a payable, all figures, so they line up on the decimal
 * and can be read down rather than across. The vendor type and the default
 * marker are annotations on the name, not states of the record — only whether
 * the vendor is still in use is a status.
 */
const columns = [
    { key: 'vendor_number', label: 'Vendor #', kind: 'ref' as const },
    { key: 'name', label: 'Vendor', kind: 'text' as const },
    { key: 'vendor_type', label: 'Type', kind: 'text' as const },
    { key: 'adult_retail_amount', label: 'Adult retail', kind: 'amount' as const },
    { key: 'adult_cost_amount', label: 'Adult cost', kind: 'amount' as const },
    { key: 'child_retail_amount', label: 'Child retail', kind: 'amount' as const },
    { key: 'child_cost_amount', label: 'Child cost', kind: 'amount' as const },
    { key: 'balance', label: 'Payable', kind: 'amount' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const form = useForm({
    vendor_number: props.nextVendorNumber,
    name: '',
    vendor_type: 'government',
    is_default: false,
    phone: '',
    email: '',
    city: '',
    logo_url: '',
    adult_retail_amount: '0',
    adult_cost_amount: '0',
    child_retail_amount: '0',
    child_cost_amount: '0',
    notes: '',
});
const hasRequiredVisaRates = computed(
    () =>
        [
            form.adult_retail_amount,
            form.adult_cost_amount,
            form.child_retail_amount,
            form.child_cost_amount,
        ].every((amount) => Number(amount) > 0),
);

const editingVendor = ref<any | null>(null);
const vendorDialogOpen = ref(false);
const statusForm = useForm({ is_active: false });
const updateStatus = (vendor: any) => {
    statusForm.is_active = !vendor.is_active;
    statusForm.patch(
        `/${props.company.slug}/umrah/vendors/${vendor.id}/status`,
        {
            preserveScroll: true,
            onError: () =>
                toast.error(
                    statusForm.errors.vendor ||
                        'Vendor status could not be changed',
                ),
        },
    );
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
    form.is_default = false;
    form.phone = '';
    form.email = '';
    form.city = '';
    form.logo_url = '';
    form.adult_retail_amount = '0';
    form.adult_cost_amount = '0';
    form.child_retail_amount = '0';
    form.child_cost_amount = '0';
    form.notes = '';
};

const startCreate = () => {
    resetForm();
    vendorDialogOpen.value = true;
};

const startEdit = (vendor: any) => {
    editingVendor.value = vendor;
    form.clearErrors();
    form.vendor_number = vendor.vendor_number || '';
    form.name = vendor.name || '';
    form.vendor_type = vendor.vendor_type || 'government';
    form.is_default = Boolean(vendor.is_default);
    form.phone = vendor.phone || '';
    form.email = vendor.email || '';
    form.city = vendor.city || '';
    form.logo_url = vendor.logo_url || '';
    form.adult_retail_amount = String(vendor.adult_retail_amount ?? 0);
    form.adult_cost_amount = String(vendor.adult_cost_amount ?? 0);
    form.child_retail_amount = String(
        vendor.child_retail_amount ?? vendor.adult_retail_amount ?? 0,
    );
    form.child_cost_amount = String(
        vendor.child_cost_amount ?? vendor.adult_cost_amount ?? 0,
    );
    form.notes = vendor.notes || '';
    vendorDialogOpen.value = true;
};

const closeDialog = () => {
    vendorDialogOpen.value = false;
    resetForm();
};

const payload = (data: any) => ({
    ...data,
    is_default: data.is_default,
    adult_retail_amount: Number(data.adult_retail_amount || 0),
    adult_cost_amount: Number(data.adult_cost_amount || 0),
    child_retail_amount: Number(
        data.child_retail_amount || data.adult_retail_amount || 0,
    ),
    child_cost_amount: Number(
        data.child_cost_amount || data.adult_cost_amount || 0,
    ),
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: closeDialog,
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
    <Head title="Visa Vendors" />
    <PageShell
        title="Visa Vendors"
        description="Visa vendors with independent adult and child rates and payable balances."
        :breadcrumbs="breadcrumbs"
        :icon="FileText"
    >
        <div class="space-y-4">
            <div v-if="canManageVendors" class="flex justify-end">
                <Button type="button" @click="startCreate"><Plus class="mr-2 h-4 w-4" />Add Visa Vendor</Button>
            </div>
            <Dialog v-model:open="vendorDialogOpen" @update:open="(open) => { if (!open && !form.processing) resetForm() }">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ editingVendor ? 'Edit Visa Vendor' : 'Add Visa Vendor' }}</DialogTitle>
                        <DialogDescription>Maintain visa selling prices and supplier costs separately from transport fares.</DialogDescription>
                    </DialogHeader>
            <Card v-if="canManageVendors" class="min-w-0 border-0 shadow-none">
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
                        <div class="flex items-center gap-3 rounded-md border p-3">
                            <Checkbox id="visa-vendor-default" v-model="form.is_default" />
                            <Label for="visa-vendor-default">Default visa vendor for new groups</Label>
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
                        <div class="space-y-2">
                            <Label>Logo URL</Label>
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="form.logo_url"
                                    :src="form.logo_url"
                                    alt="Vendor logo preview"
                                    class="h-12 w-12 rounded-md border object-contain"
                                />
                                <Input
                                    v-model="form.logo_url"
                                    type="url"
                                    placeholder="https://example.com/logo.png"
                                />
                            </div>
                            <p
                                v-if="form.errors.logo_url"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.logo_url }}
                            </p>
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
                                        min="0.01"
                                        step="0.01"
                                    />
                                    <p
                                        v-if="form.errors.adult_retail_amount"
                                        class="text-xs text-destructive"
                                    >
                                        {{ form.errors.adult_retail_amount }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Cost</Label
                                    ><Input
                                        v-model="form.adult_cost_amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                    />
                                    <p
                                        v-if="form.errors.adult_cost_amount"
                                        class="text-xs text-destructive"
                                    >
                                        {{ form.errors.adult_cost_amount }}
                                    </p>
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
                                        min="0.01"
                                        step="0.01"
                                    />
                                    <p
                                        v-if="form.errors.child_retail_amount"
                                        class="text-xs text-destructive"
                                    >
                                        {{ form.errors.child_retail_amount }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Cost</Label
                                    ><Input
                                        v-model="form.child_cost_amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                    />
                                    <p
                                        v-if="form.errors.child_cost_amount"
                                        class="text-xs text-destructive"
                                    >
                                        {{ form.errors.child_cost_amount }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Notes</Label
                            ><Textarea v-model="form.notes" />
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="form.processing"
                                @click="closeDialog"
                                >Cancel</Button
                            >
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing || !hasRequiredVisaRates
                                "
                                ><span v-if="form.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" /><Save v-else class="mr-2 h-4 w-4" />{{
                                    editingVendor
                                        ? 'Save Changes'
                                        : 'Save Vendor'
                                }}</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
                </DialogContent>
            </Dialog>

            <Card class="min-w-0">
                <CardHeader><CardTitle>Vendor List</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <LedgerRegister :data="vendors.data" :columns="columns">
                        <template #empty>No visa vendors yet.</template>

                        <template #cell-name="{ row }">
                            <div>{{ row.name }}</div>
                            <MetaChip v-if="row.is_default" tone="neutral" class="mt-1">Default</MetaChip>
                        </template>

                        <template #cell-vendor_type="{ row }">
                            <MetaChip tone="neutral" bare>{{
                                vendorTypes[row.vendor_type] || row.vendor_type
                            }}</MetaChip>
                        </template>

                        <template #cell-adult_retail_amount="{ row }">
                            <MoneyText :amount="row.adult_retail_amount" :currency="company.base_currency" />
                        </template>
                        <template #cell-adult_cost_amount="{ row }">
                            <MoneyText :amount="row.adult_cost_amount" :currency="company.base_currency" />
                        </template>
                        <template #cell-child_retail_amount="{ row }">
                            <MoneyText :amount="row.child_retail_amount" :currency="company.base_currency" />
                        </template>
                        <template #cell-child_cost_amount="{ row }">
                            <MoneyText :amount="row.child_cost_amount" :currency="company.base_currency" />
                        </template>
                        <template #cell-balance="{ row }">
                            <MoneyText :amount="row.balance" :currency="company.base_currency" class="font-semibold" />
                        </template>

                        <template #cell-status="{ row }">
                            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    @click="router.get(`/${company.slug}/umrah/vendors/${row.id}`)"
                                >
                                    <Eye class="h-4 w-4" />
                                    <span class="sr-only">View {{ row.name }} statement</span>
                                </Button>
                                <Button
                                    v-if="canManageVendors"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    @click="startEdit(row)"
                                >
                                    <Pencil class="h-4 w-4" />
                                    <span class="sr-only">Edit {{ row.name }}</span>
                                </Button>
                                <Button
                                    v-if="canManageVendors"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :title="row.is_active ? 'Deactivate vendor' : 'Reactivate vendor'"
                                    :disabled="statusForm.processing"
                                    @click="updateStatus(row)"
                                >
                                    <Power v-if="row.is_active" class="h-4 w-4" />
                                    <RotateCcw v-else class="h-4 w-4" />
                                </Button>
                            </div>
                        </template>
                    </LedgerRegister>
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
