<?php

namespace App\Constants;

class Permissions
{
    public const INVOICE_CREATE = 'invoice.create';
    public const INVOICE_VIEW = 'invoice.view';
    public const INVOICE_UPDATE = 'invoice.update';
    public const INVOICE_DELETE = 'invoice.delete';
    public const INVOICE_SEND = 'invoice.send';
    public const INVOICE_VOID = 'invoice.void';

    public const PAYMENT_CREATE = 'payment.create';
    public const PAYMENT_VIEW = 'payment.view';
    public const PAYMENT_DELETE = 'payment.delete';
    public const PAYMENT_VOID = 'payment.void';

    public const CREDIT_NOTE_CREATE = 'credit_note.create';
    public const CREDIT_NOTE_VIEW = 'credit_note.view';
    public const CREDIT_NOTE_UPDATE = 'credit_note.update';
    public const CREDIT_NOTE_APPLY = 'credit_note.apply';
    public const CREDIT_NOTE_VOID = 'credit_note.void';

    public const CUSTOMER_CREATE = 'customer.create';
    public const CUSTOMER_VIEW = 'customer.view';
    public const CUSTOMER_UPDATE = 'customer.update';
    public const CUSTOMER_DELETE = 'customer.delete';

    public const VENDOR_CREATE = 'vendor.create';
    public const VENDOR_VIEW = 'vendor.view';
    public const VENDOR_UPDATE = 'vendor.update';
    public const VENDOR_DELETE = 'vendor.delete';

    public const BILL_CREATE = 'bill.create';
    public const BILL_VIEW = 'bill.view';
    public const BILL_PAY = 'bill.pay';
    public const BILL_VOID = 'bill.void';
    public const BILL_UPDATE = 'bill.update';
    public const BILL_DELETE = 'bill.delete';

    public const VENDOR_CREDIT_CREATE = 'vendor_credit.create';
    public const VENDOR_CREDIT_VIEW = 'vendor_credit.view';
    public const VENDOR_CREDIT_APPLY = 'vendor_credit.apply';
    public const VENDOR_CREDIT_VOID = 'vendor_credit.void';

    public const EXPENSE_CREATE = 'expense.create';
    public const EXPENSE_VIEW = 'expense.view';
    public const EXPENSE_UPDATE = 'expense.update';
    public const EXPENSE_DELETE = 'expense.delete';

    public const ACCOUNT_CREATE = 'account.create';
    public const ACCOUNT_VIEW = 'account.view';
    public const ACCOUNT_UPDATE = 'account.update';
    public const ACCOUNT_RECONCILE = 'account.reconcile';
    public const ACCOUNT_DELETE = 'account.delete';

    public const JOURNAL_CREATE = 'journal.create';
    public const JOURNAL_VIEW = 'journal.view';

    public const REPORT_VIEW = 'report.view';
    public const REPORT_EXPORT = 'report.export';

    public const COMPANY_CREATE = 'company.create';
    public const COMPANY_VIEW = 'company.view';
    public const COMPANY_UPDATE = 'company.update';
    public const COMPANY_DELETE = 'company.delete';
    public const COMPANY_INVITE_USER = 'company.invite-user';
    public const COMPANY_MANAGE_USERS = 'company.manage-users';
    public const COMPANY_DELETE_USER = 'company.delete-user';
    public const COMPANY_MANAGE_ROLES = 'company.manage-roles';

    // Tax Management permissions
    public const TAX_MANAGE = 'tax.manage';
    public const TAX_VIEW = 'tax.view';
    public const TAX_SETTINGS_UPDATE = 'tax.settings.update';
    public const TAX_RATE_CREATE = 'tax.rate.create';
    public const TAX_RATE_UPDATE = 'tax.rate.update';
    public const TAX_RATE_DELETE = 'tax.rate.delete';
    public const TAX_GROUP_CREATE = 'tax.group.create';
    public const TAX_GROUP_UPDATE = 'tax.group.update';
    public const TAX_GROUP_DELETE = 'tax.group.delete';
    public const TAX_REGISTRATION_CREATE = 'tax.registration.create';
    public const TAX_REGISTRATION_UPDATE = 'tax.registration.update';
    public const TAX_REGISTRATION_DELETE = 'tax.registration.delete';
    public const TAX_EXEMPTION_CREATE = 'tax.exemption.create';
    public const TAX_EXEMPTION_UPDATE = 'tax.exemption.update';
    public const TAX_EXEMPTION_DELETE = 'tax.exemption.delete';
    public const TAX_CALCULATE = 'tax.calculate';

