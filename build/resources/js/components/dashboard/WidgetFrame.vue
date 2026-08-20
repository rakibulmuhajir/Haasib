<script setup lang="ts">
/**
 * WidgetFrame — every dashboard widget's only Card.
 *
 * Twelve widgets cannot invent twelve dialects. So the Card, the title, the
 * optional description, the optional single footer action link, the empty
 * state (via EmptyState.vue) and the loading skeleton live here and nowhere
 * else. A widget that renders its own <Card> or its own <h2> is a bug — a
 * widget's only job is to lay its data out inside the default slot this
 * frame gives it.
 */
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import EmptyState from '@/components/EmptyState.vue'
import { ArrowRight } from 'lucide-vue-next'

withDefaults(
    defineProps<{
        title: string
        description?: string
        /** "See all of August" — the arrow is added by the frame, never typed by the caller. */
        footerLabel?: string
        footerHref?: string
        isEmpty?: boolean
        emptyMessage?: string
        loading?: boolean
    }>(),
    {
        description: undefined,
        footerLabel: undefined,
        footerHref: undefined,
        isEmpty: false,
        emptyMessage: 'Nothing here yet.',
        loading: false,
    },
)
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">{{ description }}</CardDescription>
        </CardHeader>

        <CardContent class="flex-1">
            <div v-if="loading" class="space-y-3">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-5/6" />
                <Skeleton class="h-4 w-2/3" />
            </div>

            <EmptyState v-else-if="isEmpty" :title="emptyMessage" size="sm" />

            <slot v-else />
        </CardContent>

        <div
            v-if="footerLabel && footerHref && !loading"
            class="border-t border-rule-subtle px-6 py-3"
        >
            <a
                :href="footerHref"
                class="inline-flex items-center gap-1.5 font-mono text-xs uppercase tracking-wider text-text-secondary transition-colors hover:text-text-primary"
            >
                {{ footerLabel }}
                <ArrowRight class="h-3 w-3" />
            </a>
        </div>
    </Card>
</template>
