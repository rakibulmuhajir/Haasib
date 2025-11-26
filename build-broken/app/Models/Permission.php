<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models as SpatieModels;

class Permission extends SpatieModels\Permission
{
    use HasUuids;

    protected $table = 'public.permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
    ];
}
