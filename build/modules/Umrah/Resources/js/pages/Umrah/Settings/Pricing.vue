<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageShell from '@/components/PageShell.vue';
import CommercialPricingWorkspace from '../../../components/CommercialPricingWorkspace.vue';
import { useFormFeedback } from '@/composables/useFormFeedback';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Tags, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    categories: any[];
    agents: any[];
    rates: any[];
    targets: Record<string, any[]>;
    serviceTypes: Record<string, string>;
    scopeTypes: Record<string, string>;
    calculationTypes: Record<string, string>;
    canManagePricing: boolean;
    focus: { agent_id?: string | null; target_id?: string | null };
}>();

const { showError } = useFormFeedback();
const categoryOpen = ref(false);
const editingCategoryId = ref<string | null>(null);
const categoryStatusId = ref<string | null>(null);
const categoryForm = useForm({ name: '', description: '' });
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Setup', href: `/${props.company.slug}/umrah/settings` },
    { title: 'Commercial Pricing' },
];

const startCategory = () => {
    editingCategoryId.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryOpen.value = true;
};
const editCategory = (category: any) => {
    editingCategoryId.value = category.id;
    categoryForm.name = category.name;
    categoryForm.description = category.description || '';
    categoryForm.clearErrors();
    categoryOpen.value = true;
};
const saveCategory = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => { categoryOpen.value = false; categoryForm.reset(); editingCategoryId.value = null; },
        onError: (errors: Record<string, string>) => showError(errors),
    };
    const base = `/${props.company.slug}/umrah/settings/pricing/categories`;
    if (editingCategoryId.value) categoryForm.put(`${base}/${editingCategoryId.value}`, options);
    else categoryForm.post(base, options);
};
const setCategoryActive = (category: any, isActive: boolean) => {
    categoryStatusId.value = category.id;
    router.patch(
        `/${props.company.slug}/umrah/settings/pricing/categories/${category.id}/status`,
        { is_active: isActive },
        {
            preserveScroll: true,
            onError: (errors) => showError(errors),
            onFinish: () => { categoryStatusId.value = null; },
        },
    );
};
</script>

<template>
    <Head title="Commercial Pricing" />
    <PageShell
        title="Commercial Pricing"
        description="One place for dated defaults, category terms and agent-specific prices."
        :breadcrumbs="breadcrumbs"
        :icon="Tags"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/settings`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Setup
            </Button>
        </template>

        <Tabs default-value="rates" class="space-y-5">
            <TabsList>
                <TabsTrigger value="rates">Rate rules</TabsTrigger>
                <TabsTrigger value="categories">Agent categories</TabsTrigger>
            </TabsList>

            <TabsContent value="rates">
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
                    :initial-target-id="focus.target_id"
                />
            </TabsContent>

            <TabsContent value="categories" class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Agent categories</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Optional shared terms for agents without their own rule.</p>
                    </div>
                    <Button v-if="canManagePricing" @click="startCategory"><Plus class="mr-2 h-4 w-4" />Add category</Button>
                </div>

                <Card v-if="categoryOpen" variant="form">
                    <CardHeader class="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{ editingCategoryId ? 'Edit category' : 'New category' }}</CardTitle>
                            <CardDescription>Use business terms such as Standard B2B, Preferred or Volume Partner.</CardDescription>
                        </div>
                        <Button variant="ghost" size="icon" aria-label="Close category form" @click="categoryOpen = false"><X class="h-4 w-4" /></Button>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" novalidate @submit.prevent="saveCategory">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Name</Label>
                                    <Input v-model="categoryForm.name" autofocus />
                                    <InputError :message="categoryForm.errors.name" />
                                </div>
                                <div class="space-y-2">
                                    <Label>Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                                    <Textarea v-model="categoryForm.description" rows="2" />
                                    <InputError :message="categoryForm.errors.description" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 border-t pt-4">
                                <Button type="button" variant="outline" :disabled="categoryForm.processing" @click="categoryOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="categoryForm.processing || !categoryForm.name.trim()">
                                    {{ categoryForm.processing ? 'Saving…' : 'Save category' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="overflow-x-auto border">
                    <Table>
                        <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Description</TableHead><TableHead>Agents</TableHead><TableHead>Status</TableHead><TableHead class="text-right">Actions</TableHead></TableRow></TableHeader>
                        <TableBody>
                            <TableRow v-if="categories.length === 0"><TableCell colspan="5" class="h-28 text-center text-muted-foreground">No categories yet. Every agent currently uses normal pricing.</TableCell></TableRow>
                            <TableRow v-for="category in categories" :key="category.id" :class="{ 'opacity-55': !category.is_active }">
                                <TableCell class="font-medium">{{ category.name }}</TableCell>
                                <TableCell class="max-w-lg text-muted-foreground">{{ category.description || '—' }}</TableCell>
                                <TableCell>{{ category.agents_count }}</TableCell>
                                <TableCell><Badge :variant="category.is_active ? 'success' : 'secondary'">{{ category.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                                <TableCell><div class="flex justify-end gap-2">
                                    <Button v-if="canManagePricing" size="sm" variant="ghost" @click="editCategory(category)"><Pencil class="h-4 w-4" /><span class="sr-only">Edit</span></Button>
                                    <Button v-if="canManagePricing" size="sm" variant="outline" :disabled="categoryStatusId === category.id" @click="setCategoryActive(category, !category.is_active)">{{ category.is_active ? 'Deactivate' : 'Activate' }}</Button>
                                </div></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </TabsContent>
        </Tabs>
    </PageShell>
</template>
