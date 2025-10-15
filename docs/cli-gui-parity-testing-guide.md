# CLI↔GUI Parity Testing Guide

## Overview

This guide explains the approach and implementation for testing parity between CLI commands and GUI (web interface) functionality in the Haasib payment batch processing system.

## Testing Philosophy

### Why CLI↔GUI Parity Matters

1. **Consistency**: Users should get the same results regardless of interface
2. **Reliability**: Both interfaces should handle errors similarly
3. **Performance**: Response times should be comparable across interfaces
4. **User Experience**: CLI power users and GUI users should have equivalent capabilities

### Testing Strategy

We implement a multi-layered testing approach:

1. **Functional Parity Tests**: Both interfaces produce identical results
2. **Error Handling Tests**: Both interfaces handle validation and errors consistently  
3. **Performance Tests**: Response times are within acceptable ranges
4. **Accessibility Tests**: Keyboard navigation and responsive design
5. **Data Integrity Tests**: Both interfaces modify data consistently

## Test Implementation

### 1. Parity Tests (PHP/Pest)

**Location**: `tests/Feature/Payments/BatchProcessingParityTest.php`

These tests verify that CLI and API endpoints produce consistent results:

```php
test('CLI and GUI batch creation parity', function () {
    // Create batch via CLI
    $cliResult = $this->artisan('payment:batch:import', [
        '--source' => 'manual',
        '--entries' => json_encode($entries),
    ]);
    
    // Create batch via API (simulating GUI)
    $response = $this->postJson('/api/accounting/payment-batches', $batchData);
    
    // Verify parity between results
    expect($apiBatchData['status'])->toBe($cliBatch->status);
    expect($apiBatchData['receipt_count'])->toBe($cliBatch->receipt_count);
});
```

### 2. Browser Tests (Playwright)

**Location**: `tests/e2e/batch-processing-parity.spec.ts`

These tests verify GUI functionality and user experience:

```typescript
test('GUI batch creation matches CLI behavior', async ({ page }) => {
    await page.click('[data-testid="create-batch-btn"]');
    await page.fill('[data-testid="entity-id"]', testBatchData.entries[0].entity_id);
    await page.click('[data-testid="submit-batch-btn"]');
    
    // Verify results match CLI output structure
    await expect(page.locator('[data-testid="batch-list"]')).toContainText(referenceNumber);
});
```

### 3. Validation Script (Bash)

**Location**: `scripts/validate-cli-gui-parity.sh`

Automated script for continuous parity validation:

```bash
# Test CLI functionality
CLI_OUTPUT=$(php app/artisan payment:batch:import --source=manual --entries="$TEST_ENTRIES")

# Test API functionality  
API_RESPONSE=$(curl -X POST http://localhost:8000/api/accounting/payment-batches -d "$BATCH_DATA")

# Compare results and validate parity
```

## Test Coverage Areas

### 1. Batch Creation

**CLI Command**: `php artisan payment:batch:import`
**GUI Endpoint**: `POST /api/accounting/payment-batches`

**Test Scenarios**:
- ✅ Manual entry creation
- ✅ CSV file upload
- ✅ Validation error handling
- ✅ Data structure consistency
- ✅ Response format parity

### 2. Batch Status Monitoring

**CLI Command**: `php artisan payment:batch:status`
**GUI Endpoint**: `GET /api/accounting/payment-batches/{id}`

**Test Scenarios**:
- ✅ Status information consistency
- ✅ Progress tracking parity
- ✅ Real-time updates
- ✅ Error display consistency
- ✅ Metadata structure matching

### 3. Batch Listing and Filtering

**CLI Command**: `php artisan payment:batch:list`
**GUI Endpoint**: `GET /api/accounting/payment-batches`

**Test Scenarios**:
- ✅ List structure consistency
- ✅ Filtering options parity
- ✅ Pagination behavior
- ✅ Sort order consistency
- ✅ Search functionality

### 4. Error Handling

**CLI**: Command-line error messages and exit codes
**GUI**: HTTP status codes and JSON error responses

