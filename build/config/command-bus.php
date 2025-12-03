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

    // Payment
    'payment.create' => \App\Modules\Accounting\Actions\Payment\CreateAction::class,
    'payment.list' => \App\Modules\Accounting\Actions\Payment\IndexAction::class,
    'payment.void' => \App\Modules\Accounting\Actions\Payment\VoidAction::class,
];
