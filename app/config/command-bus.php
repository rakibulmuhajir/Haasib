<?php

return [
    'user.create' => App\Actions\DevOps\UserCreate::class,
    'user.update' => App\Actions\DevOps\UserUpdate::class,
    'user.delete' => App\Actions\DevOps\UserDelete::class,
    'user.activate' => App\Actions\User\ActivateUser::class,
    'user.deactivate' => App\Actions\User\DeactivateUser::class,
    'company.create' => App\Actions\DevOps\CompanyCreate::class,
    'company.activate' => App\Actions\Company\ActivateCompany::class,
    'company.deactivate' => App\Actions\Company\DeactivateCompany::class,
    'company.delete' => App\Actions\DevOps\CompanyDelete::class,
    'company.assign' => App\Actions\DevOps\CompanyAssign::class,
    'company.unassign' => App\Actions\DevOps\CompanyUnassign::class,
    'company.invite' => App\Actions\Company\CompanyInvite::class,
    'invitation.revoke' => App\Actions\Invitation\InvitationRevoke::class,
];
