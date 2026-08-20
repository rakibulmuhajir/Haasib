<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    agents: {
        data: any[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search?: string };
    canManageAgents: boolean;
}>();

const search = ref(props.filters.search || '');
const removeForm = useForm({});
const agentToRemove = ref<any | null>(null);
const removeDialogOpen = ref(false);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
];

/**
 * An agent list is a register of who owes what, so the balance is a figure in
 * the figure column and everything else is language. The actions column only
 * exists for someone who can act, which is why the array is computed.
 */
const columns = computed(() => [
    { key: 'agent_number', label: 'Agent #', kind: 'ref' as const },
    { key: 'name', label: 'Agent', kind: 'text' as const },
    { key: 'phone', label: 'Phone', kind: 'text' as const },
    { key: 'city', label: 'City', kind: 'text' as const },
    { key: 'country', label: 'Country', kind: 'text' as const },
    { key: 'balance', label: 'Balance', kind: 'amount' as const },
    ...(props.canManageAgents
        ? [
              {
                  key: 'actions',
                  label: '',
                  kind: 'text' as const,
                  class: 'text-right',
                  headerClass: 'text-right',
              },
          ]
        : []),
]);

const applySearch = () =>
    router.get(
        `/${props.company.slug}/umrah/agents`,
        { search: search.value },
        { preserveState: true },
    );

const removeAgent = (agent: any) => {
    agentToRemove.value = agent;
    removeDialogOpen.value = true;
};

const confirmRemoveAgent = () => {
    if (!agentToRemove.value) return;

    removeForm.delete(
        `/${props.company.slug}/umrah/agents/${agentToRemove.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                removeDialogOpen.value = false;
                agentToRemove.value = null;
            },
            onError: () => toast.error('Failed to remove agent'),
        },
    );
};
</script>

<template>
    <Head title="Agents" />
    <PageShell
        title="Agents"
        description="People or companies sending passports and groups."
        :breadcrumbs="breadcrumbs"
        :icon="Users"
    >
        <template v-if="canManageAgents" #actions>
            <Button @click="router.get(`/${company.slug}/umrah/agents/create`)">
                <Plus class="mr-2 h-4 w-4" />
                New Agent
            </Button>
        </template>

        <div class="relative max-w-xl">
            <Search
                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-10"
                placeholder="Search agents..."
                @keyup.enter="applySearch"
            />
        </div>

        <Card>
            <CardContent class="p-0">
                <LedgerRegister
                    :data="agents.data"
                    :columns="columns"
                    clickable
                    @row-click="
                        (row) =>
                            router.get(
                                `/${company.slug}/umrah/agents/${row.id}`,
                            )
                    "
                >
                    <template #empty>No agents yet.</template>

                    <template #cell-phone="{ row }">{{
                        row.phone || '—'
                    }}</template>
                    <template #cell-city="{ row }">{{
                        row.city || '—'
                    }}</template>
                    <template #cell-country="{ row }">{{
                        row.country || '—'
                    }}</template>

                    <template #cell-balance="{ row }">
                        <MoneyText
                            :amount="row.balance"
                            :currency="company.base_currency"
                            class="font-semibold"
                        />
                    </template>

                    <template #cell-actions="{ row }">
                        <div
                            class="flex items-center justify-end gap-1"
                            @click.stop
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="
                                    router.get(
                                        `/${company.slug}/umrah/agents/${row.id}/edit`,
                                    )
                                "
                            >
                                <Pencil class="h-4 w-4" />
                                <span class="sr-only">Edit {{ row.name }}</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="removeForm.processing"
                                @click="removeAgent(row)"
                            >
                                <Trash2 class="h-4 w-4" />
                                <span class="sr-only"
                                    >Remove {{ row.name }}</span
                                >
                            </Button>
                        </div>
                    </template>
                </LedgerRegister>
                <RecordPagination
                    :current-page="agents.current_page"
                    :last-page="agents.last_page"
                    :from="agents.from"
                    :to="agents.to"
                    :total="agents.total"
                    :previous-url="agents.prev_page_url"
                    :next-url="agents.next_page_url"
                />
            </CardContent>
        </Card>

        <ConfirmDialog
            v-model:open="removeDialogOpen"
            variant="destructive"
            title="Remove Agent"
            :description="`Remove ${agentToRemove?.name || 'this agent'} from future use? Existing groups keep their history.`"
            confirm-text="Remove Agent"
            :loading="removeForm.processing"
            @confirm="confirmRemoveAgent"
        />
    </PageShell>
</template>
