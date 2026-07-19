<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Pencil,
    Plane,
    Plus,
    ScrollText,
    Trash2,
    WalletCards,
    Calculator,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    group: any;
    paymentMethods: Record<string, string>;
    paymentDirections: Record<string, string>;
    currencies: Array<{
        currency_code: string;
        exchange_rate: string | number;
    }>;
    passengerStatuses: Record<string, string>;
    visaVendors: any[];
    transportVendors: any[];
    hotelVendors: any[];
    groupCapabilities: {
        can_modify: boolean;
        has_started: boolean;
        requires_override_reason: boolean;
        can_record_payment: boolean;
        can_view_accounting: boolean;
    };
    changeLogs: any[];
}>();

const page = usePage();
const currentRole = computed(
    () => (page.props.auth as any)?.currentCompanyRole || null,
);
const canViewAccounting = computed(() =>
    ['super_admin', 'owner', 'accountant'].includes(String(currentRole.value)),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
    {
        title: props.group.group_number,
        href: `/${props.company.slug}/umrah/groups/${props.group.id}`,
    },
];

const passengerForm = useForm({
    full_name: '',
    passport_number: '',
    nationality: '',
    date_of_birth: '',
    imported_age: '',
    service_type: 'visa_transport',
    transport_charge_amount: '0',
    visa_status: 'received',
    notes: '',
    override_reason: '',
});

const paymentForm = useForm({
    payment_date: new Date().toISOString().slice(0, 10),
    direction: 'received',
    payee: 'none',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: '',
});

const bulkForm = useForm({
    visa_status: 'approved',
    override_reason: '',
});
const singleStatusForm = useForm({
    visa_status: '',
    override_reason: '',
});
const selectedPassengerIds = ref<string[]>([]);
const statusOverrideReason = ref('');
const passengerOpen = ref(false);
const editingPassenger = ref<any>(null);
const removePassengerTarget = ref<any>(null);
const editPassengerForm = useForm({ full_name: '', passport_number: '', nationality: '', date_of_birth: '', imported_age: '' as string | number, service_type: 'visa_transport', transport_charge_amount: '0', visa_status: 'received', notes: '', override_reason: '' });
const removeForm = useForm({ reason: '' });

const selectedPayee = computed(() => {
    const [type, id] = paymentForm.payee.split(':');
    if (type === 'visa')
        return props.visaVendors.find((vendor) => vendor.id === id);
    if (type === 'transport')
        return props.transportVendors.find((vendor) => vendor.id === id);
    return props.hotelVendors.find((vendor) => vendor.id === id);
});
const selectedCurrency = computed(() =>
    props.currencies.find(
        (currency) => currency.currency_code === paymentForm.currency,
    ),
);
const paymentBaseAmount = computed(
    () =>
        Math.round(
            Number(paymentForm.amount || 0) *
                Number(paymentForm.exchange_rate || 1) *
                100,
        ) / 100,
);
const remainingAfterPayment = computed(() => {
    const currentBalance =
        paymentForm.direction === 'received'
            ? Number(props.group.balance || 0)
            : Number(selectedPayee.value?.balance || 0);
    return Math.max(currentBalance - paymentBaseAmount.value, 0);
});
const canRecordPayment = computed(() =>
    paymentForm.direction === 'received'
        ? Number(props.group.balance || 0) > 0
        : paymentForm.payee !== 'none' &&
          Number(selectedPayee.value?.balance || 0) > 0,
);

const passengers = computed(() => props.group.passengers || []);

watch(
    () => paymentForm.currency,
    (currency) => {
        paymentForm.exchange_rate =
            currency === props.company.base_currency
                ? ''
                : String(selectedCurrency.value?.exchange_rate || '');
    },
);

const normalizeDate = (value: string | null | undefined) =>
    String(value || '').slice(0, 10);

const calculateAge = (dateOfBirth: string | null | undefined) => {
    const normalizedBirthDate = normalizeDate(dateOfBirth);

    if (!normalizedBirthDate) {
        return null;
    }

    const birthDate = new Date(`${normalizedBirthDate}T00:00:00`);
    const referenceDate = props.group.travel_date
        ? new Date(`${normalizeDate(props.group.travel_date)}T00:00:00`)
        : new Date();

    if (
        Number.isNaN(birthDate.getTime()) ||
        Number.isNaN(referenceDate.getTime())
    ) {
        return null;
    }

    let age = referenceDate.getFullYear() - birthDate.getFullYear();
    const monthDelta = referenceDate.getMonth() - birthDate.getMonth();

    if (
        monthDelta < 0 ||
        (monthDelta === 0 && referenceDate.getDate() < birthDate.getDate())
    ) {
        age -= 1;
    }

    return Math.max(age, 0);
};

