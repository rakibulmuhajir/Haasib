# Invoice Management - Complete Lifecycle Implementation Tasks

**Feature Branch**: `003-invoice-management-complete`
**Created**: 2025-10-12
**Status**: Tasks Generated
**Based on**: Comprehensive plan.md with constitutional requirements

## Phase 1: CLI Parity Implementation (Priority: High)

### Task 1.1: Create CLI Command Structure and Base Classes
**Description**: Establish the foundation for all invoice CLI commands with consistent patterns and error handling.
**Dependencies**: None
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceBaseCommand.php` - Base class with common functionality
- `app/app/Console/Commands/Concerns/HandlesInvoiceOperations.php` - Shared trait for invoice operations
- `app/app/Console/Commands/Concerns/ProvidesNaturalLanguageInteraction.php` - Trait for NLP-style input
- Standardized error handling and output formatting
- JSON output support for automation

**Implementation Details**:
```php
// Base command structure
abstract class InvoiceBaseCommand extends Command
{
    protected function formatOutput($data, $format = 'table')
    protected function validateCompanyAccess($companyId)
    protected function handleServiceException($exception)
    protected function parseNaturalLanguageInput($input)
}
```

### Task 1.2: Implement invoice:create Command
**Description**: Interactive invoice creation with natural language input and validation.
**Dependencies**: Task 1.1
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceCreate.php` - Main command implementation
- Natural language parsing for invoice data
- Interactive prompts for missing information
- JSON output mode for automation
- Comprehensive validation and error handling

**Command Signature**:
```bash
php artisan invoice:create
    [--customer=]
    [--items=]
    [--currency=]
    [--date=]
    [--due-date=]
    [--notes=]
    [--terms=]
    [--json]
    [--interactive]
```

**Natural Language Examples**:
- "Create invoice for Acme Corp for web design services $5000 due in 30 days"
- "New invoice for customer ABC-123 with 2 items: consulting $200/hour for 10 hours, hosting $50/month"

### Task 1.3: Implement invoice:update Command
**Description**: Update existing invoices with validation and change tracking.
**Dependencies**: Task 1.2
**Estimated Time**: 6 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceUpdate.php` - Update command implementation
- Field-specific update options
- Validation for editable fields only
- Change summary output
- Audit trail integration

**Command Signature**:
```bash
php artisan invoice:update {id}
    [--customer=]
    [--items=]
    [--date=]
    [--due-date=]
    [--notes=]
    [--terms=]
    [--json]
```

### Task 1.4: Implement invoice:send Command
**Description**: Mark invoices as sent with optional email functionality.
**Dependencies**: Task 1.3
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceSend.php` - Send command implementation
- Status transition validation
- Email sending integration
- PDF generation on send
- Batch sending support

**Command Signature**:
```bash
php artisan invoice:send {id} [--email=] [--subject=] [--message=] [--generate-pdf]
```

### Task 1.5: Implement invoice:post Command
**Description**: Post invoices to ledger with journal entry creation.
**Dependencies**: Task 1.4
**Estimated Time**: 6 hours
**Deliverables**:
- `app/app/Console/Commands/InvoicePost.php` - Post command implementation
- Ledger integration via existing services
- Journal entry creation
- Posting validation
- Batch posting support

**Command Signature**:
```bash
php artisan invoice:post {id} [--force] [--batch=]
```

### Task 1.6: Implement invoice:cancel Command
**Description**: Cancel invoices with reason tracking and audit trail.
**Dependencies**: Task 1.5
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceCancel.php` - Cancel command implementation
- Required reason input
- Cancellation validation
- Audit trail integration
- Status reversal support

**Command Signature**:
```bash
php artisan invoice:cancel {id} --reason= [--batch=]
```

### Task 1.7: Implement invoice:list Command
**Description**: List invoices with comprehensive filtering and output options.
**Dependencies**: Task 1.6
**Estimated Time**: 6 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceList.php` - List command implementation
- Multiple filtering options
- Sorting capabilities
- Table and JSON output formats
- Export functionality

**Command Signature**:
```bash
php artisan invoice:list
    [--status=]
    [--customer=]
    [--date-from=]
    [--date-to=]
    [--amount-from=]
    [--amount-to=]
    [--sort=]
    [--limit=]
    [--json]
    [--export=]
```

