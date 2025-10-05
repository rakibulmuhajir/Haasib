<!--
Sync Impact Report:
Version change: 1.0.0 → 2.0.0 (major overhaul - added comprehensive AI coder binding rules)
List of modified principles: Expanded from 5 to 10 non-negotiable rules
Added sections: AI Coder Binding (primary section), Technical Integrity Rules
Removed sections: N/A (restructured existing content)
Templates requiring updates:
  ✅ plan-template.md (already references constitution correctly)
  ✅ spec-template.md (already aligned with constitutional principles)
  ✅ tasks-template.md (already reflects TDD and task organization principles)
Follow-up TODOs: N/A
-->

# Haasib Constitution

## AI Coder Binding
*This constitution binds any AI coder working on Haasib. Every directive below is non-negotiable—violating one voids the session.*

### I. Single Source Doctrine
Obey canonical docs (handbook, playbook, module guide, briefs). Never contradict or bypass them; if guidance seems missing, pause and clarify before acting. Canonical documentation sources in `/docs/` directory supersede all other instructions.

### II. Command-Bus Supremacy
All write flows must dispatch registered command actions (app/config/command-bus.php). Controllers, CLI, future clients share these actions. No direct service mutations without command registration. Every mutation MUST be traceable through the command bus.

### III. CLI–GUI Parity
Every GUI capability must have an equal CLI path. Keep palette metadata and parser fragments in sync with command-bus actions, and test them together. CLI commands use unique names (no module prefixes) and support natural language interaction.

### IV. Tenancy & RLS Safety
Every tenant record carries company_id. Enforce RLS and policies, never weaken isolation or bypass permissions. Direct database access bypassing safety checks is prohibited. All queries MUST include tenant scoping.

### V. RBAC Integrity
Respect the seeded role/permission catalog. No hidden routes, no privilege creep. Every new feature declares precise permissions and tests allow/deny behavior. All endpoints MUST have permission guards.

### VI. Translation & Accessibility
All user-facing strings live in locale files (EN + AR baseline). Preserve accessibility cues (ARIA labels, focus states) and confirm right-to-left rendering where applicable. Hard-coded strings in views are prohibited.

### VII. PrimeVue v4 & FontAwesome 5 Compliance
Build UI with PrimeVue v4 components, synchronized light/dark themes, and the FontAwesome 5 icon set described in docs. Mixing component libraries is prohibited. Custom CSS only through Tailwind utilities.

### VIII. Module Governance
Use php artisan module:make to scaffold modules under modules/<Name>. Update registries, providers, and company-module toggles exactly as documented; no ad-hoc directories. Each module must be independently testable and optionally enabled.

### IX. Tests Before Triumph
Add or update unit, feature, CLI, and RLS tests to cover each change. Never mark a feature complete without verifying automation and documenting manual QA. TDD is mandatory: failing tests written first.

### X. Audit, Idempotency & Observability
All write actions log via the audit system and enforce idempotency keys. Expose structured errors, maintain logs/traces, and never silence failures. Every financial mutation MUST be auditable and idempotent.

## Additional Constraints

### Documentation Fidelity
Update briefs, trackers, and modules guide after every change. The docs must mirror reality before handing off work. Documentation changes are part of the feature completion.

### No Undocumented Dependencies or Shortcuts
Do not install or rely on new packages, schemas, or toggles unless the docs are updated and the change is approved. All dependencies must be declared in composer.json with version constraints.

### Pause on Ambiguity
If requirements conflict or clarity is missing, stop and ask. Acting on guesswork is prohibited. Mark unclear requirements with [NEEDS CLARIFICATION] and await human guidance.

### Decompose & Align
Before executing significant work, break tasks into clear sub-steps (naming conventions, migration column choices, service boundaries, testing strategy) and surface them to the human for confirmation. Never assume silent approval for structural decisions.

## Architecture Standards

### Technology Stack
**Backend**: Laravel 12 with PHP 8.2+, PostgreSQL 16, REST APIs
**Frontend**: Vue 3 with Inertia.js v2, PrimeVue v4, Tailwind CSS
**Testing**: Pest v4 for backend, Playwright for E2E
**Performance**: Laravel Octane with Swoole, <200ms p95 response times

### Code Quality
- Laravel Pint for code formatting
- ServiceContext pattern for user context and tenancy
- Command Bus pattern for all mutations
- Role-based access control with granular permissions
- All financial operations must be auditable
- Enforce Single Responsibility: prefer small, focused classes/files; orchestrators should remain thin and delegate to services/actions; long god classes are prohibited.

## Development Workflow

### Phase Gates
1. **Specification**: User requirements documented in spec.md with acceptance criteria
2. **Planning**: Technical design created with data models and contracts
3. **Task Generation**: Implementation tasks ordered by dependency
4. **Implementation**: TDD cycle with tests before code
5. **Validation**: Automated tests pass, manual QA completed

### Quality Gates
- All tests must pass before merge
- Code coverage minimum 80%
- Performance benchmarks met
- Security review completed for sensitive features
- Documentation updated

## Governance

This constitution supersedes all other practices and guidelines. Amendments require:
1. Documented proposal with rationale
2. Owner approval (current: solo founder/PM)
3. Version increment following semantic versioning
4. Migration plan for existing code
5. Communication to all team members

All PRs and reviews must verify compliance with constitutional principles. Complexity beyond simple patterns must be explicitly justified in the PR description. For runtime development guidance, refer to project-specific documentation in the docs/ directory.

### Salvage & Heritage Clause
- Preserve and reuse the CLI architecture established in the `main` branch’s latest commit; it remains the canonical pattern for command bus + palette integration.
- The `rebootstrap-primevue` branch contains vetted core and invoicing implementations—treat them as reference implementations when rebuilding modules; import logic rather than rewriting blindly.
- Schemas under `/docs/schemas/` are the authoritative data backbone. Adjustments may be applied for practical reasons, but these files are the primary blueprint for table structure, constraints, and RLS strategies.

**Version**: 2.0.0 | **Ratified**: 2025-01-16 | **Last Amended**: 2025-01-16
