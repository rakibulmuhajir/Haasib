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
| Roles: owner, manager, accountant, operations, agent
|
| Run: php artisan app:sync-role-permissions
|
*/

return [

    'owner' => [
        // All permissions
        ...Permissions::all(),
    ],

    'manager' => [
        // Company
        'company.invite-user',
        'company.manage-users',
        'company.manage-roles',
        'company.delete-user',

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
        'payment.update',
        'payment.void',

        // Credit notes
        'credit_note.create',
        'credit_note.view',
        'credit_note.update',
        'credit_note.delete',
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

        // Umrah
        'umrah.agent.create',
        'umrah.agent.view',
        'umrah.agent.update',
        'umrah.agent.delete',
        'umrah.group.create',
        'umrah.group.view',
        'umrah.group.update',
        'umrah.group-accounting.view',
        'umrah.group-accounting.update',
        'umrah.payment.view',
        'umrah.payment.create',
        'umrah.payment.reverse',
        'umrah.payment.submit',
        'umrah.payment.approve',
        'umrah.refund.view',
        'umrah.refund.create',
        'umrah.refund.approve',
        'umrah.refund.cancel',
        'umrah.expense.view',
        'umrah.expense.create',
        'umrah.expense.reverse',
        'umrah.voucher.create',
        'umrah.voucher.view',
        'umrah.voucher-accounting.view',
        'umrah.voucher.approve',
        'umrah.voucher.update',
        'umrah.voucher.cancel',
        'umrah.vendor.create',
        'umrah.vendor.view',
        'umrah.vendor.update',
        'umrah.settings.update',
        'umrah.settings.delete',
        'umrah.report.view',

        // Ticketing: a manager runs the desk end to end, including
        // cancellations, which move money on both sides of the book.
        'umrah.ticket.view',
        'umrah.ticket.create',
        'umrah.ticket.update',
        'umrah.ticket.cancel',
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
        'payment.update',
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

        // Umrah
        'umrah.agent.view',
        'umrah.group.create',
        'umrah.group.view',
        'umrah.group.update',
        'umrah.group-accounting.view',
        'umrah.group-accounting.update',
        'umrah.payment.view',
        'umrah.payment.create',
        'umrah.payment.reverse',
        'umrah.payment.submit',
        'umrah.payment.approve',
        'umrah.refund.view',
        'umrah.refund.create',
        'umrah.refund.approve',
        'umrah.refund.cancel',
        'umrah.expense.view',
        'umrah.expense.create',
        'umrah.expense.reverse',
        'umrah.voucher.create',
        'umrah.voucher.view',
        'umrah.voucher-accounting.view',
        'umrah.voucher.approve',
        'umrah.voucher.update',
        'umrah.voucher.cancel',
        'umrah.vendor.view',
        'umrah.report.view',

        // Ticketing: an accountant reads the register and reconciles it,
        // but never books one -- booking is an operational act, not a
        // financial one. Cancellation is theirs because it is a credit
        // note / vendor credit posting, the same kind of ledger event
        // as the rest of this role's remit.
        'umrah.ticket.view',
        'umrah.ticket.cancel',
    ],

    'operations' => [
        // Operational entry only. Deliberately excludes accounts, payments,
        // expenses, reports, profitability, and all *-accounting permissions.
        // Refunds are the documented exception: operations may request one
        // (refunds.md's permissions table), but never approve or cancel.
        'umrah.group.create',
        'umrah.group.view',
        'umrah.group.update',
        'umrah.voucher.create',
        'umrah.voucher.view',
        'umrah.voucher.update',
        'umrah.refund.view',
        'umrah.refund.create',

        // Ticketing: the booking clerk's job -- raise and amend a
        // booking -- but cancellation is deliberately withheld here too,
        // for the same reason the rest of this role excludes accounts
        // and approvals: it moves money.
        'umrah.ticket.view',
        'umrah.ticket.create',
        'umrah.ticket.update',
    ],

    'agent' => [
        // Umrah agent self-service only. Runtime ownership checks further scope records.
        'umrah.group.create',
        'umrah.group.view',
        'umrah.group.update',
        'umrah.payment.view',
        'umrah.payment.submit',
        'umrah.voucher.create',
        'umrah.voucher.view',
        'umrah.voucher.approve',
        'umrah.voucher.update',
        'umrah.report.own.view',
        'umrah.refund.view',
        'umrah.refund.create',

        // Ticketing: an agent sees only the bookings sold under their own
        // name -- never the full register, never a supplier cost, and
        // never able to create, edit or cancel one directly.
        'umrah.ticket.own.view',
    ],

];
