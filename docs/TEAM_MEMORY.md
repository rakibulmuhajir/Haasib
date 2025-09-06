# Team Memory — Working Principles (MVP Phase)

Last updated: 2025-09-05

These notes capture persistent decisions and constraints to keep delivery fast and consistent.

- MVP Priority: Deliver the MVP ASAP. Optimize for speed over breadth; defer nice-to-haves.
- PrimeVue First: Use PrimeVue as the single Vue UI/components library for all new and refactored UI. Avoid mixing multiple UI libraries; exceptions require an explicit note in this file.
- CLI Parity: Every guided palette feature must have a freeform counterpart (parseable via the input) with sensible synonyms/flags.

Context & Authority
- Solo Owner/PM: This project has a single owner who is both the lead developer and the project manager. The company relies on this role for delivery and technical direction.
- CEO Mandate: The CEO has expressed high confidence and granted full responsibility and decision authority to develop the SME accounting app (Laravel + Vue + Inertia).

Principles
- Don’t Reinvent the Wheel: Prefer proven, well-maintained libraries and patterns over bespoke implementations when quality is comparable.
- DRY: Centralize shared logic and UI patterns; compose rather than duplicate.

Frontend Library Decision
- Chosen Library: PrimeVue (deep expertise; “inside out” familiarity). Suitable for data-heavy enterprise UIs with robust DataTable/TreeTable, form controls, overlays, and accessibility.
- Theming: Use PrimeVue themes/presets and tokens; pair with Tailwind for layout/spacing utilities. Avoid re-styling components from scratch.
- Usage Rule: New UI should leverage PrimeVue components. If a needed primitive is missing, favor small, headless utilities with Tailwind, and document the exception here.

Migration SOP
- Plan: See `docs/mig-to-prime.md` for the end-to-end PrimeVue migration order (root → leaves), acceptance criteria per phase, and verification checklist.
- Dark Mode: Keep Tailwind’s `dark` class as the source of truth via `useTheme()`. Adjust PrimeVue theme tokens/CSS variables under `.dark` if needed for contrast.
- Removal: Reka UI will be removed after migration passes verification (grep shows no `reka-ui` imports; UI parity confirmed).

PrimeVue Guardrails
- Local Docs: Use `docs/primevue-inventory.md` and `app/node_modules/primevue/README.md` as the API source of truth for v4.3.9.
- Before Changes: For any new PrimeVue component/service/directive:
  1) Verify the import path exists in `app/node_modules/primevue/*`.
  2) If service/composable, confirm exported name (e.g., `confirmationservice`, `usetoast`).
  3) Add a minimal usage snippet in the PR description to lock in the API.
- Disallowed: Do not use undocumented paths (e.g., `confirmdialogservice`); grep the inventory first.
- Review Checklist: Ensure imports match inventory; avoid mixing Reka and PrimeVue in a single component.

PrimeVue Docs Sources
- Offline Showcase: `docs/vendor/primevue-docs/apps/showcase/doc/`
  - Each component has variant docs (e.g., Dialog: `.../dialog/WithoutModalDoc.vue`).
  - Use these as canonical examples before adding/updating components.
- Types as Spec: `app/node_modules/primevue/<component>/index.d.ts` and `app/node_modules/primevue/index.d.ts` define props/events/slots.
- Quick Inventory: `docs/primevue-inventory.md` lists valid import paths and services.

Example — Non‑modal Dialog
- Import: `import Dialog from 'primevue/dialog'`
- Usage: `<Dialog v-model:visible="open" :modal="false" :blockScroll="false" />`
- Reference: `docs/vendor/primevue-docs/apps/showcase/doc/dialog/WithoutModalDoc.vue`

Practical implications
- Stick to one feedback surface for validation (PrimeVue Toast). Avoid adding duplicate inline validation UIs unless explicitly requested.
- When adding new CLI verbs, include synonyms in `entities.ts` and extend the freeform parser for parity.
- Keep implementation surgical; avoid introducing overlapping widgets or redundant flows that increase maintenance burden.

Ownership
- Command Palette + Parser: Shared between frontend and backend. Keep responses structured for clear errors and previews when needed.
- Tests: Prefer lightweight Python probes/suites (tools/cli_probe.py, tools/cli_suite.py) and Playwright-based GUI checks (tools/gui_suite.py).

See also
- PR review checklist: `.github/pull_request_template.md`
