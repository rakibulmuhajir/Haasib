# Vue I18n Implementation Guide

This document describes how to add multi-language support to the Haasib frontend with Vue 3 and vue-i18n v9. Follow it end-to-end to set up the localization infrastructure, migrate existing UI strings, and establish a sustainable translation workflow.

## Goals & Success Criteria
- Offer a consistent, high-quality experience in each supported language with minimal runtime overhead.
- Keep locale-specific logic isolated so developers can add languages or copy without regression risk.
- Provide a repeatable translation workflow that scales across teams, environments, and release cadence.
- Ensure SSR/SSG builds, analytics, and QA automation continue to work after localization.

## Prerequisites
- Vue 3 + Vite (or equivalent modern bundler) project using Composition API.
- Node 18+ and PNPM/Yarn/NPM available for package installs.
- Global state management (Pinia/Vuex) and router ready to share current locale.
- Agreement on priority locales, fallback ordering, and copy ownership.

## Architectural Decisions
- **Library**: vue-i18n v9 with the Composition API (`useI18n`). Legacy API remains available only for Options components.
- **Message format**: ICU syntax for pluralization, gender, and formatted values. Store messages as UTF-8 JSON.
- **Resource structure**: Keep locale bundles under `src/locales/<locale>/<namespace>.json`. Namespace by domain (e.g., `auth`, `billing`) to simplify lazy loading.
- **Fallback chain**: `[userLocale, tenantDefault, 'en-US']`. Missing keys log to Sentry + console (development) to surface gaps.
- **Lazy loading**: Code-split locale bundles with dynamic imports so non-default languages do not inflate the initial payload.
- **State persistence**: Persist selected locale in a cookie (for SSR) and `localStorage` (client). Router guards hydrate the store on navigation.
- **Testing**: Use unit tests for key formatting, integration tests per locale for screens, and screenshot regression for RTL.

## Installation & Project Wiring

1. Install dependencies:
   ```bash
   pnpm add vue-i18n@^9.0.0
   pnpm add -D @intlify/unplugin-vue-i18n eslint-plugin-i18n-json
   ```
2. Update `vite.config.ts` to enable the vue-i18n plugin:
   ```ts
   import vueI18n from '@intlify/unplugin-vue-i18n/vite';

   export default defineConfig({
     plugins: [
       vue(),
       vueI18n({
         include: path.resolve(__dirname, 'src/locales/**'),
         runtimeOnly: true,
         compositionOnly: true,
         defaultSFCLang: 'json'
       })
     ]
   });
   ```
3. Create the locale directory structure:
   ```text
   src/locales/
   ├─ en-US/
   │  ├─ common.json
   │  ├─ auth.json
   │  └─ billing.json
   └─ fr-FR/
      ├─ common.json
      └─ auth.json
   ```
4. Add a message key naming convention (e.g., `namespace.page.section.key`) to avoid collisions and clarify context.
5. Configure ESLint rule:
   ```json
   {
     "extends": ["plugin:i18n-json/recommended"],
     "rules": {
       "i18n-json/valid-message-syntax": ["error", { "syntax": "icu" }]
     }
   }
   ```

## Bootstrapping vue-i18n

Create `src/plugins/i18n.ts` to instantiate the i18n instance and expose helper utilities.

```ts
import { createI18n } from 'vue-i18n';
import { loadLocaleMessages, defaultLocale } from '@/services/i18n-loader';

export const SUPPORTED_LOCALES = ['en-US', 'fr-FR'];

export async function setupI18n(locale?: string) {
  const targetLocale = locale ?? window.navigator.language ?? defaultLocale;
  const messages = await loadLocaleMessages(targetLocale);

  return createI18n({
    legacy: false,
    locale: targetLocale,
    fallbackLocale: ['en-US'],
    messages: { [targetLocale]: messages }
  });
}
```

In `main.ts` bootstrapping code, hydrate the instance before mounting the app:

```ts
const i18n = await setupI18n(initialLocaleFromSSR);
app.use(i18n);
```

## Locale Detection & Persistence

