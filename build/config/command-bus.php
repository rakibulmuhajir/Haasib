<?php

return [
    'company.create' => \App\Actions\Company\CreateAction::class,
    'company.list' => \App\Actions\Company\IndexAction::class,
    'company.switch' => \App\Actions\Company\SwitchAction::class,
    'company.view' => \App\Actions\Company\ViewAction::class,
    'company.delete' => \App\Actions\Company\DeleteAction::class,

    'user.invite' => \App\Actions\User\InviteAction::class,
    'user.list' => \App\Actions\User\IndexAction::class,
    'user.assign-role' => \App\Actions\User\AssignRoleAction::class,
    'user.remove-role' => \App\Actions\User\RemoveRoleAction::class,
    'user.deactivate' => \App\Actions\User\DeactivateAction::class,
    'user.delete' => \App\Actions\User\DeleteAction::class,

    'role.list' => \App\Actions\Role\IndexAction::class,
    'role.assign' => \App\Actions\Role\AssignPermissionAction::class,
    'role.revoke' => \App\Actions\Role\RevokePermissionAction::class,
];
