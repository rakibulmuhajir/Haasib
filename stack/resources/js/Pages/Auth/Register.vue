<script setup>
import { reactive } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const register = () => {
    form.post('/register', {
        onSuccess: () => {
            // Registration successful - will be redirected by Laravel
        },
        onError: (errors) => {
            // Registration failed - errors will be displayed automatically
        },
    })
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                    <i class="fas fa-chart-line text-blue-600 dark:text-blue-400"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                    Create your account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <Link href="/login" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                        Sign in here
                    </Link>
                </p>
                <p class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                    After registration, create a company or wait for an invitation
                </p>
            </div>

            <!-- Success/Flash Messages -->
            <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 dark:bg-green-900 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400 dark:text-green-300"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                            {{ $page.props.flash.success }}
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Error Messages -->
            <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 dark:bg-red-900 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400 dark:text-red-300"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                            {{ $page.props.flash.error }}
                        </h3>
                    </div>
                </div>
            </div>
            
            <form class="mt-8 space-y-6" @submit.prevent="register">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-4 py-5 sm:px-6">
                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Full Name
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                name="name"
                                type="text"
                                required
                                autocomplete="name"
                                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="John Doe"
                                :disabled="form.processing"
                            >
                            <div v-if="form.errors.name" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email Address
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                required
                                autocomplete="email"
                                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="john@example.com"
                                :disabled="form.processing"
                            >
                            <div v-if="form.errors.email" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Password
                            </label>
                            <input
                                id="password"
                                v-model="form.password"
                                name="password"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="••••••••"
                                :disabled="form.processing"
                            >
                            <div v-if="form.errors.password" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                {{ form.errors.password }}
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Must be at least 8 characters
                            </p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirm Password
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="••••••••"
                                :disabled="form.processing"
                            >
                        </div>
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing" class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-spinner fa-spin text-blue-300"></i>
                        </span>
                        {{ form.processing ? 'Creating Account...' : 'Create Account' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>