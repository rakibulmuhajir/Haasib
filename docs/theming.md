# Blue Whale Theming Guide

The Haasib front end ships with a bespoke “Blue Whale” design system layered on top of PrimeVue, Tailwind, and Inertia.js. This document explains the moving pieces so you can extend the look and feel without re-inventing page-level styles.

## Theme Tokens & Location

- Primary stylesheet: `stack/resources/js/styles/themes/blue-whale.css`
- Light mode key: `blue-whale`
- Dark mode key: `blue-whale-dark`

The file defines CSS custom properties for brand colors, surfaces, typography, shadows, and PrimeVue token overrides. Tokens are grouped so Tailwind utilities, PrimeVue components, and bespoke CSS can all reference the same palette.

Notable variables:
- `--p-primary-*` / `--p-surface-*` – PrimeVue color ramps.
- `--bw-focus-ring-*` – Shared focus-ring width, color, and shadow.
- `--bw-surface-elevated-*` – Gradient + shadow system for raised containers.
- `--bw-table-*` – Table header, hover, and “current row” treatments.
- `--bw-floating-toolbar-*` – Specs for the sticky bulk-action pill.

## Global Focus Rings

The theme applies the `:focus-visible` styles globally via a `:where(...)` selector so any interactive element automatically picks up the Blue Whale ring and offset. Avoid overriding focus unless absolutely required for accessibility.

## Utility Classes

Use the pre-built classes instead of redefining ad-hoc styles:

| Utility | Purpose |
| --- | --- |
| `.bw-table-container` | Elevated table wrapper with responsive shadows. |
| `.bw-table` | Table typography, spacing, and hover states. |
| `.bw-table-row-current` | Highlights the active company / entity row. |
| `.bw-interactive-cell` + `__content` | Subtle translate animation on hover with reduced-motion support. |
| `.bw-floating-toolbar` | Floating bulk-action pill (with `__button`, `__count`, and `__divider` children). |

Feel free to compose these with Tailwind classes for layout tweaks, but resist copying the underlying gradients or shadows into page-level CSS—the utilities will continue to evolve.

## Theme Runtime (useTheme Composable)

File: `stack/resources/js/composables/useTheme.js`

Key behaviors:
1. Normalizes stored values (`'light'` and `'dark'` migrate to `'blue-whale'` / `'blue-whale-dark'`).
2. Sets the `data-theme` attribute on `<html>` and toggles the `.dark` class for Tailwind.
3. Persists the selected theme to `localStorage`; falls back to system preference when unset.

Usage:

```js
import { useTheme } from '@/composables/useTheme'

const { initializeTheme, toggleTheme, setTheme, theme, isDark } = useTheme()
```

- Call `initializeTheme()` once in your shell component (`LayoutShell` already does this).
- Bind `toggleTheme()` to buttons or commands.
- Use `setTheme('blue-whale-dark')` or `setTheme('blue-whale')` to force a specific variant (e.g., user profile settings).

## PrimeVue Configuration

`stack/resources/js/app.js` registers PrimeVue with the Aura preset, while the Blue Whale tokens supply overrides. When adding new PrimeVue components, trust the defaults first—only reach for scoped CSS if the component does not expose token hooks.

## Extending the Theme

1. Add new tokens or utilities to `blue-whale.css`. Keep variable names prefixed with `--bw-` to avoid collisions.
2. Reuse the new class in a component template (no additional scoped CSS needed).
3. If a PrimeVue component needs a token, extend the `:root[data-theme="blue-whale"]` block with the appropriate `--p-*` property so it applies everywhere.
4. Document any bespoke utilities in this file so future contributors understand available building blocks.

## Component Refactoring Patterns

### Index Page Modernization Approach

When modernizing existing Index pages to use the Blue Whale theme system, follow this proven pattern:

**1. Replace Custom CSS with Theme Utilities**
- Remove complex custom gradients and shadows in favor of `.bw-*` utility classes
- Use `.bw-table-container`, `.bw-table`, and `.bw-table-row-current` instead of inline table styling
- Apply `.bw-interactive-cell` and `__content` for consistent hover animations

**2. Adopt PrimeVue Components Consistently**  
- Replace custom search implementations with `IconField` and `InputIcon` components
- Use PrimeVue `DataTable` or custom tables with theme utility classes for consistency
- Leverage PrimeVue's built-in accessibility features

**3. Implement Null-Safe Data Handling**
- Add comprehensive null checks: `{{ company.industry || 'N/A' }}`
- Ensure all components gracefully handle incomplete API data
- Use computed properties with proper fallbacks

**4. Maintain Single File Architecture**
- Keep primary logic centralized in one `Index.vue` file
- Use smaller components (CompanyRow, CompanyCard) for specific display patterns
- Ensure consistent data flow and state management patterns

**Success Example**: Companies/Index.vue (2025-10-20) demonstrates:
- Clean table styling with `.bw-table-*` utilities
- PrimeVue search with proper debouncing
- Null-safe data display across all components
- Simplified CSS architecture using theme tokens

By consolidating theme primitives in one place and applying consistent refactoring patterns, we minimize drift and keep Haasib's UI cohesive across modules.
