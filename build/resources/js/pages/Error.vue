<script setup lang="ts">
/**
 * Error — the page that renders when app context may not be available
 * (session expired, company not resolved, a route that never existed).
 * Deliberately does not use AppLayout/PageShell: those assume a working
 * shell (sidebar, current company, nav) that a hard error cannot promise.
 */
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

const props = defineProps<{ status: number }>()

interface Copy {
    title: string
    detail: string
}

const copy = computed<Copy>(() => {
    switch (props.status) {
        case 503:
            return {
                title: 'Down for maintenance',
                detail: 'The app is offline while we make changes. It will be back shortly.',
            }
        case 500:
            return {
                title: 'Something broke on our side',
                detail: 'The error has been logged and the team has been notified. Try again in a moment.',
            }
        case 403:
            return {
                title: "You don't have access to this",
                detail: 'Your account is not permitted to view this page. Switch companies or ask an admin for access.',
            }
        case 404:
        default:
            return {
                title: "That page doesn't exist",
                detail: 'Check the address, or it may have moved.',
            }
    }
})

const pageTitle = computed(() => `${props.status} — ${copy.value.title}`)

function goBack() {
    if (window.history.length > 1) {
        window.history.back()
        return
    }
    router.visit('/')
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex min-h-screen flex-col items-center justify-center gap-8 bg-surface-canvas px-6">
        <div class="flex max-w-md flex-col items-center gap-4 text-center">
            <span class="font-mono text-xs uppercase tracking-widest text-text-tertiary">Error {{ status }}</span>

            <h1 class="font-display text-3xl text-text-primary">{{ copy.title }}</h1>

            <p class="text-sm text-text-secondary">{{ copy.detail }}</p>
        </div>

        <div class="flex items-center gap-4">
            <Button variant="default" @click="goBack">Go back</Button>
            <Button variant="ghost" as-child>
                <a href="/">Return home</a>
            </Button>
        </div>
    </div>
</template>
