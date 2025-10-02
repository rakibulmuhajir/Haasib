# RBAC Implementation Brief

## Super Admin System Permissions

This document outlines all available permissions in the system, specifically focusing on super admin capabilities for system-wide operations.

### System-Level Permissions (team_id = null)

#### 1. Company Management
- `system.companies.view` - View all companies in the system
- `system.companies.create` - Create new companies
- `system.companies.update` - Update any company details
- `system.companies.deactivate` - Deactivate/suspend companies
- `system.companies.manage` - Full company management (master permission)

#### 2. Currency Management
- `system.currencies.manage` - Manage system-wide currency settings
- `system.fx.view` - View foreign exchange rates globally
- `system.fx.update` - Update foreign exchange rates system-wide
- `system.fx.sync` - Sync FX rates from external providers

#### 3. User Management
- `system.users.manage` - Manage all users across the system
- `users.roles.assign` - Assign roles to users (system and company contexts)
- `users.deactivate` - Deactivate users (system and company contexts)

#### 4. System Monitoring & Audit
- `system.audit.view` - View system audit logs
- `system.reports.view` - View system-level reports
- `logs.view` - View system logs
- `logs.export` - Export system logs
- `monitoring.view` - View system monitoring dashboard
- `monitoring.alerts.view` - View system alerts
- `monitoring.alerts.manage` - Manage system alert configurations

#### 5. Data Import/Export
- `import.view` - View import configurations
- `import.execute` - Execute data imports
- `export.view` - View export configurations
- `export.execute` - Execute data exports
- `export.schedule` - Schedule automated exports

#### 6. Backup & Recovery
- `backup.create` - Create system backups
- `backup.download` - Download backup files
- `backup.restore` - Restore from backups
- `backup.schedule` - Schedule automated backups

### Company-Level Permissions (Available to Super Admin in Any Context)

#### 1. Company Operations
- `companies.view` - View company details
- `companies.update` - Update company information
- `companies.settings.view` - View company settings
- `companies.settings.update` - Modify company settings

#### 2. Currency Management (Company Context)
- `companies.currencies.view` - View company currencies
- `companies.currencies.enable` - Enable currencies for company
- `companies.currencies.disable` - Disable currencies for company
- `companies.currencies.set-base` - Set company base currency
- `companies.currencies.exchange-rates.view` - View company FX rates
- `companies.currencies.exchange-rates.update` - Update company FX rates

#### 3. User Management (Within Companies)
- `users.invite` - Invite users to companies
- `users.view` - View company users
- `users.update` - Update user details
- `users.roles.revoke` - Revoke user roles

#### 4. Customer Relationship Management (CRM)
- `customers.view` - View customers
- `customers.create` - Create customers
- `customers.update` - Update customer details
- `customers.delete` - Delete customers
- `customers.merge` - Merge customer records
- `customers.export` - Export customer data
- `customers.import` - Import customer data

#### 5. Vendor Management
- `vendors.view` - View vendors
- `vendors.create` - Create vendors
- `vendors.update` - Update vendor details
- `vendors.delete` - Delete vendors
- `vendors.merge` - Merge vendor records
- `vendors.export` - Export vendor data
- `vendors.import` - Import vendor data
- `vendors.credits.view` - View vendor credits
- `vendors.credits.create` - Create vendor credits

#### 6. Invoice Management (Accounts Receivable)
- `invoices.view` - View invoices
- `invoices.create` - Create invoices
- `invoices.update` - Update invoices
- `invoices.delete` - Delete invoices
- `invoices.send` - Send invoices to customers
- `invoices.post` - Post invoices to ledger
- `invoices.void` - Void invoices
- `invoices.duplicate` - Duplicate invoices
- `invoices.export` - Export invoices
- `invoices.import` - Import invoices
- `invoices.approve` - Approve invoices (workflow)

#### 7. Invoice Items Management
- `invoice-items.view` - View invoice line items
- `invoice-items.create` - Create invoice line items
- `invoice-items.update` - Update invoice line items
- `invoice-items.delete` - Delete invoice line items

#### 8. Payment Management (AR/AP)
- `payments.view` - View payments
- `payments.create` - Create payments
- `payments.update` - Update payment details
- `payments.delete` - Delete payments
- `payments.allocate` - Allocate payments to invoices
- `payments.unallocate` - Unallocate payments
- `payments.reconcile` - Reconcile payments
- `payments.refund` - Process refunds
- `payments.void` - Void payments
- `payments.export` - Export payment data
- `payments.import` - Import payment data