const passengerAgeText = (passenger: any) => {
    const age = calculateAge(passenger.date_of_birth);

    if (age === null) {
        return passenger.imported_age !== null &&
            passenger.imported_age !== undefined
            ? `Age ${passenger.imported_age}`
            : 'Age not set';
    }

    return `${normalizeDate(passenger.date_of_birth)} · Age ${age}`;
};

const actionableStatuses = computed(() => {
    return Object.fromEntries(
        Object.entries(props.passengerStatuses).filter(([value]) =>
            ['approved', 'rejected', 'embassy'].includes(value),
        ),
    );
});

const allPassengersSelected = computed(() => {
    return (
        passengers.value.length > 0 &&
        selectedPassengerIds.value.length === passengers.value.length
    );
});

const somePassengersSelected = computed(() => {
    return (
        selectedPassengerIds.value.length > 0 &&
        selectedPassengerIds.value.length < passengers.value.length
    );
});

const isChecked = (checked: boolean | 'indeterminate') => checked === true;

const togglePassengerSelection = (
    passengerId: string,
    checked: boolean | 'indeterminate',
) => {
    const shouldSelect = isChecked(checked);

    if (shouldSelect && !selectedPassengerIds.value.includes(passengerId)) {
        selectedPassengerIds.value = [
            ...selectedPassengerIds.value,
            passengerId,
        ];
        return;
    }

    if (!shouldSelect) {
        selectedPassengerIds.value = selectedPassengerIds.value.filter(
            (id) => id !== passengerId,
        );
    }
};

const toggleAllPassengers = (checked: boolean | 'indeterminate') => {
    selectedPassengerIds.value = isChecked(checked)
        ? passengers.value.map((passenger: any) => passenger.id)
        : [];
};

const addPassenger = () =>
    passengerForm
        .transform((data) => ({
            ...data,
            imported_age:
                data.imported_age === '' ? null : Number(data.imported_age),
        }))
        .post(
            `/${props.company.slug}/umrah/groups/${props.group.id}/passengers`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Passenger added successfully');
                    passengerForm.reset();
                    passengerForm.visa_status = 'received';
                    passengerForm.service_type = 'visa_transport';
                    passengerForm.transport_charge_amount = '0';
                },
                onError: () => toast.error('Failed to add passenger'),
            },
        );

const updatePassengerStatus = (passenger: any, status: string) => {
    if (
        props.groupCapabilities.requires_override_reason &&
        statusOverrideReason.value.trim().length < 5
    ) {
        toast.error('Enter a reason before changing passenger data');
        return;
    }
    singleStatusForm.visa_status = status;
    singleStatusForm.override_reason = statusOverrideReason.value;
    singleStatusForm.put(
        `/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${passenger.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Passenger visa status updated'),
            onError: () => toast.error('Failed to update passenger status'),
        },
    );
};

const openPassenger = (passenger: any) => {
    editingPassenger.value = passenger;
    editPassengerForm.full_name = passenger.full_name;
    editPassengerForm.passport_number = passenger.passport_number || '';
    editPassengerForm.nationality = passenger.nationality || '';
    editPassengerForm.date_of_birth = normalizeDate(passenger.date_of_birth);
    editPassengerForm.imported_age = passenger.imported_age ?? '';
    editPassengerForm.service_type = passenger.service_type;
    editPassengerForm.transport_charge_amount = String(passenger.transport_charge_amount || 0);
    editPassengerForm.visa_status = passenger.visa_status;
    editPassengerForm.notes = passenger.notes || '';
    editPassengerForm.override_reason = statusOverrideReason.value;
    passengerOpen.value = true;
};
const updatePassenger = () => {
    if (!editingPassenger.value) return;
    editPassengerForm.transform((data) => ({ ...data, imported_age: data.imported_age === '' ? null : Number(data.imported_age), transport_charge_amount: Number(data.transport_charge_amount || 0) }))
        .put(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${editingPassenger.value.id}`, { preserveScroll: true, onSuccess: () => { passengerOpen.value = false; toast.success('Passenger corrected'); }, onError: () => toast.error('Failed to correct passenger') });
};
const removePassenger = (passenger: any) => {
    removePassengerTarget.value = passenger;
    removeForm.reset();
    removeForm.reason = statusOverrideReason.value;
};
const confirmRemovePassenger = () => {
    if (!removePassengerTarget.value) return;
    removeForm.delete(`/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${removePassengerTarget.value.id}`, { preserveScroll: true, onSuccess: () => { removePassengerTarget.value = null; toast.success('Passenger removed'); }, onError: () => toast.error('Passenger could not be removed') });
};

