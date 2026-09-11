<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFigure,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, localDateInput } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Calculator,
    Pencil,
    Plane,
    Plus,
    ScrollText,
    Trash2,
    Undo2,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type CurrentParty = {
    id: string | null;
    number: string | null;
    agent: string | null;
    status: string | null;
    elsewhere: boolean;
};

const props = defineProps<{
    company: { slug: string; base_currency: string };
    group: any;
    paymentMethods: Record<string, string>;
    paymentDirections: Record<string, string>;
    currencies: Array<{
        currency_code: string;
        exchange_rate: string | number;
    }>;
    travellingParties: {
        assignments: Record<string, CurrentParty>;
        joining: Array<{
            id: string;
            name: string;
            passport: string | null;
            original_group: string | null;
            original_group_id: string | null;
            voucher: CurrentParty;
        }>;
    };
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

    notes: '',
    override_reason: '',
});

const paymentForm = useForm({
    payment_date: localDateInput(),
    direction: 'received',
    payee: 'none',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: '',
});

const passengerOpen = ref(false);
const addPassengerOpen = ref(false);
const recordPaymentOpen = ref(false);
const editingPassenger = ref<any>(null);
const removePassengerTarget = ref<any>(null);
const editPassengerForm = useForm({
    full_name: '',
    passport_number: '',
    nationality: '',
    date_of_birth: '',
    imported_age: '' as string | number,
    notes: '',
    override_reason: '',
});

/*
 * The group answers this once, for everyone in it. A passenger has no
 * service of its own to state or to edit; the server derives one from
 * the group and ignores anything a passenger row claims.
 */
const groupService = computed(() =>
    props.group.includes_visa === false
        ? 'Transport only'
        : props.group.transport_mode === 'none'
          ? 'Visa only'
          : 'Visa included',
);
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
    // Clamped at zero because a negative amount would otherwise make the
    // balance appear to *grow*, so typing -1 against a 60,000 balance
    // previewed 60,001 -- a figure that reads as money arriving from a
    // payment the server is about to refuse.
    return Math.max(currentBalance - Math.max(paymentBaseAmount.value, 0), 0);
});

const paymentSubmitAttempted = ref(false);

/**
 * A number field holds three kinds of nothing-useful -- blank, zero and
 * negative -- and the server rejects all three. Browser validation used to
 * catch them before the form was ever submitted; it is switched off across the
 * app now, so this says the same thing in the app's own voice, next to the
 * field, as the figure is typed.
 */
const paymentAmountIssue = computed(() => {
    const raw = String(paymentForm.amount ?? '').trim();

    if (raw === '') {
        // Nothing typed yet is not yet a mistake. An empty field only becomes
        // one once someone has tried to submit it, so the dialog does not open
        // already telling people off.
        return paymentSubmitAttempted.value ? 'Enter an amount.' : null;
    }

    const value = Number(raw);

    if (!Number.isFinite(value)) {
        return 'Enter the amount as a number.';
    }

    return value > 0 ? null : 'An amount has to be more than zero.';
});

/**
 * Why the submit is unavailable, in words. The button used to grey out on a
 * condition held entirely off-screen -- a group with nothing outstanding, or a
 * vendor not yet chosen -- which leaves someone clicking a dead control with
 * nothing to read.
 */
const recordPaymentBlockedReason = computed(() => {
    if (paymentForm.direction === 'received') {
        return Number(props.group.balance || 0) > 0
            ? null
            : 'This group has nothing outstanding, so there is no payment left to record against it.';
    }

    if (paymentForm.payee === 'none') {
        return 'Choose who the money was paid to.';
    }

    return Number(selectedPayee.value?.balance || 0) > 0
        ? null
        : 'Nothing is owed to this vendor for this group.';
});
const canRecordPayment = computed(
    () => recordPaymentBlockedReason.value === null,
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

    return `Age ${age}`;
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
                    passengerForm.reset();
                    addPassengerOpen.value = false;
                },
                onError: () => toast.error('Failed to add passenger'),
            },
        );

