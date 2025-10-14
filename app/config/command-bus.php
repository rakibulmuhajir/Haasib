<?php

return [
    'user.create' => App\Actions\DevOps\UserCreate::class,
    'user.update' => App\Actions\DevOps\UserUpdate::class,
    'user.delete' => App\Actions\DevOps\UserDelete::class,
    'user.activate' => App\Actions\User\ActivateUser::class,
    'user.deactivate' => App\Actions\User\DeactivateUser::class,
    // Customer actions
    'customer.create' => App\Actions\DevOps\CustomerCreate::class,
    'customer.update' => App\Actions\DevOps\CustomerUpdate::class,
    'customer.delete' => App\Actions\DevOps\CustomerDelete::class,
    // Invoice actions
    'invoice.create' => App\Actions\DevOps\InvoiceCreate::class,
    'invoice.update' => App\Actions\DevOps\InvoiceUpdate::class,
    'invoice.delete' => App\Actions\DevOps\InvoiceDelete::class,
    'invoice.post' => App\Actions\DevOps\InvoicePost::class,
    'invoice.cancel' => App\Actions\DevOps\InvoiceCancel::class,
    'company.create' => App\Actions\DevOps\CompanyCreate::class,
    'company.activate' => App\Actions\Company\ActivateCompany::class,
    'company.deactivate' => App\Actions\Company\DeactivateCompany::class,
    'company.delete' => App\Actions\DevOps\CompanyDelete::class,
    'company.assign' => App\Actions\DevOps\CompanyAssign::class,
    'company.update_role' => App\Actions\DevOps\CompanyUpdateRole::class,
    'company.unassign' => App\Actions\DevOps\CompanyUnassign::class,
    'company.invite' => App\Actions\Company\CompanyInvite::class,
    'invitation.revoke' => App\Actions\Invitation\InvitationRevoke::class,
    
    // Payment actions
    'payment.create' => Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction::class,
    'payment.allocate' => Modules\Accounting\Domain\Payments\Actions\AllocatePaymentAction::class,
    'payment.allocate.auto' => Modules\Accounting\Domain\Payments\Actions\AutoAllocatePaymentAction::class,
    'payment.reverse' => Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction::class,
    'payment.allocation.reverse' => Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction::class,
    
    // Batch actions
    'payment.batch.create' => Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction::class,
    'payment.batch.process' => Modules\Accounting\Domain\Payments\Actions\ProcessPaymentBatchAction::class,
];
