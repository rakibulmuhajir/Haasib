# Manual Testing Checklist

## Test Instructions
- Mark each test with: ✅ (passed), ❌ (failed), ⚠️ (partial/warning), or ➖ (not tested)
- Add notes for any failures or issues found
- Date your test runs
- Confirm CLI parity and API idempotency for every flow that mutates data (per Constitution v2.2.0).

---

## 1. Authentication & User Management

### 1.1 User Registration
- [ ] New user registration with valid data
- [ ] Registration with existing email
- [ ] Registration with weak password
- [ ] Email verification after registration
- [ ] Registration without required fields

### 1.2 Login/Logout
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Login with unverified email
- [ ] Remember me functionality
- [ ] Logout from all sessions
- [ ] Session timeout

### 1.3 Password Management
- [ ] Password reset request
- [ ] Password reset with valid token
- [ ] Password reset with expired token
- [ ] Change password when logged in
- [ ] Change password with wrong current password

---

## 2. Company Management

### 2.1 Company Operations
- [ ] Create new company
- [ ] Edit company details
- [ ] Delete company (with and without data)
- [ ] Switch between companies
- [ ] Company selection on login

### 2.2 Company User Management
- [ ] Invite user to company
- [ ] Accept company invitation
- [ ] Reject company invitation
- [ ] Remove user from company
- [ ] Change user role in company

### 2.3 Data Isolation
- [ ] User can only see their company data
- [ ] Company data separation
- [ ] Cross-company access prevention

---

## 3. Invoicing System

### 3.1 Invoice CRUD
- [ ] Create new invoice (all fields)
- [ ] Edit draft invoice
- [ ] Edit sent invoice
- [ ] Edit posted invoice
- [ ] Delete draft invoice
- [ ] Delete sent invoice
- [ ] Delete posted invoice

### 3.2 Invoice Status Transitions
- [ ] Draft → Sent
- [ ] Sent → Posted
- [ ] Sent → Cancelled
- [ ] Posted → Paid (via payment)
- [ ] Posted → Cancelled
- [ ] Paid → Refunded

### 3.3 Invoice Items
- [ ] Add line items to invoice
- [ ] Edit line items
- [ ] Delete line items
- [ ] Add taxes to line items
- [ ] Calculate totals correctly
- [ ] Handle quantity and price decimals

### 3.4 PDF & Email
- [ ] Generate PDF for invoice
- [ ] Email invoice to customer
- [ ] Download PDF
- [ ] PDF branding/customization

### 3.5 Bulk Operations
- [ ] Bulk delete invoices
- [ ] Bulk change status
- [ ] Bulk send emails
- [ ] Bulk export

### 3.6 Invoice Actions
- [ ] Duplicate invoice
- [ ] Copy invoice to new customer
- [ ] Add invoice notes
- [ ] Void invoice
- [ ] Credit note creation

---

## 4. Customer Management

### 4.1 Customer CRUD
- [ ] Create new customer (all fields)
- [ ] Edit customer details
- [ ] Delete customer (with and without invoices)
- [ ] Customer search and filtering
- [ ] Customer sorting

### 4.2 Customer Contacts
- [ ] Add multiple contacts
- [ ] Edit contact details
- [ ] Set primary contact
- [ ] Delete contact

### 4.3 Customer Statements
- [ ] Generate customer statement
- [ ] View aging report
- [ ] View payment history
- [ ] Export customer data

---

## 5. Payment Processing

### 5.1 Payment CRUD
- [ ] Create new payment
- [ ] Edit unallocated payment
- [ ] Delete unallocated payment
- [ ] Void payment
- [ ] Refund payment

### 5.2 Payment Allocation
- [ ] Manual allocation to specific invoices
- [ ] Auto-allocation (oldest first)
- [ ] Partial allocation
- [ ] Overpayment handling
- [ ] Unallocate payment
- [ ] Reallocate payment

### 5.3 Payment Methods
- [ ] Different payment methods (cash, check, bank transfer)
- [ ] Payment reference tracking
- [ ] Payment date validation
- [ ] Multi-currency payments

### 5.4 Payment Reconciliation
- [ ] Match payments to invoices
- [ ] Handle unidentified payments
- [ ] Payment reconciliation reports

---

## 6. Currency & Exchange Rates

### 6.1 Currency Setup
- [ ] Add new currency
- [ ] Set primary currency for company
- [ ] Configure secondary currencies
- [ ] Disable currency

### 6.2 Exchange Rates
- [ ] Manual exchange rate entry
- [ ] Automatic exchange rate sync
- [ ] Historical exchange rates
- [ ] Exchange rate calculation accuracy

### 6.3 Multi-currency Transactions
- [ ] Create invoice in foreign currency
- [ ] Make payment in different currency
- [ ] Currency conversion calculations
- [ ] Gain/loss calculations

