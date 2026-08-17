<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eye, Pencil, Power, RotateCcw, Save, Truck, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    providers: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null };
    nextProviderNumber: string;
    canManageProviders: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Transport Providers', href: `/${props.company.slug}/umrah/transport-providers` },
];

const editing = ref<any | null>(null);
const form = useForm({
    vendor_number: props.nextProviderNumber,
    name: '',
    is_company_owned: false,
    phone: '',
    email: '',
    city: '',
    logo_url: '',
    standard_bus_retail_amount: '0',
    standard_bus_cost_amount: '0',
    charge_child_fare: true,
    notes: '',
});

const resetForm = () => {
    editing.value = null;
    form.clearErrors();
    form.vendor_number = props.nextProviderNumber;
    form.name = '';
    form.is_company_owned = false;
    form.phone = '';
    form.email = '';
    form.city = '';
    form.logo_url = '';
    form.standard_bus_retail_amount = '0';
    form.standard_bus_cost_amount = '0';
    form.charge_child_fare = true;
    form.notes = '';
};

const startEdit = (provider: any) => {
    editing.value = provider;
    form.clearErrors();
    form.vendor_number = provider.vendor_number || '';
    form.name = provider.name || '';
    form.is_company_owned = Boolean(provider.is_company_owned);
    form.phone = provider.phone || '';
    form.email = provider.email || '';
    form.city = provider.city || '';
    form.logo_url = provider.logo_url || '';
    form.standard_bus_retail_amount = String(provider.standard_bus_retail_amount ?? 0);
    form.standard_bus_cost_amount = String(provider.standard_bus_cost_amount ?? 0);
    form.charge_child_fare = Boolean(provider.charge_child_fare);
    form.notes = provider.notes || '';
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        standard_bus_retail_amount: Number(data.standard_bus_retail_amount || 0),
        standard_bus_cost_amount: Number(data.standard_bus_cost_amount || 0),
    }));
    const options = { preserveScroll: true, onSuccess: resetForm, onError: (errors: any) => toast.error(Object.values(errors)[0] as string || 'Check the highlighted fields') };
    if (editing.value) {
        form.put(`/${props.company.slug}/umrah/transport-providers/${editing.value.id}`, options);
        return;
    }
    form.post(`/${props.company.slug}/umrah/transport-providers`, options);
};

const statusForm = useForm({ is_active: false });
const updateStatus = (provider: any) => {
    statusForm.is_active = !provider.is_active;
    statusForm.patch(`/${props.company.slug}/umrah/transport-providers/${provider.id}/status`, {
        preserveScroll: true,
        onError: () => toast.error(statusForm.errors.vendor || 'Provider status could not be changed'),
    });
};
</script>

