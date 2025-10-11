 i dont want to lose your important thoughts on this though. can you add these to @/.claude/commands/constitution.md and @/docs/dosdonts  wherever each suggestion belong and then start looking issues in the currently working directory @/stack

## Issues and Improvements Needed

Based on my systematic review of the Laravel application codebase (focusing on the `app/` directory, which appears to be the primary development area), here are the key issues and required improvements to align with the Haasib Constitution v2.0.1:

### 1. **Database Schema & Migrations**
- **Issue**: Inconsistent user reference types (auth.users uses UUID `id`, but some tables reference auth.user_accounts `user_id` as integer). RLS policies use inconsistent setting names (`app.current_company` vs `app.current_company_id`).
- **Improvement**: Standardize user references and RLS setting names across all migrations. Ensure all tenant tables have RLS enabled with consistent policies.

### 2. **Eloquent Models**
- **Issue**: Models contain excessive business logic (e.g., Invoice model has 793 lines with complex calculations, state management). No global scopes for automatic tenancy filtering. Models don't extend BaseModel, so schema prefixing isn't automatic.
- **Improvement**: Refactor business logic into dedicated services/actions. Implement global scopes for tenancy. Make all models extend BaseModel for consistent schema handling.

### 3. **Controllers & Command Bus**
- **Issue**: Inconsistent use of command bus - some controllers use actions directly, others use services. Not all endpoints have permission guards (e.g., InvoiceController index() lacks authorization). Controllers are bloated with business logic.
- **Improvement**: Enforce command-bus supremacy for all write operations. Add permission guards to all endpoints. Thin out controllers by delegating to services/actions.

### 4. **Tenancy & RLS Safety**
- **Issue**: RLS enabled on only some tables (invoices, payments, ledger), not all tenant tables (e.g., customers lack RLS). Inconsistent RLS setting names break functionality. Manual tenancy filtering in controllers bypasses RLS.
- **Improvement**: Enable RLS on all tenant tables with consistent `app.current_company_id` setting. Remove manual filtering and rely on RLS for security.

### 5. **RBAC Integrity**
- **Issue**: Permissions comprehensively seeded, but no automated tests for allow/deny behavior. Some controllers skip authorization checks.
- **Improvement**: Add comprehensive permission tests. Ensure 100% endpoint coverage with authorization.

### 6. **Translation & Accessibility**
- **Issue**: No backend locale files. Frontend has en-US/fr-FR but missing AR baseline. Hard-coded strings throughout Vue components. No accessibility features (ARIA labels, RTL support) verified.
- **Improvement**: Create complete locale files (EN + AR). Implement translation system in Vue. Add accessibility features and RTL rendering.

### 7. **UI Compliance**
- **Issue**: FontAwesome v7 used instead of specified v5. No verification of theme synchronization or component library purity.
- **Improvement**: Downgrade to FontAwesome 5. Ensure strict PrimeVue v4 + Tailwind only, no mixing libraries.

### 8. **Testing & TDD**
- **Issue**: Extremely low test coverage (only 1 test file with actual tests out of 45 files). No TDD adherence - features implemented without tests first.
- **Improvement**: Implement comprehensive test suite (unit, feature, CLI, RLS). Enforce TDD with failing tests written first. Target 80%+ coverage.

### 9. **Audit Logging & Idempotency**
- **Issue**: AuditLogging trait exists but unused in models. Idempotency keys only on some tables (invoices, customers). Financial mutations not fully auditable.
- **Improvement**: Implement audit logging on all write operations. Add idempotency keys to all mutation endpoints. Ensure all financial operations are traceable.

### 10. **Module Governance**
- **Issue**: No module structure in `app/` - everything in flat Laravel structure. No use of `php artisan module:make`.
- **Improvement**: Restructure into modules under `modules/<Name>` using proper scaffolding. Note: `stack/` directory shows correct module structure - migrate to this pattern.

### 11. **Architecture Standards**
- **Issue**: Single Responsibility Principle violations (god classes). No ServiceContext pattern enforcement. Raw queries used in some places.
- **Improvement**: Enforce small, focused classes. Implement ServiceContext for user/tenancy context. Favor Eloquent over raw queries.

### 12. **Documentation & Workflow**
- **Issue**: No verification of briefs/trackers updates. No phase gates enforcement (spec → plan → task → implement → validate).
- **Improvement**: Implement documentation synchronization checks. Enforce development workflow with proper gates.

### Priority Recommendations:
1. **Security Critical**: Fix RLS inconsistencies and add missing permission guards
2. **Architecture**: Refactor models/controllers, implement proper module structure
3. **Quality**: Add comprehensive test suite and enforce TDD
4. **Compliance**: Implement translations, audit logging, and accessibility features

The codebase shows good foundational work on multi-tenancy and RBAC seeding, but requires significant refactoring to fully comply with constitutional principles. The `stack/` directory appears to represent a more advanced implementation with proper module structure that should be adopted.
