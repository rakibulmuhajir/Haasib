<script setup lang="ts">
/**
 * Umrah dashboard — thin shell over the widget registry.
 *
 * The page used to own six stat tiles, two hand-rolled "Upcoming
 * Travel"/"Recent Groups" cards and a button strip of hard-coded queries.
 * All of that is now a layout: PageShell for the frame, DashboardTabs for
 * the (possibly single, possibly absent) tab strip, DashboardGrid for the
 * widgets themselves. The page resolves nothing — every widget owns its own
 * query on the server and the grid just places what it is given.
 */
import { computed } from 'vue';
import DashboardGrid from '@/components/dashboard/DashboardGrid.vue';
import DashboardTabs from '@/components/dashboard/DashboardTabs.vue';
import type { DashboardWidgetPlacement } from '@/components/dashboard/DashboardGrid.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FilePlus2, Plane, Plus } from 'lucide-vue-next';

interface DashboardTab {
    key: string;
    label: string;
    widgets: DashboardWidgetPlacement[];
}

const props = defineProps<{
    company: { id: string; name: string; slug: string; base_currency: string };
    isAgent: boolean;
    isOperations: boolean;
    capabilities: {
        canCreateGroup: boolean;
        canCreateVoucher: boolean;
        canViewAccounting: boolean;
        canViewAgents: boolean;
        canViewVendors: boolean;
        canViewReports: boolean;
        canViewPayments: boolean;
    };
    dashboard: {
        tabs: DashboardTab[];
        activeTab: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Umrah', href: `/${props.company.slug}/umrah` }];

const tabs = computed(() => props.dashboard?.tabs ?? []);
const tabStrip = computed(() => tabs.value.map((tab) => ({ key: tab.key, label: tab.label })));
const activeWidgets = computed(
    () => tabs.value.find((tab) => tab.key === props.dashboard?.activeTab)?.widgets ?? [],
);
</script>

<template>
    <Head :title="isAgent ? 'My Umrah Dashboard' : 'Umrah Dashboard'" />
    <PageShell
        :title="isAgent ? 'My Umrah Dashboard' : 'Umrah Dashboard'"
        :description="isAgent
            ? 'Your groups, passengers, upcoming travel and account balance.'
            : isOperations
                ? 'Visa groups, passengers and upcoming travel.'
                : 'Visa groups, passengers, travel dates, payments and balances.'"
        :breadcrumbs="breadcrumbs"
        :icon="Plane"
    >
        <template #actions>
            <Button v-if="capabilities.canCreateVoucher" variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers/create`)">
                <FilePlus2 class="mr-2 h-4 w-4" />New Voucher
            </Button>
            <Button v-if="capabilities.canCreateGroup" @click="router.get(`/${company.slug}/umrah/groups/create`)">
                <Plus class="mr-2 h-4 w-4" />New Visa Group
            </Button>
        </template>

        <div class="flex flex-col gap-6">
            <DashboardTabs :tabs="tabStrip" :active-tab="dashboard.activeTab" />
            <DashboardGrid :widgets="activeWidgets" />
        </div>
    </PageShell>
</template>
