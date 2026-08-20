<script setup lang="ts">
/**
 * EmptyState — an empty screen is an invitation to act, not an apology.
 *
 * Two call conventions are supported on purpose. The `actions` array is the
 * canonical one; `actionLabel` + `@action` is what fifteen index pages actually
 * pass, and under the old props those pages rendered a heading, a description,
 * and no button at all — the one thing an empty state exists to offer. Same
 * story with `icon`: they pass the lucide name as a string, which the old
 * `Component`-typed prop rendered as nothing.
 */
import { computed } from 'vue'
import type { Component } from 'vue'
import { resolveIcon } from '@/lib/icons'
import { Button } from '@/components/ui/button'

interface Action {
    label: string
    icon?: Component
    onClick: () => void
    variant?: 'default' | 'secondary' | 'outline' | 'ghost'
}

interface Props {
    /** A component, or a lucide icon name such as "FileText". */
    icon?: Component | string
    title: string
    description?: string
    actions?: Action[]
    /** Legacy single-action shape. Emits `action` when clicked. */
    actionLabel?: string
    /** Visual size variant */
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
    icon: undefined,
    description: undefined,
    actions: () => [],
    actionLabel: undefined,
    size: 'md',
})

const emit = defineEmits<{ action: [] }>()

const resolvedIcon = computed(() => resolveIcon(props.icon))

const sizeClasses = {
    sm: {
        wrapper: 'p-8',
        icon: 'h-10 w-10',
        iconWrapper: 'h-16 w-16',
        title: 'text-base',
        description: 'text-sm max-w-xs',
    },
    md: {
        wrapper: 'p-12',
        icon: 'h-12 w-12',
        iconWrapper: 'h-20 w-20',
        title: 'text-lg',
        description: 'text-sm max-w-sm',
    },
    lg: {
        wrapper: 'p-16',
        icon: 'h-14 w-14',
        iconWrapper: 'h-24 w-24',
        title: 'text-xl',
        description: 'text-base max-w-md',
    },
}
</script>

<template>
    <div
        :class="[
            'flex flex-col items-center justify-center text-center',
            sizeClasses[size].wrapper,
        ]"
    >
        <!-- Was a zinc gradient well with a translucent ring. Rules and a quiet
             ground do the same separating work without the soft-focus. -->
        <div
            v-if="resolvedIcon || $slots.icon"
            :class="[
                'mb-4 flex items-center justify-center rounded-2xl border border-border bg-surface-2',
                sizeClasses[size].iconWrapper,
            ]"
        >
            <slot name="icon">
                <component
                    :is="resolvedIcon"
                    :class="['text-text-tertiary', sizeClasses[size].icon]"
                />
            </slot>
        </div>

        <h3 :class="['font-display font-semibold text-foreground', sizeClasses[size].title]">
            {{ title }}
        </h3>

        <p
            v-if="description"
            :class="['mt-2 leading-relaxed text-text-secondary', sizeClasses[size].description]"
        >
            {{ description }}
        </p>

        <div
            v-if="$slots.description"
            :class="['mt-2 text-text-secondary', sizeClasses[size].description]"
        >
            <slot name="description" />
        </div>

        <div
            v-if="actions.length > 0 || actionLabel || $slots.actions"
            class="mt-6 flex items-center gap-3"
        >
            <slot name="actions">
                <Button
                    v-for="(action, index) in actions"
                    :key="index"
                    :variant="action.variant || 'default'"
                    size="sm"
                    @click="action.onClick"
                >
                    <component :is="action.icon" v-if="action.icon" class="mr-2 h-4 w-4" />
                    {{ action.label }}
                </Button>

                <Button v-if="actionLabel" size="sm" @click="emit('action')">
                    {{ actionLabel }}
                </Button>
            </slot>
        </div>
    </div>
</template>
