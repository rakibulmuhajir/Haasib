<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import OperationSavedViews from '../../../components/OperationSavedViews.vue';
import MetaChip from '@/components/MetaChip.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useFormFeedback } from '@/composables/useFormFeedback';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    BedDouble,
    Bus,
    CalendarRange,
    ChevronDown,
    Download,
    ExternalLink,
    Hotel,
    LoaderCircle,
    MapPin,
    Plane,
    Printer,
    Users,
    Wrench,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, reactive, ref } from 'vue';

type Option = { value: string; label: string };
type SummaryCard = {
    key: string;
    label: string;
    value: number;
    event_type: string | null;
    readiness: string | null;
};
type PartyRef = { id: string; number?: string; name?: string; title?: string };
type Passenger = {
    id: string;
    name: string;
    passport?: string | null;
    nationality?: string | null;
};
type ResolutionAction = {
    key: string;
    label: string;
    description: string;
    href: string;
};
type EventRow = {
    event_key: string;
    type: string;
    type_label: string;
    scheduled_at: string;
    scheduled_date: string;
    scheduled_time: string | null;
    is_all_day: boolean;
    origin: string | null;
    destination: string | null;
    location: string | null;
    headline: string;
    airport: string | null;
    flight: string | null;
    hotel: string | null;
    city: string | null;
    room_type?: string | null;
    room_count?: number;
    passenger_count: number;
    readiness: string;
    readiness_label: string;
    readiness_issues: string[];
    voucher?: PartyRef | null;
    group?: PartyRef | null;
    agent?: { id: string; name: string } | null;
    passengers?: Passenger[];
    transport?: {
        provider?: string | null;
        vehicle?: string | null;
        quantity?: number;
        capacity?: number | null;
        driver?: string | null;
        driver_phone?: string | null;
        terminal?: string | null;
    } | null;
    source_href?: string | null;
    resolution_actions?: ResolutionAction[];
};

const props = defineProps<{
    company: { id: string; name: string; slug: string };
    savedViews: { id: string; name: string; filters: Record<string, string | null> }[];
    operationsData: {
        profile: string;
        shows_details: boolean;
        period_label: string;
        filters: {
            period: string;
            date: string;
            start: string | null;
            end: string | null;
            event_type: string;
            readiness: string;
            agent_id: string | null;
        };
        periods: Record<string, string>;
        event_types: Record<string, string>;
        readiness_options: Record<string, string>;
        summary: SummaryCard[];
        events: EventRow[];
        matching_events: number | null;
        agents: Option[];
    };
}>();

