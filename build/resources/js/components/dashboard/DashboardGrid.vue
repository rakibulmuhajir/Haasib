<script setup lang="ts">
/**
 * DashboardGrid — a layout over a registry.
 *
 * The page controller resolves nothing; it hands down an ordered list of
 * placements (`key`, `span`, `options`, `data`) and this grid looks each key
 * up in the widget map. An unknown key — a widget renamed or removed since a
 * layout was saved — is skipped silently, because layouts outlive code:
 * never an error, never a visible placeholder, at most a console.warn for
 * whoever is debugging.
 */
import { computed } from 'vue'
import { widgetMap } from '@/components/dashboard/widgets'

export interface DashboardWidgetPlacement {
    key: string
    span: number
    options: Record<string, unknown>
    data: unknown
}

const props = defineProps<{
    widgets: DashboardWidgetPlacement[]
}>()

const resolved = computed(() =>
    props.widgets
        .map((placement) => ({ placement, component: widgetMap[placement.key] }))
        .filter(({ placement, component }) => {
            if (!component) {
                console.warn(`[DashboardGrid] unknown widget key "${placement.key}" — skipped`)
                return false
            }
            return true
        }),
)

const spanClass = (span: number) => (span >= 12 ? 'col-span-12' : 'col-span-12 md:col-span-6')
</script>

<template>
    <div class="grid grid-cols-12 gap-6">
        <div
            v-for="{ placement, component } in resolved"
            :key="placement.key"
            :class="spanClass(placement.span)"
        >
            <component :is="component" :data="placement.data" :options="placement.options" />
        </div>
    </div>
</template>