#### 9. Bill Management (Accounts Payable)
- `bills.view` - View bills
- `bills.create` - Create bills
- `bills.update` - Update bills
- `bills.delete` - Delete bills
- `bills.approve` - Approve bills (workflow)
- `bills.pay` - Pay bills
- `bills.void` - Void bills
- `bills.duplicate` - Duplicate bills
- `bills.export` - Export bills
- `bills.import` - Import bills

#### 10. Bill Items Management
- `bill-items.view` - View bill line items
- `bill-items.create` - Create bill line items
- `bill-items.update` - Update bill line items
- `bill-items.delete` - Delete bill line items

#### 11. Ledger & Accounting
- `ledger.view` - View ledger entries
- `ledger.entries.create` - Create ledger entries
- `ledger.entries.update` - Update ledger entries
- `ledger.entries.delete` - Delete ledger entries
- `ledger.entries.post` - Post ledger entries
- `ledger.entries.void` - Void ledger entries
- `ledger.journal.view` - View journal entries
- `ledger.journal.create` - Create journal entries
- `ledger.reports.view` - View ledger reports
- `ledger.trial-balance.view` - View trial balance
- `ledger.balance-sheet.view` - View balance sheet
- `ledger.income-statement.view` - View income statement

#### 12. Financial Reports
- `reports.financial.view` - View financial reports
- `reports.ar.view` - View accounts receivable reports
- `reports.ap.view` - View accounts payable reports
- `reports.sales.view` - View sales reports
- `reports.tax.view` - View tax reports
- `reports.custom.create` - Create custom reports
- `reports.custom.view` - View custom reports
- `reports.export` - Export reports

#### 13. Settings & Configuration
- `settings.view` - View settings
- `settings.update` - Update settings
- `settings.company.view` - View company-specific settings
- `settings.company.update` - Update company-specific settings
- `settings.billing.view` - View billing settings
- `settings.billing.update` - Update billing settings
- `settings.integrations.view` - View integration settings
- `settings.integrations.update` - Update integration settings

#### 14. Tax Management
- `tax.view` - View tax configurations
- `tax.create` - Create tax rules
- `tax.update` - Update tax rules
- `tax.delete` - Delete tax rules
- `tax.rates.view` - View tax rates
- `tax.rates.create` - Create tax rates
- `tax.rates.update` - Update tax rates
- `tax.rates.delete` - Delete tax rates
- `tax.reports.view` - View tax reports

#### 15. Document Management
- `attachments.view` - View attachments
- `attachments.upload` - Upload attachments
- `attachments.download` - Download attachments
- `attachments.delete` - Delete attachments

#### 16. Notes & Communications
- `notes.view` - View notes
- `notes.create` - Create notes
- `notes.update` - Update notes
- `notes.delete` - Delete notes

#### 17. API Management
- `api.access` - Access API endpoints
- `api.keys.create` - Create API keys
- `api.keys.update` - Update API keys
- `api.keys.delete` - Delete API keys
- `api.keys.revoke` - Revoke API keys

#### 18. Dashboard & Widgets
- `dashboard.view` - View dashboard
- `dashboard.customize` - Customize dashboard
- `widgets.create` - Create dashboard widgets
- `widgets.update` - Update dashboard widgets
- `widgets.delete` - Delete dashboard widgets
- `widgets.share` - Share dashboard widgets

### Super Admin Special Permissions

Super admins have implicit access to all permissions regardless of context. The following behaviors are built into the system:

1. **Global View Mode**: Super admins can switch to a global context to perform system-wide operations without company affiliation
2. **Cross-Company Access**: Super admins can access any company's data directly
3. **Implicit Permissions**: Super admins don't need explicit permission assignments; they have access to all operations
4. **System Context Operations**: Certain operations like user management and company creation are only available in system context
5. **Permission Inheritance**: System-level permissions (e.g., `users.roles.assign`, `users.deactivate`) are available in both global and company contexts for super admins

### Permission Matrix (Seeded Roles)

The table below is generated directly from `RbacSeeder.php` and shows how many permissions each role receives in every functional area. A `✓` means the role owns the full set of actions for that category, a number indicates partial coverage, and `-` means no access. Treat this as the canonical reference when auditing privileges, writing tests, or deciding which UI controls should be visible per role.

