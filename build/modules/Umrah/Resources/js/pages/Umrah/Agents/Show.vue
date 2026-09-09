<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { useFormFeedback } from '@/composables/useFormFeedback';
import CommercialPricingWorkspace from '../../../components/CommercialPricingWorkspace.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Tags, Trash2, Undo2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    agent: any;
    canManageAgents: boolean;
    canCreateRefund: boolean;
    canManagePricing: boolean;
    canManageVoucherSettings: boolean;
    categories: any[];
    agents: any[];
    rates: any[];
    targets: Record<string, any[]>;
    serviceTypes: Record<string, string>;
    scopeTypes: Record<string, string>;
    calculationTypes: Record<string, string>;
}>();

const { showError } = useFormFeedback();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
    {
        title: props.agent.name,
        href: `/${props.company.slug}/umrah/agents/${props.agent.id}`,
    },
];

const removeForm = useForm({});
const accessForm = useForm({
    can_create_voucher: Boolean(props.agent.can_create_voucher),
    can_approve_voucher: Boolean(props.agent.can_approve_voucher),
    can_edit_group: Boolean(props.agent.can_edit_group),
    can_edit_voucher: Boolean(props.agent.can_edit_voucher),
    voucher_cutoff_hours: String(props.agent.voucher_cutoff_hours || 6),
});
const removeDialogOpen = ref(false);
const categoryForm = useForm({
    pricing_category_id: props.agent.pricing_category_id || '',
});

const confirmRemoveAgent = () => {
    removeForm.delete(`/${props.company.slug}/umrah/agents/${props.agent.id}`, {
        onError: () => toast.error('Failed to remove agent'),
    });
};
const saveAccess = () =>
    accessForm
        .transform((data) => ({
            ...data,
            voucher_cutoff_hours: Number(data.voucher_cutoff_hours),
        }))
        .put(
            `/${props.company.slug}/umrah/agents/${props.agent.id}/voucher-access`,
            {
                preserveScroll: true,
                onError: () =>
                    toast.error('Failed to update agent voucher access'),
            },
        );
const savePricingCategory = () =>
    categoryForm.transform((data) => ({
        pricing_category_id: data.pricing_category_id === 'none' || !data.pricing_category_id
            ? null
            : data.pricing_category_id,
    })).put(
        `/${props.company.slug}/umrah/agents/${props.agent.id}/pricing-category`,
        {
            preserveScroll: true,
            onError: (errors) => showError(errors),
        },
    );
</script>

