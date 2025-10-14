# PrimeVue Migration Plan

Last updated: 2025-09-05

Purpose: Replace Reka UI components/primitives with PrimeVue across the app, while keeping Tailwind for layout/spacing and the existing dark-mode toggle. Execute from root → leaves with clear acceptance at each phase.

Scope: Entire Vue/Inertia frontend: toasts, confirms, tooltips, dialogs, tabs, collapsibles, menus, forms (inputs, buttons, checkboxes), pickers, data tables, navigation/sidebar, and theme integration. Reka UI is removed only after final verification.

Prerequisites
- Installed: `primevue @primeuix/themes` (done)
- Optional: `primeicons` for built-in icons
  - `npm i primeicons` and import `primeicons/primeicons.css` in `stack/resources/js/app.js`

Core Wiring (Phase 0)
- File: `stack/resources/js/app.js`
  - Use `PrimeVue` with `Aura` preset, `ToastService`, `ConfirmDialogService`. [Done]
- File: `stack/resources/js/Layouts/AuthenticatedLayout.vue`
  - Keep Reka provider temporarily. Render `<Toast />` and `<ConfirmDialog />` once globally. [Done]
- Acceptance: App boots clean; both toast systems can coexist.

Phase 1 — Feedback & Confirmation
Goal: Migrate notifications and confirmation flows first.
- File: `stack/resources/js/composables/useToasts.js`
  - Replace custom store with PrimeVue `useToast` thin wrapper. Keep same external API if referenced widely.
  - Example:
    ```ts
    import { useToast as usePvToast } from 'primevue/usetoast'
    export function useToasts() {
      const toast = usePvToast()
      const add = (severity, message, life = 3000) => toast.add({ severity, summary: severity, detail: message, life })
      return { add, success: (m,l)=>add('success',m,l), info: (m,l)=>add('info',m,l), warning: (m,l)=>add('warn',m,l), danger: (m,l)=>add('error',m,l) }
    }
    ```
- File: `stack/resources/js/Components/Toasts.vue`
  - Remove Reka usage; either delete or convert into a no-op once all callers use `useToast`.
- File: `stack/resources/js/Layouts/AuthenticatedLayout.vue`
  - Remove `<Toasts />` and then `<ToastProvider>` after all toasts are migrated.
- Grep: Replace any direct Reka `Toast*` usage.
- Acceptance: All toasts and confirms work via PrimeVue; no `ToastRoot/ToastProvider` left.

Phase 2 — Tooltip
Goal: Replace Reka tooltip primitives with PrimeVue tooltip directive.
- File: `stack/resources/js/Components/Tooltip.vue`
  - Keep the same prop API and apply `v-tooltip="{ value: text, showDelay: 0 }"` to the trigger slot.
  - Or delete the wrapper and apply `v-tooltip` directly where used.
- Files: `SidebarNavItem.vue` and any tooltip consumers
  - Update to use the wrapper or `v-tooltip` directly.
- Acceptance: Tooltips render identically; no Reka tooltip imports.

Phase 3 — Collapsible
Goal: Replace Reka Collapsible with PrimeVue.
- Strategy A: Use `Panel` with `toggleable` to mimic open/close sections.
- Strategy B: Use `Accordion` when a group behavior fits better.
- File: `stack/resources/js/Components/Collapsible.vue`
  - Keep external slots intact; internally render PrimeVue `Panel`.
- Files: `CompanyMemberList.vue`, `UserMembershipList.vue`
  - Update imports/props if the wrapper API changed.
- Acceptance: Toggle behavior and a11y preserved.

Phase 4 — Dialogs (Command Palette)
Goal: Replace Reka dialogs with PrimeVue `Dialog`.
- File: `stack/resources/js/Components/CommandPalette.vue`
  - Replace `DialogRoot/Portal/Overlay/Content` with `<Dialog :modal="true" :draggable="false" :dismissableMask="false">` and custom header/body.
  - Keep keyboard shortcuts and focus handling as-is.
- Acceptance: Palette opens/closes correctly, overlay and sizing preserved.

