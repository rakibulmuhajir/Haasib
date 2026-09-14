<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    url: string;
    candidates: Array<{
        id: string;
        name: string;
        passport: string | null;
        group_number: string;
    }>;
}>();
const open = ref(false);
const search = ref('');
const searching = ref(false);
const searched = ref(false);
const form = useForm({ passenger_ids: [] as string[] });
const find = () => {
    if (search.value.trim().length < 2 || searching.value || form.processing)
        return;
    form.reset();
    form.clearErrors();
    searching.value = true;
    router.get(
        props.url,
        { passenger_search: search.value },
        {
            only: ['joiningCandidates'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                searched.value = true;
            },
            onError: () =>
                toast.error('Passenger search failed. Please try again.'),
            onFinish: () => {
                searching.value = false;
            },
        },
    );
};
const add = () => {
    if (form.processing || searching.value || !form.passenger_ids.length)
        return;
    form.post(`${props.url}/passengers/join`, {
        preserveScroll: true,
        onSuccess: (page) => {
            if ((page.props.flash as { error?: string } | undefined)?.error)
                return;
            open.value = false;
            form.reset();
        },
        onError: () =>
            toast.error(
                'Passengers were not added. Review the error and search again.',
            ),
    });
};
</script>

<template>
    <Button variant="outline" @click="open = true"
        >Add existing passenger</Button
    >
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Add existing passengers</DialogTitle>
                <DialogDescription
                    >Join this travelling party before buying its hotel service.
                    No source voucher is needed; original visa and transport
                    purchases stay with the original group.</DialogDescription
                >
            </DialogHeader>
            <form class="flex min-w-0 gap-2" @submit.prevent="find">
                <Input
                    v-model="search"
                    aria-label="Find passenger"
                    placeholder="Name, passport or original group number"
                    :disabled="form.processing"
                />
                <Button
                    type="submit"
                    :disabled="
                        searching || form.processing || search.trim().length < 2
                    "
                    >{{ searching ? 'Searching…' : 'Search' }}</Button
                >
            </form>
            <p
                v-if="searched && !candidates.length && !searching"
                class="text-sm text-muted-foreground"
            >
                No unassigned passengers found. Passengers already on a voucher
                use Move Passengers instead.
            </p>
            <div class="max-h-72 space-y-2 overflow-y-auto">
                <label
                    v-for="pax in candidates"
                    :key="pax.id"
                    class="flex items-start gap-3 border p-3 text-sm"
                >
                    <Checkbox
                        :model-value="form.passenger_ids.includes(pax.id)"
                        :disabled="searching || form.processing"
                        @update:model-value="
                            (checked) => {
                                form.passenger_ids =
                                    checked === true
                                        ? [
                                              ...new Set([
                                                  ...form.passenger_ids,
                                                  pax.id,
                                              ]),
                                          ]
                                        : form.passenger_ids.filter(
                                              (id) => id !== pax.id,
                                          );
                            }
                        "
                    />
                    <span
                        ><span class="font-medium">{{ pax.name }}</span> ·
                        {{ pax.passport }}<br />From group
                        {{ pax.group_number }}</span
                    >
                </label>
            </div>
            <p class="text-xs text-muted-foreground">
                Up to 50 matches. Refine your search if needed. Adding
                passengers does not reserve extra beds.
            </p>
            <p
                v-for="(error, field) in form.errors"
                :key="field"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ error }}
            </p>
            <Button
                :disabled="
                    form.processing || searching || !form.passenger_ids.length
                "
                @click="add"
                >{{
                    form.processing
                        ? 'Adding…'
                        : `Add selected (${form.passenger_ids.length})`
                }}</Button
            >
        </DialogContent>
    </Dialog>
</template>
