<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, CreditCard, FilePlus2, Plane, Plus, ScrollText, Users } from 'lucide-vue-next';

const props = defineProps<{
    company: { id: string; name: string; slug: string; base_currency: string };
    summary: {
        active_groups: number;
        passports_in_process: number;
        agent_balance: number;
        month_charges: number;
        month_profit: number;
        payments_this_month: number;
    };
    upcomingGroups: any[];
    recentGroups: any[];
    isAgent: boolean;
    capabilities: {
        canCreateGroup: boolean;
        canCreateVoucher: boolean;
        canViewAccounting: boolean;
        canViewAgents: boolean;
        canViewVendors: boolean;
        canViewReports: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Umrah', href: `/${props.company.slug}/umrah` }];
const openGroup = (id: string) => router.get(`/${props.company.slug}/umrah/groups/${id}`);
</script>

<template>
    <Head :title="isAgent ? 'My Umrah Dashboard' : 'Umrah Dashboard'" />
    <PageShell
        :title="isAgent ? 'My Umrah Dashboard' : 'Umrah Dashboard'"
        :description="isAgent ? 'Your groups, passengers, upcoming travel and account balance.' : 'Visa groups, passengers, travel dates, payments and balances.'"
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

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-md border bg-border md:grid-cols-3 xl:grid-cols-6">
            <div class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ isAgent ? 'My Groups' : 'Active Groups' }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ summary.active_groups }}</div>
            </div>
            <div class="bg-background p-4">
                <div class="text-sm text-muted-foreground">Passengers</div>
                <div class="mt-1 text-2xl font-semibold">{{ summary.passports_in_process }}</div>
            </div>
            <div class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ isAgent ? 'Amount Due' : 'Agent Balances' }}</div>
                <MoneyText class="mt-1 text-2xl font-semibold" :amount="summary.agent_balance" :currency="company.base_currency" />
            </div>
            <div class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ isAgent ? 'Charges This Month' : 'Month Revenue' }}</div>
                <MoneyText class="mt-1 text-2xl font-semibold" :amount="summary.month_charges" :currency="company.base_currency" />
            </div>
            <div class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ isAgent ? 'Paid This Month' : 'Collected This Month' }}</div>
                <MoneyText class="mt-1 text-2xl font-semibold" :amount="summary.payments_this_month" :currency="company.base_currency" />
            </div>
            <div v-if="capabilities.canViewAccounting" class="bg-background p-4">
                <div class="text-sm text-muted-foreground">Month Profit</div>
                <MoneyText class="mt-1 text-2xl font-semibold" :amount="summary.month_profit" :currency="company.base_currency" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Upcoming Travel</CardTitle>
                    <CardDescription>Groups ordered by their next travel date.</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="!upcomingGroups.length" class="px-6 pb-6 text-sm text-muted-foreground">No upcoming travel dates.</div>
                    <Button
                        v-for="group in upcomingGroups"
                        :key="group.id"
                        type="button"
                        variant="ghost"
                        class="h-auto w-full justify-between gap-4 rounded-none border-t px-6 py-4 text-left"
                        @click="openGroup(group.id)"
                    >
                        <div class="min-w-0">
                            <div class="font-medium">{{ group.group_number }}</div>
                            <div class="truncate text-sm text-muted-foreground">{{ group.name }}</div>
                            <div class="mt-1 flex gap-3 text-xs text-muted-foreground">
                                <DateTimeText :value="group.travel_date" mode="date" />
                                <span>{{ group.passenger_count }} pax</span>
                            </div>
                        </div>
                        <ArrowRight class="h-4 w-4 shrink-0" />
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recent Groups</CardTitle>
                    <CardDescription>Most recently created visa groups.</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="!recentGroups.length" class="px-6 pb-6 text-sm text-muted-foreground">No visa groups yet.</div>
                    <Button
                        v-for="group in recentGroups"
                        :key="group.id"
                        type="button"
                        variant="ghost"
                        class="h-auto w-full justify-between gap-4 rounded-none border-t px-6 py-4 text-left"
                        @click="openGroup(group.id)"
                    >
                        <div class="min-w-0">
                            <div class="font-medium">{{ group.group_number }}</div>
                            <div class="truncate text-sm text-muted-foreground">{{ group.name }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">{{ group.passenger_count }} pax</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <MoneyText class="font-medium" :amount="group.balance" :currency="company.base_currency" />
                            <Badge class="mt-1 block" :variant="Number(group.balance || 0) <= 0 ? 'default' : 'secondary'">
                                {{ Number(group.balance || 0) <= 0 ? 'Paid' : 'Due' }}
                            </Badge>
                        </div>
                    </Button>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups`)">
                <Plane class="mr-2 h-4 w-4" />{{ isAgent ? 'My Groups' : 'Groups' }}
            </Button>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers`)">
                <ScrollText class="mr-2 h-4 w-4" />{{ isAgent ? 'My Vouchers' : 'Vouchers' }}
            </Button>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/payments`)">
                <CreditCard class="mr-2 h-4 w-4" />Payments
            </Button>
            <Button v-if="capabilities.canViewAgents" variant="outline" @click="router.get(`/${company.slug}/umrah/agents`)">
                <Users class="mr-2 h-4 w-4" />Agents
            </Button>
            <Button v-if="capabilities.canViewVendors" variant="outline" @click="router.get(`/${company.slug}/umrah/vendors`)">Vendors</Button>
            <Button v-if="capabilities.canViewReports" variant="outline" @click="router.get(`/${company.slug}/umrah/reports/earnings`)">Earnings Report</Button>
        </div>
    </PageShell>
</template>
