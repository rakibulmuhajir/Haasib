<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from 'vue'
import { valueTrailBatchKey } from '@/composables/useValueTrail'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, ChevronRight, ExternalLink, LoaderCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import MoneyText from '@/components/MoneyText.vue'
import { useLexicon } from '@/composables/useLexicon'
import type { ValueTrail } from '@/types/valueTrail'
import { formatDateTime } from '@/lib/datetime'

const props = defineProps<{ open: boolean; loading: boolean; error?: string; trail?: ValueTrail | null; root: string; currency: string }>()
const emit = defineEmits<{ 'update:open': [value: boolean]; retry: [] }>()
const { t } = useLexicon()
const batch = inject(valueTrailBatchKey, null)
const path = ref<string[]>([])
const heading = ref<HTMLElement | null>(null)
const visibleCount = ref(40)
const current = computed(() => props.trail?.nodes[path.value.at(-1) ?? ''])
const crumbs = computed(() => path.value.map((id) => props.trail?.nodes[id]).filter((node) => node !== undefined))
const children = computed(() => (current.value?.children ?? []).map((id) => props.trail?.nodes[id]).filter((node) => node !== undefined))
const quantity = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 3 }).format(value)

watch([() => props.root, () => props.open], () => {
    const rootId = props.trail?.roots[props.root]
    path.value = rootId ? [rootId] : []
    visibleCount.value = 40
}, { immediate: true })
watch(() => props.trail, () => {
    if (!path.value.length || !props.trail?.batch) {
        const rootId = props.trail?.roots[props.root]
        if (rootId) path.value = [rootId]
    }
})

async function navigate(id: string) {
    if (!props.trail?.nodes[id] || path.value.includes(id)) return
    path.value.push(id)
    if (props.trail.nodes[id].summary) batch?.load(id, 0)
    visibleCount.value = 40
    await nextTick()
    heading.value?.focus()
}

async function backTo(index: number) {
    path.value = path.value.slice(0, index + 1)
    visibleCount.value = 40
    await nextTick()
    heading.value?.focus()
}

