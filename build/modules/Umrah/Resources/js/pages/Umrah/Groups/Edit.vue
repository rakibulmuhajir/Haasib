<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { Plane, Save } from 'lucide-vue-next';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string };
    group: any;
    requiresOverrideReason: boolean;
    canManageVendors: boolean;
    vendors: any[];
    transportVendors: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
    {
        title: props.group.group_number,
        href: `/${props.company.slug}/umrah/groups/${props.group.id}`,
    },
    {
        title: 'Edit',
        href: `/${props.company.slug}/umrah/groups/${props.group.id}/edit`,
    },
];

const form = useForm({
    name: props.group.name || '',
    vendor_id: props.group.vendor_id || 'none',
    mandatory_transport_vendor_id: props.group.mandatory_transport_vendor_id || 'none',
    travel_date: String(props.group.travel_date || '').slice(0, 10),
    flight_airline: props.group.flight_info?.airline || '',
    flight_number: props.group.flight_info?.number || '',
    flight_notes: props.group.flight_info?.notes || '',
    hotel_makkah: props.group.hotel_info?.makkah || '',
    hotel_madinah: props.group.hotel_info?.madinah || '',
    hotel_notes: props.group.hotel_info?.notes || '',
    notes: props.group.notes || '',
    override_reason: '',
});

watch(
    () => form.vendor_id,
    (vendorId) => {
        if (!props.canManageVendors) return;
        const vendor = props.vendors.find((item) => item.id === vendorId);
        if (!vendor) return;
        form.mandatory_transport_vendor_id = vendor.provides_mandatory_transport
            ? vendor.id
            : vendor.mandatory_transport_vendor_id || 'none';
    },
);

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            vendor_id: data.vendor_id === 'none' ? null : data.vendor_id,
            mandatory_transport_vendor_id:
                data.mandatory_transport_vendor_id === 'none'
                    ? null
                    : data.mandatory_transport_vendor_id,
        }))
        .put(`/${props.company.slug}/umrah/groups/${props.group.id}`, {
            onSuccess: () => toast.success('Visa group updated successfully'),
            onError: () => toast.error('Failed to update visa group'),
        });
</script>

<template>
    <Head :title="`Edit ${group.group_number}`" />
    <PageShell
        title="Edit Visa Group"
        description="Update the group schedule and operational details."
        :breadcrumbs="breadcrumbs"
        :icon="Plane"
    >
        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader><CardTitle>Group</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label>Name</Label
                        ><Input v-model="form.name" required />
                        <p
                            v-if="form.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div v-if="canManageVendors" class="space-y-2">
                        <Label>Visa vendor</Label>
                        <Select v-model="form.vendor_id">
                            <SelectTrigger><SelectValue placeholder="Select vendor" /></SelectTrigger>
                            <SelectContent><SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}{{ vendor.is_default ? ' · Default' : '' }}</SelectItem></SelectContent>
                        </Select>
                    </div>
                    <div v-if="canManageVendors && group.transport_mode === 'standard_bus'" class="space-y-2">
                        <Label>Mandatory transport provider</Label>
                        <Select v-model="form.mandatory_transport_vendor_id">
                            <SelectTrigger><SelectValue placeholder="Select provider" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="vendor in transportVendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</SelectItem>
                                <SelectItem v-for="vendor in vendors.filter((item) => item.provides_mandatory_transport)" :key="vendor.id" :value="vendor.id">{{ vendor.name }} · Provides transport</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Travel date</Label
                        ><Input v-model="form.travel_date" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label>Airline</Label
                        ><Input v-model="form.flight_airline" />
                    </div>
                    <div class="space-y-2">
                        <Label>Flight #</Label
                        ><Input v-model="form.flight_number" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label>Flight notes</Label
                        ><Textarea v-model="form.flight_notes" />
                    </div>
                    <div class="space-y-2">
                        <Label>Makkah hotel</Label
                        ><Input v-model="form.hotel_makkah" />
                    </div>
                    <div class="space-y-2">
                        <Label>Madinah hotel</Label
                        ><Input v-model="form.hotel_madinah" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label>Hotel notes</Label
                        ><Textarea v-model="form.hotel_notes" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label>Group notes</Label
                        ><Textarea v-model="form.notes" />
                    </div>
                    <div
                        v-if="requiresOverrideReason"
                        class="space-y-2 md:col-span-2"
                    >
                        <Label>Reason for changing a started trip</Label>
                        <Textarea
                            v-model="form.override_reason"
                            required
                            placeholder="Record why this company override is necessary"
                        />
                        <p
                            v-if="form.errors.override_reason"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.override_reason }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="
                        router.get(`/${company.slug}/umrah/groups/${group.id}`)
                    "
                    >Cancel</Button
                >
                <Button type="submit" :disabled="form.processing"
                    ><Save class="mr-2 h-4 w-4" />Save Changes</Button
                >
            </div>
        </form>
    </PageShell>
</template>
