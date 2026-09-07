<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    BedDouble,
    Bus,
    ClipboardPlus,
    FileSpreadsheet,
    LoaderCircle,
    PackageCheck,
    Plus,
    Save,
    ShieldCheck,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type PassengerRow = {
    row_id: string;
    full_name: string;
    passport_number: string;
    nationality: string;
    date_of_birth: string;
    imported_age: string;
    visa_status: string;
};

type TransportRow = {
    transport_fare_id: string;
    driver_id: null;
    scheduled_at: string;
    terminal: 'standard' | 'hajj';
    quantity: string;
    passenger_count: string;
    notes: string;
};

type ServiceMode =
    | 'visa'
    | 'visa_transport'
    | 'transport'
    | 'hotel'
    | 'visa_hotel'
    | 'transport_hotel'
    | 'complete';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    nextBookingNumber: string;
    agents: Array<{
        id: string;
        name: string;
        agent_number: string;
        country: string;
    }>;
    isAgent: boolean;
    canBuildVoucher: boolean;
    pricing: {
        visa: { adult: number; child: number; source?: string } | null;
        standard_transport: {
            per_passenger: number;
            charge_child_fare: boolean;
            source?: string;
        } | null;
        agent_id?: string | null;
        service_date?: string;
        selected_service_date?: string | null;
    };
    transportFares: Array<{
        id: string;
        name: string;
        charging_basis: 'per_vehicle' | 'per_passenger' | 'flat_group';
        sale_amount: number;
        hajj_terminal_sale_amount: number;
        service: {
            name: string;
            vehicle_type: string | null;
            pax_capacity: number | null;
        } | null;
        route: string | null;
    }>;
    hotels: Array<{ id: string; name: string; city: string }>;
    roomTypes: Record<string, string>;
    countries: Record<string, string>;
    passengerStatuses: Record<string, string>;
    setup: {
        has_visa_rate: boolean;
        has_standard_transport_rate: boolean;
        has_specialized_transport_rate: boolean;
    };
}>();

const page = usePage();
let passengerSequence = 0;
const nextPassengerId = () => `quick-passenger-${++passengerSequence}`;
const randomKey = () =>
    globalThis.crypto?.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });

const emptyPassenger = (nationality = 'Pakistan'): PassengerRow => ({
    row_id: nextPassengerId(),
    full_name: '',
    passport_number: '',
    nationality,
    date_of_birth: '',
    imported_age: '',
    visa_status: 'received',
});

const serviceOptions: Array<{
    value: ServiceMode;
    title: string;
    description: string;
    icon: typeof ShieldCheck;
}> = [
    {
        value: 'visa',
        title: 'Visa only',
        description: 'Visa processing; transport and hotel arranged elsewhere.',
        icon: ShieldCheck,
    },
    {
        value: 'visa_transport',
        title: 'Visa + transport',
        description: 'Visa processing with company-arranged Saudi transport.',
        icon: Bus,
    },
    {
        value: 'transport',
        title: 'Transport only',
        description: 'Saudi movements for travellers who already have visas.',
        icon: Bus,
    },
    {
        value: 'hotel',
        title: 'Hotel only',
        description: 'Accommodation without visa or transport service.',
        icon: BedDouble,
    },
    {
        value: 'visa_hotel',
        title: 'Visa + hotel',
        description: 'Visa and accommodation; transport is self-arranged.',
        icon: BedDouble,
    },
    {
        value: 'transport_hotel',
        title: 'Transport + hotel',
        description: 'Ground service without visa processing.',
        icon: PackageCheck,
    },
    {
        value: 'complete',
        title: 'Complete package',
        description: 'Visa, transport and hotel using current company defaults.',
        icon: PackageCheck,
    },
];

const form = useForm({
    service_mode: 'visa_transport' as ServiceMode,
    next_step: 'group' as 'group' | 'voucher',
    idempotency_key: randomKey(),
    group_number: props.nextBookingNumber,
    name: '',
    agent_id: props.pricing.agent_id || '',
    travel_date: props.pricing.selected_service_date || '',
    passenger_count: '1',
    transport_mode: 'standard_bus',
    hotel_makkah_id: 'none',
    hotel_madinah_id: 'none',
    room_type: 'double',
    makkah_nights: '0',
    madinah_nights: '0',
    notes: '',
    passengers: [emptyPassenger(props.agents[0]?.country)] as PassengerRow[],
    transport_items: [] as TransportRow[],
});