function more() {
    if (current.value && props.trail?.batch) {
        batch?.load(current.value.id, current.value.children_loaded ?? 0)
    } else {
        visibleCount.value += 40
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full gap-0 overflow-hidden sm:max-w-xl">
            <SheetHeader class="border-b p-6 pr-12">
                <SheetTitle>{{ t('valueTrailTitle') }}</SheetTitle>
                <SheetDescription>
                    <template v-if="trail?.context?.label">{{ trail.context.label }} · {{ current?.currency || currency }}</template>
                    <template v-else-if="trail?.context?.start_date && trail.context.end_date">{{ trail.context.start_date }} — {{ trail.context.end_date }} · {{ currency }}</template>
                    <template v-else>{{ t('valueTrailOpen') }}</template>
                </SheetDescription>
            </SheetHeader>
            <div class="min-h-0 flex-1 overflow-y-auto p-6" :aria-busy="loading">
                <div v-if="loading" role="status" class="flex items-center gap-3 py-8 text-sm text-muted-foreground">
                    <LoaderCircle class="size-5 animate-spin" />{{ t('valueTrailLoading') }}
                </div>
                <div v-else-if="error" role="alert" class="space-y-4 py-8">
                    <p class="text-sm">{{ error }}</p>
                    <Button type="button" variant="outline" @click="emit('retry')">{{ t('valueTrailRetry') }}</Button>
                </div>
                <div v-else-if="current" class="space-y-6">
                    <nav v-if="path.length > 1" :aria-label="t('valueTrailBreadcrumbs')" class="space-y-2">
                        <Button type="button" variant="ghost" size="sm" class="-ml-3" @click="backTo(path.length - 2)"><ArrowLeft class="size-4" />{{ t('back') }}</Button>
                        <ol class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                            <li v-for="(crumb, index) in crumbs" :key="crumb.id" class="flex items-center gap-1">
                                <ChevronRight v-if="index" class="size-3" />
                                <span v-if="index === crumbs.length - 1" aria-current="page">{{ crumb.label }}</span>
                                <Button v-else type="button" variant="link" class="h-auto whitespace-normal p-0 text-xs" @click="backTo(index)">{{ crumb.label }}</Button>
                            </li>
                        </ol>
                    </nav>
                    <div class="space-y-2">
                        <h3 ref="heading" tabindex="-1" class="text-sm font-medium">{{ current.label }}</h3>
                        <div class="text-3xl font-semibold tabular-nums">
                            <MoneyText v-if="current.unit.startsWith('money')" :amount="current.value" :currency="current.currency || currency" />
                            <template v-else>{{ quantity(current.value) }}</template>
                            <span v-if="current.unit !== 'money'" class="ml-1 text-base font-normal text-muted-foreground">{{ current.unit.replace('money/', '/') }}</span>
                        </div>
                        <p v-if="current.estimated" class="text-xs font-medium text-status-attention">{{ t('valueTrailEstimated') }}</p>
                        <p v-if="current.formula" class="border-l-2 pl-3 text-sm font-medium">{{ current.formula }}</p>
                        <p class="text-sm leading-relaxed text-muted-foreground">{{ current.explanation }}</p>
                    </div>
                    <div v-if="current.source" class="space-y-2 border-y py-4 text-sm">
                        <p class="font-medium">{{ t('valueTrailSource') }}</p>
                        <p v-if="current.source.restricted" class="text-muted-foreground">{{ t('valueTrailRestricted') }}</p>
                        <p v-else-if="current.source.unavailable" class="text-muted-foreground">{{ t('valueTrailSourceUnavailable') }}</p>
                        <template v-else>
                            <p>{{ current.source.label }}<span v-if="current.source.date"> · {{ current.source.date }}</span></p>
                            <p v-if="current.source.recorded_by" class="text-muted-foreground">{{ t('valueTrailRecordedBy') }} {{ current.source.recorded_by }}</p>
                            <p v-if="current.source.recorded_at" class="text-xs text-muted-foreground">{{ t('valueTrailRecordedAt') }} {{ formatDateTime(current.source.recorded_at) }}</p>
                            <p v-if="!current.source.recorded_by" class="text-xs text-muted-foreground">{{ t('valueTrailActorUnavailable') }}</p>
                            <Button v-if="current.source.href" as-child variant="link" class="h-auto p-0">
                                <Link :href="current.source.href">{{ t('valueTrailOpenRecord') }}<ExternalLink class="size-3" /></Link>
                            </Button>
                        </template>
                    </div>
                    <div v-if="children.length" class="divide-y border-y">
                        <Button v-for="child in (trail?.batch ? children : children.slice(0, visibleCount))" :key="child.id" type="button" variant="ghost" class="h-auto w-full justify-between gap-4 rounded-none px-0 py-4 text-left whitespace-normal" @click="navigate(child.id)">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ child.label }}</span>
                                <span v-if="child.estimated" class="text-xs text-status-attention">{{ t('valueTrailEstimated') }}</span>
                            </span>
                            <span class="shrink-0 text-right tabular-nums">
                                <MoneyText v-if="child.unit.startsWith('money')" :amount="child.value" :currency="child.currency || currency" />
                                <template v-else>{{ quantity(child.value) }} {{ child.unit }}</template>
                            </span>
                            <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                        </Button>
                        <Button v-if="trail?.batch ? (current.children_loaded ?? 0) < (current.children_total ?? 0) : children.length > visibleCount" type="button" variant="link" @click="more">{{ t('valueTrailMore') }}</Button>
                    </div>
                </div>
                <p v-else class="py-8 text-sm text-muted-foreground">{{ t('valueTrailUnavailable') }}</p>
            </div>
        </SheetContent>
    </Sheet>
</template>