1. **Server request**: For SSR, derive locale from host mapping, user profile, or `Accept-Language`. Store it in the session or signed cookie.
2. **Router guard**: On client navigation, read `router.currentRoute.value.params.lang` or cookie/localStorage and update the i18n instance if the locale changes.
3. **Helper service**: Provide `setLocale(locale: string)` that updates vue-i18n, persists the value, and preloads resources:

```ts
export async function setLocale(newLocale: string) {
  const i18n = useI18n();
  if (i18n.locale.value === newLocale) return;

  const messages = await loadLocaleMessages(newLocale);
  i18n.mergeLocaleMessage(newLocale, messages);
  i18n.locale.value = newLocale;
  localStorage.setItem('locale', newLocale);
  document.documentElement.setAttribute('lang', newLocale);
}
```

4. **Accessibility**: Flip `<html dir="rtl">` for RTL locales and adjust theming tokens.

## Loading Locale Messages Lazily

Use dynamic imports per namespace to avoid bundling every language in the main chunk.

```ts
const loadedLocales = new Set<string>();

export async function loadLocaleMessages(locale: string) {
  if (loadedLocales.has(locale)) return i18n.global.getLocaleMessage(locale);

  const modules = import.meta.glob(`../locales/${locale}/*.json`);
  const messages = {} as Record<string, unknown>;

  for (const path in modules) {
    const mod = await modules[path]();
    Object.assign(messages, mod.default ?? mod);
  }

  loadedLocales.add(locale);
  return messages;
}
```

- For Vite, group namespaces with magic comments: `/* @vite-ignore */` or `/* webpackChunkName: "locale-[request]" */` depending on bundler.
- Preload default locale at build time to prevent waterfall requests on first paint.
- During SSR, call `await loadLocaleMessages(locale)` on the server before rendering to send the hydrated state to the client.

## Component Usage Patterns

- **Composition API**: Preferred approach within `<script setup>` components.
  ```vue
  <template>
    <h1>{{ t('dashboard.header.title', { user: userName }) }}</h1>
    <p>{{ d(new Date(lastLogin), 'short') }}</p>
  </template>

  <script setup lang="ts">
  import { useI18n } from 'vue-i18n';

  const { t, d, n, availableLocales } = useI18n();
  </script>
  ```
- **Options API**: For legacy components, enable `legacy: false` but expose `global.t` via mixin until those files are migrated.
- **Scoped locales**: Use `useI18n({ useScope: 'local', messages: {...} })` for component-specific translations (e.g., charts) to avoid polluting global namespaces.
- **Parameter hygiene**: Prefer explicit objects (`{ count }`) rather than positional arguments to keep translator context clear.

## Formatting Helpers & Utilities

- Use vue-i18n built-ins `d()` and `n()` for date and number formatting with locale-aware presets defined in `src/i18n/formats.ts`.
- For currency, rely on `Intl.NumberFormat` with `currencyDisplay: 'symbol'` and fallback to ISO code when symbol missing.
- Handle pluralization and gender via ICU message syntax, e.g., `{count, plural, one {# invoice} other {# invoices}}`.
- Document any custom formatter functions so translators understand expected placeholders.

## Validation, Errors & Logs

- Wrap validation messages in translation keys; surface developer-friendly fallbacks in logs only.
- For exceptions thrown server-side, map error codes to localized messages client-side to avoid embedding translations in API responses.
- Ensure logs/metrics remain English to simplify ops runbooks.

## Testing Strategy

- **Unit tests**: Mock i18n with generated messages to confirm keys exist and parameters render correctly.
- **Integration tests**: Use Cypress or Playwright to verify locale switchers, routing (`/fr/...`), and persisted session behavior.
- **Snapshot tests**: Capture reference screenshots per locale (especially RTL) after key releases.
- **Static checks**: Lint for unused keys, missing translations (`eslint-plugin-i18n-json` + custom script that diff en-US vs other locales).

## Translation Workflow

1. **String extraction**: Developers add keys to `en-US` JSON files and mark placeholders with descriptive names.
2. **Sync script**: Provide `pnpm run i18n:sync` that:
   - Scaffolds missing locale files with TODO markers.
   - Validates ICU syntax using `@intlify/message-compiler`.
   - Rejects commits if fallback locale contains duplicates.
3. **Review**: Translation submissions go through PR review (dev + product + translator). Use branch naming like `i18n/fr-dashboard` for traceability.
4. **External tooling**: When connecting to a TMS (Lokalise, Phrase), integrate via API to push/pull JSON bundles and run validation after pulls.
5. **Versioning**: Tag translation bundle versions in release notes; maintain changelog so support teams know when new languages ship.

## Adding New Pages & Generating Copy

Follow this checklist whenever a feature introduces new UI text.

1. **Plan the namespace**
   - Pick a descriptive namespace that matches the route or domain (`profile`, `settings.general`, etc.).
   - Update `resources/js/services/i18n-loader.ts` if you introduce a new locale folder structure.

2. **Write English copy first**
   - Draft copy alongside the design review; confirm tone with product.
   - Add keys to the relevant `en-US/*.json` file using the `namespace.section.element` pattern.
   - Prefer complete sentences and avoid reusing keys with different context.

3. **Scaffold other locales immediately**
   - Run `pnpm run i18n:sync` (once available) to create stubs for every supported locale.
   - If a locale is not ready, leave the value as `TODO` and log the gap in the translation backlog issue.

4. **Wire components to vue-i18n**
   - Import `useI18n` in the new page/component and replace literals with `t('namespace.key')` calls.
   - For lists or tables, store reusable labels (column headers, tooltips) next to the page namespace.
   - Keep aria labels, tooltips, and dialog copy in the same namespace as the triggering component.

5. **Update supporting code**
   - Extend Playwright/Cypress coverage to toggle locales on the new page if it is a major surface.
   - Add unit tests for computed i18n helpers when they manipulate arrays or dictionaries (e.g., tab configs).

6. **Request translations**
   - Ping the localisation channel or create a TMS job referencing the new keys.
   - Once translations return, replace `TODO` entries and re-run `pnpm run i18n:sync` to ensure parity.

7. **Validate before merging**
   - Run `npm run build` and the locale smoke test (`npx playwright test tests/e2e/locale-switcher.spec.ts`).
   - Check the console for `[i18n]` warnings while browsing the new page in each locale.

Document the new keys in the feature PR description so QA and translators know where to verify them.

## Deployment & Monitoring

- Include locale bundles in build artifacts; for CDN delivery, set `cache-control` with long max-age and unique hashes per build.
- Emit telemetry (e.g., via Segment) when users switch locale to track adoption.
- Log missing translations with structured payload (`{ key, locale, route }`) and alert when counts exceed threshold.
- Monitor bundle size per locale to ensure lazy-loaded chunks stay under designated budgets.

## Rollout Plan

1. **Phase 0**: Integrate vue-i18n, externalize a pilot surface (e.g., auth flows) in English only with keys.
2. **Phase 1**: Add secondary locale (fr-FR) behind feature flag. QA targeted flows, gather feedback.
3. **Phase 2**: Expand to core app, complete regression pass, add screenshot tests.
4. **Phase 3**: Enable locale selector publicly, update onboarding docs, and announce availability.
5. **Phase 4**: Add tertiary locales. Automate TMS sync, finalize governance.

## Maintenance Checklist

- Review translation debt monthly; triage missing keys and outdated strings.
- Update locale list and fallback chain when new markets launch.
- Schedule i18n regression testing ahead of each quarterly release.
- Document style guide for translators (tone, glossary, persona) in `docs/translation-style-guide.md` (to be created).

## Reference Links

- vue-i18n Docs: https://vue-i18n.intlify.dev/
- ICU Message Format Guide: https://formatjs.io/docs/core-concepts/icu-syntax
- Intl APIs: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl
- eslint-plugin-i18n-json: https://github.com/adam-paterson/eslint-plugin-i18n-json
- Lokalise CLI (example TMS): https://docs.lokalise.com/en/articles/1400528-cli-tool