const { showError } = useFormFeedback();
const loading = ref(false);
const resolvingKey = ref<string | null>(null);
const expandedKeys = ref<Set<string>>(new Set());
const filters = reactive({
    period: props.operationsData.filters.period,
    date: props.operationsData.filters.date,
    start:
        props.operationsData.filters.start ?? props.operationsData.filters.date,
    end: props.operationsData.filters.end ?? props.operationsData.filters.date,
    event_type: props.operationsData.filters.event_type,
    readiness: props.operationsData.filters.readiness,
    agent_id: props.operationsData.filters.agent_id ?? 'all',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Operations', href: `/${props.company.slug}/umrah/operations` },
];

const iconByType: Record<string, Component> = {
    airport_arrival: ArrowDownToLine,
    airport_departure: ArrowUpFromLine,
    hotel_check_in: Hotel,
    hotel_check_out: BedDouble,
    city_transfer: Bus,
    transport_pickup: MapPin,
};

const groupedEvents = computed(() => {
    const groups = new Map<string, EventRow[]>();
    for (const event of props.operationsData.events) {
        const rows = groups.get(event.scheduled_date) ?? [];
        rows.push(event);
        groups.set(event.scheduled_date, rows);
    }
    return Array.from(groups.entries()).map(([date, events]) => ({
        date,
        events,
    }));
});

const showDayDividers = computed(() => groupedEvents.value.length > 1);

const customDateError = computed(() => {
    if (filters.period !== 'custom') return null;
    if (!filters.start || !filters.end) return 'Choose both dates.';
    return filters.start > filters.end
        ? 'The From date is after the To date.'
        : null;
});

const query = () => {
    const values: Record<string, string> = {
        period: filters.period,
        date: filters.date,
    };

    if (filters.period === 'custom') {
        values.start = filters.start;
        values.end = filters.end;
    }

    if (props.operationsData.shows_details) {
        values.event_type = filters.event_type;
        values.readiness = filters.readiness;
        if (filters.agent_id !== 'all') values.agent_id = filters.agent_id;
    }

    return values;
};

const applyFilters = () => {
    if (customDateError.value) return;
    router.get(`/${props.company.slug}/umrah/operations`, query(), {
        preserveState: true,
        replace: true,
        onStart: () => {
            loading.value = true;
        },
        onError: (errors) => showError(errors),
        onFinish: () => {
            loading.value = false;
        },
    });
};

const choosePeriod = (period: string) => {
    filters.period = period;
    applyFilters();
};

const chooseEventType = (eventType: string) => {
    filters.event_type = eventType;
    applyFilters();
};

const drillIntoSummary = (item: SummaryCard) => {
    if (!props.operationsData.shows_details || loading.value) return;

    filters.event_type = item.event_type ?? 'all';
    filters.readiness = item.readiness ?? 'all';
    applyFilters();
};

const reportUrl = (format: 'print' | 'pdf' | 'csv' = 'print') => {
    // Export the displayed result, not date/filter edits that have not been applied.
    const applied = Object.fromEntries(
        Object.entries(props.operationsData.filters).filter((entry): entry is [string, string] => entry[1] !== null),
    );
    const params = new URLSearchParams(applied).toString();
    const suffix = format === 'print' ? '' : `/${format}`;
    return `/${props.company.slug}/umrah/operations/report${suffix}?${params}`;
};

const openPrintReport = () => {
    if (customDateError.value) return;
    window.open(reportUrl(), '_blank', 'noopener,noreferrer');
};

const downloadReport = () => {
    if (customDateError.value) return;
    window.location.href = reportUrl('pdf');
};

const pageActions = computed(() => [
    {
        label: 'Export CSV',
        icon: Download,
        variant: 'outline' as const,
        disabled: loading.value || customDateError.value !== null,
        onClick: () => {
            if (!customDateError.value) window.location.href = reportUrl('csv');
        },
    },
    {
        label: 'Print report',
        icon: Printer,
        variant: 'outline' as const,
        disabled: loading.value || customDateError.value !== null,
        onClick: openPrintReport,
    },
    {
        label: 'Download PDF',
        icon: Download,
        disabled: loading.value || customDateError.value !== null,
        onClick: downloadReport,
    },
]);

const openSource = (event: EventRow) => {
    if (event.source_href)
        router.get(`/${props.company.slug}${event.source_href}`);
};

const openResolution = (event: EventRow, action: ResolutionAction) => {
    resolvingKey.value = `${event.event_key}:${action.key}`;
    router.get(
        `/${props.company.slug}${action.href}`,
        {},
        {
            onFinish: () => {
                resolvingKey.value = null;
            },
        },
    );
};

const toggleEvent = (eventKey: string) => {
    const next = new Set(expandedKeys.value);
    if (next.has(eventKey)) next.delete(eventKey);
    else next.add(eventKey);
    expandedKeys.value = next;
};

const dayLabel = (date: string) =>
    new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date(`${date}T00:00:00`));

const timeLabel = (event: EventRow) => {
    if (event.is_all_day || !event.scheduled_time) return '—';
    const [hours, minutes] = event.scheduled_time.split(':').map(Number);
    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(2000, 0, 1, hours, minutes));
};

const missingTimeLabel = (event: EventRow) => {
    if (event.scheduled_time && !event.is_all_day) return null;

    return (
        {
            airport_arrival: 'Arrival time missing',
            airport_departure: 'Departure time missing',
            city_transfer: 'Pickup time missing',
            transport_pickup: 'Pickup time missing',
            hotel_check_in: 'Time not specified',
            hotel_check_out: 'Time not specified',
        }[event.type] ?? 'Time not set'
    );
};

const missingTimeNeedsAttention = (event: EventRow) =>
    [
        'airport_arrival',
        'airport_departure',
        'city_transfer',
        'transport_pickup',
    ].includes(event.type);

const showsTransport = (event: EventRow) =>
    [
        'airport_arrival',
        'airport_departure',
        'city_transfer',
        'transport_pickup',
    ].includes(event.type);

const movementLabel = (event: EventRow) => {
    if (event.origin && event.destination)
        return `${event.origin} → ${event.destination}`;
    return event.location || 'Location not set';
};