### Task 1.8: Implement invoice:show Command
**Description**: Display detailed invoice information with multiple view formats.
**Dependencies**: Task 1.7
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceShow.php` - Show command implementation
- Detailed invoice display
- Payment history
- Status transition history
- JSON output for API consumption

**Command Signature**:
```bash
php artisan invoice:show {id} [--format=] [--with-payments] [--with-history]
```

### Task 1.9: Implement invoice:duplicate Command
**Description**: Create duplicate invoices with option to modify details.
**Dependencies**: Task 1.8
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceDuplicate.php` - Duplicate command implementation
- Item copying with tax preservation
- Date adjustment options
- Customer change support
- Number generation

**Command Signature**:
```bash
php artisan invoice:duplicate {id} [--customer=] [--date=] [--due-date=] [--notes=]
```

### Task 1.10: Implement invoice:pdf Command
**Description**: Generate PDF invoices with storage and download options.
**Dependencies**: Task 1.9
**Estimated Time**: 4 hours
**Deliverables**:
- `app/app/Console/Commands/InvoicePdf.php` - PDF command implementation
- PDF generation using existing templates
- Storage location options
- Email delivery option
- Batch PDF generation

**Command Signature**:
```bash
php artisan invoice:pdf {id} [--path=] [--email=] [--template=] [--batch=]
```

