<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LedgerRegister from '@/components/LedgerRegister.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Power, RotateCcw, Save, Users, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { slug: string };
    drivers: Array<{
        id: string;
        name: string;
        phone?: string | null;
        notes?: string | null;
        is_active: boolean;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Drivers', href: `/${props.company.slug}/umrah/settings/drivers` },
];

/**
 * A settings list is still a register. It gets the same headings, the same
 * banding and the same status chip as every other table in the application,
 * because a driver being inactive and an invoice being voided are the same
 * kind of fact and should not be drawn two different ways.
 */
const columns = [
    { key: 'name', label: 'Driver', kind: 'text' as const },
    { key: 'phone', label: 'Phone', kind: 'text' as const },
    { key: 'notes', label: 'Notes', kind: 'text' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const form = useForm({
    name: '',
    phone: '',
    notes: '',
});

const statusForm = useForm({ is_active: false });
const editingDriver = ref<(typeof props.drivers)[number] | null>(null);
const driverToRemove = ref<{ id: string; name: string } | null>(null);
const removeDialogOpen = ref(false);

const resetForm = () => {
    editingDriver.value = null;
    form.reset();
    form.clearErrors();
};
const startEdit = (driver: (typeof props.drivers)[number]) => {
    editingDriver.value = driver;
    form.name = driver.name;
    form.phone = driver.phone || '';
    form.notes = driver.notes || '';
    form.clearErrors();
};
const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetForm(),
        onError: () =>
            toast.error(
                editingDriver.value
                    ? 'Failed to update driver'
                    : 'Failed to add driver',
            ),
    };
    if (editingDriver.value)
        form.put(
            `/${props.company.slug}/umrah/settings/drivers/${editingDriver.value.id}`,
            options,
        );
    else form.post(`/${props.company.slug}/umrah/settings/drivers`, options);
};

const removeDriver = (driver: { id: string; name: string }) => {
    driverToRemove.value = driver;
    removeDialogOpen.value = true;
};

const confirmRemoveDriver = () => {
    if (!driverToRemove.value) return;

    statusForm.is_active = false;
    statusForm.patch(
        `/${props.company.slug}/umrah/settings/drivers/${driverToRemove.value.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => {
                removeDialogOpen.value = false;
                driverToRemove.value = null;
            },
            onError: () =>
                toast.error(
                    statusForm.errors.driver || 'Failed to deactivate driver',
                ),
        },
    );
};
const reactivateDriver = (driver: (typeof props.drivers)[number]) => {
    statusForm.is_active = true;
    statusForm.patch(
        `/${props.company.slug}/umrah/settings/drivers/${driver.id}/status`,
        {
            preserveScroll: true,
            onError: () =>
                toast.error(
                    statusForm.errors.driver || 'Failed to reactivate driver',
                ),
        },
    );
};
</script>

<template>
    <Head title="Drivers" />
    <PageShell
        title="Drivers"
        description="Drivers available for Umrah group transport."
        :breadcrumbs="breadcrumbs"
        :icon="Users"
    >
        <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
            <Card class="min-w-0" variant="form">
                <CardHeader
                    ><CardTitle>{{
                        editingDriver ? 'Edit Driver' : 'Add Driver'
                    }}</CardTitle></CardHeader
                >
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Name</Label>
                            <Input
                                v-model="form.name"
                                placeholder="Driver name"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Phone</Label>
                            <Input v-model="form.phone" placeholder="+966..." />
                            <p
                                v-if="form.errors.phone"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Notes</Label>
                            <Textarea v-model="form.notes" />
                        </div>
                        <div
                            class="grid gap-2"
                            :class="editingDriver ? 'grid-cols-2' : ''"
                        >
                            <Button
                                v-if="editingDriver"
                                type="button"
                                variant="outline"
                                @click="resetForm"
                                ><X class="mr-2 size-4" />Cancel</Button
                            ><Button type="submit" :disabled="form.processing"
                                ><Save class="mr-2 h-4 w-4" />{{
                                    editingDriver
                                        ? 'Save Changes'
                                        : 'Save Driver'
                                }}</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="min-w-0" variant="register">
                <CardHeader>
                    <CardTitle>Available Drivers</CardTitle>
                </CardHeader>
                <CardContent>
                    <LedgerRegister :data="drivers" :columns="columns">
                        <template #empty>No drivers yet.</template>

                        <template #cell-phone="{ row }">{{
                            row.phone || '—'
                        }}</template>

                        <template #cell-notes="{ row }">
                            <span class="block max-w-72 truncate text-text-secondary">{{
                                row.notes || '—'
                            }}</span>
                        </template>

                        <template #cell-status="{ row }">
                            <StatusBadge
                                :status="row.is_active ? 'active' : 'inactive'"
                            />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Edit driver"
                                    @click="startEdit(row)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    v-if="row.is_active"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Deactivate driver"
                                    :disabled="statusForm.processing"
                                    @click="removeDriver(row)"
                                >
                                    <Power class="size-4" />
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Reactivate driver"
                                    :disabled="statusForm.processing"
                                    @click="reactivateDriver(row)"
                                >
                                    <RotateCcw class="size-4" />
                                </Button>
                            </div>
                        </template>
                    </LedgerRegister>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="removeDialogOpen"
            variant="destructive"
            title="Deactivate Driver"
            :description="`Deactivate ${driverToRemove?.name || 'this driver'} for future assignments? Existing groups keep their history.`"
            confirm-text="Deactivate Driver"
            :loading="statusForm.processing"
            @confirm="confirmRemoveDriver"
        />
    </PageShell>
</template>
