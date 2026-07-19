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
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    ArrowRightLeft,
    Calculator,
    Download,
    FilePenLine,
    Pencil,
    Plane,
    Printer,
    Scissors,
    ScrollText,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: {
        slug: string;
        name: string;
        base_currency: string;
        logo_url?: string | null;
        helpline?: string | null;
    };
    voucher: any;
    statuses: Record<string, string>;
    serviceBundles: Record<string, string>;
    airlines: Record<string, string>;
    airportCities: Record<string, string>;
    agentCapabilities: {
        can_create: boolean;
        can_approve: boolean;
        can_edit: boolean;
        cutoff_hours: number | null;
        has_started?: boolean;
        requires_override_reason?: boolean;
        can_cancel?: boolean;
        can_amend?: boolean;
        can_delete?: boolean;
    };
    changeLogs: any[];
    moveTargets: Array<{
        id: string;
        voucher_number: string;
        title: string;
        passengers_count: number;
    }>;
    canViewAccounting: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
    {
        title: props.voucher.voucher_number,
        href: `/${props.company.slug}/umrah/vouchers/${props.voucher.id}`,
    },
];
const approveForm = useForm({ override_reason: '' });
const moveOpen = ref(false);
const separateOpen = ref(false);
const workflowOpen = ref<'amend' | 'cancel' | 'delete' | null>(null);
const workflowForm = useForm({ reason: '' });
const moveForm = useForm({
    passenger_ids: [] as string[],
    target_voucher_id: '',
    override_reason: '',
});
const separateForm = useForm({
    passenger_ids: [] as string[],
    override_reason: '',
});
const canViewAccounting = computed(() => props.canViewAccounting);
const canApprove = computed(() => props.agentCapabilities.can_approve);
const canEdit = computed(() => props.agentCapabilities.can_edit);
const canReassignPassengers = computed(
    () =>
        props.voucher.status === 'draft' &&
        canEdit.value &&
        (props.voucher.passengers?.length || 0) > 1,
);
const includesTransport = computed(() =>
    [
        'visa_transport',
        'visa_transport_hotel',
        'transport',
        'transport_hotel',
    ].includes(props.voucher.service_bundle),
);
const approve = () =>
    approveForm.post(
        `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/approve`,
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Voucher approved successfully'),
            onError: () => toast.error('Failed to approve voucher'),
        },
    );

const togglePassenger = (
    selected: string[],
    passengerId: string,
    checked: boolean | 'indeterminate',
) => {
    const next = new Set(selected);
    if (checked === true) next.add(passengerId);
    else next.delete(passengerId);
    return [...next];
};

const submitMove = () => {
    moveForm.post(
        `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/passengers/move`,
        {
            preserveScroll: true,
            onSuccess: () => {
                moveOpen.value = false;
                moveForm.reset();
                toast.success('Passengers moved successfully');
            },
            onError: () => toast.error('Failed to move passengers'),
        },
    );
};

const submitSeparation = () => {
    separateForm.post(
        `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/passengers/separate`,
        {
            onSuccess: () => {
                separateOpen.value = false;
                separateForm.reset();
                toast.success('Individual vouchers created');
            },
            onError: () => toast.error('Failed to separate vouchers'),
        },
    );
};

const submitWorkflow = () => {
    if (!workflowOpen.value) return;
    const action = workflowOpen.value;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            workflowOpen.value = null;
            workflowForm.reset();
            toast.success(action === 'cancel' ? 'Voucher cancelled' : action === 'delete' ? 'Draft voucher deleted' : 'Draft amendment created');
        },
        onError: () => toast.error(`Failed to ${action} voucher`),
    };
    const url = `/${props.company.slug}/umrah/vouchers/${props.voucher.id}`;
    if (action === 'delete') workflowForm.delete(url, options);
    else workflowForm.post(`${url}/${action}`, options);
};

const escapeHtml = (value: unknown) =>
    String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