### Task 1.11: Implement InvoiceCliService
**Description**: Central service for CLI command orchestration and shared functionality.
**Dependencies**: Task 1.10
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Services/InvoiceCliService.php` - CLI service implementation
- Command orchestration methods
- Natural language processing
- Output formatting utilities
- Error handling standardization

**Service Methods**:
```php
public function parseInvoiceFromText(string $text): array
public function formatInvoiceForCli(Invoice $invoice, string $format): string
public function validateInvoiceForOperation(Invoice $invoice, string $operation): bool
public function executeBulkOperation(array $invoiceIds, string $operation, array $options): array
```

### Task 1.12: Add Comprehensive CLI Testing with TDD Approach
**Description**: Create thorough test suite for all CLI commands with test-first approach.
**Dependencies**: Task 1.11
**Estimated Time**: 12 hours
**Deliverables**:
- `tests/Feature/Console/InvoiceCommandsTest.php` - Main test file
- Individual test files for each command
- Mock services for isolated testing
- Natural language input testing
- Output format validation
- Error scenario testing
- Performance benchmarks

**Test Structure**:
```php
class InvoiceCreateCommandTest extends TestCase
{
    public function test_it_creates_invoice_with_interactive_prompts()
    public function test_it_creates_invoice_with_natural_language_input()
    public function test_it_validates_required_fields()
    public function test_it_handles_duplicate_invoice_numbers()
    public function test_it_outputs_json_format_correctly()
    // ... more tests
}
```

### Task 1.13: Validate CLI-GUI Parity Across All Operations
**Description**: Ensure CLI commands provide equivalent functionality to GUI operations.
**Dependencies**: Task 1.12
**Estimated Time**: 6 hours
**Deliverables**:
- Parity validation matrix
- Feature comparison documentation
- Missing functionality identification
- Consistency verification
- Performance comparison

**Validation Criteria**:
- All GUI operations have CLI equivalents
- Same validation rules apply
- Identical error messages
- Consistent state transitions
- Equal audit trail quality

## Phase 2: Invoice Templates (FR-011) (Priority: Medium)

### Task 2.1: Create InvoiceTemplate Model
**Description**: Define the InvoiceTemplate model with proper relationships and business logic.
**Dependencies**: None
**Estimated Time**: 6 hours
**Deliverables**:
- `app/Models/InvoiceTemplate.php` - Model implementation
- Relationship definitions (Company, Customer, Currency)
- Business logic methods
- Validation rules
- Scopes for common queries

**Model Features**:
```php
class InvoiceTemplate extends Model
{
    public function company(): BelongsTo
    public function customer(): BelongsTo
    public function currency(): BelongsTo
    public function scopeActive($query)
    public function scopeForCompany($query, $companyId)
    public function applyToInvoice(Invoice $invoice): Invoice
    public function validateTemplate(): bool
}
```

### Task 2.2: Create Database Migration for Invoice Templates
**Description**: Design and implement the database schema for invoice templates.
**Dependencies**: Task 2.1
**Estimated Time**: 4 hours
**Deliverables**:
- `database/migrations/YYYY_MM_DD_HHMMSS_create_invoice_templates_table.php` - Migration file
- Proper foreign key constraints
- Indexes for performance
- JSON structure for template items
- Schema documentation

**Schema Structure**:
```sql
CREATE TABLE acct.invoice_templates (
    template_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    customer_id UUID REFERENCES acct.customers(customer_id),
    currency_id UUID REFERENCES public.currencies(id),
    items JSONB NOT NULL,
    settings JSONB,
    is_active BOOLEAN DEFAULT true,
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Task 2.3: Implement InvoiceTemplateService
**Description**: Create service layer for template management with business logic.
**Dependencies**: Task 2.2
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Services/InvoiceTemplateService.php` - Service implementation
- CRUD operations
- Template application logic
- Validation methods
- Integration with InvoiceService

**Service Methods**:
```php
public function createTemplate(Company $company, array $data, ServiceContext $context): InvoiceTemplate
public function updateTemplate(InvoiceTemplate $template, array $data, ServiceContext $context): InvoiceTemplate
public function deleteTemplate(InvoiceTemplate $template, ServiceContext $context): void
public function applyTemplate(InvoiceTemplate $template, ?Customer $customer, ServiceContext $context): Invoice
public function createTemplateFromInvoice(Invoice $invoice, string $name, ServiceContext $context): InvoiceTemplate
```

### Task 2.4: Create CLI Commands for Template Management
**Description**: Implement CLI commands for all template operations.
**Dependencies**: Task 2.3
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Console/Commands/InvoiceTemplateCreate.php`
- `app/app/Console/Commands/InvoiceTemplateList.php`
- `app/app/Console/Commands/InvoiceTemplateShow.php`
- `app/app/Console/Commands/InvoiceTemplateUpdate.php`
- `app/app/Console/Commands/InvoiceTemplateDelete.php`
- `app/app/Console/Commands/InvoiceTemplateApply.php`

**Command Examples**:
```bash
php artisan invoice:template:create --name="Web Design" --from-invoice=INV-001
php artisan invoice:template:list --company=123
php artisan invoice:template:apply --template=456 --customer=789
```

### Task 2.5: Implement Template Creation from Existing Invoices
**Description**: Add functionality to create templates from existing invoices.
**Dependencies**: Task 2.4
**Estimated Time**: 4 hours
**Deliverables**:
- Invoice to template conversion logic
- Item data transformation
- Settings extraction
- Validation of source invoice
- Duplicate template prevention

**Implementation Details**:
```php
public function createTemplateFromInvoice(
    Invoice $invoice,
    string $name,
    ?string $description = null,
    ServiceContext $context
): InvoiceTemplate
```

### Task 2.6: Implement Template Application to New Invoices
**Description**: Apply templates to create new invoices with pre-filled data.
**Dependencies**: Task 2.5
**Estimated Time**: 6 hours
**Deliverables**:
- Template application logic
- Dynamic field substitution
- Item copying with tax preservation
- Date and number generation
- Override capabilities

**Application Process**:
1. Load template structure
2. Validate template currency and customer
3. Create new invoice with template data
4. Apply dynamic substitutions
5. Allow field overrides

### Task 2.7: Add Template Validation and Settings Management
**Description**: Implement validation logic and settings for templates.
**Dependencies**: Task 2.6
**Estimated Time**: 4 hours
**Deliverables**:
- Template validation rules
- Settings structure definition
- Field validation
- Currency compatibility checks
- Customer relationship validation

**Validation Rules**:
- Required fields completeness
- Valid currency and customer
- Item structure validity
- Settings format validation
- Company ownership verification

### Task 2.8: Create InvoiceTemplatePolicy
**Description**: Define authorization policies for template operations.
**Dependencies**: Task 2.7
**Estimated Time**: 2 hours
**Deliverables**:
- `app/app/Policies/InvoiceTemplatePolicy.php` - Policy implementation
- Permission definitions
- Company scope validation
- User role checks
- Operation authorization

**Policy Methods**:
```php
public function view(User $user, InvoiceTemplate $template)
public function create(User $user, Company $company)
public function update(User $user, InvoiceTemplate $template)
public function delete(User $user, InvoiceTemplate $template)
public function apply(User $user, InvoiceTemplate $template)
```

### Task 2.9: Add Template-Related GUI Endpoints and Views
**Description**: Create web interface for template management.
**Dependencies**: Task 2.8
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Http/Controllers/Invoicing/InvoiceTemplateController.php`
- Template CRUD routes
- Vue.js components for template management
- Template selection interface
- Application workflow

**Controller Methods**:
```php
public function index(Request $request)
public function create(Request $request)
public function store(Request $request)
public function show(Request $request, $id)
public function edit(Request $request, $id)
public function update(Request $request, $id)
public function destroy(Request $request, $id)
public function apply(Request $request)
```

### Task 2.10: Write Comprehensive Tests for Template Functionality
**Description**: Create complete test suite for template operations.
**Dependencies**: Task 2.9
**Estimated Time**: 8 hours
**Deliverables**:
- `tests/Feature/InvoiceTemplateTest.php` - Feature tests
- `tests/Unit/Services/InvoiceTemplateServiceTest.php` - Service tests
- CLI command tests
- Policy tests
- Integration tests

## Phase 3: Credit Notes (FR-014) (Priority: Medium)

### Task 3.1: Create CreditNote Model
**Description**: Define the CreditNote model with proper relationships and business logic.
**Dependencies**: None
**Estimated Time**: 6 hours
**Deliverables**:
- `app/app/Models/CreditNote.php` - Model implementation
- Relationship definitions
- Business logic methods
- Status management
- Financial calculations

**Model Features**:
```php
class CreditNote extends Model
{
    public function company(): BelongsTo
    public function invoice(): BelongsTo
    public function currency(): BelongsTo
    public function items(): HasMany
    public function canBePosted(): bool
    public function canBeCancelled(): bool
    public function calculateRemainingBalance(): Money
}
```

### Task 3.2: Create Database Migration for Credit Notes
**Description**: Design and implement the database schema for credit notes.
**Dependencies**: Task 3.1
**Estimated Time**: 4 hours
**Deliverables**:
- `database/migrations/YYYY_MM_DD_HHMMSS_create_credit_notes_table.php` - Migration file
- `database/migrations/YYYY_MM_DD_HHMMSS_create_credit_note_items_table.php` - Items migration
- Foreign key constraints
- Indexes for performance
- Schema documentation

**Schema Structure**:
```sql
CREATE TABLE acct.credit_notes (
    credit_note_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
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

### Task 3.3: Implement CreditNoteService
**Description**: Create service layer for credit note management with business logic.
**Dependencies**: Task 3.2
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Services/CreditNoteService.php` - Service implementation
- CRUD operations
- Balance adjustment logic
- Ledger integration
- Validation methods

**Service Methods**:
```php
public function createCreditNote(Invoice $invoice, array $data, ServiceContext $context): CreditNote
public function postCreditNote(CreditNote $creditNote, ServiceContext $context): CreditNote
public function cancelCreditNote(CreditNote $creditNote, ?string $reason, ServiceContext $context): CreditNote
public function applyToInvoiceBalance(CreditNote $creditNote, ServiceContext $context): void
```

### Task 3.4: Create CLI Commands for Credit Note Operations
**Description**: Implement CLI commands for all credit note operations.
**Dependencies**: Task 3.3
**Estimated Time**: 8 hours
**Deliverables**:
- `app/app/Console/Commands/CreditNoteCreate.php`
- `app/app/Console/Commands/CreditNoteList.php`
- `app/app/Console/Commands/CreditNoteShow.php`
- `app/app/Console/Commands/CreditNotePost.php`
- `app/app/Console/Commands/CreditNoteCancel.php`

**Command Examples**:
```bash
php artisan creditnote:create --invoice=INV-001 --amount=500 --reason="Returned goods"
php artisan creditnote:list --status=posted
php artisan creditnote:post CN-001
```

### Task 3.5: Implement Credit Note Creation Against Invoices
**Description**: Add functionality to create credit notes linked to specific invoices.
**Dependencies**: Task 3.4
**Estimated Time**: 6 hours
**Deliverables**:
- Credit note creation logic
- Invoice validation
- Amount restrictions
- Reason tracking
- Number generation

**Creation Process**:
1. Validate invoice exists and is posted
2. Check credit limits and balance
3. Create credit note with proper references
4. Generate unique credit note number
5. Apply initial status and audit trail

### Task 3.6: Add Automatic Balance Adjustments for Credit Notes
**Description**: Implement automatic balance updates when credit notes are applied.
**Dependencies**: Task 3.5
**Estimated Time**: 6 hours
**Deliverables**:
- Balance calculation logic
- Automatic invoice updates
- Payment allocation adjustments
- Financial impact tracking
- Audit trail updates

**Adjustment Logic**:
```php
public function applyCreditNoteToInvoice(CreditNote $creditNote): void
{
    // Update invoice balance
    // Create payment allocation
    // Update accounts receivable
    // Log financial changes
}
```

### Task 3.7: Integrate Credit Notes with Ledger System
**Description**: Add ledger integration for posted credit notes.
**Dependencies**: Task 3.6
**Estimated Time**: 8 hours
**Deliverables**:
- Journal entry creation
- Ledger account mapping
- Reversal entries
- Financial reporting integration
- Audit trail completeness

**Ledger Integration**:
- Debit revenue accounts
- Credit accounts receivable
- Create reversing journal entries
- Maintain financial integrity

### Task 3.8: Implement PDF Generation for Credit Notes
**Description**: Add PDF generation functionality for credit notes.
**Dependencies**: Task 3.7
**Estimated Time**: 4 hours
**Deliverables**:
- PDF template for credit notes
- Generation service
- Storage management
- Email integration
- Batch generation

**PDF Features**:
- Professional layout
- Company branding
- Itemized details
- Terms and conditions
- Digital signature support

### Task 3.9: Add Email Support for Credit Notes
**Description**: Implement email functionality for credit note delivery.
**Dependencies**: Task 3.8
**Estimated Time**: 4 hours
**Deliverables**:
- Email templates
- Delivery service
- Tracking functionality
- Batch email support
- Delivery confirmation

**Email Features**:
- HTML and text formats
- PDF attachment
- Customizable templates
- Delivery tracking
- Bounce handling

### Task 3.10: Create CreditNotePolicy
**Description**: Define authorization policies for credit note operations.
**Dependencies**: Task 3.9
**Estimated Time**: 2 hours
**Deliverables**:
- `app/app/Policies/CreditNotePolicy.php` - Policy implementation
- Permission definitions
- Company scope validation
- User role checks
- Operation authorization

### Task 3.11: Write Comprehensive Tests for Credit Note Functionality
**Description**: Create complete test suite for credit note operations.
**Dependencies**: Task 3.10
**Estimated Time**: 8 hours
**Deliverables**:
- `tests/Feature/CreditNoteTest.php` - Feature tests
- `tests/Unit/Services/CreditNoteServiceTest.php` - Service tests
- CLI command tests
- Policy tests
- Integration tests

## Phase 4: Enhanced Payment Allocation (Priority: Medium)

### Task 4.1: Analyze Existing Payment Allocation System
**Description**: Review and document current payment allocation implementation.
**Dependencies**: None
**Estimated Time**: 4 hours
**Deliverables**:
- Current system documentation
- Architecture analysis
- Limitation identification
- Enhancement opportunities
- Performance assessment

**Analysis Areas**:
- PaymentAllocation model
- PaymentService methods
- Allocation algorithms
- Database queries
- Performance bottlenecks

### Task 4.2: Implement Multi-Invoice Payment Allocation
**Description**: Add support for allocating payments across multiple invoices.
**Dependencies**: Task 4.1
**Estimated Time**: 8 hours
**Deliverables**:
- Multi-invoice allocation logic
- User interface for allocation
- Validation rules
- Allocation strategies
- Undo functionality

**Allocation Process**:
1. Select unpaid invoices
2. Specify allocation amounts
3. Apply allocation rules
4. Update invoice balances
5. Create allocation records

### Task 4.3: Add Automatic Allocation Strategies (FIFO, Manual)
**Description**: Implement different allocation strategies for automatic payment distribution.
**Dependencies**: Task 4.2
**Estimated Time**: 6 hours
**Deliverables**:
- FIFO allocation strategy
- Proportional allocation strategy
- Manual allocation interface
- Strategy selection
- Configuration management

**Strategies**:
- FIFO (First In, First Out)
- Proportional (by balance)
- By due date
- By invoice amount
- Custom priority

### Task 4.4: Implement Payment Allocation Reversal Functionality
**Description**: Add ability to reverse payment allocations with proper audit trail.
**Dependencies**: Task 4.3
**Estimated Time**: 6 hours
**Deliverables**:
- Allocation reversal logic
- Validation rules
- Audit trail tracking
- Balance recalculation
- Permission controls

**Reversal Process**:
1. Validate reversal permissions
2. Check allocation status
3. Create reversal records
4. Recalculate balances
5. Update audit trail

### Task 4.5: Enhance Balance Tracking Across Multiple Invoices
**Description**: Improve balance tracking for complex payment scenarios.
**Dependencies**: Task 4.4
**Estimated Time**: 6 hours
**Deliverables**:
- Enhanced balance calculations
- Real-time updates
- Balance history tracking
- Reporting improvements
- Performance optimization

**Tracking Features**:
- Real-time balance updates
- Historical balance views
- Allocation impact tracking
- Customer balance summary
- Aging reports

### Task 4.6: Add Payment Allocation Reporting
**Description**: Create comprehensive reports for payment allocation analysis.
**Dependencies**: Task 4.5
**Estimated Time**: 6 hours
**Deliverables**:
- Allocation detail reports
- Summary reports
- Trend analysis
- Export functionality
- Dashboard widgets

**Report Types**:
- Daily allocation summary
- Payment allocation details
- Unallocated payments
- Allocation efficiency
- Customer payment patterns

### Task 4.7: Update PaymentService with New Allocation Features
**Description**: Enhance PaymentService with new allocation capabilities.
**Dependencies**: Task 4.6
**Estimated Time**: 8 hours
**Deliverables**:
- Enhanced PaymentService
- New allocation methods
- Strategy integration
- Validation improvements
- Performance optimization

**Service Enhancements**:
```php
public function allocatePaymentAcrossInvoices(Payment $payment, array $allocations, ServiceContext $context)
public function reverseAllocation(PaymentAllocation $allocation, ServiceContext $context)
public function applyAllocationStrategy(Payment $payment, string $strategy, ServiceContext $context)
```

### Task 4.8: Create CLI Commands for Payment Allocation Management
**Description**: Implement CLI commands for payment allocation operations.
**Dependencies**: Task 4.7
**Estimated Time**: 6 hours
**Deliverables**:
- `stack/app/Console/Commands/PaymentAllocate.php`
- `stack/app/Console/Commands/PaymentAllocationList.php`
- `stack/app/Console/Commands/PaymentAllocationReverse.php`
- `stack/app/Console/Commands/PaymentAllocationReport.php`

**Command Examples**:
```bash
php artisan payment:allocate PAY-001 --invoice=INV-001 --amount=500
php artisan payment:allocation:list --payment=PAY-001
php artisan payment:allocation:reverse ALLOC-001 --reason="Error correction"
```

### Task 4.9: Add Comprehensive Tests for Allocation Scenarios
**Description**: Create thorough test suite for payment allocation functionality.
**Dependencies**: Task 4.8
**Estimated Time**: 8 hours
**Deliverables**:
- `tests/Feature/PaymentAllocationTest.php` - Feature tests
- `tests/Unit/Services/PaymentServiceTest.php` - Enhanced service tests
- CLI command tests
- Integration tests
- Performance tests

## Documentation and Validation

### Task 5.1: Update API Documentation for All New Features
**Description**: Create comprehensive API documentation for all new functionality.
**Dependencies**: All Phase 1-4 tasks
**Estimated Time**: 8 hours
**Deliverables**:
- Updated OpenAPI/Swagger specifications
- Endpoint documentation
- Request/response examples
- Authentication requirements
- Error code reference

### Task 5.2: Create CLI Command Documentation and Examples
**Description**: Document all new CLI commands with examples and use cases.
**Dependencies**: Task 5.1
**Estimated Time**: 6 hours
**Deliverables**:
- Command reference guide
- Usage examples
- Natural language examples
- Best practices guide
- Troubleshooting section

### Task 5.3: Validate All Constitutional Requirements Are Met
**Description**: Ensure implementation complies with all constitutional principles.
**Dependencies**: Task 5.2
**Estimated Time**: 6 hours
**Deliverables**:
- Compliance checklist
- Validation report
- Remediation tasks
- Compliance documentation
- Sign-off from stakeholders

### Task 5.4: Perform Security Review of New Functionality
**Description**: Conduct comprehensive security review of all new features.
**Dependencies**: Task 5.3
**Estimated Time**: 8 hours
**Deliverables**:
- Security assessment report
- Vulnerability scan results
- Remediation plan
- Security guidelines
- Penetration test results

### Task 5.5: Run Performance Benchmarks for All Operations
**Description**: Establish performance baselines and ensure optimization.
**Dependencies**: Task 5.4
**Estimated Time**: 6 hours
**Deliverables**:
- Performance benchmark results
- Optimization recommendations
- Load testing reports
- Database query analysis
- Memory usage reports

### Task 5.6: Complete End-to-End Testing of Invoice Lifecycle
**Description**: Test complete invoice lifecycle from creation to payment.
**Dependencies**: Task 5.5
**Estimated Time**: 8 hours
**Deliverables**:
- End-to-end test suite
- Lifecycle validation
- Integration test results
- User acceptance testing
- Performance validation

### Task 5.7: Final Validation of All Deliverables and Requirements
**Description**: Final review and validation of all project deliverables.
**Dependencies**: Task 5.6
**Estimated Time**: 4 hours
**Deliverables**:
- Final validation report
- Requirements compliance matrix
- Quality assurance sign-off
- Stakeholder approval
- Project completion documentation

## Implementation Guidelines

### Constitutional Compliance
1. **Single Source Doctrine**: All implementation follows existing patterns
2. **Command-Bus Supremacy**: All write operations use registered command actions
3. **CLI-GUI Parity**: All GUI operations have CLI equivalents
4. **Tenancy & RLS Safety**: All operations maintain proper tenant isolation
5. **RBAC Integrity**: Proper authorization and permission checks

### Code Quality Standards
1. **TDD Approach**: Write failing tests first, then implement functionality
2. **Documentation**: All methods and classes properly documented
3. **Error Handling**: Comprehensive error handling and logging
4. **Performance**: Optimize for sub-200ms response times
5. **Security**: Follow OWASP guidelines and security best practices

### Testing Requirements
1. **Unit Tests**: 90%+ code coverage for business logic
2. **Feature Tests**: All user workflows tested
3. **Integration Tests**: System integration points validated
4. **Performance Tests**: Load testing for critical paths
5. **Security Tests**: Vulnerability scanning and penetration testing

### Deliverable Standards
1. **Code**: Follow PSR-12 coding standards
2. **Documentation**: Clear, comprehensive, and up-to-date
3. **Tests**: Well-structured and maintainable
4. **Performance**: Meets or exceeds benchmarks
5. **Security**: No critical vulnerabilities

## Risk Mitigation

### Technical Risks
1. **CLI Complexity**: Implement incremental natural language processing
2. **Schema Changes**: Use versioned migrations with rollback capability
3. **Performance**: Implement caching and query optimization
4. **Integration**: Maintain backward compatibility

### Business Risks
1. **Scope Creep**: Strict adherence to defined requirements
2. **Timeline**: Regular progress reviews and adjustments
3. **Resources**: Cross-training and knowledge sharing
4. **Quality**: Continuous integration and automated testing

## Success Criteria

### Functional Requirements
- [ ] All CLI commands implemented and tested
- [ ] Invoice templates fully functional
- [ ] Credit notes integrated with ledger
- [ ] Enhanced payment allocation working
- [ ] GUI-CLI parity achieved

### Quality Requirements
- [ ] 90%+ test coverage achieved
- [ ] Performance benchmarks met
- [ ] Security review passed
- [ ] Constitutional compliance verified
- [ ] Documentation complete

### User Acceptance
- [ ] CLI commands intuitive and easy to use
- [ ] Natural language processing functional
- [ ] Error messages clear and helpful
- [ ] Performance meets expectations
- [ ] Training materials adequate

---

**Next Steps**: Begin with Phase 1: CLI Parity Implementation, starting with Task 1.1: Create CLI Command Structure and Base Classes.