const openPassenger = (passenger: any) => {
    editingPassenger.value = passenger;
    editPassengerForm.full_name = passenger.full_name;
    editPassengerForm.passport_number = passenger.passport_number || '';
    editPassengerForm.nationality = passenger.nationality || '';
    editPassengerForm.date_of_birth = normalizeDate(passenger.date_of_birth);
    editPassengerForm.imported_age = passenger.imported_age ?? '';
    editPassengerForm.notes = passenger.notes || '';
    editPassengerForm.override_reason = '';
    passengerOpen.value = true;
};
const updatePassenger = () => {
    if (!editingPassenger.value) return;
    editPassengerForm
        .transform((data) => ({
            ...data,
            imported_age:
                data.imported_age === '' ? null : Number(data.imported_age),
        }))
        .put(
            `/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${editingPassenger.value.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    passengerOpen.value = false;
                },
                onError: () => toast.error('Failed to correct passenger'),
            },
        );
};
const removePassenger = (passenger: any) => {
    removePassengerTarget.value = passenger;
    removeForm.reset();
};
const confirmRemovePassenger = () => {
    if (!removePassengerTarget.value) return;
    removeForm.delete(
        `/${props.company.slug}/umrah/groups/${props.group.id}/passengers/${removePassengerTarget.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                removePassengerTarget.value = null;
            },
            onError: () => toast.error('Passenger could not be removed'),
        },
    );
};

const addPayment = () => {
    paymentSubmitAttempted.value = true;

    // Browser validation no longer stops a bad figure at the field, so a blank
    // or zero amount would otherwise go to the server and come back as a toast
    // with nothing pointing at the field that caused it.
    if (paymentAmountIssue.value) {
        return;
    }

    return paymentForm
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
                    paymentForm.reset('amount');
                    paymentForm.payee = 'none';
                    paymentForm.payment_date = localDateInput();
                    paymentSubmitAttempted.value = false;
                    recordPaymentOpen.value = false;
                },
                onError: () => toast.error('Failed to record payment'),
            },
        );
};
</script>