<template>
    <Head :title="agent.name" />
    <PageShell
        :title="agent.name"
        :description="`${agent.agent_number} · ${agent.phone || 'No phone'} · ${agent.country || 'No country'}`"
        :breadcrumbs="breadcrumbs"
        :icon="Users"
    >
        <template #actions>
            <Button v-if="canManageVoucherSettings" variant="outline" @click="router.get(`/${company.slug}/umrah/settings/voucher`, { target: `agent:${agent.id}` })">Voucher contacts &amp; footer</Button>
            <Button
                v-if="canCreateRefund"
                variant="outline"
                @click="
                    router.get(`/${company.slug}/umrah/refunds/create`, {
                        party_type: 'agent',
                        party_id: agent.id,
                    })
                "
            >
                <Undo2 class="mr-2 h-4 w-4" />
                Request a refund
            </Button>
            <template v-if="canManageAgents">
                <Button
                    variant="outline"
                    @click="
                        router.get(`/${company.slug}/umrah/agents/${agent.id}/edit`)
                    "
                >
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
                </Button>
                <Button
                    variant="destructive"
                    :disabled="removeForm.processing"
                    @click="removeDialogOpen = true"
                >
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                </Button>
            </template>
        </template>

        <Tabs default-value="overview" class="space-y-5">
            <TabsList>
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger v-if="canManagePricing" value="pricing">Pricing</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" class="space-y-5">
        <div v-if="agent.logo_url" class="flex items-center gap-3">
            <img
                :src="agent.logo_url"
                :alt="`${agent.name} logo`"
                class="h-16 w-16 rounded-md border object-contain"
            />
            <div>
                <div class="font-medium">{{ agent.name }}</div>
                <div class="text-sm text-muted-foreground">
                    {{ agent.email || agent.phone || agent.agent_number }}
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card variant="figure"
                ><CardHeader><CardTitle>Total Receivable</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="agent.total_receivable"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
            <Card variant="figure"
                ><CardHeader><CardTitle>Paid</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="agent.total_paid"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
            <Card variant="figure"
                ><CardHeader><CardTitle>Balance</CardTitle></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="agent.balance"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
        </div>

        <Card v-if="canManageAgents" variant="form">
            <CardHeader
                ><CardTitle>Login and Travel Access</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="rounded-md border p-3">
                    <div class="text-sm text-muted-foreground">Username</div>
                    <div class="font-medium">
                        {{ agent.user?.username || 'No login access' }}
                    </div>
                </div>
                <form novalidate
                    v-if="agent.user_id"
                    class="space-y-3"
                    @submit.prevent="saveAccess"
                >
                    <Label class="flex cursor-pointer items-center gap-2"
                        ><Checkbox
                            v-model="accessForm.can_create_voucher"
                        />Create vouchers</Label
                    >
                    <Label class="flex cursor-pointer items-center gap-2"
                        ><Checkbox
                            v-model="accessForm.can_approve_voucher"
                        />Approve vouchers</Label
                    >
                    <Label class="flex cursor-pointer items-center gap-2"
                        ><Checkbox v-model="accessForm.can_edit_group" />Edit
                        own groups before travel</Label
                    >
                    <Label class="flex cursor-pointer items-center gap-2"
                        ><Checkbox v-model="accessForm.can_edit_voucher" />Edit
                        draft vouchers and schedules</Label
                    >
                    <div class="max-w-sm space-y-2">
                        <Label>Creation and change cutoff</Label
                        ><Select v-model="accessForm.voucher_cutoff_hours"
                            ><SelectTrigger><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="hours in [2, 6, 12, 18, 24, 48]"
                                    :key="hours"
                                    :value="String(hours)"
                                    >{{ hours }} hours before flight</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <Button type="submit" :disabled="accessForm.processing"
                        >Save Access</Button
                    >
                </form>
            </CardContent>
        </Card>

        <Card variant="detail">
            <CardHeader><CardTitle>Groups</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-if="!agent.groups?.length"
                    class="text-sm text-muted-foreground"
                >
                    No groups for this agent yet.
                </div>
                <div
                    v-for="group in agent.groups"
                    :key="group.id"
                    class="flex items-center justify-between rounded-md border p-3"
                >
                    <div>
                        <div class="font-medium">
                            {{ group.group_number }} · {{ group.name }}
                        </div>
                        <div class="text-sm text-muted-foreground flex items-center gap-1">
                            <StatusBadge :status="group.status" /> ·
                            {{ group.passenger_count }} passengers
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            router.get(
                                `/${company.slug}/umrah/groups/${group.id}`,
                            )
                        "
                        >Open</Button
                    >
                </div>
            </CardContent>
        </Card>
            </TabsContent>

            <TabsContent v-if="canManagePricing" value="pricing" class="space-y-6">
                <Card variant="form">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2"><Tags class="h-4 w-4" />Shared pricing category</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="flex flex-col gap-3 sm:flex-row sm:items-end" novalidate @submit.prevent="savePricingCategory">
                            <div class="w-full max-w-md space-y-2">
                                <Label>Category</Label>
                                <Select v-model="categoryForm.pricing_category_id">
                                    <SelectTrigger><SelectValue placeholder="Normal pricing — no category" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Normal pricing — no category</SelectItem>
                                        <SelectItem v-for="category in categories.filter((item) => item.is_active)" :key="category.id" :value="category.id">{{ category.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="categoryForm.errors.pricing_category_id" />
                            </div>
                            <Button type="submit" :disabled="categoryForm.processing">
                                {{ categoryForm.processing ? 'Saving…' : 'Save category' }}
                            </Button>
                        </form>
                        <p class="mt-3 text-sm text-muted-foreground">Agent-specific rules below take priority over this category.</p>
                    </CardContent>
                </Card>

                <CommercialPricingWorkspace
                    :company-slug="company.slug"
                    :base-currency="company.base_currency"
                    :categories="categories"
                    :agents="agents"
                    :rates="rates"
                    :targets="targets"
                    :service-types="serviceTypes"
                    :scope-types="scopeTypes"
                    :calculation-types="calculationTypes"
                    :can-manage="canManagePricing"
                    :locked-agent="{ id: agent.id, name: agent.name }"
                />
            </TabsContent>
        </Tabs>

        <ConfirmDialog
            v-model:open="removeDialogOpen"
            variant="destructive"
            title="Remove Agent"
            :description="`Remove ${agent.name} from future use? Existing groups keep their history.`"
            confirm-text="Remove Agent"
            :loading="removeForm.processing"
            @confirm="confirmRemoveAgent"
        />
    </PageShell>
</template>
