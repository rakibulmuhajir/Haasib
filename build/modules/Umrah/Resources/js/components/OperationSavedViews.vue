<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormFeedback } from '@/composables/useFormFeedback';

type SavedView = { id: string; name: string; filters: Record<string, string | null> };
const props = defineProps<{ companySlug: string; views: SavedView[]; applied: Record<string, string | null> }>();
const form = useForm({ name: '' });
const opening = ref(false);
const { showError } = useFormFeedback();
const base = () => `/${props.companySlug}/umrah/operations`;
const save = () => {
    form.transform((data) => ({ ...props.applied, ...data })).post(`${base()}/views`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors) => showError(errors),
    });
};
const open = (view: SavedView) => {
    router.get(base(), view.filters, {
        preserveState: false,
        onStart: () => { opening.value = true; },
        onFinish: () => { opening.value = false; },
        onError: (errors) => showError(errors),
    });
};
const remove = (view: SavedView) => {
    form.transform(() => ({})).delete(`${base()}/views/${view.id}`, {
        preserveScroll: true,
        onError: (errors) => showError(errors),
    });
};
</script>

<template>
    <section class="space-y-2 border border-rule-default p-3" aria-label="My saved Operations views">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium">My saved views</span>
            <div v-for="view in views" :key="view.id" class="flex items-center border border-rule-subtle">
                <Button size="sm" variant="ghost" :disabled="opening || form.processing" @click="open(view)">{{ view.name }}</Button>
                <Button size="sm" variant="ghost" :aria-label="`Remove saved view ${view.name}`" :disabled="opening || form.processing" @click="remove(view)">×</Button>
            </div>
        </div>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="save">
            <div class="space-y-1">
                <Label for="operation-view-name" class="text-xs">View name</Label>
                <Input id="operation-view-name" v-model="form.name" maxlength="80" placeholder="e.g. Tomorrow’s arrivals" class="h-8" :aria-invalid="!!form.errors.name" />
            </div>
            <Button type="submit" size="sm" variant="outline" :disabled="form.processing || opening || !form.name.trim()">{{ form.processing ? 'Saving…' : 'Save applied filters' }}</Button>
        </form>
        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
        <p class="text-xs text-text-secondary">Personal to you in this company. Saving the same name replaces it. Today/tomorrow roll forward; custom dates stay fixed. Removing a view only removes its shortcut.</p>
    </section>
</template>
