# Invoice Management - Complete Lifecycle Implementation Plan

**Feature Branch**: `003-invoice-management-complete`
**Created**: 2025-10-12
**Status**: Planning Complete
**Input**: Complete invoice lifecycle with CLI-GUI parity, draft creation, posting to ledger, payment allocation, and status tracking

## Constitution Check

### I. Single Source Doctrine ✅ COMPLIANT
- All implementation follows canonical documentation in `/docs/` directory
- Uses established patterns from existing invoice models and services
- No contradictions with handbook or playbook guidelines

### II. Command-Bus Supremacy ✅ COMPLIANT
- All write operations dispatch registered command actions via `config/command-bus.php`
- Existing invoice actions: `invoice.create`, `invoice.update`, `invoice.delete`, `invoice.post`, `invoice.cancel`
- New CLI commands will use command-bus actions for consistency

### III. CLI–GUI Parity ❌ VIOLATION - REQUIRES IMPLEMENTATION
- **Current State**: GUI fully implemented in `InvoiceController` with comprehensive CRUD operations
- **Gap**: No CLI commands exist for invoice operations
- **Required**: Implement CLI commands equivalent to all GUI functions (FR-009)
- **Action**: Create Artisan commands using natural language interaction

### IV. Tenancy & RLS Safety ✅ COMPLIANT
- All invoice records carry `company_id` with proper RLS policies
- Queries include tenant scoping via `ServiceContext`
- No direct database access bypassing safety checks

### V. RBAC Integrity ✅ COMPLIANT
- Uses Spatie Laravel Permission system with `HasRoles` trait
- Permissions declared: `invoices.view`, `invoices.create`, `invoices.update`, `invoices.delete`, etc.
- All endpoints have permission guards via middleware

### VI. Translation & Accessibility ✅ COMPLIANT
- User-facing strings use locale files (EN baseline)
- UI built with PrimeVue v4 components and FontAwesome 5
- RTL support maintained where applicable

### VII. PrimeVue v4 & FontAwesome 5 Compliance ✅ COMPLIANT
- Frontend uses PrimeVue v4 components with synchronized themes
- No mixing of component libraries
- Custom styling via Tailwind utilities only

### VIII. Module Governance ✅ COMPLIANT
- Invoice functionality properly structured under `app/` namespace
- No ad-hoc directories; follows Laravel conventions
- Independently testable components

### IX. Tests Before Triumph ✅ COMPLIANT
- Existing test coverage for invoice operations
- TDD approach: failing tests written first
- Unit, feature, and integration tests present

### X. Audit, Idempotency & Observability ✅ COMPLIANT
- All write actions logged via `AuditLogging` trait
- Idempotency keys enforced on all operations
- Financial mutations fully auditable

## Gate Evaluation

### Content Compliance ✅ PASS
- No implementation details in spec (focused on user needs)
- Business stakeholder oriented
- All mandatory sections completed

### Requirement Completeness ✅ PASS
- No [NEEDS CLARIFICATION] markers
- Requirements testable and unambiguous
- Success criteria measurable

### Technical Feasibility ✅ PASS
- All constitutional principles can be satisfied
- Existing architecture supports required features
- No conflicting requirements

### Scope Validation ✅ PASS
- Core invoice lifecycle fully implemented
- Missing features identified and scoped:
  - CLI commands (FR-009)
  - Invoice templates (FR-011)
  - Credit notes (FR-014)

## Technical Design

### Data Models

#### Existing Models (acct schema)
- **Invoice**: Core invoice entity with lifecycle states
- **InvoiceItem**: Line items with quantity, price, taxes
- **InvoiceItemTax**: Tax calculations per line item
- **PaymentAllocation**: Payment-to-invoice mappings
- **JournalEntry**: Ledger integration for posted invoices

#### New Models Required
- **InvoiceTemplate**: Recurring billing templates (FR-011)
- **CreditNote**: Invoice adjustments and corrections (FR-014)

### Service Contracts

#### InvoiceService (existing)
```php
interface InvoiceServiceContract {
    public function createInvoice(Company $company, Customer $customer, array $items, ServiceContext $context): Invoice;
    public function updateInvoice(Invoice $invoice, array $data, ServiceContext $context): Invoice;
    public function markAsSent(Invoice $invoice, ServiceContext $context): Invoice;
    public function markAsPosted(Invoice $invoice, ServiceContext $context): Invoice;
    public function markAsCancelled(Invoice $invoice, string $reason, ServiceContext $context): Invoice;
    public function duplicateInvoice(Invoice $invoice, ServiceContext $context): Invoice;
    public function generatePDF(Invoice $invoice, ServiceContext $context): string;
}
```

