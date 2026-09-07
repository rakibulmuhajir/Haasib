<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useFormFeedback } from '@/composables/useFormFeedback';
import { Badge } from '@/components/ui/badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Option = { id: string; label?: string; name?: string; is_active?: boolean };
type Rate = Record<string, any>;

const props = withDefaults(defineProps<{
    companySlug: string;
    baseCurrency: string;
    categories: Option[];
    agents: Option[];
    rates: Rate[];
    targets: Record<string, Option[]>;
    serviceTypes: Record<string, string>;
    scopeTypes: Record<string, string>;
    calculationTypes: Record<string, string>;
    canManage: boolean;
    lockedAgent?: { id: string; name: string } | null;
    initialTargetId?: string | null;
}>(), {
    lockedAgent: null,
    initialTargetId: null,
});

const { showError } = useFormFeedback();
const editingId = ref<string | null>(null);
const formOpen = ref(false);
const processingRateId = ref<string | null>(null);
const today = new Date().toISOString().slice(0, 10);

const initialService = Object.keys(props.serviceTypes).find((service) =>
    (props.targets[service] || []).some((target) => target.id === props.initialTargetId),
) || Object.keys(props.serviceTypes)[0] || '';

const form = useForm({
    service_type: initialService,
    target_id: props.initialTargetId || props.targets[initialService]?.[0]?.id || '',
    scope_type: props.lockedAgent ? 'agent' : 'default',
    scope_id: props.lockedAgent?.id || '',
    calculation_type: 'set_price',
    amount: '',
    percentage: '',
    cost_amount: '',
    effective_from: today,
    effective_until: '',
    notes: '',
});

const availableTargets = computed(() => props.targets[form.service_type] || []);
const scopeOptions = computed(() => {
    if (form.scope_type === 'category') return props.categories.filter((item) => item.is_active !== false);
    if (form.scope_type === 'agent') return props.agents;
    return [];
});
const usesPercentage = computed(() => form.calculation_type.endsWith('_percentage'));
const isDefault = computed(() => form.scope_type === 'default');
const activeRates = computed(() => props.rates.filter((rate) => rate.is_active));
const inactiveRates = computed(() => props.rates.filter((rate) => !rate.is_active));

// Apply dependent defaults immediately so loading a saved rule can then restore
// its target, scope and amount without a queued watcher overwriting them.
watch(() => form.service_type, () => {
    if (!availableTargets.value.some((target) => target.id === form.target_id)) {
        form.target_id = availableTargets.value[0]?.id || '';
    }
}, { flush: 'sync' });
watch(() => form.scope_type, (scope) => {
    if (props.lockedAgent) {
        form.scope_id = props.lockedAgent.id;
        return;
    }
    form.scope_id = scope === 'default' ? '' : scopeOptions.value[0]?.id || '';
    if (scope === 'default') form.calculation_type = 'set_price';
}, { flush: 'sync' });
watch(() => form.calculation_type, () => {
    if (usesPercentage.value) form.amount = '';
    else form.percentage = '';
}, { flush: 'sync' });

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.service_type = initialService;
    form.target_id = props.initialTargetId || props.targets[initialService]?.[0]?.id || '';
    form.scope_type = props.lockedAgent ? 'agent' : 'default';
    form.scope_id = props.lockedAgent?.id || '';
    form.calculation_type = 'set_price';
    form.effective_from = today;
};

const startNew = () => {
    resetForm();
    formOpen.value = true;
};

const editRate = (rate: Rate) => {
    editingId.value = rate.id;
    form.service_type = rate.service_type;
    form.target_id = rate.target_id;
    form.scope_type = rate.scope_type;
    form.scope_id = rate.scope_id || '';
    form.calculation_type = rate.calculation_type;
    form.amount = rate.amount == null ? '' : String(rate.amount);
    form.percentage = rate.percentage == null ? '' : String(rate.percentage);
    form.cost_amount = rate.cost_amount == null ? '' : String(rate.cost_amount);
    form.effective_from = String(rate.effective_from).slice(0, 10);
    form.effective_until = rate.effective_until ? String(rate.effective_until).slice(0, 10) : '';
    form.notes = rate.notes || '';
    form.clearErrors();
    formOpen.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            resetForm();
        },
        onError: (errors: Record<string, string>) => showError(errors),
    };
    const base = `/${props.companySlug}/umrah/settings/pricing/rates`;
    if (editingId.value) form.put(`${base}/${editingId.value}`, options);
    else form.post(base, options);
};

const setActive = (rate: Rate, isActive: boolean) => {
    processingRateId.value = rate.id;
    router.patch(
        `/${props.companySlug}/umrah/settings/pricing/rates/${rate.id}/status`,
        { is_active: isActive },
        {
            preserveScroll: true,
            onError: (errors) => showError(errors),
            onFinish: () => { processingRateId.value = null; },
        },
    );
};

