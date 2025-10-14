<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Admin Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-gray-800 dark:border dark:border-gray-700 shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <div>Welcome, superadmin!!</div>
                        <div class="text-sm">
                            <Link :href="route('admin.companies.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Manage Companies</Link>
                            ·
                            <Link :href="route('admin.users.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Manage Users</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