#### New Services Required
- **InvoiceTemplateService**: Template management
- **CreditNoteService**: Adjustment processing
- **InvoiceCliService**: CLI command orchestration

### Command-Bus Actions

#### Existing Actions
- `App\Actions\DevOps\InvoiceCreate`
- `App\Actions\DevOps\InvoiceUpdate`
- `App\Actions\DevOps\InvoiceDelete`
- `App\Actions\DevOps\InvoicePost`
- `App\Actions\DevOps\InvoiceCancel`

#### New Actions Required
- `App\Actions\Invoicing\InvoiceSend` (user-facing send)
- `App\Actions\Invoicing\InvoiceDuplicate`
- `App\Actions\Invoicing\CreditNoteCreate`
- `App\Actions\Invoicing\InvoiceTemplateCreate`

## Implementation Approach

### Phase 1: CLI Parity Implementation
**Goal**: Achieve CLI-GUI parity as required by constitution

#### CLI Commands to Implement
1. `php artisan invoice:create` - Interactive invoice creation
2. `php artisan invoice:update {id}` - Update existing invoice
3. `php artisan invoice:send {id}` - Mark as sent
4. `php artisan invoice:post {id}` - Post to ledger
5. `php artisan invoice:cancel {id}` - Cancel invoice
6. `php artisan invoice:list` - List invoices with filtering
7. `php artisan invoice:show {id}` - Display invoice details
8. `php artisan invoice:duplicate {id}` - Create duplicate
9. `php artisan invoice:pdf {id}` - Generate PDF

#### Implementation Strategy
- Use Laravel's `Command` class with natural language interaction
- Leverage existing `InvoiceService` methods
- Support JSON output for automation
- Include progress indicators and error handling

### Phase 2: Invoice Templates (FR-011)
**Goal**: Support recurring billing templates

#### Features
- Template creation from existing invoices
- Template application to new invoices
- Template management (CRUD operations)
- CLI support for template operations

#### Database Schema
```sql
CREATE TABLE acct.invoice_templates (
    template_id UUID PRIMARY KEY,
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    customer_id UUID REFERENCES acct.customers(customer_id),
    currency_id UUID REFERENCES public.currencies(id),
    items JSONB NOT NULL, -- Template line items
    settings JSONB, -- Template-specific settings
    is_active BOOLEAN DEFAULT true,
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Phase 3: Credit Notes (FR-014)
**Goal**: Support invoice adjustments and corrections

#### Features
- Credit note creation against invoices
- Automatic balance adjustments
- Ledger integration for credit notes
- PDF generation and email support

#### Database Schema
```sql
CREATE TABLE acct.credit_notes (
    credit_note_id UUID PRIMARY KEY,
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    invoice_id UUID NOT NULL REFERENCES acct.invoices(invoice_id),
    credit_note_number VARCHAR(50) NOT NULL,
    reason TEXT,
    amount DECIMAL(15,2) NOT NULL,
    currency_id UUID REFERENCES public.currencies(id),
    status VARCHAR(20) DEFAULT 'draft',
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    posted_at TIMESTAMP,
    cancelled_at TIMESTAMP
);
```

### Phase 4: Enhanced Payment Allocation
**Goal**: Support partial payments across multiple invoices

#### Features
- Multi-invoice payment allocation
- Automatic allocation strategies (FIFO, manual)
- Payment allocation reversal
- Balance tracking improvements

## Module Structure

```
app/
├── Actions/
│   ├── Invoicing/
│   │   ├── InvoiceCreate.php
│   │   ├── InvoiceSend.php
│   │   ├── InvoiceDuplicate.php
│   │   ├── CreditNoteCreate.php
│   │   └── InvoiceTemplateCreate.php
├── Console/
│   └── Commands/
│       ├── InvoiceCreate.php
│       ├── InvoiceUpdate.php
│       ├── InvoiceSend.php
│       ├── InvoicePost.php
│       ├── InvoiceCancel.php
│       ├── InvoiceList.php
│       ├── InvoiceShow.php
│       ├── InvoiceDuplicate.php
│       └── InvoicePdf.php
├── Models/
│   ├── InvoiceTemplate.php
│   └── CreditNote.php
├── Services/
│   ├── InvoiceTemplateService.php
│   ├── CreditNoteService.php
│   └── InvoiceCliService.php
└── Policies/
    ├── InvoiceTemplatePolicy.php
    └── CreditNotePolicy.php
