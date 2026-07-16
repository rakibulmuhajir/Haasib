<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, CircleDollarSign, Plane } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const capabilities = [
    {
        title: 'Business records',
        description: 'Sales, purchases, payments, and daily accounts.',
        icon: CircleDollarSign,
    },
    {
        title: 'Company operations',
        description: 'People, payroll, permissions, and company settings.',
        icon: Building2,
    },
    {
        title: 'Travel services',
        description: 'Groups, vouchers, hotels, transport, and agents.',
        icon: Plane,
    },
];
</script>

<template>
    <Head title="Haasib" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8"
            >
                <Link :href="'/'" class="flex items-center gap-3">
                    <span
                        class="flex size-9 items-center justify-center rounded-md bg-foreground text-base font-semibold text-background"
                        aria-hidden="true"
                    >
                        H
                    </span>
                    <span class="text-lg font-semibold">Haasib</span>
                </Link>

                <Button
                    v-if="$page.props.auth.user"
                    variant="outline"
                    as-child
                >
                    <Link :href="dashboard()">
                        Open workspace
                        <ArrowRight />
                    </Link>
                </Button>
                <Button v-else variant="ghost" as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
            </div>
        </header>

        <main>
            <section
                class="mx-auto flex min-h-[calc(100vh-10rem)] max-w-6xl flex-col justify-center px-5 py-16 sm:px-8 sm:py-24"
            >
                <div class="max-w-3xl">
                    <p class="mb-5 text-sm font-medium text-accent-green">
                        Business management, made practical
                    </p>
                    <h1
                        class="max-w-2xl text-5xl leading-[1.05] font-semibold sm:text-6xl"
                    >
                        Haasib
                    </h1>
                    <p
                        class="mt-6 max-w-2xl text-lg leading-8 text-muted-foreground sm:text-xl"
                    >
                        Keep your accounts and day-to-day operations in one
                        clear workspace, without making simple work feel like
                        accounting work.
                    </p>

                    <div class="mt-9 flex flex-wrap gap-3">
                        <Button
                            v-if="$page.props.auth.user"
                            size="lg"
                            as-child
                        >
                            <Link :href="dashboard()">
                                Open workspace
                                <ArrowRight />
                            </Link>
                        </Button>
                        <template v-else>
                            <Button size="lg" as-child>
                                <Link :href="login()">
                                    Log in
                                    <ArrowRight />
                                </Link>
                            </Button>
                            <Button
                                v-if="canRegister"
                                size="lg"
                                variant="outline"
                                as-child
                            >
                                <Link :href="register()">Create account</Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </section>

            <section class="border-y border-border bg-muted/35">
                <div
                    class="mx-auto grid max-w-6xl divide-y divide-border px-5 sm:px-8 md:grid-cols-3 md:divide-x md:divide-y-0"
                >
                    <div
                        v-for="(capability, index) in capabilities"
                        :key="capability.title"
                        class="flex gap-4 py-7 md:px-7"
                        :class="{ 'md:pl-0': index === 0 }"
                    >
                        <component
                            :is="capability.icon"
                            class="mt-0.5 size-5 shrink-0 text-accent-green"
                            aria-hidden="true"
                        />
                        <div>
                            <h2 class="text-sm font-semibold">
                                {{ capability.title }}
                            </h2>
                            <p
                                class="mt-1 text-sm leading-6 text-muted-foreground"
                            >
                                {{ capability.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer>
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 text-sm text-muted-foreground sm:px-8"
            >
                <span>Haasib</span>
                <span>Clear records. Better decisions.</span>
            </div>
        </footer>
    </div>
</template>