const bulkUpdatePassengerStatus = () => {
    if (
        props.groupCapabilities.requires_override_reason &&
        statusOverrideReason.value.trim().length < 5
    ) {
        toast.error('Enter a reason before changing passenger data');
        return;
    }
    bulkForm.override_reason = statusOverrideReason.value;
    bulkForm
        .transform((data) => ({
            ...data,
            passenger_ids: [...selectedPassengerIds.value],
        }))
        .put(
            `/${props.company.slug}/umrah/groups/${props.group.id}/passengers/status`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Selected passenger statuses updated');
                    selectedPassengerIds.value = [];
                },
                onError: () =>
                    toast.error('Failed to update selected passengers'),
            },
        );
};

const addPayment = () =>
    paymentForm
        .transform((data) => ({
            payment_date: data.payment_date,
            direction: data.direction,
            agent_id:
                data.direction === 'received' ? props.group.agent_id : null,
            visa_group_id: props.group.id,
            amount: Number(data.amount || 0),
            currency: data.currency,
            exchange_rate:
                data.currency === props.company.base_currency
                    ? null
                    : Number(data.exchange_rate || 0),
            visa_vendor_id:
                data.direction === 'sent' && data.payee.startsWith('visa:')
                    ? data.payee.slice(5)
                    : null,
            transport_vendor_id:
                data.direction === 'sent' && data.payee.startsWith('transport:')
                    ? data.payee.slice(10)
                    : null,
            hotel_vendor_id:
                data.direction === 'sent' && data.payee.startsWith('hotel:')
                    ? data.payee.slice(6)
                    : null,
        }))
        .post(
            `/${props.company.slug}/umrah/groups/${props.group.id}/payments`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Payment recorded successfully');
                    paymentForm.reset('amount');
                    paymentForm.payee = 'none';
                    paymentForm.payment_date = new Date()
                        .toISOString()
                        .slice(0, 10);
                },
                onError: () => toast.error('Failed to record payment'),
            },
        );
</script>

