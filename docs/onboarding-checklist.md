# Developer Onboarding Checklist

> **ARCHIVE NOTICE**: This onboarding plan references the retired ServiceContext workflow. Consult `docs/TEAM_MEMORY.md` and the Constitution v2.2.0 for the current process.

## 🚀 Quick Start

### Day 1: Environment Setup
- [ ] Clone repository: `git clone <repo-url>`
- [ ] Install dependencies: `composer install`
- [ ] Copy environment file: `cp .env.example .env`
- [ ] Generate app key: `php artisan key:generate`
- [ ] Setup database: `php artisan migrate`
- [ ] Seed database: `php artisan db:seed`
- [ ] Install Node dependencies: `npm install`
- [ ] Build assets: `npm run build`

### Day 2: Codebase Orientation
- [ ] Read [Architecture Overview](./architecture.md)
- [ ] Understand [Service Layer Pattern](./service-layer.md)
- [ ] Review [Testing Guidelines](./testing.md)
- [ ] Setup IDE for Laravel development
- [ ] Configure Xdebug for debugging

## 📋 Core Concepts

### ServiceContext Pattern ⭐
**CRITICAL**: All services now use ServiceContext for user context

```php
// ✅ Always use ServiceContext in services
public function createPayment(..., ServiceContext $context)
{
    $userId = $context->getActingUser()?->id;
    $companyId = $context->getCompanyId();
}

// ✅ Create context in controllers
public function store(Request $request)
{
    $context = ServiceContextHelper::fromRequest($request);
    $result = $this->service->create($data, $context);
}

// ❌ NEVER use auth() in services
public function badExample()
{
    $userId = auth()->id(); // WRONG!
}
```

**Learn more**: [ServiceContext Guide](./ServiceContext-Guide.md)

### Audit Logging
All actions must be logged:

```php
$this->logAudit('action.create', [
    'entity_id' => $entity->id,
    'data' => $data
], $context);
```

### Idempotency
All write operations must support idempotency:

```php
// Controller adds Idempotency-Key header
$idempotencyKey = $request->header('Idempotency-Key');

// Service uses key for deduplication
```

## 🧪 Testing Requirements

### Unit Tests
- [ ] All services must have unit tests
- [ ] Mock ServiceContext in tests:
  ```php
  $context = ServiceContext::forUser($user, $company->id);
  ```

### Feature Tests
- [ ] Test authentication and authorization
- [ ] Test validation rules
- [ ] Test error scenarios
- [ ] Test ServiceContext propagation

### Payment & Allocation Tests
- [ ] Use `PaymentServiceAllocationTest` as reference
- [ ] Test race conditions with database locks
- [ ] Test money precision handling
- [ ] Test batch processing workflows with CSV and manual entries
- [ ] Test batch status monitoring and error handling
- [ ] Use `BatchProcessingTest` as reference for comprehensive batch scenarios

## 🔒 Security & Compliance

### Authentication
- [ ] Use Laravel's built-in auth
- [ ] Never store passwords in plain text
- [ ] Use policy classes for authorization

### Data Validation
- [ ] Validate all user input
- [ ] Use Form Request classes
- [ ] Sanitize output for XSS

### SQL Injection Prevention
- [ ] Always use Eloquent or parameterized queries
- [ ] Never concatenate SQL strings
- [ ] Use scopes for complex queries

## 🏗️ Code Standards

### PHP Standards
- [ ] Follow PSR-12 coding standards
- [ ] Use type hints everywhere
- [ ] Write PHPDoc blocks for all public methods
- [ ] Keep methods small (< 20 lines)
- [ ] Use early returns

### Service Layer Standards
```php
// ✅ Good service method
public function createInvoice(
    Company $company,
    Customer $customer,
    array $items,
    ServiceContext $context
): Invoice {
    // Validate
    // Process
    // Log
    // Return
}
```

### Frontend Standards
- [ ] Use Vue 3 Composition API
- [ ] Follow Pinia for state management
- [ ] Write component tests with Vitest
- [ ] Use TypeScript for new components

## 📊 Monitoring & Observability

### Logging
- [ ] Log all errors with context
- [ ] Use structured logging
- [ ] Don't log sensitive data
- [ ] Log at appropriate levels

### Performance
- [ ] Use database indexes properly
- [ ] Cache expensive operations
- [ ] Optimize N+1 queries
- [ ] Monitor query performance

### Metrics to Watch
- [ ] API response times
- [ ] Error rates
- [ ] Database query times
- [ ] Queue processing times

## 🚦 Development Workflow

