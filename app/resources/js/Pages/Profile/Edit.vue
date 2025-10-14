<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LayoutShell from '@/Components/Layout/LayoutShell.vue';
import Sidebar from '@/Components/Sidebar/Sidebar.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    user: {
        type: Object,
        required: true
    }
});

const { t } = useI18n();

const breadcrumbItems = computed(() => [
    { label: t('profile.breadcrumb.home'), url: '/dashboard', icon: 'home' },
    { label: t('profile.breadcrumb.profile'), url: '/profile', icon: 'user' },
]);
</script>

<template>
    <Head :title="t('profile.pageTitle')" />

    <LayoutShell>
        <template #sidebar>
            <Sidebar :title="t('profile.pageTitle')" />
        </template>

        <template #topbar>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="max-w-4xl space-y-6">
            <PageHeader
                :title="t('profile.header.title')"
                :subtitle="t('profile.header.subtitle')"
            />

            <div class="bg-white p-6 shadow rounded-lg dark:bg-gray-800 dark:border dark:border-surface-700">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-6 shadow rounded-lg dark:bg-gray-800 dark:border dark:border-surface-700">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-6 shadow rounded-lg dark:bg-gray-800 dark:border dark:border-surface-700">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </LayoutShell>
</template>
