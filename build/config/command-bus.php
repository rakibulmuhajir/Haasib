<?php

return [
    'company.create' => \App\Modules\Accounting\Actions\Company\CreateAction::class,
    'company.list' => \App\Modules\Accounting\Actions\Company\IndexAction::class,
    'company.switch' => \App\Modules\Accounting\Actions\Company\SwitchAction::class,
    'company.view' => \App\Modules\Accounting\Actions\Company\ViewAction::class,
    'company.delete' => \App\Modules\Accounting\Actions\Company\DeleteAction::class,

    'user.invite' => \App\Modules\Accounting\Actions\User\InviteAction::class,
    'user.list' => \App\Modules\Accounting\Actions\User\IndexAction::class,
    'user.assign-role' => \App\Modules\Accounting\Actions\User\AssignRoleAction::class,
    'user.remove-role' => \App\Modules\Accounting\Actions\User\RemoveRoleAction::class,
    'user.deactivate' => \App\Modules\Accounting\Actions\User\DeactivateAction::class,
    'user.delete' => \App\Modules\Accounting\Actions\User\DeleteAction::class,

    'role.list' => \App\Modules\Accounting\Actions\Role\IndexAction::class,
    'role.assign' => \App\Modules\Accounting\Actions\Role\AssignPermissionAction::class,
    'role.revoke' => \App\Modules\Accounting\Actions\Role\RevokePermissionAction::class,

    // Customer
    'customer.create' => \App\Modules\Accounting\Actions\Customer\CreateAction::class,
    'customer.list' => \App\Modules\Accounting\Actions\Customer\IndexAction::class,
    'customer.view' => \App\Modules\Accounting\Actions\Customer\ViewAction::class,
    'customer.update' => \App\Modules\Accounting\Actions\Customer\UpdateAction::class,
    'customer.delete' => \App\Modules\Accounting\Actions\Customer\DeleteAction::class,
    'customer.restore' => \App\Modules\Accounting\Actions\Customer\RestoreAction::class,

    // Invoice
    'invoice.create' => \App\Modules\Accounting\Actions\Invoice\CreateAction::class,
    'invoice.list' => \App\Modules\Accounting\Actions\Invoice\IndexAction::class,
    'invoice.view' => \App\Modules\Accounting\Actions\Invoice\ViewAction::class,
    'invoice.send' => \App\Modules\Accounting\Actions\Invoice\SendAction::class,
    'invoice.void' => \App\Modules\Accounting\Actions\Invoice\VoidAction::class,
    'invoice.duplicate' => \App\Modules\Accounting\Actions\Invoice\DuplicateAction::class,
    'invoice.update' => \App\Modules\Accounting\Actions\Invoice\UpdateAction::class,
    'invoice.delete' => \App\Modules\Accounting\Actions\Invoice\DeleteAction::class,

    // Payment
    'payment.create' => \App\Modules\Accounting\Actions\Payment\CreateAction::class,
    'payment.list' => \App\Modules\Accounting\Actions\Payment\IndexAction::class,
    'payment.void' => \App\Modules\Accounting\Actions\Payment\VoidAction::class,
    'payment.update' => \App\Modules\Accounting\Actions\Payment\UpdateAction::class,
    'payment.delete' => \App\Modules\Accounting\Actions\Payment\DeleteAction::class,

    // Credit Note
    'credit_note.create' => \App\Modules\Accounting\Actions\CreditNote\CreateAction::class,
    'credit_note.list' => \App\Modules\Accounting\Actions\CreditNote\IndexAction::class,
    'credit_note.update' => \App\Modules\Accounting\Actions\CreditNote\UpdateAction::class,
    'credit_note.delete' => \App\Modules\Accounting\Actions\CreditNote\DeleteAction::class,
    'credit_note.apply' => \App\Modules\Accounting\Actions\CreditNote\ApplyAction::class,
    'credit_note.void' => \App\Modules\Accounting\Actions\CreditNote\VoidAction::class,

    // Account
    'account.list' => \App\Modules\Accounting\Actions\Account\IndexAction::class,
    'account.view' => \App\Modules\Accounting\Actions\Account\ViewAction::class,
    'account.create' => \App\Modules\Accounting\Actions\Account\CreateAction::class,
    'account.update' => \App\Modules\Accounting\Actions\Account\UpdateAction::class,
    'account.delete' => \App\Modules\Accounting\Actions\Account\DeleteAction::class,
    'account.toggle-active' => \App\Modules\Accounting\Actions\Account\ToggleActiveAction::class,

    // Vendor
    'vendor.list' => \App\Modules\Accounting\Actions\Vendor\IndexAction::class,
    'vendor.view' => \App\Modules\Accounting\Actions\Vendor\ViewAction::class,
    'vendor.create' => \App\Modules\Accounting\Actions\Vendor\CreateAction::class,
    'vendor.update' => \App\Modules\Accounting\Actions\Vendor\UpdateAction::class,
    'vendor.delete' => \App\Modules\Accounting\Actions\Vendor\DeleteAction::class,

    // Bill
    'bill.list' => \App\Modules\Accounting\Actions\Bill\IndexAction::class,
    'bill.view' => \App\Modules\Accounting\Actions\Bill\ViewAction::class,
    'bill.create' => \App\Modules\Accounting\Actions\Bill\CreateAction::class,
    'bill.update' => \App\Modules\Accounting\Actions\Bill\UpdateAction::class,
    'bill.delete' => \App\Modules\Accounting\Actions\Bill\DeleteAction::class,
    'bill.void' => \App\Modules\Accounting\Actions\Bill\VoidAction::class,
    'bill.receive' => \App\Modules\Accounting\Actions\Bill\ReceiveAction::class,

    // Bill Payment
    'bill_payment.list' => \App\Modules\Accounting\Actions\BillPayment\IndexAction::class,
    'bill_payment.create' => \App\Modules\Accounting\Actions\BillPayment\CreateAction::class,
    'bill_payment.void' => \App\Modules\Accounting\Actions\BillPayment\VoidAction::class,

    // Vendor Credit
    'vendor_credit.list' => \App\Modules\Accounting\Actions\VendorCredit\IndexAction::class,
    'vendor_credit.create' => \App\Modules\Accounting\Actions\VendorCredit\CreateAction::class,
    'vendor_credit.apply' => \App\Modules\Accounting\Actions\VendorCredit\ApplyAction::class,
    'vendor_credit.void' => \App\Modules\Accounting\Actions\VendorCredit\VoidAction::class,
];
