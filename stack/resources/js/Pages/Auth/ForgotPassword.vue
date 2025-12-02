<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const form = useForm({
    email: '',
})

const submit = () => {
    form.post('/forgot-password')
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                    <i class="fas fa-key text-blue-600 dark:text-blue-400"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                    Reset your password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Enter your email address and we'll send you a link to reset your password
                </p>
            </div>

            <!-- Success Message -->
            <div v-if="$page.props.status" class="rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">
                            {{ $page.props.status }}
                        </p>
                    </div>
                </div>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email Address
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="you@example.com"
                            :disabled="form.processing"
                        >
                        <div v-if="form.errors.email" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            {{ form.errors.email }}
                        </div>
                    </div>
                </div>

                <div>
                    <PrimaryButton
                        type="submit"
                        :disabled="form.processing"
                        class="w-full justify-center"
                    >
                        <i v-if="form.processing" class="fas fa-spinner fa-spin mr-2"></i>
                        <i v-else class="fas fa-paper-plane mr-2"></i>
                        {{ form.processing ? 'Sending...' : 'Send Reset Link' }}
                    </PrimaryButton>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <Link href="/login" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                        ← Back to login
                    </Link>
                    <Link href="/register" class="font-medium text-gray-600 hover:text-gray-500 dark:text-gray-400">
                        Create account →
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
