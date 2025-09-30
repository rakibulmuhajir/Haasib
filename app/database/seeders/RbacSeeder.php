<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // System-wide permissions (team_id = null)
            'system.companies.view',
            'system.companies.create',
            'system.companies.update',
            'system.companies.deactivate',
            'system.currencies.manage',
            'system.fx.view',
            'system.fx.update',
            'system.fx.sync',
            'system.users.manage',
            'system.audit.view',
            'system.reports.view',

            // Company management permissions
            'companies.view',
            'companies.update',
            'companies.settings.view',
            'companies.settings.update',
            'companies.currencies.view',
            'companies.currencies.enable',
            'companies.currencies.disable',
            'companies.currencies.set-base',
            'companies.currencies.exchange-rates.view',
            'companies.currencies.exchange-rates.update',
            
            // User management within company
            'users.invite',
            'users.view',
            'users.update',
            'users.deactivate',
            'users.roles.assign',
            'users.roles.revoke',
            
            // Customer management (CRM)
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.merge',
            'customers.export',
            'customers.import',
            
            // Vendor management (CRM)
            'vendors.view',
            'vendors.create',
            'vendors.update',
            'vendors.delete',
            'vendors.merge',
            'vendors.export',
            'vendors.import',
            'vendors.credits.view',
            'vendors.credits.create',
            
            // Invoice management (AR)
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'invoices.send',
            'invoices.post',
            'invoices.void',
            'invoices.duplicate',
            'invoices.export',
            'invoices.import',
            'invoices.approve', // For invoices requiring approval
            
            // Invoice items management
            'invoice-items.view',
            'invoice-items.create',
            'invoice-items.update',
            'invoice-items.delete',
            
            // Payment management (AR/AP)
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',
            'payments.allocate',
            'payments.unallocate',
            'payments.reconcile',
            'payments.refund',
            'payments.void',
            'payments.export',
            'payments.import',
            
            // Bill management (AP)
            'bills.view',
            'bills.create',
            'bills.update',
            'bills.delete',
            'bills.approve',
            'bills.pay',
            'bills.void',
            'bills.duplicate',
            'bills.export',
            'bills.import',
            
            // Bill items management
            'bill-items.view',
            'bill-items.create',
            'bill-items.update',
            'bill-items.delete',
            
            // Ledger and accounting
            'ledger.view',
            'ledger.entries.create',
            'ledger.entries.update',
            'ledger.entries.delete',
            'ledger.entries.post',
            'ledger.entries.void',
            'ledger.journal.view',
            'ledger.journal.create',
            'ledger.reports.view',
            'ledger.trial-balance.view',
            'ledger.balance-sheet.view',
            'ledger.income-statement.view',
            
            // Reports and analytics
            'reports.financial.view',
            'reports.ar.view',
            'reports.ap.view',
            'reports.sales.view',
            'reports.tax.view',
            'reports.custom.create',
            'reports.custom.view',
            'reports.export',
            
            // Settings and configuration
            'settings.view',
            'settings.update',
            'settings.company.view',
            'settings.company.update',
            'settings.billing.view',
            'settings.billing.update',
            'settings.integrations.view',
            'settings.integrations.update',
            
            // Tax management
            'tax.view',
            'tax.create',
            'tax.update',
            'tax.delete',
            'tax.rates.view',
            'tax.rates.create',
            'tax.rates.update',
            'tax.rates.delete',
            'tax.reports.view',
            
            // Attachments and documents
            'attachments.view',
            'attachments.upload',
            'attachments.download',
            'attachments.delete',
            
            // Notes and communications
            'notes.view',
            'notes.create',
            'notes.update',
            'notes.delete',
            
            // API access
            'api.access',
            'api.keys.create',
            'api.keys.update',
            'api.keys.delete',
            'api.keys.revoke',
            
            // Dashboard and widgets
            'dashboard.view',
            'dashboard.customize',
            'widgets.create',
            'widgets.update',
            'widgets.delete',
            'widgets.share',
            
            // Import/Export operations
            'import.view',
            'import.execute',
            'export.view',
            'export.execute',
            'export.schedule',
            
            // Backup and restore
            'backup.create',
            'backup.download',
            'backup.restore',
            'backup.schedule',
            
            // System logs and monitoring
            'logs.view',
            'logs.export',
            'monitoring.view',
            'monitoring.alerts.view',
            'monitoring.alerts.manage',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create system roles (team_id = null)
        $systemRoles = [
            'super_admin' => [
                'name' => 'super_admin',
                'guard_name' => 'web',
                'team_id' => null,
                'permissions' => $permissions, // All permissions
            ],
        ];

        // Create company roles (will be assigned with team_id context)
        $companyRoles = [
            'owner' => [
                'permissions' => [
                    // Full company access
                    'companies.view', 'companies.update', 'companies.settings.view', 'companies.settings.update',
                    'companies.currencies.view', 'companies.currencies.enable', 'companies.currencies.disable',
                    'companies.currencies.set-base', 'companies.currencies.exchange-rates.view', 'companies.currencies.exchange-rates.update',
                    
                    // User management
                    'users.invite', 'users.view', 'users.update', 'users.deactivate', 'users.roles.assign', 'users.roles.revoke',
                    
                    // Full customer/vendor access
                    'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.merge', 'customers.export', 'customers.import',
                    'vendors.view', 'vendors.create', 'vendors.update', 'vendors.delete', 'vendors.merge', 'vendors.export', 'vendors.import',
                    'vendors.credits.view', 'vendors.credits.create',
                    
                    // Full invoice access
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.send', 'invoices.post', 'invoices.void',
                    'invoices.duplicate', 'invoices.export', 'invoices.import', 'invoices.approve',
                    'invoice-items.view', 'invoice-items.create', 'invoice-items.update', 'invoice-items.delete',
                    
                    // Full payment access
                    'payments.view', 'payments.create', 'payments.update', 'payments.delete', 'payments.allocate', 'payments.unallocate',
                    'payments.reconcile', 'payments.refund', 'payments.void', 'payments.export', 'payments.import',
                    
                    // Full bill access
                    'bills.view', 'bills.create', 'bills.update', 'bills.delete', 'bills.approve', 'bills.pay', 'bills.void',
                    'bills.duplicate', 'bills.export', 'bills.import',
                    'bill-items.view', 'bill-items.create', 'bill-items.update', 'bill-items.delete',
                    
                    // Full ledger access
                    'ledger.view', 'ledger.entries.create', 'ledger.entries.update', 'ledger.entries.delete',
                    'ledger.entries.post', 'ledger.entries.void', 'ledger.journal.view', 'ledger.journal.create',
                    'ledger.reports.view', 'ledger.trial-balance.view', 'ledger.balance-sheet.view', 'ledger.income-statement.view',
                    
                    // Full reports
                    'reports.financial.view', 'reports.ar.view', 'reports.ap.view', 'reports.sales.view', 'reports.tax.view',
                    'reports.custom.create', 'reports.custom.view', 'reports.export',
                    
                    // Settings and configuration
                    'settings.view', 'settings.update', 'settings.company.view', 'settings.company.update',
                    'settings.billing.view', 'settings.billing.update', 'settings.integrations.view', 'settings.integrations.update',
                    
                    // Tax management
                    'tax.view', 'tax.create', 'tax.update', 'tax.delete', 'tax.rates.view', 'tax.rates.create',
                    'tax.rates.update', 'tax.rates.delete', 'tax.reports.view',
                    
                    // Attachments and documents
                    'attachments.view', 'attachments.upload', 'attachments.download', 'attachments.delete',
                    
                    // Notes
                    'notes.view', 'notes.create', 'notes.update', 'notes.delete',
                    
                    // API access
                    'api.access', 'api.keys.create', 'api.keys.update', 'api.keys.delete', 'api.keys.revoke',
                    
                    // Dashboard
                    'dashboard.view', 'dashboard.customize', 'widgets.create', 'widgets.update', 'widgets.delete', 'widgets.share',
                    
                    // Import/Export
                    'import.view', 'import.execute', 'export.view', 'export.execute', 'export.schedule',
                    
                    // Backup
                    'backup.create', 'backup.download', 'backup.restore', 'backup.schedule',
                    
                    // Logs and monitoring
                    'logs.view', 'logs.export', 'monitoring.view', 'monitoring.alerts.view', 'monitoring.alerts.manage',
                ],
            ],
            'admin' => [
                'permissions' => [
                    // Company management
                    'companies.view', 'companies.update', 'companies.settings.view', 'companies.settings.update',
                    'companies.currencies.view', 'companies.currencies.enable', 'companies.currencies.disable',
                    'companies.currencies.exchange-rates.view', 'companies.currencies.exchange-rates.update',
                    
                    // User management (except owner)
                    'users.invite', 'users.view', 'users.update', 'users.deactivate', 'users.roles.assign', 'users.roles.revoke',
                    
                    // Customer/vendor management
                    'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.merge', 'customers.export',
                    'vendors.view', 'vendors.create', 'vendors.update', 'vendors.delete', 'vendors.merge', 'vendors.export',
                    'vendors.credits.view', 'vendors.credits.create',
                    
                    // Invoice management
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'invoices.post', 'invoices.void',
                    'invoices.duplicate', 'invoices.export', 'invoices.approve',
                    'invoice-items.view', 'invoice-items.create', 'invoice-items.update', 'invoice-items.delete',
                    
                    // Payment management
                    'payments.view', 'payments.create', 'payments.update', 'payments.allocate', 'payments.reconcile',
                    'payments.refund', 'payments.void', 'payments.export',
                    
                    // Bill management
                    'bills.view', 'bills.create', 'bills.update', 'bills.approve', 'bills.pay', 'bills.void',
                    'bills.duplicate', 'bills.export',
                    'bill-items.view', 'bill-items.create', 'bill-items.update', 'bill-items.delete',
                    
                    // Ledger (view only)
                    'ledger.view', 'ledger.journal.view', 'ledger.reports.view', 'ledger.trial-balance.view',
                    'ledger.balance-sheet.view', 'ledger.income-statement.view',
                    
                    // Reports
                    'reports.financial.view', 'reports.ar.view', 'reports.ap.view', 'reports.sales.view', 'reports.tax.view',
                    'reports.custom.view', 'reports.export',
                    
                    // Settings
                    'settings.view', 'settings.update', 'settings.company.view', 'settings.company.update',
                    'settings.billing.view', 'settings.integrations.view', 'settings.integrations.update',
                    
                    // Tax view/edit
                    'tax.view', 'tax.update', 'tax.rates.view', 'tax.rates.update', 'tax.reports.view',
                    
                    // Attachments
                    'attachments.view', 'attachments.upload', 'attachments.download', 'attachments.delete',
                    
                    // Notes
                    'notes.view', 'notes.create', 'notes.update', 'notes.delete',
                    
                    // API
                    'api.access', 'api.keys.create', 'api.keys.update', 'api.keys.delete',
                    
                    // Dashboard
                    'dashboard.view', 'dashboard.customize', 'widgets.create', 'widgets.update', 'widgets.delete',
                    
                    // Import/Export
                    'import.view', 'import.execute', 'export.view', 'export.execute',
                    
                    // Logs
                    'logs.view', 'monitoring.view', 'monitoring.alerts.view',
                ],
            ],
            'manager' => [
                'permissions' => [
                    // Company view only
                    'companies.view', 'companies.settings.view',
                    'companies.currencies.view', 'companies.currencies.exchange-rates.view',
                    
                    // User view and invite
                    'users.view', 'users.invite',
                    
                    // Customer/vendor management
                    'customers.view', 'customers.create', 'customers.update', 'customers.export',
                    'vendors.view', 'vendors.create', 'vendors.update', 'vendors.export',
                    
                    // Invoice operations
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'invoices.duplicate',
                    'invoice-items.view', 'invoice-items.create', 'invoice-items.update',
                    
                    // Payment operations
                    'payments.view', 'payments.create', 'payments.allocate', 'payments.refund', 'payments.export',
                    
                    // Bill operations
                    'bills.view', 'bills.create', 'bills.update', 'bills.approve', 'bills.pay', 'bills.export',
                    'bill-items.view', 'bill-items.create', 'bill-items.update',
                    
                    // Ledger view only
                    'ledger.view', 'ledger.journal.view', 'ledger.reports.view',
                    
                    // Reports
                    'reports.financial.view', 'reports.ar.view', 'reports.ap.view', 'reports.sales.view',
                    'reports.custom.view', 'reports.export',
                    
                    // Settings view only
                    'settings.view', 'settings.company.view',
                    
                    // Tax view only
                    'tax.view', 'tax.rates.view', 'tax.reports.view',
                    
                    // Attachments
                    'attachments.view', 'attachments.upload', 'attachments.download',
                    
                    // Notes
                    'notes.view', 'notes.create', 'notes.update',
                    
                    // Dashboard
                    'dashboard.view', 'widgets.create', 'widgets.update',
                    
                    // Import/Export view
                    'import.view', 'export.view', 'export.execute',
                ],
            ],
            'accountant' => [
                'permissions' => [
                    // Company view
                    'companies.view', 'companies.settings.view',
                    'companies.currencies.view', 'companies.currencies.exchange-rates.view',
                    
                    // Customer/vendor view
                    'customers.view', 'customers.export',
                    'vendors.view', 'vendors.export',
                    
                    // Invoice operations (no delete)
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.post', 'invoices.export',
                    'invoice-items.view', 'invoice-items.create', 'invoice-items.update',
                    
                    // Payment operations
                    'payments.view', 'payments.create', 'payments.allocate', 'payments.reconcile', 'payments.refund', 'payments.export',
                    
                    // Bill operations
                    'bills.view', 'bills.create', 'bills.update', 'bills.approve', 'bills.pay', 'bills.void', 'bills.export',
                    'bill-items.view', 'bill-items.create', 'bill-items.update',
                    
                    // Full ledger access
                    'ledger.view', 'ledger.entries.create', 'ledger.entries.update', 'ledger.entries.post',
                    'ledger.entries.void', 'ledger.journal.view', 'ledger.journal.create',
                    'ledger.reports.view', 'ledger.trial-balance.view', 'ledger.balance-sheet.view', 'ledger.income-statement.view',
                    
                    // All reports
                    'reports.financial.view', 'reports.ar.view', 'reports.ap.view', 'reports.sales.view', 'reports.tax.view',
                    'reports.custom.create', 'reports.custom.view', 'reports.export',
                    
                    // Tax management
                    'tax.view', 'tax.create', 'tax.update', 'tax.rates.view', 'tax.rates.create', 'tax.rates.update', 'tax.reports.view',
                    
                    // Attachments
                    'attachments.view', 'attachments.upload', 'attachments.download',
                    
                    // Notes
                    'notes.view', 'notes.create', 'notes.update',
                    
                    // Dashboard
                    'dashboard.view', 'widgets.create', 'widgets.update',
                    
                    // Import/Export
                    'import.view', 'import.execute', 'export.view', 'export.execute',
                    
                    // Logs
                    'logs.view',
                ],
            ],
            'employee' => [
                'permissions' => [
                    // Basic company view
                    'companies.view',
                    'companies.currencies.view',
                    
                    // Customer/vendor view
                    'customers.view',
                    'vendors.view',
                    
                    // Invoice operations (limited)
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send',
                    'invoice-items.view', 'invoice-items.create', 'invoice-items.update',
                    
                    // Payment view and create
                    'payments.view', 'payments.create',
                    
                    // Bill view and create
                    'bills.view', 'bills.create', 'bills.update',
                    'bill-items.view', 'bill-items.create',
                    
                    // Ledger view only
                    'ledger.view', 'ledger.journal.view',
                    
                    // Basic reports
                    'reports.ar.view', 'reports.ap.view', 'reports.sales.view',
                    'reports.custom.view',
                    
                    // Attachments
                    'attachments.view', 'attachments.upload', 'attachments.download',
                    
                    // Notes
                    'notes.view', 'notes.create',
                    
                    // Dashboard
                    'dashboard.view',
                ],
            ],
            'viewer' => [
                'permissions' => [
                    // Read-only access to most things
                    'companies.view', 'companies.currencies.view', 'companies.currencies.exchange-rates.view',
                    'users.view', 'customers.view', 'vendors.view',
                    'invoices.view', 'invoice-items.view',
                    'payments.view', 'bills.view', 'bill-items.view',
                    'ledger.view', 'ledger.journal.view',
                    'reports.financial.view', 'reports.ar.view', 'reports.ap.view', 'reports.sales.view',
                    'tax.view', 'tax.rates.view', 'tax.reports.view',
                    'attachments.view', 'attachments.download',
                    'notes.view', 'settings.view',
                    'dashboard.view',
                ],
            ],
        ];

        // Create system roles
        foreach ($systemRoles as $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleData['name'],
                'guard_name' => $roleData['guard_name'],
                'team_id' => $roleData['team_id'],
            ]);
            
            $role->givePermissionTo($roleData['permissions']);
        }

        // Create company roles (without team_id - will be set when assigned)
        foreach ($companyRoles as $roleName => $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => null, // Will be set when assigned to a company
            ]);
            
            $role->givePermissionTo($roleData['permissions']);
        }

        $this->command->info('RBAC seeder completed successfully!');
        $this->command->info('Created ' . count($permissions) . ' permissions');
        $this->command->info('Created 1 system role: super_admin');
        $this->command->info('Created 6 company roles: owner, admin, manager, accountant, employee, viewer');
    }
}