const importForm = useForm<{ mutamers_file: File | null }>({
    mutamers_file: null,
});
const importedSignature = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Quick Booking', href: `/${props.company.slug}/umrah/quick-booking` },
];

const includesVisa = computed(() =>
    ['visa', 'visa_transport', 'visa_hotel', 'complete'].includes(
        form.service_mode,
    ),
);
const includesTransport = computed(() =>
    ['visa_transport', 'transport', 'transport_hotel', 'complete'].includes(
        form.service_mode,
    ),
);
const includesHotel = computed(() =>
    ['hotel', 'visa_hotel', 'transport_hotel', 'complete'].includes(
        form.service_mode,
    ),
);
const selectedAgent = computed(() =>
    props.agents.find((agent) => agent.id === form.agent_id),
);
const namedPassengers = computed(() =>
    form.passengers.filter((passenger) => passenger.full_name.trim() !== ''),
);
const defaultNationality = computed(
    () => selectedAgent.value?.country || 'Pakistan',
);

const normalizeDate = (value: string) => value.slice(0, 10);
const ageBand = (passenger: PassengerRow) => {
    if (passenger.date_of_birth) {
        const birth = new Date(`${normalizeDate(passenger.date_of_birth)}T00:00:00`);
        const reference = form.travel_date
            ? new Date(`${normalizeDate(form.travel_date)}T00:00:00`)
            : new Date();
        let age = reference.getFullYear() - birth.getFullYear();
        const month = reference.getMonth() - birth.getMonth();
        if (
            month < 0 ||
            (month === 0 && reference.getDate() < birth.getDate())
        )
            age -= 1;
        return age < 12 ? 'child' : 'adult';
    }

    return passenger.imported_age !== '' && Number(passenger.imported_age) < 12
        ? 'child'
        : 'adult';
};

const pricingPassengers = computed(() =>
    namedPassengers.value.length
        ? namedPassengers.value
        : Array.from(
              { length: Math.max(Number(form.passenger_count), 1) },
              (): PassengerRow => ({
                  row_id: 'pricing-placeholder',
                  full_name: '',
                  passport_number: '',
                  nationality: defaultNationality.value,
                  date_of_birth: '',
                  imported_age: '',
                  visa_status: 'received',
              }),
          ),
);
const visaTotal = computed(() => {
    if (!includesVisa.value || !props.pricing.visa) return 0;
    return pricingPassengers.value.reduce(
        (total, passenger) => total + props.pricing.visa![ageBand(passenger)],
        0,
    );
});
const standardTransportTotal = computed(() => {
    if (
        !includesTransport.value ||
        form.transport_mode !== 'standard_bus' ||
        !props.pricing.standard_transport
    )
        return 0;

    const count = props.pricing.standard_transport.charge_child_fare
        ? pricingPassengers.value.length
        : pricingPassengers.value.filter(
              (passenger) => ageBand(passenger) === 'adult',
          ).length;
    return count * props.pricing.standard_transport.per_passenger;
});
const fareFor = (id: string) =>
    props.transportFares.find((fare) => fare.id === id);
const specializedTransportTotal = computed(() =>
    form.transport_items.reduce((total, item) => {
        const fare = fareFor(item.transport_fare_id);
        if (!fare) return total;
        const factor =
            fare.charging_basis === 'per_passenger'
                ? Math.max(Number(item.passenger_count || form.passenger_count), 1)
                : fare.charging_basis === 'flat_group'
                  ? 1
                  : Math.max(Number(item.quantity), 1);
        const surcharge =
            item.terminal === 'hajj' ? fare.hajj_terminal_sale_amount : 0;
        return total + (fare.sale_amount + surcharge) * factor;
    }, 0),
);
const transportTotal = computed(() =>
    form.transport_mode === 'standard_bus'
        ? standardTransportTotal.value
        : specializedTransportTotal.value,
);
const sellingTotal = computed(() => visaTotal.value + transportTotal.value);