const formatDateTime = (value: unknown) => {
    if (!value) return '';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return String(value);

    return date.toLocaleString();
};
const formatDate = (value: unknown) => {
    if (!value) return '';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime())
        ? String(value)
        : date.toLocaleDateString();
};
const roomBeds = (stay: any) =>
    Number(
        stay.beds_per_room ||
            (
                {
                    sharing: 1,
                    double: 2,
                    triple: 3,
                    quad: 4,
                    quint: 5,
                } as Record<string, number>
            )[stay.room_type] ||
            0,
    );

const voucherHtml = () => {
    const passengerRows = (props.voucher.passengers || [])
        .map(
            (passenger: any, index: number) => `
    <tr>
      <td>${index + 1}</td>
      <td>${escapeHtml(passenger.full_name)}</td>
      <td>${escapeHtml(passenger.passport_number || 'No passport')}</td>
      <td>${escapeHtml(passenger.nationality || '')}</td>
      <td>${escapeHtml(passenger.date_of_birth || (passenger.imported_age !== null ? `Age ${passenger.imported_age}` : ''))}</td>
      <td>${escapeHtml(passenger.visa_status || '')}</td>
    </tr>
  `,
        )
        .join('');

    const hotelRows = (props.voucher.hotel_stays || [])
        .map(
            (stay: any) => `
    <tr>
      <td>${escapeHtml(stay.hotel_name)}</td>
      <td>${escapeHtml(stay.city || '')}</td>
      <td>${escapeHtml(`${stay.room_count || 1} ${stay.room_type || ''} (${roomBeds(stay)} beds each)`)}</td>
      <td>${escapeHtml(formatDate(stay.check_in_date))}</td>
      <td>${escapeHtml(formatDate(stay.check_out_date))}</td>
      <td>${escapeHtml(stay.notes || '')}</td>
    </tr>
  `,
        )
        .join('');
    const hotelSection = `
  <h2>Hotel Stays</h2>
  <table>
    <thead><tr><th>Hotel</th><th>City</th><th>Room</th><th>Check-in</th><th>Checkout</th><th>Notes</th></tr></thead>
    <tbody>${hotelRows || '<tr><td colspan="6">No hotel stays added.</td></tr>'}</tbody>
  </table>`;
    const transportRows = (props.voucher.group?.transport_items || [])
        .map(
            (item: any) => `
    <tr>
      <td>${escapeHtml(item.sector?.name || item.description || 'Transport')}</td>
      <td>${escapeHtml(item.service?.name || item.service?.vehicle_type || '')}</td>
      <td>${escapeHtml(formatDateTime(item.scheduled_at))}</td>
      <td>${escapeHtml(item.driver?.name || item.service?.driver_name || '')}</td>
      <td>${escapeHtml(item.driver?.phone || item.service?.driver_contact || '')}</td>
    </tr>
  `,
        )
        .join('');
    const transportSection = includesTransport.value
        ? `
  <h2>Transport</h2>
  <table>
    <thead><tr><th>Sector</th><th>Vehicle</th><th>Schedule</th><th>Driver</th><th>Contact</th></tr></thead>
    <tbody>${transportRows || `<tr><td colspan="5">${escapeHtml(props.voucher.group?.transport_mode === 'specialized' ? 'Specialized transport' : 'Standard bus transport')}</td></tr>`}</tbody>
  </table>`
        : '';

    const flightSection =
        props.voucher.service_bundle === 'hotel'
            ? ''
            : `
  <h2>Flights</h2>
  <div class="grid">
    <div class="box">
      <strong>Onward</strong><br>
      ${escapeHtml(props.voucher.onward_airline)} · ${escapeHtml(props.airlines[props.voucher.onward_airline] || '')} ${escapeHtml(props.voucher.onward_flight_number || '')}<br>
      ${escapeHtml(props.voucher.onward_departure_city)} · ${escapeHtml(props.airportCities[props.voucher.onward_departure_city] || '')}
      to ${escapeHtml(props.voucher.onward_arrival_city)} · ${escapeHtml(props.airportCities[props.voucher.onward_arrival_city] || '')}<br>
      Depart ${escapeHtml(formatDateTime(props.voucher.onward_departure_at))}<br>
      Arrive ${escapeHtml(formatDateTime(props.voucher.onward_arrival_at))}
    </div>
    <div class="box">
      <strong>Return</strong><br>
      ${escapeHtml(props.voucher.return_airline)} · ${escapeHtml(props.airlines[props.voucher.return_airline] || '')} ${escapeHtml(props.voucher.return_flight_number || '')}<br>
      ${escapeHtml(props.voucher.return_departure_city)} · ${escapeHtml(props.airportCities[props.voucher.return_departure_city] || '')}
      to ${escapeHtml(props.voucher.return_arrival_city)} · ${escapeHtml(props.airportCities[props.voucher.return_arrival_city] || '')}<br>
      Depart ${escapeHtml(formatDateTime(props.voucher.return_departure_at))}<br>
      Arrive ${escapeHtml(formatDateTime(props.voucher.return_arrival_at))}
    </div>
  </div>`;

    return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(props.voucher.voucher_number)} - Voucher</title>
  <style>
    body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
    h1, h2 { margin: 0; }
    h1 { font-size: 24px; }
    h2 { border-bottom: 1px solid #d1d5db; font-size: 15px; margin-top: 28px; padding-bottom: 6px; }
    .muted { color: #6b7280; }
    .grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 18px; }
    .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; }
    .label { color: #6b7280; font-size: 12px; margin-bottom: 4px; }
    table { border-collapse: collapse; margin-top: 10px; width: 100%; }
    th, td { border: 1px solid #d1d5db; font-size: 12px; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; }
    @media print { body { margin: 18mm; } }
  </style>
</head>
<body>
  <div style="text-align:center;margin-bottom:18px">
    ${props.company.logo_url ? `<img src="${escapeHtml(props.company.logo_url)}" alt="Company logo" style="display:block;height:60px;max-width:180px;object-fit:contain;margin:0 auto 6px">` : ''}
    <strong style="display:block;font-size:18px">${escapeHtml(props.company.name)}</strong>
    ${props.company.helpline ? `<span class="muted">Helpline: ${escapeHtml(props.company.helpline)}</span>` : ''}
  </div>
  <h1>${escapeHtml(props.voucher.title)}</h1>
  <div class="muted">${escapeHtml(props.voucher.voucher_number)} · ${escapeHtml(props.statuses[props.voucher.status] || props.voucher.status)} · ${escapeHtml(props.serviceBundles[props.voucher.service_bundle] || props.voucher.service_bundle)}</div>

  <div class="grid">
    <div class="box"><div class="label">Group</div>${escapeHtml(props.voucher.group?.group_number)} · ${escapeHtml(props.voucher.group?.name)}</div>
    <div class="box"><div class="label">Agent</div>${escapeHtml(props.voucher.agent?.name || '')}</div>
    <div class="box"><div class="label">Passengers</div>${(props.voucher.passengers || []).length}</div>
    <div class="box"><div class="label">Created By</div>${escapeHtml(props.voucher.created_by?.name || 'System')}</div>
  </div>

  ${flightSection}

  ${hotelSection}

  ${transportSection}

  <h2>Passengers</h2>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Passport</th><th>Nationality</th><th>DOB / Age</th><th>Status</th></tr></thead>
    <tbody>${passengerRows}</tbody>
  </table>
</body>
</html>`;
};

const printVoucher = () => {
    const printWindow = window.open(
        '',
        '_blank',
        'noopener,noreferrer,width=900,height=700',
    );
    if (!printWindow) return;

    printWindow.document.open();
    printWindow.document.write(voucherHtml());
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
};

const exportVoucher = () => {
    window.location.assign(
        `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/pdf`,
    );
};
</script>

<template>
    <Head :title="voucher.voucher_number" />
    <PageShell
        :title="`${voucher.voucher_number} · ${voucher.title}`"
        :description="`${voucher.agent?.name || 'No agent'} · ${voucher.passengers?.length || 0} passengers`"
        :breadcrumbs="breadcrumbs"
        :icon="ScrollText"
    >
        <template #actions>
            <Button
                v-if="canViewAccounting"
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}/accounting`)"
            >
                <Calculator class="mr-2 h-4 w-4" />Accounting
            </Button>
            <Button variant="outline" @click="printVoucher">
                <Printer class="mr-2 h-4 w-4" />
                Print
            </Button>
            <Button variant="outline" @click="exportVoucher">
                <Download class="mr-2 h-4 w-4" />
                Export PDF
            </Button>
            <Button
                v-if="voucher.status === 'draft' && canEdit"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/vouchers/${voucher.id}/edit`,
                    )
                "
            >
                <Pencil class="mr-2 h-4 w-4" />
                Edit
            </Button>
            <Button v-if="agentCapabilities.can_amend" variant="outline" @click="workflowOpen = 'amend'">
                <FilePenLine class="mr-2 h-4 w-4" />Amend
            </Button>
            <Button v-if="agentCapabilities.can_delete" variant="outline" @click="workflowOpen = 'delete'">
                <Trash2 class="mr-2 h-4 w-4" />Delete Draft
            </Button>
            <Button v-if="agentCapabilities.can_cancel" variant="destructive" @click="workflowOpen = 'cancel'">
                <XCircle class="mr-2 h-4 w-4" />Cancel Voucher
            </Button>
            <Button
                v-if="canReassignPassengers && moveTargets.length"
                variant="outline"
                @click="moveOpen = true"
            >
                <ArrowRightLeft class="mr-2 h-4 w-4" />
                Move Passengers
            </Button>
            <Button
                v-if="canReassignPassengers"
                variant="outline"
                @click="separateOpen = true"
            >
                <Scissors class="mr-2 h-4 w-4" />
                Separate Vouchers
            </Button>
            <Button
                v-if="voucher.status === 'draft' && canApprove"
                :disabled="
                    approveForm.processing ||
                    (agentCapabilities.requires_override_reason &&
                        approveForm.override_reason.trim().length < 5)
                "
                @click="approve"
                >Approve Voucher</Button
            >
            <Button
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/groups/${voucher.group.id}`,
                    )
                "
            >
                <Plane class="mr-2 h-4 w-4" />
                Open Group
            </Button>
        </template>

        <div class="mb-6 flex flex-col items-center text-center">
            <img
                v-if="company.logo_url"
                :src="company.logo_url"
                :alt="`${company.name} logo`"
                class="mb-2 max-h-20 max-w-48 object-contain"
            />
            <div class="text-xl font-semibold">{{ company.name }}</div>
            <div v-if="company.helpline" class="text-sm text-muted-foreground">
                Helpline: {{ company.helpline }}
            </div>
        </div>

        <div
            v-if="voucher.source_voucher"
            class="mb-4 rounded-md border px-4 py-3 text-sm"
        >
            Separated from voucher
            <span class="font-medium">
                {{ voucher.source_voucher.voucher_number }}
            </span>
        </div>

        <div v-if="voucher.amended_voucher || voucher.superseded_by_voucher || voucher.cancelled_at" class="mb-4 rounded-md border px-4 py-3 text-sm">
            <span v-if="voucher.amended_voucher">Version {{ voucher.version_number }} amends {{ voucher.amended_voucher.voucher_number }}.</span>
            <span v-if="voucher.superseded_by_voucher"> Superseded by {{ voucher.superseded_by_voucher.voucher_number }}.</span>
            <span v-if="voucher.cancelled_at"> Cancelled: {{ voucher.cancellation_reason }}</span>
        </div>

        <div
            v-if="
                voucher.status === 'draft' &&
                canApprove &&
                agentCapabilities.requires_override_reason
            "
            class="mb-4 ml-auto max-w-xl space-y-2"
        >
            <label class="text-sm font-medium"
                >Reason for approving after travel started</label
            >
            <Textarea v-model="approveForm.override_reason" required />
            <p
                v-if="approveForm.errors.override_reason"
                class="text-xs text-destructive"
            >
                {{ approveForm.errors.override_reason }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card
                ><CardHeader><CardTitle>Status</CardTitle></CardHeader
                ><CardContent class="flex flex-wrap gap-2"
                    ><Badge variant="secondary">{{
                        statuses[voucher.status] || voucher.status
                    }}</Badge
                    ><Badge variant="outline">{{
                        serviceBundles[voucher.service_bundle] ||
                        voucher.service_bundle
                    }}</Badge></CardContent
                ></Card
            >
            <Card
                ><CardHeader><CardTitle>Group</CardTitle></CardHeader
                ><CardContent class="font-medium"
                    >{{ voucher.group?.group_number }} ·
                    {{ voucher.group?.name }}</CardContent
                ></Card
            >
            <Card
                ><CardHeader><CardTitle>Agent</CardTitle></CardHeader
                ><CardContent class="font-medium">{{
                    voucher.agent?.name || 'No agent'
                }}</CardContent></Card
            >
            <Card
                ><CardHeader><CardTitle>Created By</CardTitle></CardHeader
                ><CardContent class="font-medium">{{
                    voucher.created_by?.name || 'System'
                }}</CardContent></Card
            >
        </div>

        <Card v-if="changeLogs.length" class="mt-6">
            <CardHeader
                ><CardTitle>Change History</CardTitle
                ><CardDescription
                    >Company overrides and voucher changes.</CardDescription
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

        <Dialog :open="workflowOpen !== null" @update:open="(open) => { if (!open) workflowOpen = null; }">
            <DialogContent>
                <DialogHeader><DialogTitle>{{ workflowOpen === 'cancel' ? 'Cancel Voucher' : workflowOpen === 'delete' ? 'Delete Draft Voucher' : 'Create Voucher Amendment' }}</DialogTitle></DialogHeader>
                <div class="space-y-2">
                    <Label for="workflow-reason">Reason {{ workflowOpen === 'cancel' ? '' : '(optional before travel)' }}</Label>
                    <Textarea id="workflow-reason" v-model="workflowForm.reason" />
                    <p v-if="workflowForm.errors.reason" class="text-sm text-destructive">{{ workflowForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="workflowOpen = null">Keep Voucher</Button>
                    <Button :variant="workflowOpen === 'cancel' || workflowOpen === 'delete' ? 'destructive' : 'default'" :disabled="workflowForm.processing || (workflowOpen === 'cancel' && workflowForm.reason.trim().length < 5)" @click="submitWorkflow">
                        {{ workflowOpen === 'cancel' ? 'Cancel Voucher' : workflowOpen === 'delete' ? 'Delete Draft' : 'Create Amendment' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-if="voucher.service_bundle !== 'hotel'">
                <CardHeader>
                    <CardTitle>Flights</CardTitle>
                    <CardDescription
                        >Onward and return ticket details.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="rounded-md border p-3">
                        <div class="font-medium">
                            Onward · {{ voucher.onward_airline }} ·
                            {{ airlines[voucher.onward_airline] || 'Airline' }}
                            {{ voucher.onward_flight_number || '' }}
                        </div>
                        <div class="text-sm font-medium">
                            {{ voucher.onward_departure_city }} ·
                            {{
                                airportCities[voucher.onward_departure_city] ||
                                'Departure city not set'
                            }}
                            →
                            {{ voucher.onward_arrival_city }} ·
                            {{
                                airportCities[voucher.onward_arrival_city] ||
                                'Arrival city not set'
                            }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            Depart
                            <DateTimeText
                                :value="voucher.onward_departure_at"
                                mode="datetime"
                            />
                            · Arrive
                            <DateTimeText
                                :value="voucher.onward_arrival_at"
                                mode="datetime"
                            />
                        </div>
                    </div>
                    <div class="rounded-md border p-3">
                        <div class="font-medium">
                            Return · {{ voucher.return_airline }} ·
                            {{ airlines[voucher.return_airline] || 'Airline' }}
                            {{ voucher.return_flight_number || '' }}
                        </div>
                        <div class="text-sm font-medium">
                            {{ voucher.return_departure_city }} ·
                            {{
                                airportCities[voucher.return_departure_city] ||
                                'Departure city not set'
                            }}
                            →
                            {{ voucher.return_arrival_city }} ·
                            {{
                                airportCities[voucher.return_arrival_city] ||
                                'Arrival city not set'
                            }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            Depart
                            <DateTimeText
                                :value="voucher.return_departure_at"
                                mode="datetime"
                            />
                            · Arrive
                            <DateTimeText
                                :value="voucher.return_arrival_at"
                                mode="datetime"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="includesTransport">
                <CardHeader>
                    <CardTitle>Transport</CardTitle>
                    <CardDescription
                        >Transport included with this voucher.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-if="!voucher.group?.transport_items?.length"
                        class="rounded-md border p-3 text-sm"
                    >
                        {{
                            voucher.group?.transport_mode === 'specialized'
                                ? 'Specialized transport'
                                : 'Standard bus transport'
                        }}
                    </div>
                    <div
                        v-for="item in voucher.group?.transport_items || []"
                        :key="item.id"
                        class="rounded-md border p-3"
                    >
                        <div class="font-medium">
                            {{
                                item.sector?.name ||
                                item.description ||
                                'Transport'
                            }}
                        </div>
                        <div class="text-sm">
                            {{
                                item.service?.name ||
                                item.service?.vehicle_type ||
                                'Vehicle not assigned'
                            }}<span v-if="item.service?.number_plate">
                                · {{ item.service.number_plate }}</span
                            >
                        </div>
                        <div
                            v-if="item.scheduled_at"
                            class="text-sm text-muted-foreground"
                        >
                            <DateTimeText
                                :value="item.scheduled_at"
                                mode="datetime"
                            />
                        </div>
                        <div
                            v-if="
                                item.driver?.name || item.service?.driver_name
                            "
                            class="text-sm text-muted-foreground"
                        >
                            {{ item.driver?.name || item.service?.driver_name }}
                            ·
                            {{
                                item.driver?.phone ||
                                item.service?.driver_contact ||
                                'No contact'
                            }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Hotel Stays</CardTitle>
                    <CardDescription
                        >Stays included in this voucher.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-if="!voucher.hotel_stays?.length"
                        class="text-sm text-muted-foreground"
                    >
                        No hotel stays added.
                    </div>
                    <div
                        v-for="(stay, index) in voucher.hotel_stays"
                        :key="index"
                        class="rounded-md border p-3"
                    >
                        <div class="font-medium">
                            {{ stay.hotel_name
                            }}<span v-if="stay.city"> · {{ stay.city }}</span>
                        </div>
                        <div class="text-sm">
                            {{ stay.room_count || 1 }} × {{ stay.room_type }} ·
                            {{ roomBeds(stay) }} beds each ·
                            {{
                                stay.source === 'company'
                                    ? 'Company supplied'
                                    : 'Self arranged'
                            }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            <DateTimeText
                                :value="stay.check_in_date"
                                mode="date"
                            />
                            to
                            <DateTimeText
                                :value="stay.check_out_date"
                                mode="date"
                            />
                        </div>
                        <div
                            v-if="
                                [
                                    'visa_transport_hotel',
                                    'transport_hotel',
                                    'hotel',
                                ].includes(voucher.service_bundle) &&
                                stay.source === 'company' &&
                                !voucher.billing_voucher_id
                            "
                            class="text-sm text-muted-foreground"
                        >
                            Charge
                            <MoneyText
                                :amount="stay.total_retail_amount"
                                :currency="company.base_currency"
                            /><span v-if="canViewAccounting">
                                · Cost
                                <MoneyText
                                    :amount="stay.total_cost_amount"
                                    :currency="company.base_currency"
                            /></span>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            <template v-if="voucher.billing_voucher">
                                Hotel billing retained on
                                {{ voucher.billing_voucher.voucher_number }}
                            </template>
                            <template v-else>
                                Itinerary only · No hotel charge
                            </template>
                        </div>
                        <div
                            v-if="stay.notes"
                            class="text-sm text-muted-foreground"
                        >
                            {{ stay.notes }}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Passengers</CardTitle>
                <CardDescription
                    >Members covered by this voucher.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="passenger in voucher.passengers"
                    :key="passenger.id"
                    class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_180px_140px_160px]"
                >
                    <div>
                        <div class="font-medium">{{ passenger.full_name }}</div>
                        <div class="text-sm text-muted-foreground">
                            {{ passenger.passport_number || 'No passport' }}
                        </div>
                    </div>
                    <div>{{ passenger.nationality || 'No nationality' }}</div>
                    <div>
                        {{
                            passenger.date_of_birth ||
                            (passenger.imported_age !== null
                                ? `Age ${passenger.imported_age}`
                                : 'Age not set')
                        }}
                    </div>
                    <Badge variant="secondary">{{
                        passenger.visa_status
                    }}</Badge>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="moveOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move Passengers</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitMove">
                    <div class="space-y-2">
                        <Label>Destination voucher</Label>
                        <Select v-model="moveForm.target_voucher_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select voucher" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="target in moveTargets"
                                    :key="target.id"
                                    :value="target.id"
                                >
                                    {{ target.voucher_number }} ·
                                    {{ target.title }} ·
                                    {{ target.passengers_count }} pax
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="moveForm.errors.target_voucher_id"
                            class="text-xs text-destructive"
                        >
                            {{ moveForm.errors.target_voucher_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Passengers</Label>
                        <label
                            v-for="passenger in voucher.passengers"
                            :key="passenger.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2"
                        >
                            <Checkbox
                                :model-value="
                                    moveForm.passenger_ids.includes(
                                        passenger.id,
                                    )
                                "
                                @update:model-value="
                                    moveForm.passenger_ids = togglePassenger(
                                        moveForm.passenger_ids,
                                        passenger.id,
                                        $event,
                                    )
                                "
                            />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{
                                    passenger.full_name
                                }}</span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{
                                        passenger.passport_number ||
                                        'No passport'
                                    }}</span
                                >
                            </span>
                        </label>
                        <p
                            v-if="moveForm.errors.passenger_ids"
                            class="text-xs text-destructive"
                        >
                            {{ moveForm.errors.passenger_ids }}
                        </p>
                    </div>

                    <div
                        v-if="agentCapabilities.requires_override_reason"
                        class="space-y-2"
                    >
                        <Label>Reason for post-travel change</Label>
                        <Textarea v-model="moveForm.override_reason" required />
                        <p
                            v-if="moveForm.errors.override_reason"
                            class="text-xs text-destructive"
                        >
                            {{ moveForm.errors.override_reason }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="
                                moveForm.processing ||
                                !moveForm.target_voucher_id ||
                                !moveForm.passenger_ids.length
                            "
                        >
                            <ArrowRightLeft class="mr-2 h-4 w-4" />
                            Move
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="separateOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Separate Vouchers</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitSeparation">
                    <div class="space-y-2">
                        <Label>Individual voucher passengers</Label>
                        <label
                            v-for="passenger in voucher.passengers"
                            :key="passenger.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2"
                        >
                            <Checkbox
                                :model-value="
                                    separateForm.passenger_ids.includes(
                                        passenger.id,
                                    )
                                "
                                @update:model-value="
                                    separateForm.passenger_ids =
                                        togglePassenger(
                                            separateForm.passenger_ids,
                                            passenger.id,
                                            $event,
                                        )
                                "
                            />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{
                                    passenger.full_name
                                }}</span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{
                                        passenger.passport_number ||
                                        'No passport'
                                    }}</span
                                >
                            </span>
                        </label>
                        <p
                            v-if="separateForm.errors.passenger_ids"
                            class="text-xs text-destructive"
                        >
                            {{ separateForm.errors.passenger_ids }}
                        </p>
                    </div>

                    <div
                        v-if="agentCapabilities.requires_override_reason"
                        class="space-y-2"
                    >
                        <Label>Reason for post-travel change</Label>
                        <Textarea
                            v-model="separateForm.override_reason"
                            required
                        />
                        <p
                            v-if="separateForm.errors.override_reason"
                            class="text-xs text-destructive"
                        >
                            {{ separateForm.errors.override_reason }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="
                                separateForm.processing ||
                                !separateForm.passenger_ids.length
                            "
                        >
                            <Scissors class="mr-2 h-4 w-4" />
                            Create Individual Vouchers
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