<template>
    <Head title="Transport Providers" />
    <PageShell title="Transport Providers" description="Manage independent standard-bus prices, costs, and supplier balances." :breadcrumbs="breadcrumbs" :icon="Truck">
        <div :class="['grid gap-6', canManageProviders ? 'lg:grid-cols-[460px_minmax(0,1fr)]' : '']">
            <Card v-if="canManageProviders">
                <CardHeader><CardTitle>{{ editing ? 'Edit Transport Provider' : 'New Transport Provider' }}</CardTitle></CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2"><Label>Provider #</Label><Input v-model="form.vendor_number" /><p v-if="form.errors.vendor_number" class="text-xs text-destructive">{{ form.errors.vendor_number }}</p></div>
                            <div class="space-y-2"><Label>Name</Label><Input v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p></div>
                        </div>
                        <Label class="flex items-center gap-3 rounded-md border p-3"><Checkbox v-model="form.is_company_owned" /><span>Company-owned provider</span></Label>
                        <div class="rounded-md border p-3">
                            <div class="mb-3 font-medium">Standard bus fare per passenger</div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2"><Label>Selling price</Label><Input v-model="form.standard_bus_retail_amount" type="number" min="0" step="0.01" /><p v-if="form.errors.standard_bus_retail_amount" class="text-xs text-destructive">{{ form.errors.standard_bus_retail_amount }}</p></div>
                                <div class="space-y-2"><Label>Supplier cost</Label><Input v-model="form.standard_bus_cost_amount" type="number" min="0" step="0.01" /><p v-if="form.errors.standard_bus_cost_amount" class="text-xs text-destructive">{{ form.errors.standard_bus_cost_amount }}</p></div>
                            </div>
                            <Label class="mt-3 flex items-center gap-3"><Checkbox v-model="form.charge_child_fare" /><span>Charge standard bus fare for children</span></Label>
                            <p class="mt-1 text-xs text-muted-foreground">When off, passengers under 12 are excluded. Unnamed aggregate PAX are treated as adults.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2"><div class="space-y-2"><Label>Phone</Label><Input v-model="form.phone" /></div><div class="space-y-2"><Label>City</Label><Input v-model="form.city" /></div></div>
                        <div class="space-y-2"><Label>Email</Label><Input v-model="form.email" type="email" /><p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p></div>
                        <div class="space-y-2"><Label>Notes</Label><Textarea v-model="form.notes" /></div>
                        <div class="grid gap-2 sm:grid-cols-2"><Button v-if="editing" type="button" variant="outline" @click="resetForm"><X class="mr-2 h-4 w-4" />Cancel</Button><Button type="submit" :class="editing ? '' : 'sm:col-span-2'" :disabled="form.processing || !form.name.trim()"><Save class="mr-2 h-4 w-4" />{{ editing ? 'Save Changes' : 'Save Provider' }}</Button></div>
                    </form>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader><CardTitle>Provider List</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader><TableRow><TableHead>Provider</TableHead><TableHead class="text-right">Bus Sale</TableHead><TableHead class="text-right">Bus Cost</TableHead><TableHead>Child Fare</TableHead><TableHead class="text-right">Payable</TableHead><TableHead>Status</TableHead><TableHead class="text-right">Action</TableHead></TableRow></TableHeader>
                        <TableBody>
                            <TableEmpty v-if="!providers.data.length" :colspan="7">No transport providers yet.</TableEmpty>
                            <TableRow v-for="provider in providers.data" :key="provider.id" :class="{ 'opacity-60': !provider.is_active }">
                                <TableCell><div class="font-medium">{{ provider.name }}</div><div class="text-xs text-muted-foreground">{{ provider.vendor_number }}</div></TableCell>
                                <TableCell class="text-right"><MoneyText :amount="provider.standard_bus_retail_amount" :currency="company.base_currency" /></TableCell>
                                <TableCell class="text-right"><MoneyText :amount="provider.standard_bus_cost_amount" :currency="company.base_currency" /></TableCell>
                                <TableCell><Badge variant="outline">{{ provider.charge_child_fare ? 'Charged' : 'Free' }}</Badge></TableCell>
                                <TableCell class="text-right font-semibold"><MoneyText :amount="provider.balance" :currency="company.base_currency" /></TableCell>
                                <TableCell><Badge :variant="provider.is_active ? 'default' : 'secondary'">{{ provider.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                                <TableCell class="text-right"><Button type="button" variant="ghost" size="icon" @click="router.get(`/${company.slug}/umrah/transport-providers/${provider.id}`)"><Eye class="h-4 w-4" /></Button><Button v-if="canManageProviders" type="button" variant="ghost" size="icon" @click="startEdit(provider)"><Pencil class="h-4 w-4" /></Button><Button v-if="canManageProviders" type="button" variant="ghost" size="icon" :disabled="statusForm.processing" @click="updateStatus(provider)"><Power v-if="provider.is_active" class="h-4 w-4" /><RotateCcw v-else class="h-4 w-4" /></Button></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <RecordPagination :current-page="providers.current_page" :last-page="providers.last_page" :from="providers.from" :to="providers.to" :total="providers.total" :previous-url="providers.prev_page_url" :next-url="providers.next_page_url" />
                </CardContent>
            </Card>
        </div>
    </PageShell>
</template>
