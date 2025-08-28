<?php

namespace App\Support;

use App\Models\User;

class CommandBus
{
    public static function dispatch(string $action, array $params, User $user): array
    {
        return match ($action) {
            'user.create'      => app(\App\Actions\DevOps\UserCreate::class)->handle($params, $user),
            'user.delete'      => app(\App\Actions\DevOps\UserDelete::class)->handle($params, $user),
            'company.create'   => app(\App\Actions\DevOps\CompanyCreate::class)->handle($params, $user),
            'company.delete'   => app(\App\Actions\DevOps\CompanyDelete::class)->handle($params, $user),
            'company.assign'   => app(\App\Actions\DevOps\CompanyAssign::class)->handle($params, $user),
            'company.unassign' => app(\App\Actions\DevOps\CompanyUnassign::class)->handle($params, $user),
            default            => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }
}
