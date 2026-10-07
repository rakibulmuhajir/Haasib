<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useValueTrails } from '@/composables/useValueTrails';
import { useLexicon } from '@/composables/useLexicon';
import { useFormFeedback } from '@/composables/useFormFeedback';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];

const { t } = useLexicon();
const { enabled } = useValueTrails();
const { showError } = useFormFeedback();
const form = useForm({ show_value_trails: enabled.value });
watch(enabled, (value) => { form.show_value_trails = value; });
const savePreferences = () => form.patch('/settings/appearance', {
    preserveScroll: true,
    onError: (errors) => showError(errors),
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                />
                <AppearanceTabs />
                <form class="space-y-4 border-t pt-6" @submit.prevent="savePreferences">
                    <HeadingSmall :title="t('valueTrailPreferenceTitle')" :description="t('valueTrailPreferenceScope')" />
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <Label for="show-value-trails">{{ t('valueTrailPreferenceLabel') }}</Label>
                            <p class="text-sm text-muted-foreground">{{ t('valueTrailPreferenceHelp') }}</p>
                        </div>
                        <Switch id="show-value-trails" v-model="form.show_value_trails" :disabled="form.processing" />
                    </div>
                    <InputError :message="form.errors.show_value_trails" />
                    <Button type="submit" :disabled="form.processing || !form.isDirty">
                        {{ form.processing ? t('valueTrailSaving') : t('save') }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