### Git Workflow
- [ ] Create feature branch from `main`
- [ ] Write atomic commits
- [ ] Use conventional commits
- [ ] Keep PRs focused and small
- [ ] All PRs require review

### PR Checklist
- [ ] Tests pass
- [ ] Code follows standards
- [ ] Documentation updated
- [ ] Security review complete
- [ ] Performance considered

### Deployment
- [ ] Deploy to staging first
- [ ] Run all tests
- [ ] Check monitoring dashboards
- [ ] Deploy to production during business hours
- [ ] Monitor for 1 hour post-deploy

## 🛠️ Common Tasks

### Adding a New Service
1. Create service class in `app/Services/`
2. Add ServiceContext to all public methods
3. Write unit tests
4. Register in service container if needed
5. Update documentation

### Adding a New API Endpoint
1. Create Form Request for validation
2. Create controller method
3. Use ServiceContextHelper::fromRequest()
4. Implement business logic in service
5. Write tests
6. Update API documentation

### Adding Database Migration
1. Create migration: `php artisan make:migration`
2. Use Schema Builder methods
3. Add indexes for foreign keys
4. Write rollback logic
5. Test migration and rollback

### Batch Processing Workflows
1. **CSV Import Process**:
   - Validate CSV format and required columns
   - Handle file uploads with proper validation
   - Create batch records with metadata
   - Process entries asynchronously with queues
   - Monitor progress and handle errors

2. **Manual Entry Batches**:
   - Create batch with manual payment entries
   - Validate customer UUIDs and payment data
   - Support auto-allocation strategies
   - Track processing status and statistics

3. **CLI Operations**:
   ```bash
   # Import batch from CSV
   php artisan payment:batch:import --source=csv --file=payments.csv
   
   # Monitor batch status
   php artisan payment:batch:status BATCH-20250115-001 --refresh
   
   # List recent batches
   php artisan payment:batch:list --status=completed --limit=10
   ```

4. **Testing Batch Features**:
   - Test CSV validation with malformed files
   - Test large batch processing performance
   - Test error handling and recovery scenarios
   - Test real-time status updates in UI
   - Test idempotency with duplicate batch creation

## 📚 Key Documentation

### Must Read
- [ServiceContext Guide](./ServiceContext-Guide.md) - Understand our context pattern
- [Testing Guidelines](./testing.md) - How to write tests
- [API Documentation](./api/) - API reference
- [Deployment Guide](./deployment.md) - How to deploy
- [Payment Batch Processing Quick Start](./payment-batch-quickstart.md) - Batch processing overview
- [Payment Batch CLI Reference](./payment-batch-cli-reference.md) - Complete CLI command reference
- [Payment Allocations API Guide](./api-allocation-guide.md) - Payment operations reference

### Architecture
- [System Architecture](./architecture.md)
- [Database Schema](./database-schema.md)
- [Queue System](./queues.md)
- [Cache Strategy](./caching.md)

### Reference
- [PHP Standards](./php-standards.md)
- [JavaScript Standards](./js-standards.md)
- [Security Guidelines](./security.md)
- [Performance Guide](./performance.md)

### Component Library
- [Component Documentation Index](./components/README.md) - Overview of all reusable components
- [Ledger Components](./components/Ledger-Components.md) - Journal entry and account management components
- [Form Components](./components/README.md#form-components) - Entity pickers and form elements
- [Data Display Components](./components/README.md#data-display-components) - Tables, badges, and displays
- [Development Guide](./development-guide.md) - Best practices for using components

## 🆘 Getting Help

### Slack Channels
- `#engineering` - General engineering discussions
- `#help` - Technical questions
- `#code-review` - PR reviews
- `#deployments` - Deployment notifications

### Code Review Process
1. Self-review your code first
2. Request review from team members
3. Address all feedback
4. Ensure CI passes
5. Merge after approval

### Common Issues
- [ ] Check ServiceContext is passed correctly
- [ ] Verify audit logging is implemented
- [ ] Test idempotency for write operations
- [ ] Check for N+1 queries
- [ ] Validate all user input

## 🎯 Success Metrics

After 30 days, you should be able to:
- [ ] Set up development environment independently
- [ ] Write code following our standards
- [ ] Create and merge PRs successfully
- [ ] Debug and fix issues
- [ ] Write effective tests
- [ ] Deploy changes safely
- [ ] Understand our architecture patterns

## 📝 Notes

- Always ask questions if unsure
- We value code reviews and collaboration
- Take time to understand the "why" behind decisions
- Focus on writing maintainable, secure code
- Remember to document your work

---

**Welcome to the team! 🎉**
