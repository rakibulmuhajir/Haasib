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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import VoucherPreview from '../../../components/VoucherPreview.vue';
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
        /** Assembled server-side by CompanyLetterhead — the same identity
            every invoice, bill and receipt in the application prints. */
        letterhead?: {
            name: string;
            legalName?: string | null;
            logoUrl?: string | null;
            lines?: string[];
            email?: string | null;
            phone?: string | null;
            taxId?: string | null;
            taxIdLabel?: string | null;
        } | null;
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
    openWorkflow?: 'amend' | null;
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
const approvalError = ref('');
const moveOpen = ref(false);
const separateOpen = ref(false);
const workflowOpen = ref<'amend' | 'cancel' | 'delete' | null>(
    props.openWorkflow ?? null,
);
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
/*
 * The group decides whether there is transport; the bundle only names what was
 * sold. Passenger and voucher vocabularies both spell a value 'visa_transport'
 * but mean different things by it -- for a passenger it reads "visa included",
 * for a voucher "Visa + Transport" -- so a self-arranged group produced a
 * voucher advertising a bus nobody booked. transport_mode is the fact.
 */
const includesTransport = computed(() =>
    props.voucher.group?.transport_mode !== 'none' &&
    [
        'visa_transport',
        'visa_transport_hotel',
        'transport',
        'transport_hotel',
    ].includes(props.voucher.service_bundle),
);
/*
 * Historical correction, not a live rule: records written before 'visa' and
 * 'visa_hotel' existed as a vocabulary carry 'visa_transport' or
 * 'visa_transport_hotel' on self-arranged groups (transport_mode 'none'),
 * which is exactly the defect this feature fixes -- those vouchers never had
 * a bus. Re-derive the truthful label from the group's transport_mode
 * without touching the stored value.
 */
const serviceBundleLabel = computed(() => {
    const bundle = props.voucher.service_bundle;
    if (props.voucher.group?.transport_mode === 'none') {
        if (bundle === 'visa_transport') return 'Visa Only';
        if (bundle === 'visa_transport_hotel') return 'Visa + Hotel';
    }
    return props.serviceBundles[bundle] || bundle;
});

const approve = () => {
    approvalError.value = '';
    approveForm.post(
        `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/approve`,
        {
            preserveScroll: true,
            onSuccess: () => {
                approvalError.value = '';
            },
            onError: (errors) => {
                approvalError.value = String(
                    errors.voucher ||
                        errors.override_reason ||
                        'Failed to approve voucher',
                );
                toast.error(approvalError.value);
            },
        },
    );
};

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
        },
        onError: () => toast.error(`Failed to ${action} voucher`),
    };
    const url = `/${props.company.slug}/umrah/vouchers/${props.voucher.id}`;
    if (action === 'delete') workflowForm.delete(url, options);
    else workflowForm.post(`${url}/${action}`, options);
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

/**
 * The same faces the application serves, from the same origin.
 *
 * A print window fetching a stylesheet from a CDN is the worst place for that
 * dependency to live: the sheet is produced precisely when the network may not
 * be there, and a failed fetch silently reprints the document in a fallback
 * face. Only the latin subsets are declared -- everything a voucher prints is
 * latin or is an image.
 *
 * The print template interpolates this, so the printed voucher and the screen
 * cannot drift into different typefaces.
 */
/**
 * What is printed beneath the company's name.
 *
 * A voucher is presented at a hotel desk in Makkah and at an airport counter,
 * by someone who may need to ring the issuer or show a registration number.
 * Until the letterhead arrived it carried a name, a logo and a helpline, which
 * is not enough to identify a company to anyone who does not already know it.
 *
 * The helpline stays as the fallback for the phone line, because it is the
 * number the company chose to publish and companies that set one may not have
 * filled in the contact field.
 */
const issuerLines = computed<string[]>(() => {
    const letterhead = props.company.letterhead;

    return [
        letterhead?.legalName,
        ...(letterhead?.lines ?? []),
        letterhead?.email,
        (letterhead?.phone ?? props.company.helpline)
            ? `Helpline: ${letterhead?.phone ?? props.company.helpline}`
            : null,
        letterhead?.taxId
            ? `${letterhead.taxIdLabel ?? 'Tax no.'} ${letterhead.taxId}`
            : null,
    ].filter((line): line is string => Boolean(line));
});


