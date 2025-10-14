
# Implementation Plan: Reporting Dashboard - Financial & KPI

**Branch**: `010-reporting-dashboard-financial` | **Date**: 2025-01-16 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/010-reporting-dashboard-financial/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → If not found: ERROR "No feature spec at {path}"
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → Detect Project Type from file system structure or context (web=frontend+backend, mobile=app+api)
   → Set Structure Decision based on project type
3. Fill the Constitution Check section based on the content of the constitution document.
4. Evaluate Constitution Check section below
   → If violations exist: Document in Complexity Tracking
   → If no justification possible: ERROR "Simplify approach first"
   → Update Progress Tracking: Initial Constitution Check
5. Execute Phase 0 → research.md
   → If NEEDS CLARIFICATION remain: ERROR "Resolve unknowns"
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file (e.g., `CLAUDE.md` for Claude Code, `.github/copilot-instructions.md` for GitHub Copilot, `GEMINI.md` for Gemini CLI, `QWEN.md` for Qwen Code, or `AGENTS.md` for all other agents).
7. Re-evaluate Constitution Check section
   → If new violations: Refactor design, return to Phase 1
   → Update Progress Tracking: Post-Design Constitution Check
8. Plan Phase 2 → Describe task generation approach (DO NOT create tasks.md)
9. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 7. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary
The Reporting Dashboard feature provides real-time financial statements, KPIs, and management dashboards with multi-currency support. The technical approach uses a multi-layer caching strategy with Redis, WebSocket/polling hybrid for real-time updates, and PostgreSQL with RLS for tenant isolation. The implementation follows Laravel's modular architecture under app/Modules/Reporting/ with full CLI-GUI parity as required by the constitution.

## Technical Context
**Language/Version**: PHP 8.2+, Laravel 12
**Primary Dependencies**: Vue 3, Inertia.js v2, PrimeVue v4, PostgreSQL 16
**Storage**: PostgreSQL 16 with RLS
**Testing**: Pest v4 for backend, Playwright for E2E
**Target Platform**: Linux server (web application)
**Project Type**: web (frontend + backend)
**Performance Goals**: <200ms p95 response times, <5 second dashboard refresh
**Constraints**: Real-time data freshness, unlimited historical data retention, 10-second report generation limit
**Scale/Scope**: Multi-company accounting platform for SMEs

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Mandatory Requirements (from Constitution v2.0.0)
- [ ] **Single Source Doctrine**: All implementation must follow canonical docs in `/docs/`
- [ ] **Command-Bus Supremacy**: All report generation actions must dispatch through command bus
- [ ] **CLI-GUI Parity**: Dashboard features must have equivalent CLI commands
- [ ] **Tenancy & RLS Safety**: All report queries must include company_id scoping
- [ ] **RBAC Integrity**: Report access must respect Owner/Accountant/Viewer roles
- [ ] **Translation & Accessibility**: All UI strings must use locale files (EN + AR)
- [ ] **PrimeVue v4 Compliance**: Must use PrimeVue components for charts and tables
- [ ] **Module Governance**: Must be implemented as independent module under modules/
- [ ] **Tests Before Triumph**: TDD required - tests must be written first
- [ ] **Audit & Idempotency**: Report generation must be auditable with idempotency keys

### Initial Assessment
- ✓ Project structure follows Laravel standards
- ✓ Technology stack matches constitutional requirements
- ⚠️ Need to ensure module compliance for Reporting module
- ⚠️ Need to verify CLI command parity for all dashboard features

## Project Structure

### Documentation (this feature)
```
specs/[###-feature]/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
backend/
├── app/
│   ├── Modules/Reporting/
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Livewire/
│   │   ├── CLI/
│   │   └── resources/
│   │       ├── lang/
│   │       └── views/
│   └── Config/
├── database/
│   ├── migrations/
│   └── seeders/
└── tests/
    ├── Feature/Reporting/
    ├── Unit/Reporting/
    └── CLI/

frontend/
├── resources/
│   ├── js/
│   │   ├── Pages/Reporting/
│   │   ├── Components/Reporting/
│   │   └── Layouts/
│   └── css/
└── tests/
```

