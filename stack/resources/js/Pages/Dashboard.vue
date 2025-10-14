<script setup>
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

const page = usePage()
const user = page.props.auth?.user

const logout = async () => {
    try {
        await fetch('/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        window.location.href = '/'
    } catch (error) {
        console.error('Logout failed:', error)
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Navigation Header -->
        <nav class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                Haasib Dashboard
                            </h1>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Welcome, {{ user?.name || 'User' }}
                        </span>
                        <Link
                            href="/companies"
                            class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium"
                        >
                            Companies
                        </Link>
                        <button
                            @click="logout"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Companies Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-building text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Companies
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            Manage your companies
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                            <div class="text-sm">
                                <Link
                                    href="/companies"
                                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                                >
                                    View all companies →
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-file-invoice text-green-600 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Invoices
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            Create and manage invoices
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                            <div class="text-sm">
                                <Link
                                    href="/invoices"
                                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                                >
                                    Manage invoices →
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Customers Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-purple-600 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Customers
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            Customer management
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                            <div class="text-sm">
                                <Link
                                    href="/customers"
                                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                                >
                                    View customers →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/companies/create"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center font-medium"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            New Company
                        </Link>
                        <Link
                            href="/invoices/create"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-center font-medium"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            New Invoice
                        </Link>
                        <Link
                            href="/customers/create"
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-center font-medium"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            New Customer
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>