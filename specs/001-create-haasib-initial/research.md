# Research Findings: Create Haasib - Initial Platform Setup

## Module Creation Strategy

**Decision**: Use in-repo module scaffolder (`php artisan module:make`) with reusable legacy assets
- **Rationale**: Constitutional requirement for module governance and Salvage clause direction to reuse the existing CLI/core foundations
- **Implementation**:
  - Scaffold modules under `modules/<Name>` via `module:make`
  - Import vetted code from `main` (CLI architecture) and `rebootstrap-primevue` (core/invoicing logic) rather than rewriting
  - Single `Accounting` module: organizes domains under `modules/Accounting/` with subdirectories (`Domain/Ledger`, `Domain/Invoicing`, `Domain/Payments`)
  - Maintain CLI registry, command bus actions, and migrations within the consolidated module
- **Alternatives considered**:
  - Installing external module package (rejected: conflicts with our custom scaffolder and autoloading)
  - Rewriting modules from scratch (rejected: violates Salvage clause and risks regression)

## Demo Data Seeding Approach

**Decision**: Reuse legacy migrations/seeders and extend with factory-driven demo data
- **Rationale**: Salvage clause requires leveraging vetted migrations; need 3 months of progressive data showing business growth
- **Implementation**:
  - Copy and adapt migrations from legacy app (`app/database/migrations`) guided by `/docs/schemas/*.sql`
  - Retain Seeder logic from `rebootstrap-primevue` (e.g., role seeding, invoicing fixtures) and extend with industry-specific factories
  - Wrap seeding in transactions; fail fast on partial errors (strategy A)
  - Add progress logging + audit entries for seed operations
- **Alternatives considered**:
  - Fixed SQL dumps (rejected: hard to maintain)
  - Manual data entry (rejected: not scalable)

## User Role and Permission System

**Decision**: Use Spatie Laravel Permission package
- **Rationale**: Constitutional requirement for RBAC integrity
- **Implementation**:
  - Define roles: System Owner, Company Owner, Accountant, Member 1, Member 2
  - Create granular permissions per module capability
  - Seed default role-permission assignments
  - Company-scoped permissions using middleware
- **Alternatives considered**:
  - Custom RBAC implementation (rejected: reinventing the wheel)
  - Gate-based only (rejected: lacks persistence and UI)

## Command Palette Implementation

**Decision**: Reuse legacy command palette + enhance via PrimeVue autocomplete
- **Rationale**: Constitutional requirement for CLI-GUI parity
- **Implementation**:
  - Command registry service maps commands to actions
  - Natural language parsing for simple commands
  - Keyboard shortcut (Cmd/Ctrl+K) for activation
  - CLI commands mirror GUI actions through command bus
- **Alternatives considered**:
  - Native select dropdown (rejected: poor UX for many commands)
  - Custom search library (rejected: PrimeVue autocomplete is sufficient)

## Company Context Switching

**Decision**: Session-based context with middleware
- **Rationale**: Constitutional requirement for tenancy & RLS safety
- **Implementation**:
  - Session variable for active company
  - Middleware sets PostgreSQL GUC for RLS
  - UI dropdown for switching (with save prompt)
  - CLI flag --company for command-line context
  - Reuse tenant middleware from legacy branches (SetTenantContext)
- **Alternatives considered**:
  - Subdomain per company (rejected: overkill for demo)
  - Separate databases (rejected: violates single DB architecture)

## Industry-Specific Data Templates

**Decision**: Create specialized factory patterns per industry
- **Rationale**: Need realistic demo data per industry type
- **Implementation**:
  - Hospitality: Room bookings, seasonal patterns, restaurant POS
  - Retail: Product inventory, sales transactions, returns
  - Professional Services: Hourly billing, project tracking retainers
- **Alternatives considered**:
  - Generic data for all (rejected: doesn't showcase capabilities)
  - Manual data creation (rejected: not scalable)

## Audit Trail Strategy

**Decision**: Use Laravel's auditing with idempotency keys
- **Rationale**: Constitutional requirement for audit & idempotency
- **Implementation**:
  - Append-only audit table for all mutations
  - UUID idempotency keys for all write operations
  - Automated logging via observers
  - Per-company audit views
- **Alternatives considered**:
  - Event sourcing (rejected: complex for initial setup)
  - Simple logging (rejected: lacks audit trail integrity)