const setupProblem = computed(() => {
    if (includesVisa.value && !props.setup.has_visa_rate)
        return 'A default visa rate must be configured first.';
    if (
        includesTransport.value &&
        form.transport_mode === 'standard_bus' &&
        !props.setup.has_standard_transport_rate
    )
        return 'A standard transport provider and rate must be configured first.';
    if (
        includesTransport.value &&
        form.transport_mode === 'specialized' &&
        !props.setup.has_specialized_transport_rate
    )
        return 'A specialized transport fare must be configured first.';
    return null;
});

watch(
    () => form.service_mode,
    () => {
        form.clearErrors('service_mode', 'transport_mode');
        if (!includesTransport.value) {
            form.transport_mode = 'none';
            form.transport_items = [];
        } else if (form.transport_mode === 'none') {
            form.transport_mode = 'standard_bus';
        }
    },
);

watch(
    () => form.agent_id,
    () => form.clearErrors('agent_id'),
);

const defaultQuoteDate = props.pricing.service_date || new Date().toISOString().slice(0, 10);
let quoteTimer: ReturnType<typeof setTimeout> | null = null;
watch(
    [() => form.agent_id, () => form.travel_date],
    ([agentId, travelDate]) => {
        if (!agentId) return;
        const serviceDate = travelDate || defaultQuoteDate;
        if (props.pricing.agent_id === agentId && props.pricing.service_date === serviceDate) return;
        if (quoteTimer) clearTimeout(quoteTimer);
        quoteTimer = setTimeout(() => {
            router.reload({
                data: { agent_id: agentId, travel_date: travelDate || undefined },
                only: ['pricing', 'transportFares'],
                preserveState: true,
                preserveScroll: true,
            });
        }, 250);
    },
);

watch(
    () => form.transport_mode,
    (mode) => {
        if (mode === 'specialized' && form.transport_items.length === 0) {
            form.transport_items.push({
                transport_fare_id: props.transportFares[0]?.id || 'none',
                driver_id: null,
                scheduled_at: '',
                terminal: 'standard',
                quantity: '1',
                passenger_count: form.passenger_count,
                notes: '',
            });
        }
        if (mode !== 'specialized') form.transport_items = [];
    },
);

watch(defaultNationality, (nationality, previous) => {
    form.passengers.forEach((passenger) => {
        if (!passenger.nationality || passenger.nationality === previous)
            passenger.nationality = nationality;
    });
});

watch(
    namedPassengers,
    (passengers) => {
        if (passengers.length > Number(form.passenger_count))
            form.passenger_count = String(passengers.length);
        form.transport_items.forEach(
            (item) => (item.passenger_count = form.passenger_count),
        );
    },
    { deep: true },
);

const addPassenger = () =>
    form.passengers.push(emptyPassenger(defaultNationality.value));
const removePassenger = (index: number) => {
    if (form.passengers.length === 1) {
        form.passengers[0] = emptyPassenger(defaultNationality.value);
        return;
    }
    form.passengers.splice(index, 1);
};
const addTransport = () =>
    form.transport_items.push({
        transport_fare_id: 'none',
        driver_id: null,
        scheduled_at: '',
        terminal: 'standard',
        quantity: '1',
        passenger_count: form.passenger_count,
        notes: '',
    });
const removeTransport = (index: number) => form.transport_items.splice(index, 1);

const importedMutamers = computed(
    () => ((page.props.flash as any)?.umrahImportedMutamers || []) as any[],
);
watch(
    importedMutamers,
    (rows) => {
        if (!rows.length) return;
        const signature = JSON.stringify(rows);
        if (signature === importedSignature.value) return;
        importedSignature.value = signature;
        if (form.passengers.length === 1 && !form.passengers[0].full_name)
            form.passengers = [];
        rows.forEach((row) =>
            form.passengers.push({
                row_id: nextPassengerId(),
                full_name: String(row.full_name || ''),
                passport_number: String(row.passport_number || ''),
                nationality: row.nationality || defaultNationality.value,
                date_of_birth: '',
                imported_age:
                    row.imported_age === null || row.imported_age === undefined
                        ? ''
                        : String(row.imported_age),
                visa_status: row.visa_status || 'received',
            }),
        );
        form.passenger_count = String(namedPassengers.value.length);
    },
    { immediate: true },
);

const handleImportFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    importForm.mutamers_file = target.files?.[0] || null;
};
const importPassengers = () => {
    if (!importForm.mutamers_file) return;
    importForm.post(`/${props.company.slug}/umrah/groups/import-mutamers`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset(),
        onError: (errors) =>
            toast.error(
                Object.values(errors)[0] || 'Passenger file could not be imported.',
            ),
    });
};

const submit = (nextStep: 'group' | 'voucher') => {
    form.next_step = nextStep;
    form.transform((data) => ({
        ...data,
        passenger_count: Number(data.passenger_count),
        hotel_makkah_id:
            data.hotel_makkah_id === 'none' ? null : data.hotel_makkah_id,
        hotel_madinah_id:
            data.hotel_madinah_id === 'none' ? null : data.hotel_madinah_id,
        room_type: data.room_type === 'none' ? null : data.room_type,
        makkah_nights: Number(data.makkah_nights || 0),
        madinah_nights: Number(data.madinah_nights || 0),
        passengers: data.passengers.map((passenger) => ({
            full_name: passenger.full_name,
            passport_number: passenger.passport_number,
            nationality: passenger.nationality,
            date_of_birth: passenger.date_of_birth,
            imported_age:
                passenger.imported_age === ''
                    ? null
                    : Number(passenger.imported_age),
            visa_status: passenger.visa_status,
        })),
        transport_items: data.transport_items.map((item) => ({
            ...item,
            transport_fare_id:
                item.transport_fare_id === 'none'
                    ? null
                    : item.transport_fare_id,
            quantity: Number(item.quantity || 1),
            passenger_count: Number(
                item.passenger_count || data.passenger_count,
            ),
        })),
    }));
    form.post(`/${props.company.slug}/umrah/quick-booking`, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(
                Object.values(errors)[0] || 'Please review the booking details.',
            ),
    });
};
</script>

