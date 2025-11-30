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

    public const EXPENSE_CREATE = 'expense.create';
    public const EXPENSE_VIEW = 'expense.view';
    public const EXPENSE_UPDATE = 'expense.update';
    public const EXPENSE_DELETE = 'expense.delete';

    public const ACCOUNT_CREATE = 'account.create';
    public const ACCOUNT_VIEW = 'account.view';
    public const ACCOUNT_UPDATE = 'account.update';
    public const ACCOUNT_RECONCILE = 'account.reconcile';

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

    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}