**Structure Decision**: Web application with Laravel backend (using modular architecture) and Vue 3 frontend. Reporting module will be self-contained under app/Modules/Reporting/ with CLI actions in CLI/ subdirectory.

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context** above:
   - For each NEEDS CLARIFICATION → research task
   - For each dependency → best practices task
   - For each integration → patterns task

2. **Generate and dispatch research agents**:
   ```
   For each unknown in Technical Context:
     Task: "Research {unknown} for {feature context}"
   For each technology choice:
     Task: "Find best practices for {tech} in {domain}"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all NEEDS CLARIFICATION resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - Entity name, fields, relationships
   - Validation rules from requirements
   - State transitions if applicable

2. **Generate API contracts** from functional requirements:
   - For each user action → endpoint
   - Use standard REST/GraphQL patterns
   - Output OpenAPI/GraphQL schema to `/contracts/`

3. **Generate contract tests** from contracts:
   - One test file per endpoint
   - Assert request/response schemas
   - Tests must fail (no implementation yet)

4. **Extract test scenarios** from user stories:
   - Each story → integration test scenario
   - Quickstart test = story validation steps

5. **Update agent file incrementally** (O(1) operation):
   - Run `.specify/scripts/bash/update-agent-context.sh claude`
     **IMPORTANT**: Execute it exactly as specified above. Do not add or remove any arguments.
   - If exists: Add only NEW tech from current plan
   - Preserve manual additions between markers
   - Update recent changes (keep last 3)
   - Keep under 150 lines for token efficiency
   - Output to repository root

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, agent-specific file

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `.specify/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs (contracts, data model, quickstart)
- Each contract → contract test task [P]
- Each entity → model creation task [P] 
- Each user story → integration test task
- Implementation tasks to make tests pass

**Ordering Strategy**:
- TDD order: Tests before implementation 
- Dependency order: Models before services before UI
- Mark [P] for parallel execution (independent files)

**Estimated Output**: 25-30 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Implementation Roadmap
*This section documents the recommended development order for implementation*

### Development Strategy

The implementation follows an incremental approach delivering value every week:

#### Week 1: Foundation
- Module setup and database migrations
- Core models (Report, ExchangeRate, DashboardKPI)
- Basic permissions and middleware
- Empty dashboard route and view

#### Week 2: Data Processing
- Trial balance service (foundation for all reports)
- Currency conversion service
- Caching layer implementation
- Basic report generation framework

#### Week 3: Core Reports (MVP)
- Income Statement generation
- Balance Sheet generation
- PDF export functionality
- CLI commands for core reports
- **MVP Delivered**

#### Week 4: Dashboard Interface
- KPI calculation service
- Real-time updates (WebSocket/polling)
- Vue components for charts and tables
- Empty state with onboarding guidance
- Sample data mode

#### Week 5: Advanced Features
- Cash Flow Statement
- Aging Reports (Customer/Vendor)
- Report templates
- Scheduled reports

#### Week 6: Polish & Optimization
- Performance optimization
- Bulk operations
- Email delivery
- **Feature Complete**

### Parallel Development Streams
```
Backend (Server-side):
├── Report generation services
├── Command bus actions
└── Database queries/optimization

CLI Commands:
├── Natural language parsing
├── Output formatting
└── Progress indicators

Frontend (Client-side):
├── Vue components
├── Chart.js integration
└── Real-time updates

Testing:
├── Unit tests for services
├── Feature tests for reports
├── CLI tests for commands
└── RLS tests for tenancy
```

### Critical Path Dependencies
1. Trial Balance Service → All Financial Reports
2. Report Generation → Dashboard Display
3. Caching Layer → Performance Requirements
4. Permissions/RBAC → Security Requirements

### MVP Definition (End of Week 3)
- Trial balance generation
- Income statement and balance sheet
- PDF export
- CLI commands for all features
- Empty state dashboard

### Empty State Design
When no data exists, dashboard shows:
- Welcome message with value proposition
- Quick action buttons (Create Invoice, Import Data)
- Onboarding checklist
- Option to toggle sample data mode

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |


## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented

---
*Based on Constitution v2.1.1 - See `/memory/constitution.md`*