    // Inventory - Items
    public const ITEM_CREATE = 'item.create';
    public const ITEM_VIEW = 'item.view';
    public const ITEM_UPDATE = 'item.update';
    public const ITEM_DELETE = 'item.delete';

    // Inventory - Item Categories
    public const ITEM_CATEGORY_CREATE = 'item_category.create';
    public const ITEM_CATEGORY_VIEW = 'item_category.view';
    public const ITEM_CATEGORY_UPDATE = 'item_category.update';
    public const ITEM_CATEGORY_DELETE = 'item_category.delete';

    // Inventory - Warehouses
    public const WAREHOUSE_CREATE = 'warehouse.create';
    public const WAREHOUSE_VIEW = 'warehouse.view';
    public const WAREHOUSE_UPDATE = 'warehouse.update';
    public const WAREHOUSE_DELETE = 'warehouse.delete';

    // Inventory - Stock
    public const STOCK_VIEW = 'stock.view';
    public const STOCK_ADJUST = 'stock.adjust';
    public const STOCK_TRANSFER = 'stock.transfer';
    public const STOCK_COUNT = 'stock.count';

    // Payroll - Employees
    public const EMPLOYEE_CREATE = 'employee.create';
    public const EMPLOYEE_VIEW = 'employee.view';
    public const EMPLOYEE_UPDATE = 'employee.update';
    public const EMPLOYEE_DELETE = 'employee.delete';

    // Payroll - Settings (earning types, deduction types, leave types, benefit plans)
    public const PAYROLL_SETTINGS_VIEW = 'payroll.settings.view';
    public const PAYROLL_SETTINGS_UPDATE = 'payroll.settings.update';

    // Payroll - Leave Requests
    public const LEAVE_REQUEST_CREATE = 'leave_request.create';
    public const LEAVE_REQUEST_VIEW = 'leave_request.view';
    public const LEAVE_REQUEST_UPDATE = 'leave_request.update';
    public const LEAVE_REQUEST_APPROVE = 'leave_request.approve';
    public const LEAVE_REQUEST_DELETE = 'leave_request.delete';

    // Payroll - Payroll Runs & Periods
    public const PAYROLL_RUN_CREATE = 'payroll_run.create';
    public const PAYROLL_RUN_VIEW = 'payroll_run.view';
    public const PAYROLL_RUN_CLOSE = 'payroll_run.close';
    public const PAYROLL_RUN_DELETE = 'payroll_run.delete';

    // Payroll - Payslips
    public const PAYSLIP_CREATE = 'payslip.create';
    public const PAYSLIP_VIEW = 'payslip.view';
    public const PAYSLIP_APPROVE = 'payslip.approve';
    public const PAYSLIP_PAY = 'payslip.pay';
    public const PAYSLIP_DELETE = 'payslip.delete';

    // Banking - Bank Accounts
    public const BANK_ACCOUNT_CREATE = 'bank_account.create';
    public const BANK_ACCOUNT_VIEW = 'bank_account.view';
    public const BANK_ACCOUNT_UPDATE = 'bank_account.update';
    public const BANK_ACCOUNT_DELETE = 'bank_account.delete';

    // Banking - Bank Transactions
    public const BANK_TRANSACTION_VIEW = 'bank_transaction.view';
    public const BANK_TRANSACTION_CREATE = 'bank_transaction.create';
    public const BANK_TRANSACTION_IMPORT = 'bank_transaction.import';

    // Banking - Bank Feed (Resolution Engine)
    public const BANK_FEED_VIEW = 'bank_feed.view';
    public const BANK_FEED_RESOLVE = 'bank_feed.resolve';

    // Banking - Bank Reconciliation
    public const BANK_RECONCILIATION_CREATE = 'bank_reconciliation.create';
    public const BANK_RECONCILIATION_VIEW = 'bank_reconciliation.view';
    public const BANK_RECONCILIATION_COMPLETE = 'bank_reconciliation.complete';
    public const BANK_RECONCILIATION_CANCEL = 'bank_reconciliation.cancel';

    // Banking - Bank Rules
    public const BANK_RULE_CREATE = 'bank_rule.create';
    public const BANK_RULE_VIEW = 'bank_rule.view';
    public const BANK_RULE_UPDATE = 'bank_rule.update';
    public const BANK_RULE_DELETE = 'bank_rule.delete';

    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}