Phase 5 — Tabs
Goal: Replace Reka tabs with PrimeVue `TabView`/`TabPanel`.
- Files: `stack/resources/js/Pages/Admin/Users/Show.vue`, `.../Companies/Show.vue`
  - Map active tab state; maintain deep-linking if present.
- Acceptance: Tabs functionally equivalent.

Phase 6 — Menus/Dropdowns
Goal: Migrate dropdowns and menus used in header/user menu.
- Files: `Dropdown.vue`, `DropdownLink.vue`, `UserMenu.vue`
  - Option A (minimal change): Keep `Dropdown.vue` wrapper but implement with `OverlayPanel` internally so external API remains stable.
  - Option B: Replace with PrimeVue `Menu` popup + `Button` trigger, adjusting callers.
- Acceptance: Keyboard and click-outside behavior correct.

Phase 7 — Forms (atoms)
Goal: Standardize on PrimeVue inputs/buttons while preserving external props.
- Files and mappings:
  - `TextInput.vue` → `InputText`
  - `Checkbox.vue` → `Checkbox`
  - `PrimaryButton.vue`, `SecondaryButton.vue`, `DangerButton.vue` → `Button` with `severity`, `outlined`, `text` as needed.
  - `InputLabel.vue`, `InputError.vue` can remain or be aligned for consistent spacing.
- Acceptance: Validation styles intact; no layout regressions.

Phase 8 — Pickers/Selects
Goal: Replace custom selects with data-aware PrimeVue components.
- Files: `Pickers/*` → `Dropdown`, `MultiSelect`, `AutoComplete` depending on feature.
- Acceptance: Search, clear, and async options behave as before.

Phase 9 — Data-heavy widgets (as needed)
Goal: Introduce PrimeVue for tables/trees/calendars/uploads.
- Examples: `DataTable` + `Column`, `TreeTable`, `Calendar`, `FileUpload`.
- Acceptance: Pagination/sorting/filters perform; virtualization if needed.

Phase 10 — Navigation & Sidebar
Goal: Ensure layout elements are consistent with PrimeVue styling.
- Files: `AppHeader.vue`, `AppSidebar.vue`, `SidebarNavItem.vue`, `AppMobileNav.vue`
  - Keep Tailwind layout; use PrimeVue atoms where helpful (e.g., `Button`, `Badge`).
  - Ensure tooltips/menus already migrated in previous phases.
- Acceptance: No style clashes; responsive behavior preserved.

Phase 11 — Theme & Dark Mode Alignment
Goal: Make PrimeVue theme harmonize with existing dark-mode toggle.
- Current: `useTheme()` toggles `document.documentElement.classList.toggle('dark', isDark)`.
- Action:
  - Keep Tailwind dark mode as the source of truth.
  - If needed, override PrimeVue CSS variables inside `.dark { ... }` to refine contrast.
  - Optional: expose a small `usePrimeTheme()` util if we want to swap presets later.
- Acceptance: Components look coherent in light and dark; no unreadable contrasts.

Phase 12 — Cleanup
Goal: Remove Reka UI from the codebase.
- Grep: `rg reka-ui` → should return no results.
- Files: Remove any leftover wrappers using Reka.
- Package: `npm remove reka-ui` after final checks.
- Acceptance: Build succeeds; UI works; docs updated.

Verification Checklist
- Toasts: PrimeVue only; `useToasts.js` wraps `useToast`.
- Confirms: `useConfirm()` paths in destructive actions.
- Tooltips/Collapsible/Tabs/Dialogs: All PrimeVue; keyboard/a11y OK.
- Forms: Inputs and buttons migrated; consistent spacing.
- Menus: User menu and nav menus migrated.
- Theme: Dark mode consistent; no jarring colors.
- Reka: No imports left; package removed.

Rollout Strategy
- Migrate page-by-page, preferring shared components first.
- After Phase 1 completes, remove Reka toast provider to avoid dual notifications.
- Commit in small PRs per phase to ease review/reverts.

Notes
- Keep Tailwind for layout; use PrimeVue components for interactive controls.
- Prefer wrapper components to minimize churn in consumers; swap internals first.