const roomLabel = (event: EventRow) => {
    if (!event.room_type) return null;
    const rooms = event.room_count
        ? ` · ${event.room_count} room${event.room_count === 1 ? '' : 's'}`
        : '';
    return `${event.room_type}${rooms}`;
};

const serviceLabel = (event: EventRow) => {
    if (event.flight && event.airport)
        return `${event.flight} · ${event.airport}`;
    return event.flight || event.airport || event.hotel || null;
};
</script>

<template>
    <Head title="Operations" />
    <PageShell
        title="Operations"
        :description="
            operationsData.shows_details
                ? 'Flights, stays and road movements from approved vouchers.'
                : 'A clear count of people moving in, moving out and between cities.'
        "
        :breadcrumbs="breadcrumbs"
        :icon="CalendarRange"
        :actions="pageActions"
        compact
    >
        <div class="flex flex-col gap-3">
            <OperationSavedViews :company-slug="company.slug" :views="savedViews" :applied="operationsData.filters" />
            <section class="border-y border-rule-default bg-surface-1">
                <div class="flex flex-wrap items-center gap-1.5 px-2 py-2">
                    <Button
                        v-for="(label, key) in operationsData.periods"
                        :key="key"
                        size="sm"
                        :variant="filters.period === key ? 'default' : 'ghost'"
                        :disabled="loading"
                        @click="choosePeriod(key)"
                    >
                        <LoaderCircle
                            v-if="loading && filters.period === key"
                            class="size-4 animate-spin"
                        />
                        {{ label }}
                    </Button>
                    <span class="ml-auto px-2 text-xs text-text-secondary">
                        {{ operationsData.period_label }} · scheduled local time
                    </span>
                </div>

                <div
                    v-if="filters.period === 'custom'"
                    class="grid gap-2 border-t border-rule-subtle px-3 py-2 sm:grid-cols-[minmax(10rem,14rem)_minmax(10rem,14rem)_auto] sm:items-end"
                >
                    <div class="space-y-1">
                        <Label for="operations-start" class="text-xs"
                            >From</Label
                        >
                        <Input
                            id="operations-start"
                            v-model="filters.start"
                            type="date"
                            class="h-8"
                            :aria-invalid="customDateError ? true : undefined"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="operations-end" class="text-xs">To</Label>
                        <Input
                            id="operations-end"
                            v-model="filters.end"
                            type="date"
                            class="h-8"
                            :aria-invalid="customDateError ? true : undefined"
                        />
                    </div>
                    <Button
                        size="sm"
                        :disabled="loading || customDateError !== null"
                        @click="applyFilters"
                    >
                        <LoaderCircle
                            v-if="loading"
                            class="size-4 animate-spin"
                        />
                        Apply dates
                    </Button>
                    <p
                        v-if="customDateError"
                        class="text-xs text-destructive sm:col-span-3"
                    >
                        {{ customDateError }}
                    </p>
                </div>
            </section>

            <section
                class="grid overflow-hidden border-y border-rule-default bg-surface-1 sm:grid-cols-2 xl:grid-cols-5"
                aria-label="Movement summary"
            >
                <template
                    v-for="item in operationsData.summary"
                    :key="item.key"
                >
                    <Button
                        v-if="operationsData.shows_details"
                        variant="ghost"
                        class="h-auto min-h-14 justify-between rounded-none border-b border-rule-subtle px-3 py-2 text-left xl:border-r xl:border-b-0 xl:last:border-r-0 sm:[&:nth-child(odd)]:border-r"
                        :aria-label="`Show ${item.label.toLowerCase()} movements`"
                        :data-summary-key="item.key"
                        @click="drillIntoSummary(item)"
                    >
                        <span class="text-xs font-medium text-text-secondary">{{
                            item.label
                        }}</span>
                        <span
                            class="font-mono text-lg font-semibold text-text-primary tabular-nums"
                        >
                            {{ item.value.toLocaleString() }}
                        </span>
                    </Button>
                    <div
                        v-else
                        class="flex min-h-14 items-center justify-between border-b border-rule-subtle px-3 py-2 xl:border-r xl:border-b-0 xl:last:border-r-0 sm:[&:nth-child(odd)]:border-r"
                    >
                        <span class="text-xs font-medium text-text-secondary">{{
                            item.label
                        }}</span>
                        <span
                            class="font-mono text-lg font-semibold text-text-primary tabular-nums"
                        >
                            {{ item.value.toLocaleString() }}
                        </span>
                    </div>
                </template>
            </section>

            <Card
                v-if="!operationsData.shows_details"
                variant="register"
                class="rounded-none"
            >
                <CardContent class="flex items-start gap-3 py-4">
                    <Users class="mt-0.5 size-5 shrink-0 text-text-secondary" />
                    <div>
                        <p class="font-medium text-foreground">
                            Movement summary
                        </p>
                        <p class="mt-1 max-w-2xl text-sm text-text-secondary">
                            This role receives movement totals without passenger
                            or itinerary details.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <template v-else>
                <section class="border-y border-rule-default bg-surface-1">
                    <div
                        class="flex gap-1 overflow-x-auto border-b border-rule-subtle px-2 py-1.5"
                        role="tablist"
                        aria-label="Movement type"
                    >
                        <Button
                            size="sm"
                            :variant="
                                filters.event_type === 'all'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            role="tab"
                            :aria-selected="filters.event_type === 'all'"
                            :disabled="loading"
                            @click="chooseEventType('all')"
                        >
                            All movements
                        </Button>
                        <Button
                            v-for="(label, key) in operationsData.event_types"
                            :key="key"
                            size="sm"
                            :variant="
                                filters.event_type === key
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            role="tab"
                            :aria-selected="filters.event_type === key"
                            :disabled="loading"
                            @click="chooseEventType(key)"
                        >
                            {{ label }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-end gap-2 px-3 py-2">
                        <div class="min-w-44 space-y-1">
                            <Label class="text-xs">Readiness</Label>
                            <Select v-model="filters.readiness">
                                <SelectTrigger class="h-8"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(
                                            label, key
                                        ) in operationsData.readiness_options"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-if="operationsData.agents.length"
                            class="min-w-52 space-y-1"
                        >
                            <Label class="text-xs">Agent</Label>
                            <Select v-model="filters.agent_id">
                                <SelectTrigger class="h-8"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All agents</SelectItem
                                    >
                                    <SelectItem
                                        v-for="agent in operationsData.agents"
                                        :key="agent.value"
                                        :value="agent.value"
                                    >
                                        {{ agent.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            size="sm"
                            :disabled="loading || customDateError !== null"
                            @click="applyFilters"
                        >
                            <LoaderCircle
                                v-if="loading"
                                class="size-4 animate-spin"
                            />
                            Apply filters
                        </Button>
                        <div
                            class="ml-auto pb-1 text-right text-xs text-text-secondary"
                        >
                            <p>
                                {{
                                    operationsData.matching_events?.toLocaleString()
                                }}
                                matching movements
                            </p>
                            <p>Times are shown as scheduled locally</p>
                        </div>
                    </div>
                </section>

                <div v-if="groupedEvents.length">
                    <div
                        class="hidden overflow-hidden border-y border-rule-default xl:block"
                    >
                        <Table class="table-fixed">
                            <TableHeader class="sticky top-0 z-10 bg-surface-1">
                                <TableRow class="hover:outline-none">
                                    <TableHead class="w-[9%]"
                                        >Local time</TableHead
                                    >
                                    <TableHead class="w-[23%]"
                                        >Movement</TableHead
                                    >
                                    <TableHead class="w-[21%]"
                                        >Route / service</TableHead
                                    >
                                    <TableHead class="w-[13%]"
                                        >People</TableHead
                                    >
                                    <TableHead class="w-[17%]"
                                        >Transport</TableHead
                                    >
                                    <TableHead class="w-[14%]"
                                        >Readiness</TableHead
                                    >
                                    <TableHead class="w-[3%]"
                                        ><span class="sr-only"
                                            >Actions</span
                                        ></TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody
                                class="[&_tr:nth-child(odd)]:bg-transparent"
                            >
                                <template
                                    v-for="group in groupedEvents"
                                    :key="group.date"
                                >
                                    <TableRow
                                        v-if="showDayDividers"
                                        class="bg-surface-2 hover:outline-none"
                                    >
                                        <TableCell
                                            colspan="7"
                                            class="border-y border-rule-default py-1.5"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="font-mono text-xs font-semibold tracking-wider text-text-secondary uppercase"
                                                >
                                                    {{ dayLabel(group.date) }}
                                                </span>
                                                <span
                                                    class="text-xs text-text-tertiary"
                                                >
                                                    {{ group.events.length }}
                                                    movements
                                                </span>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <template
                                        v-for="event in group.events"
                                        :key="event.event_key"
                                    >
                                        <TableRow
                                            :class="{
                                                'border-l-2 border-l-status-attention':
                                                    event.readiness ===
                                                    'needs_attention',
                                            }"
                                        >
                                            <TableCell class="align-top">
                                                <p
                                                    class="font-mono text-sm font-semibold tabular-nums"
                                                >
                                                    {{ timeLabel(event) }}
                                                </p>
                                                <p
                                                    v-if="
                                                        missingTimeLabel(event)
                                                    "
                                                    class="mt-1 text-[11px] leading-tight"
                                                    :class="
                                                        missingTimeNeedsAttention(
                                                            event,
                                                        )
                                                            ? 'text-status-attention'
                                                            : 'text-text-tertiary'
                                                    "
                                                >
                                                    {{
                                                        missingTimeLabel(event)
                                                    }}
                                                </p>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div
                                                    class="flex min-w-0 items-start gap-2"
                                                >
                                                    <component
                                                        :is="
                                                            iconByType[
                                                                event.type
                                                            ] || CalendarRange
                                                        "
                                                        class="mt-0.5 size-4 shrink-0 text-text-secondary"
                                                    />
                                                    <div class="min-w-0">
                                                        <p
                                                            class="truncate font-medium"
                                                        >
                                                            {{ event.headline }}
                                                        </p>
                                                        <div
                                                            class="mt-1 flex flex-wrap items-center gap-1.5"
                                                        >
                                                            <MetaChip
                                                                tone="neutral"
                                                                >{{
                                                                    event.type_label
                                                                }}</MetaChip
                                                            >
                                                            <span
                                                                v-if="
                                                                    event
                                                                        .voucher
                                                                        ?.number
                                                                "
                                                                class="font-mono text-[11px] text-text-tertiary"
                                                            >
                                                                {{
                                                                    event
                                                                        .voucher
                                                                        .number
                                                                }}
                                                            </span>
                                                            <span
                                                                v-if="
                                                                    event.group
                                                                        ?.number
                                                                "
                                                                class="font-mono text-[11px] text-text-tertiary"
                                                            >
                                                                {{
                                                                    event.group
                                                                        .number
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <p class="truncate font-medium">
                                                    {{ movementLabel(event) }}
                                                </p>
                                                <p
                                                    v-if="serviceLabel(event)"
                                                    class="mt-1 truncate text-xs text-text-secondary"
                                                >
                                                    {{ serviceLabel(event) }}
                                                </p>
                                                <p
                                                    v-if="roomLabel(event)"
                                                    class="mt-1 truncate text-[11px] text-text-tertiary capitalize"
                                                >
                                                    {{ roomLabel(event) }}
                                                </p>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <Button
                                                    v-if="
                                                        event.passengers?.length
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    class="-ml-2 h-7 gap-1 px-2"
                                                    :aria-expanded="
                                                        expandedKeys.has(
                                                            event.event_key,
                                                        )
                                                    "
                                                    @click="
                                                        toggleEvent(
                                                            event.event_key,
                                                        )
                                                    "
                                                >
                                                    <Users class="size-3.5" />
                                                    {{ event.passenger_count }}
                                                    pax
                                                    <ChevronDown
                                                        class="size-3.5 transition-transform"
                                                        :class="{
                                                            'rotate-180':
                                                                expandedKeys.has(
                                                                    event.event_key,
                                                                ),
                                                        }"
                                                    />
                                                </Button>
                                                <p v-else class="font-medium">
                                                    {{ event.passenger_count }}
                                                    pax
                                                </p>
                                                <p
                                                    v-if="event.agent?.name"
                                                    class="mt-1 truncate text-xs text-text-tertiary"
                                                >
                                                    {{ event.agent.name }}
                                                </p>
                                            </TableCell>
                                            <TableCell
                                                class="align-top text-xs"
                                            >
                                                <template
                                                    v-if="showsTransport(event)"
                                                >
                                                    <p class="font-medium">
                                                        {{
                                                            event.readiness ===
                                                            'self_arranged'
                                                                ? 'Self-arranged'
                                                                : event
                                                                      .transport
                                                                      ?.provider ||
                                                                  'Not assigned'
                                                        }}
                                                    </p>
                                                    <template
                                                        v-if="
                                                            event.readiness !==
                                                            'self_arranged'
                                                        "
                                                    >
                                                        <p
                                                            class="mt-1 text-text-secondary"
                                                        >
                                                            {{
                                                                event.transport
                                                                    ?.vehicle ||
                                                                'Vehicle pending'
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-0.5 text-text-tertiary"
                                                        >
                                                            {{
                                                                event.transport
                                                                    ?.driver ||
                                                                'Driver pending'
                                                            }}
                                                        </p>
                                                    </template>
                                                </template>
                                                <span
                                                    v-else
                                                    class="text-text-tertiary"
                                                >
                                                    Not required
                                                </span>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <StatusBadge
                                                    :status="event.readiness"
                                                    :fallback="
                                                        event.readiness_label
                                                    "
                                                />
                                                <p
                                                    v-for="issue in event.readiness_issues"
                                                    :key="issue"
                                                    class="mt-1 text-[11px] leading-tight text-status-attention"
                                                >
                                                    {{ issue }}
                                                </p>
                                                <Button
                                                    v-for="action in event.resolution_actions"
                                                    :key="action.key"
                                                    size="sm"
                                                    variant="outline"
                                                    class="mt-1.5 h-7 gap-1 px-2 text-xs"
                                                    :title="action.description"
                                                    :disabled="
                                                        resolvingKey !== null
                                                    "
                                                    @click="
                                                        openResolution(
                                                            event,
                                                            action,
                                                        )
                                                    "
                                                >
                                                    <LoaderCircle
                                                        v-if="
                                                            resolvingKey ===
                                                            `${event.event_key}:${action.key}`
                                                        "
                                                        class="size-3.5 animate-spin"
                                                    />
                                                    <Wrench
                                                        v-else
                                                        class="size-3.5"
                                                    />
                                                    {{ action.label }}
                                                </Button>
                                            </TableCell>
                                            <TableCell class="px-1 align-top">
                                                <Button
                                                    v-if="event.source_href"
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Open source record"
                                                    @click="openSource(event)"
                                                >
                                                    <ExternalLink
                                                        class="size-4"
                                                    />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                        <TableRow
                                            v-if="
                                                event.passengers?.length &&
                                                expandedKeys.has(
                                                    event.event_key,
                                                )
                                            "
                                            class="bg-surface-2 hover:outline-none"
                                        >
                                            <TableCell colspan="7" class="py-2">
                                                <div
                                                    class="grid gap-x-6 sm:grid-cols-2 xl:grid-cols-3"
                                                >
                                                    <div
                                                        v-for="passenger in event.passengers"
                                                        :key="passenger.id"
                                                        class="flex items-center justify-between gap-3 border-b border-rule-subtle py-2 last:border-0"
                                                    >
                                                        <div class="min-w-0">
                                                            <p
                                                                class="truncate text-sm font-medium"
                                                            >
                                                                {{
                                                                    passenger.name
                                                                }}
                                                            </p>
                                                            <p
                                                                class="font-mono text-xs text-text-tertiary"
                                                            >
                                                                {{
                                                                    passenger.passport ||
                                                                    'Passport not recorded'
                                                                }}
                                                            </p>
                                                        </div>
                                                        <MetaChip
                                                            v-if="
                                                                passenger.nationality
                                                            "
                                                            tone="neutral"
                                                        >
                                                            {{
                                                                passenger.nationality
                                                            }}
                                                        </MetaChip>
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                </template>
                            </TableBody>
                        </Table>
                    </div>

                    <div
                        class="divide-y divide-rule-default border-y border-rule-default xl:hidden"
                    >
                        <section
                            v-for="group in groupedEvents"
                            :key="group.date"
                        >
                            <div
                                v-if="showDayDividers"
                                class="flex items-center justify-between bg-surface-2 px-3 py-1.5"
                            >
                                <span
                                    class="font-mono text-[11px] font-semibold tracking-wider text-text-secondary uppercase"
                                >
                                    {{ dayLabel(group.date) }}
                                </span>
                                <span class="text-[11px] text-text-tertiary"
                                    >{{ group.events.length }} movements</span
                                >
                            </div>
                            <article
                                v-for="event in group.events"
                                :key="event.event_key"
                                class="px-3 py-3"
                                :class="{
                                    'border-l-2 border-l-status-attention':
                                        event.readiness === 'needs_attention',
                                }"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="w-20 shrink-0">
                                        <p
                                            class="font-mono text-sm font-semibold tabular-nums"
                                        >
                                            {{ timeLabel(event) }}
                                        </p>
                                        <p
                                            v-if="missingTimeLabel(event)"
                                            class="mt-1 text-[11px] leading-tight"
                                            :class="
                                                missingTimeNeedsAttention(event)
                                                    ? 'text-status-attention'
                                                    : 'text-text-tertiary'
                                            "
                                        >
                                            {{ missingTimeLabel(event) }}
                                        </p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p class="truncate font-medium">
                                                    {{ event.headline }}
                                                </p>
                                                <p
                                                    class="mt-0.5 truncate text-sm text-text-secondary"
                                                >
                                                    {{ movementLabel(event) }}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                :status="event.readiness"
                                                :fallback="
                                                    event.readiness_label
                                                "
                                            />
                                        </div>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-tertiary"
                                        >
                                            <span>{{ event.type_label }}</span>
                                            <span v-if="serviceLabel(event)">{{
                                                serviceLabel(event)
                                            }}</span>
                                            <span v-if="event.agent?.name">{{
                                                event.agent.name
                                            }}</span>
                                        </div>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-1.5"
                                        >
                                            <Button
                                                v-if="event.passengers?.length"
                                                variant="outline"
                                                size="sm"
                                                class="h-7 gap-1 px-2 text-xs"
                                                :aria-expanded="
                                                    expandedKeys.has(
                                                        event.event_key,
                                                    )
                                                "
                                                @click="
                                                    toggleEvent(event.event_key)
                                                "
                                            >
                                                <Users class="size-3.5" />
                                                {{ event.passenger_count }} pax
                                                <ChevronDown
                                                    class="size-3.5 transition-transform"
                                                    :class="{
                                                        'rotate-180':
                                                            expandedKeys.has(
                                                                event.event_key,
                                                            ),
                                                    }"
                                                />
                                            </Button>
                                            <span
                                                v-else
                                                class="text-xs font-medium"
                                                >{{
                                                    event.passenger_count
                                                }}
                                                pax</span
                                            >
                                            <Button
                                                v-for="action in event.resolution_actions"
                                                :key="action.key"
                                                size="sm"
                                                variant="outline"
                                                class="h-7 gap-1 px-2 text-xs"
                                                :title="action.description"
                                                :disabled="
                                                    resolvingKey !== null
                                                "
                                                @click="
                                                    openResolution(
                                                        event,
                                                        action,
                                                    )
                                                "
                                            >
                                                <LoaderCircle
                                                    v-if="
                                                        resolvingKey ===
                                                        `${event.event_key}:${action.key}`
                                                    "
                                                    class="size-3.5 animate-spin"
                                                />
                                                <Wrench
                                                    v-else
                                                    class="size-3.5"
                                                />
                                                {{ action.label }}
                                            </Button>
                                            <Button
                                                v-if="event.source_href"
                                                variant="ghost"
                                                size="sm"
                                                class="h-7 gap-1 px-2 text-xs"
                                                @click="openSource(event)"
                                            >
                                                <ExternalLink
                                                    class="size-3.5"
                                                />
                                                Open
                                            </Button>
                                        </div>
                                        <div
                                            v-if="
                                                event.passengers?.length &&
                                                expandedKeys.has(
                                                    event.event_key,
                                                )
                                            "
                                            class="mt-2 divide-y divide-rule-subtle border-t border-rule-subtle"
                                        >
                                            <div
                                                v-for="passenger in event.passengers"
                                                :key="passenger.id"
                                                class="flex items-center justify-between gap-3 py-2"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate text-sm font-medium"
                                                    >
                                                        {{ passenger.name }}
                                                    </p>
                                                    <p
                                                        class="font-mono text-xs text-text-tertiary"
                                                    >
                                                        {{
                                                            passenger.passport ||
                                                            'Passport not recorded'
                                                        }}
                                                    </p>
                                                </div>
                                                <MetaChip
                                                    v-if="passenger.nationality"
                                                    tone="neutral"
                                                >
                                                    {{ passenger.nationality }}
                                                </MetaChip>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </div>
                </div>

                <Card v-else variant="register" class="rounded-none">
                    <EmptyState
                        :icon="Plane"
                        title="No scheduled movements"
                        description="Nothing matches this period and filter combination. Try another date or show all movement types."
                        size="sm"
                    />
                </Card>
            </template>
        </div>
    </PageShell>
</template>