const preparingPrint = ref(false);
const viewTab = ref('voucher');
const printUrl = computed(() => `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/print`);
const printVoucher = () => {
    if (preparingPrint.value) return;
    preparingPrint.value = true;
    const printFrame = document.createElement('iframe');
    printFrame.setAttribute('aria-hidden', 'true');
    printFrame.style.position = 'fixed';
    printFrame.style.width = '0';
    printFrame.style.height = '0';
    printFrame.style.border = '0';
    printFrame.style.visibility = 'hidden';

    printFrame.addEventListener(
        'load',
        async () => {
            const printWindow = printFrame.contentWindow;
            if (!printWindow || printWindow.document.body?.dataset.voucherPrint !== props.voucher.id) {
                preparingPrint.value = false;
                clearTimeout(printTimeout);
                printFrame.remove();
                toast.error('Unable to open the voucher print view.');
                return;
            }
            await printWindow.document.fonts.ready;
            await Promise.all(Array.from(printWindow.document.images).map((image) => image.decode().catch(() => undefined)));
            if (!printFrame.isConnected) return;
            clearTimeout(printTimeout);
            preparingPrint.value = false;
            printWindow.addEventListener(
                'afterprint',
                () => printFrame.remove(),
                { once: true },
            );
            printWindow.focus();
            printWindow.print();
        },
        { once: true },
    );

    const printTimeout = window.setTimeout(() => {
        preparingPrint.value = false;
        printFrame.remove();
        toast.error('The print view could not load. Please try again.');
    }, 15000);
    printFrame.src = `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/print`;
    document.body.appendChild(printFrame);
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
            <div class="flex max-w-full flex-wrap gap-2">
            <Button
                v-if="canViewAccounting"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/umrah/vouchers/${voucher.id}/accounting`,
                    )
                "
            >
                <Calculator class="mr-2 h-4 w-4" />Accounting
            </Button>
            <Button variant="outline" :disabled="preparingPrint" @click="printVoucher">
                <Printer class="mr-2 h-4 w-4" />
                {{ preparingPrint ? 'Preparing…' : 'Print' }}
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
            <Button
                v-if="agentCapabilities.can_amend"
                variant="outline"
                @click="workflowOpen = 'amend'"
            >
                <FilePenLine class="mr-2 h-4 w-4" />Amend
            </Button>
            <Button
                v-if="agentCapabilities.can_delete"
                variant="outline"
                @click="workflowOpen = 'delete'"
            >
                <Trash2 class="mr-2 h-4 w-4" />Delete Draft
            </Button>
            <Button
                v-if="agentCapabilities.can_cancel"
                variant="destructive"
                @click="workflowOpen = 'cancel'"
            >
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
                >{{
                    approveForm.processing
                        ? 'Approving...'
                        : 'Approve Voucher'
                }}</Button
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
            </div>
        </template>

        <div
            v-if="approvalError"
            class="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            role="alert"
        >
            {{ approvalError }}
        </div>

        <Tabs v-model="viewTab" class="mb-4 min-w-0 max-w-full">
            <TabsList aria-label="Voucher view">
                <TabsTrigger value="voucher">Voucher</TabsTrigger>
                <TabsTrigger value="details">Internal details &amp; history</TabsTrigger>
            </TabsList>
        <TabsContent value="voucher" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>Passenger copy · same layout as Print and Export PDF</span>
                <span>Long vouchers continue beyond one page.</span>
            </div>
            <div v-if="voucher.status === 'draft' && canApprove && agentCapabilities.requires_override_reason" class="space-y-2">
                <Label for="preview-override">Reason for approving after travel started</Label>
                <Textarea id="preview-override" v-model="approveForm.override_reason" />
            </div>
            <VoucherPreview :url="printUrl" :voucher-id="voucher.id" :revision="`${voucher.updated_at}-${voucher.status}`" />
        </TabsContent>

        <TabsContent value="details" force-mount v-show="viewTab === 'details'" class="space-y-6">
        <div class="mb-6 flex flex-col items-center text-center">
            <img
                v-if="company.logo_url"
                :src="company.logo_url"
                :alt="`${company.name} logo`"
                class="mb-2 max-h-20 max-w-48 object-contain"
            />
            <div class="text-xl font-semibold">{{ company.name }}</div>
            <div
                v-for="line in issuerLines"
                :key="line"
                class="text-sm text-muted-foreground"
            >
                {{ line }}
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

        <div
            v-if="
                voucher.amended_voucher ||
                voucher.superseded_by_voucher ||
                voucher.cancelled_at
            "
            class="mb-4 rounded-md border px-4 py-3 text-sm"
        >
            <span v-if="voucher.amended_voucher"
                >Version {{ voucher.version_number }} amends
                {{ voucher.amended_voucher.voucher_number }}.</span
            >
            <span v-if="voucher.superseded_by_voucher">
                Superseded by
                {{ voucher.superseded_by_voucher.voucher_number }}.</span
            >
            <span v-if="voucher.cancelled_at">
                Cancelled: {{ voucher.cancellation_reason }}</span
            >
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
            <Card variant="detail"
                ><CardHeader><CardTitle>Status</CardTitle></CardHeader
                ><CardContent class="flex flex-wrap gap-2"
                    ><Badge variant="secondary">{{
                        statuses[voucher.status] || voucher.status
                    }}</Badge
                    ><Badge variant="outline">{{
                        serviceBundleLabel
                    }}</Badge></CardContent
                ></Card
            >
            <Card variant="detail"
                ><CardHeader><CardTitle>Group</CardTitle></CardHeader
                ><CardContent class="font-medium"
                    >{{ voucher.group?.group_number }} ·
                    {{ voucher.group?.name }}</CardContent
                ></Card
            >
            <Card variant="detail"
                ><CardHeader><CardTitle>Agent</CardTitle></CardHeader
                ><CardContent class="font-medium">{{
                    voucher.agent?.name || 'No agent'
                }}</CardContent></Card
            >
            <Card variant="detail"
                ><CardHeader><CardTitle>Created By</CardTitle></CardHeader
                ><CardContent class="font-medium">{{
                    voucher.created_by?.name || 'System'
                }}</CardContent></Card
            >
        </div>

        <Card v-if="changeLogs.length" class="mt-6" variant="detail">
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

        <Dialog
            :open="workflowOpen !== null"
            @update:open="
                (open) => {
                    if (!open) workflowOpen = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>{{
                        workflowOpen === 'cancel'
                            ? 'Cancel Voucher'
                            : workflowOpen === 'delete'
                              ? 'Delete Draft Voucher'
                              : 'Create Voucher Amendment'
                    }}</DialogTitle></DialogHeader
                >
                <div class="space-y-2">
                    <Label for="workflow-reason"
                        >Reason
                        {{
                            workflowOpen === 'cancel'
                                ? ''
                                : '(optional before travel)'
                        }}</Label
                    >
                    <Textarea
                        id="workflow-reason"
                        v-model="workflowForm.reason"
                    />
                    <p
                        v-if="workflowForm.errors.reason"
                        class="text-sm text-destructive"
                    >
                        {{ workflowForm.errors.reason }}
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="workflowOpen = null"
                        >Keep Voucher</Button
                    >
                    <Button
                        :variant="
                            workflowOpen === 'cancel' ||
                            workflowOpen === 'delete'
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="
                            workflowForm.processing ||
                            (workflowOpen === 'cancel' &&
                                workflowForm.reason.trim().length < 5)
                        "
                        @click="submitWorkflow"
                    >
                        {{
                            workflowOpen === 'cancel'
                                ? 'Cancel Voucher'
                                : workflowOpen === 'delete'
                                  ? 'Delete Draft'
                                  : 'Create Amendment'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-if="voucher.service_bundle !== 'hotel'" variant="detail">
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

            <Card v-if="includesTransport" variant="detail">
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
                                : voucher.group?.transport_mode === 'none'
                                  ? 'Self-arranged transport'
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

            <Card variant="detail">
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
                            <!--
                                A draft holds no amounts: hotel rates are
                                taken at approval. Printing the zeros as
                                money read as "this stay costs nothing",
                                directly contradicting the figures the
                                create form had just shown.
                            -->
                            <template v-if="voucher.status === 'draft'">
                                Priced on approval
                            </template>
                            <template v-else>
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
                            </template>
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

        <Card variant="detail">
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
                <form novalidate class="space-y-5" @submit.prevent="submitMove">
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
                <form novalidate class="space-y-5" @submit.prevent="submitSeparation">
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
        <Card v-if="voucher.print_details?.contacts?.length || voucher.print_details?.footer_text" variant="detail">
            <CardHeader><CardTitle>Printed contacts &amp; footer</CardTitle><CardDescription>Saved on this voucher; directory changes do not change this copy.</CardDescription></CardHeader>
            <CardContent class="space-y-3">
                <div v-for="(contact, index) in voucher.print_details.contacts" :key="index" class="grid gap-1 border-b py-2 text-sm sm:grid-cols-4">
                    <span>{{ contact.responsibility }} · {{ contact.city }}</span>
                    <strong>{{ contact.name }}</strong><span>{{ contact.organization }}</span>
                    <span>{{ contact.phone }}<span v-if="contact.whatsapp && contact.whatsapp !== contact.phone"> · WhatsApp {{ contact.whatsapp }}</span></span>
                </div>
                <p v-if="voucher.print_details.footer_text" class="whitespace-pre-wrap text-sm">{{ voucher.print_details.footer_text }}</p>
            </CardContent>
        </Card>
        </TabsContent>
        </Tabs>
    </PageShell>
</template>