<template>
    <Head title="Quick Booking" />
    <PageShell
        title="Quick Booking"
        description="Record what the customer bought, add the travellers, and continue. Haasib handles the accounting setup."
        :breadcrumbs="breadcrumbs"
        :icon="ClipboardPlus"
        compact
    >
        <form class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_300px]" @submit.prevent>
            <div class="min-w-0 space-y-4">
                <Card variant="form">
                    <CardHeader class="pb-3">
                        <CardTitle>What are you selling?</CardTitle>
                        <CardDescription>Only the fields needed for this service will appear.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <Button
                                v-for="option in serviceOptions"
                                :key="option.value"
                                type="button"
                                :variant="form.service_mode === option.value ? 'default' : 'outline'"
                                class="h-auto min-h-20 justify-start whitespace-normal px-3 py-3 text-left"
                                @click="form.service_mode = option.value"
                            >
                                <component :is="option.icon" class="mr-3 h-5 w-5 shrink-0" />
                                <span>
                                    <span class="block font-semibold">{{ option.title }}</span>
                                    <span class="mt-0.5 block text-xs opacity-75">{{ option.description }}</span>
                                </span>
                            </Button>
                        </div>
                        <p v-if="form.errors.service_mode" class="mt-2 text-xs text-destructive">
                            {{ form.errors.service_mode }}
                        </p>
                    </CardContent>
                </Card>

                <Card variant="form">
                    <CardHeader class="pb-3">
                        <CardTitle>Booking details</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Agent / customer</Label>
                            <Select v-model="form.agent_id" :disabled="isAgent">
                                <SelectTrigger><SelectValue placeholder="Select agent" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="agent in agents" :key="agent.id" :value="agent.id">
                                        {{ agent.name }} · {{ agent.agent_number }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.agent_id" class="text-xs text-destructive">{{ form.errors.agent_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Booking number</Label>
                            <Input v-model="form.group_number" />
                            <p v-if="form.errors.group_number" class="text-xs text-destructive">{{ form.errors.group_number }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Booking name <span class="text-muted-foreground">(optional)</span></Label>
                            <Input v-model="form.name" placeholder="Generated from agent and passengers if blank" />
                        </div>
                        <div class="space-y-2">
                            <Label>Expected travel date</Label>
                            <Input v-model="form.travel_date" type="date" />
                            <p v-if="form.errors.travel_date" class="text-xs text-destructive">{{ form.errors.travel_date }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="includesTransport" variant="form">
                    <CardHeader class="pb-3">
                        <CardTitle>Transport</CardTitle>
                        <CardDescription>Choose the normal per-passenger bus or specific transport services.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="max-w-md space-y-2">
                            <Label>Transport arrangement</Label>
                            <Select v-model="form.transport_mode">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="standard_bus">Standard transport</SelectItem>
                                    <SelectItem value="specialized">Specific vehicle or route</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.transport_mode" class="text-xs text-destructive">{{ form.errors.transport_mode }}</p>
                        </div>

                        <div v-if="form.transport_mode === 'specialized'" class="space-y-3">
                            <div
                                v-for="(item, index) in form.transport_items"
                                :key="index"
                                class="grid gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_130px_100px_150px_40px] xl:items-end"
                            >
                                <div class="space-y-2">
                                    <Label>Route / service</Label>
                                    <Select v-model="item.transport_fare_id">
                                        <SelectTrigger><SelectValue placeholder="Select fare" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="fare in transportFares" :key="fare.id" :value="fare.id">
                                                {{ fare.name }}<span v-if="fare.route"> · {{ fare.route }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Terminal</Label>
                                    <Select v-model="item.terminal">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="standard">Standard</SelectItem>
                                            <SelectItem value="hajj">Hajj terminal</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Quantity</Label>
                                    <Input v-model="item.quantity" type="number" min="1" max="100" />
                                </div>
                                <div class="space-y-2">
                                    <Label>Pickup time <span class="text-muted-foreground">(optional)</span></Label>
                                    <Input v-model="item.scheduled_at" type="datetime-local" />
                                </div>
                                <Button type="button" size="icon" variant="ghost" title="Remove transport" @click="removeTransport(index)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="addTransport">
                                <Plus class="mr-2 h-4 w-4" /> Add transport
                            </Button>
                            <p v-if="form.errors.transport_items" class="text-xs text-destructive">{{ form.errors.transport_items }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="includesHotel" variant="form">
                    <CardHeader class="pb-3">
                        <CardTitle>Hotel preferences</CardTitle>
                        <CardDescription>Optional shortcuts. Exact dates, rooms and pricing are confirmed in the voucher.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Makkah hotel</Label>
                            <Select v-model="form.hotel_makkah_id">
                                <SelectTrigger><SelectValue placeholder="Choose later" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Choose later</SelectItem>
                                    <SelectItem v-for="hotel in hotels.filter((item) => item.city === 'Makkah')" :key="hotel.id" :value="hotel.id">{{ hotel.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Makkah nights</Label>
                            <Input v-model="form.makkah_nights" type="number" min="0" max="90" />
                        </div>
                        <div class="space-y-2">
                            <Label>Madinah hotel</Label>
                            <Select v-model="form.hotel_madinah_id">
                                <SelectTrigger><SelectValue placeholder="Choose later" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Choose later</SelectItem>
                                    <SelectItem v-for="hotel in hotels.filter((item) => item.city === 'Madinah')" :key="hotel.id" :value="hotel.id">{{ hotel.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Madinah nights</Label>
                            <Input v-model="form.madinah_nights" type="number" min="0" max="90" />
                        </div>
                        <div class="space-y-2">
                            <Label>Room basis</Label>
                            <Select v-model="form.room_type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Choose later</SelectItem>
                                    <SelectItem v-for="(label, value) in roomTypes" :key="value" :value="String(value)">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card variant="form">
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <CardTitle>Passengers</CardTitle>
                                <CardDescription>Enter names now or use the existing Mutamer spreadsheet importer.</CardDescription>
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="w-24 space-y-1">
                                    <Label>Count</Label>
                                    <Input v-model="form.passenger_count" type="number" min="1" max="500" />
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="addPassenger">
                                    <Plus class="mr-2 h-4 w-4" /> Add person
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-2 rounded-md border border-dashed p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <div class="space-y-2">
                                <Label>Import passenger spreadsheet</Label>
                                <Input type="file" accept=".xlsx,.xls,.csv" @change="handleImportFile" />
                                <p v-if="importForm.errors.mutamers_file" class="text-xs text-destructive">{{ importForm.errors.mutamers_file }}</p>
                            </div>
                            <Button type="button" variant="outline" :disabled="!importForm.mutamers_file || importForm.processing" @click="importPassengers">
                                <LoaderCircle v-if="importForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                <Upload v-else class="mr-2 h-4 w-4" /> Import
                            </Button>
                        </div>

                        <div
                            v-for="(passenger, index) in form.passengers"
                            :key="passenger.row_id"
                            class="grid min-w-0 gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_150px_120px_150px_40px] xl:items-end"
                        >
                            <div class="space-y-2">
                                <Label>Full name</Label>
                                <Input v-model="passenger.full_name" :placeholder="`Passenger ${index + 1}`" />
                                <p v-if="form.errors[`passengers.${index}.full_name`]" class="text-xs text-destructive">{{ form.errors[`passengers.${index}.full_name`] }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Passport</Label>
                                <Input v-model="passenger.passport_number" />
                            </div>
                            <div class="space-y-2">
                                <Label>Age</Label>
                                <Input v-model="passenger.imported_age" type="number" min="0" max="130" />
                            </div>
                            <div class="space-y-2">
                                <Label>Nationality</Label>
                                <Select v-model="passenger.nationality">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="(label, value) in countries" :key="value" :value="String(value)">{{ label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="button" size="icon" variant="ghost" title="Remove passenger" @click="removePassenger(index)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <p v-if="form.errors.passengers" class="text-xs text-destructive">{{ form.errors.passengers }}</p>
                    </CardContent>
                </Card>

                <Card variant="form">
                    <CardHeader class="pb-3"><CardTitle>Internal note</CardTitle></CardHeader>
                    <CardContent>
                        <Textarea v-model="form.notes" placeholder="Optional handover or booking note" />
                    </CardContent>
                </Card>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
                <Card variant="figure">
                    <CardHeader class="pb-3">
                        <CardTitle>Booking summary</CardTitle>
                        <CardDescription>{{ serviceOptions.find((item) => item.value === form.service_mode)?.title }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4"><span class="text-muted-foreground">Passengers</span><span class="font-medium">{{ Math.max(Number(form.passenger_count), namedPassengers.length) }}</span></div>
                        <div v-if="includesVisa" class="flex items-center justify-between gap-4"><span class="text-muted-foreground">Visa</span><MoneyText :amount="visaTotal" :currency="company.base_currency" /></div>
                        <div v-if="includesTransport" class="flex items-center justify-between gap-4"><span class="text-muted-foreground">Transport</span><MoneyText :amount="transportTotal" :currency="company.base_currency" /></div>
                        <div v-if="includesHotel" class="flex items-center justify-between gap-4"><span class="text-muted-foreground">Hotel</span><span>Priced on voucher</span></div>
                        <div class="border-t pt-3">
                            <div class="flex items-center justify-between gap-4 font-semibold"><span>Current selling total</span><MoneyText :amount="sellingTotal" :currency="company.base_currency" /></div>
                            <p v-if="includesHotel" class="mt-1 text-xs text-muted-foreground">Hotel is added after rooms and dates are confirmed.</p>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="setupProblem" class="rounded-md border border-status-attention/40 bg-status-attention/10 p-3 text-sm text-status-attention">
                    {{ setupProblem }}
                </div>

                <div class="grid gap-2">
                    <Button type="button" :disabled="form.processing || !!setupProblem" @click="submit('group')">
                        <LoaderCircle v-if="form.processing && form.next_step === 'group'" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" /> Save booking
                    </Button>
                    <Button v-if="canBuildVoucher" type="button" variant="outline" :disabled="form.processing || !!setupProblem" @click="submit('voucher')">
                        <LoaderCircle v-if="form.processing && form.next_step === 'voucher'" class="mr-2 h-4 w-4 animate-spin" />
                        <ClipboardPlus v-else class="mr-2 h-4 w-4" /> Save &amp; build voucher
                    </Button>
                    <Button type="button" variant="ghost" @click="router.get(`/${company.slug}/umrah/groups`)">Cancel</Button>
                </div>

                <div class="rounded-md border p-3 text-xs text-muted-foreground">
                    <div class="flex gap-2">
                        <FileSpreadsheet class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Supplier costs and journals are resolved on the server. They are not editable here.</p>
                    </div>
                </div>
            </aside>
        </form>
    </PageShell>
</template>