```

## Testing Strategy

### Unit Tests
- **Invoice Model**: Status transitions, calculations, validation
- **Services**: Business logic, error handling
- **Actions**: Command-bus integration, authorization

### Feature Tests
- **GUI Operations**: Full CRUD via web interface
- **CLI Operations**: Command execution and output validation
- **API Integration**: REST endpoints with authentication

### Integration Tests
- **Ledger Posting**: Journal entry creation and validation
- **Payment Allocation**: Multi-invoice payment distribution
- **Email/PDF Generation**: File creation and delivery

### End-to-End Tests
- **Invoice Lifecycle**: Draft → Sent → Posted → Paid
- **Bulk Operations**: Multi-invoice status updates
- **Error Scenarios**: Invalid transitions, permission failures

### CLI Testing
- **Command Parsing**: Natural language input handling
- **Output Formats**: JSON and human-readable output
- **Interactive Mode**: User prompts and validation

## Validation Plan

### Phase 1 Validation (CLI Parity)
- [ ] All GUI operations have CLI equivalents
- [ ] CLI commands support natural language interaction
- [ ] JSON output for automation scripts
- [ ] Error handling matches GUI behavior

### Phase 2 Validation (Templates)
- [ ] Template creation from existing invoices
- [ ] Template application to new invoices
- [ ] Template CRUD operations functional
- [ ] CLI template management working

### Phase 3 Validation (Credit Notes)
- [ ] Credit note creation against invoices
- [ ] Balance adjustments applied correctly
- [ ] Ledger entries posted for credit notes
- [ ] PDF generation and email support

### Phase 4 Validation (Enhanced Allocation)
- [ ] Partial payments across multiple invoices
- [ ] Automatic allocation strategies
- [ ] Allocation reversal functionality
- [ ] Balance tracking accuracy

### Constitution Compliance Validation
- [ ] All 10 constitutional principles verified
- [ ] CLI-GUI parity achieved
- [ ] Command-bus supremacy maintained
- [ ] RBAC and tenancy safety confirmed

## Phase Structure

### Planning Phase ✅ COMPLETE
- Constitution check completed
- Technical design finalized
- Implementation roadmap defined
- Testing strategy established

### Task Execution Phase (Next)
- Create detailed task breakdown
- Implement CLI commands
- Add invoice templates
- Implement credit notes
- Enhance payment allocation

### Implementation Phase
- TDD cycle for each feature
- Code reviews and testing
- Documentation updates
- Performance optimization

### Validation Phase
- Automated test execution
- Manual QA validation
- Performance benchmarking
- Security review completion

## Risk Assessment

### Technical Risks
- **CLI Complexity**: Natural language parsing may require significant effort
- **Schema Changes**: New tables require careful migration planning
- **Integration Points**: Ledger and payment system integration testing

### Business Risks
- **Scope Creep**: Additional features may extend timeline
- **User Adoption**: CLI tools may have limited initial usage
- **Training Requirements**: Staff training for new features

### Mitigation Strategies
- **Incremental Delivery**: Phase-wise implementation with validation gates
- **Backward Compatibility**: All changes maintain existing functionality
- **Documentation**: Comprehensive docs for new features
- **Testing Coverage**: 80%+ test coverage maintained

## Success Criteria

### Functional Completeness
- [ ] All FR-001 through FR-014 implemented
- [ ] CLI-GUI parity achieved
- [ ] Invoice lifecycle fully functional
- [ ] Payment allocation working across invoices

### Quality Assurance
- [ ] All tests passing (unit, feature, integration, E2E)
- [ ] Code coverage > 80%
- [ ] Performance benchmarks met (<200ms p95)
- [ ] Security review completed

### Constitutional Compliance
- [ ] All 10 principles satisfied
- [ ] No violations identified
- [ ] Documentation updated
- [ ] Audit trails complete

### User Acceptance
- [ ] GUI operations unchanged
- [ ] CLI commands intuitive
- [ ] Error messages clear
- [ ] Performance acceptable

---

**Ready for Task Generation**: This plan provides complete technical specifications and implementation roadmap for the invoice management feature, ensuring constitutional compliance and successful delivery.