<template>
    <Head :title="group.group_number" />
    <PageShell
        :title="`${group.group_number} · ${group.name}`"
        :description="`${group.agent?.name || 'No agent'} · ${passengers.length} ${passengers.length === 1 ? 'passenger' : 'passengers'} in purchase group`"
        :breadcrumbs="breadcrumbs"
        :icon="Plane"
    >
        <template #actions>
            <Button type="button" @click="addPassengerOpen = true">
                <Plus class="mr-2 h-4 w-4" />
                Add Passenger
            </Button>
            <Button
                v-if="groupCapabilities.can_record_payment"
                type="button"
                variant="outline"
                @click="recordPaymentOpen = true"
            >
                <WalletCards class="mr-2 h-4 w-4" />
                Record Payment
            </Button>
            <Button
                v-if="groupCapabilities.can_view_accounting"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/groups/${group.id}/accounting`,
                    )
                "
            >
                <Calculator class="mr-2 h-4 w-4" />
                Adjust charges
            </Button>
            <!--
                Both refunds start from the trip, because that is where
                somebody is standing when they decide to make one. The group
                travels in the query so the form opens with it already
                chosen rather than asking again for something the page knew.
            -->
            <Button
                v-if="groupCapabilities.can_view_accounting && group.agent_id"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/refunds/create?party_type=agent&party_id=${group.agent_id}&visa_group_id=${group.id}`,
                    )
                "
            >
                <Undo2 class="mr-2 h-4 w-4" />
                Refund agent
            </Button>
            <Button
                v-if="groupCapabilities.can_view_accounting && group.vendor_id"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/refunds/create?party_type=visa_vendor&party_id=${group.vendor_id}&visa_group_id=${group.id}`,
                    )
                "
            >
                <Undo2 class="mr-2 h-4 w-4" />
                Supplier credit
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
            <Card variant="figure"
                ><CardHeader><CardTitle>Receivable</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="group.total_receivable"
                            :currency="
                                company.base_currency
                            " /></CardFigure></CardContent
            ></Card>
            <Card variant="figure"
                ><CardHeader><CardTitle>Paid</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="group.total_paid"
                            :currency="
                                company.base_currency
                            " /></CardFigure></CardContent
            ></Card>
            <Card variant="figure"
                ><CardHeader><CardTitle>Balance</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="group.balance"
                            :currency="
                                company.base_currency
                            " /></CardFigure></CardContent
            ></Card>
            <Card>
                <CardHeader><CardTitle>Payment Status</CardTitle></CardHeader>
                <CardContent>
                    <!--
                        The server's answer, not one worked out here. This
                        was recomputed from group.balance, which an agent
                        is never sent: undefined became zero, zero read as
                        settled, and a group with one small payment against
                        it showed Paid beside a dash where its balance
                        should be.
                    -->
                    <StatusBadge :status="group.payment_status || 'unpaid'" />
                </CardContent>
            </Card>
            <Card v-if="canViewAccounting" variant="figure"
                ><CardHeader><CardTitle>Profit</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="group.profit"
                            :currency="
                                company.base_currency
                            " /></CardFigure></CardContent
            ></Card>
        </div>

        <div class="grid gap-6">
            <div class="space-y-6">
                <Card variant="detail">
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
                                <DateTimeText
                                    :value="group.travel_date"
                                    mode="date"
                                    fallback="Not set"
                                />
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
                                Includes
                            </div>
                            <div class="font-medium">
                                {{
                                    group.includes_visa === false
                                        ? 'Transport only'
                                        : group.transport_mode === 'none'
                                          ? 'Visa only (self transport)'
                                          : group.transport_mode ===
                                              'specialized'
                                            ? 'Visa and specialized transport'
                                            : 'Visa and standard bus'
                                }}
                            </div>
                            <div
                                v-if="group.mandatory_transport_vendor"
                                class="text-xs text-muted-foreground"
                            >
                                Vendor:
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
                                    group.transport_mode !== 'none' &&
                                    (group.driver ||
                                        group.transport_service?.driver_name ||
                                        group.transport_service?.number_plate)
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

                <Card
                    v-if="group.transport_mode === 'specialized'"
                    variant="form"
                >
                    <CardHeader
                        ><CardTitle>Transport Schedule</CardTitle
                        ><CardDescription
                            >Selected journey and sector fare
                            snapshots.</CardDescription
                        ></CardHeader
                    >
                    <CardContent class="space-y-3">
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
                                    {{
                                        item.scheduled_at
                                            ? formatDateTime(item.scheduled_at)
                                            : 'Not scheduled'
                                    }}
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

                <Card variant="form">
                    <CardHeader>
                        <CardTitle
                            >Passengers · {{ passengers.length }}</CardTitle
                        >
                        <CardDescription
                            >Original purchase group. Visa and transport charges
                            stay here, even when passengers travel on another
                            voucher.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader
                                ><TableRow>
                                    <TableHead>Name</TableHead
                                    ><TableHead>Passport</TableHead
                                    ><TableHead>Age</TableHead>
                                    <TableHead>Current voucher</TableHead
                                    ><TableHead
                                        v-if="groupCapabilities.can_modify"
                                        >Actions</TableHead
                                    >
                                </TableRow></TableHeader
                            >
                            <TableBody>
                                <TableRow v-if="!passengers.length"
                                    ><TableCell
                                        :colspan="
                                            groupCapabilities.can_modify ? 5 : 4
                                        "
                                        >No passengers added yet.</TableCell
                                    ></TableRow
                                >
                                <TableRow
                                    v-for="passenger in passengers"
                                    :key="passenger.id"
                                >
                                    <TableCell
                                        ><div class="font-medium">
                                            {{ passenger.full_name }}
                                        </div>
                                        <div
                                            v-if="passenger.notes"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ passenger.notes }}
                                        </div></TableCell
                                    >
                                    <TableCell>{{
                                        passenger.passport_number || '—'
                                    }}</TableCell>
                                    <TableCell>{{
                                        passengerAgeText(passenger)
                                    }}</TableCell>
                                    <TableCell>
                                        <template
                                            v-if="
                                                travellingParties.assignments[
                                                    passenger.id
                                                ]
                                            "
                                        >
                                            <Button
                                                v-if="
                                                    travellingParties
                                                        .assignments[
                                                        passenger.id
                                                    ].id
                                                "
                                                variant="link"
                                                class="h-auto p-0"
                                                @click="
                                                    router.get(
                                                        `/${company.slug}/umrah/vouchers/${travellingParties.assignments[passenger.id].id}`,
                                                    )
                                                "
                                                >{{
                                                    travellingParties
                                                        .assignments[
                                                        passenger.id
                                                    ].number
                                                }}</Button
                                            >
                                            <span v-else
                                                >Assigned to a voucher</span
                                            >
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    travellingParties
                                                        .assignments[
                                                        passenger.id
                                                    ].elsewhere
                                                        ? 'Travelling with another group'
                                                        : 'This group’s voucher'
                                                }}
                                                <span
                                                    v-if="
                                                        travellingParties
                                                            .assignments[
                                                            passenger.id
                                                        ].agent
                                                    "
                                                >
                                                    ·
                                                    {{
                                                        travellingParties
                                                            .assignments[
                                                            passenger.id
                                                        ].agent
                                                    }}</span
                                                >
                                                <span
                                                    v-if="
                                                        travellingParties
                                                            .assignments[
                                                            passenger.id
                                                        ].status
                                                    "
                                                >
                                                    ·
                                                    {{
                                                        travellingParties
                                                            .assignments[
                                                            passenger.id
                                                        ].status === 'draft'
                                                            ? 'Draft'
                                                            : 'Approved'
                                                    }}</span
                                                >
                                            </div>
                                        </template>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >Not assigned</span
                                        >
                                    </TableCell>
                                    <TableCell
                                        v-if="groupCapabilities.can_modify"
                                    >
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title="Correct passenger"
                                            @click="openPassenger(passenger)"
                                            ><Pencil class="h-4 w-4"
                                        /></Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title="Remove passenger"
                                            @click="removePassenger(passenger)"
                                            ><Trash2 class="h-4 w-4"
                                        /></Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
                <Card v-if="travellingParties.joining.length" variant="form">
                    <CardHeader>
                        <CardTitle
                            >Joining from other groups ·
                            {{ travellingParties.joining.length }}</CardTitle
                        >
                        <CardDescription
                            >Travelling on this group’s vouchers. Their existing
                            visa and transport purchases remain with their
                            original purchasing agent.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader
                                ><TableRow
                                    ><TableHead>Name</TableHead
                                    ><TableHead>Passport</TableHead
                                    ><TableHead
                                        >Original purchase group</TableHead
                                    ><TableHead
                                        >Current voucher</TableHead
                                    ></TableRow
                                ></TableHeader
                            >
                            <TableBody>
                                <TableRow
                                    v-for="passenger in travellingParties.joining"
                                    :key="passenger.id"
                                >
                                    <TableCell class="font-medium">{{
                                        passenger.name
                                    }}</TableCell>
                                    <TableCell>{{
                                        passenger.passport || '—'
                                    }}</TableCell>
                                    <TableCell>
                                        <Button
                                            v-if="passenger.original_group_id"
                                            variant="link"
                                            class="h-auto p-0"
                                            @click="
                                                router.get(
                                                    `/${company.slug}/umrah/groups/${passenger.original_group_id}`,
                                                )
                                            "
                                            >{{
                                                passenger.original_group
                                            }}</Button
                                        >
                                        <span v-else
                                            >Another purchase group</span
                                        >
                                    </TableCell>
                                    <TableCell
                                        ><Button
                                            variant="link"
                                            class="h-auto p-0"
                                            @click="
                                                router.get(
                                                    `/${company.slug}/umrah/vouchers/${passenger.voucher.id}`,
                                                )
                                            "
                                            >{{
                                                passenger.voucher.number
                                            }}</Button
                                        >
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                passenger.voucher.status ===
                                                'draft'
                                                    ? 'Draft'
                                                    : 'Approved'
                                            }}
                                        </div></TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card v-if="groupCapabilities.can_modify" variant="detail">
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
                                    {{
                                        formatDateTime(payment.payment_date, {
                                            mode: 'date',
                                        })
                                    }}
                                    ·
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
                                        : 'text-status-success'
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
                <Dialog v-model:open="addPassengerOpen">
                    <DialogContent
                        class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                    >
                        <DialogHeader>
                            <DialogTitle>Add Passenger</DialogTitle>
                            <DialogDescription
                                >Add one passenger to {{ group.group_number }}.
                                Group visa and transport totals will be
                                recalculated.</DialogDescription
                            >
                        </DialogHeader>
                        <Card class="border-0 shadow-none" variant="form">
                            <CardContent>
                                <form
                                    novalidate
                                    class="space-y-3"
                                    @submit.prevent="addPassenger"
                                >
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
                                                v-model="
                                                    passengerForm.passport_number
                                                "
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Age</Label
                                            ><Input
                                                v-model="
                                                    passengerForm.imported_age
                                                "
                                                type="number"
                                                min="0"
                                                max="130"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Nationality</Label
                                            ><Input
                                                v-model="
                                                    passengerForm.nationality
                                                "
                                            />
                                        </div>
                                    </div>

                                    <!-- Stated, not asked. Whoever joins this group
                                 takes what the group sells. -->
                                    <div class="space-y-2">
                                        <Label>Service</Label>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ groupService }} — set by the
                                            group.
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Notes</Label
                                        ><Textarea
                                            v-model="passengerForm.notes"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            groupCapabilities.requires_override_reason
                                        "
                                        class="space-y-2"
                                    >
                                        <Label>Override reason</Label>
                                        <Textarea
                                            v-model="
                                                passengerForm.override_reason
                                            "
                                            required
                                        />
                                        <p
                                            v-if="
                                                passengerForm.errors
                                                    .override_reason
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                passengerForm.errors
                                                    .override_reason
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        type="submit"
                                        class="w-full"
                                        :disabled="passengerForm.processing"
                                        ><span
                                            v-if="passengerForm.processing"
                                            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                        /><Plus
                                            v-else
                                            class="mr-2 h-4 w-4"
                                        />Add Passenger</Button
                                    >
                                </form>
                            </CardContent>
                        </Card>
                    </DialogContent>
                </Dialog>

                <Dialog v-model:open="recordPaymentOpen">
                    <DialogContent
                        class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                    >
                        <DialogHeader>
                            <DialogTitle>Record Payment</DialogTitle>
                            <DialogDescription
                                >Record money received from the agent or paid to
                                a vendor for this group.</DialogDescription
                            >
                        </DialogHeader>
                        <Card
                            v-if="groupCapabilities.can_record_payment"
                            class="border-0 shadow-none"
                            variant="form"
                        >
                            <CardContent class="space-y-4 p-0">
                                <div
                                    class="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground"
                                >
                                    {{
                                        paymentForm.direction === 'received'
                                            ? 'Agent balance'
                                            : 'Vendor balance'
                                    }}
                                    after payment:
                                    <MoneyText
                                        :amount="remainingAfterPayment"
                                        :currency="company.base_currency"
                                    />
                                </div>
                                <form
                                    novalidate
                                    class="space-y-3"
                                    @submit.prevent="addPayment"
                                >
                                    <div class="space-y-2">
                                        <Label>Date</Label
                                        ><Input
                                            v-model="paymentForm.payment_date"
                                            type="date"
                                            required
                                        />
                                    </div>
                                    <div
                                        v-if="canViewAccounting"
                                        class="space-y-2"
                                    >
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
                                                    >{{ vendor.name }} ·
                                                    Visa</SelectItem
                                                >
                                                <SelectItem
                                                    v-for="vendor in transportVendors"
                                                    :key="`transport-${vendor.id}`"
                                                    :value="`transport:${vendor.id}`"
                                                    >{{ vendor.name }} ·
                                                    Transport<span
                                                        v-if="
                                                            vendor.is_company_owned
                                                        "
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
                                            <Select
                                                v-model="paymentForm.currency"
                                            >
                                                <SelectTrigger
                                                    ><SelectValue
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="currency in currencies"
                                                        :key="
                                                            currency.currency_code
                                                        "
                                                        :value="
                                                            currency.currency_code
                                                        "
                                                    >
                                                        {{
                                                            currency.currency_code
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p
                                                v-if="
                                                    paymentForm.errors.currency
                                                "
                                                class="text-xs text-destructive"
                                            >
                                                {{
                                                    paymentForm.errors.currency
                                                }}
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
                                            <!-- This message used to sit outside the
                                         field's own column, so a two-column
                                         grid placed it under Currency and it
                                         read as belonging to the wrong field.
                                         An error has to be next to the thing
                                         that caused it. -->
                                            <p
                                                v-if="
                                                    paymentForm.errors.amount ||
                                                    paymentAmountIssue
                                                "
                                                class="text-xs text-destructive"
                                            >
                                                {{
                                                    paymentForm.errors.amount ||
                                                    paymentAmountIssue
                                                }}
                                            </p>
                                        </div>
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
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            1 {{ paymentForm.currency }} =
                                            {{ paymentForm.exchange_rate || 0 }}
                                            {{ company.base_currency }} ·
                                            Converted:
                                            <MoneyText
                                                :amount="paymentBaseAmount"
                                                :currency="
                                                    company.base_currency
                                                "
                                            />
                                        </p>
                                        <p
                                            v-if="
                                                paymentForm.errors.exchange_rate
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                paymentForm.errors.exchange_rate
                                            }}
                                        </p>
                                    </div>
                                    <!-- A disabled control has to say why. Without
                                 this the button simply greyed out on a
                                 condition held off-screen. -->
                                    <p
                                        v-if="recordPaymentBlockedReason"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ recordPaymentBlockedReason }}
                                    </p>
                                    <Button
                                        type="submit"
                                        class="w-full"
                                        :disabled="
                                            paymentForm.processing ||
                                            !canRecordPayment
                                        "
                                        ><span
                                            v-if="paymentForm.processing"
                                            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                        /><WalletCards
                                            v-else
                                            class="mr-2 h-4 w-4"
                                        />Record Payment</Button
                                    >
                                </form>
                            </CardContent>
                        </Card>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
        <Card v-if="changeLogs.length" variant="detail">
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
        <Dialog v-model:open="passengerOpen"
            ><DialogContent class="max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>Correct Passenger</DialogTitle></DialogHeader
                >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Full name</Label
                        ><Input v-model="editPassengerForm.full_name" />
                    </div>
                    <div class="space-y-2">
                        <Label>Passport number</Label
                        ><Input v-model="editPassengerForm.passport_number" />
                    </div>
                    <div class="space-y-2">
                        <Label>Date of birth</Label
                        ><Input
                            v-model="editPassengerForm.date_of_birth"
                            type="date"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Imported age</Label
                        ><Input
                            v-model="editPassengerForm.imported_age"
                            type="number"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Nationality</Label
                        ><Input v-model="editPassengerForm.nationality" />
                    </div>
                    <div class="space-y-2">
                        <Label>Service</Label>
                        <p class="text-sm text-muted-foreground">
                            {{ groupService }} — set by the group.
                        </p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label>Notes</Label
                        ><Textarea v-model="editPassengerForm.notes" />
                    </div>
                    <div
                        v-if="groupCapabilities.requires_override_reason"
                        class="space-y-2 md:col-span-2"
                    >
                        <Label>Reason for post-travel correction</Label
                        ><Textarea
                            v-model="editPassengerForm.override_reason"
                            required
                        />
                    </div>
                </div>
                <DialogFooter
                    ><Button variant="outline" @click="passengerOpen = false"
                        >Cancel</Button
                    ><Button
                        :disabled="editPassengerForm.processing"
                        @click="updatePassenger"
                        >Save Correction</Button
                    ></DialogFooter
                >
            </DialogContent></Dialog
        >
        <Dialog
            :open="removePassengerTarget !== null"
            @update:open="
                (open) => {
                    if (!open) removePassengerTarget = null;
                }
            "
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>Remove Passenger</DialogTitle></DialogHeader
                >
                <p class="text-sm text-muted-foreground">
                    Remove {{ removePassengerTarget?.full_name }} and
                    recalculate visa, transport, and group totals. A passenger
                    on an approved voucher cannot be removed.
                </p>
                <div class="space-y-2">
                    <Label for="remove-reason"
                        >Reason
                        {{
                            groupCapabilities.requires_override_reason
                                ? ''
                                : '(optional)'
                        }}</Label
                    ><Textarea id="remove-reason" v-model="removeForm.reason" />
                    <p
                        v-if="removeForm.errors.reason"
                        class="text-sm text-destructive"
                    >
                        {{ removeForm.errors.reason }}
                    </p>
                </div>
                <DialogFooter
                    ><Button
                        variant="outline"
                        @click="removePassengerTarget = null"
                        >Keep Passenger</Button
                    ><Button
                        variant="destructive"
                        :disabled="
                            removeForm.processing ||
                            (groupCapabilities.requires_override_reason &&
                                removeForm.reason.trim().length < 5)
                        "
                        @click="confirmRemovePassenger"
                        >Remove Passenger</Button
                    ></DialogFooter
                >
            </DialogContent></Dialog
        >
    </PageShell>
</template>