**Test Scenarios**:
- ✅ Invalid data validation
- ✅ Missing required fields
- ✅ Authentication/authorization errors
- ✅ System error handling
- ✅ User-friendly error messages

### 5. Performance

**CLI**: Command execution time
**GUI**: HTTP response time + page load time

**Test Scenarios**:
- ✅ Batch creation performance
- ✅ Status check response time
- ✅ List loading performance
- ✅ Large dataset handling
- ✅ Concurrent operation handling

## Running the Tests

### 1. PHP Unit/Feature Tests

```bash
# Run all parity tests
php artisan test tests/Feature/Payments/BatchProcessingParityTest.php

# Run specific test
php artisan test --filter=CLI_and_GUI_batch_creation_parity
```

### 2. Playwright Browser Tests

```bash
# Install Playwright dependencies
npm install --save-dev @playwright/test

# Run browser tests
npx playwright test

# Run specific test file
npx playwright test tests/e2e/batch-processing-parity.spec.ts

# Run with specific browser
npx playwright test --project=chromium
```

### 3. Automated Validation Script

```bash
# Run parity validation
./scripts/validate-cli-gui-parity.sh

# Run with verbose output
./scripts/validate-cli-gui-parity.sh --verbose

# Run performance benchmarks only
./scripts/validate-cli-gui-parity.sh --performance-only
```

## Expected Results

### Success Criteria

1. **Functional Parity**: Both interfaces produce identical results for the same input
2. **Error Consistency**: Validation rules and error messages are consistent
3. **Performance**: GUI response time within 2x CLI execution time
4. **Data Integrity**: Both interfaces modify database consistently
5. **User Experience**: Both interfaces provide equivalent capabilities

### Performance Benchmarks

| Operation | CLI Target | GUI Target | Acceptable Difference |
|-----------|------------|------------|---------------------|
| Batch Creation | < 2s | < 3s | < 2s |
| Status Check | < 1s | < 2s | < 1s |
| Batch Listing | < 1s | < 2s | < 1s |
| Error Handling | < 500ms | < 1s | < 500ms |

## Continuous Integration

### GitHub Actions Workflow

```yaml
name: CLI↔GUI Parity Tests

on: [push, pull_request]

jobs:
  parity-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install Dependencies
      run: composer install
      
    - name: Run PHP Tests
      run: php artisan test tests/Feature/Payments/BatchProcessingParityTest.php
      
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install Playwright
      run: npx playwright install
      
    - name: Run Browser Tests
      run: npx playwright test tests/e2e/batch-processing-parity.spec.ts
      
    - name: Run Validation Script
      run: ./scripts/validate-cli-gui-parity.sh
```

## Troubleshooting

### Common Issues

1. **Environment Mismatch**: Ensure both CLI and web server use same environment
2. **Database State**: Use database transactions for test isolation
3. **Authentication**: Set up proper test users and permissions
4. **File Permissions**: Ensure CSV upload directories are writable
5. **Queue Configuration**: Verify queue workers are running for async operations

### Debug Mode

Enable detailed logging for troubleshooting:

```bash
# Set debug level
php artisan log:level=debug

# Run tests with verbose output
php artisan test --verbose

# Run Playwright with debug
npx playwright test --debug
```

## Future Enhancements

### Planned Improvements

1. **Automated Screenshot Comparison**: Visual regression testing
2. **Load Testing**: Performance testing under high load
3. **Cross-browser Testing**: Additional browser compatibility
4. **Mobile App Testing**: Native mobile app parity testing
5. **API Contract Testing: OpenAPI specification validation

### Monitoring and Alerting

1. **Performance Metrics**: Track CLI vs GUI response times
2. **Error Rate Monitoring**: Alert on parity deviations
3. **User Analytics**: Track interface usage patterns
4. **Automated Reporting**: Daily parity validation reports

---

This guide provides a comprehensive framework for ensuring CLI↔GUI parity in the Haasib payment processing system. Regular execution of these tests helps maintain consistency and reliability across all user interfaces.