<template>
    <Head :title="group.group_number" />
    <PageShell
        :title="`${group.group_number} · ${group.name}`"
        :description="`${group.agent?.name || 'No agent'} · ${group.passenger_count} passengers`"
        :breadcrumbs="breadcrumbs"
        :icon="Plane"
    >
        <template #actions>
            <Button
                v-if="groupCapabilities.can_view_accounting"
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/groups/${group.id}/accounting`)"
            >
                <Calculator class="mr-2 h-4 w-4" />
                Accounting
            </Button>
            <Button
                v-if="groupCapabilities.can_modify"
                variant="outline"
                @click="
                    router.get(`/${company.slug}/umrah/groups/${group.id}/edit`)
                "
            >
                <Pencil class="mr-2 h-4 w-4" />
                Edit Group
            </Button>
            <Button
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/vouchers/create?group_id=${group.id}`,
                    )
                "
            >
                <ScrollText class="mr-2 h-4 w-4" />
                Create Voucher
            </Button>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <Card
                ><CardHeader><CardTitle>Receivable</CardTitle></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="group.total_receivable"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card
                ><CardHeader><CardTitle>Paid</CardTitle></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="group.total_paid"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card
                ><CardHeader><CardTitle>Balance</CardTitle></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="group.balance"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card>
                <CardHeader><CardTitle>Payment Status</CardTitle></CardHeader>
                <CardContent>
                    <Badge
                        :variant="
                            Number(group.balance || 0) <= 0
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{
                            Number(group.balance || 0) <= 0 ? 'Paid' : 'Unpaid'
                        }}
                    </Badge>
                </CardContent>
            </Card>
            <Card v-if="canViewAccounting"
                ><CardHeader><CardTitle>Profit</CardTitle></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="group.profit"
                        :currency="company.base_currency" /></CardContent
            ></Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Group Info</CardTitle>
                        <CardDescription v-if="groupCapabilities.can_modify"
                            >Travel and service details.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-3">
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Travel Date
                            </div>
                            <div class="font-medium">
                                {{ group.travel_date || 'Not set' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Vendor
                            </div>
                            <div class="font-medium">
                                {{ group.vendor?.name || 'Not set' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Visa Service
                            </div>
                            <div class="font-medium">
                                {{ group.visa_service?.name || 'Custom' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Flight
                            </div>
                            <div class="font-medium">
                                {{ group.flight_info?.airline || 'Not set' }}
                                {{ group.flight_info?.number || '' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Makkah Hotel
                            </div>
                            <div class="font-medium">
                                {{ group.hotel_info?.makkah || 'Not set' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Madinah Hotel
                            </div>
                            <div class="font-medium">
                                {{ group.hotel_info?.madinah || 'Not set' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Transport
                            </div>
                            <div class="font-medium">
                                {{
                                    group.transport_mode === 'specialized'
                                        ? 'Specialized transport'
                                        : 'Standard bus included'
                                }}
                            </div>
                            <div
                                v-if="group.mandatory_transport_vendor"
                                class="text-xs text-muted-foreground"
                            >
                                Provider:
                                {{ group.mandatory_transport_vendor.name }}
                            </div>
                            <div
                                v-if="
                                    group.transport_required &&
                                    (group.transport_pax_capacity ||
                                        group.transport_service?.vehicle_type)
                                "
                                class="text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="group.transport_service?.vehicle_type"
                                    >{{
                                        group.transport_service.vehicle_type
                                    }}</span
                                >
                                <span v-if="group.transport_pax_capacity">
                                    · {{ group.transport_pax_capacity }} pax
                                    each</span
                                >
                            </div>
                            <div
                                v-if="
                                    group.driver ||
                                    group.transport_service?.driver_name ||
                                    group.transport_service?.number_plate
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    group.driver?.name ||
                                    group.transport_service?.driver_name ||
                                    'No driver'
                                }}
                                <span v-if="group.driver?.phone">
                                    · {{ group.driver.phone }}</span
                                >
                                <span
                                    v-if="group.transport_service?.number_plate"
                                >
                                    ·
                                    {{
                                        group.transport_service.number_plate
                                    }}</span
                                >
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Visa Sale
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.visa_sale_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Transport Charge
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.transport_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Hotel Charge
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.hotel_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div v-if="canViewAccounting">
                            <div class="text-sm text-muted-foreground">
                                Visa Cost
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.visa_cost_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div
                            v-if="
                                canViewAccounting &&
                                Number(group.included_bus_cost_deduction || 0) >
                                    0
                            "
                        >
                            <div class="text-sm text-muted-foreground">
                                Included Bus Cost Deducted
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.included_bus_cost_deduction"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div v-if="canViewAccounting">
                            <div class="text-sm text-muted-foreground">
                                Transport Cost
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.transport_cost_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div v-if="canViewAccounting">
                            <div class="text-sm text-muted-foreground">
                                Hotel Cost
                            </div>
                            <div class="font-medium">
                                <MoneyText
                                    :amount="group.hotel_cost_amount"
                                    :currency="company.base_currency"
                                />
                            </div>
                        </div>
                        <div v-if="canViewAccounting">
                            <div class="text-sm text-muted-foreground">
                                Sale Journal
                            </div>
                            <Button
                                v-if="group.sale_transaction"
                                variant="link"
                                class="h-auto p-0"
                                @click="
                                    router.get(
                                        `/${company.slug}/journals/${group.sale_transaction.id}`,
                                    )
                                "
                            >
                                {{ group.sale_transaction.transaction_number }}
                            </Button>
                            <div v-else class="font-medium">Not posted</div>
                        </div>
                        <div v-if="canViewAccounting">
                            <div class="text-sm text-muted-foreground">
                                Cost Journal
                            </div>
                            <Button
                                v-if="group.cost_transaction"
                                variant="link"
                                class="h-auto p-0"
                                @click="
                                    router.get(
                                        `/${company.slug}/journals/${group.cost_transaction.id}`,
                                    )
                                "
                            >
                                {{ group.cost_transaction.transaction_number }}
                            </Button>
                            <div v-else class="font-medium">Not posted</div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="group.transport_mode === 'specialized'">
                    <CardHeader
                        ><CardTitle>Transport Schedule</CardTitle
                        ><CardDescription
                            >Selected journey and sector fare
                            snapshots.</CardDescription
                        ></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div
                            v-if="
                                groupCapabilities.requires_override_reason &&
                                groupCapabilities.can_modify
                            "
                            class="space-y-2"
                        >
                            <Label
                                >Reason for changing passenger data after travel
                                started</Label
                            >
                            <Textarea v-model="statusOverrideReason" required />
                        </div>
                        <div
                            v-for="item in group.transport_items"
                            :key="item.id"
                            class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_140px_120px_150px]"
                        >
                            <div>
                                <div class="font-medium">
                                    {{ item.description }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        item.sector?.name || item.package?.name
                                    }}
                                    · {{ item.service?.name }} ·
                                    {{ item.transport_vendor?.name
                                    }}<span v-if="item.terminal === 'hajj'">
                                        · Hajj Terminal</span
                                    >
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">
                                    Schedule
                                </div>
                                <div>
                                    {{ item.scheduled_at || 'Not scheduled' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">
                                    Vehicles / Pax
                                </div>
                                <div>
                                    {{ item.quantity }} /
                                    {{ item.passenger_count }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">
                                    Charge
                                </div>
                                <MoneyText
                                    :amount="item.total_sale_amount"
                                    :currency="company.base_currency"
                                />
                                <div
                                    v-if="canViewAccounting"
                                    class="text-xs text-muted-foreground"
                                >
                                    Cost
                                    <MoneyText
                                        :amount="item.total_cost_amount"
                                        :currency="company.base_currency"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Passengers</CardTitle>
                        <CardDescription
                            >Update visa status one by one or select multiple
                            passengers for a bulk change.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-if="!group.passengers?.length"
                            class="text-sm text-muted-foreground"
                        >
                            No passengers added yet.
                        </div>
                        <div
                            v-else-if="groupCapabilities.can_modify"
                            class="flex flex-col gap-3 rounded-md border p-3 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <Checkbox
                                    v-if="groupCapabilities.can_modify"
                                    :model-value="
                                        somePassengersSelected
                                            ? 'indeterminate'
                                            : allPassengersSelected
                                    "
                                    @update:model-value="toggleAllPassengers"
                                />
                                <div class="text-sm text-muted-foreground">
                                    {{ selectedPassengerIds.length }} selected
                                </div>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-[180px_auto]">
                                <Select v-model="bulkForm.visa_status">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(
                                                label, value
                                            ) in actionableStatuses"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    :disabled="
                                        bulkForm.processing ||
                                        selectedPassengerIds.length === 0
                                    "
                                    @click="bulkUpdatePassengerStatus"
                                >
                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                    Apply to Selected
                                </Button>
                            </div>
                        </div>
                        <div
                            v-for="passenger in group.passengers"
                            :key="passenger.id"
                            class="grid gap-2 rounded-md border p-3 md:grid-cols-[32px_1fr_150px_120px_130px_190px_160px_80px]"
                        >
                            <div class="flex items-start pt-1">
                                <Checkbox
                                    :model-value="
                                        selectedPassengerIds.includes(
                                            passenger.id,
                                        )
                                    "
                                    @update:model-value="
                                        (checked) =>
                                            togglePassengerSelection(
                                                passenger.id,
                                                checked,
                                            )
                                    "
                                />
                            </div>
                            <div>
                                <div class="font-medium">
                                    {{ passenger.full_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ passenger.notes || 'No notes' }}
                                </div>
                            </div>
                            <div>
                                {{ passenger.passport_number || 'No passport' }}
                            </div>
                            <div>{{ passengerAgeText(passenger) }}</div>
                            <div>
                                {{
                                    passenger.nationality ||
                                    'Nationality not set'
                                }}
                            </div>
                            <div>
                                <div>
                                    {{
                                        passenger.service_type ===
                                        'transport_only'
                                            ? 'Transport only'
                                            : 'Visa included'
                                    }}
                                </div>
                                <div
                                    v-if="
                                        passenger.service_type ===
                                        'transport_only'
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    <MoneyText
                                        :amount="
                                            passenger.transport_charge_amount
                                        "
                                        :currency="company.base_currency"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Badge variant="secondary">{{
                                    passengerStatuses[passenger.visa_status] ||
                                    passenger.visa_status
                                }}</Badge>
                                <Select
                                    v-if="groupCapabilities.can_modify"
                                    :model-value="passenger.visa_status"
                                    @update:model-value="
                                        (status) =>
                                            updatePassengerStatus(
                                                passenger,
                                                String(status),
                                            )
                                    "
                                >
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(
                                                label, value
                                            ) in actionableStatuses"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="groupCapabilities.can_modify" class="flex gap-1">
                                <Button size="icon" variant="ghost" title="Correct passenger" @click="openPassenger(passenger)"><Pencil class="h-4 w-4" /></Button>
                                <Button size="icon" variant="ghost" title="Remove passenger" @click="removePassenger(passenger)"><Trash2 class="h-4 w-4" /></Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="groupCapabilities.can_modify">
                    <CardHeader><CardTitle>Payments</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-if="!group.payments?.length"
                            class="text-sm text-muted-foreground"
                        >
                            No payments recorded yet.
                        </div>
                        <div
                            v-for="payment in group.payments"
                            :key="payment.id"
                            class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_170px_170px]"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="font-medium">
                                        {{ payment.payment_number }}
                                    </div>
                                    <Badge
                                        :variant="
                                            payment.direction === 'sent'
                                                ? 'outline'
                                                : 'secondary'
                                        "
                                        >{{
                                            paymentDirections[
                                                payment.direction
                                            ] || payment.direction
                                        }}</Badge
                                    >
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ payment.payment_date }} ·
                                    {{
                                        paymentMethods[payment.method] ||
                                        payment.method
                                    }}
                                    · {{ payment.reference || 'No reference' }}
                                </div>
                                <Button
                                    v-if="payment.transaction"
                                    variant="link"
                                    class="h-auto p-0 text-xs"
                                    @click="
                                        router.get(
                                            `/${company.slug}/journals/${payment.transaction.id}`,
                                        )
                                    "
                                >
                                    Journal
                                    {{ payment.transaction.transaction_number }}
                                </Button>
                            </div>
                            <div>
                                <div>
                                    {{
                                        payment.visa_vendor?.name ||
                                        payment.hotel_vendor?.name ||
                                        group.agent?.name
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        payment.account
                                            ? `${payment.account.code} — ${payment.account.name}`
                                            : 'No account selected'
                                    }}
                                </div>
                            </div>
                            <div
                                class="text-right font-semibold"
                                :class="
                                    payment.direction === 'sent'
                                        ? 'text-destructive'
                                        : 'text-emerald-700'
                                "
                            >
                                <MoneyText
                                    :amount="payment.allocated_base_amount"
                                    :currency="payment.base_currency"
                                />
                                <div
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    Allocated from
                                    <MoneyText
                                        :amount="payment.amount"
                                        :currency="payment.currency"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>Add Passenger</CardTitle></CardHeader
                    >
                    <CardContent>
                        <form class="space-y-3" @submit.prevent="addPassenger">
                            <div class="space-y-2">
                                <Label>Name</Label
                                ><Input
                                    v-model="passengerForm.full_name"
                                    required
                                />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Passport #</Label
                                    ><Input
                                        v-model="passengerForm.passport_number"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Age</Label
                                    ><Input
                                        v-model="passengerForm.imported_age"
                                        type="number"
                                        min="0"
                                        max="130"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Nationality</Label
                                    ><Input
                                        v-model="passengerForm.nationality"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Status</Label>
                                <Select v-model="passengerForm.visa_status">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(
                                                label, value
                                            ) in passengerStatuses"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Service</Label>
                                <Select v-model="passengerForm.service_type">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent
                                        ><SelectItem value="visa_transport"
                                            >Visa included</SelectItem
                                        ><SelectItem value="transport_only"
                                            >Already has visa - transport
                                            only</SelectItem
                                        ></SelectContent
                                    >
                                </Select>
                            </div>
                            <div
                                v-if="
                                    passengerForm.service_type ===
                                    'transport_only'
                                "
                                class="space-y-2"
                            >
                                <Label>Transport Charge</Label
                                ><Input
                                    v-model="
                                        passengerForm.transport_charge_amount
                                    "
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Notes</Label
                                ><Textarea v-model="passengerForm.notes" />
                            </div>
                            <div
                                v-if="
                                    groupCapabilities.requires_override_reason
                                "
                                class="space-y-2"
                            >
                                <Label>Override reason</Label>
                                <Textarea
                                    v-model="passengerForm.override_reason"
                                    required
                                />
                                <p
                                    v-if="passengerForm.errors.override_reason"
                                    class="text-xs text-destructive"
                                >
                                    {{ passengerForm.errors.override_reason }}
                                </p>
                            </div>
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="passengerForm.processing"
                                ><Plus class="mr-2 h-4 w-4" />Add
                                Passenger</Button
                            >
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="groupCapabilities.can_record_payment">
                    <CardHeader>
                        <CardTitle>Record Payment</CardTitle>
                        <CardDescription
                            >{{
                                paymentForm.direction === 'received'
                                    ? 'Agent balance'
                                    : 'Vendor balance'
                            }}
                            after payment:
                            <MoneyText
                                :amount="remainingAfterPayment"
                                :currency="company.base_currency"
                        /></CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-3" @submit.prevent="addPayment">
                            <div class="space-y-2">
                                <Label>Date</Label
                                ><Input
                                    v-model="paymentForm.payment_date"
                                    type="date"
                                    required
                                />
                            </div>
                            <div v-if="canViewAccounting" class="space-y-2">
                                <Label>Direction</Label>
                                <Select
                                    v-model="paymentForm.direction"
                                    @update:model-value="
                                        paymentForm.payee = 'none'
                                    "
                                >
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent
                                        ><SelectItem
                                            v-for="(
                                                label, value
                                            ) in paymentDirections"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        ></SelectContent
                                    >
                                </Select>
                            </div>
                            <div
                                v-if="paymentForm.direction === 'sent'"
                                class="space-y-2"
                            >
                                <Label>Paid To</Label>
                                <Select v-model="paymentForm.payee">
                                    <SelectTrigger
                                        ><SelectValue
                                            placeholder="Select vendor"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none"
                                            >Select vendor</SelectItem
                                        >
                                        <SelectItem
                                            v-for="vendor in visaVendors"
                                            :key="`visa-${vendor.id}`"
                                            :value="`visa:${vendor.id}`"
                                            >{{ vendor.name }} · Visa</SelectItem
                                        >
                                        <SelectItem
                                            v-for="vendor in transportVendors"
                                            :key="`transport-${vendor.id}`"
                                            :value="`transport:${vendor.id}`"
                                            >{{ vendor.name }} · Transport<span
                                                v-if="vendor.is_company_owned"
                                            >
                                                · Company-owned</span
                                            ></SelectItem
                                        >
                                        <SelectItem
                                            v-for="vendor in hotelVendors"
                                            :key="`hotel-${vendor.id}`"
                                            :value="`hotel:${vendor.id}`"
                                            >{{ vendor.name }} ·
                                            Hotel</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="paymentForm.errors.vendor_id"
                                    class="text-xs text-destructive"
                                >
                                    {{ paymentForm.errors.vendor_id }}
                                </p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Currency</Label>
                                    <Select v-model="paymentForm.currency">
                                        <SelectTrigger
                                            ><SelectValue
                                        /></SelectTrigger>
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
                                    <p
                                        v-if="paymentForm.errors.currency"
                                        class="text-xs text-destructive"
                                    >
                                        {{ paymentForm.errors.currency }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Amount</Label>
                                    <Input
                                        v-model="paymentForm.amount"
                                        type="number"
                                        min="0.000001"
                                        step="0.000001"
                                        required
                                    />
                                </div>
                                <p
                                    v-if="paymentForm.errors.amount"
                                    class="text-xs text-destructive"
                                >
                                    {{ paymentForm.errors.amount }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    paymentForm.currency !==
                                    company.base_currency
                                "
                                class="space-y-2"
                            >
                                <Label>Exchange Rate</Label>
                                <Input
                                    v-model="paymentForm.exchange_rate"
                                    type="number"
                                    min="0.00000001"
                                    step="0.00000001"
                                    required
                                />
                                <p class="text-xs text-muted-foreground">
                                    1 {{ paymentForm.currency }} =
                                    {{ paymentForm.exchange_rate || 0 }}
                                    {{ company.base_currency }} · Converted:
                                    <MoneyText
                                        :amount="paymentBaseAmount"
                                        :currency="company.base_currency"
                                    />
                                </p>
                                <p
                                    v-if="paymentForm.errors.exchange_rate"
                                    class="text-xs text-destructive"
                                >
                                    {{ paymentForm.errors.exchange_rate }}
                                </p>
                            </div>
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="
                                    paymentForm.processing || !canRecordPayment
                                "
                                ><WalletCards class="mr-2 h-4 w-4" />Record
                                Payment</Button
                            >
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
        <Card v-if="changeLogs.length">
            <CardHeader
                ><CardTitle>Change History</CardTitle
                ><CardDescription
                    >Company overrides and operational changes.</CardDescription
                ></CardHeader
            >
            <CardContent class="divide-y p-0">
                <div
                    v-for="log in changeLogs"
                    :key="log.id"
                    class="grid gap-1 px-6 py-3 md:grid-cols-[180px_160px_1fr]"
                >
                    <DateTimeText :value="log.created_at" />
                    <div class="font-medium">
                        {{ log.user?.name || 'System' }}
                    </div>
                    <div>
                        <span class="capitalize">{{
                            String(log.action).replaceAll('_', ' ')
                        }}</span
                        ><span v-if="log.reason" class="text-muted-foreground">
                            · {{ log.reason }}</span
                        >
                    </div>
                </div>
            </CardContent>
        </Card>
        <Dialog v-model:open="passengerOpen"><DialogContent class="max-w-2xl"><DialogHeader><DialogTitle>Correct Passenger</DialogTitle></DialogHeader>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2"><Label>Full name</Label><Input v-model="editPassengerForm.full_name" /></div>
                <div class="space-y-2"><Label>Passport number</Label><Input v-model="editPassengerForm.passport_number" /></div>
                <div class="space-y-2"><Label>Date of birth</Label><Input v-model="editPassengerForm.date_of_birth" type="date" /></div>
                <div class="space-y-2"><Label>Imported age</Label><Input v-model="editPassengerForm.imported_age" type="number" /></div>
                <div class="space-y-2"><Label>Nationality</Label><Input v-model="editPassengerForm.nationality" /></div>
                <div class="space-y-2"><Label>Service</Label><Select v-model="editPassengerForm.service_type"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="visa_transport">Visa included</SelectItem><SelectItem value="transport_only">Transport only</SelectItem></SelectContent></Select></div>
                <div v-if="editPassengerForm.service_type === 'transport_only'" class="space-y-2"><Label>Transport charge</Label><Input v-model="editPassengerForm.transport_charge_amount" type="number" min="0" step="0.01" /></div>
                <div class="space-y-2"><Label>Visa status</Label><Select v-model="editPassengerForm.visa_status"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem v-for="(label, value) in passengerStatuses" :key="value" :value="value">{{ label }}</SelectItem></SelectContent></Select></div>
                <div class="space-y-2 md:col-span-2"><Label>Notes</Label><Textarea v-model="editPassengerForm.notes" /></div>
                <div v-if="groupCapabilities.requires_override_reason" class="space-y-2 md:col-span-2"><Label>Reason for post-travel correction</Label><Textarea v-model="editPassengerForm.override_reason" required /></div>
            </div>
            <DialogFooter><Button variant="outline" @click="passengerOpen = false">Cancel</Button><Button :disabled="editPassengerForm.processing" @click="updatePassenger">Save Correction</Button></DialogFooter>
        </DialogContent></Dialog>
        <Dialog :open="removePassengerTarget !== null" @update:open="(open) => { if (!open) removePassengerTarget = null; }"><DialogContent><DialogHeader><DialogTitle>Remove Passenger</DialogTitle></DialogHeader>
            <p class="text-sm text-muted-foreground">Remove {{ removePassengerTarget?.full_name }} and recalculate visa, transport, and group totals. A passenger on an approved voucher cannot be removed.</p>
            <div class="space-y-2"><Label for="remove-reason">Reason {{ groupCapabilities.requires_override_reason ? '' : '(optional)' }}</Label><Textarea id="remove-reason" v-model="removeForm.reason" /><p v-if="removeForm.errors.reason" class="text-sm text-destructive">{{ removeForm.errors.reason }}</p></div>
            <DialogFooter><Button variant="outline" @click="removePassengerTarget = null">Keep Passenger</Button><Button variant="destructive" :disabled="removeForm.processing || (groupCapabilities.requires_override_reason && removeForm.reason.trim().length < 5)" @click="confirmRemovePassenger">Remove Passenger</Button></DialogFooter>
        </DialogContent></Dialog>
    </PageShell>
</template>
