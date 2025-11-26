<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
        >
            <!-- KPI Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Revenue</p>
                            <h3 class="text-2xl font-bold">$128,450</h3>
                            <p class="text-xs text-green-600">+12.5% from last month</p>
                        </div>
                        <div class="rounded-full bg-blue-500/10 p-3">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-green-50 to-green-100 dark:from-green-950 dark:to-green-900 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Outstanding Invoices</p>
                            <h3 class="text-2xl font-bold">$24,680</h3>
                            <p class="text-xs text-orange-600">12 invoices pending</p>
                        </div>
                        <div class="rounded-full bg-green-500/10 p-3">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-950 dark:to-purple-900 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Expenses</p>
                            <h3 class="text-2xl font-bold">$8,240</h3>
                            <p class="text-xs text-red-600">+5.2% from last month</p>
                        </div>
                        <div class="rounded-full bg-purple-500/10 p-3">
                            <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions & Recent Activity -->
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button class="w-full flex items-center gap-3 p-3 rounded-lg border border-dashed border-blue-300 hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors">
                            <div class="rounded-full bg-blue-500/10 p-2">
                                <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Create New Invoice</span>
                        </button>
                        <button class="w-full flex items-center gap-3 p-3 rounded-lg border border-dashed border-green-300 hover:bg-green-50 dark:hover:bg-green-950 transition-colors">
                            <div class="rounded-full bg-green-500/10 p-2">
                                <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Add New Customer</span>
                        </button>
                        <button class="w-full flex items-center gap-3 p-3 rounded-lg border border-dashed border-purple-300 hover:bg-purple-50 dark:hover:bg-purple-950 transition-colors">
                            <div class="rounded-full bg-purple-500/10 p-2">
                                <svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Record Payment</span>
                        </button>
                    </div>
                </div>
                
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="rounded-full bg-blue-500/10 p-2">
                                <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Invoice #2024-001 created</p>
                                <p class="text-xs text-muted-foreground">Acme Corp - $2,500</p>
                            </div>
                            <span class="text-xs text-muted-foreground">2m ago</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="rounded-full bg-green-500/10 p-2">
                                <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Payment received</p>
                                <p class="text-xs text-muted-foreground">TechStart Inc - $1,200</p>
                            </div>
                            <span class="text-xs text-muted-foreground">1h ago</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="rounded-full bg-purple-500/10 p-2">
                                <svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">New customer added</p>
                                <p class="text-xs text-muted-foreground">Global Solutions LLC</p>
                            </div>
                            <span class="text-xs text-muted-foreground">3h ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppSidebarLayout>
</template>
