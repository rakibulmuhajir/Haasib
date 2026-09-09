<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import VoucherPrintDetails, {
    type ContactProfile,
    type PrintDetails,
} from '../../../components/VoucherPrintDetails.vue';

const props = defineProps<{
    company: { name: string; slug: string };
    profiles: ContactProfile[];
    target: string;
}>();
const initialTarget = props.profiles.some(
    (profile) => profile.key === props.target,
)
    ? props.target
    : 'company';
const copyDetails = (key: string): PrintDetails => {
    const details = props.profiles.find(
        (profile) => profile.key === key,
    )?.details;
    return {
        footer_text: details?.footer_text || '',
        contacts: (details?.contacts || []).map((contact) => ({ ...contact })),
    };
};
const form = useForm({
    target: initialTarget,
    details: copyDetails(initialTarget),
});
const options = computed(() =>
    props.profiles.map((profile) => ({
        value: profile.key,
        label: profile.label,
    })),
);
function changeTarget(value: string | number | null) {
    if (form.isDirty) {
        toast.error(
            'Save your changes or use Discard changes before switching profile.',
        );
        return;
    }
    form.defaults({
        target: String(value),
        details: copyDetails(String(value)),
    });
    form.reset();
    form.clearErrors();
}
function save() {
    form.put(`/${props.company.slug}/umrah/settings/voucher`, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: () =>
            toast.error('Please check the highlighted voucher settings.'),
    });
}
</script>

<template>
    <Head title="Voucher settings" />
    <PageShell
        title="Voucher settings"
        description="Reusable footer text and representatives. Changes apply to new vouchers, not issued copies."
        :icon="ScrollText"
        :breadcrumbs="[
            { title: 'Setup', href: `/${company.slug}/umrah/settings` },
            { title: 'Voucher settings' },
        ]"
    >
        <form class="max-w-5xl space-y-6" @submit.prevent="save">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 space-y-2">
                    <Label>Company, agent or provider</Label>
                    <SearchableSelect
                        :model-value="form.target"
                        :options="options"
                        :show-value="false"
                        :disabled="form.processing"
                        @update:model-value="changeTarget"
                    />
                    <p
                        v-if="form.errors.target"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.target }}
                    </p>
                </div>
                <Button type="submit" :disabled="form.processing">{{
                    form.processing ? 'Saving…' : 'Save defaults'
                }}</Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing || !form.isDirty"
                    @click="
                        form.reset();
                        form.clearErrors();
                    "
                    >Discard changes</Button
                >
            </div>
            <p class="text-sm text-muted-foreground">
                Company and agent contacts prefill new vouchers. An agent footer
                replaces the company footer when filled. Provider contacts can
                be selected individually on each voucher.
            </p>
            <VoucherPrintDetails
                v-model="form.details"
                :errors="form.errors"
                prefix="details"
                :disabled="form.processing"
            />
            <Button type="submit" :disabled="form.processing">{{
                form.processing ? 'Saving…' : 'Save defaults'
            }}</Button>
        </form>
    </PageShell>
</template>
