<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class ServiceContextHelper
{
    /**
     * Create ServiceContext from current HTTP request
     */
    public static function fromRequest(Request $request, ?string $companyId = null): ServiceContext
    {
        $user = $request->user();

        return new ServiceContext(
            actingUser: $user,
            companyId: $companyId ?? $user?->current_company_id,
            idempotencyKey: $request->header('Idempotency-Key')
        );
    }

    /**
     * Create ServiceContext for system operations (no acting user)
     */
    public static function forSystem(string $companyId, ?string $idempotencyKey = null): ServiceContext
    {
        return new ServiceContext(
            actingUser: null,
            companyId: $companyId,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Create ServiceContext for a specific user
     */
    public static function forUser(User $user, ?string $companyId = null, ?string $idempotencyKey = null): ServiceContext
    {
        return new ServiceContext(
            actingUser: $user,
            companyId: $companyId ?? $user->current_company_id,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Create ServiceContext with minimal info for background jobs
     */
    public static function forJob(?string $userId = null, ?string $companyId = null, ?string $idempotencyKey = null): ServiceContext
    {
        $user = $userId ? User::find($userId) : null;

        return new ServiceContext(
            actingUser: $user,
            companyId: $companyId,
            idempotencyKey: $idempotencyKey
        );
    }
}
