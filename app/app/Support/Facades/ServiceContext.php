<?php

namespace App\Support\Facades;

use App\Support\ServiceContext;
use App\Support\ServiceContextHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ServiceContext fromRequest(Request $request, ?string $companyId = null)
 * @method static ServiceContext forSystem(string $companyId, ?string $idempotencyKey = null)
 * @method static ServiceContext forUser(\App\Models\User $user, ?string $companyId = null, ?string $idempotencyKey = null)
 * @method static ServiceContext forJob(?string $userId = null, ?string $companyId = null, ?string $idempotencyKey = null)
 */
class ServiceContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServiceContextHelper::class;
    }
}