| Category | Total | super_admin | owner | admin | manager | accountant | employee | viewer |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| api | 5 | ✓ | ✓ | 4 | - | - | - | - |
| attachments | 4 | ✓ | ✓ | ✓ | 3 | 3 | 3 | 2 |
| backup | 4 | ✓ | ✓ | - | - | - | - | - |
| bill-items | 4 | ✓ | ✓ | ✓ | 3 | 3 | 2 | 1 |
| bills | 10 | ✓ | ✓ | 8 | 6 | 7 | 3 | 1 |
| companies | 10 | ✓ | ✓ | 9 | 4 | 4 | 2 | 3 |
| customers | 7 | ✓ | ✓ | 6 | 4 | 2 | 1 | 1 |
| dashboard | 2 | ✓ | ✓ | ✓ | 1 | 1 | 1 | 1 |
| export | 3 | ✓ | ✓ | 2 | 2 | 2 | - | - |
| import | 2 | ✓ | ✓ | ✓ | 1 | ✓ | - | - |
| invoice-items | 4 | ✓ | ✓ | ✓ | 3 | 3 | 3 | 1 |
| invoices | 11 | ✓ | ✓ | 9 | 5 | 5 | 4 | 1 |
| ledger | 12 | ✓ | ✓ | 6 | 3 | 11 | 2 | 2 |
| logs | 2 | ✓ | ✓ | 1 | - | 1 | - | - |
| monitoring | 3 | ✓ | ✓ | 2 | - | - | - | - |
| notes | 4 | ✓ | ✓ | ✓ | 3 | 3 | 2 | 1 |
| payments | 11 | ✓ | ✓ | 8 | 5 | 6 | 2 | 1 |
| reports | 8 | ✓ | ✓ | 7 | 6 | ✓ | 4 | 4 |
| settings | 8 | ✓ | ✓ | 7 | 2 | - | - | 1 |
| system | 12 | ✓ | - | - | - | - | - | - |
| tax | 9 | ✓ | ✓ | 5 | 3 | 7 | - | 3 |
| users | 6 | ✓ | ✓ | ✓ | 2 | - | - | 1 |
| vendors | 9 | ✓ | ✓ | 8 | 4 | 2 | 1 | 1 |
| widgets | 4 | ✓ | ✓ | 3 | 2 | 2 | - | - |
| **Total permissions** | **154** | **154 (100%)** | **142 (92%)** | **111 (72%)** | **62 (40%)** | **72 (47%)** | **30 (19%)** | **25 (16%)** |

**How to use the matrix**

- **Security reviews**: confirm that planned changes only touch intended categories and quickly spot privilege creep.
- **Automated testing**: seed a role, assert that protected routes/pages behave according to the matrix, and catch regressions early.
- **UI gating**: map each button/menu item to its permission; the matrix provides a one-glance check for which roles should see it.
- **Onboarding & support**: share the table when explaining bundled roles to customers or diagnosing “why can’t I…” questions.

> ℹ️ **Notes**
> - The `system` category represents super-admin-only capabilities (`team_id = null`) and is intentionally unavailable to tenant roles.
> - Dashboard-related permissions are split into two categories: `dashboard.*` (high-level layout) and `widgets.*` (component management). Combine both rows for full dashboard coverage.

### Implementation Notes

1. **Permission Checking**: The system checks permissions at two levels:
   - System-level permissions (for super admin operations)
   - Company-level permissions (for company-specific operations)

2. **Context Switching**: Super admins can operate in two contexts:
   - Company Context: Perform operations within a specific company
   - Global Context: Perform system-wide operations

3. **Team-based Permissions**: Using Spatie's multi-tenant permission system:
   - System permissions have `team_id = null`
   - Company permissions have `team_id = company_id`

4. **Frontend Integration**: Permissions are exposed to the frontend via the `usePermissions` composable for UI-based access control

5. **API Security**: All API endpoints are protected with permission middleware using Laravel gates and policies

### Recommended Super Admin Workflow

1. Start in Global View for system-wide tasks (user management, company creation)
2. Switch to specific company context for company-specific operations
3. Use the company switcher to navigate between companies as needed
4. Leverage the implicit permissions to access any data or perform any operation

### Future Considerations

1. **Audit Logging**: Implement comprehensive audit logging for all super admin actions
2. **Approval Workflows**: Consider requiring approval for certain super admin actions
3. **Time-based Access**: Implement temporary elevation of privileges for specific tasks
4. **MFA Enhancement**: Require multi-factor authentication for sensitive operations
