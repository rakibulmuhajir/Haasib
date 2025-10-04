import { createI18n } from 'vue-i18n';
import {
    DEFAULT_LOCALE,
    SUPPORTED_LOCALES,
    isSupportedLocale,
    loadLocaleMessages,
} from './i18n-loader';

export { DEFAULT_LOCALE, SUPPORTED_LOCALES } from './i18n-loader';

type I18nInstance = ReturnType<typeof createI18n>;

let i18nInstance: I18nInstance | null = null;

const RTL_LOCALES = new Set(['ar', 'fa', 'he']);

function resolveLocale(candidate?: string | null): string {
    if (!candidate) {
        return DEFAULT_LOCALE;
    }

    if (isSupportedLocale(candidate)) {
        return candidate;
    }

    const normalized = candidate.split('-')[0];
    const fallback = SUPPORTED_LOCALES.find((locale) => locale.startsWith(normalized));

    return fallback ?? DEFAULT_LOCALE;
}

function syncDocumentMetadata(locale: string) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.setAttribute('lang', locale);
    const direction = RTL_LOCALES.has(locale.split('-')[0]) ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', direction);
}

export async function createI18nInstance(initialLocale?: string): Promise<I18nInstance> {
    const browserLocale = typeof navigator === 'undefined' ? undefined : navigator.language;
    const locale = resolveLocale(initialLocale ?? getPersistedLocale() ?? browserLocale);
    const messages = await loadLocaleMessages(locale);

    i18nInstance = createI18n({
        legacy: false,
        locale,
        fallbackLocale: [DEFAULT_LOCALE],
        messages: {
            [locale]: messages,
        },
    });

    syncDocumentMetadata(locale);

    return i18nInstance;
}

export function getI18nInstance(): I18nInstance {
    if (!i18nInstance) {
        throw new Error('i18n instance has not been initialised. Call createI18nInstance() first.');
    }
    return i18nInstance;
}

export async function setLocale(locale: string): Promise<void> {
    const targetLocale = resolveLocale(locale);

    const instance = getI18nInstance();

    if (instance.global.locale.value === targetLocale) {
        return;
    }

    const messages = await loadLocaleMessages(targetLocale);

    instance.global.setLocaleMessage(targetLocale, messages);
    instance.global.locale.value = targetLocale;

    persistLocale(targetLocale);
    syncDocumentMetadata(targetLocale);
}

const STORAGE_KEY = 'haasib.locale';

function getPersistedLocale(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            return stored;
        }
        const match = document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${STORAGE_KEY}=`));
        return match ? match.split('=')[1] : null;
    } catch (error) {
        console.warn('[i18n] Unable to read persisted locale', error);
        return null;
    }
}

function persistLocale(locale: string) {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(STORAGE_KEY, locale);
        document.cookie = `${STORAGE_KEY}=${locale};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
    } catch (error) {
        console.warn('[i18n] Unable to persist locale', error);
    }
}
