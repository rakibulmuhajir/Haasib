<?php

use App\Constants\Permissions;

/*
|--------------------------------------------------------------------------
| Role-Permission Matrix
|--------------------------------------------------------------------------
|
| Defines which permissions each role gets.
| This matrix is applied PER COMPANY when roles are synced.
|
| Roles: owner, accountant, viewer
|
| Run: php artisan app:sync-role-permissions
|
*/

return [

    'owner' => [
        // All permissions
        ...Permissions::all(),
    ],

    'admin' => [
        // Company
        'company.invite-user',
        'company.manage-users',
        'company.manage-roles',

        // Customer
        'customer.create',
        'customer.view',
        'customer.update',
        'customer.delete',

        // Invoice
        'invoice.create',
        'invoice.view',
        'invoice.update',
        'invoice.send',
        'invoice.void',

        // Payment
        'payment.create',
        'payment.view',
        'payment.void',

        // Credit notes
        'credit_note.create',
        'credit_note.view',
        'credit_note.update',
        'credit_note.apply',
        'credit_note.void',

        // AP / GL
        'account.create',
        'account.view',
        'account.update',
        'account.delete',
        'journal.create',
        'journal.view',
        'posting_template.create',
        'posting_template.view',
        'posting_template.update',
        'posting_template.delete',
        'bill.create',
        'bill.view',
        'bill.update',
        'bill.delete',
        'bill.pay',
        'bill.void',
        'vendor.create',
        'vendor.view',
        'vendor.update',
        'vendor.delete',
        'vendor_credit.create',
        'vendor_credit.view',
        'vendor_credit.apply',
        'vendor_credit.void',

        // Tax Management
        'tax.manage',
        'tax.view',
        'tax.settings.update',
        'tax.rate.create',
        'tax.rate.update',
        'tax.rate.delete',
        'tax.group.create',
        'tax.group.update',
        'tax.group.delete',
        'tax.registration.create',
        'tax.registration.update',
        'tax.registration.delete',
        'tax.exemption.create',
        'tax.exemption.update',
        'tax.exemption.delete',
        'tax.calculate',

        // Inventory - Items
        'item.create',
        'item.view',
        'item.update',
        'item.delete',

        // Inventory - Categories
        'item_category.create',
        'item_category.view',
        'item_category.update',
        'item_category.delete',

        // Inventory - Warehouses
        'warehouse.create',
        'warehouse.view',
        'warehouse.update',
        'warehouse.delete',

        // Inventory - Stock
        'stock.view',
        'stock.adjust',
        'stock.transfer',
        'stock.count',

        // Payroll - Employees
        'employee.create',
        'employee.view',
        'employee.update',
        'employee.delete',

        // Payroll - Settings
        'payroll.settings.view',
        'payroll.settings.update',

        // Payroll - Leave
        'leave_request.create',
        'leave_request.view',
        'leave_request.update',
        'leave_request.approve',
        'leave_request.delete',

        // Payroll - Runs & Payslips
        'payroll_run.create',
        'payroll_run.view',
        'payroll_run.close',
        'payroll_run.delete',
        'payslip.create',
        'payslip.view',
        'payslip.approve',
        'payslip.pay',
        'payslip.delete',

        // Banking - Bank Accounts
        'bank_account.create',
        'bank_account.view',
        'bank_account.update',
        'bank_account.delete',

        // Banking - Bank Transactions
        'bank_transaction.view',
        'bank_transaction.create',
        'bank_transaction.import',

        // Banking - Bank Feed
        'bank_feed.view',
        'bank_feed.resolve',

        // Banking - Reconciliation
        'bank_reconciliation.create',
        'bank_reconciliation.view',
        'bank_reconciliation.complete',
        'bank_reconciliation.cancel',

        // Banking - Bank Rules
        'bank_rule.create',
        'bank_rule.view',
        'bank_rule.update',
        'bank_rule.delete',

        // Fuel Station - Pumps
        'pump.create',
        'pump.view',
        'pump.update',
        'pump.delete',

        // Fuel Station - Tank Readings
        'tank_reading.create',
        'tank_reading.view',
        'tank_reading.update',

        // Fuel Station - Pump Readings
        'pump_reading.create',
        'pump_reading.view',

        // Fuel Station - Rate Management
        'fuel_rate.update',
        'fuel_product.setup',

        // Fuel Station - Investors
        'investor.create',
        'investor.view',
        'investor.update',

        // Fuel Station - Handovers
        'handover.create',
        'handover.view',

        // Fuel Station - Amanat
        'amanat.deposit',
        'amanat.withdraw',

        // Fuel Station - Sales
        'fuel_sale.create',

        // Fuel Station - Daily Close (admin can amend/lock)
        'daily_close.create',
        'daily_close.view',
        'daily_close.amend',
        'daily_close.lock',
    ],

    'accountant' => [
        // Customer
        'customer.create',
        'customer.view',
        'customer.update',

        // Invoice
        'invoice.create',
        'invoice.view',
        'invoice.update',
        'invoice.send',
        'invoice.void',

        // Payment
        'payment.create',
        'payment.view',
        'payment.void',

        // Credit notes
        'credit_note.create',
        'credit_note.view',
        'credit_note.update',
        'credit_note.apply',
        'credit_note.void',

        // AP / GL
        'account.create',
        'account.view',
        'account.update',
        'journal.create',
        'journal.view',
        'posting_template.view',
        'posting_template.update',
        'bill.create',
        'bill.view',
        'bill.update',
        'bill.pay',
        'vendor.create',
        'vendor.view',
        'vendor.update',
        'vendor_credit.create',
        'vendor_credit.view',
        'vendor_credit.apply',

        // Tax Management
        'tax.manage',
        'tax.view',
        'tax.settings.update',
        'tax.rate.create',
        'tax.rate.update',
        'tax.rate.delete',
        'tax.group.create',
        'tax.group.update',
        'tax.group.delete',
        'tax.registration.create',
        'tax.registration.update',
        'tax.registration.delete',
        'tax.exemption.create',
        'tax.exemption.update',
        'tax.exemption.delete',
        'tax.calculate',

        // Inventory - Items
        'item.create',
        'item.view',
        'item.update',

        // Inventory - Categories
        'item_category.create',
        'item_category.view',
        'item_category.update',

        // Inventory - Warehouses
        'warehouse.view',

        // Inventory - Stock
        'stock.view',
        'stock.adjust',
        'stock.transfer',

        // Payroll - Employees
        'employee.create',
        'employee.view',
        'employee.update',

        // Payroll - Settings
        'payroll.settings.view',
        'payroll.settings.update',

        // Payroll - Leave
        'leave_request.create',
        'leave_request.view',
        'leave_request.update',
        'leave_request.approve',

        // Payroll - Runs & Payslips
        'payroll_run.create',
        'payroll_run.view',
        'payroll_run.close',
        'payslip.create',
        'payslip.view',
        'payslip.approve',
        'payslip.pay',

        // Banking - Bank Accounts
        'bank_account.create',
        'bank_account.view',
        'bank_account.update',

        // Banking - Bank Transactions
        'bank_transaction.view',
        'bank_transaction.create',
        'bank_transaction.import',

        // Banking - Bank Feed
        'bank_feed.view',
        'bank_feed.resolve',

        // Banking - Reconciliation
        'bank_reconciliation.create',
        'bank_reconciliation.view',
        'bank_reconciliation.complete',
        'bank_reconciliation.cancel',

        // Banking - Bank Rules
        'bank_rule.create',
        'bank_rule.view',
        'bank_rule.update',

        // Fuel Station - Pumps (view only)
        'pump.view',

        // Fuel Station - Tank Readings
        'tank_reading.create',
        'tank_reading.view',
        'tank_reading.update',

        // Fuel Station - Pump Readings
        'pump_reading.create',
        'pump_reading.view',

        // Fuel Station - Rate Management
        'fuel_rate.update',

        // Fuel Station - Investors (view only)
        'investor.view',

        // Fuel Station - Handovers
        'handover.create',
        'handover.view',

        // Fuel Station - Amanat
        'amanat.deposit',
        'amanat.withdraw',

        // Fuel Station - Sales
        'fuel_sale.create',

        // Fuel Station - Daily Close (accountant can create/view only)
        'daily_close.create',
        'daily_close.view',
    ],

    'member' => [
        // Customer (read only)
        'customer.view',

        // Invoice (read only)
        'invoice.view',

        // Payment (read only)
        'payment.view',

        // AP/GL read-only
        'account.view',
        'journal.view',
        'bill.view',
        'vendor.view',
        'vendor_credit.view',

        // Tax Management (read only)
        'tax.view',

        // Inventory (read only)
        'item.view',
        'item_category.view',
        'warehouse.view',
        'stock.view',

        // Payroll (read only)
        'employee.view',
        'payroll.settings.view',
        'leave_request.view',
        'payroll_run.view',
        'payslip.view',

        // Banking (read only)
        'bank_account.view',
        'bank_transaction.view',
        'bank_feed.view',
        'bank_reconciliation.view',
        'bank_rule.view',

        // Fuel Station (read only)
        'pump.view',
        'tank_reading.view',
        'pump_reading.view',
        'investor.view',
        'handover.view',

        // Fuel Station - Daily Close (view only)
        'daily_close.view',
    ],

];
