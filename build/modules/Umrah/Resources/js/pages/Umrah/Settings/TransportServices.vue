<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MoneyText from '@/components/MoneyText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Bus,
    Map,
    Package,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Save,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    transportServices: any[];
    drivers: any[];
    sectors: any[];
    packages: any[];
    fares: any[];
    transportVendors: any[];
    chargingBases: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    {
        title: 'Transport Services',
        href: `/${props.company.slug}/umrah/settings/transport-services`,
    },
];

const serviceColumns = [
    { key: 'name', label: 'Service', kind: 'text' as const },
    { key: 'vehicle_type', label: 'Vehicle Type', kind: 'text' as const },
    { key: 'make', label: 'Make', kind: 'text' as const },
    { key: 'model', label: 'Model', kind: 'text' as const },
    { key: 'pax_capacity', label: 'Capacity', kind: 'amount' as const },
    { key: 'number_plate', label: 'Plate', kind: 'ref' as const },
    { key: 'driver', label: 'Driver', kind: 'text' as const },
    { key: 'contact', label: 'Contact', kind: 'text' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const sectorColumns = [
    { key: 'code', label: 'Code', kind: 'ref' as const },
    { key: 'name', label: 'Sector', kind: 'text' as const },
    { key: 'origin', label: 'Origin', kind: 'text' as const },
    { key: 'destination', label: 'Destination', kind: 'text' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const packageColumns = [
    { key: 'name', label: 'Journey', kind: 'text' as const },
    { key: 'sectors', label: 'Included Sectors', kind: 'text' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const fareColumns = [
    { key: 'name', label: 'Fare', kind: 'text' as const },
    { key: 'provider', label: 'Provider', kind: 'text' as const },
    { key: 'vehicle', label: 'Vehicle', kind: 'text' as const },
    { key: 'coverage', label: 'Coverage', kind: 'text' as const },
    { key: 'basis', label: 'Basis', kind: 'text' as const },
    { key: 'sale_amount', label: 'Retail', kind: 'amount' as const },
    { key: 'cost_amount', label: 'Cost', kind: 'amount' as const },
    { key: 'hajj_terminal_sale_amount', label: 'Hajj Extra', kind: 'amount' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const form = useForm({
    name: '',
    driver_id: 'none',
    vehicle_type: '',
    pax_capacity: '',
    make: '',
    model: '',
    color: '',
    number_plate: '',
    driver_name: '',
    driver_contact: '',
    default_sale_amount: '0',
    default_cost_amount: '0',
    notes: '',
});

const statusForm = useForm({ is_active: false });
const catalogStatusForm = useForm({ is_active: false });
const sectorForm = useForm({ code: '', name: '', origin: '', destination: '' });
const packageForm = useForm({
    name: '',
    notes: '',
    sector_ids: [] as string[],
});
const fareForm = useForm({
    name: '',
    transport_vendor_id: '',
    transport_service_id: '',
    fare_target: '',
    charging_basis: 'per_vehicle',
    sale_amount: '0',
    cost_amount: '0',
    hajj_terminal_sale_amount: '90',
    hajj_terminal_cost_amount: '0',
});
const editingService = ref<any | null>(null);
const editingSector = ref<any | null>(null);
const editingPackage = ref<any | null>(null);
const editingFare = ref<any | null>(null);
const serviceToRemove = ref<any | null>(null);
const removeDialogOpen = ref(false);
const activeServices = computed(() =>
    props.transportServices.filter((record) => record.is_active),
);
const activeSectors = computed(() =>
    props.sectors.filter((record) => record.is_active),
);
const activePackages = computed(() =>
    props.packages.filter((record) => record.is_active),
);

const resetForm = () => {
    editingService.value = null;
    form.clearErrors();
    form.name = '';
    form.driver_id = 'none';
    form.vehicle_type = '';
    form.pax_capacity = '';
    form.make = '';
    form.model = '';
    form.color = '';
    form.number_plate = '';
    form.driver_name = '';
    form.driver_contact = '';
    form.default_sale_amount = '0';
    form.default_cost_amount = '0';
    form.notes = '';
};

const startEdit = (service: any) => {
    editingService.value = service;
    form.clearErrors();
    form.name = service.name || '';
    form.driver_id = service.driver_id || 'none';
    form.vehicle_type = service.vehicle_type || '';
    form.pax_capacity = service.pax_capacity
        ? String(service.pax_capacity)
        : '';
    form.make = service.make || '';
    form.model = service.model || '';
    form.color = service.color || '';
    form.number_plate = service.number_plate || '';
    form.driver_name = service.driver_name || '';
    form.driver_contact = service.driver_contact || '';
    form.default_sale_amount = String(service.default_sale_amount ?? 0);
    form.default_cost_amount = String(service.default_cost_amount ?? 0);
    form.notes = service.notes || '';
};

const payload = (data: any) => ({
    ...data,
    driver_id: data.driver_id === 'none' ? null : data.driver_id,
    pax_capacity: data.pax_capacity ? Number(data.pax_capacity) : null,
    default_sale_amount: Number(data.default_sale_amount || 0),
    default_cost_amount: Number(data.default_cost_amount || 0),
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
        onError: () =>
            toast.error(
                editingService.value
                    ? 'Failed to update transport service'
                    : 'Failed to add transport service',
            ),
    };

    form.transform(payload);

    if (editingService.value) {
        form.put(
            `/${props.company.slug}/umrah/settings/transport-services/${editingService.value.id}`,
            options,
        );
        return;
    }

    form.post(
        `/${props.company.slug}/umrah/settings/transport-services`,
        options,
    );
};

const removeService = (service: any) => {
    serviceToRemove.value = service;
    removeDialogOpen.value = true;
};

const confirmRemoveService = () => {
    if (!serviceToRemove.value) return;

    statusForm.is_active = false;
    statusForm.patch(
        `/${props.company.slug}/umrah/settings/transport-services/${serviceToRemove.value.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (editingService.value?.id === serviceToRemove.value?.id)
                    resetForm();
                removeDialogOpen.value = false;
                serviceToRemove.value = null;
            },
            onError: () =>
                toast.error(
                    statusForm.errors.transport ||
                        'Failed to deactivate transport service',
                ),
        },
    );
};

const reactivateService = (service: any) => {
    statusForm.is_active = true;
    statusForm.patch(
        `/${props.company.slug}/umrah/settings/transport-services/${service.id}/status`,
        {
            preserveScroll: true,
            onError: () =>
                toast.error(
                    statusForm.errors.transport ||
                        'Failed to reactivate transport service',
                ),
        },
    );
};

const resetSector = () => {
    editingSector.value = null;
    sectorForm.reset();
    sectorForm.clearErrors();
};
const startEditSector = (sector: any) => {
    editingSector.value = sector;
    sectorForm.code = sector.code;
    sectorForm.name = sector.name;
    sectorForm.origin = sector.origin;
    sectorForm.destination = sector.destination;
};
const submitSector = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetSector(),
        onError: () =>
            toast.error(
                editingSector.value
                    ? 'Failed to update transport sector'
                    : 'Failed to add transport sector',
            ),
    };
    if (editingSector.value)
        sectorForm.put(
            `/${props.company.slug}/umrah/settings/transport-sectors/${editingSector.value.id}`,
            options,
        );
    else
        sectorForm.post(
            `/${props.company.slug}/umrah/settings/transport-sectors`,
            options,
        );
};

const togglePackageSector = (
    sectorId: string,
    checked: boolean | 'indeterminate',
) => {
    packageForm.sector_ids =
        checked === true
            ? [...packageForm.sector_ids, sectorId]
            : packageForm.sector_ids.filter((id) => id !== sectorId);
};

const resetPackage = () => {
    editingPackage.value = null;
    packageForm.reset();
    packageForm.sector_ids = [];
    packageForm.clearErrors();
};
const startEditPackage = (journey: any) => {
    editingPackage.value = journey;
    packageForm.name = journey.name;
    packageForm.notes = journey.notes || '';
    packageForm.sector_ids = journey.sectors.map((sector: any) => sector.id);
};
const submitPackage = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetPackage(),
        onError: () =>
            toast.error(
                editingPackage.value
                    ? 'Failed to update journey package'
                    : 'Failed to add journey package',
            ),
    };
    if (editingPackage.value)
        packageForm.put(
            `/${props.company.slug}/umrah/settings/transport-packages/${editingPackage.value.id}`,
            options,
        );
    else
        packageForm.post(
            `/${props.company.slug}/umrah/settings/transport-packages`,
            options,
        );
};

const resetFare = () => {
    editingFare.value = null;
    fareForm.reset();
    fareForm.transport_vendor_id = '';
    fareForm.transport_service_id = '';
    fareForm.fare_target = '';
    fareForm.charging_basis = 'per_vehicle';
    fareForm.hajj_terminal_sale_amount = '90';
    fareForm.hajj_terminal_cost_amount = '0';
    fareForm.clearErrors();
};
const startEditFare = (fare: any) => {
    editingFare.value = fare;
    fareForm.name = fare.name;
    fareForm.transport_vendor_id = fare.transport_vendor_id;
    fareForm.transport_service_id = fare.transport_service_id;
    fareForm.fare_target = fare.transport_sector_id
        ? `sector:${fare.transport_sector_id}`
        : `package:${fare.transport_package_id}`;
    fareForm.charging_basis = fare.charging_basis;
    fareForm.sale_amount = String(fare.sale_amount ?? 0);
    fareForm.cost_amount = String(fare.cost_amount ?? 0);
    fareForm.hajj_terminal_sale_amount = String(
        fare.hajj_terminal_sale_amount ?? 0,
    );
    fareForm.hajj_terminal_cost_amount = String(
        fare.hajj_terminal_cost_amount ?? 0,
    );
};

const submitFare = () => {
    const [targetType, targetId] = fareForm.fare_target.split(':');
    const options = {
        preserveScroll: true,
        onSuccess: () => resetFare(),
        onError: () =>
            toast.error(
                editingFare.value
                    ? 'Failed to update transport fare'
                    : 'Failed to add transport fare',
            ),
    };
    fareForm.transform((data) => ({
        ...data,
        fare_target: undefined,
        transport_sector_id: targetType === 'sector' ? targetId : null,
        transport_package_id: targetType === 'package' ? targetId : null,
        sale_amount: Number(data.sale_amount || 0),
        cost_amount: Number(data.cost_amount || 0),
        hajj_terminal_sale_amount: Number(data.hajj_terminal_sale_amount || 0),
        hajj_terminal_cost_amount: Number(data.hajj_terminal_cost_amount || 0),
    }));
    if (editingFare.value)
        fareForm.put(
            `/${props.company.slug}/umrah/settings/transport-fares/${editingFare.value.id}`,
            options,
        );
    else
        fareForm.post(
            `/${props.company.slug}/umrah/settings/transport-fares`,
            options,
        );
};

const updateCatalogStatus = (path: string, record: any) => {
    catalogStatusForm.is_active = !record.is_active;
    catalogStatusForm.patch(
        `/${props.company.slug}/umrah/settings/${path}/${record.id}/status`,
        {
            preserveScroll: true,
            onError: () =>
                toast.error(
                    catalogStatusForm.errors.transport ||
                        'This record has active dependencies',
                ),
        },
    );
};
</script>

<template>
    <Head title="Transport Services" />
    <PageShell
        title="Transport Services"
        description="Vehicles, sector fares, complete journeys, and terminal charges."
        :breadcrumbs="breadcrumbs"
        :icon="Bus"
    >
        <div class="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
            <Card class="min-w-0">
                <CardHeader
                    ><CardTitle>{{
                        editingService
                            ? 'Edit Transport Service'
                            : 'Add Transport Service'
                    }}</CardTitle></CardHeader
                >
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Name</Label>
                            <Input
                                v-model="form.name"
                                placeholder="Jeddah airport pickup"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Default Driver</Label>
                                <Select v-model="form.driver_id">
                                    <SelectTrigger
                                        ><SelectValue placeholder="Optional"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none"
                                            >No default driver</SelectItem
                                        >
                                        <SelectItem
                                            v-for="driver in drivers"
                                            :key="driver.id"
                                            :value="driver.id"
                                        >
                                            {{ driver.name
                                            }}<span v-if="driver.phone">
                                                · {{ driver.phone }}</span
                                            >
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Vehicle Type</Label
                                ><Input
                                    v-model="form.vehicle_type"
                                    placeholder="Car, 7-seater, coaster, bus"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Pax Capacity</Label
                                ><Input
                                    v-model="form.pax_capacity"
                                    type="number"
                                    min="1"
                                    placeholder="7"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Make</Label
                                ><Input
                                    v-model="form.make"
                                    placeholder="Toyota"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Model</Label
                                ><Input
                                    v-model="form.model"
                                    placeholder="Hiace"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Color</Label
                                ><Input v-model="form.color" />
                            </div>
                            <div class="space-y-2">
                                <Label>Number Plate</Label
                                ><Input v-model="form.number_plate" />
                            </div>
                            <div class="space-y-2">
                                <Label>Driver</Label
                                ><Input v-model="form.driver_name" />
                            </div>
                            <div class="space-y-2">
                                <Label>Driver Contact</Label
                                ><Input v-model="form.driver_contact" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Notes</Label
                            ><Textarea v-model="form.notes" />
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Button
                                v-if="editingService"
                                type="button"
                                variant="outline"
                                @click="resetForm"
                                ><X class="mr-2 h-4 w-4" />Cancel</Button
                            >
                            <Button
                                type="submit"
                                :class="editingService ? '' : 'sm:col-span-2'"
                                :disabled="form.processing"
                                ><Save class="mr-2 h-4 w-4" />{{
                                    editingService
                                        ? 'Save Changes'
                                        : 'Save Transport'
                                }}</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader
                    ><CardTitle>Available Transport</CardTitle></CardHeader
                >
                <CardContent class="p-0">
                    <LedgerRegister :data="transportServices" :columns="serviceColumns">
                        <template #empty>No transport services yet.</template>

                        <template #cell-vehicle_type="{ row }">{{
                            row.vehicle_type || '—'
                        }}</template>

                        <template #cell-make="{ row }">{{
                            row.make || '—'
                        }}</template>

                        <template #cell-model="{ row }">{{
                            row.model || '—'
                        }}</template>

                        <template #cell-pax_capacity="{ row }">{{
                            row.pax_capacity || '—'
                        }}</template>

                        <template #cell-number_plate="{ row }">{{
                            row.number_plate || '—'
                        }}</template>

                        <template #cell-driver="{ row }">{{
                            row.driver?.name || row.driver_name || '—'
                        }}</template>

                        <template #cell-contact="{ row }">{{
                            row.driver?.phone || row.driver_contact || '—'
                        }}</template>

                        <template #cell-status="{ row }">
                            <StatusBadge
                                :status="row.is_active ? 'active' : 'inactive'"
                            />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Edit transport service"
                                    @click="startEdit(row)"
                                    ><Pencil class="h-4 w-4" /></Button
                                ><Button
                                    v-if="row.is_active"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Deactivate transport service"
                                    :disabled="statusForm.processing"
                                    @click="removeService(row)"
                                    ><Power class="h-4 w-4" /></Button
                                ><Button
                                    v-else
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Reactivate transport service"
                                    :disabled="statusForm.processing"
                                    @click="reactivateService(row)"
                                    ><RotateCcw class="h-4 w-4"
                                /></Button>
                            </div>
                        </template>
                    </LedgerRegister>
                </CardContent>
            </Card>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <Card class="min-w-0">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><Map class="h-4 w-4" />Transport Sectors</CardTitle
                    ></CardHeader
                >
                <CardContent class="space-y-4">
                    <form
                        class="grid gap-3 sm:grid-cols-2"
                        @submit.prevent="submitSector"
                    >
                        <div class="space-y-2">
                            <Label>Code</Label
                            ><Input
                                v-model="sectorForm.code"
                                placeholder="JED-MAK"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Name</Label
                            ><Input
                                v-model="sectorForm.name"
                                placeholder="Airport to Makkah"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Origin</Label
                            ><Input
                                v-model="sectorForm.origin"
                                placeholder="Jeddah Airport"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Destination</Label
                            ><Input
                                v-model="sectorForm.destination"
                                placeholder="Makkah Hotel"
                                required
                            />
                        </div>
                        <div
                            class="grid gap-2 sm:col-span-2"
                            :class="editingSector ? 'grid-cols-2' : ''"
                        >
                            <Button
                                v-if="editingSector"
                                type="button"
                                variant="outline"
                                @click="resetSector"
                                ><X class="mr-2 size-4" />Cancel</Button
                            ><Button
                                type="submit"
                                :disabled="sectorForm.processing"
                                ><Save
                                    v-if="editingSector"
                                    class="mr-2 size-4"
                                /><Plus v-else class="mr-2 size-4" />{{
                                    editingSector
                                        ? 'Save Changes'
                                        : 'Add Sector'
                                }}</Button
                            >
                        </div>
                    </form>
                    <LedgerRegister :data="sectors" :columns="sectorColumns">
                        <template #empty>No transport sectors yet.</template>

                        <template #cell-status="{ row }">
                            <StatusBadge
                                :status="row.is_active ? 'active' : 'inactive'"
                            />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Edit sector"
                                    @click="startEditSector(row)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :title="
                                        row.is_active
                                            ? 'Deactivate sector'
                                            : 'Reactivate sector'
                                    "
                                    :disabled="catalogStatusForm.processing"
                                    @click="
                                        updateCatalogStatus(
                                            'transport-sectors',
                                            row,
                                        )
                                    "
                                    ><Power
                                        v-if="row.is_active"
                                        class="size-4" /><RotateCcw
                                        v-else
                                        class="size-4"
                                /></Button>
                            </div>
                        </template>
                    </LedgerRegister>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><Package class="h-4 w-4" />Journey Packages</CardTitle
                    ></CardHeader
                >
                <CardContent class="space-y-4">
                    <form class="space-y-3" @submit.prevent="submitPackage">
                        <div class="space-y-2">
                            <Label>Package Name</Label
                            ><Input
                                v-model="packageForm.name"
                                placeholder="Complete Umrah Journey"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Included Sectors</Label>
                            <div
                                class="grid gap-2 rounded-md border p-3 sm:grid-cols-2"
                            >
                                <Label
                                    v-for="sector in activeSectors"
                                    :key="sector.id"
                                    class="flex cursor-pointer items-start gap-2 text-sm font-normal"
                                >
                                    <Checkbox
                                        :model-value="
                                            packageForm.sector_ids.includes(
                                                sector.id,
                                            )
                                        "
                                        @update:model-value="
                                            togglePackageSector(
                                                sector.id,
                                                $event,
                                            )
                                        "
                                    />
                                    <span>{{ sector.name }}</span>
                                </Label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Notes</Label
                            ><Textarea v-model="packageForm.notes" />
                        </div>
                        <div
                            class="grid gap-2"
                            :class="editingPackage ? 'grid-cols-2' : ''"
                        >
                            <Button
                                v-if="editingPackage"
                                type="button"
                                variant="outline"
                                @click="resetPackage"
                                ><X class="mr-2 size-4" />Cancel</Button
                            ><Button
                                type="submit"
                                :disabled="
                                    packageForm.processing ||
                                    !packageForm.sector_ids.length
                                "
                                ><Save
                                    v-if="editingPackage"
                                    class="mr-2 size-4"
                                /><Plus v-else class="mr-2 size-4" />{{
                                    editingPackage
                                        ? 'Save Changes'
                                        : 'Add Journey Package'
                                }}</Button
                            >
                        </div>
                    </form>
                    <LedgerRegister :data="packages" :columns="packageColumns">
                        <template #empty>No journey packages yet.</template>

                        <template #cell-sectors="{ row }">
                            <span class="text-text-secondary">{{
                                row.sectors
                                    .map((sector) => sector.code)
                                    .join(' → ')
                            }}</span>
                        </template>

                        <template #cell-status="{ row }">
                            <StatusBadge
                                :status="row.is_active ? 'active' : 'inactive'"
                            />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Edit journey"
                                    @click="startEditPackage(row)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :title="
                                        row.is_active
                                            ? 'Deactivate journey'
                                            : 'Reactivate journey'
                                    "
                                    :disabled="catalogStatusForm.processing"
                                    @click="
                                        updateCatalogStatus(
                                            'transport-packages',
                                            row,
                                        )
                                    "
                                    ><Power
                                        v-if="row.is_active"
                                        class="size-4" /><RotateCcw
                                        v-else
                                        class="size-4"
                                /></Button>
                            </div>
                        </template>
                    </LedgerRegister>
                </CardContent>
            </Card>
        </div>

        <Card class="mt-6 min-w-0">
            <CardHeader
                ><CardTitle>Sector and Journey Fares</CardTitle></CardHeader
            >
            <CardContent class="space-y-5">
                <form
                    class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="submitFare"
                >
                    <div class="space-y-2">
                        <Label>Fare Name</Label
                        ><Input
                            v-model="fareForm.name"
                            placeholder="Sedan complete journey"
                            required
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Transport Provider</Label>
                        <Select v-model="fareForm.transport_vendor_id"
                            ><SelectTrigger
                                ><SelectValue
                                    placeholder="Select provider" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="vendor in transportVendors"
                                    :key="vendor.id"
                                    :value="vendor.id"
                                    >{{ vendor.name
                                    }}{{
                                        vendor.is_company_owned
                                            ? ' · Company-owned'
                                            : ''
                                    }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                        <p
                            v-if="fareForm.errors.transport_vendor_id"
                            class="text-xs text-destructive"
                        >
                            {{ fareForm.errors.transport_vendor_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Vehicle</Label>
                        <Select v-model="fareForm.transport_service_id"
                            ><SelectTrigger
                                ><SelectValue
                                    placeholder="Select vehicle" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="service in activeServices"
                                    :key="service.id"
                                    :value="service.id"
                                    >{{ service.name }} ·
                                    {{
                                        service.vehicle_type || 'Vehicle'
                                    }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="space-y-2">
                        <Label>Coverage</Label>
                        <Select v-model="fareForm.fare_target"
                            ><SelectTrigger
                                ><SelectValue
                                    placeholder="Sector or complete journey" /></SelectTrigger
                            ><SelectContent>
                                <SelectItem
                                    v-for="sector in activeSectors"
                                    :key="`sector:${sector.id}`"
                                    :value="`sector:${sector.id}`"
                                    >Sector · {{ sector.name }}</SelectItem
                                >
                                <SelectItem
                                    v-for="journey in activePackages"
                                    :key="`package:${journey.id}`"
                                    :value="`package:${journey.id}`"
                                    >Journey · {{ journey.name }}</SelectItem
                                >
                            </SelectContent></Select
                        >
                    </div>
                    <div class="space-y-2">
                        <Label>Charging Basis</Label>
                        <Select v-model="fareForm.charging_basis"
                            ><SelectTrigger><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="(label, value) in chargingBases"
                                    :key="value"
                                    :value="value"
                                    >{{ label }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="space-y-2">
                        <Label>Retail Fare</Label
                        ><Input
                            v-model="fareForm.sale_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Cost</Label
                        ><Input
                            v-model="fareForm.cost_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Hajj Terminal Extra</Label
                        ><Input
                            v-model="fareForm.hajj_terminal_sale_amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Hajj Terminal Extra Cost</Label
                        ><Input
                            v-model="fareForm.hajj_terminal_cost_amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div
                        class="grid gap-2 md:col-span-2 xl:col-span-4"
                        :class="editingFare ? 'grid-cols-2' : ''"
                    >
                        <Button
                            v-if="editingFare"
                            type="button"
                            variant="outline"
                            @click="resetFare"
                            ><X class="mr-2 size-4" />Cancel</Button
                        ><Button
                            type="submit"
                            :disabled="
                                fareForm.processing ||
                                !fareForm.transport_vendor_id ||
                                !fareForm.transport_service_id ||
                                !fareForm.fare_target
                            "
                            ><Save class="mr-2 h-4 w-4" />{{
                                editingFare ? 'Save Changes' : 'Save Fare'
                            }}</Button
                        >
                    </div>
                </form>

                <LedgerRegister :data="fares" :columns="fareColumns">
                    <template #empty>No sector or journey fares yet.</template>

                    <template #cell-provider="{ row }">{{
                        row.transport_vendor?.name || '—'
                    }}</template>

                    <template #cell-vehicle="{ row }">{{
                        row.service?.name || '—'
                    }}</template>

                    <template #cell-coverage="{ row }">{{
                        row.sector?.name || row.package?.name || '—'
                    }}</template>

                    <template #cell-basis="{ row }">{{
                        chargingBases[row.charging_basis]
                    }}</template>

                    <template #cell-sale_amount="{ row }">
                        <MoneyText
                            :amount="row.sale_amount"
                            :currency="company.base_currency"
                        />
                    </template>

                    <template #cell-cost_amount="{ row }">
                        <MoneyText
                            :amount="row.cost_amount"
                            :currency="company.base_currency"
                        />
                    </template>

                    <template #cell-hajj_terminal_sale_amount="{ row }">
                        <MoneyText
                            :amount="row.hajj_terminal_sale_amount"
                            :currency="company.base_currency"
                        />
                    </template>

                    <template #cell-status="{ row }">
                        <StatusBadge
                            :status="row.is_active ? 'active' : 'inactive'"
                        />
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="Edit fare"
                                @click="startEditFare(row)"
                                ><Pencil class="size-4" /></Button
                            ><Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :title="
                                    row.is_active
                                        ? 'Deactivate fare'
                                        : 'Reactivate fare'
                                "
                                :disabled="catalogStatusForm.processing"
                                @click="
                                    updateCatalogStatus('transport-fares', row)
                                "
                                ><Power
                                    v-if="row.is_active"
                                    class="size-4" /><RotateCcw
                                    v-else
                                    class="size-4"
                            /></Button>
                        </div>
                    </template>
                </LedgerRegister>
            </CardContent>
        </Card>

        <ConfirmDialog
            v-model:open="removeDialogOpen"
            variant="destructive"
            title="Deactivate Transport Service"
            :description="`Deactivate ${serviceToRemove?.name || 'this transport'} for future groups? Existing groups keep their history.`"
            confirm-text="Deactivate Transport"
            :loading="statusForm.processing"
            @confirm="confirmRemoveService"
        />
    </PageShell>
</template>
