<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { setLocale, SUPPORTED_LOCALES } from '@/services/i18n';

const page = usePage();
const { locale, t } = useI18n();

const LOCALE_LABELS: Record<string, string> = {
    'en-US': 'English (US)',
    'fr-FR': 'Français',
};

const availableLocales = computed(() => {
    const fromServer = (page.props.supportedLocales as string[] | undefined) ?? [];
    const codes = fromServer.length ? fromServer : SUPPORTED_LOCALES;
    return codes.map((code) => ({
        code,
        name: LOCALE_LABELS[code] ?? code,
    }));
});

const selectedLocale = ref<string>(locale.value);

watch(
    () => locale.value,
    (next) => {
        selectedLocale.value = next;
    },
);

watch(selectedLocale, async (next, prev) => {
    if (!next || next === prev) {
        return;
    }

    try {
        await setLocale(next);
    } catch (error) {
        console.error('[i18n] Failed to switch locale', error);
        selectedLocale.value = prev ?? locale.value;
    }
});
</script>

<template>
    <Dropdown
        v-model="selectedLocale"
        :options="availableLocales"
        optionLabel="name"
        optionValue="code"
        class="w-40"
        data-testid="locale-switcher"
        :aria-label="t('navigation.language')"
    />
</template>
