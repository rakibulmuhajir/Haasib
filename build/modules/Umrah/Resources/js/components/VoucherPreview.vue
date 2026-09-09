<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ url: string; voucherId: string; revision: string }>();
const frame = ref<HTMLIFrameElement | null>(null);
const height = ref(1123);
const loading = ref(true);
const failed = ref(false);
const attempt = ref(0);
let timer: ReturnType<typeof setTimeout> | undefined;
let observer: ResizeObserver | undefined;

function retry() {
    clearTimeout(timer);
    observer?.disconnect();
    loading.value = true;
    failed.value = false;
    attempt.value++;
    timer = setTimeout(() => { loading.value = false; failed.value = true; }, 20000);
}

async function loaded() {
    const element = frame.value;
    try {
        const doc = element?.contentDocument;
        if (!doc || doc.body.dataset.voucherPrint !== props.voucherId) throw new Error('Invalid preview');
        await doc.fonts.ready;
        await Promise.all(Array.from(doc.images).map(image => image.decode().catch(() => undefined)));
        if (element !== frame.value) return;
        const resize = () => {
            // Measure the body, not the viewport: a previous tall iframe must
            // be able to shrink when a shorter voucher replaces it.
            height.value = Math.max(1123, Math.ceil(doc.body.getBoundingClientRect().height));
        };
        observer?.disconnect();
        observer = new ResizeObserver(resize);
        observer.observe(doc.body);
        resize();
        loading.value = false;
        clearTimeout(timer);
    } catch {
        clearTimeout(timer);
        loading.value = false;
        failed.value = true;
    }
}

watch(() => [props.url, props.revision], retry, { immediate: true });
onBeforeUnmount(() => { clearTimeout(timer); observer?.disconnect(); });
</script>

<template>
    <section aria-label="Voucher print preview" :aria-busy="loading" class="min-w-0 max-w-full overflow-hidden border bg-muted/40 p-3 sm:p-6">
        <div v-if="failed" role="alert" class="flex items-center justify-center gap-4 p-8 text-sm">
            <span>The voucher preview could not load.</span>
            <Button variant="outline" @click="retry">Try again</Button>
        </div>
        <template v-else>
            <p v-if="loading" role="status" class="mb-3 text-center text-sm text-muted-foreground">Preparing voucher preview…</p>
            <p class="mb-2 text-xs text-muted-foreground sm:hidden">Scroll sideways to read the full sheet.</p>
            <div class="overflow-x-auto" tabindex="0" aria-label="A4 voucher sheet">
                <iframe
                    :key="`${revision}-${attempt}`"
                    ref="frame"
                    :src="url"
                    title="Passenger voucher — print layout"
                    class="mx-auto block border-0 bg-white shadow-sm"
                    :style="{ width: '210mm', height: `${height}px`, visibility: loading ? 'hidden' : 'visible' }"
                    @load="loaded"
                    @error="failed = true; loading = false"
                />
            </div>
        </template>
    </section>
</template>