const displayValue = (rate: Rate) => {
    if (rate.calculation_type === 'set_price') return `${props.baseCurrency} ${Number(rate.amount).toFixed(2)}`;
    if (rate.calculation_type.endsWith('_percentage')) return `${Number(rate.percentage).toFixed(2).replace(/\.00$/, '')}%`;
    return `${props.baseCurrency} ${Number(rate.amount).toFixed(2)}`;
};
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Rate rules</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Agent wins over category; category wins over the dated default. Rules never stack.
                </p>
            </div>
            <Button v-if="canManage" @click="startNew">
                <Plus class="mr-2 h-4 w-4" />Add rate rule
            </Button>
        </div>

        <Card v-if="formOpen" variant="form">
            <CardHeader class="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>{{ editingId ? 'Edit rate rule' : 'New rate rule' }}</CardTitle>
                    <CardDescription>Dates are inclusive. Existing bookings keep their saved price.</CardDescription>
                </div>
                <Button variant="ghost" size="icon" type="button" aria-label="Close rate form" @click="formOpen = false">
                    <X class="h-4 w-4" />
                </Button>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" novalidate @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="space-y-2">
                            <Label>Service</Label>
                            <Select v-model="form.service_type">
                                <SelectTrigger><SelectValue placeholder="Choose service" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in serviceTypes" :key="value" :value="value">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.service_type" />
                        </div>
                        <div class="space-y-2 md:col-span-1 xl:col-span-2">
                            <Label>Vendor or service</Label>
                            <Select v-model="form.target_id">
                                <SelectTrigger><SelectValue placeholder="Choose vendor or service" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="target in availableTargets" :key="target.id" :value="target.id">{{ target.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.target_id" />
                        </div>
                        <div v-if="!lockedAgent" class="space-y-2">
                            <Label>Applies to</Label>
                            <Select v-model="form.scope_type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in scopeTypes" :key="value" :value="value">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.scope_type" />
                        </div>
                        <div v-else class="space-y-2">
                            <Label>Applies to</Label>
                            <div class="flex h-9 items-center border px-3 text-sm">{{ lockedAgent.name }}</div>
                        </div>
                    </div>

                    <div v-if="!isDefault && !lockedAgent" class="max-w-xl space-y-2">
                        <Label>{{ form.scope_type === 'category' ? 'Agent category' : 'Agent' }}</Label>
                        <Select v-model="form.scope_id">
                            <SelectTrigger><SelectValue placeholder="Choose one" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in scopeOptions" :key="option.id" :value="option.id">{{ option.name || option.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.scope_id" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="space-y-2">
                            <Label>Pricing method</Label>
                            <Select v-model="form.calculation_type" :disabled="isDefault">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in calculationTypes" :key="value" :value="value">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.calculation_type" />
                        </div>
                        <div class="space-y-2">
                            <Label>{{ usesPercentage ? 'Percentage' : isDefault ? 'Selling price' : 'Amount' }}</Label>
                            <Input v-if="usesPercentage" v-model="form.percentage" type="number" min="0" step="0.01" />
                            <Input v-else v-model="form.amount" type="number" min="0" step="0.01" />
                            <InputError :message="usesPercentage ? form.errors.percentage : form.errors.amount" />
                        </div>
                        <div v-if="isDefault" class="space-y-2">
                            <Label>Supplier cost <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <Input v-model="form.cost_amount" type="number" min="0" step="0.01" />
                            <InputError :message="form.errors.cost_amount" />
                        </div>
                        <div class="space-y-2">
                            <Label>Effective from</Label>
                            <Input v-model="form.effective_from" type="date" />
                            <InputError :message="form.errors.effective_from" />
                        </div>
                        <div class="space-y-2">
                            <Label>Effective until <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <Input v-model="form.effective_until" type="date" />
                            <InputError :message="form.errors.effective_until" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Internal note <span class="font-normal text-muted-foreground">(optional)</span></Label>
                        <Textarea v-model="form.notes" rows="2" />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                        <Button type="button" variant="outline" :disabled="form.processing" @click="formOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing || !form.target_id">
                            {{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Save rate rule' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="overflow-x-auto border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Service</TableHead>
                        <TableHead>Vendor / item</TableHead>
                        <TableHead>Applies to</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Value</TableHead>
                        <TableHead>Dates</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="rates.length === 0">
                        <TableCell colspan="7" class="h-28 text-center text-muted-foreground">
                            No rate rules yet. Current vendor and room rates remain the fallback.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="rate in [...activeRates, ...inactiveRates]" :key="rate.id" :class="{ 'opacity-55': !rate.is_active }">
                        <TableCell class="font-medium">{{ rate.service_label }}</TableCell>
                        <TableCell>{{ rate.target_label }}</TableCell>
                        <TableCell>{{ rate.scope_label }}</TableCell>
                        <TableCell>{{ rate.calculation_label }}</TableCell>
                        <TableCell class="tabular-nums">{{ displayValue(rate) }}</TableCell>
                        <TableCell class="whitespace-nowrap">
                            {{ String(rate.effective_from).slice(0, 10) }} → {{ rate.effective_until ? String(rate.effective_until).slice(0, 10) : 'No end' }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Badge :variant="rate.is_active ? 'success' : 'secondary'">{{ rate.is_active ? 'Active' : 'Inactive' }}</Badge>
                                <Button v-if="canManage" size="sm" variant="ghost" @click="editRate(rate)">
                                    <Pencil class="h-4 w-4" /><span class="sr-only">Edit</span>
                                </Button>
                                <Button
                                    v-if="canManage"
                                    size="sm"
                                    variant="outline"
                                    :disabled="processingRateId === rate.id"
                                    @click="setActive(rate, !rate.is_active)"
                                >
                                    {{ rate.is_active ? 'Deactivate' : 'Activate' }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
