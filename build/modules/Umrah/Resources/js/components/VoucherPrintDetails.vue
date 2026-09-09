<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref } from 'vue';

export type VoucherContact = {
    name: string;
    responsibility: string;
    organization: string;
    phone: string;
    whatsapp: string;
    city: string;
};
export type PrintDetails = { footer_text: string; contacts: VoucherContact[] };
export type ContactProfile = {
    key: string;
    label: string;
    details: PrintDetails;
};
const details = defineModel<PrintDetails>({ required: true });
const props = withDefaults(
    defineProps<{
        profiles?: ContactProfile[];
        errors?: Record<string, string>;
        prefix?: string;
        disabled?: boolean;
    }>(),
    {
        profiles: () => [],
        errors: () => ({}),
        prefix: 'print_details',
        disabled: false,
    },
);
const selected = ref('');
const options = computed(() =>
    props.profiles.flatMap((profile) =>
        (profile.details.contacts || []).map((contact, index) => ({
            value: `${profile.key}/${index}`,
            label: `${contact.name} · ${contact.city || contact.responsibility} · ${profile.label}`,
        })),
    ),
);
const fields = [
    {
        key: 'responsibility',
        label: 'Responsibility',
        placeholder: 'Makkah assistance',
        max: 100,
    },
    {
        key: 'name',
        label: 'Representative name',
        placeholder: 'Name',
        max: 150,
    },
    {
        key: 'organization',
        label: 'Company / agent',
        placeholder: 'Organization',
        max: 150,
    },
    { key: 'city', label: 'City', placeholder: 'Makkah / Madinah', max: 100 },
    { key: 'phone', label: 'Phone', placeholder: '+966 …', max: 50 },
    {
        key: 'whatsapp',
        label: 'WhatsApp (optional)',
        placeholder: '+966 …',
        max: 50,
    },
] as const;
function addContact() {
    const [key, index] = selected.value.split('/');
    const source = props.profiles.find((profile) => profile.key === key)
        ?.details.contacts[Number(index)];
    details.value.contacts.push(
        source
            ? { ...source }
            : {
                  name: '',
                  responsibility: '',
                  organization: '',
                  city: '',
                  phone: '',
                  whatsapp: '',
              },
    );
    selected.value = '';
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="profiles.length" class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 space-y-2">
                <Label>Saved representatives</Label>
                <SearchableSelect
                    v-model="selected"
                    :options="options"
                    :show-value="false"
                    placeholder="Find a representative by name, city or provider"
                    :disabled="disabled"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="
                    disabled || !selected || details.contacts.length >= 12
                "
                @click="addContact"
                >Add selected</Button
            >
        </div>
        <p class="text-sm text-muted-foreground">
            Only the contacts listed below will print. Choose different
            providers for each responsibility if needed.
        </p>
        <p
            v-if="errors[prefix] || errors[`${prefix}.contacts`]"
            class="text-sm text-destructive"
        >
            {{ errors[prefix] || errors[`${prefix}.contacts`] }}
        </p>
        <div
            v-for="(contact, index) in details.contacts"
            :key="index"
            class="space-y-3 border p-3"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium">Contact {{ index + 1 }}</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="disabled"
                    @click="details.contacts.splice(index, 1)"
                    >Remove contact {{ index + 1 }}</Button
                >
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="field in fields"
                    :key="field.key"
                    class="min-w-0 space-y-1"
                >
                    <Label :for="`${prefix}-${index}-${field.key}`">{{
                        field.label
                    }}</Label>
                    <Input
                        :id="`${prefix}-${index}-${field.key}`"
                        v-model="contact[field.key]"
                        :placeholder="field.placeholder"
                        :maxlength="field.max"
                        :disabled="disabled"
                    />
                    <p
                        v-if="
                            errors[`${prefix}.contacts.${index}.${field.key}`]
                        "
                        class="text-sm text-destructive"
                    >
                        {{ errors[`${prefix}.contacts.${index}.${field.key}`] }}
                    </p>
                </div>
            </div>
        </div>
        <Button
            type="button"
            variant="outline"
            :disabled="disabled || details.contacts.length >= 12"
            @click="
                selected = '';
                addContact();
            "
            >Add contact</Button
        >
        <div class="space-y-2">
            <Label :for="`${prefix}-footer`">Voucher footer / terms</Label>
            <Textarea
                :id="`${prefix}-footer`"
                v-model="details.footer_text"
                :disabled="disabled"
                :maxlength="2000"
                :rows="4"
                placeholder="Optional instructions or terms to print below the contacts"
            />
            <p
                v-if="errors[`${prefix}.footer_text`]"
                class="text-sm text-destructive"
            >
                {{ errors[`${prefix}.footer_text`] }}
            </p>
            <p class="text-xs text-muted-foreground">
                Keep this short for a single-page voucher. Empty text is not
                printed.
            </p>
        </div>
    </div>
</template>
