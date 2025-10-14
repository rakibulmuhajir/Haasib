<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="container mx-auto px-4 py-16">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    Welcome to Haasib
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                    Business Management Platform
                </p>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-8">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">
                        Getting Started
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Haasib provides comprehensive business management tools including invoicing, 
                        customer management, and financial reporting.
                    </p>
                    
                    <div v-if="canLogin" class="space-x-4">
                        <Link 
                            v-if="$page.props.auth?.user"
                            href="/dashboard" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors"
                        >
                            Dashboard
                        </Link>
                        
                        <template v-else>
                            <Link 
                                href="/login" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors"
                            >
                                Login
                            </Link>
                            
                            <Link 
                                v-if="canRegister"
                                href="/register" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors"
                            >
                                Register
                            </Link>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Invoicing
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Create and manage invoices with ease
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Customer Management
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Track customers and their relationships
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Financial Reports
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Comprehensive reporting and analytics
                        </p>
                    </div>
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <p>Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})</p>
                </div>
            </div>
        </div>
    </div>
</template>