---

## 7. Ledger & Accounting

### 7.1 Chart of Accounts
- [ ] View chart of accounts
- [ ] Add new account
- [ ] Edit account details
- [ ] Archive account

### 7.2 Journal Entries
- [ ] Manual journal entry creation
- [ ] Edit unposted entry
- [ ] Post journal entry
- [ ] Reverse journal entry
- [ ] Recurring entries

### 7.3 Double-Entry Validation
- [ ] Debits = Credits validation
- [ ] Account balance calculations
- [ ] Trial balance generation
- [ ] Balance sheet accuracy

### 7.4 Accounts Receivable
- [ ] AR ledger entries
- [ ] Aging reports
- [ ] Bad debt provision
- [ ] AR reconciliation

---

## 8. API Testing

### 8.1 Authentication
- [ ] API login with valid credentials
- [ ] API login with invalid credentials
- [ ] Token refresh
- [ ] Token invalidation

### 8.2 Invoice API
- [ ] GET /api/invoices (list, filter, paginate)
- [ ] POST /api/invoices (create)
- [ ] PUT /api/invoices/{id} (update)
- [ ] DELETE /api/invoices/{id} (delete)
- [ ] Bulk operations

### 8.3 Customer API
- [ ] GET /api/customers
- [ ] POST /api/customers
- [ ] PUT /api/customers/{id}
- [ ] DELETE /api/customers/{id}

### 8.4 Payment API
- [ ] GET /api/payments
- [ ] POST /api/payments
- [ ] POST /api/payments/{id}/allocate
- [ ] POST /api/payments/{id}/void

### 8.5 Idempotency
- [ ] Retry duplicate requests safely
- [ ] Idempotency key validation
- [ ] Concurrent request handling

---

## 9. UI/UX Testing

### 9.1 Navigation
- [ ] Menu navigation works correctly
- [ ] Breadcrumb navigation
- [ ] Back/forward browser buttons
- [ ] Mobile responsive navigation

### 9.2 Forms & Validation
- [ ] All form validations work
- [ ] Error messages are clear
- [ ] Form state persistence
- [ ] Keyboard navigation
- [ ] Screen reader accessibility

### 9.3 Data Display
- [ ] Tables load and display data
- [ ] Sorting and filtering work
- [ ] Pagination works
- [ ] Search functionality
- [ ] Export buttons work

### 9.4 Responsive Design
- [ ] Desktop layout (1920x1080)
- [ ] Tablet layout (768x1024)
- [ ] Mobile layout (375x667)
- [ ] Landscape and portrait modes

---

## 10. Security & Permissions

### 10.1 Authentication Security
- [ ] Session hijacking prevention
- [ ] CSRF protection
- [ ] XSS protection
- [ ] Rate limiting on login

### 10.2 Authorization
- [ ] Role-based access control
- [ ] Company data isolation
- [ ] API endpoint protection
- [ ] Sensitive data encryption

### 10.3 Data Validation
- [ ] Input sanitization
- [ ] SQL injection prevention
- [ ] File upload security
- [ ] API parameter validation

---

## 11. Performance Testing

### 11.1 Load Testing
- [ ] Page load times < 3 seconds
- [ ] API response times < 1 second
- [ ] Database query optimization
- [ ] Concurrent user handling

### 11.2 Large Data Sets
- [ ] 1000+ invoices in list
- [ ] 100+ line items on invoice
- [ ] Large customer database
- [ ] Year-long date range queries

---

## 12. Integration Testing

### 12.1 Cross-Module Integration
- [ ] Invoice → Payment → Ledger flow
- [ ] Customer → Invoice → Statement flow
- [ ] Currency conversion across modules
- [ ] Email notifications on all actions

### 12.2 Third-Party Integration
- [ ] Email delivery (SMTP)
- [ ] PDF generation
- [ ] File storage
- [ ] Background jobs

---

## Test Results Log

### Test Run 1 - [Date]
- Environment: [Local/Production]
- Browser: [Browser name and version]
- Tester: [Name]

#### Summary
- Total tests: [count]
- Passed: [count]
- Failed: [count]
- Warnings: [count]
- Not tested: [count]

#### Critical Issues
1. [Issue description]
2. [Issue description]

#### Notes
[Additional notes, observations, or suggestions]

---

### Test Run 2 - [Date]
- Environment: [Local/Production]
- Browser: [Browser name and version]
- Tester: [Name]

#### Summary
- Total tests: [count]
- Passed: [count]
- Failed: [count]
- Warnings: [count]
- Not tested: [count]

#### Critical Issues
1. [Issue description]
2. [Issue description]

#### Notes
[Additional notes, observations, or suggestions]
