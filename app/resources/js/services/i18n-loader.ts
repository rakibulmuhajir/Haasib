const localeModules = import.meta.glob('../locales/*/*.json');

const loadedMessages = new Map<string, Record<string, unknown>>();

export const DEFAULT_LOCALE = 'en-US';
export const SUPPORTED_LOCALES = ['en-US', 'fr-FR'];

export function isSupportedLocale(locale: string): boolean {
    return SUPPORTED_LOCALES.includes(locale);
}

export async function loadLocaleMessages(locale: string): Promise<Record<string, unknown>> {
    if (loadedMessages.has(locale)) {
        return loadedMessages.get(locale)!;
    }

    const messages: Record<string, unknown> = {};

    const matchingEntries = Object.entries(localeModules).filter(([path]) =>
        path.includes(`/locales/${locale}/`),
    );

    if (!matchingEntries.length) {
        console.warn(`[i18n] No message files found for locale "${locale}". Falling back to ${DEFAULT_LOCALE}.`);
        if (locale === DEFAULT_LOCALE) {
            loadedMessages.set(locale, messages);
            return messages;
        }
        return loadLocaleMessages(DEFAULT_LOCALE);
    }

    for (const [, loader] of matchingEntries) {
        const mod = await loader();
        Object.assign(messages, mod.default ?? mod);
    }

    loadedMessages.set(locale, messages);
    return messages;
}
