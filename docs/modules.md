# Module Development Guide

This guide defines how to create new modules and where module code must live.

## Core Rule
- All module logic must live inside the module folder. Do not place module code in core folders.

## Required Module Structure
Each module must own its full stack:
- Migrations
- Models
- Services and actions
- Controllers and requests
- Routes
- Views/pages/components
- Sidebars (override module sidebar entries in core)

## Location
- Create modules in `build/modules/{ModuleName}/`.
- Module-specific assets live under the module’s own resources folder.

## Sidebar Overrides
- Each module must provide its own navigation configuration at `Resources/js/nav.ts`.
- The host app aggregates module navigation entries via the registry in `build/resources/js/navigation/registry.ts`.
- Standalone business modules (e.g., FuelStation) may override or replace the sidebar.

## Required Module Docs (in module root)
Create these documents before implementation:
- `permissions.md`
  - List all module-specific permissions.
  - List any new roles needed for the module.
  - Include permission-to-role mapping if applicable.
- `coa.md`
  - List all default accounts introduced by the module.
  - List all posting templates introduced or required by the module.
  - Include account codes, names, and types/subtypes.

## Planning Expectations
- Plan permissions and COA upfront before coding.
- Use these docs to keep development consistent and prevent missing pieces.

## Module Types
- Helper modules provide shared capabilities and expose navigation entries for aggregation.
- Standalone business modules (verticals) can override or replace the sidebar if needed.

## Notes
- Keep module scope isolated; do not leak module logic into core.
- If a module needs shared infrastructure, add it to core only if it is truly shared.
