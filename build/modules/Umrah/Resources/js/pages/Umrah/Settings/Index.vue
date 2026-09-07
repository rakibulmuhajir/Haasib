<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    Bus,
    Hotel,
    Landmark,
    Tags,
    UserRoundCog,
    Users,
} from 'lucide-vue-next';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    counts: Record<string, number>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Setup' },
];

const sections = [
    {
        title: 'Company & Currencies',
        description:
            'Company profile, base currency and enabled currencies used across Haasib.',
        href: 'settings',
        companyLevel: true,
        icon: Building2,
        count: () => `Base: ${props.company.base_currency}`,
    },
    {
        title: 'Commercial Pricing',
        description:
            'Effective rates, agent categories and customer-specific prices.',
        href: 'settings/pricing',
        icon: Tags,
        count: () => `${props.counts.pricing_categories} categories`,
    },
    {
        title: 'Agents',
        description: 'Agent profiles, login access and pricing assignment.',
        href: 'agents',
        icon: Users,
        count: () => `${props.counts.agents} active`,
    },
    {
        title: 'Visa Vendors',
        description: 'Visa suppliers and the current fallback rates.',
        href: 'vendors',
        icon: Landmark,
        count: () => `${props.counts.visa_vendors} active`,
    },
    {
        title: 'Transport Vendors',
        description: 'Bus and private transport suppliers.',
        href: 'transport-providers',
        icon: Bus,
        count: () => `${props.counts.transport_vendors} active`,
    },
    {
        title: 'Transport Services',
        description: 'Vehicles, sectors, journey packages and fares.',
        href: 'settings/transport-services',
        icon: Building2,
        count: () =>
            `${props.counts.transport_services} vehicles · ${props.counts.transport_fares} fares`,
    },
    {
        title: 'Hotels',
        description: 'Hotel suppliers, properties and per-bed room rates.',
        href: 'settings/hotels',
        icon: Hotel,
        count: () => `${props.counts.hotels} active`,
    },
    {
        title: 'Drivers',
        description: 'Reusable drivers and their contact details.',
        href: 'settings/drivers',
        icon: UserRoundCog,
        count: () => `${props.counts.drivers} active`,
    },
];
</script>

<template>
    <Head title="Umrah Setup" />
    <PageShell
        title="Setup"
        description="The working parts behind bookings. Open one area without hunting through the rest."
        :breadcrumbs="breadcrumbs"
        :icon="Building2"
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="section in sections"
                :key="section.title"
                variant="detail"
                class="flex min-h-52 flex-col"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <CardTitle>{{ section.title }}</CardTitle>
                            <CardDescription>{{
                                section.description
                            }}</CardDescription>
                        </div>
                        <component
                            :is="section.icon"
                            class="h-5 w-5 shrink-0 text-muted-foreground"
                        />
                    </div>
                </CardHeader>
                <CardContent
                    class="mt-auto flex items-center justify-between gap-3"
                >
                    <span class="text-sm text-muted-foreground">{{
                        section.count()
                    }}</span>
                    <Button
                        variant="outline"
                        @click="
                            router.get(
                                section.companyLevel
                                    ? `/${company.slug}/${section.href}`
                                    : `/${company.slug}/umrah/${section.href}`,
                            )
                        "
                    >
                        Open
                    </Button>
                </CardContent>
            </Card>
        </div>
    </PageShell>
</template>
