<?php

namespace App\Models;

use Spatie\Permission\Models as SpatieModels;

class Permission extends SpatieModels\Permission
{
    protected $table = 'public.permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
    ];
}
